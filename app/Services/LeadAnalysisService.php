<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
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
        $lead = $this->hydrateLeadForAnalysis($lead, $tenantId);

        // Usa contexto completo do lead sem leitura de redes sociais.
        $enrichedContext = $this->smartContext->buildDeepIntelligenceContext(
            $lead,
            [],
            ['include_social_signals' => false]
        );
        $ragMeta         = $this->smartContext->getLastRetrievalMeta();

        $leadName = $lead['name'] ?? 'Lead';
        $agencyProfile = $this->smartContext->loadCompanyProfile($tenantId);
        $agencyName = trim((string) ($agencyProfile['name'] ?? 'sua empresa'));
        $serviceCatalog = $this->extractAgencyServiceCatalog($agencyProfile);
        $serviceCatalogText = !empty($serviceCatalog)
            ? implode('; ', $serviceCatalog)
            : 'Use a oferta principal e os diferenciais da empresa vendedora, sem inventar novos serviços.';

        $systemPrompt = <<<PROMPT
Você é um Consultor de Inteligência Comercial Sênior da {$agencyName}, especializado em qualificar leads B2B e gerar diagnósticos que apoiam decisões de venda reais.

REGRAS ABSOLUTAS:
1. O FOCO é o lead "{$leadName}" como PROSPECT da {$agencyName}. Analise ELE, não apenas o negócio genérico.
2. USE TODOS os dados fornecidos sobre site, Google, reviews, dados públicos, horários, endereço, telefone, categoria, timeline e contexto comercial.
3. TRIANGULE sempre: Lead (quem é) + Empresa do Lead (contexto) + {$agencyName} (o que pode vender).
4. Seja PRAGMÁTICO e DIRETO. Cada insight deve ser útil para uma decisão de abordagem.
5. TODA conclusão precisa estar ancorada em pelo menos um sinal concreto do contexto: site, avaliações, review_count, endereço, etapa atual, notas do vendedor, timeline, telefone, horário, categoria, follow-up, etc.
6. NÃO invente dados. Se algo não está disponível, deixe isso explícito em "missingInformation" e não preencha com suposição.
7. NÃO infle o score. Se o lead tem site fraco, site ausente ou dados públicos incompletos, isso é OPORTUNIDADE para a {$agencyName} vender serviços, mas com leitura realista.
8. Quando conectar com a proposta da {$agencyName}, use apenas os serviços reais informados no contexto. Não invente entregáveis, cases ou promessas.
9. Evite linguagem vazia como "pode melhorar a presença digital" ou "falta rede social". Diga exatamente qual é o gap estrutural do site, do Google, da autoridade local, dos dados públicos ou da operação comercial.
10. PROIBIDO analisar redes sociais neste diagnóstico. Não use Instagram, Facebook, LinkedIn, seguidores, frequência de postagem ou ausência de social como argumento, problema ou oportunidade.
11. Priorize evidências vindas do Google, do site, dos dados presentes no lead e da pesquisa pública disponível no provedor.

CRITÉRIOS DE SCORE (seja rigoroso e realista):
- 80-100: Estrutura web e autoridade pública fortes (site profissional, boas avaliações, dados públicos consistentes). Lead com alto fit mas MENOS necessidade imediata dos serviços da {$agencyName}.
- 50-79: Estrutura parcial. Tem elementos relevantes, mas com gaps claros em site, autoridade local, dados públicos ou clareza comercial. MELHOR faixa para abordagem.
- 20-49: Site fraco/inexistente ou base pública muito limitada. ALTA oportunidade de venda, mas pode ter resistência. Score de prioridade ajustado pela oportunidade.
- 0-19: Dados insuficientes ou lead claramente fora do ICP.

LÓGICA DO fitScore (aderência ao ICP da {$agencyName}):
- Score alto = lead se encaixa perfeitamente no perfil de cliente ideal da {$agencyName}.
- Score baixo = lead tem características que dificultam a venda ou fora do perfil.
PROMPT;

        $userPrompt = <<<PROMPT
{$enrichedContext}

===== CATÁLOGO REAL DA OPERAÇÃO COMERCIAL =====
Serviços que podem ser sugeridos se fizerem sentido para este lead: {$serviceCatalogText}

Com base em TODOS os dados acima, gere um diagnóstico comercial profundo do lead "{$leadName}".

A análise precisa responder com clareza:
- O que está acontecendo hoje com este lead, especificamente.
- Quais sinais concretos mostram dores, gargalos ou janelas de oportunidade com base no site, no Google, nos dados presentes e na pesquisa pública.
- Como isso se conecta com a proposta da {$agencyName} de forma convincente.
- Qual deve ser a narrativa de abordagem, sem soar genérica.
- Que informação ainda falta para elevar a assertividade.

Retorne APENAS um JSON válido (sem markdown, sem comentários) com esta estrutura EXATA:
{
  "priorityScore": number (0-100, score geral de prioridade comercial),
  "fitScore": number (0-100, aderência ao ICP da {$agencyName}),
  "scoreExplanation": "Frase direta e específica sobre este lead explicando o porquê do score",
  "digitalMaturity": "Baixa" | "Média" | "Alta" (interprete como maturidade estrutural do site, da presença no Google e dos dados públicos, nunca como social media),
  "urgencyLevel": "Baixa" | "Média" | "Alta",
  "summary": "Parágrafo denso de 3-4 frases com visão realmente específica do lead: quem é, momento atual, principal alavanca comercial e por que vale ou não a pena abordar agora. Menção ao nome do lead obrigatória.",
  "leadSituation": {
    "businessSnapshot": "Leitura objetiva da situação atual do lead, baseada em sinais reais",
    "commercialMoment": "Leitura do momento comercial e do timing do lead",
    "valueHypothesis": "Qual hipótese de valor mais faz sentido para este lead hoje"
  },
  "diagnosis": [
    {
      "title": "Título curto do problema",
      "detail": "Explicação específica sobre o problema deste lead",
      "evidence": "Sinal concreto que sustenta essa leitura"
    }
  ],
  "opportunities": [
    {
      "title": "Título curto da oportunidade",
      "detail": "Explicação da oportunidade comercial",
      "impact": "Impacto esperado para o lead ou para a abordagem",
      "evidence": "Sinal concreto que sustenta essa oportunidade"
    }
  ],
  "recommendations": [
    "Ação prática e específica para a equipe comercial da {$agencyName}",
    "Segunda recomendação de abordagem"
  ],
  "proposalConnection": {
    "coreNarrative": "Explique de forma convincente como conectar a situação do lead com a proposta da {$agencyName}",
    "whyNow": "Por que este lead deve ser abordado agora",
    "positioningAngle": "Ângulo de posicionamento ideal para a {$agencyName} na conversa",
    "dealPotential": "Leitura honesta do potencial comercial e do tamanho provável da oportunidade",
    "recommendedServices": [
      {
        "service": "Nome exato de um serviço do catálogo real da {$agencyName}",
        "reason": "Por que esse serviço faz sentido para este lead",
        "priority": "Alta" | "Média" | "Baixa",
        "expectedOutcome": "Resultado esperado se esse serviço for vendido"
      }
    ]
  },
  "approachPlan": {
    "openingHook": "Gancho inicial personalizado para iniciar a conversa com este lead",
    "discoveryFocus": [
      "Pergunta ou ponto de diagnóstico que a equipe comercial deve explorar"
    ],
    "objectionHandling": [
      "Como antecipar ou tratar objeções prováveis deste lead"
    ],
    "nextStepCTA": "Próximo passo recomendado para avançar a negociação"
  },
  "risksAndObjections": [
    "Risco comercial ou objeção provável deste lead"
  ],
  "missingInformation": [
    "Informação importante que não foi encontrada e faz falta para refinar a abordagem"
  ],
  "evidence": [
    "Sinal factual relevante usado na análise"
  ],
  "operonFit": "Parágrafo curto explicando como a {$agencyName} se encaixa neste lead: quais serviços fazem sentido, por que este lead precisa da {$agencyName}, qual o valor estimado do deal.",
  "extractedContact": {
    "phone": "telefone se disponível nos dados ou vazio",
    "address": "endereço se disponível nos dados ou vazio",
    "website": "site se disponível nos dados ou vazio"
  }
}
PROMPT;

        $result = $this->executeJsonAI(
            'lead_analysis', $tenantId,
            $systemPrompt, $userPrompt,
            $lead['id'], $ragMeta,
            ['temperature' => 0.2, 'max_tokens' => 5500, 'google_search' => true]
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
            $agencyName = trim((string) ($this->smartContext->loadCompanyProfile((string) ($lead['tenant_id'] ?? ''))['name'] ?? 'sua empresa'));
            $result['opportunities'] = ["{$leadName} pode se beneficiar de uma consultoria inicial da {$agencyName} para mapear gaps digitais."];
        }

        // Garantir summary não vazio
        if (empty($result['summary'])) {
            $result['summary'] = sprintf(
                'Lead "%s" do segmento %s. Análise gerada com dados disponíveis limitados — recomenda-se enriquecimento adicional.',
                $lead['name'] ?? 'N/D',
                $lead['segment'] ?? 'não informado'
            );
        }

        $result['leadSituation'] = $this->normalizeLeadSituation($result['leadSituation'] ?? null, $lead, $result);
        $result['diagnosis'] = $this->normalizeInsightCollection($result['diagnosis'] ?? null, $this->buildFallbackDiagnosis($lead));
        $result['opportunities'] = $this->normalizeInsightCollection($result['opportunities'] ?? null, $this->buildFallbackOpportunities($lead), true);

        // Garantir recommendations
        $result['recommendations'] = $this->normalizeStringList(
            $result['recommendations'] ?? null,
            ['Agendar um primeiro contato consultivo para entender melhor as necessidades do lead e validar as dores prioritárias.'],
            5
        );

        // Garantir operonFit
        if (empty($result['operonFit'])) {
            $agencyName = trim((string) ($this->smartContext->loadCompanyProfile((string) ($lead['tenant_id'] ?? ''))['name'] ?? 'sua empresa'));
            $result['operonFit'] = "Lead dentro do perfil de atuação da {$agencyName}. Recomenda-se abordagem consultiva para mapear oportunidades.";
        }

        $result['proposalConnection'] = $this->normalizeProposalConnection(
            $result['proposalConnection'] ?? null,
            $lead,
            $result
        );

        $result['approachPlan'] = $this->normalizeApproachPlan(
            $result['approachPlan'] ?? null,
            $lead,
            $result
        );

        $result['risksAndObjections'] = $this->normalizeStringList(
            $result['risksAndObjections'] ?? null,
            $this->buildFallbackRisks($lead),
            5
        );

        $result['missingInformation'] = $this->normalizeStringList(
            $result['missingInformation'] ?? null,
            $this->buildMissingInformationList($lead),
            6
        );

        $result['evidence'] = $this->normalizeStringList(
            $result['evidence'] ?? null,
            $this->buildLeadEvidence($lead),
            6
        );

        $result['extractedContact'] = $this->normalizeContactPayload($result['extractedContact'] ?? null, $lead);

        return $result;
    }

    private function hydrateLeadForAnalysis(array $lead, string $tenantId): array
    {
        if (empty($lead['id'])) {
            return $lead;
        }

        $activities = Database::select(
            "SELECT type, title, content, created_at
             FROM lead_activities
             WHERE tenant_id = ? AND lead_id = ?
             ORDER BY created_at DESC
             LIMIT 6",
            [$tenantId, (string) $lead['id']]
        );

        $lead['recent_activities'] = array_map(
            static function (array $activity): array {
                return [
                    'type' => $activity['type'] ?? '',
                    'title' => trim((string) ($activity['title'] ?? '')),
                    'content' => mb_substr(trim((string) ($activity['content'] ?? '')), 0, 220),
                    'created_at' => $activity['created_at'] ?? '',
                ];
            },
            $activities
        );

        return $lead;
    }

    private function extractAgencyServiceCatalog(array $agencyProfile): array
    {
        $services = [];
        $servicesFull = $agencyProfile['services_full'] ?? [];
        if (is_string($servicesFull)) {
            $servicesFull = json_decode($servicesFull, true) ?? [];
        }

        if (is_array($servicesFull)) {
            foreach ($servicesFull as $service) {
                if (!is_array($service)) {
                    continue;
                }

                $name = trim((string) ($service['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $line = $name;
                $description = trim((string) ($service['description'] ?? ''));
                $priceRange = trim((string) ($service['price_range'] ?? ''));

                if ($description !== '') {
                    $line .= ' — ' . $description;
                }
                if ($priceRange !== '') {
                    $line .= ' (' . $priceRange . ')';
                }

                $services[] = $line;
            }
        }

        if (empty($services)) {
            $raw = $agencyProfile['offer_services'] ?? [];
            if (is_string($raw)) {
                $raw = json_decode($raw, true) ?? [$raw];
            }

            if (is_array($raw)) {
                foreach ($raw as $service) {
                    $serviceName = trim((string) (is_array($service) ? ($service['name'] ?? '') : $service));
                    if ($serviceName !== '') {
                        $services[] = $serviceName;
                    }
                }
            }
        }

        return array_values(array_unique($services));
    }

    private function normalizeLeadSituation(mixed $value, array $lead, array $result): array
    {
        $value = is_array($value) ? $value : [];
        $gapLabel = $this->inferPrimaryGap($lead);
        $stageLabel = stageLabel($lead['pipeline_status'] ?? Lead::STAGE_NEW);
        $ctx = is_array($lead['human_context'] ?? null) ? $lead['human_context'] : [];
        $temperature = $ctx['temperature'] ?? '';
        $segment = trim((string) ($lead['segment'] ?? 'segmento não informado'));
        $leadName = trim((string) ($lead['name'] ?? 'Este lead'));
        $agencyName = trim((string) ($this->smartContext->loadCompanyProfile((string) ($lead['tenant_id'] ?? ''))['name'] ?? 'sua empresa'));

        $fallback = [
            'businessSnapshot' => sprintf(
                '%s atua no segmento %s e hoje aparece no Vault em %s, com %s como principal ponto de atenção visível nos dados disponíveis.',
                $leadName,
                $segment,
                $stageLabel,
                $gapLabel
            ),
            'commercialMoment' => $this->buildCommercialMomentFallback($lead, $temperature),
            'valueHypothesis' => sprintf(
                'A hipótese de valor mais forte para %s é conectar %s com uma entrega da %s que reduza atrito comercial e aumente previsibilidade de captação.',
                $leadName,
                $gapLabel,
                $agencyName
            ),
        ];

        return [
            'businessSnapshot' => $this->normalizeText($value['businessSnapshot'] ?? null, $fallback['businessSnapshot']),
            'commercialMoment' => $this->normalizeText($value['commercialMoment'] ?? null, $fallback['commercialMoment']),
            'valueHypothesis' => $this->normalizeText($value['valueHypothesis'] ?? null, $fallback['valueHypothesis']),
        ];
    }

    private function normalizeInsightCollection(mixed $items, array $fallback, bool $withImpact = false): array
    {
        if (!is_array($items) || empty($items)) {
            return $fallback;
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $text = trim($item);
                if ($text === '') {
                    continue;
                }

                $normalized[] = [
                    'title' => $this->deriveShortTitle($text),
                    'detail' => $text,
                    'evidence' => '',
                ];
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $title = $this->normalizeText($item['title'] ?? $item['headline'] ?? null);
            $detail = $this->normalizeText($item['detail'] ?? $item['description'] ?? $item['reason'] ?? null);
            $evidence = $this->normalizeText($item['evidence'] ?? $item['signal'] ?? $item['proof'] ?? null);
            $impact = $this->normalizeText($item['impact'] ?? $item['expectedOutcome'] ?? null);

            if ($title === '' && $detail === '') {
                continue;
            }

            if ($detail === '') {
                $detail = $title;
            }

            if ($title === '') {
                $title = $this->deriveShortTitle($detail);
            }

            $entry = [
                'title' => $title,
                'detail' => $detail,
                'evidence' => $evidence,
            ];

            if ($withImpact && $impact !== '') {
                $entry['impact'] = $impact;
            }

            $normalized[] = $entry;
        }

        if (empty($normalized)) {
            return $fallback;
        }

        return array_slice($normalized, 0, 4);
    }

    private function normalizeProposalConnection(mixed $value, array $lead, array $result): array
    {
        $value = is_array($value) ? $value : [];
        $leadName = trim((string) ($lead['name'] ?? 'este lead'));
        $gapLabel = $this->inferPrimaryGap($lead);
        $agencyName = trim((string) ($this->smartContext->loadCompanyProfile((string) ($lead['tenant_id'] ?? ''))['name'] ?? 'sua empresa'));
        $ctx = is_array($lead['human_context'] ?? null) ? $lead['human_context'] : [];
        $recommendedServices = $this->normalizeRecommendedServices($value['recommendedServices'] ?? null, $lead);

        return [
            'coreNarrative' => $this->normalizeText(
                $value['coreNarrative'] ?? null,
                sprintf(
                    'A conexão mais forte com %s está em mostrar como a %s consegue atacar %s com uma entrega consultiva e orientada a resultado, em vez de uma proposta genérica de marketing.',
                    $leadName,
                    $agencyName,
                    $gapLabel
                )
            ),
            'whyNow' => $this->normalizeText(
                $value['whyNow'] ?? null,
                $this->buildCommercialMomentFallback($lead, $ctx['temperature'] ?? '')
            ),
            'positioningAngle' => $this->normalizeText(
                $value['positioningAngle'] ?? null,
                sprintf(
                    'Posicionar a %s como parceira de clareza comercial e tração, conectando diagnóstico real do lead com um plano objetivo de execução.',
                    $agencyName
                )
            ),
            'dealPotential' => $this->normalizeText(
                $value['dealPotential'] ?? null,
                $result['operonFit'] ?? 'Potencial comercial a validar em reunião diagnóstica.'
            ),
            'recommendedServices' => $recommendedServices,
        ];
    }

    private function normalizeApproachPlan(mixed $value, array $lead, array $result): array
    {
        $value = is_array($value) ? $value : [];
        $leadName = trim((string) ($lead['name'] ?? 'este lead'));
        $gapLabel = $this->inferPrimaryGap($lead);

        return [
            'openingHook' => $this->normalizeText(
                $value['openingHook'] ?? null,
                sprintf(
                    'Quero te mostrar rapidamente onde %s pode estar perdendo oportunidade hoje, principalmente em %s, e como isso pode ser revertido com uma abordagem mais estruturada.',
                    $leadName,
                    $gapLabel
                )
            ),
            'discoveryFocus' => $this->normalizeStringList(
                $value['discoveryFocus'] ?? null,
                [
                    'Entender como o lead gera demanda hoje e onde sente maior gargalo comercial.',
                    'Validar se a percepção interna do negócio bate com os sinais visíveis no digital.',
                    'Mapear urgência, capacidade de execução e nível de decisão de quem vai conduzir a conversa.',
                ],
                4
            ),
            'objectionHandling' => $this->normalizeStringList(
                $value['objectionHandling'] ?? null,
                $this->buildFallbackObjectionHandling($lead),
                4
            ),
            'nextStepCTA' => $this->normalizeText(
                $value['nextStepCTA'] ?? null,
                'Convidar para uma conversa diagnóstica curta, com foco em mapear os gargalos visíveis e priorizar o primeiro movimento de execução.'
            ),
        ];
    }

    private function normalizeRecommendedServices(mixed $items, array $lead): array
    {
        if (!is_array($items) || empty($items)) {
            return $this->buildFallbackRecommendedServices($lead);
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $service = $this->normalizeText($item['service'] ?? $item['name'] ?? null);
            $reason = $this->normalizeText($item['reason'] ?? $item['detail'] ?? null);
            $priority = $this->normalizePriorityLabel($item['priority'] ?? 'Média');
            $expectedOutcome = $this->normalizeText($item['expectedOutcome'] ?? $item['impact'] ?? null);

            if ($service === '' || $reason === '') {
                continue;
            }

            $normalized[] = [
                'service' => $service,
                'reason' => $reason,
                'priority' => $priority,
                'expectedOutcome' => $expectedOutcome,
            ];
        }

        if (empty($normalized)) {
            return $this->buildFallbackRecommendedServices($lead);
        }

        return array_slice($normalized, 0, 4);
    }

    private function normalizeStringList(mixed $items, array $fallback = [], int $limit = 5): array
    {
        if (!is_array($items) || empty($items)) {
            return array_slice($fallback, 0, $limit);
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $text = trim($item);
            } elseif (is_array($item)) {
                $text = $this->normalizeText(
                    $item['text'] ?? $item['title'] ?? $item['detail'] ?? $item['description'] ?? $item['reason'] ?? $item['evidence'] ?? null
                );
            } else {
                $text = '';
            }

            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        if (empty($normalized)) {
            return array_slice($fallback, 0, $limit);
        }

        return array_slice(array_values(array_unique($normalized)), 0, $limit);
    }

    private function normalizeContactPayload(mixed $value, array $lead): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'phone' => $this->normalizeText($value['phone'] ?? null, trim((string) ($lead['phone'] ?? ''))),
            'address' => $this->normalizeText($value['address'] ?? null, trim((string) ($lead['address'] ?? ''))),
            'website' => $this->normalizeText($value['website'] ?? null, trim((string) ($lead['website'] ?? ''))),
        ];
    }

    private function buildFallbackDiagnosis(array $lead): array
    {
        $items = [];
        $reviewCount = (int) ($lead['review_count'] ?? 0);
        $hasWebsite = trim((string) ($lead['website'] ?? '')) !== '';
        $hasPhone = trim((string) ($lead['phone'] ?? '')) !== '';
        $hasAddress = trim((string) ($lead['address'] ?? '')) !== '';

        if (!$hasWebsite) {
            $items[] = [
                'title' => 'Ativo próprio de conversão ausente',
                'detail' => 'O lead não apresenta site identificado, o que reduz controle sobre aquisição, credibilidade e captura de demanda fora de canais terceirizados.',
                'evidence' => 'Website não encontrado nos dados atuais do lead.',
            ];
        }

        if ($reviewCount > 0 && $reviewCount < 20) {
            $items[] = [
                'title' => 'Prova social ainda curta',
                'detail' => 'O lead já tem presença no Google, mas com volume limitado de avaliações, o que enfraquece confiança e autoridade competitiva na decisão de compra.',
                'evidence' => sprintf('Apenas %d avaliação(ões) registradas no Google.', $reviewCount),
            ];
        }

        if (!$hasPhone || !$hasAddress) {
            $items[] = [
                'title' => 'Dados públicos incompletos',
                'detail' => 'Parte dos dados públicos do lead ainda está incompleta, o que reduz clareza operacional para uma abordagem mais precisa e para uma prova inicial de autoridade.',
                'evidence' => 'Telefone e/ou endereço não estão completos no cadastro atual.',
            ];
        }

        if (empty($items)) {
            $items[] = [
                'title' => 'Diagnóstico depende de aprofundamento comercial',
                'detail' => 'Os sinais atuais mostram um lead com base mínima identificada, mas ainda sem clareza suficiente sobre funil, aquisição e operação comercial interna.',
                'evidence' => 'Os dados existentes não expõem com profundidade a máquina comercial do lead.',
            ];
        }

        return array_slice($items, 0, 3);
    }

    private function buildFallbackOpportunities(array $lead): array
    {
        $items = [];
        $leadName = trim((string) ($lead['name'] ?? 'Este lead'));
        $reviewCount = (int) ($lead['review_count'] ?? 0);

        if (trim((string) ($lead['website'] ?? '')) === '') {
            $items[] = [
                'title' => 'Estruturar ativo web próprio',
                'detail' => sprintf('Existe espaço para vender uma estrutura web própria para %s, reduzindo dependência de descoberta passiva e aumentando previsibilidade comercial.', $leadName),
                'impact' => 'Mais controle sobre geração de demanda e conversão.',
                'evidence' => 'Site não identificado nos dados do lead.',
            ];
        }

        if ($reviewCount < 30) {
            $items[] = [
                'title' => 'Ganhar autoridade local com prova social',
                'detail' => 'Há oportunidade de trabalhar reputação e posicionamento local como forma de elevar confiança e diferencial competitivo.',
                'impact' => 'Melhora percepção de marca e eficiência na abordagem comercial.',
                'evidence' => sprintf('Volume atual de avaliações no Google: %d.', $reviewCount),
            ];
        }

        if (empty($items)) {
            $items[] = [
                'title' => 'Aprofundar diagnóstico para proposta sob medida',
                'detail' => sprintf('O melhor movimento com %s é transformar os sinais já existentes em uma proposta mais específica e baseada em prioridade real de negócio.', $leadName),
                'impact' => 'Aumenta aderência da proposta e reduz percepção de abordagem genérica.',
                'evidence' => 'O lead já possui dados mínimos para uma conversa diagnóstica consistente.',
            ];
        }

        return array_slice($items, 0, 3);
    }

    private function buildFallbackRisks(array $lead): array
    {
        $risks = [];
        $ctx = is_array($lead['human_context'] ?? null) ? $lead['human_context'] : [];

        if (($ctx['objectionCategory'] ?? '') === 'PRICE') {
            $risks[] = 'Sensibilidade a preço pode aparecer cedo na conversa; vale defender retorno e priorização, não pacote fechado logo na abertura.';
        }

        if (($ctx['timingStatus'] ?? '') === 'LONG_TERM') {
            $risks[] = 'O timing percebido está mais longo, então a abordagem precisa gerar urgência racional sem pressionar demais.';
        }

        if (trim((string) ($lead['website'] ?? '')) === '') {
            $risks[] = 'Sem site identificado, o lead pode estar operando com menor estrutura web e exigir uma venda mais consultiva e educativa.';
        }

        if (empty($risks)) {
            $risks[] = 'A maior objeção provável é o lead não perceber claramente o custo atual dos gaps digitais/comerciais identificados.';
        }

        return array_slice($risks, 0, 4);
    }

    private function buildMissingInformationList(array $lead): array
    {
        $missing = [];
        if (trim((string) ($lead['website'] ?? '')) === '') {
            $missing[] = 'Site oficial não identificado.';
        }
        if (trim((string) ($lead['phone'] ?? '')) === '') {
            $missing[] = 'Telefone principal não confirmado.';
        }
        if (trim((string) ($lead['email'] ?? '')) === '') {
            $missing[] = 'E-mail comercial não encontrado.';
        }

        if ((int) ($lead['review_count'] ?? 0) === 0) {
            $missing[] = 'Não há base mínima de avaliações para ler reputação com segurança.';
        }

        if (empty($lead['recent_activities'])) {
            $missing[] = 'Sem histórico recente de interação comercial registrado no lead.';
        }

        return array_slice($missing, 0, 6);
    }

    private function buildLeadEvidence(array $lead): array
    {
        $evidence = [];

        $website = trim((string) ($lead['website'] ?? ''));
        $phone = trim((string) ($lead['phone'] ?? ''));
        $address = trim((string) ($lead['address'] ?? ''));
        $rating = trim((string) ($lead['rating'] ?? ''));
        $reviewCount = (int) ($lead['review_count'] ?? 0);

        $evidence[] = $website !== '' ? 'Website identificado para o lead.' : 'Website não identificado nos dados atuais.';
        if ($phone !== '') {
            $evidence[] = 'Telefone principal disponível para abordagem.';
        }
        if ($address !== '') {
            $evidence[] = 'Endereço/localização do negócio presente no cadastro.';
        }
        if ($rating !== '' || $reviewCount > 0) {
            $evidence[] = sprintf('Google com nota %s e %d avaliação(ões).', $rating !== '' ? $rating : 'não informada', $reviewCount);
        }

        $ctx = is_array($lead['human_context'] ?? null) ? $lead['human_context'] : [];
        if (!empty($ctx['temperature'])) {
            $evidence[] = 'Temperatura do lead registrada como ' . $ctx['temperature'] . '.';
        }
        if (!empty($ctx['timingStatus'])) {
            $evidence[] = 'Timing comercial marcado como ' . $ctx['timingStatus'] . '.';
        }
        if (!empty($lead['next_followup_at'])) {
            $evidence[] = 'Existe follow-up agendado para este lead.';
        }
        if (!empty($lead['recent_activities']) && is_array($lead['recent_activities'])) {
            $evidence[] = 'Há timeline comercial recente registrada no perfil.';
        }

        return array_slice(array_values(array_unique($evidence)), 0, 6);
    }

    private function buildFallbackObjectionHandling(array $lead): array
    {
        $ctx = is_array($lead['human_context'] ?? null) ? $lead['human_context'] : [];
        $objection = $ctx['objectionCategory'] ?? '';

        if ($objection === 'PRICE') {
            return [
                'Se o lead trouxer preço cedo, deslocar a conversa para custo de oportunidade e priorização do gargalo mais caro.',
                'Ancorar a proposta em etapas ou em um primeiro sprint para reduzir percepção de risco.',
            ];
        }

        if ($objection === 'COMPETITOR') {
            return [
                'Trazer diferenciação por diagnóstico e execução sob medida, não por comparação superficial com concorrente.',
                'Explorar o que hoje ainda não está funcionando, em vez de disputar apenas narrativa de autoridade.',
            ];
        }

        return [
            'Validar primeiro o problema percebido pelo lead antes de apresentar qualquer solução mais ampla.',
            'Conectar a proposta com um ganho tangível e próximo, para reduzir resistência inicial.',
        ];
    }

    private function buildFallbackRecommendedServices(array $lead): array
    {
        $agencyProfile = $this->smartContext->loadCompanyProfile((string) ($lead['tenant_id'] ?? ''));
        $catalog = $this->extractAgencyServiceCatalog($agencyProfile);
        $gapLabel = $this->inferPrimaryGap($lead);

        if (empty($catalog)) {
            return [];
        }

        $items = [];
        foreach (array_slice($catalog, 0, 2) as $index => $service) {
            $serviceName = trim((string) preg_replace('/\s+—.*$/u', '', $service));
            $serviceName = trim((string) preg_replace('/\s+\(.+\)$/', '', $serviceName));
            $items[] = [
                'service' => $serviceName !== '' ? $serviceName : $service,
                'reason' => sprintf('Conecta diretamente com o gap mais visível hoje: %s.', $gapLabel),
                'priority' => $index === 0 ? 'Alta' : 'Média',
                'expectedOutcome' => 'Gerar uma proposta mais aderente ao momento atual do lead.',
            ];
        }

        return $items;
    }

    private function inferPrimaryGap(array $lead): string
    {
        if (trim((string) ($lead['website'] ?? '')) === '') {
            return 'ausência de site estruturado';
        }

        if ((int) ($lead['review_count'] ?? 0) < 20) {
            return 'prova social ainda curta';
        }

        if (trim((string) ($lead['phone'] ?? '')) === '' || trim((string) ($lead['address'] ?? '')) === '') {
            return 'dados públicos incompletos';
        }

        return 'clareza limitada sobre a estrutura web e a máquina comercial atual';
    }

    private function buildCommercialMomentFallback(array $lead, string $temperature = ''): string
    {
        $parts = [];
        $stageLabel = stageLabel($lead['pipeline_status'] ?? Lead::STAGE_NEW);
        $parts[] = 'O lead está atualmente em ' . $stageLabel . ' no pipeline.';

        if ($temperature !== '') {
            $parts[] = 'A temperatura percebida hoje é ' . $temperature . '.';
        }

        if (!empty($lead['next_followup_at'])) {
            $parts[] = 'Já existe follow-up previsto, o que indica oportunidade de abordagem com contexto.';
        } elseif (!empty($lead['recent_activities'])) {
            $parts[] = 'Há histórico recente no lead, então vale usar continuidade na conversa em vez de reiniciar do zero.';
        } else {
            $parts[] = 'Ainda falta histórico comercial suficiente, então a abertura precisa ser diagnóstica e orientada a descoberta.';
        }

        return implode(' ', $parts);
    }

    private function deriveShortTitle(string $text): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        if ($clean === '') {
            return 'Insight';
        }

        $slice = mb_substr($clean, 0, 58, 'UTF-8');
        return mb_strlen($clean, 'UTF-8') > 58 ? $slice . '…' : $slice;
    }

    private function normalizeText(mixed $value, string $fallback = ''): string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : $fallback;
    }

    private function normalizePriorityLabel(mixed $value): string
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');

        return match ($normalized) {
            'alta', 'high' => 'Alta',
            'baixa', 'low' => 'Baixa',
            default => 'Média',
        };
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

            $updateData = ['analysis' => $analysis];
            if (Lead::shouldAutoMoveToAnalyzed($lead['pipeline_status'] ?? null)) {
                $updateData['pipeline_status'] = Lead::STAGE_ANALYZED;
            }

            Lead::update($lead['id'], $tenantId, $updateData);
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

    // ─── Advanced Script Generation (with playbooks, tone, instructions) ──

    public function generateAdvancedScripts(
        array $lead,
        string $tenantId,
        string $tone = 'consultivo',
        ?string $channel = null,
        string $instructions = ''
    ): array {
        $agency   = $this->smartContext->loadCompanyProfile($tenantId);
        $services = implode(', ', $agency['offer_services'] ?? []);
        $diffs    = implode('; ', $agency['differentials'] ?? []);
        $maturity = $lead['analysis']['digitalMaturity'] ?? 'Média';
        $score    = $lead['analysis']['priorityScore'] ?? $lead['priority_score'] ?? 50;

        // Load playbook context
        $playbookContext = \App\Models\ApproachPlaybook::getActiveContext($tenantId, 2500);

        // Build lead deep context
        $leadContext = $this->buildLeadSnapshot($lead);

        // Tone mapping
        $toneDescriptions = [
            'consultivo'  => 'Tom consultivo, que demonstra expertise e cria valor antes de vender. Faz perguntas estratégicas.',
            'direto'      => 'Tom direto e objetivo, vai ao ponto sem rodeios. Proposição clara e CTA forte.',
            'elegante'    => 'Tom elegante e premium, linguagem sofisticada, posicionamento de alto valor.',
            'humano'      => 'Tom humano e empático, com linguagem natural, como se estivesse falando com um amigo de confiança.',
            'autoridade'  => 'Tom de autoridade e especialista do setor, usa dados e referências para se posicionar como referência.',
            'curto'       => 'Tom conciso e minimalista. Máximo de impacto com mínimo de palavras. Cada frase conta.',
            'storytelling' => 'Tom narrativo que conecta com uma história relevante antes de apresentar a proposta.',
        ];
        $toneDesc = $toneDescriptions[$tone] ?? $toneDescriptions['consultivo'];

        $systemPrompt = <<<PROMPT
Você é um Copywriter Estratégico de Vendas B2B com vasta experiência em abordagem comercial high-ticket.

CONTEXTO DA EMPRESA QUE VOCÊ REPRESENTA:
- Empresa: {$agency['name']}
- Oferta: {$agency['offer_title']}
- Preço: {$agency['offer_base_price']}
- Serviços: {$services}
- Diferenciais: {$diffs}
- Proposta de valor: {$agency['unique_proposal']}
- Garantias: {$agency['guarantees']}

TOM E ESTILO:
{$toneDesc}

PROMPT;

        if (!empty($playbookContext)) {
            $systemPrompt .= <<<PROMPT

MATERIAL DE REFERÊNCIA (use como base de estilo, framework e princípios de abordagem):
---
{$playbookContext}
---
IMPORTANTE: Use os PRINCÍPIOS e o ESTILO deste material como referência, mas adapte ao contexto específico do lead e da empresa. Não copie o texto literalmente — aplique a essência.

PROMPT;
        }

        if (!empty($instructions)) {
            $systemPrompt .= <<<PROMPT

INSTRUÇÃO PERSONALIZADA DO USUÁRIO:
{$instructions}
Siga essa orientação como diretriz prioritária na construção do script.

PROMPT;
        }

        // Determine channels to generate
        $channels = $channel ? [$channel] : ['whatsapp', 'linkedin', 'email', 'coldCall'];
        $channelDescriptions = [
            'whatsapp' => 'WhatsApp (informal, direto, máx 120 palavras, emojis contextuais permitidos)',
            'linkedin' => 'LinkedIn InMail (profissional, conciso, máx 100 palavras)',
            'email'    => 'E-mail frio (subject + corpo, máx 150 palavras, estruturado)',
            'coldCall' => 'Ligação a frio (roteiro falado, natural, máx 130 palavras, com ganchos de abertura)',
        ];

        $channelList = implode("\n", array_map(fn($ch) => "- {$ch}: {$channelDescriptions[$ch]}", $channels));
        $channelKeys = implode('","', $channels);

        $userPrompt = <<<PROMPT
CONTEXTO COMPLETO DO LEAD:
{$leadContext}

CANAIS PARA GERAR:
{$channelList}

REGRAS:
1. Cada script deve ser INDIVIDUAL para este lead específico — mencione o nome, segmento, ou dor específica dele.
2. Não use placeholders como [nome] ou [empresa]. Use os dados reais.
3. Cruze: contexto do lead + oferta da empresa + material de referência + tom solicitado.
4. Cada script deve ter: abertura com gancho, conexão com a dor/oportunidade, proposta de valor contextualizada, CTA claro.
5. O script deve parecer escrito por um humano que estudou o lead, não um template genérico.

Retorne APENAS JSON válido: {"{$channelKeys}":"script..."}
PROMPT;

        $result = $this->executeJsonAI('advanced_scripts', $tenantId, $systemPrompt, $userPrompt, $lead['id'] ?? null);

        // Ensure all requested channels have a value
        $output = [];
        foreach ($channels as $ch) {
            $output[$ch] = $result[$ch] ?? '';
        }
        return $output;
    }

    // ─── Script Refinement (iterative chat) ────────────────────────────

    public function refineScript(
        array $lead,
        string $tenantId,
        string $currentScript,
        string $instruction,
        string $tone = 'consultivo',
        string $channel = 'whatsapp'
    ): string {
        $agency   = $this->smartContext->loadCompanyProfile($tenantId);
        $playbookContext = \App\Models\ApproachPlaybook::getActiveContext($tenantId, 1500);

        $systemPrompt = <<<PROMPT
Você é um Copywriter Estratégico especializado em refinar scripts de abordagem comercial.

CONTEXTO DA EMPRESA: {$agency['name']} — {$agency['offer_title']}
LEAD: {$lead['name']} — {$lead['segment']}
CANAL: {$channel}

PROMPT;

        if (!empty($playbookContext)) {
            $systemPrompt .= "REFERÊNCIA DE ESTILO:\n{$playbookContext}\n\n";
        }

        $systemPrompt .= <<<PROMPT
REGRAS:
1. Mantenha o tom de "{$tone}" mas ajuste conforme a instrução do usuário.
2. Preserve os dados contextuais do lead (nome, segmento, dores) que já estão no script.
3. Aplique a melhoria de forma cirúrgica — não reescreva do zero a menos que solicitado.
4. O resultado deve ser o script melhorado, pronto para uso. Apenas o texto do script, sem explicações.
PROMPT;

        $userPrompt = <<<PROMPT
SCRIPT ATUAL:
---
{$currentScript}
---

INSTRUÇÃO DE REFINAMENTO:
{$instruction}

Melhore o script acima seguindo a instrução. Retorne apenas o script refinado, sem comentários ou explicações.
PROMPT;

        return $this->executeTextAI('script_refinement', $tenantId, $systemPrompt, $userPrompt, $lead['id'] ?? null);
    }

    /**
     * Build a comprehensive lead context snapshot for AI prompts.
     */
    private function buildLeadSnapshot(array $lead): string
    {
        $parts = [];
        $parts[] = "LEAD: {$lead['name']}";
        if (!empty($lead['segment'])) $parts[] = "SEGMENTO: {$lead['segment']}";
        if (!empty($lead['category'])) $parts[] = "CATEGORIA: {$lead['category']}";
        $parts[] = "SCORE: " . ($lead['priority_score'] ?? 0) . "/100";

        $analysis = $lead['analysis'] ?? [];
        if (!empty($analysis['digitalMaturity'])) $parts[] = "MATURIDADE DIGITAL: {$analysis['digitalMaturity']}";
        if (!empty($analysis['urgencyLevel']))     $parts[] = "URGÊNCIA: {$analysis['urgencyLevel']}";
        if (!empty($analysis['summary']))          $parts[] = "DIAGNÓSTICO: {$analysis['summary']}";

        if (!empty($analysis['diagnosis'])) {
            $problems = is_array($analysis['diagnosis']) ? implode('; ', array_slice(array_map(fn($d) => is_string($d) ? $d : ($d['title'] ?? ''), $analysis['diagnosis']), 0, 4)) : '';
            if ($problems) $parts[] = "PROBLEMAS DETECTADOS: {$problems}";
        }
        if (!empty($analysis['opportunities'])) {
            $opps = is_array($analysis['opportunities']) ? implode('; ', array_slice(array_map(fn($o) => is_string($o) ? $o : ($o['title'] ?? ''), $analysis['opportunities']), 0, 4)) : '';
            if ($opps) $parts[] = "OPORTUNIDADES: {$opps}";
        }

        $ctx = $lead['human_context'] ?? [];
        if (!empty($ctx['temperature'])) $parts[] = "TEMPERATURA: {$ctx['temperature']}";
        if (!empty($ctx['timingStatus'])) $parts[] = "TIMING: {$ctx['timingStatus']}";
        if (!empty($ctx['objectionCategory'])) $parts[] = "OBJEÇÃO PRINCIPAL: {$ctx['objectionCategory']}";
        if (!empty($ctx['notes'])) $parts[] = "OBSERVAÇÕES DO VENDEDOR: " . mb_substr($ctx['notes'], 0, 200);

        if (!empty($lead['pipeline_status'])) $parts[] = "ESTÁGIO: " . stageLabel($lead['pipeline_status']);
        if (!empty($lead['website'])) $parts[] = "WEBSITE: {$lead['website']}";
        if (!empty($lead['phone'])) $parts[] = "TELEFONE: {$lead['phone']}";
        if (!empty($lead['email'])) $parts[] = "EMAIL: {$lead['email']}";

        $social = $lead['social_presence'] ?? [];
        if (!empty($social['instagram'])) $parts[] = "INSTAGRAM: @{$social['instagram']}";

        if (!empty($lead['rating'])) $parts[] = "AVALIAÇÃO GOOGLE: {$lead['rating']} ({$lead['review_count']} reviews)";

        return implode("\n", $parts);
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
