<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Helpers\AIResponseParser;

/**
 * Provider para OpenAI API (também usado para Grok via endpoint customizado).
 * Aceita chave/modelo/endpoint injetados (para AIProviderFactory) ou lê do config.
 */
class OpenAIProvider
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private string $providerName;

    /** @var array|null Último usage capturado */
    private ?array $lastUsage = null;

    public function __construct(
        string $providerOverride = 'openai',
        ?string $apiKey = null,
        ?string $model = null,
        ?string $endpoint = null
    ) {
        $this->providerName = $providerOverride;

        if ($providerOverride === 'grok') {
            $this->apiKey   = $apiKey   ?? config('services.grok.key', '');
            $this->model    = $model    ?? config('services.grok.model', 'grok-2');
            $this->endpoint = $endpoint ?? config('services.grok.endpoint', 'https://api.x.ai/v1/chat/completions');
        } else {
            $this->apiKey   = $apiKey   ?? config('services.openai.key', '');
            $this->model    = $model    ?? config('services.openai.model', 'gpt-4o');
            $this->endpoint = $endpoint ?? config('services.openai.endpoint', 'https://api.openai.com/v1/chat/completions');
        }
    }

    /**
     * Gera resposta via Chat Completions. Backward-compatible: retorna string.
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $meta = $this->generateWithMeta($systemPrompt, $userPrompt, $options);
        return $meta['text'];
    }

    /**
     * Gera resposta e retorna texto + metadata de tokens reais.
     *
     * @return array{text: string, usage: array{input: int, output: int, total: int}}
     */
    public function generateWithMeta(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $emptyUsage = ['input' => 0, 'output' => 0, 'total' => 0];

        if (empty($this->apiKey)) {
            return ['text' => $this->mockResponse($options), 'usage' => $emptyUsage];
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

        error_log(sprintf('[AI] %s/%s latency=%dms', $this->providerName, $this->model, $latencyMs));

        $text = $response['choices'][0]['message']['content'] ?? '';

        // Capturar tokens reais do usage
        $usage = $emptyUsage;
        if (isset($response['usage'])) {
            $u = $response['usage'];
            $usage = [
                'input'  => (int)($u['prompt_tokens'] ?? 0),
                'output' => (int)($u['completion_tokens'] ?? 0),
                'total'  => (int)($u['total_tokens'] ?? 0),
            ];
        }
        $this->lastUsage = $usage;

        return ['text' => $text, 'usage' => $usage];
    }

    /**
     * Gera e parseia JSON. Backward-compatible.
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
     * Retorna o último usage capturado.
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
        return $this->providerName;
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
            error_log("[OpenAI] HTTP {$code}: " . substr($raw ?: '', 0, 500));
            return [];
        }

        return json_decode($raw, true) ?? [];
    }

    private function mockResponse(array $options): string
    {
        error_log("[{$this->providerName}] API key não configurada. Configure pelo painel Admin > Chaves de IA.");
        if ($options['json_mode'] ?? false) {
            return json_encode([
                '_error' => true,
                '_message' => "Chave de API {$this->providerName} não configurada. Solicite ao administrador que configure pelo painel Admin > Chaves de IA.",
            ]);
        }
        return '';
    }
}
