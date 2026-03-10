<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;
use App\Models\TokenQuota;

class DashboardController
{
    public function index(): void
    {
        Session::requireAuth();

        $tenantId = Session::get('tenant_id');

        $stats = Lead::pipelineStats($tenantId);
        $recentLeads = Lead::allByTenant($tenantId, ['limit' => 5, 'order' => 'created_at DESC']);
        $hotLeads = Lead::allByTenant($tenantId, ['limit' => 3, 'min_score' => 70, 'order' => 'priority_score DESC']);
        $tokenBalance = TokenQuota::getBalance($tenantId);

        // Metrics for the Nexus dashboard
        $total   = $stats['total'] ?? 0;
        $won     = $stats['closed_won'] ?? 0;
        $metrics = [
            'total_leads'     => $total,
            'qualified_leads' => $stats['qualified'] ?? 0,
            'proposals'       => $stats['proposal'] ?? 0,
            'won'             => $won,
            'conversion_rate' => $total > 0 ? round(($won / $total) * 100, 1) : 0,
            'avg_score'       => $stats['avg_score'] ?? 0,
        ];

        View::render('dashboard/nexus', [
            'active'       => 'dashboard',
            'stats'        => $stats,
            'metrics'      => $metrics,
            'recentLeads'  => $recentLeads,
            'hotLeads'     => $hotLeads,
            'tokenBalance' => $tokenBalance,
        ]);
    }
}
