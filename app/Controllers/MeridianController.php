<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Services\TokenService;

class MeridianController
{
    public function __construct()
    {
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        try {
            $exists = Database::selectFirst(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='meridian_analyses'",
                []
            );
            if ($exists) return;

            $sqlPath = defined('ROOT_PATH')
                ? ROOT_PATH . '/database/migrations/013_meridian.sql'
                : dirname(__DIR__, 2) . '/database/migrations/013_meridian.sql';

            if (!file_exists($sqlPath)) return;

            $sql = file_get_contents($sqlPath);
            $clean = preg_replace('/--[^\n]*/', '', $sql);

            foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
                if (!empty($stmt)) {
                    Database::execute($stmt, []);
                }
            }
        } catch (\Throwable $e) {
            error_log('[MeridianController] Migration failed: ' . $e->getMessage());
        }
    }

    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        // Load history
        $history = Database::select(
            "SELECT id, niche, sub_niche, adherence_score, potential_score, created_at
             FROM meridian_analyses
             WHERE tenant_id = ?
             ORDER BY created_at DESC
             LIMIT 20",
            [$tenantId]
        );

        // Check if company profile exists
        $profile = \App\Models\CompanyProfile::findByTenant($tenantId);
        $hasProfile = $profile && !empty($profile['agency_name']);

        View::render('meridian/index', [
            'active'     => 'meridian',
            'history'    => $history,
            'hasProfile' => $hasProfile,
        ]);
    }

    public function analyze(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('id');

        $body = $this->getJsonBody();
        if (!$this->validateCsrf($body)) return;

        $niche = trim($body['niche'] ?? '');
        if (empty($niche)) {
            $this->jsonError('validation_error', 'Informe o nicho a ser analisado.');
            return;
        }

        // Token check
        $tokens = new TokenService();
        if (!$tokens->hasSufficient('meridian', $tenantId)) {
            $this->jsonError('tokens_depleted', 'Tokens diários esgotados.');
            return;
        }

        // Check if company profile exists
        $profile = \App\Models\CompanyProfile::findByTenant($tenantId);
        if (!$profile || empty($profile['agency_name'])) {
            $this->jsonError('no_profile', 'Configure o Knowledge Base com o perfil da sua empresa antes de usar o Meridian.');
            return;
        }

        try {
            $analyzer = new \App\Services\Meridian\MeridianAnalyzer();
            $result = $analyzer->analyze($niche, $tenantId, [
                'sub_niche' => $body['sub_niche'] ?? '',
                'region'    => $body['region'] ?? '',
                'focus'     => $body['focus'] ?? '',
            ]);

            if (!$result['success']) {
                $this->jsonError('analysis_failed', 'Falha na análise: ' . ($result['error'] ?? 'Erro desconhecido'));
                return;
            }

            // Save to history
            $id = $this->generateId();
            $adherenceScore = $result['analysis']['adherence']['score'] ?? 0;
            $potentialScore = $result['analysis']['potential']['score'] ?? 0;

            Database::execute(
                "INSERT INTO meridian_analyses (id, tenant_id, user_id, niche, sub_niche, analysis_data, adherence_score, potential_score)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $id,
                    $tenantId,
                    $userId,
                    $niche,
                    $body['sub_niche'] ?? null,
                    json_encode($result['analysis'], JSON_UNESCAPED_UNICODE),
                    $adherenceScore,
                    $potentialScore,
                ]
            );

            header('Content-Type: application/json');
            echo json_encode([
                'success'  => true,
                'id'       => $id,
                'analysis' => $result['analysis'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[MeridianController] analyze error: ' . $e->getMessage());
            $this->jsonError('server_error', 'Erro interno: ' . $e->getMessage());
        }
    }

    public function history(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $body = $this->getJsonBody();
        $id = trim($body['id'] ?? '');

        if (empty($id)) {
            $this->jsonError('validation_error', 'ID da análise é obrigatório.');
            return;
        }

        $row = Database::selectFirst(
            "SELECT * FROM meridian_analyses WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$row) {
            $this->jsonError('not_found', 'Análise não encontrada.');
            return;
        }

        $row['analysis_data'] = json_decode($row['analysis_data'] ?? '{}', true);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'analysis' => $row['analysis_data'], 'niche' => $row['niche']], JSON_UNESCAPED_UNICODE);
    }

    public function delete(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $body = $this->getJsonBody();
        if (!$this->validateCsrf($body)) return;

        $id = trim($body['id'] ?? '');
        if (empty($id)) {
            $this->jsonError('validation_error', 'ID da análise é obrigatório.');
            return;
        }

        Database::execute(
            "DELETE FROM meridian_analyses WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
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

    private function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
