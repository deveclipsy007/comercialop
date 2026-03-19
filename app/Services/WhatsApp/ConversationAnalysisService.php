<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Session;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppConversationAnalysis;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;

class ConversationAnalysisService
{

    /**
     * Analisa uma conversa de WhatsApp para extrair contexto e dor do lead.
     */
    public function analyze(string $conversationId, string $tenantId): array
    {
        try {
            // Pegar as últimas 50 mensagens
            $messages = WhatsAppMessage::findByConversation($conversationId, 50);
            if (empty($messages)) {
                return ['success' => false, 'error' => 'Nenhuma mensagem encontrada para analisar.'];
            }

            // Inverter para ordem cronológica
            $messages = array_reverse($messages);

            $transcript = "";
            foreach ($messages as $msg) {
                $sender = $msg['direction'] === 'outgoing' ? 'Vendedor' : 'Lead';
                $transcript .= "[{$sender}]: {$msg['body']}\n";
            }

            $prompt = $this->buildPrompt($transcript);

            $provider = AIProviderFactory::make('wa_summary', $tenantId);
            $meta = $provider->generateWithMeta($prompt, '');
            $response = $meta['text'] ?? '';
            $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

            // Parse response (espera-se JSON)
            $analysis = json_decode($response, true);

            if (!$analysis) {
                preg_match('/\{.*\}/s', $response, $matches);
                $analysis = json_decode($matches[0] ?? '{}', true);
            }

            if (empty($analysis)) {
                return ['success' => false, 'error' => 'Falha ao processar análise da IA.'];
            }

            $tokens = new TokenService();
            $tokens->consume(
                'wa_summary', $tenantId, Session::get('id'),
                $provider->getProviderName(), $provider->getModel(),
                $usage['input'], $usage['output']
            );

            WhatsAppConversationAnalysis::store($conversationId, $analysis, 0, $tenantId);

            return [
                'success'  => true,
                'analysis' => $analysis,
                'version'  => 1 // simplificado
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildPrompt(string $transcript): string
    {
        return "Analise a seguinte conversa de WhatsApp de um vendedor B2B com um possível lead.
Extraia as informações em formato JSON rigoroso:
{
  \"summary\": \"Resumo da conversa\",
  \"pains\": [\"lista de dores identificadas\"],
  \"objections\": [\"lista de objeções mencionadas\"],
  \"readiness\": 0-100, // disposição de compra
  \"next_steps\": \"Sugestão de próximo passo\",
  \"interest_level\": \"Baixo|Médio|Alto\"
}

Conversa:
{$transcript}

Responda APENAS o JSON.";
    }
}
