<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;
use App\Services\AI\GeminiProvider;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

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
        $userId = Session::get('user_id');

        $body = $this->getJsonBody();
        if (!$this->validateCsrf($body)) return;

        $tokens = new TokenService();
        if (!$tokens->hasSufficient('hunter', $tenantId)) {
            $this->jsonError('tokens_depleted', 'Tokens diários esgotados.');
            return;
        }

        $segment = trim($body['segment'] ?? '');
        $city = trim($body['city'] ?? '');
        $radius = (int) ($body['radius'] ?? 5);

        if (empty($segment) || empty($city)) {
            $this->jsonError('validation_error', 'Segmento e cidade são obrigatórios.');
            return;
        }

        // 1. Create Search Record
        $searchId = \App\Models\HunterSearch::create($tenantId, [
           'user_id' => $userId,
           'term' => "{$segment} em {$city}",
           'segment' => $segment,
           'location' => $city,
           'filters' => $body
        ]);

        // Token consumption is handled inside GeminiPlacesSearchProvider::search()
        // via TokenService with real API token tracking

        // 2. Perform Search
        $provider = new \App\Services\Hunter\GeminiPlacesSearchProvider();
        $results = $provider->search($segment, $city, $body);

        if (empty($results)) {
            \App\Models\HunterSearch::updateStatus($searchId, $tenantId, 'failed', 'Nenhum resultado retornado pelo provider.');
            $this->jsonError('search_failed', 'A busca falhou ou não retornou empresas válidas.');
            return;
        }

        // 3. Save Results
        $savedIds = [];
        foreach ($results as $item) {
            $savedIds[] = \App\Models\HunterResult::create($tenantId, [
                'search_id' => $searchId,
                'name' => $item['name'] ?? 'Empresa Desconhecida',
                'segment' => $item['segment'] ?? $segment,
                'address' => $item['address'] ?? null,
                'city' => $item['city'] ?? $city,
                'phone' => $item['phone'] ?? null,
                'website' => $item['website'] ?? null,
                'instagram' => $item['instagram'] ?? null,
                'email' => $item['email'] ?? null,
                'google_rating' => $item['google_rating'] ?? null,
                'google_reviews' => $item['google_reviews'] ?? null,
                'data_source' => 'gemini_places'
            ]);
        }

        \App\Models\HunterSearch::updateStatus($searchId, $tenantId, 'finished');

        // Retorna a lista de entidades salvas para o frontend
        $dbResults = \App\Models\HunterResult::getBySearchId($searchId, $tenantId);
        echo json_encode(['success' => true, 'search_id' => $searchId, 'results' => $dbResults]);
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

        // 1. Enrich details (find social/website if missing)
        $enricher = new \App\Services\Hunter\HunterEnrichmentService();
        $enricher->enrich($resultId, $tenantId);

        // 2. AI Analysis
        $analyzer = new \App\Services\Hunter\HunterAiAnalyzer();
        $success = $analyzer->analyze($resultId, $tenantId);

        if ($success) {
            // Token consumption already handled inside HunterAiAnalyzer + HunterEnrichmentService
            $fullResult = \App\Models\HunterResult::findById($resultId, $tenantId);
            $analysis = \App\Models\HunterResultAnalysis::findByResultId($resultId, $tenantId);
            
            echo json_encode(['success' => true, 'result' => $fullResult, 'analysis' => $analysis]);
        } else {
            $this->jsonError('analysis_failed', 'Falha ao processar a análise via Inteligência Artificial.');
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
        $userId = Session::get('user_id');
        
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
}
