<?php

namespace App\Services\Transcription\Providers;

use App\Services\Transcription\TranscriptionProviderInterface;
use Exception;

class OpenAIWhisperProvider implements TranscriptionProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.openai.key', '');
        $this->model    = config('services.openai.whisper_model', 'whisper-1');
        $this->endpoint = config('services.openai.whisper_endpoint', 'https://api.openai.com/v1/audio/transcriptions');
    }

    public function transcribe(string $audioPath): array
    {
        if (empty($this->apiKey)) {
            return $this->mockResponse();
        }

        if (!file_exists($audioPath)) {
            throw new Exception("Arquivo de áudio não encontrado para transcrição: {$audioPath}");
        }

        $cFile = new \CURLFile($audioPath);

        $postFields = [
            'file' => $cFile,
            'model' => $this->model,
            'response_format' => 'verbose_json', // Para obtermos duration e language
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: multipart/form-data'
            ],
            CURLOPT_TIMEOUT        => 300, // Transcrições podem demorar
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $startTime = microtime(true);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
        $this->logCall('openai_whisper', $this->model, $latencyMs, $code === 200);

        if ($raw === false || $code >= 400) {
            $msg = $raw ? json_decode($raw, true)['error']['message'] ?? $raw : $error;
            error_log("[Whisper] HTTP {$code}: {$msg}");
            throw new Exception("Falha na transcrição via OpenAI Whisper: " . $msg);
        }

        $data = json_decode($raw, true);

        if (!$data || !isset($data['text'])) {
            throw new Exception("Resposta inválida ou vazia do provedor de transcrição.");
        }

        return [
            'text'         => trim($data['text']),
            'language'     => $data['language'] ?? 'unknown',
            'duration'     => isset($data['duration']) ? (float)$data['duration'] : 0.0,
            'raw_response' => $data
        ];
    }

    private function logCall(string $provider, string $model, int $latencyMs, bool $success): void
    {
        error_log(sprintf('[Transcription] %s/%s latency=%dms status=%s', $provider, $model, $latencyMs, $success ? 'ok' : 'error'));
    }

    private function mockResponse(): array
    {
        error_log("[Transcription] Mock Warning: OpenAI API Key not configured. Using Mock Transcription.");
        return [
            'text' => "Olá, tudo bem? Eu sou o João da empresa fictícia. Gostaríamos de testar a plataforma de vocês porque estamos com problemas na captação de leads. O CRM atual é muito lento.",
            'language' => 'pt',
            'duration' => 15.5,
            'raw_response' => ['mock' => true]
        ];
    }
}
