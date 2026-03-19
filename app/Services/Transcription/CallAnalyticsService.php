<?php

namespace App\Services\Transcription;

use App\Models\Call;
use App\Models\Lead;
use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\SmartContextService;
use App\Services\Transcription\Providers\OpenAIWhisperProvider;
use App\Services\TokenService;
use Exception;

class CallAnalyticsService
{
    private TranscriptionProviderInterface $transcriptionProvider;
    private SmartContextService $contextService;
    private TokenService $tokenService;

    public function __construct()
    {
        $this->transcriptionProvider = new OpenAIWhisperProvider();
        $this->contextService = new SmartContextService();
        $this->tokenService = new TokenService();
    }

    /**
     * Processa um arquivo de áudio recém criado/uma Call.
     * Fase 1: Transcrição (Whisper)
     * Fase 2: Análise Comercial Estruturada RAG (Gemini)
     */
    public function processCall(int $callId, string $tenantId): void
    {
        $call = Call::findById($callId, $tenantId);
        if (!$call) {
            throw new Exception("Call não encontrada: {$callId}");
        }

        try {
            // Fase 1: Transcrição
            Call::updateStatus($callId, Call::STATUS_TRANSCRIBING);
            $transcriptionResult = $this->transcriptionProvider->transcribe($call['audio_path']);
            
            $transcriptClean = $transcriptionResult['text'];
            
            Call::update($callId, [
                'language' => $transcriptionResult['language'],
                'duration' => $transcriptionResult['duration'],
                'transcript_raw' => json_encode($transcriptionResult['raw_response']),
                'transcript_clean' => $transcriptClean,
                'status' => Call::STATUS_TRANSCRIBED
            ]);

            // Se a transcrição for muito curta, pode ser ruído ou inválida
            if (strlen(trim($transcriptClean)) < 15) {
               Call::update($callId, [
                   'status' => Call::STATUS_COMPLETED,
                   'analysis_data' => json_encode(['nota' => 'Áudio muito curto para análise comercial estruturada.'])
               ]);
               return;
            }

            // Integrando tokens simulados
            // $this->tokenService->consume('whisper_transcription', $tenantId, ceil($transcriptionResult['duration'] / 60) * 10);

            // Fase 2: Análise RAG Geminni
            Call::updateStatus($callId, Call::STATUS_ANALYZING);

            $lead = Lead::findByTenant($call['lead_id'], $tenantId);
            $context = $this->contextService->buildCompanyContext($tenantId) . "\n" . $this->contextService->buildLeadContext($call['lead_id'], $tenantId);

            $analysisResult = $this->analyzeTranscript($transcriptClean, $context, $lead, $tenantId);
            $analysisData = $analysisResult['data'];

            Call::update($callId, [
                'analysis_data' => json_encode($analysisData),
                'status' => Call::STATUS_COMPLETED
            ]);

        } catch (Exception $e) {
            Call::update($callId, [
                'status' => Call::STATUS_FAILED,
                'error_message' => $e->getMessage()
            ]);
            error_log("[CallAnalyticsService] Falha no processamento da chamada {$callId}: " . $e->getMessage());
        }
    }

    private function analyzeTranscript(string $transcript, string $companyContext, ?array $lead, string $tenantId = ''): array
    {
        $systemPrompt = "
        Você é um Diretor de Vendas Sênior e Estrategista Comercial.
        O usuário enviará a transcrição de uma ligação comercial (Call).
        Sua missão é extrair inteligência estruturada desta call.

        Use o contexto da empresa para basear suas respostas:
        -- CONTEXTO DA EMPRESA E LEAD --
        " . $companyContext . "
        -- FIM DO CONTEXTO --

        Analise a transcrição e retorne APENAS um JSON válido.
        Seja analítico, profissional e foque no fechamento da venda. Identifique se houve menções a preços, produtos da empresa, etc.

        Formato esperado (JSON EXATAMENTE ASSIM):
        {
            \"executive_summary\": \"Resumo da conversa em 3 frases claras.\",
            \"core_pain_points\": [\"dor 1\", \"dor 2\"],
            \"lead_goal\": \"Qual o grande objetivo que o lead quer alcançar?\",
            \"identified_objections\": [\"objeção de preço\", \"concorrente X\"],
            \"buying_signals\": [\"sinal 1\"],
            \"risk_signals\": [\"risco de não fechar\"],
            \"icp_fit_score\": 85,
            \"temperature\": \"Fria / Morna / Quente\",
            \"recommended_next_steps\": [\"Enviar proposta A\", \"Fazer follow-up na terça\"],
            \"key_quotes\": [\"Mero recorte de uma aspa forte dita na ligação\"]
        }
        ";

        $provider = AIProviderFactory::make('audio_strategy', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $transcript);
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $this->tokenService->consume(
            'audio_strategy', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        return ['data' => $meta['parsed'] ?? [], 'usage' => $usage];
    }
}
