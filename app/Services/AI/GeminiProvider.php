<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Helpers\AIResponseParser;

/**
 * Provider para Google Gemini API.
 * Suporta: generateContent, google-search grounding, JSON mode.
 * Aceita chave/modelo injetados (para AIProviderFactory) ou lê do config.
 */
class GeminiProvider
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    /** @var array|null Último usageMetadata capturado */
    private ?array $lastUsage = null;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey   = $apiKey   ?? config('services.gemini.key', '');
        $this->model    = $model    ?? config('services.gemini.model', 'gemini-2.0-flash');
        $this->endpoint = config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models/');
    }

    /**
     * Gera conteúdo via Gemini com system + user prompt.
     * Backward-compatible: retorna string.
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $meta = $this->generateWithMeta($systemPrompt, $userPrompt, $options);
        return $meta['text'];
    }

    /**
     * Gera conteúdo e retorna texto + metadata de tokens reais.
     *
     * @return array{text: string, usage: array{input: int, output: int, total: int}}
     */
    public function generateWithMeta(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $emptyUsage = ['input' => 0, 'output' => 0, 'total' => 0];

        if (empty($this->apiKey)) {
            return ['text' => $this->mockResponse($options), 'usage' => $emptyUsage];
        }

        $url = $this->endpoint . $this->model . ':generateContent?key=' . $this->apiKey;

        $body = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'temperature'     => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 8192,
            ],
        ];

        // JSON mode
        if ($options['json_mode'] ?? false) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }

        // Google Search grounding
        if ($options['google_search'] ?? false) {
            $body['tools'] = [['google_search' => new \stdClass()]];
        }

        $startTime = microtime(true);
        $response  = $this->httpPost($url, $body);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->logCall('gemini', $this->model, $latencyMs, !empty($response));

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Capturar tokens reais do usageMetadata
        $usage = $emptyUsage;
        if (isset($response['usageMetadata'])) {
            $um = $response['usageMetadata'];
            $usage = [
                'input'  => (int)($um['promptTokenCount'] ?? 0),
                'output' => (int)($um['candidatesTokenCount'] ?? 0),
                'total'  => (int)($um['totalTokenCount'] ?? 0),
            ];
        }
        $this->lastUsage = $usage;

        return ['text' => $text, 'usage' => $usage];
    }

    /**
     * Gera e parseia JSON diretamente. Backward-compatible.
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $options['json_mode'] = true;
        $raw = $this->generate($systemPrompt, $userPrompt, $options);
        return AIResponseParser::parse($raw);
    }

    /**
     * Gera JSON e retorna resultado parseado + metadata de tokens.
     *
     * @return array{parsed: array, text: string, usage: array{input: int, output: int, total: int}}
     */
    public function generateJsonWithMeta(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $options['json_mode'] = true;
        $meta = $this->generateWithMeta($systemPrompt, $userPrompt, $options);
        return [
            'parsed' => AIResponseParser::parse($meta['text']),
            'text'   => $meta['text'],
            'usage'  => $meta['usage'],
        ];
    }

    /**
     * Retorna o último usageMetadata capturado (útil após generate()).
     */
    public function getLastUsage(): array
    {
        return $this->lastUsage ?? ['input' => 0, 'output' => 0, 'total' => 0];
    }

    /**
     * Retorna o modelo atual.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Retorna o nome do provedor.
     */
    public function getProviderName(): string
    {
        return 'gemini';
    }

    private function httpPost(string $url, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $code >= 400) {
            error_log("[Gemini] HTTP {$code}: {$raw}");
            return [];
        }

        return json_decode($raw, true) ?? [];
    }

    private function logCall(string $provider, string $model, int $latencyMs, bool $success): void
    {
        error_log(sprintf('[AI] %s/%s latency=%dms status=%s', $provider, $model, $latencyMs, $success ? 'ok' : 'error'));
    }

    /**
     * Mock para quando não há API key configurada (desenvolvimento).
     */
    private function mockResponse(array $options): string
    {
        if ($options['json_mode'] ?? false) {
            return json_encode([
                'priorityScore'    => 72,
                'fitScore'         => 68,
                'digitalMaturity'  => 'Média',
                'urgencyLevel'     => 'Alta',
                'summary'          => 'Lead com potencial moderado. Site desatualizado e sem presença digital estruturada.',
                'diagnosis'        => ['Site lento (>4s)', 'Ausência de pixel de conversão', 'Instagram desatualizado'],
                'opportunities'    => ['Gestão de tráfego pago', 'SEO local', 'CRM automatizado'],
                'extractedContact' => ['phone' => null, 'whatsappAvailable' => false, 'address' => '', 'website' => '', 'websiteStatus' => 'NotFound'],
                'socialPresence'   => ['linkedin' => null, 'instagram' => null, 'facebook' => null],
                'businessDetails'  => ['timeInMarket' => '3-5 anos', 'operatingHours' => 'Seg-Sex 9h-18h'],
                '_mock'            => true,
            ]);
        }
        return 'Análise mockada — configure GEMINI_API_KEY no .env para resultados reais.';
    }
}
