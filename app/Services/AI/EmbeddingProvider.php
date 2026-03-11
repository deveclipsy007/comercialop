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

    public function __construct()
    {
        $this->provider  = config('services.embedding.provider', 'gemini');
        $this->geminiKey = config('services.gemini.key', '');
        $this->openaiKey = config('services.openai.key', '');
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
        // Header padrão sempre presente; headers extras são mesclados
        $allHeaders = array_merge(['Content-Type: application/json'], $headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

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
