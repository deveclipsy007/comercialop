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
        header('Content-Type: application/json');

        $request = json_decode(file_get_contents('php://input'), true);
        $leadId = (int)($request['lead_id'] ?? 0);
        $type = $request['type'] ?? '';

        if (!$leadId || !$type) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            return;
        }

        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');

        try {
            // Em aplicação real, você deve validar também o CSRF Token aqui.
            // if(!isset($request['_csrf']) || $request['_csrf'] !== Session::get('csrf_token')){ throw new Exception('CSRF Invalid'); }

            $result = $this->manager->runIntelligence($leadId, $tenantId, $type, $userId);

            // Fetch balance pós-consumo
            $newBalance = TokenQuota::getBalance($tenantId);
            $result['tokenBalance'] = $newBalance;

            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
