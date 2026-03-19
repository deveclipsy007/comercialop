<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Helpers\AIResponseParser;
use App\Models\AnalysisTrace;
use App\Models\Lead;
use App\Services\AI\AIProviderFactory;

/**
 * Serviço central de análise de leads com IA.
 * Orquestra todas as operações de IA: qualificação, deep analysis, Operon 4D, SPIN, scripts.
 */
class LeadAnalysisService
{
    private SmartContextService $smartContext;
    private TokenService $tokens;

    public function __construct()
    {
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

    /**
     * Helper: executa uma chamada de IA JSON, registra tokens e trace.
     */
    private function executeJsonAI(
        string $operation,
        string $tenantId,
        string $systemPrompt,
        string $userPrompt,
        ?string $leadId = null,
        array $ragMeta = [],
        array $aiOptions = []
    ): array {
        $provider = AIProviderFactory::make($operation, $tenantId);

        $t0   = microtime(true);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, $aiOptions);
        $latMs = (int) ((microtime(true) - $t0) * 1000);

        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $this->tokens->consume(
            $operation, $tenantId,
            Session::get('id'),
            $provider->getProviderName(),
            $provider->getModel(),
            $usage['input'], $usage['output']
        );

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $leadId,
            operation:     $operation,
            contextSource: $ragMeta['source'] ?? 'default',
            queryText:     null,
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      $provider->getProviderName(),
            model:         $provider->getModel(),
            latencyMs:     $latMs,
            tokenCost:     $usage['input'] + $usage['output']
        );

        return $meta['parsed'] ?? $meta;
    }

    /**
     * Helper: executa uma chamada de IA texto livre, registra tokens e trace.
     */
    private function executeTextAI(
        string $operation,
        string $tenantId,
        string $systemPrompt,
        string $userPrompt,
        ?string $leadId = null,
        array $ragMeta = []
    ): string {
        $provider = AIProviderFactory::make($operation, $tenantId);

        $t0   = microtime(true);
        $meta = $provider->generateWithMeta($systemPrompt, $userPrompt);
        $latMs = (int) ((microtime(true) - $t0) * 1000);

        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $this->tokens->consume(
            $operation, $tenantId,
            Session::get('id'),
            $provider->getProviderName(),
            $provider->getModel(),
            $usage['input'], $usage['output']
        );

        AnalysisTrace::log(
            tenantId:      $tenantId,
            leadId:        $leadId,
            operation:     $operation,
            contextSource: $ragMeta['source'] ?? 'default',
            queryText:     null,
            chunksUsed:    array_map(fn($id) => ['chunk_id' => $id], $ragMeta['chunk_ids'] ?? []),
            provider:      $provider->getProviderName(),
            model:         $provider->getModel(),
            latencyMs:     $latMs,
            tokenCost:     $usage['input'] + $usage['output']
        );

        return $meta['text'] ?? '';
    }

    // ─── Lead Analysis (Qualificação) — 7 tokens ────────────────────

    public function analyzeLeadWithAI(array $lead, string $tenantId): array
    {
        // Usa contexto COMPLETO com dados enriquecidos (rating, reviews, social, horários, etc.)
        $enrichedContext = $this->smartContext->buildDeepIntelligenceContext($lead);
        $ragMeta         = $this->smartContext->getLastRetrievalMeta();

        $leadName = $lead['name'] ?? 'Lead';

        $systemPrompt = <<<PROMPT
Você é um Consultor de Inteligência Comercial Sênior da Operon, especializado em qualificar leads B2B e gerar diagnósticos que apoiam decisões de venda reais.

REGRAS ABSOLUTAS:
1. O FOCO é o lead "{$leadName}" como PROSPECT da Operon. Analise ELE, não apenas o negócio genérico.
2. USE TODOS os dados fornecidos: avaliações, reviews de clientes, redes sociais, horários, endereço, maturidade digital.
3. TRIANGULE sempre: Lead (quem é) + Empresa do Lead (contexto) + Operon (o que pode vender).
4. Seja PRAGMÁTICO e DIRETO. Cada insight deve ser útil para uma decisão de abordagem.
5. NÃO invente dados. Se algo não está disponível, infira com base no que existe e sinalize.
6. NÃO infle o score. Se o lead tem presença digital fraca, isso é OPORTUNIDADE para a Operon vender serviços.

CRITÉRIOS DE SCORE (seja rigoroso e realista):
- 80-100: Presença digital forte (site profissional, redes ativas, boas avaliações). Lead com alto fit mas MENOS necessidade imediata dos serviços Operon.
- 50-79: Presença digital parcial. Tem elementos mas com gaps claros. MELHOR faixa para abordagem — precisa da Operon.
- 20-49: Presença digital fraca ou inexistente. ALTA oportunidade de venda, mas pode ter resistência. Score de prioridade ajustado pela oportunidade.
- 0-19: Dados insuficientes ou lead claramente fora do ICP.

LÓGICA DO fitScore (aderência ao ICP da Operon):
- Score alto = lead se encaixa perfeitamente no perfil de cliente ideal da Operon.
- Score baixo = lead tem características que dificultam a venda ou fora do perfil.
PROMPT;

        $userPrompt = <<<PROMPT
{$enrichedContext}

Com base em TODOS os dados acima, gere um diagnóstico comercial profundo do lead "{$leadName}".

Retorne APENAS um JSON válido (sem markdown, sem comentários) com esta estrutura EXATA:
{
  "priorityScore": number (0-100, score geral de prioridade comercial),
  "fitScore": number (0-100, aderência ao ICP da Operon),
  "scoreExplanation": "Frase direta e específica sobre este lead explicando o porquê do score",
  "digitalMaturity": "Baixa" | "Média" | "Alta",
  "urgencyLevel": "Baixa" | "Média" | "Alta",
  "summary": "Parágrafo de 2-3 frases com visão geral do lead: quem é, onde está, qual o potencial. Menção ao nome do lead obrigatória.",
  "diagnosis": [
    "Problema crítico real e específico deste lead (não genérico)",
    "Segundo problema com evidência concreta dos dados",
    "Terceiro problema ou risco identificado"
  ],
  "opportunities": [
    "Oportunidade concreta de venda para a Operon com justificativa",
    "Segunda oportunidade com base nos dados do lead",
    "Terceira oportunidade (se aplicável)"
  ],
  "recommendations": [
    "Ação prática e específica para a equipe comercial da Operon",
    "Segunda recomendação de abordagem"
  ],
  "operonFit": "Parágrafo curto explicando como a Operon se encaixa neste lead: quais serviços fazem sentido, por que este lead precisa da Operon, qual o valor estimado do deal.",
  "extractedContact": {
    "phone": "telefone se disponível nos dados ou vazio",
    "address": "endereço se disponível nos dados ou vazio",
    "website": "site se disponível nos dados ou vazio"
  },
  "socialPresence": {
    "instagram": "handle ou URL se disponível ou vazio",
    "facebook": "handle ou URL se disponível ou vazio",
    "linkedin": "handle ou URL se disponível ou vazio"
  }
}
PROMPT;

        $result = $this->executeJsonAI(
            'lead_analysis', $tenantId,
            $systemPrompt, $userPrompt,
            $lead['id'], $ragMeta
        );

        if (!AIResponseParser::hasError($result)) {
            // Validação de qualidade mínima
            $result = $this->validateAndEnrich($result, $lead);
            Lead::saveAnalysis($lead['id'], $tenantId, $result);
        }

        return $result;
    }

    /**
     * Valida qualidade da resposta e preenche gaps com defaults inteligentes.
     */
    private function validateAndEnrich(array $result, array $lead): array
    {
        // Garantir score mínimo válido
        if (!isset($result['priorityScore']) || $result['priorityScore'] < 1) {
            $result['priorityScore'] = 25; // Default baixo, não zero
            $result['scoreExplanation'] = ($result['scoreExplanation'] ?? '') ?: 'Score atribuído automaticamente — dados insuficientes para avaliação precisa.';
        }

        if (!isset($result['fitScore']) || $result['fitScore'] < 1) {
            $result['fitScore'] = $result['priorityScore'];
        }

        // Garantir digitalMaturity
        if (empty($result['digitalMaturity']) || !in_array($result['digitalMaturity'], ['Baixa', 'Média', 'Alta'])) {
            $result['digitalMaturity'] = 'Baixa';
        }

        // Garantir urgencyLevel
        if (empty($result['urgencyLevel']) || !in_array($result['urgencyLevel'], ['Baixa', 'Média', 'Alta'])) {
            $result['urgencyLevel'] = 'Média';
        }

        // Garantir que diagnosis e opportunities não sejam vazios
        if (empty($result['diagnosis']) || !is_array($result['diagnosis'])) {
            $result['diagnosis'] = ['Presença digital não avaliada com profundidade — recomendável análise manual complementar.'];
        }

        if (empty($result['opportunities']) || !is_array($result['opportunities'])) {
            $leadName = $lead['name'] ?? 'Este lead';
            $result['opportunities'] = ["{$leadName} pode se beneficiar de uma consultoria inicial da Operon para mapear gaps digitais."];
        }

        // Garantir summary não vazio
        if (empty($result['summary'])) {
            $result['summary'] = sprintf(
                'Lead "%s" do segmento %s. Análise gerada com dados disponíveis limitados — recomenda-se enriquecimento adicional.',
                $lead['name'] ?? 'N/D',
                $lead['segment'] ?? 'não informado'
            );
        }

        // Garantir recommendations
        if (empty($result['recommendations']) || !is_array($result['recommendations'])) {
            $result['recommendations'] = ['Agendar um primeiro contato consultivo para entender melhor as necessidades do lead.'];
        }

        // Garantir operonFit
        if (empty($result['operonFit'])) {
            $result['operonFit'] = 'Lead dentro do perfil de atuação da Operon. Recomenda-se abordagem consultiva para mapear oportunidades.';
        }

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

        return $this->executeJsonAI('operon_diagnostico', $tenantId, $systemPrompt, $userPrompt, $lead['id'], $ragMeta);
    }

    // ─── Deep Insights (Value Proposition, Target Audience, Competitors) ─────

    public function runDeepInsights(array $lead, string $tenantId): array
    {
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

        $result = $this->executeJsonAI('deep_analysis', $tenantId, $systemPrompt, $userPrompt, $lead['id'], $ragMeta);

        if (!AIResponseParser::hasError($result)) {
            $analysis = $lead['analysis'] ?? [];
            $analysis['valueProposition'] = $result['valueProposition'] ?? null;
            $analysis['targetAudience']   = $result['targetAudience'] ?? null;
            $analysis['competitors']      = $result['competitors'] ?? [];
            Lead::update($lead['id'], $tenantId, ['analysis' => $analysis]);
        }

        return $result;
    }

    private function runPotencialComercial(array $lead, string $systemPrompt, string $tenantId, array $ragMeta = []): array
    {
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

        return $this->executeJsonAI('operon_potencial', $tenantId, $systemPrompt, $userPrompt, $lead['id'], $ragMeta);
    }

    private function runAutoridadeLocal(array $lead, string $systemPrompt, string $tenantId, array $ragMeta = []): array
    {
        $userPrompt = <<<PROMPT
Analise a autoridade local de "{$lead['name']}". Localização: {$lead['address']}.
Retorne APENAS JSON válido:
{"status":"forte"|"moderado"|"fraco"|"inexistente","score_autoridade":0,"comparacao_setor":"string","metricas":{"avaliacao_atual":0,"total_avaliacoes":0,"media_setor":0},"impacto_faturamento":"string","acoes_melhoria":[{"acao":"string","impacto":"alto"|"medio"|"baixo"}]}
PROMPT;

        return $this->executeJsonAI('operon_autoridade', $tenantId, $systemPrompt, $userPrompt, $lead['id'], $ragMeta);
    }

    private function runScriptAbordagem(array $lead, string $systemPrompt, string $tenantId, array $ragMeta = []): string
    {
        $userPrompt = <<<PROMPT
Crie um script de abordagem para WhatsApp para "{$lead['name']}".
Formato: 1. Abertura personalizada. 2. Gancho da dor. 3. Proposta de valor. 4. CTA direto.
IMPORTANTE: Máximo 150 palavras. Tom consultivo mas direto. Pronto para copiar e colar no WhatsApp.
PROMPT;

        return $this->executeTextAI('operon_script', $tenantId, $systemPrompt, $userPrompt, $lead['id'], $ragMeta);
    }

    // ─── SPIN Framework — 4 tokens ──────────────────────────────────

    public function generateSpin(array $lead, string $tenantId): array
    {
        $maturity = $lead['analysis']['digitalMaturity'] ?? 'Média';
        $agency   = $this->smartContext->loadCompanyProfile($tenantId);

        $services = implode(', ', $agency['offer_services'] ?? []);
        $diffs    = implode(', ', $agency['differentials'] ?? []);
        $icpPains = implode(', ', $agency['icp_pain_points'] ?? []);

        $systemPrompt = <<<PROMPT
Você é um consultor de vendas high-ticket especialista no framework SPIN, trabalhando DENTRO da empresa "{$agency['name']}".

CONTEXTO DA EMPRESA:
- Oferta: {$agency['offer_title']}
- Serviços: {$services}
- Diferenciais: {$diffs}
- Proposta de valor: {$agency['unique_proposal']}
- Dores típicas do ICP: {$icpPains}

Suas perguntas SPIN devem ser construídas para VENDER os serviços desta empresa. As implicações devem levar o lead a perceber que precisa dos serviços acima.
PROMPT;

        $userPrompt   = <<<PROMPT
LEAD: {$lead['name']}
SEGMENTO: {$lead['segment']}
MATURIDADE DIGITAL: {$maturity}

Crie 3 perguntas para cada fase do SPIN (Situação, Problema, Implicação, Necessidade de Solução).
As perguntas devem ser específicas para o nicho de {$lead['segment']} e direcionadas para vender os serviços de "{$agency['name']}".

Retorne JSON estrito:
{"s":["Pergunta 1","Pergunta 2","Pergunta 3"],"p":["..."],"i":["..."],"n":["..."]}
PROMPT;

        return $this->executeJsonAI('spin_questions', $tenantId, $systemPrompt, $userPrompt, $lead['id'] ?? null, [], ['json_mode' => true]);
    }

    // ─── Script Variations — 7 tokens ───────────────────────────────

    public function generateScriptVariations(array $lead, string $tenantId): array
    {
        $score    = $lead['analysis']['priorityScore'] ?? 50;
        $maturity = $lead['analysis']['digitalMaturity'] ?? 'Média';
        $agency   = $this->smartContext->loadCompanyProfile($tenantId);

        $services = implode(', ', $agency['offer_services'] ?? []);

        $systemPrompt = $this->baseSystemPrompt() . <<<PROMPT


CONTEXTO DA SUA EMPRESA:
Empresa: {$agency['name']}
Oferta: {$agency['offer_title']}
Preço: {$agency['offer_base_price']}
Serviços: {$services}
Proposta única: {$agency['unique_proposal']}
Garantias: {$agency['guarantees']}

REGRA: Todos os scripts devem vender os serviços DESTA empresa, usando os diferenciais e proposta de valor acima. O tom deve refletir o posicionamento da empresa.
PROMPT;

        $userPrompt   = <<<PROMPT
Crie variações de script de abordagem para este lead:
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Score: {$score}
Maturidade Digital: {$maturity}

Para cada canal, crie um script curto e persuasivo (máx 80 palavras) que venda os serviços de "{$agency['name']}":
Retorne JSON: {"whatsapp":"string","linkedin":"string","email":"string","coldCall":"string"}
PROMPT;

        return $this->executeJsonAI('script_variations', $tenantId, $systemPrompt, $userPrompt, $lead['id'] ?? null);
    }

    // ─── Follow-up Message Generation ─────────────────────────────────

    public function generateFollowupMessage(array $followup, string $tenantId): string
    {
        $agency = $this->smartContext->loadCompanyProfile($tenantId);

        $services = implode(', ', $agency['offer_services'] ?? []);
        $diffs    = implode('; ', $agency['differentials'] ?? []);

        $systemPrompt = $this->baseSystemPrompt() . <<<PROMPT


CONTEXTO DA SUA EMPRESA:
Empresa: {$agency['name']}
Oferta: {$agency['offer_title']}
Preço: {$agency['offer_base_price']}
Serviços: {$services}
Proposta única: {$agency['unique_proposal']}
Diferenciais: {$diffs}

REGRA: O follow-up deve refletir o posicionamento e a linguagem desta empresa. Use os diferenciais e a proposta de valor como argumentos naturais na mensagem.
PROMPT;

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

Seja premium, direto e elegante.
PROMPT;

        return $this->executeTextAI('followup_message', $tenantId, $systemPrompt, $userPrompt, $followup['lead_id'] ?? null);
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
