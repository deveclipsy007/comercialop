<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;

class AgendaController
{
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $followups = Database::select(
            "SELECT f.*, l.name as lead_name, l.segment as lead_segment, l.priority_score
             FROM followups f
             JOIN leads l ON l.id = f.lead_id
             WHERE f.tenant_id = ? AND f.completed = 0
             ORDER BY f.scheduled_at ASC
             LIMIT 50",
            [$tenantId]
        );

        $completed = Database::select(
            "SELECT f.*, l.name as lead_name, l.segment as lead_segment
             FROM followups f
             JOIN leads l ON l.id = f.lead_id
             WHERE f.tenant_id = ? AND f.completed = 1
             ORDER BY f.completed_at DESC
             LIMIT 20",
            [$tenantId]
        );

        View::render('agenda/index', [
            'active'    => 'agenda',
            'followups' => $followups,
            'completed' => $completed,
        ]);
    }
}
