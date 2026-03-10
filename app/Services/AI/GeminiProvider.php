<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Helpers\AIResponseParser;

/**
 * Provider para Google Gemini API.
 * Suporta: generateContent, google-search grounding, JSON mode.
 */
class GeminiProvider
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.gemini.key', '');
        $this->model    = config('services.gemini.model', 'gemini-2.0-flash');
        $this->endpoint = config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models/');
    }

    /**
     * Gera conteúdo via Gemini com system + user prompt.
     *
     * @param string $systemPrompt Instruções para a IA
     * @param string $userPrompt   Conteúdo da requisição
     * @param array  $options      [json_mode, google_search, temperature, max_tokens]
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        if (empty($this->apiKey)) {
            return $this->mockResponse($options);
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

        if (empty($response)) {
            return '';
        }

        // Extrair texto da resposta Gemini
        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Gera e parseia JSON diretamente.
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $options['json_mode'] = true;
        $raw = $this->generate($systemPrompt, $userPrompt, $options);
        return AIResponseParser::parse($raw);
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
