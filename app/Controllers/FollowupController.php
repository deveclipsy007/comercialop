<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\AgendaEvent;
use App\Services\LeadAnalysisService;

class FollowupController
{
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('user_id');

        // Fetch followups for this tenant/user
        $today = date('Y-m-d');
        
        $sql = "
            SELECT f.*, l.name as lead_name, l.segment as lead_segment, l.human_context as lead_context, l.pipeline_status
            FROM followups f
            JOIN leads l ON f.lead_id = l.id
            WHERE f.tenant_id = ? AND f.completed = 0
            ORDER BY f.scheduled_at ASC
        ";
        
        $followupsRaw = Database::select($sql, [$tenantId]);
        
        $overdue = [];
        $todayList = [];
        $upcoming = [];
        
        foreach ($followupsRaw as $f) {
            $dateOnly = substr($f['scheduled_at'], 0, 10);
            if ($dateOnly < $today) {
                $overdue[] = $f;
            } elseif ($dateOnly === $today) {
                $todayList[] = $f;
            } else {
                $upcoming[] = $f;
            }
        }

        View::render('followup/index', [
            'active' => 'followup',
            'overdue' => $overdue,
            'today' => $todayList,
            'upcoming' => $upcoming
        ]);
    }
    
    public function store(): void
    {
        Session::requireAuth();
        
        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('user_id');
        
        $leadId = $_POST['lead_id'] ?? '';
        $title = $_POST['title'] ?? 'Follow-up';
        $desc = $_POST['description'] ?? '';
        $date = $_POST['scheduled_at'] ?? ''; // YYYY-MM-DD
        
        if (!$leadId || !$date) {
            Session::setFlash('error', 'Dados incompletos para o follow-up.');
            header('Location: /follow-up');
            return;
        }
        
        $id = bin2hex(random_bytes(8));
        Database::execute(
            'INSERT INTO followups (id, tenant_id, lead_id, user_id, title, description, scheduled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $tenantId, $leadId, $userId, $title, $desc, $date . ' 09:00:00']
        );
        
        // Sincronizar com a agenda
        AgendaEvent::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => '[Follow-up] ' . $title,
            'description' => $desc,
            'event_type' => 'reminder',
            'start_time' => $date . ' 09:00:00'
        ]);
        
        Session::setFlash('success', 'Follow-up agendado com sucesso!');
        header('Location: /follow-up');
    }
    
    public function complete(string $id): void
    {
         Session::requireAuth();
         $tenantId = Session::get('tenant_id');
         $userId   = Session::get('user_id');
         
         // Inserir log na lead_activities primeiro para obter os dados originais
         $followup = Database::selectFirst('SELECT * FROM followups WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
         if ($followup) {
             $actId = bin2hex(random_bytes(8));
             Database::execute(
                'INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, content)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$actId, $tenantId, $followup['lead_id'], $userId, 'note', 'Follow-up Concluído', 'Mensagem enviada e follow-up finalizado. Objetivo: ' . $followup['title']]
             );
         }

         // Mark as complete
         Database::execute(
            'UPDATE followups SET completed = 1, completed_at = datetime("now") WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
         );
         
         Session::setFlash('success', 'Follow-up concluído com sucesso!');
         header('Location: /follow-up');
    }
    
    public function formatMessage(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');
        
        $tenantId = Session::get('tenant_id');
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        
        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }
        
        $followupId = $body['followup_id'] ?? '';
        
        $sql = "
            SELECT f.*, l.name as lead_name, l.segment as lead_segment, l.human_context as lead_context, l.pipeline_status
            FROM followups f
            JOIN leads l ON f.lead_id = l.id
            WHERE f.id = ? AND f.tenant_id = ?
        ";
        $followup = Database::selectFirst($sql, [$followupId, $tenantId]);
        
        if (!$followup) {
            echo json_encode(['error' => 'Follow-up não encontrado']); return;
        }
        
        $service = new LeadAnalysisService();
        $message = $service->generateFollowupMessage($followup, $tenantId);
        
        echo json_encode(['message' => $message]);
    }
}
