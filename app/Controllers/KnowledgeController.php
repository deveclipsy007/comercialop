<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\AnalysisTrace;
use App\Models\CompanyProfile;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeEmbedding;
use App\Services\Knowledge\KnowledgeIndexingService;

/**
 * Módulo Knowledge Base — CRUD do perfil de empresa + pipeline RAG.
 *
 * Rotas:
 *   GET  /knowledge              → index()        — painel principal
 *   POST /knowledge/profile      → saveProfile()  — salva perfil e re-indexa
 *   POST /knowledge/reindex      → reindex()      — força re-indexação sem alterar dados
 *   GET  /knowledge/status       → getStatus()    — JSON com status atual (polling)
 *   POST /knowledge/document/:id/delete → deleteDocument() — remove doc específico
 */
class KnowledgeController
{
    private KnowledgeIndexingService $indexer;

    public function __construct()
    {
        $this->ensureTablesExist();
        $this->indexer = new KnowledgeIndexingService();
    }

    /**
     * Aplica a migration 004_rag_module.sql automaticamente se as tabelas não existirem.
     * Usa `CREATE TABLE IF NOT EXISTS`, portanto é idempotente.
     */
    private function ensureTablesExist(): void
    {
        try {
            // Testa se a tabela principal existe (SQLite)
            $exists = Database::selectFirst(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='company_profiles'",
                []
            );

            if ($exists) return; // já existe, nada a fazer

            // Aplica migration
            $sqlPath = defined('ROOT_PATH')
                ? ROOT_PATH . '/database/migrations/004_rag_module.sql'
                : dirname(__DIR__, 2) . '/database/migrations/004_rag_module.sql';

            if (!file_exists($sqlPath)) {
                error_log('[KnowledgeController] Migration file not found: ' . $sqlPath);
                return;
            }

            $sql = file_get_contents($sqlPath);

            // Remove comentários de linha e executa statement por statement
            $clean = preg_replace('/--[^\n]*/', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
                Database::execute($stmt, []);
            }

            error_log('[KnowledgeController] Migration 004_rag_module aplicada automaticamente.');
        } catch (\Throwable $e) {
            // Se falhar, deixa o fluxo continuar — o erro de PDO vai ser exibido normalmente
            error_log('[KnowledgeController] ensureTablesExist() falhou: ' . $e->getMessage());
        }
    }

    // ─── GET /knowledge ──────────────────────────────────────────────────────

    public function index(): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { View::redirect('/login'); }

        $profile   = CompanyProfile::findByTenant($tenantId);
        $documents = KnowledgeDocument::allByTenant($tenantId);
        $chunks    = KnowledgeChunk::countByTenant($tenantId);
        $traces    = AnalysisTrace::recentByTenant($tenantId, 20);

        // Estatísticas rápidas por doc_type
        $docsByType = [];
        foreach ($documents as $doc) {
            $docsByType[$doc['doc_type']] = ($docsByType[$doc['doc_type']] ?? 0) + 1;
        }

        View::render('knowledge/index', [
            'active'      => 'knowledge',
            'profile'     => $profile,
            'documents'   => $documents,
            'chunks'      => $chunks,
            'docsByType'  => $docsByType,
            'traces'      => $traces,
            'traceCount'  => AnalysisTrace::countByTenant($tenantId),
            'hasIndex'    => KnowledgeEmbedding::existsForTenant($tenantId),
            'flash'       => Session::getFlash('success') ?? Session::getFlash('error'),
        ]);
    }

    // ─── POST /knowledge/profile ─────────────────────────────────────────────

    public function saveProfile(): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { $this->jsonError('Não autorizado', 401); }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        // ── Coleta e sanitiza campos do formulário ──────────────────────────

        // Campos JSON que chegam como arrays de texto (um por linha)
        $differentials = $this->parseTextareaToArray($_POST['differentials'] ?? '');
        $icpSegment    = $this->parseTextareaToArray($_POST['icp_segment']   ?? '');
        $icpPainPoints = $this->parseTextareaToArray($_POST['icp_pain_points'] ?? '');

        // Serviços: array associativo {name, description, price_range}
        $services = [];
        foreach ($_POST['services'] ?? [] as $svc) {
            $name = trim($svc['name'] ?? '');
            if ($name !== '') {
                $services[] = [
                    'name'        => $name,
                    'description' => trim($svc['description'] ?? ''),
                    'price_range' => trim($svc['price_range'] ?? ''),
                ];
            }
        }

        // Cases: array associativo {client, result, niche, timeframe}
        $cases = [];
        foreach ($_POST['cases'] ?? [] as $c) {
            $client = trim($c['client'] ?? '');
            if ($client !== '') {
                $cases[] = [
                    'client'    => $client,
                    'result'    => trim($c['result']    ?? ''),
                    'niche'     => trim($c['niche']     ?? ''),
                    'timeframe' => trim($c['timeframe'] ?? ''),
                ];
            }
        }

        // Objections: array associativo {objection, response}
        $objections = [];
        foreach ($_POST['objection_responses'] ?? [] as $o) {
            $obj = trim($o['objection'] ?? '');
            if ($obj !== '') {
                $objections[] = [
                    'objection' => $obj,
                    'response'  => trim($o['response'] ?? ''),
                ];
            }
        }

        // Competitors: array associativo {name, weakness, how_to_win}
        $competitors = [];
        foreach ($_POST['competitors'] ?? [] as $comp) {
            $name = trim($comp['name'] ?? '');
            if ($name !== '') {
                $competitors[] = [
                    'name'       => $name,
                    'weakness'   => trim($comp['weakness']   ?? ''),
                    'how_to_win' => trim($comp['how_to_win'] ?? ''),
                ];
            }
        }

        $data = [
            // Identidade
            'agency_name'          => trim($_POST['agency_name']         ?? ''),
            'agency_city'          => trim($_POST['agency_city']         ?? ''),
            'agency_state'         => trim($_POST['agency_state']        ?? ''),
            'agency_niche'         => trim($_POST['agency_niche']        ?? ''),
            'founding_year'        => trim($_POST['founding_year']       ?? ''),
            'team_size'            => trim($_POST['team_size']           ?? ''),
            'website_url'          => trim($_POST['website_url']         ?? ''),
            // Oferta
            'offer_summary'        => trim($_POST['offer_summary']       ?? ''),
            'offer_price_range'    => trim($_POST['offer_price_range']   ?? ''),
            'services'             => $services,
            'guarantees'           => trim($_POST['guarantees']          ?? ''),
            'delivery_timeline'    => trim($_POST['delivery_timeline']   ?? ''),
            // Posicionamento
            'differentials'        => $differentials,
            'unique_value_prop'    => trim($_POST['unique_value_prop']   ?? ''),
            'awards_recognition'   => trim($_POST['awards_recognition']  ?? ''),
            // ICP
            'icp_profile'          => trim($_POST['icp_profile']         ?? ''),
            'icp_segment'          => $icpSegment,
            'icp_company_size'     => trim($_POST['icp_company_size']    ?? ''),
            'icp_ticket_range'     => trim($_POST['icp_ticket_range']    ?? ''),
            'icp_pain_points'      => $icpPainPoints,
            // Prova social
            'cases'                => $cases,
            'portfolio_url'        => trim($_POST['portfolio_url']       ?? ''),
            // Comercial
            'objection_responses'  => $objections,
            'competitors'          => $competitors,
            'pricing_justification'=> trim($_POST['pricing_justification'] ?? ''),
            // Contexto livre
            'custom_context'       => trim($_POST['custom_context']      ?? ''),
        ];

        // ── Salva perfil e dispara indexação ────────────────────────────────

        $profileId = CompanyProfile::upsert($tenantId, $data);
        $indexResult = $this->indexer->indexTenant($tenantId);

        if ($indexResult['success']) {
            $this->jsonSuccess([
                'message'        => 'Perfil salvo e indexado com sucesso.',
                'chunks_indexed' => $indexResult['chunks_indexed'],
                'docs_created'   => $indexResult['docs_created'],
                'profile_id'     => $profileId,
            ]);
        } else {
            $this->jsonError(
                'Perfil salvo, mas indexação falhou: ' . ($indexResult['error'] ?? 'erro desconhecido'),
                200  // HTTP 200 — dados salvos, só o RAG falhou
            );
        }
    }

    // ─── POST /knowledge/reindex ─────────────────────────────────────────────

    public function reindex(): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { $this->jsonError('Não autorizado', 401); }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $profile = CompanyProfile::findByTenant($tenantId);
        if (!$profile) {
            $this->jsonError('Nenhum perfil encontrado. Salve o perfil primeiro.');
        }

        $result = $this->indexer->indexTenant($tenantId);

        if ($result['success']) {
            $this->jsonSuccess([
                'message'        => 'Re-indexação concluída.',
                'chunks_indexed' => $result['chunks_indexed'],
                'chunks_failed'  => $result['chunks_failed'],
            ]);
        } else {
            $this->jsonError('Falha na re-indexação: ' . ($result['error'] ?? 'erro desconhecido'));
        }
    }

    // ─── GET /knowledge/status ───────────────────────────────────────────────

    public function getStatus(): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { $this->jsonError('Não autorizado', 401); }

        $profile = CompanyProfile::findByTenant($tenantId);

        if (!$profile) {
            $this->jsonSuccess([
                'status'       => 'no_profile',
                'chunks_count' => 0,
                'has_index'    => false,
                'last_indexed' => null,
            ]);
            return;
        }

        $this->jsonSuccess([
            'status'          => $profile['indexing_status'],
            'chunks_count'    => (int) ($profile['chunks_count'] ?? 0),
            'profile_version' => (int) ($profile['profile_version'] ?? 1),
            'last_indexed'    => $profile['last_indexed_at'],
            'indexing_error'  => $profile['indexing_error'],
            'has_index'       => KnowledgeEmbedding::existsForTenant($tenantId),
            'doc_count'       => KnowledgeDocument::countByTenant($tenantId),
        ]);
    }

    // ─── POST /knowledge/document/:id/delete ─────────────────────────────────

    public function deleteDocument(string $docId): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { $this->jsonError('Não autorizado', 401); }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $doc = KnowledgeDocument::findById($docId);
        if (!$doc || $doc['tenant_id'] !== $tenantId) {
            $this->jsonError('Documento não encontrado', 404);
        }

        KnowledgeDocument::deleteById($docId);

        $this->jsonSuccess(['message' => 'Documento removido.']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function parseTextareaToArray(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($line) => $line !== ''
        ));
    }

    private function jsonSuccess(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, ...$data]);
        exit;
    }

    private function jsonError(string $message, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
