<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AIResponseParser;
use App\Models\AnalysisTrace;
use App\Models\Lead;
use App\Services\AI\GeminiProvider;
use App\Services\AI\OpenAIProvider;

/**
 * Serviço central de análise de leads com IA.
 * Orquestra todas as operações de IA: qualificação, deep analysis, Operon 4D, SPIN, scripts.
 */
class LeadAnalysisService
{
    private GeminiProvider $gemini;
    private SmartContextService $smartContext;
    private TokenService $tokens;

    public function __construct()
    {
        $this->gemini       = new GeminiProvider();
        $this->smartContext  = new SmartContextService();
        $this->tokens        = new TokenService();
    }

    // ─── System Prompt Base ─────────────────────────────────────────

    private function baseSystemPrompt(): string
    {
        return <<<PROMPT
Você é um Consultor de Vendas Sênior especializado em identificar oportunidades de negócio.
Sua missão é analisar dados de empresas e gerar diagnósticos comerciais agressivos e diretos.
Foco: Identificar onde o lead está perdendo dinheiro e criar ganchos de abordagem prontos para uso.
Use SEMPRE o contexto real da agência (oferta, preços, cases) para personalizar os scripts e diagnósticos.
Seja conciso, objetivo e comercialmente impactante.
PROMPT;
    }

    // ─── Lead Analysis (Qualificação) — 7 tokens ────────────────────

    public function analyzeLeadWithAI(array $lead, string $tenantId): array
    {
        $this->tokens->consume('lead_analysis', $tenantId);

        $systemPrompt = $this->baseSystemPrompt() . "\n\n" . $this->smartContext->buildLeadContext($lead);
        $ragMeta      = $this->smartContext->getLastRetrievalMeta();

        $userPrompt = <<<PROMPT
Analise esta empresa para qualificação de lead B2B.
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Site Fornecido: {$lead['website']}

IMPORTANTE: Use o Google Search para encontrar dados OFICIAIS:
1. O endereço real completo.
2. Telefone ou WhatsApp de contato.
3. A URL OFICIAL do Website.
4. Redes sociais (Instagram, LinkedIn).
5. Tempo estimado de mercado.

CRITÉRIOS RIGOROSOS DE SCORE (Seja realista):
- Score > 80: Apenas se tiver Site Profissional, Instagram Ativo, LinkedIn e bons reviews.
- Score < 40: Se não tiver site ou presença digital quase nula.
- NÃO infle a nota. Queremos vender serviços digitais, então precisamos identificar as FALHAS.

Retorne APENAS um JSON válido (sem markdown) com a seguinte estrutura:
{
  "priorityScore": number (0-100, seja rigoroso),
  "scoreExplanation": "Uma frase direta explicando o porquê da nota",
  "digitalMaturity": "Baixa" | "Média" | "Alta",
  "diagnosis": ["problema crítico 1", "problema crítico 2", "problema crítico 3"],
  "opportunities": ["oportunidade 1", "oportunidade 2"],
  "urgencyLevel": "Baixa" | "Média" | "Alta",
  "fitScore": number (0-100),
  "summary": string,
  "extractedContact": {
    "phone": string,
    "whatsappAvailable": boolean,
    "address": string,
    "website": string,
    "websiteStatus": "Active" | "Inactive" | "NotFound"
  },
  "socialPresence": {
    "linkedin": string,
    "instagram": string,
    "facebook": string
  },
  "businessDetails": {
    "timeInMarket": string,
    "operatingHours": string
  }
}
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generateJson($systemPrompt, $userPrompt, ['google_search' => true]);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        if (!AIResponseParser::hasError($result)) {
            Lead::saveAnalysis($lead['id'], $tenantId, $result);
        }

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'],
            operation:     'lead_analysis',
            contextSource: $ragMeta['source'] ?? 'default',
            queryText:     null,
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    // ─── Operon 4D Intelligence ─────────────────────────────────────

    public function runOperon4D(array $lead, string $tenantId): array
    {
        $agencyContext = $this->smartContext->buildOperonContext($lead);
        $ragMeta       = $this->smartContext->getLastRetrievalMeta();
        $systemPrompt  = $this->baseSystemPrompt() . "\n\n" . $agencyContext;

        return [
            'diagnostico' => $this->runDiagnosticoPerda($lead, $systemPrompt, $tenantId, $ragMeta),
            'potencial'   => $this->runPotencialComercial($lead, $systemPrompt, $tenantId, $ragMeta),
            'autoridade'  => $this->runAutoridadeLocal($lead, $systemPrompt, $tenantId, $ragMeta),
            'script'      => $this->runScriptAbordagem($lead, $systemPrompt, $tenantId, $ragMeta),
        ];
    }

    private function runDiagnosticoPerda(array $lead, string $systemPrompt, string $tenantId, array $ragMeta = []): array
    {
        $this->tokens->consume('operon_diagnostico', $tenantId);

        $site  = $lead['website'] ?? null;
        $ps    = $lead['pagespeed_data'] ?? null;

        if (!$site) {
            $siteBlock = "PROBLEMA CRÍTICO: Esta empresa NÃO possui site.\nGere um diagnóstico de perda focado na \"Invisibilidade Digital\".";
        } elseif ($ps) {
            $perf = $ps['performanceScore'] ?? '?';
            $load = $ps['loadTime'] ?? '?';
            $siteBlock = "Dados técnicos:\n- Performance: {$perf}/100\n- Tempo de carregamento: {$load}s";
        } else {
            $siteBlock = "Site informado mas não analisado tecnicamente. Foque em problemas comuns de sites desatualizados.";
        }

        $userPrompt = <<<PROMPT
Analise o lead "{$lead['name']}" ({$lead['segment']}).
{$siteBlock}
Retorne APENAS JSON válido:
{"titulo":"string","status":"critico"|"atencao"|"moderado","problemas":[],"impactoFinanceiro":{"perda_mensal_min":0,"perda_mensal_max":0,"descricao":"string"},"urgencia":"alta"|"media"|"baixa","acoes_imediatas":[]}
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generateJson($systemPrompt, $userPrompt);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'],
            operation:     'operon_diagnostico',
            contextSource: $ragMeta['source'] ?? 'default',
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    // ─── Deep Insights (Value Proposition, Target Audience, Competitors) ─────

    public function runDeepInsights(array $lead, string $tenantId): array
    {
        $this->tokens->consume('deep_insights', $tenantId);

        $systemPrompt = $this->baseSystemPrompt() . "\n\n" . $this->smartContext->buildLeadContext($lead);
        $ragMeta      = $this->smartContext->getLastRetrievalMeta();

        $userPrompt = <<<PROMPT
Você precisa gerar insights comerciais profundos sobre esta empresa:
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Site: {$lead['website']}

Retorne APENAS um JSON válido com a seguinte estrutura:
{
  "valueProposition": "Descreva em 1-2 parágrafos de forma clara o que a empresa vende e qual a sua proposta de valor principal.",
  "targetAudience": "Descreva em 1-2 parágrafos quem é o público-alvo principal que compra desta empresa.",
  "competitors": ["Nome de um concorrente real ou tipologia de concorrente 1", "Concorrente 2", "Concorrente 3"]
}
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generateJson($systemPrompt, $userPrompt);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        if (!AIResponseParser::hasError($result)) {
            $analysis = $lead['analysis'] ?? [];
            $analysis['valueProposition'] = $result['valueProposition'] ?? null;
            $analysis['targetAudience']   = $result['targetAudience'] ?? null;
            $analysis['competitors']      = $result['competitors'] ?? [];
            Lead::update($lead['id'], $tenantId, ['analysis' => $analysis]);
        }

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'],
            operation:     'deep_analysis',
            contextSource: $ragMeta['source'] ?? 'default',
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    private function runPotencialComercial(array $lead, string $systemPrompt, string $tenantId, array $ragMeta = []): array
    {
        $this->tokens->consume('operon_potencial', $tenantId);
        $cnpj = $lead['cnpj_data'] ?? null;
        $cnpjBlock = $cnpj
            ? "Dados da empresa:\n- Capital Social: R$ " . ($cnpj['capitalSocial'] ?? 'N/D') . "\n- CNAE: " . ($cnpj['cnaePrincipal'] ?? 'N/D')
            : "CNPJ não disponível. Analise com base no segmento: {$lead['segment']}.";

        $userPrompt = <<<PROMPT
Analise o potencial comercial de "{$lead['name']}".
{$cnpjBlock}
Retorne APENAS JSON válido:
{"classificacao":"Entrada"|"Médio"|"Alto Ticket","score_potencial":0,"poder_compra":"string","servicos_recomendados":[{"nome":"string","prioridade":"alta"|"media"|"baixa"}],"valor_proposta":{"minimo":0,"maximo":0,"recorrente":true},"justificativa":"string"}
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generateJson($systemPrompt, $userPrompt);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'],
            operation:     'operon_potencial',
            contextSource: $ragMeta['source'] ?? 'default',
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    private function runAutoridadeLocal(array $lead, string $systemPrompt, string $tenantId, array $ragMeta = []): array
    {
        $this->tokens->consume('operon_autoridade', $tenantId);
        $userPrompt = <<<PROMPT
Analise a autoridade local de "{$lead['name']}". Localização: {$lead['address']}.
Retorne APENAS JSON válido:
{"status":"forte"|"moderado"|"fraco"|"inexistente","score_autoridade":0,"comparacao_setor":"string","metricas":{"avaliacao_atual":0,"total_avaliacoes":0,"media_setor":0},"impacto_faturamento":"string","acoes_melhoria":[{"acao":"string","impacto":"alto"|"medio"|"baixo"}]}
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generateJson($systemPrompt, $userPrompt);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'],
            operation:     'operon_autoridade',
            contextSource: $ragMeta['source'] ?? 'default',
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    private function runScriptAbordagem(array $lead, string $systemPrompt, string $tenantId, array $ragMeta = []): string
    {
        $this->tokens->consume('operon_script', $tenantId);
        $userPrompt = <<<PROMPT
Crie um script de abordagem para WhatsApp para "{$lead['name']}".
Formato: 1. Abertura personalizada. 2. Gancho da dor. 3. Proposta de valor. 4. CTA direto.
IMPORTANTE: Máximo 150 palavras. Tom consultivo mas direto. Pronto para copiar e colar no WhatsApp.
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generate($systemPrompt, $userPrompt);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'],
            operation:     'operon_script',
            contextSource: $ragMeta['source'] ?? 'default',
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    // ─── SPIN Framework — 4 tokens ──────────────────────────────────

    public function generateSpin(array $lead, string $tenantId): array
    {
        $this->tokens->consume('spin_questions', $tenantId);

        $maturity     = $lead['analysis']['digitalMaturity'] ?? 'Média';
        $systemPrompt = "Você é um consultor de vendas high-ticket especialista no framework SPIN.";
        $userPrompt   = <<<PROMPT
LEAD: {$lead['name']}
SEGMENTO: {$lead['segment']}
MATURIDADE: {$maturity}

Crie 3 perguntas para cada fase do SPIN (Situação, Problema, Implicação, Necessidade de Solução).
As perguntas devem ser específicas para o nicho de {$lead['segment']}.

Retorne JSON estrito:
{"s":["Pergunta 1","Pergunta 2","Pergunta 3"],"p":["..."],"i":["..."],"n":["..."]}
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generateJson($systemPrompt, $userPrompt, ['json_mode' => true]);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'] ?? null,
            operation:     'spin_questions',
            contextSource: AnalysisTrace::SOURCE_DEFAULT,
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    // ─── Script Variations — 7 tokens ───────────────────────────────

    public function generateScriptVariations(array $lead, string $tenantId): array
    {
        $this->tokens->consume('script_variations', $tenantId);
        $score    = $lead['analysis']['priorityScore'] ?? 50;
        $maturity = $lead['analysis']['digitalMaturity'] ?? 'Média';

        $systemPrompt = $this->baseSystemPrompt();
        $userPrompt   = <<<PROMPT
Crie variações de script de abordagem para este lead:
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Score: {$score}
Maturidade Digital: {$maturity}

Para cada canal, crie um script curto e persuasivo (máx 80 palavras):
Retorne JSON: {"whatsapp":"string","linkedin":"string","email":"string","coldCall":"string"}
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generateJson($systemPrompt, $userPrompt);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $lead['id'] ?? null,
            operation:     'script_variations',
            contextSource: AnalysisTrace::SOURCE_DEFAULT,
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    // ─── Follow-up Message Generation ─────────────────────────────────

    public function generateFollowupMessage(array $followup, string $tenantId): string
    {
        $this->tokens->consume('followup_message', $tenantId);

        $systemPrompt = $this->baseSystemPrompt();
        $userPrompt   = <<<PROMPT
Crie um roteiro de mensagem de follow-up pronto para ser enviado (WhatsApp ou E-mail) para o lead abaixo.

Lead: {$followup['lead_name']}
Segmento: {$followup['lead_segment']}
Estágio atual: {$followup['pipeline_status']}
Contexto e Objeções: {$followup['lead_context']}

Objetivo deste follow-up específico: {$followup['title']}
Detalhes da tarefa: {$followup['description']}

O roteiro deve ser persuasivo, direto, consultivo e adaptado ao contexto do lead.
Inclua um call to action (CTA) claro focado em avançar a negociação.
Não exiba campos de placeholder gigantes, use quebras de linha e emojis contextuais se aplicável. Apenas o texto da mensagem.

Seja premium, direto e "neon obsidian" (estilo elegante e agressivo).
PROMPT;

        $t0     = microtime(true);
        $result = $this->gemini->generate($systemPrompt, $userPrompt);
        $latMs  = (int) ((microtime(true) - $t0) * 1000);

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $followup['lead_id'] ?? null,
            operation:     'followup',
            contextSource: AnalysisTrace::SOURCE_DEFAULT,
            provider:      'gemini',
            model:         config('services.gemini.model', 'gemini-2.0-flash'),
            latencyMs:     $latMs
        );

        return $result;
    }

    // ─── Score Algorithm ─────────────────────────────────────────────

    public function calculateScore(array $lead): int
    {
        // Override manual tem prioridade máxima
        if (isset($lead['manual_score_override'])) {
            return (int) $lead['manual_score_override'];
        }

        $baseScore   = (int) ($lead['analysis']['priorityScore'] ?? $lead['fit_score'] ?? 0);
        $stageBonus  = config('operon.stage_bonuses')[$lead['pipeline_status']] ?? 0;

        $contextBonus = 0;
        $ctx = $lead['human_context'] ?? [];
        if (($ctx['timingStatus'] ?? '') === 'IMMEDIATE')   $contextBonus += 10;
        if (($ctx['timingStatus'] ?? '') === 'LONG_TERM')   $contextBonus -= 10;
        if (($ctx['temperature'] ?? '') === 'HOT')           $contextBonus += 10;
        if (($ctx['temperature'] ?? '') === 'COLD')          $contextBonus -= 15;
        if (($ctx['objectionCategory'] ?? '') === 'PRICE')   $contextBonus -= 15;
        if (($ctx['objectionCategory'] ?? '') === 'COMPETITOR') $contextBonus -= 20;

        return min(100, max(0, $baseScore + $stageBonus + $contextBonus));
    }
}
