<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;

class AtlasController
{
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        // Leads with address for map plotting
        $leads = Lead::allByTenant($tenantId, ['limit' => 200, 'order' => 'priority_score DESC']);

        // Filter for ones with address, build GeoJSON-like data
        $mapLeads = array_filter($leads, fn($l) => !empty($l['address']));
        $mapLeads = array_values($mapLeads);

        // Stats for the sidebar
        $stats = [
            'total'    => count($leads),
            'mapped'   => count($mapLeads),
            'high'     => count(array_filter($leads, fn($l) => ($l['priority_score'] ?? 0) >= 70)),
            'segments' => $this->countBySegment($leads),
        ];

        View::render('atlas/index', [
            'active'    => 'atlas',
            'leads'     => $leads,
            'mapLeads'  => $mapLeads,
            'mapLeadsJson' => json_encode($mapLeads),
            'stats'     => $stats,
        ]);
    }

    private function countBySegment(array $leads): array
    {
        $counts = [];
        foreach ($leads as $lead) {
            $seg = $lead['segment'] ?? 'Outros';
            $counts[$seg] = ($counts[$seg] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, 8, true);
    }
}
