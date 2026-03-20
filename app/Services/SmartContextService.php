<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CompanyProfile;
use App\Services\Knowledge\KnowledgeContextBuilder;

/**
 * Monta o contexto dinâmico de agência injetado em TODOS os prompts de IA.
 * É o "núcleo estratégico" que personaliza a IA para cada agência/tenant.
 *
 * Carrega o CompanyProfile real do DB. Cai para defaults hardcoded apenas
 * se nenhum perfil existir.
 */
class SmartContextService
{
    private KnowledgeContextBuilder $knowledgeBuilder;

    /** Cache do perfil carregado (evita múltiplas queries) */
    private ?array $cachedProfile = null;
    private ?string $cachedTenantId = null;

    // Fallback de ÚLTIMO RECURSO — usado apenas se não houver perfil salvo
    private array $agencyDefaults = [
        'name'             => 'Agência (perfil não configurado)',
        'offer_title'      => 'Serviços de Marketing Digital',
        'offer_base_price' => 'A definir',
        'offer_services'   => ['Marketing Digital'],
        'unique_proposal'  => 'Configure o Knowledge Base para personalizar as análises',
        'differentials'    => ['Configure seus diferenciais no Knowledge Base'],
        'icp'              => ['Configure seu ICP no Knowledge Base'],
    ];

    public function __construct()
    {
        $this->knowledgeBuilder = new KnowledgeContextBuilder();
    }

    /**
     * Carrega o CompanyProfile REAL do banco de dados.
     * Converte para o shape usado internamente (agencyDefaults-compatible).
     * Resultado é cacheado por tenant para evitar queries repetidas.
     */
    public function loadCompanyProfile(string $tenantId): array
    {
        if ($this->cachedTenantId === $tenantId && $this->cachedProfile !== null) {
            return $this->cachedProfile;
        }

        $profile = CompanyProfile::findByTenant($tenantId);

        if (!$profile || empty($profile['agency_name'])) {
            $this->cachedTenantId = $tenantId;
            $this->cachedProfile = $this->agencyDefaults;
            return $this->agencyDefaults;
        }

        // Extrair nomes dos serviços para o array simples
        $services = $profile['services'] ?? [];
        if (is_string($services)) $services = json_decode($services, true) ?? [];
        $serviceNames = array_map(fn($s) => is_array($s) ? ($s['name'] ?? '') : (string)$s, $services);
        $serviceNames = array_filter($serviceNames);

        // Diferenciais
        $diffs = $profile['differentials'] ?? [];
        if (is_string($diffs)) $diffs = json_decode($diffs, true) ?? [];

        // ICP segments
        $icpSegments = $profile['icp_segment'] ?? [];
        if (is_string($icpSegments)) $icpSegments = json_decode($icpSegments, true) ?? [];
        $icpProfile = $profile['icp_profile'] ?? '';
        $icp = !empty($icpSegments) ? $icpSegments : ($icpProfile ? [$icpProfile] : $this->agencyDefaults['icp']);

        $result = [
            'name'                  => $profile['agency_name'] ?: $this->agencyDefaults['name'],
            'offer_title'           => $profile['offer_summary'] ?: $this->agencyDefaults['offer_title'],
            'offer_base_price'      => $profile['offer_price_range'] ?: $this->agencyDefaults['offer_base_price'],
            'offer_services'        => !empty($serviceNames) ? $serviceNames : $this->agencyDefaults['offer_services'],
            'unique_proposal'       => $profile['unique_value_prop'] ?: ($this->agencyDefaults['unique_proposal']),
            'differentials'         => !empty($diffs) ? $diffs : $this->agencyDefaults['differentials'],
            'icp'                   => $icp,
            // Campos estendidos do perfil completo
            'services_full'         => $services,
            'icp_profile'           => $icpProfile,
            'icp_company_size'      => $profile['icp_company_size'] ?? '',
            'icp_ticket_range'      => $profile['icp_ticket_range'] ?? '',
            'icp_pain_points'       => $profile['icp_pain_points'] ?? [],
            'cases'                 => $profile['cases'] ?? [],
            'objection_responses'   => $profile['objection_responses'] ?? [],
            'competitors'           => $profile['competitors'] ?? [],
            'guarantees'            => $profile['guarantees'] ?? '',
            'delivery_timeline'     => $profile['delivery_timeline'] ?? '',
            'pricing_justification' => $profile['pricing_justification'] ?? '',
            'custom_context'        => $profile['custom_context'] ?? '',
            'agency_niche'          => $profile['agency_niche'] ?? '',
            'agency_city'           => $profile['agency_city'] ?? '',
            'agency_state'          => $profile['agency_state'] ?? '',
            'website_url'           => $profile['website_url'] ?? '',
            'awards_recognition'    => $profile['awards_recognition'] ?? '',
        ];

        $this->cachedTenantId = $tenantId;
        $this->cachedProfile = $result;
        return $result;
    }

    // ─── Métodos públicos (RAG + fallback legado) ────────────────────────────

    /**
     * Constrói o bloco de contexto para análises Operon 4D.
     * Tenta RAG primeiro; cai para legado se não houver índice.
     */
    public function buildOperonContext(array $lead, array $agencySettings = []): string
    {
        $tenantId = $lead['tenant_id'] ?? '';

        if ($tenantId) {
            try {
                $query = $this->knowledgeBuilder->deriveQuery('operon_diagnostico', $lead);
                $rag   = $this->knowledgeBuilder->buildContext($query, $lead, $tenantId, 'operon_diagnostico');

                if ($rag['source'] !== 'default' && !empty($rag['context'])) {
                    return $rag['context'];
                }
            } catch (\Throwable $e) {
                error_log('[SmartContextService] RAG falhou em buildOperonContext: ' . $e->getMessage());
            }
        }

        return $this->buildOperonContextLegacy($lead, $agencySettings);
    }

    /**
     * Constrói contexto para análise geral de lead.
     * Tenta RAG primeiro; cai para legado se não houver índice.
     */
    public function buildLeadContext(array $lead, array $agencySettings = []): string
    {
        $tenantId = $lead['tenant_id'] ?? '';

        if ($tenantId) {
            try {
                $query = $this->knowledgeBuilder->deriveQuery('lead_analysis', $lead);
                $rag   = $this->knowledgeBuilder->buildContext($query, $lead, $tenantId, 'lead_analysis');

                if ($rag['source'] !== 'default' && !empty($rag['context'])) {
                    return $rag['context'];
                }
            } catch (\Throwable $e) {
                error_log('[SmartContextService] RAG falhou em buildLeadContext: ' . $e->getMessage());
            }
        }

        return $this->buildLeadContextLegacy($lead, $agencySettings);
    }

    /**
     * Expõe os metadados do último retrieval RAG.
     * Usado por LeadAnalysisService para popular AnalysisTrace.
     *
     * @return array ['source' => string, 'chunk_ids' => string[]]
     */
    public function getLastRetrievalMeta(): array
    {
        return $this->knowledgeBuilder->getLastRetrievalMeta();
    }

    /**
     * Constrói contexto COMPLETO para análises de Inteligência Profunda.
     * Centrado no LEAD como prospect, com empresa como apoio e
     * oferta Operon para triangulação comercial.
     *
     * Inclui dados enriquecidos (rating, reviews, horários, Google Maps, redes sociais).
     */
    public function buildDeepIntelligenceContext(array $lead, array $agencySettings = []): string
    {
        // Carregar perfil REAL do DB
        $tenantId = $lead['tenant_id'] ?? '';
        $agency = $tenantId
            ? $this->loadCompanyProfile($tenantId)
            : array_merge($this->agencyDefaults, $agencySettings);

        // ── Dados do Lead ──
        $name     = $lead['name'] ?? 'N/D';
        $segment  = $lead['segment'] ?? 'N/D';
        $website  = $lead['website'] ?? '';
        $phone    = $lead['phone'] ?? '';
        $email    = $lead['email'] ?? '';
        $address  = $lead['address'] ?? '';
        $category = $lead['category'] ?? '';

        // Dados enriquecidos
        $rating       = isset($lead['rating']) && $lead['rating'] ? number_format((float)$lead['rating'], 1) . '/5.0' : 'N/D';
        $reviewCount  = isset($lead['review_count']) ? (string)$lead['review_count'] : 'N/D';
        $openingHours = $lead['opening_hours'] ?? '';
        $closingHours = $lead['closing_hours'] ?? '';
        $googleMaps   = $lead['google_maps_url'] ?? '';

        // Reviews/depoimentos
        $reviews = $lead['reviews'] ?? [];
        if (is_string($reviews)) $reviews = json_decode($reviews, true) ?? [];
        $reviewsText = '';
        if (!empty($reviews)) {
            $reviewsText = implode("\n    • ", array_slice($reviews, 0, 5));
            $reviewsText = "    • " . $reviewsText;
        }

        // Social presence
        $social = $lead['social_presence'] ?? [];
        if (is_string($social)) $social = json_decode($social, true) ?? [];
        $socialLines = [];
        if (!empty($social['instagram'])) $socialLines[] = "Instagram: {$social['instagram']}";
        if (!empty($social['facebook']))  $socialLines[] = "Facebook: {$social['facebook']}";
        if (!empty($social['linkedin']))  $socialLines[] = "LinkedIn: {$social['linkedin']}";
        $socialText = !empty($socialLines) ? implode(' | ', $socialLines) : 'Não mapeada';

        // Dados de análise prévia
        $analysis     = $lead['analysis'] ?? [];
        $maturity     = $analysis['digitalMaturity'] ?? 'Não avaliada';
        $painPoints   = is_array($analysis['diagnosis'] ?? null)
            ? implode('; ', $analysis['diagnosis']) : '';
        $opportunities = is_array($analysis['opportunities'] ?? null)
            ? implode('; ', $analysis['opportunities']) : '';
        $scoreExpl    = $analysis['scoreExplanation'] ?? '';

        // Contexto humano
        $ctx       = $lead['human_context'] ?? [];
        if (is_string($ctx)) $ctx = json_decode($ctx, true) ?? [];
        $humanCtx  = $ctx['context'] ?? '';
        $temp      = $ctx['temperature'] ?? '';

        // Tags
        $tags = $lead['tags'] ?? [];
        if (is_string($tags)) $tags = json_decode($tags, true) ?? [];
        $tagsText = !empty($tags) ? implode(', ', $tags) : '';

        // Score
        $fitScore      = $lead['fit_score'] ?? 0;
        $priorityScore = $lead['priority_score'] ?? 0;

        // ── Contexto da Empresa (REAL do DB) ──
        $services = implode(', ', $agency['offer_services'] ?? []);
        $diffs    = implode('; ', $agency['differentials'] ?? []);
        $icps     = implode('; ', $agency['icp'] ?? []);

        // Serviços detalhados (com descrição e preço)
        $servicesFull = $agency['services_full'] ?? [];
        if (is_string($servicesFull)) $servicesFull = json_decode($servicesFull, true) ?? [];
        $servicesDetailText = '';
        if (!empty($servicesFull)) {
            $lines = [];
            foreach ($servicesFull as $svc) {
                $line = '• ' . ($svc['name'] ?? '');
                if (!empty($svc['description'])) $line .= ' — ' . $svc['description'];
                if (!empty($svc['price_range'])) $line .= ' (' . $svc['price_range'] . ')';
                $lines[] = $line;
            }
            $servicesDetailText = implode("\n", $lines);
        }

        // Cases de sucesso
        $cases = $agency['cases'] ?? [];
        if (is_string($cases)) $cases = json_decode($cases, true) ?? [];
        $casesText = '';
        if (!empty($cases)) {
            $lines = [];
            foreach (array_slice($cases, 0, 5) as $c) {
                $line = '• ' . ($c['client'] ?? 'Cliente');
                if (!empty($c['result'])) $line .= ': ' . $c['result'];
                if (!empty($c['niche'])) $line .= ' (nicho: ' . $c['niche'] . ')';
                if (!empty($c['timeframe'])) $line .= ' — ' . $c['timeframe'];
                $lines[] = $line;
            }
            $casesText = implode("\n", $lines);
        }

        // Objeções e respostas
        $objections = $agency['objection_responses'] ?? [];
        if (is_string($objections)) $objections = json_decode($objections, true) ?? [];
        $objectionsText = '';
        if (!empty($objections)) {
            $lines = [];
            foreach ($objections as $o) {
                $lines[] = '• "' . ($o['objection'] ?? '') . '" → ' . ($o['response'] ?? '');
            }
            $objectionsText = implode("\n", $lines);
        }

        // ICP pain points
        $icpPains = $agency['icp_pain_points'] ?? [];
        if (is_string($icpPains)) $icpPains = json_decode($icpPains, true) ?? [];

        // ── Montar o contexto ──
        $context = <<<CONTEXT
===== LEAD EM ANÁLISE (FOCO PRINCIPAL) =====
Nome: {$name}
Segmento: {$segment}
Categoria: {$category}
Localização: {$address}
Telefone: {$phone}
Email: {$email}
Website: {$website}
Google Maps: {$googleMaps}

--- REPUTAÇÃO & AVALIAÇÕES ---
Nota média: {$rating}
Total de avaliações: {$reviewCount}
CONTEXT;

        if ($reviewsText) {
            $context .= "\nDepoimentos de clientes:\n{$reviewsText}";
        }

        $context .= "\n\n--- PRESENÇA DIGITAL ---";
        $context .= "\nRedes sociais: {$socialText}";
        $context .= "\nMaturidade digital: {$maturity}";

        if ($openingHours || $closingHours) {
            $context .= "\n\n--- FUNCIONAMENTO ---";
            if ($openingHours) $context .= "\nHorário de abertura: {$openingHours}";
            if ($closingHours) $context .= "\nHorário de fechamento: {$closingHours}";
        }

        $context .= "\n\n--- INTELIGÊNCIA COMERCIAL PRÉVIA ---";
        $context .= "\nScore de prioridade: {$priorityScore}";
        $context .= "\nFit score (ICP): {$fitScore}/100";
        if ($temp) $context .= "\nTemperatura do lead: {$temp}";
        if ($tagsText) $context .= "\nTags: {$tagsText}";
        if ($painPoints) $context .= "\nDores identificadas: {$painPoints}";
        if ($opportunities) $context .= "\nOportunidades detectadas: {$opportunities}";
        if ($scoreExpl) $context .= "\nExplicação do score: {$scoreExpl}";
        if ($humanCtx) $context .= "\nContexto do vendedor: {$humanCtx}";

        // ── CONTEXTO DA EMPRESA (perfil real do DB) ──
        $context .= "\n\n===== CONTEXTO DA EMPRESA VENDEDORA (BASE ESTRATÉGICA — use como fundamento) =====";
        $context .= "\nEmpresa: {$agency['name']}";
        if (!empty($agency['agency_niche'])) $context .= "\nNicho: {$agency['agency_niche']}";
        if (!empty($agency['agency_city'])) $context .= "\nLocalização: {$agency['agency_city']}" . (!empty($agency['agency_state']) ? '/' . $agency['agency_state'] : '');
        $context .= "\nOferta: {$agency['offer_title']}";
        $context .= "\nPreço base: {$agency['offer_base_price']}";
        $context .= "\nProposta única: {$agency['unique_proposal']}";

        if ($servicesDetailText) {
            $context .= "\n\n--- SERVIÇOS OFERECIDOS ---\n{$servicesDetailText}";
        } else {
            $context .= "\nServiços: {$services}";
        }

        $context .= "\n\n--- DIFERENCIAIS ---\n• " . implode("\n• ", $agency['differentials'] ?? []);

        if (!empty($agency['guarantees'])) $context .= "\nGarantia: {$agency['guarantees']}";
        if (!empty($agency['delivery_timeline'])) $context .= "\nPrazo: {$agency['delivery_timeline']}";
        if (!empty($agency['awards_recognition'])) $context .= "\nPrêmios: {$agency['awards_recognition']}";

        // ICP completo
        $context .= "\n\n--- PERFIL DE CLIENTE IDEAL (ICP) ---";
        if (!empty($agency['icp_profile'])) $context .= "\n{$agency['icp_profile']}";
        $context .= "\nSegmentos: " . implode(', ', $agency['icp'] ?? []);
        if (!empty($agency['icp_company_size'])) $context .= "\nPorte ideal: {$agency['icp_company_size']}";
        if (!empty($agency['icp_ticket_range'])) $context .= "\nTicket ideal: {$agency['icp_ticket_range']}";
        if (!empty($icpPains)) $context .= "\nDores típicas do ICP: " . implode('; ', $icpPains);

        if ($casesText) {
            $context .= "\n\n--- CASES DE SUCESSO ---\n{$casesText}";
        }

        if ($objectionsText) {
            $context .= "\n\n--- OBJEÇÕES COMUNS & RESPOSTAS ---\n{$objectionsText}";
        }

        if (!empty($agency['pricing_justification'])) {
            $context .= "\n\n--- JUSTIFICATIVA DE PREÇO ---\n{$agency['pricing_justification']}";
        }

        if (!empty($agency['custom_context'])) {
            $context .= "\n\n--- CONTEXTO ESTRATÉGICO INTERNO ---\n{$agency['custom_context']}";
        }

        $context .= "\n=========================";

        return $context;
    }

    /**
     * Constrói contexto COMPLETO da empresa para análise estratégica de nicho.
     * Não depende de lead — foco é o perfil da empresa vs. nicho de mercado.
     */
    public function buildNicheContext(string $tenantId): string
    {
        $agency = $this->loadCompanyProfile($tenantId);

        $services = implode(', ', $agency['offer_services'] ?? []);
        $diffs    = implode('; ', $agency['differentials'] ?? []);
        $icps     = implode('; ', $agency['icp'] ?? []);

        // Serviços detalhados
        $servicesFull = $agency['services_full'] ?? [];
        if (is_string($servicesFull)) $servicesFull = json_decode($servicesFull, true) ?? [];
        $servicesDetailText = '';
        if (!empty($servicesFull)) {
            $lines = [];
            foreach ($servicesFull as $svc) {
                $line = '• ' . ($svc['name'] ?? '');
                if (!empty($svc['description'])) $line .= ' — ' . $svc['description'];
                if (!empty($svc['price_range'])) $line .= ' (' . $svc['price_range'] . ')';
                $lines[] = $line;
            }
            $servicesDetailText = implode("\n", $lines);
        }

        // Cases de sucesso
        $cases = $agency['cases'] ?? [];
        if (is_string($cases)) $cases = json_decode($cases, true) ?? [];
        $casesText = '';
        if (!empty($cases)) {
            $lines = [];
            foreach (array_slice($cases, 0, 5) as $c) {
                $line = '• ' . ($c['client'] ?? 'Cliente');
                if (!empty($c['result'])) $line .= ': ' . $c['result'];
                if (!empty($c['niche'])) $line .= ' (nicho: ' . $c['niche'] . ')';
                $lines[] = $line;
            }
            $casesText = implode("\n", $lines);
        }

        // Objeções
        $objections = $agency['objection_responses'] ?? [];
        if (is_string($objections)) $objections = json_decode($objections, true) ?? [];
        $objectionsText = '';
        if (!empty($objections)) {
            $lines = [];
            foreach ($objections as $o) {
                $lines[] = '• "' . ($o['objection'] ?? '') . '" → ' . ($o['response'] ?? '');
            }
            $objectionsText = implode("\n", $lines);
        }

        // Concorrentes
        $competitors = $agency['competitors'] ?? [];
        if (is_string($competitors)) $competitors = json_decode($competitors, true) ?? [];
        $competitorsText = !empty($competitors) ? implode(', ', $competitors) : '';

        // ICP pain points
        $icpPains = $agency['icp_pain_points'] ?? [];
        if (is_string($icpPains)) $icpPains = json_decode($icpPains, true) ?? [];

        $context = "===== PERFIL ESTRATÉGICO DA EMPRESA (BASE OBRIGATÓRIA PARA ANÁLISE) =====\n";
        $context .= "Empresa: {$agency['name']}\n";
        if (!empty($agency['agency_niche'])) $context .= "Nicho de atuação: {$agency['agency_niche']}\n";
        if (!empty($agency['agency_city'])) $context .= "Localização: {$agency['agency_city']}" . (!empty($agency['agency_state']) ? '/' . $agency['agency_state'] : '') . "\n";
        if (!empty($agency['website_url'])) $context .= "Website: {$agency['website_url']}\n";
        $context .= "Oferta principal: {$agency['offer_title']}\n";
        $context .= "Faixa de preço: {$agency['offer_base_price']}\n";
        $context .= "Proposta de valor única: {$agency['unique_proposal']}\n";

        if ($servicesDetailText) {
            $context .= "\n--- SERVIÇOS OFERECIDOS ---\n{$servicesDetailText}\n";
        } else {
            $context .= "Serviços: {$services}\n";
        }

        $context .= "\n--- DIFERENCIAIS ---\n• " . implode("\n• ", $agency['differentials'] ?? []) . "\n";

        if (!empty($agency['guarantees'])) $context .= "Garantia: {$agency['guarantees']}\n";
        if (!empty($agency['delivery_timeline'])) $context .= "Prazo de entrega: {$agency['delivery_timeline']}\n";
        if (!empty($agency['awards_recognition'])) $context .= "Prêmios/Reconhecimentos: {$agency['awards_recognition']}\n";

        $context .= "\n--- PERFIL DE CLIENTE IDEAL (ICP) ---\n";
        if (!empty($agency['icp_profile'])) $context .= "{$agency['icp_profile']}\n";
        $context .= "Segmentos alvo: {$icps}\n";
        if (!empty($agency['icp_company_size'])) $context .= "Porte ideal: {$agency['icp_company_size']}\n";
        if (!empty($agency['icp_ticket_range'])) $context .= "Ticket ideal: {$agency['icp_ticket_range']}\n";
        if (!empty($icpPains)) $context .= "Dores típicas do ICP: " . implode('; ', $icpPains) . "\n";

        if ($casesText) {
            $context .= "\n--- CASES DE SUCESSO ---\n{$casesText}\n";
        }

        if ($objectionsText) {
            $context .= "\n--- OBJEÇÕES COMUNS & RESPOSTAS ---\n{$objectionsText}\n";
        }

        if ($competitorsText) {
            $context .= "\n--- CONCORRENTES MAPEADOS ---\n{$competitorsText}\n";
        }

        if (!empty($agency['pricing_justification'])) {
            $context .= "\n--- JUSTIFICATIVA DE PREÇO ---\n{$agency['pricing_justification']}\n";
        }

        if (!empty($agency['custom_context'])) {
            $context .= "\n--- CONTEXTO ESTRATÉGICO INTERNO ---\n{$agency['custom_context']}\n";
        }

        $context .= "=========================";

        return $context;
    }

    // ─── Fallbacks legados (comportamento original) ──────────────────────────

    private function buildOperonContextLegacy(array $lead, array $agencySettings = []): string
    {
        $tenantId = $lead['tenant_id'] ?? '';
        $agency = $tenantId
            ? $this->loadCompanyProfile($tenantId)
            : array_merge($this->agencyDefaults, $agencySettings);

        $services     = implode(', ', $agency['offer_services'] ?? []);
        $diffs        = implode("\n• ", $agency['differentials'] ?? []);
        $icps         = implode("\n• ", $agency['icp'] ?? []);
        $painPoints   = is_array($lead['analysis']['diagnosis'] ?? null)
            ? implode('; ', $lead['analysis']['diagnosis']) : 'N/D';
        $opportunities = is_array($lead['analysis']['opportunities'] ?? null)
            ? implode('; ', $lead['analysis']['opportunities']) : 'N/D';
        $humanCtx     = $lead['human_context']['context'] ?? 'Sem contexto adicional';

        return <<<CONTEXT
=== CONTEXTO DA EMPRESA (use para personalizar scripts e diagnósticos) ===
Empresa: {$agency['name']}
Oferta: {$agency['offer_title']}
Preço base: {$agency['offer_base_price']}
Serviços: {$services}
Proposta única: {$agency['unique_proposal']}

Diferenciais competitivos:
• {$diffs}

Perfil de Cliente Ideal (ICP):
• {$icps}

=== DADOS DO LEAD ===
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Site: {$lead['website']}
Localização: {$lead['address']}
Dores identificadas: {$painPoints}
Oportunidades: {$opportunities}
Contexto do vendedor: {$humanCtx}
CONTEXT;
    }

    private function buildLeadContextLegacy(array $lead, array $agencySettings = []): string
    {
        $tenantId = $lead['tenant_id'] ?? '';
        $agency = $tenantId
            ? $this->loadCompanyProfile($tenantId)
            : array_merge($this->agencyDefaults, $agencySettings);

        $services = implode("\n• ", $agency['offer_services'] ?? []);
        $tags     = is_array($lead['tags'] ?? null) ? implode(', ', $lead['tags']) : '';
        $score    = $lead['fit_score'] ?? 0;
        $maturity = $lead['analysis']['digitalMaturity'] ?? 'Desconhecida';
        $humanCtx = $lead['human_context']['context'] ?? 'Sem contexto';

        return <<<CONTEXT
=== IDENTIDADE DA EMPRESA ===
{$agency['name']}
Proposta: {$agency['unique_proposal']}
Diferenciais: {$agency['differentials'][0]}

=== PERFIL DE CLIENTE IDEAL (ICP) ===
• {$agency['icp'][0]}

=== TABELA DE SERVIÇOS ===
• {$services}

=== PERFIL DO LEAD ===
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Maturidade Digital: {$maturity}
Score de Fit: {$score}/100
Site: {$lead['website']}
Localização: {$lead['address']}
Tags: {$tags}
Contexto manual do vendedor: {$humanCtx}
CONTEXT;
    }
}
