<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;
use App\Services\LeadAnalysisService;

class SpinController
{
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $leads = Lead::allByTenant($tenantId, ['limit' => 50, 'order' => 'priority_score DESC']);

        View::render('spin/index', [
            'active' => 'spin',
            'leads'  => $leads,
        ]);
    }

    public function generate(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }

        $leadId = $body['lead_id'] ?? '';
        $lead   = Lead::findByTenant($leadId, $tenantId);

        if (!$lead) {
            echo json_encode(['error' => 'Lead não encontrado']); return;
        }

        $service = new LeadAnalysisService();
        $spin    = $service->generateSpin($lead, $tenantId);
        $scripts = $service->generateScriptVariations($lead, $tenantId);

        echo json_encode(['spin' => $spin, 'scripts' => $scripts, 'lead' => ['name' => $lead['name'], 'segment' => $lead['segment']]]);
    }
}
