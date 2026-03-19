<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Database;
use App\Models\WhatsAppConversationAnalysis;
use App\Models\WhatsAppLeadLink;
use App\Models\WhatsAppMessage;
use App\Models\Lead;
use App\Models\AnalysisTrace;
use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;

/**
 * Serviço de inteligência comercial para conversas WhatsApp.
 *
 * 4 funcionalidades:
 *   1. Resumo automático (summary)
 *   2. Geração de próxima mensagem (next_message)
 *   3. Análise estratégica (strategic)
 *   4. Score de interesse (interest_score)
 */
class WhatsAppIntelligenceService
{
    private TokenService $tokens;

    public function __construct()
    {
        $this->tokens = new TokenService();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. RESUMO AUTOMÁTICO
    // ═══════════════════════════════════════════════════════════════════════════

    public function generateSummary(string $conversationId, string $tenantId): array
    {
        $transcript = $this->buildTranscript($conversationId);
        if (!$transcript) {
            return ['success' => false, 'error' => 'Nenhuma mensagem para analisar.'];
        }

        $leadsCtx = $this->buildLinkedLeadsContext($tenantId, $conversationId);

        $system = 'Você é um analista de inteligência comercial especializado em conversas de WhatsApp B2B. '
                . 'Analise a transcrição e extraia inteligência comercial estruturada. '
                . 'Seja direto, objetivo e foque em informações acionáveis para o closer.';

        $user = "Analise esta conversa de WhatsApp e gere um resumo comercial estruturado.\n\n"
              . ($leadsCtx ? "=== LEADS VINCULADOS ===\n{$leadsCtx}\n\n" : '')
              . "=== TRANSCRIÇÃO DA CONVERSA ===\n{$transcript}\n\n"
              . 'Retorne APENAS JSON válido com esta estrutura exata:'
              . "\n" . '{
  "summary": "Resumo executivo em 2-3 frases",
  "pains": ["dor identificada 1"],
  "objections": ["objeção 1"],
  "interest_level": "Baixo|Médio|Alto",
  "urgency": "Baixa|Média|Alta",
  "buying_signals": ["sinal de compra 1"],
  "next_steps": ["próximo passo recomendado 1"],
  "key_topics": ["tópico principal 1"],
  "conversation_stage": "Prospecção|Qualificação|Negociação|Fechamento|Pós-venda"
}';

        $provider = AIProviderFactory::make('wa_summary', $tenantId);
        $t0 = microtime(true);
        $meta = $provider->generateJsonWithMeta($system, $user, ['temperature' => 0.2]);
        $latMs = (int) ((microtime(true) - $t0) * 1000);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        if (empty($result) || empty($result['summary'])) {
            return ['success' => false, 'error' => 'IA não retornou um resumo válido.'];
        }

        $cost = $this->tokens->consume(
            'wa_summary', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        WhatsAppConversationAnalysis::storeTyped($conversationId, $tenantId, 'summary', $result, $cost);
        $this->logTrace($tenantId, 'wa_summary', $cost, $provider, $latMs, $usage);

        return ['success' => true, 'analysis' => $result, 'tokens_used' => $cost];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 2. GERAR PRÓXIMA MENSAGEM
    // ═══════════════════════════════════════════════════════════════════════════

    public function generateNextMessage(string $conversationId, string $tenantId): array
    {
        $transcript = $this->buildTranscript($conversationId, 30);
        if (!$transcript) {
            return ['success' => false, 'error' => 'Nenhuma mensagem para analisar.'];
        }

        $leadsCtx  = $this->buildLinkedLeadsContext($tenantId, $conversationId);
        $agencyCtx = $this->getAgencyContext($tenantId);

        // Buscar última análise para contexto adicional
        $lastAnalysis = WhatsAppConversationAnalysis::latestByConversation($conversationId);
        $analysisCtx  = '';
        if ($lastAnalysis) {
            $data = json_decode($lastAnalysis['analysis'] ?? '{}', true);
            $analysisCtx = sprintf(
                "Estágio: %s\nDores: %s\nObjeções: %s\nInteresse: %s",
                $data['conversation_stage'] ?? 'Desconhecido',
                implode(', ', $data['pains'] ?? []),
                implode(', ', $data['objections'] ?? []),
                $data['interest_level'] ?? 'N/D'
            );
        }

        $system = 'Você é um closer de vendas B2B especialista em WhatsApp. '
                . 'Gere uma mensagem de follow-up contextual, persuasiva e pronta para enviar. '
                . 'A mensagem deve ser natural, não robótica, e adequada ao estágio da negociação. '
                . 'Máximo: 200 palavras. Use emojis com moderação. Tom consultivo e profissional.';

        $user = "Com base na conversa abaixo, gere a próxima mensagem ideal para enviar.\n\n"
              . ($agencyCtx ? "=== CONTEXTO DA AGÊNCIA ===\n{$agencyCtx}\n\n" : '')
              . ($leadsCtx ? "=== LEADS VINCULADOS ===\n{$leadsCtx}\n\n" : '')
              . ($analysisCtx ? "=== ÚLTIMA ANÁLISE ===\n{$analysisCtx}\n\n" : '')
              . "=== TRANSCRIÇÃO (últimas mensagens) ===\n{$transcript}\n\n"
              . 'Retorne APENAS JSON válido:'
              . "\n" . '{
  "message": "Texto da mensagem pronta para copiar e enviar",
  "strategy": "Breve explicação da estratégia usada (1 frase)",
  "tone": "Consultivo|Urgente|Amigável|Direto",
  "cta_type": "Reunião|Proposta|Informação|Fechamento"
}';

        $provider = AIProviderFactory::make('wa_next_message', $tenantId);
        $t0 = microtime(true);
        $meta = $provider->generateJsonWithMeta($system, $user, ['temperature' => 0.6]);
        $latMs = (int) ((microtime(true) - $t0) * 1000);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        if (empty($result) || empty($result['message'])) {
            return ['success' => false, 'error' => 'IA não gerou uma mensagem válida.'];
        }

        $cost = $this->tokens->consume(
            'wa_next_message', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );
        $this->logTrace($tenantId, 'wa_next_message', $cost, $provider, $latMs, $usage);

        return ['success' => true, 'result' => $result, 'tokens_used' => $cost];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 3. ANÁLISE ESTRATÉGICA
    // ═══════════════════════════════════════════════════════════════════════════

    public function runStrategicAnalysis(string $conversationId, string $tenantId): array
    {
        $transcript = $this->buildTranscript($conversationId);
        if (!$transcript) {
            return ['success' => false, 'error' => 'Nenhuma mensagem para analisar.'];
        }

        $leadsCtx  = $this->buildLinkedLeadsContext($tenantId, $conversationId);
        $agencyCtx = $this->getAgencyContext($tenantId);

        $system = 'Você é um Diretor Comercial Senior analisando uma conversa de WhatsApp entre um closer e um prospect. '
                . 'Sua missão: identificar padrões estratégicos, riscos e oportunidades de fechamento. '
                . 'Seja cirúrgico e objetivo. Foque em inteligência acionável.';

        $user = "Realize uma análise estratégica profunda desta conversa comercial.\n\n"
              . ($agencyCtx ? "=== CONTEXTO DA AGÊNCIA ===\n{$agencyCtx}\n\n" : '')
              . ($leadsCtx ? "=== LEADS VINCULADOS ===\n{$leadsCtx}\n\n" : '')
              . "=== TRANSCRIÇÃO COMPLETA ===\n{$transcript}\n\n"
              . 'Retorne APENAS JSON válido:'
              . "\n" . '{
  "interest_level": "Baixo|Médio|Alto|Muito Alto",
  "conversation_tone": "Receptivo|Neutro|Resistente|Hostil",
  "perceived_intent": "Exploratório|Comparativo|Decisório|Descartando",
  "main_objections": [
    {"objection": "descrição", "severity": "Baixa|Média|Alta", "suggested_response": "como contornar"}
  ],
  "opportunity_points": [
    {"opportunity": "descrição", "leverage": "como explorar"}
  ],
  "loss_risk": {
    "level": "Baixo|Médio|Alto",
    "factors": ["fator de risco 1"],
    "mitigation": "o que fazer para reduzir o risco"
  },
  "proposal_quality": {
    "score": 75,
    "strengths": ["ponto forte 1"],
    "gaps": ["lacuna 1"]
  },
  "recommended_actions": ["ação 1", "ação 2", "ação 3"]
}';

        $provider = AIProviderFactory::make('wa_strategic', $tenantId);
        $t0 = microtime(true);
        $meta = $provider->generateJsonWithMeta($system, $user, ['temperature' => 0.3]);
        $latMs = (int) ((microtime(true) - $t0) * 1000);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        if (empty($result) || !isset($result['interest_level'])) {
            return ['success' => false, 'error' => 'IA não retornou análise estratégica válida.'];
        }

        $cost = $this->tokens->consume(
            'wa_strategic', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        WhatsAppConversationAnalysis::storeTyped($conversationId, $tenantId, 'strategic', $result, $cost);
        $this->logTrace($tenantId, 'wa_strategic', $cost, $provider, $latMs, $usage);

        return ['success' => true, 'analysis' => $result, 'tokens_used' => $cost];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 4. SCORE DE INTERESSE
    // ═══════════════════════════════════════════════════════════════════════════

    public function calculateInterestScore(string $conversationId, string $tenantId): array
    {
        $transcript = $this->buildTranscript($conversationId);
        if (!$transcript) {
            return ['success' => false, 'error' => 'Nenhuma mensagem para analisar.'];
        }

        $leadsCtx = $this->buildLinkedLeadsContext($tenantId, $conversationId);

        $system = 'Você é um algoritmo de scoring comercial. '
                . 'Analise a conversa de WhatsApp e calcule um score de interesse de 0 a 100. '
                . 'Seja rigoroso e baseado em evidências concretas da conversa. Não infle o score.';

        $user = "Calcule o Interest Score (0-100) desta conversa com base nos seguintes critérios:\n\n"
              . "CRITÉRIOS E PESOS:\n"
              . "1. Abertura do lead (0-15): Responde rápido? Faz perguntas? Demonstra curiosidade?\n"
              . "2. Profundidade do interesse (0-20): Pergunta detalhes? Quer entender o serviço?\n"
              . "3. Fit com a solução (0-15): Os problemas do lead se encaixam nos serviços oferecidos?\n"
              . "4. Urgência percebida (0-15): Menciona prazo? Tem problema imediato? Precisa resolver rápido?\n"
              . "5. Sinais de avanço (0-20): Pede proposta? Agenda reunião? Fala de orçamento?\n"
              . "6. Sinais de resistência (0 a -15): Ignora mensagens? Diz que vai pensar? Menciona concorrente?\n"
              . "7. Potencial comercial (0-15): Porte da empresa? Volume de negócio? Recorrência?\n\n"
              . ($leadsCtx ? "=== LEADS VINCULADOS ===\n{$leadsCtx}\n\n" : '')
              . "=== TRANSCRIÇÃO ===\n{$transcript}\n\n"
              . 'Retorne APENAS JSON válido:'
              . "\n" . '{
  "interest_score": 72,
  "breakdown": {
    "lead_openness": {"score": 12, "max": 15, "evidence": "evidência"},
    "interest_depth": {"score": 14, "max": 20, "evidence": "evidência"},
    "solution_fit": {"score": 10, "max": 15, "evidence": "evidência"},
    "perceived_urgency": {"score": 8, "max": 15, "evidence": "evidência"},
    "advance_signals": {"score": 16, "max": 20, "evidence": "evidência"},
    "resistance_signals": {"score": -3, "max": 15, "evidence": "evidência"},
    "commercial_potential": {"score": 10, "max": 15, "evidence": "evidência"}
  },
  "score_explanation": "Explicação em 1-2 frases do score final",
  "trend": "Subindo|Estável|Caindo"
}';

        $provider = AIProviderFactory::make('wa_interest_score', $tenantId);
        $t0 = microtime(true);
        $meta = $provider->generateJsonWithMeta($system, $user, ['temperature' => 0.2]);
        $latMs = (int) ((microtime(true) - $t0) * 1000);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        if (empty($result) || !isset($result['interest_score'])) {
            return ['success' => false, 'error' => 'IA não retornou score válido.'];
        }

        $cost = $this->tokens->consume(
            'wa_interest_score', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        WhatsAppConversationAnalysis::storeTyped(
            $conversationId, $tenantId, 'interest_score', $result, $cost, (int)$result['interest_score']
        );
        $this->logTrace($tenantId, 'wa_interest_score', $cost, $provider, $latMs, $usage);

        return ['success' => true, 'score' => $result, 'tokens_used' => $cost];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Monta transcrição da conversa em formato legível para a IA.
     */
    private function buildTranscript(string $conversationId, int $limit = 50): string
    {
        $messages = WhatsAppMessage::findByConversation($conversationId, $limit);
        if (empty($messages)) return '';

        $messages = array_reverse($messages); // Cronológico

        $lines = [];
        foreach ($messages as $msg) {
            $sender = $msg['direction'] === 'outgoing' ? 'Vendedor' : 'Lead';
            $time   = date('d/m H:i', (int)$msg['timestamp']);
            $body   = trim($msg['body'] ?? '');
            if ($body) {
                $lines[] = "[{$time}] [{$sender}]: {$body}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Monta contexto dos leads vinculados à conversa.
     */
    private function buildLinkedLeadsContext(string $tenantId, string $conversationId): string
    {
        $links = WhatsAppLeadLink::findAllByConversation($tenantId, $conversationId);
        if (empty($links)) return '';

        $parts = [];
        foreach ($links as $link) {
            $lead = Lead::findByTenant($link['lead_id'], $tenantId);
            if (!$lead) continue;

            $tags = '';
            if (!empty($lead['tags'])) {
                $decoded = is_string($lead['tags']) ? json_decode($lead['tags'], true) : $lead['tags'];
                $tags = is_array($decoded) ? implode(', ', $decoded) : '';
            }

            $analysis = is_string($lead['analysis'] ?? null) ? json_decode($lead['analysis'], true) : ($lead['analysis'] ?? []);

            $parts[] = sprintf(
                "- %s | Empresa: %s | Segmento: %s | Status: %s | Score: %d | Tags: %s | Maturidade: %s",
                $lead['name'] ?? 'N/D',
                $lead['company'] ?? 'N/D',
                $lead['segment'] ?? 'N/D',
                $lead['pipeline_status'] ?? 'N/D',
                $lead['priority_score'] ?? 0,
                $tags ?: 'N/D',
                $analysis['digitalMaturity'] ?? 'N/D'
            );
        }

        return implode("\n", $parts);
    }

    /**
     * Busca contexto da agência para o tenant.
     */
    private function getAgencyContext(string $tenantId): string
    {
        $settings = Database::selectFirst(
            'SELECT agency_name, niche, offer_summary, differentials, icp_profile, custom_context
             FROM agency_settings WHERE tenant_id = ?',
            [$tenantId]
        );

        if (!$settings) return '';

        $parts = [];
        if ($settings['agency_name'])   $parts[] = "Agência: {$settings['agency_name']}";
        if ($settings['niche'])         $parts[] = "Nicho: {$settings['niche']}";
        if ($settings['offer_summary']) $parts[] = "Oferta: {$settings['offer_summary']}";
        if ($settings['differentials']) $parts[] = "Diferenciais: {$settings['differentials']}";
        if ($settings['icp_profile'])   $parts[] = "ICP: {$settings['icp_profile']}";
        if ($settings['custom_context'])$parts[] = "Contexto: {$settings['custom_context']}";

        return implode("\n", $parts);
    }

    /**
     * Registra trace da operação de IA.
     */
    private function logTrace(string $tenantId, string $operation, int $tokenCost, $provider = null, int $latencyMs = 0, array $usage = []): void
    {
        try {
            AnalysisTrace::log(
                tenantId:      $tenantId,
                leadId:        null,
                operation:     $operation,
                contextSource: 'direct',
                provider:      $provider ? $provider->getProviderName() : 'unknown',
                model:         $provider ? $provider->getModel() : 'unknown',
                latencyMs:     $latencyMs,
                tokenCost:     ($usage['input'] ?? 0) + ($usage['output'] ?? 0)
            );
        } catch (\Throwable $e) {
            error_log("[WhatsAppIntelligence] Trace log failed: " . $e->getMessage());
        }
    }
}
