<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Core\Session;
use App\Helpers\AIResponseParser;
use App\Models\CompanyProfile;
use App\Models\HunterResult;
use App\Models\HunterResultAnalysis;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;

class HunterAiAnalyzer
{
    public function analyze(string $resultId, string $tenantId): bool
    {
        $result = HunterResult::findById($resultId, $tenantId);
        if (!$result) {
            return false;
        }

        $profile = CompanyProfile::findByTenant($tenantId);
        $fieldStatuses = is_array($result['field_statuses'] ?? null) ? $result['field_statuses'] : [];
        $digitalPresence = is_array($result['digital_presence'] ?? null) ? $result['digital_presence'] : [];
        $importNotes = is_array($result['import_notes'] ?? null) ? $result['import_notes'] : [];

        $analysis = $this->buildDeterministicAnalysis($result, $fieldStatuses, $digitalPresence, $importNotes);
        $narrative = $this->generateNarrative($result, $profile, $analysis, $tenantId);

        if (!empty($narrative['executive_summary'])) {
            $analysis['executive_summary'] = $narrative['executive_summary'];
        }
        if (!empty($narrative['recommended_approach'])) {
            $analysis['recommended_approach'] = $narrative['recommended_approach'];
        }

        HunterResultAnalysis::createOrUpdate($tenantId, $resultId, $analysis);
        return true;
    }

    private function generateNarrative(array $result, ?array $profile, array $analysis, string $tenantId): array
    {
        $myAgencyContext = "Temos uma agência B2B genérica. Preste serviços de marketing e vendas.";
        if ($profile) {
            $myAgencyContext = "Nossa agência: {$profile['agency_name']} (Nicho: {$profile['agency_niche']}).\n";
            $myAgencyContext .= "Nossa Oferta: {$profile['offer_summary']}\n";
            $myAgencyContext .= "Nosso Cliente Ideal (ICP): {$profile['icp_profile']}\n";
        }

        $systemPrompt = 'Você organiza análises comerciais orientadas a evidência. Só pode reescrever o que já foi confirmado; não pode adicionar fatos, hipóteses, dores prováveis, maturidade, faturamento ou qualquer inferência não comprovada.';

        $userPrompt = <<<PROMPT
Contexto da agência:
{$myAgencyContext}

Lead verificado:
- Nome: {$result['name']}
- Categoria confirmada: {$result['category']}
- Endereço: {$result['address']}
- Cidade/UF: {$result['city']} / {$result['state']}
- Website: {$result['website']}
- Telefone: {$result['phone']}
- Email: {$result['email']}
- Instagram: {$result['instagram']}
- Google Maps: {$result['google_maps_url']}
- Status operacional: {$result['status_label']}
- Avaliação Google: {$result['google_rating']} ({$result['google_reviews']} reviews)
- Horários: {$result['opening_hours_text']}

Evidências já aprovadas:
{$this->formatBulletLines($analysis['metadata']['evidence_used'] ?? [])}

Dados não verificados:
{$this->formatBulletLines($analysis['metadata']['missing_data'] ?? [])}

Lacunas confirmadas:
{$this->formatBulletLines($analysis['pain_points'] ?? [])}

Oportunidades com evidência:
{$this->formatBulletLines($analysis['opportunities'] ?? [])}

Retorne EXATAMENTE UM JSON válido com esta estrutura:
{
  "executive_summary": "Resumo curto, conservador e factual, sem fatos novos.",
  "recommended_approach": "Abordagem comercial usando apenas canais e sinais confirmados."
}

Regras:
- Não invente nada.
- Não use palavras como "provável", "parece", "talvez" ou equivalentes.
- Não cite fatos fora das listas aprovadas acima.
PROMPT;

        try {
            $provider = AIProviderFactory::make('hunter', $tenantId);
            $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, []);
            $response = $meta['parsed'] ?? [];
            $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

            try {
                $tokens = new TokenService();
                $tokens->consume(
                    'hunter',
                    $tenantId,
                    Session::get('id'),
                    $provider->getProviderName(),
                    $provider->getModel(),
                    (int) ($usage['input'] ?? 0),
                    (int) ($usage['output'] ?? 0)
                );
            } catch (\Throwable $e) {
                error_log('[HunterAiAnalyzer] Token tracking failed: ' . $e->getMessage());
            }

            if (AIResponseParser::hasError($response)) {
                error_log('[HunterAiAnalyzer] Narrative generation failed: ' . json_encode($response));
                return [];
            }

            $summary = trim((string) ($response['executive_summary'] ?? ''));
            $approach = trim((string) ($response['recommended_approach'] ?? ''));

            return [
                'executive_summary' => !$this->containsBannedReasoning($summary) ? $summary : '',
                'recommended_approach' => !$this->containsBannedReasoning($approach) ? $approach : '',
            ];
        } catch (\Throwable $e) {
            error_log('[HunterAiAnalyzer] Narrative fallback used: ' . $e->getMessage());
            return [];
        }
    }

    private function buildDeterministicAnalysis(
        array $result,
        array $fieldStatuses,
        array $digitalPresence,
        array $importNotes
    ): array {
        $evidenceUsed = $this->buildEvidenceUsed($result, $fieldStatuses, $digitalPresence, $importNotes);
        $missingData = $this->buildMissingData($fieldStatuses);
        $painPoints = $this->buildPainPoints($result, $fieldStatuses);
        $opportunities = $this->buildOpportunities($result, $fieldStatuses);
        $icpMatchScore = $this->calculateIcpMatchScore($result, $fieldStatuses);
        $priorityScore = $this->calculatePriorityScore($result, $fieldStatuses);
        $priorityLevel = $priorityScore >= 75 ? 'hot' : ($priorityScore >= 45 ? 'warm' : 'cold');

        return [
            'executive_summary' => $this->buildExecutiveSummary($result, $evidenceUsed, $missingData),
            'pain_points' => $painPoints,
            'opportunities' => $opportunities,
            'recommended_approach' => $this->buildRecommendedApproach($result, $fieldStatuses),
            'icp_match_score' => $icpMatchScore,
            'priority_score' => $priorityScore,
            'priority_level' => $priorityLevel,
            'metadata' => [
                'analysis_mode' => 'evidence_locked',
                'evidence_used' => $evidenceUsed,
                'missing_data' => $missingData,
            ],
        ];
    }

    private function buildEvidenceUsed(array $result, array $fieldStatuses, array $digitalPresence, array $importNotes): array
    {
        $evidence = [];

        if (($fieldStatuses['category'] ?? '') === 'confirmed' && !empty($result['category'])) {
            $evidence[] = 'Categoria confirmada: ' . $result['category'] . '.';
        }
        if (($fieldStatuses['address'] ?? '') === 'confirmed' && !empty($result['address'])) {
            $evidence[] = 'Endereço confirmado: ' . $result['address'] . '.';
        }
        if (($fieldStatuses['phone'] ?? '') === 'confirmed' && !empty($result['phone'])) {
            $evidence[] = 'Telefone comercial confirmado: ' . $result['phone'] . '.';
        }
        if (($fieldStatuses['website'] ?? '') === 'confirmed' && !empty($result['website'])) {
            $evidence[] = 'Site oficial confirmado: ' . $result['website'] . '.';
        }
        if (($fieldStatuses['email'] ?? '') === 'confirmed' && !empty($result['email'])) {
            $evidence[] = 'Email público confirmado: ' . $result['email'] . '.';
        }
        if (($fieldStatuses['instagram'] ?? '') === 'confirmed' && !empty($result['instagram'])) {
            $evidence[] = 'Instagram confirmado: ' . $result['instagram'] . '.';
        }
        if (($fieldStatuses['google_maps_url'] ?? '') === 'confirmed' && !empty($result['google_maps_url'])) {
            $evidence[] = 'Perfil no Google Maps confirmado.';
        }
        if (($fieldStatuses['google_rating'] ?? '') === 'confirmed' && ($result['google_rating'] ?? null) !== null) {
            $evidence[] = 'Nota média confirmada no Google: ' . number_format((float) $result['google_rating'], 1, ',', '.') . '.';
        }
        if (($fieldStatuses['google_reviews'] ?? '') === 'confirmed' && ($result['google_reviews'] ?? 0) > 0) {
            $evidence[] = 'Quantidade de avaliações confirmada no Google: ' . (int) $result['google_reviews'] . '.';
        }
        if (($fieldStatuses['opening_hours'] ?? '') === 'confirmed' && !empty($result['opening_hours_text'])) {
            $evidence[] = 'Horário de funcionamento confirmado.';
        }
        if (($fieldStatuses['status'] ?? '') === 'confirmed' && !empty($result['status_label'])) {
            $evidence[] = 'Status operacional confirmado: ' . $result['status_label'] . '.';
        }

        foreach ($digitalPresence as $signal) {
            if (!is_array($signal) || ($signal['status'] ?? '') !== 'confirmed' || empty($signal['label'])) {
                continue;
            }
            $evidence[] = $signal['label'] . ' confirmado' . (!empty($signal['source']) ? ' via ' . $signal['source'] : '') . '.';
        }

        foreach ($importNotes as $note) {
            $note = trim((string) $note);
            if ($note !== '') {
                $evidence[] = $note;
            }
        }

        return array_values(array_unique($evidence));
    }

    private function buildMissingData(array $fieldStatuses): array
    {
        $labels = [
            'website' => 'Site oficial',
            'phone' => 'Telefone comercial',
            'email' => 'Email público',
            'instagram' => 'Instagram',
            'opening_hours' => 'Horário de funcionamento',
            'google_reviews' => 'Avaliações do Google',
        ];

        $missing = [];
        foreach ($labels as $field => $label) {
            if (($fieldStatuses[$field] ?? 'not_found') !== 'confirmed') {
                $missing[] = $label . ' não verificado.';
            }
        }

        return $missing;
    }

    private function buildPainPoints(array $result, array $fieldStatuses): array
    {
        $painPoints = [];

        if (($fieldStatuses['website'] ?? 'not_found') !== 'confirmed') {
            $painPoints[] = 'Site oficial não foi confirmado na busca real.';
        }
        if (($fieldStatuses['email'] ?? 'not_found') !== 'confirmed') {
            $painPoints[] = 'Email público não foi encontrado em fonte verificada.';
        }
        if (($fieldStatuses['phone'] ?? 'not_found') !== 'confirmed') {
            $painPoints[] = 'Telefone comercial não foi confirmado.';
        }
        if (($fieldStatuses['opening_hours'] ?? 'not_found') !== 'confirmed') {
            $painPoints[] = 'Horário de funcionamento não foi confirmado.';
        }
        if (($fieldStatuses['google_reviews'] ?? 'not_found') !== 'confirmed' || (int) ($result['google_reviews'] ?? 0) === 0) {
            $painPoints[] = 'Não há volume de avaliações do Google confirmado para reforçar prova social.';
        }

        if (empty($painPoints)) {
            $painPoints[] = 'Sem lacunas críticas confirmadas além dos campos ausentes já sinalizados.';
        }

        return array_slice(array_values(array_unique($painPoints)), 0, 5);
    }

    private function buildOpportunities(array $result, array $fieldStatuses): array
    {
        $opportunities = [];

        if (($fieldStatuses['phone'] ?? 'not_found') === 'confirmed' && !empty($result['phone'])) {
            $opportunities[] = 'Abordagem por telefone é viável porque existe telefone comercial confirmado.';
        }
        if (($fieldStatuses['email'] ?? 'not_found') === 'confirmed' && !empty($result['email'])) {
            $opportunities[] = 'Abordagem por email é viável porque existe email público confirmado.';
        }
        if (($fieldStatuses['instagram'] ?? 'not_found') === 'confirmed' && !empty($result['instagram'])) {
            $opportunities[] = 'Abordagem por Instagram é viável porque o perfil foi confirmado em fonte oficial.';
        }
        if (($fieldStatuses['website'] ?? 'not_found') === 'confirmed' && !empty($result['website'])) {
            $opportunities[] = 'O site oficial permite personalizar a prospecção com base em informações públicas verificadas.';
        }
        if (($fieldStatuses['google_reviews'] ?? 'not_found') === 'confirmed' && (int) ($result['google_reviews'] ?? 0) > 0) {
            $opportunities[] = 'A presença no Google pode ser citada na abordagem porque há avaliações confirmadas.';
        }

        if (empty($opportunities)) {
            $opportunities[] = 'Antes de uma prospecção ativa, vale priorizar coleta adicional de canais de contato verificados.';
        }

        return array_slice(array_values(array_unique($opportunities)), 0, 5);
    }

    private function calculateIcpMatchScore(array $result, array $fieldStatuses): int
    {
        $score = 35;

        if (($fieldStatuses['category'] ?? 'not_found') === 'confirmed' && !empty($result['category'])) {
            $score += 20;
        }
        if (($fieldStatuses['website'] ?? 'not_found') === 'confirmed') {
            $score += 10;
        }
        if (($fieldStatuses['phone'] ?? 'not_found') === 'confirmed') {
            $score += 10;
        }
        if (($fieldStatuses['email'] ?? 'not_found') === 'confirmed') {
            $score += 10;
        }
        if (($fieldStatuses['google_reviews'] ?? 'not_found') === 'confirmed' && (int) ($result['google_reviews'] ?? 0) >= 10) {
            $score += 10;
        }
        if (($fieldStatuses['status'] ?? 'not_found') === 'confirmed') {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    private function calculatePriorityScore(array $result, array $fieldStatuses): int
    {
        $score = 20;

        if (($fieldStatuses['phone'] ?? 'not_found') === 'confirmed') {
            $score += 25;
        }
        if (($fieldStatuses['email'] ?? 'not_found') === 'confirmed') {
            $score += 20;
        }
        if (($fieldStatuses['website'] ?? 'not_found') === 'confirmed') {
            $score += 15;
        }
        if (($fieldStatuses['instagram'] ?? 'not_found') === 'confirmed') {
            $score += 10;
        }
        if (($fieldStatuses['google_reviews'] ?? 'not_found') === 'confirmed' && (int) ($result['google_reviews'] ?? 0) >= 20) {
            $score += 10;
        }
        if (($result['open_now'] ?? null) === true) {
            $score += 10;
        }
        if (($result['status'] ?? '') === 'CLOSED_PERMANENTLY') {
            $score -= 30;
        }

        return max(0, min(100, $score));
    }

    private function buildExecutiveSummary(array $result, array $evidenceUsed, array $missingData): string
    {
        $parts = [];
        $location = trim((string) ($result['address'] ?? ''));
        if ($location === '') {
            $location = trim(implode(' / ', array_filter([
                (string) ($result['city'] ?? ''),
                (string) ($result['state'] ?? ''),
            ])));
        }

        $intro = $result['name'] . ' foi identificado no Google Maps';
        if (!empty($result['category'])) {
            $intro .= ' como ' . $result['category'];
        }
        if ($location !== '') {
            $intro .= ', localizado em ' . $location;
        }
        $intro .= '.';
        $parts[] = $intro;

        if (!empty($evidenceUsed)) {
            $parts[] = 'Sinais confirmados: ' . $this->humanJoin(array_slice($evidenceUsed, 0, 4)) . '.';
        }

        if (!empty($missingData)) {
            $parts[] = 'Dados ainda não verificados: ' . $this->humanJoin(array_slice($missingData, 0, 4)) . '.';
        }

        return implode("\n\n", $parts);
    }

    private function buildRecommendedApproach(array $result, array $fieldStatuses): string
    {
        if (($fieldStatuses['phone'] ?? 'not_found') === 'confirmed' && !empty($result['phone'])) {
            return 'Inicie por telefone, porque existe um canal comercial confirmado. Use a categoria, a localização e os sinais confirmados no Google Maps para contextualizar a abordagem.';
        }

        if (($fieldStatuses['email'] ?? 'not_found') === 'confirmed' && !empty($result['email'])) {
            return 'Inicie por email, porque existe um contato público confirmado. A mensagem deve citar apenas dados confirmados, como categoria, localização e presença no Google Maps.';
        }

        if (($fieldStatuses['instagram'] ?? 'not_found') === 'confirmed' && !empty($result['instagram'])) {
            return 'Inicie por Instagram, porque o perfil foi confirmado em fonte oficial. A abordagem deve ser curta e apoiada nos dados confirmados do Google Maps.';
        }

        if (($fieldStatuses['website'] ?? 'not_found') === 'confirmed' && !empty($result['website'])) {
            return 'Comece pelo site oficial para personalizar a abordagem e identificar um canal público adicional antes de iniciar contato ativo.';
        }

        return 'A prospecção deve ser conservadora: mantenha o lead no radar e priorize coleta adicional de canais verificados antes de uma abordagem comercial ativa.';
    }

    private function containsBannedReasoning(string $text): bool
    {
        if ($text === '') {
            return true;
        }

        return preg_match('/\b(prov[aá]vel|parece|talvez|poss[ií]vel(?:mente)?|infer|estim|supost|aparent)\b/i', $text) === 1;
    }

    private function formatBulletLines(array $items): string
    {
        if (empty($items)) {
            return '- Nenhum item';
        }

        return implode("\n", array_map(
            static fn($item): string => '- ' . trim((string) $item),
            array_values(array_filter($items, static fn($item): bool => trim((string) $item) !== ''))
        ));
    }

    private function humanJoin(array $items): string
    {
        $items = array_values(array_filter(array_map(
            static fn($item): string => rtrim(trim((string) $item), '.'),
            $items
        )));

        $count = count($items);
        if ($count === 0) {
            return 'nenhum dado adicional';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return $items[0] . ' e ' . $items[1];
        }

        $last = array_pop($items);
        return implode(', ', $items) . ' e ' . $last;
    }
}
