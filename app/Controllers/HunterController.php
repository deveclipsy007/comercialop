<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Services\TokenService;

class HunterController
{
    public function __construct()
    {
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        try {
            $exists = \App\Core\Database::selectFirst(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='hunter_searches'",
                []
            );

            if ($exists) return; // already exists

            $sqlPath = defined('ROOT_PATH')
                ? ROOT_PATH . '/database/migrations/005_hunter_protocol.sql'
                : dirname(__DIR__, 2) . '/database/migrations/005_hunter_protocol.sql';

            if (!file_exists($sqlPath)) return;

            $sql = file_get_contents($sqlPath);
            $clean = preg_replace('/--[^\n]*/', '', $sql);
            
            foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
                if (!empty($stmt)) {
                    \App\Core\Database::execute($stmt, []);
                }
            }
        } catch (\Throwable $e) {
            error_log('[HunterController] Migration failed: ' . $e->getMessage());
        }
    }

    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        
        $presets = \App\Models\HunterPreset::getByTenant($tenantId);
        $savedResults = \App\Models\HunterResult::getSaved($tenantId);
        
        View::render('hunter/index', [
            'active' => 'hunter',
            'presets' => $presets,
            'savedResults' => $savedResults
        ]);
    }

    public function search(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $body = $this->getJsonBody();
        if (!$this->validateCsrf($body)) return;

        $tokens = new TokenService();
        if (!$tokens->hasSufficient('hunter', $tenantId)) {
            $this->jsonError('tokens_depleted', 'Tokens diários esgotados.');
            return;
        }

        $segment = trim($body['segment'] ?? '');
        $city = trim($body['city'] ?? '');
        $state = trim($body['state'] ?? '');
        $radius = (int) ($body['radius'] ?? 5);

        if (empty($segment) || empty($city)) {
            $this->jsonError('validation_error', 'Segmento e cidade são obrigatórios.');
            return;
        }

        try {
            $location = $state !== '' ? $city . ', ' . $state : $city;
            $placesService = new \App\Services\Hunter\GooglePlacesService(null, $tenantId);
            if (!$placesService->isConfigured()) {
                $this->jsonError(
                    'google_places_not_configured',
                    'Google Maps Places não está configurado. Cadastre a chave em Admin > Chaves de API > Google Maps Places.'
                );
                return;
            }

            // 1. Create Search Record
            $searchId = \App\Models\HunterSearch::create($tenantId, [
                'user_id' => $userId,
                'term' => "{$segment} em {$location}",
                'segment' => $segment,
                'location' => $location,
                'filters' => $body
            ]);

            // 2. Perform Search (Google Maps / Places real)
            $provider = new \App\Services\Hunter\GooglePlacesSearchProvider($placesService);
            $results = $provider->search($segment, $location, array_merge($body, [
                'tenant_id' => $tenantId,
            ]));

            if (empty($results)) {
                \App\Models\HunterSearch::updateStatus($searchId, $tenantId, 'failed', 'Nenhum resultado retornado pelo provider.');
                $this->jsonError('search_failed', 'Nenhum lead verificável foi retornado pelo Google Maps para esse radar.');
                return;
            }

            // 3. Save Results
            $savedIds = [];
            foreach ($results as $item) {
                if (empty($item['name']) || empty($item['place_id'])) {
                    continue;
                }

                $savedIds[] = \App\Models\HunterResult::create($tenantId, [
                    'search_id' => $searchId,
                    'name' => $item['name'],
                    'segment' => $item['segment'] ?? $item['category'] ?? $segment,
                    'address' => $item['address'] ?? null,
                    'city' => $item['city'] ?? $city,
                    'phone' => $item['phone'] ?? null,
                    'website' => $item['website'] ?? null,
                    'instagram' => $item['instagram'] ?? null,
                    'email' => $item['email'] ?? null,
                    'google_rating' => $item['google_rating'] ?? null,
                    'google_reviews' => $item['google_reviews'] ?? null,
                    'data_source' => $item['data_source'] ?? 'google_places_api',
                    'raw_source_data' => $item['raw_source_data'] ?? null,
                ]);
            }

            if (empty($savedIds)) {
                \App\Models\HunterSearch::updateStatus($searchId, $tenantId, 'failed', 'Os resultados retornados não tinham identificação verificável de place_id.');
                $this->jsonError('search_failed', 'Nenhum lead retornado tinha identificação verificável suficiente para importação.');
                return;
            }

            \App\Models\HunterSearch::updateStatus($searchId, $tenantId, 'finished');

            // Retorna a lista de entidades salvas para o frontend
            $dbResults = \App\Models\HunterResult::getBySearchId($searchId, $tenantId);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'search_id' => $searchId,
                'results' => $dbResults,
                'meta' => $this->buildSearchMeta($dbResults, $segment, $location, $radius),
            ]);
        } catch (\Throwable $e) {
            error_log('[HunterController] search error: ' . $e->getMessage());
            $this->jsonError('server_error', 'Erro interno ao processar busca: ' . $e->getMessage());
        }
    }

    public function analyze(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        
        $body = $this->getJsonBody();
        if (!$this->validateCsrf($body)) return;

        $resultId = $body['result_id'] ?? '';
        if (empty($resultId)) {
            $this->jsonError('validation_error', 'ID do resultado é obrigatório.');
            return;
        }

        // Token check (AI analyzer uses tokens)
        $tokens = new TokenService();
        if (!$tokens->hasSufficient('hunter', $tenantId)) {
            $this->jsonError('tokens_depleted', 'Tokens esgotados para análise IA.');
            return;
        }

        try {
            // 1. Enrich details (Google Places + site oficial)
            $enricher = new \App\Services\Hunter\HunterEnrichmentService();
            $enricher->enrich($resultId, $tenantId);

            // 2. AI Analysis
            $analyzer = new \App\Services\Hunter\HunterAiAnalyzer();
            $success = $analyzer->analyze($resultId, $tenantId);

            if ($success) {
                // Token consumption already handled inside HunterAiAnalyzer + HunterEnrichmentService
                $fullResult = \App\Models\HunterResult::findById($resultId, $tenantId);
                $analysis = \App\Models\HunterResultAnalysis::findByResultId($resultId, $tenantId);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'result' => $fullResult, 'analysis' => $analysis]);
            } else {
                $this->jsonError('analysis_failed', 'Falha ao processar a análise via Inteligência Artificial.');
            }
        } catch (\Throwable $e) {
            error_log('[HunterController] analyze error: ' . $e->getMessage());
            $this->jsonError('server_error', 'Erro interno na análise: ' . $e->getMessage());
        }
    }

    public function toggleSave(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        
        $body = $this->getJsonBody();
        if (!$this->validateCsrf($body)) return;

        $resultId = $body['result_id'] ?? '';
        $saving = (bool) ($body['saving'] ?? true);

        if ($resultId) {
            \App\Models\HunterResult::toggleSave($resultId, $tenantId, $saving);
            echo json_encode(['success' => true]);
        } else {
            $this->jsonError('validation_error', 'ID do resultado inválido.');
        }
    }

    public function importCrm(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');
        
        $body = $this->getJsonBody();
        if (!$this->validateCsrf($body)) return;

        $resultId = $body['result_id'] ?? '';
        if (!$resultId) {
            $this->jsonError('validation_error', 'ID do resultado inválido.');
            return;
        }

        $leadId = \App\Services\Hunter\HunterIntegrationService::importToCrm($resultId, $tenantId, $userId);

        if ($leadId) {
            echo json_encode(['success' => true, 'lead_id' => $leadId]);
        } else {
            $this->jsonError('import_failed', 'Não foi possível importar o lead, ele pode já ter sido importado ou não foi encontrado.');
        }
    }

    // Helpers
    private function getJsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function validateCsrf(array $body): bool
    {
        if (!Session::validateCsrf($body['_csrf'] ?? $_POST['_csrf'] ?? '')) {
            $this->jsonError('invalid_csrf', 'Token CSRF inválido.');
            return false;
        }
        return true;
    }

    private function jsonError(string $code, string $message): void
    {
        header('Content-Type: application/json');
        echo json_encode(['error' => $code, 'message' => $message]);
    }

    private function buildSearchMeta(array $results, string $segment, string $location, int $radius): array
    {
        $confirmedCounts = [
            'website' => 0,
            'phone' => 0,
            'email' => 0,
            'google_maps_url' => 0,
            'opening_hours' => 0,
        ];

        foreach ($results as $result) {
            $fieldStatuses = is_array($result['field_statuses'] ?? null) ? $result['field_statuses'] : [];
            foreach (array_keys($confirmedCounts) as $field) {
                if (($fieldStatuses[$field] ?? 'not_found') === 'confirmed') {
                    $confirmedCounts[$field]++;
                }
            }
        }

        $first = $results[0] ?? [];
        $verification = is_array($first['verification'] ?? null) ? $first['verification'] : [];

        return [
            'source' => $verification['source'] ?? 'google_places_api',
            'source_label' => $verification['source_label'] ?? 'Google Maps',
            'search_term' => $segment,
            'location' => $location,
            'radius_km' => $radius,
            'total_results' => count($results),
            'confirmed_counts' => $confirmedCounts,
            'rules' => [
                'Somente dados confirmados por fonte real são exibidos.',
                'Campos ausentes permanecem vazios e são marcados como não encontrados.',
                'Google Maps / Places é a origem primária dos cards.',
            ],
        ];
    }
}
