<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Provedor de embeddings vetoriais para o pipeline RAG.
 *
 * Suporta dois backends:
 *   - Gemini  → text-embedding-004 (768 dims, free tier, mesmo key do LLM)
 *   - OpenAI  → text-embedding-3-small (1536 dims, pago por token)
 *
 * Seleção via config('services.embedding.provider', 'gemini').
 *
 * Comportamento de falha: em vez de lançar exceção, retorna [] e loga o erro.
 * Isso permite que RAGRetrievalService caia para keyword search graciosamente.
 *
 * Pattern de código: idêntico ao GeminiProvider (cURL nativo, sem Composer).
 */
class EmbeddingProvider
{
    private string $provider;
    private string $geminiKey;
    private string $openaiKey;
    private ?string $tenantId;

    public function __construct(?string $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $preferred = config('services.embedding.provider', 'gemini');

        // Resolve keys from DB (AiApiKey) first, then fall back to config/.env
        $this->geminiKey = $this->resolveKey('gemini');
        $this->openaiKey = $this->resolveKey('openai');

        // Use preferred provider if it has a key, otherwise auto-detect
        if ($preferred === 'openai' && !empty($this->openaiKey)) {
            $this->provider = 'openai';
        } elseif ($preferred === 'gemini' && !empty($this->geminiKey)) {
            $this->provider = 'gemini';
        } elseif (!empty($this->geminiKey)) {
            $this->provider = 'gemini';
        } elseif (!empty($this->openaiKey)) {
            $this->provider = 'openai';
        } else {
            $this->provider = $preferred; // will fail gracefully with empty key
        }

        error_log(sprintf('[EmbeddingProvider] provider=%s tenant=%s hasGemini=%s hasOpenAI=%s',
            $this->provider, $tenantId ?? 'null',
            empty($this->geminiKey) ? 'no' : 'yes',
            empty($this->openaiKey) ? 'no' : 'yes'
        ));
    }

    /**
     * Resolve API key: DB (tenant → global) → .env fallback.
     */
    private function resolveKey(string $provider): string
    {
        // Try DB resolution via AiApiKey model
        try {
            $key = \App\Models\AiApiKey::getDecryptedKey($provider, $this->tenantId);
            if (!empty($key)) {
                return $key;
            }
        } catch (\Throwable $e) {
            error_log('[EmbeddingProvider] AiApiKey lookup failed for ' . $provider . ': ' . $e->getMessage());
        }

        // Fallback to config/.env
        $configKey = config("services.{$provider}.key", '');
        return is_string($configKey) ? $configKey : '';
    }

    /**
     * Gera embedding para um único texto.
     * Retorna float[] ou [] em caso de falha (nunca lança exceção no fluxo normal).
     *
     * @return float[]
     */
    public function embed(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Trunca textos muito longos para evitar erros de limite de tokens
        // Gemini embedding-004: ~2048 tokens; conservador: 1500 words ≈ 2000 tokens
        $text = $this->truncateToWords($text, 1500);

        try {
            return $this->provider === 'openai'
                ? $this->embedViaOpenAI($text)
                : $this->embedViaGemini($text);
        } catch (\Throwable $e) {
            error_log('[EmbeddingProvider] embed() falhou: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gera embeddings para um array de textos.
     * Processa um por um com retry (máx 2 tentativas, pausa 1s).
     * Preserva a ordem — índices que falharem retornam [] no resultado.
     *
     * @param  string[] $texts
     * @return float[][]
     */
    public function embedBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $i => $text) {
            $vector = [];

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $vector = $this->embed($text);
                if (!empty($vector)) break;

                if ($attempt < 2) {
                    sleep(1); // pausa antes de retry
                }
            }

            $results[$i] = $vector;
            error_log(sprintf(
                '[EmbeddingProvider] Chunk %d/%d: %s (%d dims)',
                $i + 1,
                count($texts),
                empty($vector) ? 'FALHOU' : 'ok',
                count($vector)
            ));
        }

        return $results;
    }

    /**
     * Dimensão do vetor gerado pelo provider atual.
     */
    public function getDimensions(): int
    {
        return $this->provider === 'openai' ? 1536 : 768;
    }

    /**
     * Nome do modelo de embedding em uso.
     */
    public function getModel(): string
    {
        return $this->provider === 'openai'
            ? config('services.embedding.openai_model', 'text-embedding-3-small')
            : config('services.embedding.gemini_model', 'text-embedding-004');
    }

    // ─── Backends ──────────────────────────────────────────────────

    /**
     * Gemini Embedding API.
     * Endpoint: https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent
     * Retorna 768 floats em $response['embedding']['values'].
     *
     * @return float[]
     */
    private function embedViaGemini(string $text): array
    {
        if (empty($this->geminiKey)) {
            error_log('[EmbeddingProvider] GEMINI_API_KEY não configurada — embedding impossível.');
            return [];
        }

        $model    = $this->getModel();
        $endpoint = config(
            'services.embedding.gemini_endpoint',
            'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent'
        );
        $url = $endpoint . '?key=' . $this->geminiKey;

        $body = [
            'model'   => 'models/' . $model,
            'content' => [
                'parts' => [['text' => $text]],
            ],
        ];

        $startTime = microtime(true);
        $response  = $this->httpPost($url, [], $body);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        error_log(sprintf('[EmbeddingProvider] Gemini embed latency=%dms', $latencyMs));

        if (empty($response) || !isset($response['embedding']['values'])) {
            error_log('[EmbeddingProvider] Gemini retornou resposta inválida: ' . json_encode($response));
            return [];
        }

        return array_map('floatval', $response['embedding']['values']);
    }

    /**
     * OpenAI Embedding API.
     * Endpoint: https://api.openai.com/v1/embeddings
     * Model: text-embedding-3-small → 1536 floats.
     *
     * @return float[]
     */
    private function embedViaOpenAI(string $text): array
    {
        if (empty($this->openaiKey)) {
            error_log('[EmbeddingProvider] OPENAI_API_KEY não configurada — embedding impossível.');
            return [];
        }

        $url   = config('services.embedding.openai_endpoint', 'https://api.openai.com/v1/embeddings');
        $model = $this->getModel();

        $body = [
            'model' => $model,
            'input' => $text,
        ];

        $headers = [
            'Authorization: Bearer ' . $this->openaiKey,
            'Content-Type: application/json',
        ];

        $startTime = microtime(true);
        $response  = $this->httpPost($url, $headers, $body);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        error_log(sprintf('[EmbeddingProvider] OpenAI embed latency=%dms', $latencyMs));

        if (empty($response) || !isset($response['data'][0]['embedding'])) {
            error_log('[EmbeddingProvider] OpenAI retornou resposta inválida: ' . json_encode($response));
            return [];
        }

        return array_map('floatval', $response['data'][0]['embedding']);
    }

    // ─── HTTP ──────────────────────────────────────────────────────

    private function httpPost(string $url, array $headers, array $body): array
    {
        // Deduplicate headers: extras override defaults
        $headerMap = ['content-type' => 'Content-Type: application/json'];
        foreach ($headers as $h) {
            $name = strtolower(explode(':', $h, 2)[0]);
            $headerMap[$name] = $h;
        }
        $allHeaders = array_values($headerMap);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = @curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);

        // Retry without SSL verify if certificate error (macOS/Homebrew OpenSSL)
        if ($raw === false && (str_contains($err, 'certificate') || str_contains($err, 'trust anchors') || str_contains($err, 'SSL'))) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $raw  = @curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
        }

        // curl_close deprecated in PHP 8.5
        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }

        if ($raw === false) {
            error_log('[EmbeddingProvider] cURL error: ' . $err);
            return [];
        }

        if ($code >= 400) {
            error_log(sprintf('[EmbeddingProvider] HTTP %d: %s', $code, $raw));
            return [];
        }

        return json_decode($raw, true) ?? [];
    }

    // ─── Utilidades ────────────────────────────────────────────────

    private function truncateToWords(string $text, int $maxWords): string
    {
        $words = preg_split('/\s+/', trim($text));
        if (count($words) <= $maxWords) {
            return $text;
        }
        return implode(' ', array_slice($words, 0, $maxWords));
    }
}
