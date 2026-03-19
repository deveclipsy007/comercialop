<?php

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Services\DeepIntelligence\DeepIntelligenceManager;
use App\Models\TokenQuota;
use Exception;

class DeepIntelligenceController
{
    private DeepIntelligenceManager $manager;

    public function __construct()
    {
        $this->manager = new DeepIntelligenceManager();
    }

    /**
     * Endpoint API para executar uma inteligência sob demanda via AJAX.
     * POST /intelligence/run
     * Params: lead_id, type
     */
    public function runIntelligence(): void
    {
        Session::requireAuth();

        // Limpar qualquer output anterior (PHP warnings/deprecation notices)
        if (ob_get_level()) ob_end_clean();
        ob_start();

        header('Content-Type: application/json; charset=utf-8');

        $request = json_decode(file_get_contents('php://input'), true);
        $leadId = trim($request['lead_id'] ?? '');
        $type = trim($request['type'] ?? '');

        if (empty($leadId) || empty($type)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            return;
        }

        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        try {
            $result = $this->manager->runIntelligence($leadId, $tenantId, $type, $userId);

            // Fetch balance pós-consumo
            $newBalance = TokenQuota::getBalance($tenantId);
            $result['tokenBalance'] = $newBalance;

            ob_end_clean();
            echo json_encode($result);

        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('[DeepIntelligence] Erro: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
