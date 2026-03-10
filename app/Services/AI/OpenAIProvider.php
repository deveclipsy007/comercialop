<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Helpers\AIResponseParser;

/**
 * Provider para OpenAI API (também usado para Grok via endpoint customizado).
 */
class OpenAIProvider
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(string $providerOverride = 'openai')
    {
        if ($providerOverride === 'grok') {
            $this->apiKey   = config('services.grok.key', '');
            $this->model    = config('services.grok.model', 'grok-2');
            $this->endpoint = config('services.grok.endpoint', 'https://api.x.ai/v1/chat/completions');
        } else {
            $this->apiKey   = config('services.openai.key', '');
            $this->model    = config('services.openai.model', 'gpt-4o');
            $this->endpoint = config('services.openai.endpoint', 'https://api.openai.com/v1/chat/completions');
        }
    }

    /**
     * Gera resposta via Chat Completions (OpenAI/Grok).
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        if (empty($this->apiKey)) {
            return $this->mockResponse($options);
        }

        $body = [
            'model'    => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? 4096,
        ];

        if ($options['json_mode'] ?? false) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $startTime = microtime(true);
        $response  = $this->httpPost($body);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        error_log(sprintf('[AI] openai/%s latency=%dms', $this->model, $latencyMs));

        return $response['choices'][0]['message']['content'] ?? '';
    }

    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $options['json_mode'] = true;
        $raw = $this->generate($systemPrompt, $userPrompt, $options);
        return AIResponseParser::parse($raw);
    }

    private function httpPost(array $body): array
    {
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $code >= 400) {
            error_log("[OpenAI] HTTP {$code}: " . substr($raw, 0, 500));
            return [];
        }

        return json_decode($raw, true) ?? [];
    }

    private function mockResponse(array $options): string
    {
        if ($options['json_mode'] ?? false) {
            return json_encode(['_mock' => true, 'message' => 'Configure OPENAI_API_KEY no .env']);
        }
        return 'Mock OpenAI — configure API key';
    }
}
