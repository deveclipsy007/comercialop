<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\AgendaEvent;

class AgendaController
{
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        // Busca Follow-ups pendentes e concluídos (específicos de Leads)
        $followups = Database::select(
            "SELECT f.*, l.name as lead_name, l.segment as lead_segment, l.priority_score
             FROM followups f
             JOIN leads l ON l.id = f.lead_id
             WHERE f.tenant_id = ? AND f.completed = 0
             ORDER BY f.scheduled_at ASC",
            [$tenantId]
        );

        $completedFollowups = Database::select(
            "SELECT f.*, l.name as lead_name, l.segment as lead_segment
             FROM followups f
             JOIN leads l ON l.id = f.lead_id
             WHERE f.tenant_id = ? AND f.completed = 1
             ORDER BY f.completed_at DESC",
            [$tenantId]
        );

        // Busca Lembretes e Compromissos genéricos
        $generalEvents = AgendaEvent::allByTenant($tenantId);

        // Opcional: Combinar todos em um único array se quisermos exibir de forma mesclada na lista
        $allEvents = [];
        
        foreach ($followups as $fu) {
            $allEvents[] = [
                'id' => $fu['id'],
                'type' => 'followup',
                'title' => 'Follow-up: ' . $fu['title'],
                'description' => $fu['description'],
                'start_time' => $fu['scheduled_at'],
                'lead_name' => $fu['lead_name'],
                'lead_id' => $fu['lead_id'],
                'color' => 'bg-primary/20 text-primary border-primary/30',
                'icon' => 'notification_important'
            ];
        }

        foreach ($generalEvents as $ev) {
            $allEvents[] = [
                'id' => $ev['id'],
                'type' => $ev['event_type'], // reminder | appointment
                'title' => $ev['title'],
                'description' => $ev['description'],
                'start_time' => $ev['start_time'],
                'lead_name' => null,
                'lead_id' => null,
                'color' => $ev['event_type'] === 'appointment' ? 'bg-blue-500/20 text-blue-400 border-blue-500/30' : 'bg-operon-energy/20 text-operon-energy border-operon-energy/30',
                'icon' => $ev['event_type'] === 'appointment' ? 'event' : 'push_pin'
            ];
        }

        usort($allEvents, fn($a, $b) => strtotime($a['start_time']) <=> strtotime($b['start_time']));

        View::render('agenda/index', [
            'active'    => 'agenda',
            'allEvents' => $allEvents,
            'completed' => $completedFollowups,
            'eventsJson' => json_encode($allEvents) // Passar JSON para o Javascript do Calendário
        ]);
    }

    public function storeEvent(): void
    {
        Session::requireAuth();
        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Sessão expirada. Tente novamente.');
            header('Location: /agenda');
            exit;
        }

        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('id');
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $date     = trim($_POST['start_date'] ?? date('Y-m-d'));
        $time     = trim($_POST['start_time'] ?? '09:00');
        $type     = trim($_POST['event_type'] ?? 'reminder');

        if (!$title) {
            Session::flash('error', 'O título é obrigatório.');
            header('Location: /agenda');
            exit;
        }

        $startDateTime = $date . ' ' . $time . ':00';

        AgendaEvent::create([
            'tenant_id' => $tenantId,
            'user_id' => (string) $userId,
            'title' => $title,
            'description' => $desc,
            'event_type' => $type,
            'start_time' => $startDateTime
        ]);

        Session::flash('success', 'Evento adicionado à agenda.');
        header('Location: /agenda');
        exit;
    }

    public function deleteEvent(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        
        if (AgendaEvent::delete($id, $tenantId)) {
            Session::flash('success', 'Evento removido com sucesso.');
        } else {
            Session::flash('error', 'Não foi possível remover o evento.');
        }

        header('Location: /agenda');
        exit;
    }
}
