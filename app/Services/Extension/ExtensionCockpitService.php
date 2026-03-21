<?php

declare(strict_types=1);

namespace App\Services\Extension;

use App\Core\Database;
use App\Core\Helpers;
use App\Helpers\AIResponseParser;
use App\Models\Lead;
use App\Services\AI\AIProviderFactory;
use App\Services\SmartContextService;
use App\Services\TokenService;

class ExtensionCockpitService
{
    private SmartContextService $smartContext;
    private TokenService $tokens;

    public function __construct()
    {
        $this->smartContext = new SmartContextService();
        $this->tokens = new TokenService();
    }

    public function analyzePage(string $tenantId, string $userId, array $payload): array
    {
        $page = $this->normalizePagePayload($tenantId, $payload);
        $fallback = $this->buildHeuristicPageAnalysis($tenantId, $page);

        try {
            $systemPrompt = <<<PROMPT
Você é o Operon Extension Intelligence, um analista comercial que trabalha dentro do navegador.

REGRAS ABSOLUTAS:
1. Use SOMENTE fatos presentes no contexto enviado. Nunca invente dados.
2. Se um campo não estiver confirmado, escreva "Não identificado" ou liste isso em "warnings".
3. Diferencie fato observado de inferência comercial.
4. A sua missão é avaliar a página atual como oportunidade comercial real.
5. Use o contexto estratégico da empresa apenas para avaliar encaixe e prioridade, nunca para criar fatos sobre a página.
6. Responda em português do Brasil.
PROMPT;

            $userPrompt = <<<PROMPT
{$this->buildAgencyContextBlock($tenantId)}

{$this->buildPageFactBlock($page)}

Retorne APENAS um JSON válido com esta estrutura:
{
  "company": "nome da empresa/página ou 'Não identificado'",
  "pageType": "company_site | profile | marketplace_listing | landing_page | article | unknown",
  "summary": "resumo objetivo da página e do negócio em até 3 frases",
  "positioning": "como a oferta/posicionamento aparece na página",
  "offerClarity": "Alta | Média | Baixa",
  "digitalMaturity": number,
  "fitScore": number,
  "qualificationHint": "qual a leitura comercial rápida dessa oportunidade",
  "evidence": ["somente fatos observáveis na página"],
  "opportunities": ["oportunidades comerciais plausíveis baseadas nos fatos"],
  "painPoints": ["lacunas, riscos ou sinais fracos observados"],
  "recommendation": "recomendação prática para o time comercial",
  "nextActions": ["próximas ações claras"],
  "contextForVault": "resumo pronto para salvar no contexto do lead",
  "warnings": ["lacunas ou dados não confirmados"],
  "extractedLead": {
    "name": "",
    "segment": "",
    "category": "",
    "website": "",
    "phone": "",
    "email": "",
    "address": "",
    "linkedin_url": "",
    "instagram_url": ""
  }
}

Use notas de 0 a 10 para "digitalMaturity" e "fitScore".
PROMPT;

            $parsed = $this->runJsonOperation(
                'extension_page_analysis',
                $tenantId,
                $userId,
                $systemPrompt,
                $userPrompt,
                ['temperature' => 0.2]
            );

            return $this->sanitizePageAnalysis($parsed, $fallback, $page, 'ai');
        } catch (\Throwable $e) {
            $fallback['warnings'][] = 'IA indisponível no momento. A leitura abaixo foi montada por heurística determinística da página.';
            $fallback['warnings'][] = $this->truncateText($e->getMessage(), 160);
            $fallback['mode'] = 'heuristic';
            return $fallback;
        }
    }

    public function qualifyPage(string $tenantId, string $userId, array $payload): array
    {
        $page = $this->normalizePagePayload($tenantId, $payload);
        $fallback = $this->buildHeuristicQualification($tenantId, $page);

        try {
            $systemPrompt = <<<PROMPT
Você é um qualificador comercial B2B dentro da extensão Operon.

REGRAS ABSOLUTAS:
1. Baseie a qualificação apenas nos fatos enviados.
2. Nunca invente orçamento, dor, decisor ou urgência.
3. Se faltar evidência, reduza a confiança e diga isso explicitamente.
4. Pense como SDR/closer: fit, potencial, urgência e próximo passo.
PROMPT;

            $userPrompt = <<<PROMPT
{$this->buildAgencyContextBlock($tenantId)}

{$this->buildPageFactBlock($page)}

Retorne APENAS um JSON válido com esta estrutura:
{
  "fitScore": number,
  "potentialScore": number,
  "urgencyScore": number,
  "verdict": "leitura comercial objetiva",
  "positiveSignals": ["sinais positivos observados"],
  "objectionSignals": ["riscos, objeções ou lacunas observadas"],
  "nextSteps": "próximos passos recomendados",
  "approachSuggestion": "sugestão de abordagem inicial",
  "recommendedAction": "capturar_agora | qualificar_mais | observar",
  "confidence": "Alta | Média | Baixa"
}

Use notas de 0 a 10.
PROMPT;

            $parsed = $this->runJsonOperation(
                'extension_qualification',
                $tenantId,
                $userId,
                $systemPrompt,
                $userPrompt,
                ['temperature' => 0.15]
            );

            return $this->sanitizeQualification($parsed, $fallback, 'ai');
        } catch (\Throwable $e) {
            $fallback['verdict'] .= ' A IA não respondeu; leitura mantida por heurística da extensão.';
            $fallback['objectionSignals'][] = $this->truncateText($e->getMessage(), 160);
            $fallback['mode'] = 'heuristic';
            return $fallback;
        }
    }

    public function analyzeVisual(string $tenantId, string $userId, array $payload): array
    {
        $page = $this->normalizePagePayload($tenantId, $payload);
        $fallback = $this->buildHeuristicVisualAnalysis($tenantId, $page);
        $screenshot = trim((string) ($page['screenshot'] ?? ''));

        if ($screenshot === '') {
            $fallback['warnings'][] = 'Screenshot não enviado pela extensão.';
            $fallback['mode'] = 'heuristic';
            return $fallback;
        }

        try {
            $systemPrompt = <<<PROMPT
Você é um analista visual de websites dentro do cockpit Operon.

REGRAS ABSOLUTAS:
1. Analise a imagem enviada e o contexto factual da página.
2. Nunca invente elementos que não estejam visíveis ou confirmados.
3. Se a imagem estiver limitada, recortada ou pouco legível, explique isso em "warnings".
4. Avalie a qualidade percebida, a clareza visual e sinais de maturidade digital.
PROMPT;

            $userPrompt = <<<PROMPT
{$this->buildAgencyContextBlock($tenantId)}

{$this->buildPageFactBlock($page)}

Analise VISUALMENTE o screenshot enviado e retorne APENAS um JSON válido com esta estrutura:
{
  "firstImpression": "primeira leitura visual objetiva",
  "brandClarity": number,
  "visualHierarchy": number,
  "ctaClarity": number,
  "perceivedTrust": number,
  "digitalMaturity": number,
  "strengths": ["forças visuais observadas"],
  "weaknesses": ["fragilidades visuais observadas"],
  "commercialSignals": ["o que isso sinaliza comercialmente"],
  "recommendation": "recomendação prática",
  "warnings": ["limitações da leitura visual"]
}

Use notas de 0 a 10.
PROMPT;

            $parsed = $this->runJsonOperation(
                'extension_visual_analysis',
                $tenantId,
                $userId,
                $systemPrompt,
                $userPrompt,
                [
                    'temperature' => 0.2,
                    'images' => [$screenshot],
                    'image_detail' => 'low',
                ]
            );

            return $this->sanitizeVisualAnalysis($parsed, $fallback, 'ai');
        } catch (\Throwable $e) {
            $fallback['warnings'][] = 'A leitura visual voltou para modo heurístico.';
            $fallback['warnings'][] = $this->truncateText($e->getMessage(), 160);
            $fallback['mode'] = 'heuristic';
            return $fallback;
        }
    }

    public function copilotReply(string $tenantId, string $userId, array $payload): string
    {
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            throw new \RuntimeException('Mensagem vazia.');
        }

        $page = $this->normalizePagePayload($tenantId, $payload['page_context'] ?? []);
        $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];

        $historyText = '';
        foreach (array_slice($history, -8) as $entry) {
            $role = (($entry['role'] ?? '') === 'user') ? 'Usuário' : 'Assistente';
            $content = $this->truncateText((string) ($entry['content'] ?? ''), 320);
            if ($content !== '') {
                $historyText .= "{$role}: {$content}\n";
            }
        }

        $fallbackAnalysis = $this->buildHeuristicPageAnalysis($tenantId, $page);
        $fallbackQualification = $this->buildHeuristicQualification($tenantId, $page);

        try {
            $systemPrompt = <<<PROMPT
Você é o Operon Intelligence dentro da extensão do Chrome.

REGRAS ABSOLUTAS:
1. Use o bloco da página atual como fonte factual principal.
2. Nunca invente dados sobre a empresa, pessoa ou página analisada.
3. Se faltarem dados, diga exatamente o que falta.
4. Seja consultivo, objetivo e operacional.
5. Sempre responda em português brasileiro.
PROMPT;

            $userPrompt = <<<PROMPT
{$this->buildAgencyContextBlock($tenantId)}

{$this->buildPageFactBlock($page)}

HEURÍSTICA ATUAL DA EXTENSÃO:
- Resumo: {$fallbackAnalysis['summary']}
- Fit: {$fallbackQualification['fitScore']}/10
- Potencial: {$fallbackQualification['potentialScore']}/10
- Urgência: {$fallbackQualification['urgencyScore']}/10
- Próximo passo sugerido: {$fallbackQualification['nextSteps']}

HISTÓRICO RECENTE:
{$historyText}

Pergunta do usuário:
{$message}
PROMPT;

            return $this->runTextOperation(
                'extension_copilot',
                $tenantId,
                $userId,
                $systemPrompt,
                $userPrompt,
                ['temperature' => 0.45]
            );
        } catch (\Throwable $e) {
            $lines = [
                'Não consegui acionar a IA agora, então vou te responder com a leitura local da extensão.',
                '',
                'Resumo da página:',
                '- ' . $fallbackAnalysis['summary'],
                '',
                'Sinais positivos:',
            ];

            foreach (array_slice($fallbackQualification['positiveSignals'], 0, 3) as $signal) {
                $lines[] = '- ' . $signal;
            }

            $lines[] = '';
            $lines[] = 'Riscos ou lacunas:';
            foreach (array_slice($fallbackQualification['objectionSignals'], 0, 3) as $signal) {
                $lines[] = '- ' . $signal;
            }

            $lines[] = '';
            $lines[] = 'Próximo passo sugerido:';
            $lines[] = '- ' . $fallbackQualification['nextSteps'];
            $lines[] = '- ' . $fallbackQualification['approachSuggestion'];
            $lines[] = '';
            $lines[] = 'Falha técnica: ' . $this->truncateText($e->getMessage(), 160);

            return implode("\n", $lines);
        }
    }

    public function saveAnalysisPackage(string $tenantId, string $userId, array $payload): array
    {
        $page = $this->normalizePagePayload($tenantId, $payload['page_context'] ?? $payload);
        $analysis = is_array($payload['analysis'] ?? null) ? $payload['analysis'] : $this->buildHeuristicPageAnalysis($tenantId, $page);
        $qualification = is_array($payload['qualification'] ?? null) ? $payload['qualification'] : $this->buildHeuristicQualification($tenantId, $page);
        $visual = is_array($payload['visual'] ?? null) ? $payload['visual'] : [];

        $leadCandidate = $page['lead_candidate'];
        $existing = $page['existing_lead'];

        $leadAnalysis = $this->buildLeadAnalysisPayload($analysis, $qualification, $visual, $leadCandidate);
        $contextSummary = $this->buildContextSummary($analysis, $qualification, $visual, $page);
        $activityContent = $this->buildActivityContent($analysis, $qualification, $visual, $page);
        $socialPresence = array_filter([
            'linkedin' => $leadCandidate['linkedin_url'] ?? null,
            'instagram' => $leadCandidate['instagram_url'] ?? null,
            'facebook' => $leadCandidate['facebook_url'] ?? null,
        ]);

        if ($existing) {
            $lead = Lead::findByTenant($existing['id'], $tenantId);
            if (!$lead) {
                throw new \RuntimeException('Lead duplicado encontrado, mas não foi possível carregá-lo.');
            }

            $humanContext = is_array($lead['human_context'] ?? null) ? $lead['human_context'] : [];
            $enrichment = is_array($lead['enrichment_data'] ?? null) ? $lead['enrichment_data'] : [];

            $updateData = [
                'segment' => $this->preferSegment($lead['segment'] ?? '', $leadCandidate['segment'] ?? ''),
                'category' => $lead['category'] ?: ($leadCandidate['category'] ?? null),
                'website' => $lead['website'] ?: ($leadCandidate['website'] ?? null),
                'phone' => $lead['phone'] ?: ($leadCandidate['phone'] ?? null),
                'email' => $lead['email'] ?: ($leadCandidate['email'] ?? null),
                'address' => $lead['address'] ?: ($leadCandidate['address'] ?? null),
                'social_presence' => !empty($socialPresence) ? array_merge((array) ($lead['social_presence'] ?? []), $socialPresence) : ($lead['social_presence'] ?? []),
                'human_context' => $this->mergeHumanContext($humanContext, $contextSummary, $page),
                'enrichment_data' => $this->mergeEnrichmentData($enrichment, $analysis, $qualification, $visual, $page),
            ];

            Lead::update($lead['id'], $tenantId, $updateData);
            Lead::saveAnalysis($lead['id'], $tenantId, $leadAnalysis);

            $this->insertLeadActivity($tenantId, $lead['id'], $userId, $activityContent, $analysis, $qualification, $visual, $page);

            return [
                'lead_id' => $lead['id'],
                'url' => '/vault/' . $lead['id'],
                'created' => false,
                'message' => 'Contexto e análise anexados ao lead existente no Vault.',
            ];
        }

        $leadId = Lead::create($tenantId, [
            'name' => $leadCandidate['name'] ?: $this->fallbackLeadName($page),
            'segment' => $leadCandidate['segment'] ?: 'Não identificado',
            'website' => $leadCandidate['website'] ?: null,
            'phone' => $leadCandidate['phone'] ?: null,
            'email' => $leadCandidate['email'] ?: null,
            'address' => $leadCandidate['address'] ?: null,
            'category' => $leadCandidate['category'] ?: null,
            'social_presence' => !empty($socialPresence) ? $socialPresence : null,
            'human_context' => $this->mergeHumanContext([], $contextSummary, $page),
            'enrichment_data' => $this->mergeEnrichmentData([], $analysis, $qualification, $visual, $page),
            'analysis' => $leadAnalysis,
            'priority_score' => (int) ($leadAnalysis['priorityScore'] ?? 35),
            'fit_score' => (int) ($leadAnalysis['fitScore'] ?? 35),
        ]);

        $this->insertLeadActivity($tenantId, $leadId, $userId, $activityContent, $analysis, $qualification, $visual, $page);

        return [
            'lead_id' => $leadId,
            'url' => '/vault/' . $leadId,
            'created' => true,
            'message' => 'Pacote da página salvo no Vault e conectado ao contexto estratégico.',
        ];
    }

    private function runJsonOperation(
        string $operation,
        string $tenantId,
        string $userId,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): array {
        if (!$this->tokens->hasSufficient($operation, $tenantId)) {
            throw new \RuntimeException('Créditos esgotados para esta operação.');
        }

        $provider = AIProviderFactory::make($operation, $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, $options);
        $parsed = $meta['parsed'] ?? [];

        if (AIResponseParser::hasError($parsed)) {
            throw new \RuntimeException(AIResponseParser::getErrorMessage($parsed));
        }

        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];
        $this->tokens->consume(
            $operation,
            $tenantId,
            $userId,
            $provider->getProviderName(),
            $provider->getModel(),
            (int) ($usage['input'] ?? 0),
            (int) ($usage['output'] ?? 0)
        );

        return $parsed;
    }

    private function runTextOperation(
        string $operation,
        string $tenantId,
        string $userId,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): string {
        if (!$this->tokens->hasSufficient($operation, $tenantId)) {
            throw new \RuntimeException('Créditos esgotados para esta operação.');
        }

        $provider = AIProviderFactory::make($operation, $tenantId);
        $meta = $provider->generateWithMeta($systemPrompt, $userPrompt, $options);
        $text = trim((string) ($meta['text'] ?? ''));

        if ($text === '') {
            throw new \RuntimeException('A IA retornou uma resposta vazia.');
        }

        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];
        $this->tokens->consume(
            $operation,
            $tenantId,
            $userId,
            $provider->getProviderName(),
            $provider->getModel(),
            (int) ($usage['input'] ?? 0),
            (int) ($usage['output'] ?? 0)
        );

        return $text;
    }

    private function normalizePagePayload(string $tenantId, array $payload): array
    {
        $extracted = is_array($payload['extracted'] ?? null) ? $payload['extracted'] : [];
        $scan = is_array($payload['page_scan'] ?? null) ? $payload['page_scan'] : [];
        $source = trim((string) ($payload['source'] ?? $payload['page_source'] ?? $extracted['source'] ?? 'generic'));

        $page = [
            'url' => trim((string) ($payload['url'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? '')),
            'content' => trim((string) ($payload['content'] ?? $scan['content'] ?? '')),
            'page_scan' => $scan,
            'extracted' => $extracted,
            'source' => $source,
            'deep' => !empty($payload['deep']),
            'screenshot' => trim((string) ($payload['screenshot'] ?? '')),
        ];

        $page['lead_candidate'] = $this->buildLeadCandidate($page);
        $page['existing_lead'] = LeadNormalizationService::checkDuplicate($tenantId, $page['lead_candidate']);
        $page['page_type'] = $this->detectPageType($page);

        return $page;
    }

    private function buildLeadCandidate(array $page): array
    {
        $extracted = $page['extracted'];
        $scan = $page['page_scan'];
        $contacts = is_array($scan['contacts'] ?? null) ? $scan['contacts'] : [];
        $social = is_array($scan['social'] ?? null) ? $scan['social'] : [];

        $name = trim((string) ($extracted['name'] ?? $extracted['company'] ?? ''));
        if ($name === '') {
            $name = $this->inferNameFromTitle($page['title'], $page['url']);
        }

        $website = trim((string) ($extracted['website'] ?? ''));
        if ($website === '' && $page['source'] === 'generic') {
            $website = $page['url'];
        }

        $candidate = [
            'name' => $name,
            'segment' => trim((string) ($extracted['segment'] ?? $extracted['category'] ?? '')),
            'category' => trim((string) ($extracted['category'] ?? '')),
            'website' => LeadNormalizationService::normalizeWebsite($website ?: ''),
            'phone' => LeadNormalizationService::normalizePhone((string) ($extracted['phone'] ?? ($contacts['phones'][0] ?? ''))),
            'email' => LeadNormalizationService::normalizeEmail((string) ($extracted['email'] ?? ($contacts['emails'][0] ?? ''))),
            'address' => trim((string) ($extracted['address'] ?? '')),
            'linkedin_url' => trim((string) ($extracted['linkedin_url'] ?? $social['linkedin'] ?? '')),
            'instagram_url' => trim((string) ($extracted['instagram_url'] ?? $social['instagram'] ?? '')),
            'facebook_url' => trim((string) ($social['facebook'] ?? '')),
            'source' => trim((string) ($extracted['source'] ?? $page['source'] ?? 'chrome_extension')),
            'source_url' => $page['url'],
            'extractor_type' => trim((string) ($extracted['extractor_type'] ?? $page['source'] ?? 'generic')),
            'google_maps_url' => trim((string) ($extracted['google_maps_url'] ?? '')),
            'rating' => $extracted['rating'] ?? null,
            'review_count' => $extracted['review_count'] ?? null,
            'opening_hours' => $extracted['opening_hours'] ?? null,
        ];

        return LeadNormalizationService::normalize($candidate);
    }

    private function buildHeuristicPageAnalysis(string $tenantId, array $page): array
    {
        $lead = $page['lead_candidate'];
        $agency = $this->smartContext->loadCompanyProfile($tenantId);
        $scan = $page['page_scan'];
        $content = mb_strtolower(($page['title'] ?? '') . ' ' . ($page['content'] ?? '') . ' ' . ($lead['segment'] ?? '') . ' ' . ($lead['category'] ?? ''));

        $socialCount = count(array_filter([
            $lead['linkedin_url'] ?? null,
            $lead['instagram_url'] ?? null,
            $lead['facebook_url'] ?? null,
        ]));

        $signals = [
            'has_phone' => !empty($lead['phone']),
            'has_email' => !empty($lead['email']),
            'has_address' => !empty($lead['address']),
            'has_website' => !empty($lead['website']),
            'has_form' => !empty($scan['meta']['has_form']),
            'has_video' => !empty($scan['meta']['has_video']),
            'has_description' => !empty($scan['meta']['description']) || !empty($scan['meta']['og:description']),
            'has_schema' => !empty($scan['meta']['schema_org']),
            'has_reviews' => !empty($lead['rating']) || !empty($lead['review_count']),
            'has_social' => $socialCount > 0,
        ];

        $digitalMaturity = 1;
        foreach ($signals as $flag) {
            if ($flag) {
                $digitalMaturity++;
            }
        }
        if ($socialCount >= 2) {
            $digitalMaturity++;
        }
        $digitalMaturity = $this->normalizeScore($digitalMaturity);

        $icpKeywords = array_filter(array_map(
            fn($v) => mb_strtolower(trim((string) $v)),
            array_merge(
                (array) ($agency['icp'] ?? []),
                [(string) ($agency['agency_niche'] ?? '')]
            )
        ));

        $matchCount = 0;
        foreach ($icpKeywords as $keyword) {
            if ($keyword !== '' && str_contains($content, $keyword)) {
                $matchCount++;
            }
        }

        $fitScore = 4 + min(4, $matchCount * 2);
        if (trim((string) ($agency['name'] ?? '')) === 'Agência (perfil não configurado)') {
            $fitScore = 5;
        }
        if (!$signals['has_website'] && $page['source'] === 'generic') {
            $fitScore--;
        }
        $fitScore = $this->normalizeScore($fitScore);

        $offerClarity = $signals['has_description'] || count($scan['headers'] ?? []) >= 2
            ? ($signals['has_form'] ? 'Alta' : 'Média')
            : 'Baixa';

        $evidence = [];
        if ($lead['phone']) $evidence[] = 'Telefone público identificado na página.';
        if ($lead['email']) $evidence[] = 'Email público identificado na página.';
        if ($lead['address']) $evidence[] = 'Endereço visível no contexto atual.';
        if ($signals['has_form']) $evidence[] = 'Existe formulário ou captura de contato.';
        if ($signals['has_social']) $evidence[] = 'A página referencia redes sociais da operação.';
        if ($signals['has_reviews']) $evidence[] = 'Há sinais públicos de prova social/avaliações.';
        if ($signals['has_schema']) $evidence[] = 'A página publica dados estruturados (schema.org).';
        if ($signals['has_video']) $evidence[] = 'Há conteúdo multimídia na página.';
        if (empty($evidence)) {
            $evidence[] = 'Foi possível identificar apenas a URL e o título da página atual.';
        }

        $painPoints = [];
        if (!$signals['has_phone']) $painPoints[] = 'Não foi identificado telefone público.';
        if (!$signals['has_email']) $painPoints[] = 'Não foi identificado email público.';
        if (!$signals['has_form']) $painPoints[] = 'Não foi identificado formulário ou CTA claro de conversão.';
        if (!$signals['has_description']) $painPoints[] = 'A proposta de valor aparece pouco explícita no conteúdo principal.';
        if (!$signals['has_social']) $painPoints[] = 'A presença social não ficou evidente na página.';
        if (empty($painPoints)) {
            $painPoints[] = 'A página tem boa cobertura de sinais básicos; vale validar decisor e timing comercial.';
        }

        $opportunities = [];
        if ($signals['has_website'] && !$signals['has_form']) $opportunities[] = 'Existe oportunidade de otimizar captação de demanda e CTA da página.';
        if ($signals['has_website'] && !$signals['has_social']) $opportunities[] = 'Há espaço para reforçar presença digital e distribuição de conteúdo.';
        if ($signals['has_reviews']) $opportunities[] = 'A operação já tem prova social que pode ser convertida em argumento comercial.';
        if (!$signals['has_email'] || !$signals['has_phone']) $opportunities[] = 'Mapear contato decisor pode gerar uma abordagem mais precisa.';
        if (empty($opportunities)) {
            $opportunities[] = 'A página merece aprofundamento manual para identificar oportunidades específicas de abordagem.';
        }

        $summary = sprintf(
            '%s parece ser uma %s com sinais digitais %s. A página atual mostra %s e o encaixe com o contexto estratégico foi avaliado em %d/10.',
            $lead['name'] ?: $this->fallbackLeadName($page),
            $lead['category'] ?: ($page['page_type'] === 'profile' ? 'presença profissional' : 'operação digital'),
            $this->scoreLabel($digitalMaturity),
            $offerClarity === 'Alta' ? 'uma oferta relativamente clara' : 'uma oferta que ainda precisa de interpretação',
            $fitScore
        );

        $recommendation = $fitScore >= 7
            ? 'Vale avançar para abordagem consultiva com foco em oportunidades práticas observadas na página.'
            : 'Antes de abordar, vale validar melhor segmento, decisor e aderência ao ICP da empresa.';

        $nextActions = [
            'Executar a qualificação rápida para priorizar a oportunidade.',
            'Abrir o copiloto e pedir hipótese de abordagem baseada nesta página.',
            !empty($page['existing_lead'])
                ? 'Atualizar o lead já existente no Vault com este novo contexto.'
                : 'Salvar a oportunidade no Vault para continuar o acompanhamento.',
        ];

        $warnings = [];
        if (trim((string) ($lead['segment'] ?? '')) === '') $warnings[] = 'Segmento não identificado explicitamente na página.';
        if ($fitScore <= 5) $warnings[] = 'O encaixe com o ICP ainda depende de validação humana adicional.';

        return [
            'company' => $lead['name'] ?: $this->fallbackLeadName($page),
            'pageType' => $page['page_type'],
            'summary' => $summary,
            'positioning' => $this->inferPositioning($page),
            'offerClarity' => $offerClarity,
            'digitalMaturity' => $digitalMaturity,
            'fitScore' => $fitScore,
            'qualificationHint' => $fitScore >= 7 ? 'Bom candidato para abordagem' : 'Precisa de validação complementar',
            'evidence' => array_values(array_unique($evidence)),
            'opportunities' => array_values(array_unique($opportunities)),
            'painPoints' => array_values(array_unique($painPoints)),
            'recommendation' => $recommendation,
            'nextActions' => array_values(array_unique($nextActions)),
            'contextForVault' => $summary . ' ' . $recommendation,
            'warnings' => array_values(array_unique($warnings)),
            'leadCandidate' => $lead,
            'existingLead' => $page['existing_lead'] ?: null,
            'mode' => 'heuristic',
        ];
    }

    private function buildHeuristicQualification(string $tenantId, array $page): array
    {
        $analysis = $this->buildHeuristicPageAnalysis($tenantId, $page);
        $lead = $page['lead_candidate'];
        $scan = $page['page_scan'];

        $fitScore = $this->normalizeScore((int) ($analysis['fitScore'] ?? 5));
        $potentialScore = $this->normalizeScore((int) round(($fitScore + (11 - (int) ($analysis['digitalMaturity'] ?? 5))) / 2 + (!empty($lead['phone']) || !empty($lead['email']) ? 1 : 0)));
        $urgencyScore = $this->normalizeScore((int) round(($fitScore + (!empty($scan['meta']['has_form']) ? 4 : 6)) / 2));

        $positiveSignals = [];
        if ($lead['phone']) $positiveSignals[] = 'Contato telefônico público identificado.';
        if ($lead['email']) $positiveSignals[] = 'Email público identificado.';
        if (!empty($page['existing_lead'])) $positiveSignals[] = 'Já existe histórico desse lead no Vault.';
        if (!empty($scan['meta']['has_form'])) $positiveSignals[] = 'A página possui captura de contato.';
        if (!empty($lead['website'])) $positiveSignals[] = 'O lead tem presença própria na web.';

        $objectionSignals = [];
        foreach ($analysis['painPoints'] as $item) {
            $objectionSignals[] = $item;
        }
        if (empty($positiveSignals)) {
            $positiveSignals[] = 'Há contexto mínimo de URL e título para continuar a investigação.';
        }

        $recommendedAction = $fitScore >= 7 || $potentialScore >= 7 ? 'capturar_agora' : ($fitScore >= 5 ? 'qualificar_mais' : 'observar');
        $confidence = (!empty($lead['phone']) || !empty($lead['email']) || !empty($page['existing_lead'])) ? 'Média' : 'Baixa';

        return [
            'fitScore' => $fitScore,
            'potentialScore' => $potentialScore,
            'urgencyScore' => $urgencyScore,
            'verdict' => $recommendedAction === 'capturar_agora'
                ? 'Oportunidade com sinais suficientes para entrar no radar comercial agora.'
                : 'Vale continuar qualificando antes de priorizar uma abordagem forte.',
            'positiveSignals' => array_values(array_unique($positiveSignals)),
            'objectionSignals' => array_values(array_unique($objectionSignals)),
            'nextSteps' => $recommendedAction === 'capturar_agora'
                ? 'Salvar no Vault, identificar decisor e preparar uma abordagem consultiva baseada nos gaps observados.'
                : 'Levantar decisor, canal de contato e validar melhor o encaixe com o ICP antes de avançar.',
            'approachSuggestion' => $recommendedAction === 'capturar_agora'
                ? 'Abordagem consultiva curta, citando o que a página mostra hoje e abrindo conversa sobre oportunidades claras de melhoria.'
                : 'Abordagem exploratória, buscando entender contexto comercial e maturidade antes de propor solução.',
            'recommendedAction' => $recommendedAction,
            'confidence' => $confidence,
            'mode' => 'heuristic',
        ];
    }

    private function buildHeuristicVisualAnalysis(string $tenantId, array $page): array
    {
        $analysis = $this->buildHeuristicPageAnalysis($tenantId, $page);
        $hasScreenshot = trim((string) ($page['screenshot'] ?? '')) !== '';

        return [
            'firstImpression' => $hasScreenshot
                ? 'A extensão capturou a tela atual, mas a leitura visual completa depende do processamento multimodal.'
                : 'Sem screenshot confiável, a leitura visual fica limitada ao contexto textual da página.',
            'brandClarity' => $this->normalizeScore((int) ($analysis['digitalMaturity'] ?? 5) - 1),
            'visualHierarchy' => $this->normalizeScore(!empty($page['page_scan']['headers']) ? min(9, count($page['page_scan']['headers']) + 2) : 4),
            'ctaClarity' => $this->normalizeScore(!empty($page['page_scan']['meta']['has_form']) ? 7 : 4),
            'perceivedTrust' => $this->normalizeScore(!empty($page['lead_candidate']['rating']) || !empty($page['lead_candidate']['review_count']) ? 7 : 5),
            'digitalMaturity' => $this->normalizeScore((int) ($analysis['digitalMaturity'] ?? 5)),
            'strengths' => [
                'A página atual possui material suficiente para uma leitura comercial inicial.',
                !empty($page['page_scan']['meta']['has_form']) ? 'Existe ao menos um caminho de conversão visível.' : 'O contexto textual da página foi capturado para análise.',
            ],
            'weaknesses' => [
                'Sem leitura multimodal confirmada, não dá para afirmar detalhes finos de layout com total segurança.',
                'Elementos visuais específicos dependem da imagem completa da página.',
            ],
            'commercialSignals' => [
                'A percepção visual pode influenciar confiança, proposta de valor e conversão.',
                'Cruzar leitura visual com conteúdo e CTA ajuda a priorizar a abordagem.',
            ],
            'recommendation' => 'Use a leitura visual junto da análise textual para validar clareza de marca, CTA e maturidade percebida antes da abordagem.',
            'warnings' => $hasScreenshot
                ? ['A leitura abaixo pode ser substituída pela análise multimodal assim que o provedor responder.']
                : ['Screenshot ausente ou inválido para análise visual.'],
            'mode' => 'heuristic',
        ];
    }

    private function sanitizePageAnalysis(array $parsed, array $fallback, array $page, string $mode): array
    {
        $lead = is_array($parsed['extractedLead'] ?? null) ? $parsed['extractedLead'] : ($parsed['leadCandidate'] ?? []);
        $sanitizedLead = LeadNormalizationService::normalize(array_merge($fallback['leadCandidate'], is_array($lead) ? $lead : []));

        return [
            'company' => $this->coalesceString($parsed['company'] ?? null, $fallback['company']),
            'pageType' => $this->coalesceString($parsed['pageType'] ?? null, $fallback['pageType']),
            'summary' => $this->coalesceString($parsed['summary'] ?? null, $fallback['summary']),
            'positioning' => $this->coalesceString($parsed['positioning'] ?? null, $fallback['positioning']),
            'offerClarity' => $this->normalizeEnum($parsed['offerClarity'] ?? null, ['Alta', 'Média', 'Baixa'], $fallback['offerClarity']),
            'digitalMaturity' => $this->normalizeScore($parsed['digitalMaturity'] ?? $fallback['digitalMaturity']),
            'fitScore' => $this->normalizeScore($parsed['fitScore'] ?? $fallback['fitScore']),
            'qualificationHint' => $this->coalesceString($parsed['qualificationHint'] ?? null, $fallback['qualificationHint']),
            'evidence' => $this->normalizeStringList($parsed['evidence'] ?? [], $fallback['evidence']),
            'opportunities' => $this->normalizeStringList($parsed['opportunities'] ?? [], $fallback['opportunities']),
            'painPoints' => $this->normalizeStringList($parsed['painPoints'] ?? [], $fallback['painPoints']),
            'recommendation' => $this->coalesceString($parsed['recommendation'] ?? null, $fallback['recommendation']),
            'nextActions' => $this->normalizeStringList($parsed['nextActions'] ?? [], $fallback['nextActions']),
            'contextForVault' => $this->coalesceString($parsed['contextForVault'] ?? null, $fallback['contextForVault']),
            'warnings' => $this->normalizeStringList($parsed['warnings'] ?? [], $fallback['warnings']),
            'leadCandidate' => $sanitizedLead,
            'existingLead' => $page['existing_lead'] ?: null,
            'mode' => $mode,
        ];
    }

    private function sanitizeQualification(array $parsed, array $fallback, string $mode): array
    {
        return [
            'fitScore' => $this->normalizeScore($parsed['fitScore'] ?? $fallback['fitScore']),
            'potentialScore' => $this->normalizeScore($parsed['potentialScore'] ?? $fallback['potentialScore']),
            'urgencyScore' => $this->normalizeScore($parsed['urgencyScore'] ?? $fallback['urgencyScore']),
            'verdict' => $this->coalesceString($parsed['verdict'] ?? null, $fallback['verdict']),
            'positiveSignals' => $this->normalizeStringList($parsed['positiveSignals'] ?? [], $fallback['positiveSignals']),
            'objectionSignals' => $this->normalizeStringList($parsed['objectionSignals'] ?? [], $fallback['objectionSignals']),
            'nextSteps' => $this->coalesceString($parsed['nextSteps'] ?? null, $fallback['nextSteps']),
            'approachSuggestion' => $this->coalesceString($parsed['approachSuggestion'] ?? null, $fallback['approachSuggestion']),
            'recommendedAction' => $this->normalizeEnum($parsed['recommendedAction'] ?? null, ['capturar_agora', 'qualificar_mais', 'observar'], $fallback['recommendedAction']),
            'confidence' => $this->normalizeEnum($parsed['confidence'] ?? null, ['Alta', 'Média', 'Baixa'], $fallback['confidence']),
            'mode' => $mode,
        ];
    }

    private function sanitizeVisualAnalysis(array $parsed, array $fallback, string $mode): array
    {
        return [
            'firstImpression' => $this->coalesceString($parsed['firstImpression'] ?? null, $fallback['firstImpression']),
            'brandClarity' => $this->normalizeScore($parsed['brandClarity'] ?? $fallback['brandClarity']),
            'visualHierarchy' => $this->normalizeScore($parsed['visualHierarchy'] ?? $fallback['visualHierarchy']),
            'ctaClarity' => $this->normalizeScore($parsed['ctaClarity'] ?? $fallback['ctaClarity']),
            'perceivedTrust' => $this->normalizeScore($parsed['perceivedTrust'] ?? $fallback['perceivedTrust']),
            'digitalMaturity' => $this->normalizeScore($parsed['digitalMaturity'] ?? $fallback['digitalMaturity']),
            'strengths' => $this->normalizeStringList($parsed['strengths'] ?? [], $fallback['strengths']),
            'weaknesses' => $this->normalizeStringList($parsed['weaknesses'] ?? [], $fallback['weaknesses']),
            'commercialSignals' => $this->normalizeStringList($parsed['commercialSignals'] ?? [], $fallback['commercialSignals']),
            'recommendation' => $this->coalesceString($parsed['recommendation'] ?? null, $fallback['recommendation']),
            'warnings' => $this->normalizeStringList($parsed['warnings'] ?? [], $fallback['warnings']),
            'mode' => $mode,
        ];
    }

    private function buildLeadAnalysisPayload(array $analysis, array $qualification, array $visual, array $leadCandidate): array
    {
        $fitScore = (int) min(100, max(10, ((int) ($qualification['fitScore'] ?? $analysis['fitScore'] ?? 5)) * 10));
        $priorityScore = (int) min(100, max(10, ((int) ($qualification['potentialScore'] ?? 5)) * 10));
        $urgencyValue = (int) ($qualification['urgencyScore'] ?? 5);

        return [
            'priorityScore' => $priorityScore,
            'fitScore' => $fitScore,
            'scoreExplanation' => $qualification['verdict'] ?? ($analysis['qualificationHint'] ?? ''),
            'digitalMaturity' => $this->mapDigitalMaturityLabel((int) ($analysis['digitalMaturity'] ?? $visual['digitalMaturity'] ?? 5)),
            'urgencyLevel' => $this->mapUrgencyLabel($urgencyValue),
            'summary' => $analysis['summary'] ?? '',
            'diagnosis' => array_slice($this->normalizeStringList($analysis['painPoints'] ?? [], []), 0, 4),
            'opportunities' => array_slice($this->normalizeStringList($analysis['opportunities'] ?? [], []), 0, 4),
            'recommendations' => array_slice(array_values(array_unique(array_merge(
                $this->normalizeStringList($analysis['nextActions'] ?? [], []),
                [$qualification['nextSteps'] ?? '', $visual['recommendation'] ?? '']
            ))), 0, 4),
            'operonFit' => $qualification['approachSuggestion'] ?? ($analysis['recommendation'] ?? ''),
            'extractedContact' => [
                'phone' => $leadCandidate['phone'] ?? '',
                'address' => $leadCandidate['address'] ?? '',
                'website' => $leadCandidate['website'] ?? '',
            ],
            'socialPresence' => [
                'instagram' => $leadCandidate['instagram_url'] ?? '',
                'facebook' => $leadCandidate['facebook_url'] ?? '',
                'linkedin' => $leadCandidate['linkedin_url'] ?? '',
            ],
        ];
    }

    private function mergeHumanContext(array $current, string $summary, array $page): array
    {
        $current = is_array($current) ? $current : [];
        $block = sprintf(
            "Snapshot da extensão em %s\nPágina: %s\nURL: %s\n%s",
            date('d/m/Y H:i'),
            $page['title'] ?: $this->fallbackLeadName($page),
            $page['url'],
            trim($summary)
        );

        $snapshots = is_array($current['extension_snapshots'] ?? null) ? $current['extension_snapshots'] : [];
        array_unshift($snapshots, [
            'captured_at' => date('c'),
            'title' => $page['title'],
            'url' => $page['url'],
            'summary' => $summary,
        ]);

        $current['extension_snapshots'] = array_slice($snapshots, 0, 5);
        $current['extension_last_analysis_at'] = date('c');
        $current['extension_source_url'] = $page['url'];

        $existingContext = trim((string) ($current['context'] ?? ''));
        if ($existingContext === '') {
            $current['context'] = $block;
        } elseif (!str_contains($existingContext, (string) $page['url'])) {
            $current['context'] = $this->truncateText($existingContext . "\n\n" . $block, 2500);
        }

        return $current;
    }

    private function mergeEnrichmentData(array $current, array $analysis, array $qualification, array $visual, array $page): array
    {
        $current = is_array($current) ? $current : [];
        $current['extension_cockpit'] = [
            'saved_at' => date('c'),
            'source' => $page['source'],
            'url' => $page['url'],
            'title' => $page['title'],
            'page_type' => $page['page_type'],
            'analysis' => [
                'summary' => $analysis['summary'] ?? '',
                'fit_score' => $analysis['fitScore'] ?? null,
                'digital_maturity' => $analysis['digitalMaturity'] ?? null,
                'recommendation' => $analysis['recommendation'] ?? '',
                'evidence' => $analysis['evidence'] ?? [],
            ],
            'qualification' => [
                'fit_score' => $qualification['fitScore'] ?? null,
                'potential_score' => $qualification['potentialScore'] ?? null,
                'urgency_score' => $qualification['urgencyScore'] ?? null,
                'verdict' => $qualification['verdict'] ?? '',
                'next_steps' => $qualification['nextSteps'] ?? '',
            ],
            'visual' => [
                'first_impression' => $visual['firstImpression'] ?? '',
                'digital_maturity' => $visual['digitalMaturity'] ?? null,
                'recommendation' => $visual['recommendation'] ?? '',
            ],
        ];

        return $current;
    }

    private function insertLeadActivity(
        string $tenantId,
        string $leadId,
        string $userId,
        string $content,
        array $analysis,
        array $qualification,
        array $visual,
        array $page
    ): void {
        Database::execute(
            "INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, content, metadata)
             VALUES (?, ?, ?, ?, 'extension_analysis', 'Snapshot salvo pela extensão Operon', ?, ?)",
            [
                Helpers::uuid(),
                $tenantId,
                $leadId,
                $userId,
                $content,
                json_encode([
                    'source' => 'chrome_extension',
                    'page_url' => $page['url'],
                    'page_title' => $page['title'],
                    'page_type' => $page['page_type'],
                    'analysis' => $analysis,
                    'qualification' => $qualification,
                    'visual' => $visual,
                ], JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function buildContextSummary(array $analysis, array $qualification, array $visual, array $page): string
    {
        $parts = [];
        $parts[] = 'Resumo: ' . trim((string) ($analysis['summary'] ?? ''));
        $parts[] = 'Veredito comercial: ' . trim((string) ($qualification['verdict'] ?? ''));
        $parts[] = 'Próximo passo: ' . trim((string) ($qualification['nextSteps'] ?? ''));

        if (!empty($visual['firstImpression'])) {
            $parts[] = 'Leitura visual: ' . trim((string) $visual['firstImpression']);
        }

        $parts[] = 'Origem: ' . ($page['title'] ?: 'Página atual') . ' — ' . $page['url'];

        return trim(implode("\n", array_filter($parts)));
    }

    private function buildActivityContent(array $analysis, array $qualification, array $visual, array $page): string
    {
        $lines = [
            'Página: ' . ($page['title'] ?: $this->fallbackLeadName($page)),
            'URL: ' . $page['url'],
            '',
            'Resumo',
            trim((string) ($analysis['summary'] ?? '')),
            '',
            'Veredito',
            trim((string) ($qualification['verdict'] ?? '')),
            '',
            'Próximo passo',
            trim((string) ($qualification['nextSteps'] ?? '')),
        ];

        if (!empty($visual['firstImpression'])) {
            $lines[] = '';
            $lines[] = 'Leitura visual';
            $lines[] = trim((string) $visual['firstImpression']);
        }

        return trim(implode("\n", $lines));
    }

    private function buildAgencyContextBlock(string $tenantId): string
    {
        $agency = $this->smartContext->loadCompanyProfile($tenantId);
        $services = implode(', ', array_filter((array) ($agency['offer_services'] ?? [])));
        $icp = implode(', ', array_filter((array) ($agency['icp'] ?? [])));
        $parts = [
            '--- CONTEXTO ESTRATÉGICO DA EMPRESA ---',
            'Empresa: ' . ($agency['name'] ?? 'Não configurado'),
            'Oferta: ' . ($agency['offer_title'] ?? 'Não configurada'),
            'Serviços: ' . ($services !== '' ? $services : 'Não configurados'),
            'ICP: ' . ($icp !== '' ? $icp : 'Não configurado'),
        ];

        if (!empty($agency['unique_proposal'])) {
            $parts[] = 'Proposta única: ' . $agency['unique_proposal'];
        }

        return implode("\n", $parts);
    }

    private function buildPageFactBlock(array $page): string
    {
        $lead = $page['lead_candidate'];
        $scan = $page['page_scan'];
        $headers = is_array($scan['headers'] ?? null) ? $scan['headers'] : [];
        $headerLines = [];
        foreach (array_slice($headers, 0, 6) as $header) {
            $tag = strtoupper((string) ($header['tag'] ?? ''));
            $text = trim((string) ($header['text'] ?? ''));
            if ($text !== '') {
                $headerLines[] = "- {$tag}: {$text}";
            }
        }

        $contacts = is_array($scan['contacts'] ?? null) ? $scan['contacts'] : [];
        $meta = is_array($scan['meta'] ?? null) ? $scan['meta'] : [];
        $existingLead = $page['existing_lead'];

        $parts = [
            '--- CONTEXTO FÁTICO DA PÁGINA ATUAL ---',
            'URL: ' . ($page['url'] ?: 'Não identificado'),
            'Título: ' . ($page['title'] ?: 'Não identificado'),
            'Fonte: ' . ($page['source'] ?: 'generic'),
            'Tipo de página: ' . $page['page_type'],
            'Nome identificado: ' . ($lead['name'] ?: 'Não identificado'),
            'Segmento identificado: ' . ($lead['segment'] ?: 'Não identificado'),
            'Categoria: ' . ($lead['category'] ?: 'Não identificado'),
            'Website identificado: ' . ($lead['website'] ?: 'Não identificado'),
            'Telefone identificado: ' . ($lead['phone'] ?: 'Não identificado'),
            'Email identificado: ' . ($lead['email'] ?: 'Não identificado'),
            'Endereço identificado: ' . ($lead['address'] ?: 'Não identificado'),
            'LinkedIn: ' . (($lead['linkedin_url'] ?? '') ?: 'Não identificado'),
            'Instagram: ' . (($lead['instagram_url'] ?? '') ?: 'Não identificado'),
            'Rating: ' . ($lead['rating'] !== null ? (string) $lead['rating'] : 'Não identificado'),
            'Reviews: ' . ($lead['review_count'] !== null ? (string) $lead['review_count'] : 'Não identificado'),
            'Meta description: ' . trim((string) ($meta['description'] ?? $meta['og:description'] ?? 'Não identificado')),
            'Tem formulário: ' . (!empty($meta['has_form']) ? 'Sim' : 'Não'),
            'Tem vídeo: ' . (!empty($meta['has_video']) ? 'Sim' : 'Não'),
            'Quantidade de imagens: ' . (string) ((int) ($meta['images_count'] ?? 0)),
            'Quantidade de links: ' . (string) ((int) ($meta['links_count'] ?? 0)),
            'Emails detectados no scan: ' . implode(', ', array_slice((array) ($contacts['emails'] ?? []), 0, 3)),
            'Telefones detectados no scan: ' . implode(', ', array_slice((array) ($contacts['phones'] ?? []), 0, 3)),
            'Headers principais:',
            !empty($headerLines) ? implode("\n", $headerLines) : '- Não identificados',
            'Trecho do conteúdo:',
            $this->truncateText($page['content'] ?: 'Não identificado', 2600),
        ];

        if ($existingLead) {
            $parts[] = 'Lead já existente no Vault: ' . ($existingLead['name'] ?? '') . ' (' . ($existingLead['pipeline_status'] ?? 'sem estágio') . ')';
        }

        return implode("\n", $parts);
    }

    private function detectPageType(array $page): string
    {
        $url = mb_strtolower((string) ($page['url'] ?? ''));
        $source = mb_strtolower((string) ($page['source'] ?? 'generic'));

        if ($source === 'linkedin' || str_contains($url, 'linkedin.com')) {
            return 'profile';
        }
        if ($source === 'google-maps' || str_contains($url, '/maps')) {
            return 'marketplace_listing';
        }
        if (preg_match('#/(blog|artigo|article|post)/#', $url)) {
            return 'article';
        }
        if (!empty($page['page_scan']['meta']['has_form'])) {
            return 'landing_page';
        }
        if ($source === 'generic') {
            return 'company_site';
        }
        return 'unknown';
    }

    private function inferNameFromTitle(string $title, string $url): string
    {
        $title = trim($title);
        if ($title !== '') {
            $parts = preg_split('/[\|\-–—•]/u', $title) ?: [];
            $candidate = trim((string) ($parts[0] ?? $title));
            if ($candidate !== '') {
                return $this->truncateText($candidate, 120);
            }
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $host = preg_replace('/^www\./', '', $host);
            return ucfirst((string) preg_replace('/\.[a-z.]+$/i', '', $host));
        }

        return 'Lead da página atual';
    }

    private function inferPositioning(array $page): string
    {
        $meta = is_array($page['page_scan']['meta'] ?? null) ? $page['page_scan']['meta'] : [];
        $headers = is_array($page['page_scan']['headers'] ?? null) ? $page['page_scan']['headers'] : [];

        $description = trim((string) ($meta['description'] ?? $meta['og:description'] ?? ''));
        if ($description !== '') {
            return $this->truncateText($description, 200);
        }

        foreach ($headers as $header) {
            $text = trim((string) ($header['text'] ?? ''));
            if ($text !== '') {
                return $this->truncateText($text, 200);
            }
        }

        return 'Posicionamento não identificado explicitamente no conteúdo capturado.';
    }

    private function fallbackLeadName(array $page): string
    {
        return $page['lead_candidate']['name'] ?: $this->inferNameFromTitle((string) ($page['title'] ?? ''), (string) ($page['url'] ?? ''));
    }

    private function preferSegment(string $current, string $incoming): string
    {
        $current = trim($current);
        $incoming = trim($incoming);
        if ($incoming === '') {
            return $current;
        }
        if ($current === '' || mb_strtolower($current) === 'geral' || mb_strtolower($current) === 'não identificado') {
            return $incoming;
        }
        return $current;
    }

    private function normalizeScore(mixed $value): int
    {
        $score = (int) round((float) $value);
        return max(0, min(10, $score));
    }

    private function normalizeEnum(mixed $value, array $allowed, string $fallback): string
    {
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function normalizeStringList(mixed $value, array $fallback): array
    {
        $items = is_array($value) ? $value : [];
        $normalized = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $normalized[] = $this->truncateText($item, 220);
            }
        }

        return !empty($normalized) ? array_values(array_unique($normalized)) : $fallback;
    }

    private function coalesceString(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);
        return $value !== '' ? $this->truncateText($value, 700) : $fallback;
    }

    private function truncateText(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(0, $limit - 3))) . '...';
    }

    private function scoreLabel(int $score): string
    {
        return match (true) {
            $score >= 8 => 'fortes',
            $score >= 5 => 'intermediários',
            default => 'fracos',
        };
    }

    private function mapDigitalMaturityLabel(int $score): string
    {
        return match (true) {
            $score >= 8 => 'Alta',
            $score >= 5 => 'Média',
            default => 'Baixa',
        };
    }

    private function mapUrgencyLabel(int $score): string
    {
        return match (true) {
            $score >= 8 => 'Alta',
            $score >= 5 => 'Média',
            default => 'Baixa',
        };
    }
}
