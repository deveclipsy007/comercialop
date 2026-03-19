<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\AgendaEvent;
use App\Models\Lead;
use App\Services\LeadAnalysisService;

class FollowupController
{
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('id');
        $filter   = $_GET['filter'] ?? 'pending'; // pending | completed | all
        $leadFilter = $_GET['lead_id'] ?? '';

        $today = date('Y-m-d');

        // Base query
        $where = 'f.tenant_id = ?';
        $params = [$tenantId];

        if ($filter === 'completed') {
            $where .= ' AND f.completed = 1';
        } elseif ($filter === 'pending') {
            $where .= ' AND f.completed = 0';
        }

        if ($leadFilter) {
            $where .= ' AND f.lead_id = ?';
            $params[] = $leadFilter;
        }

        $sql = "
            SELECT f.*, l.name as lead_name, l.segment as lead_segment,
                   l.human_context as lead_context, l.pipeline_status,
                   l.phone as lead_phone, l.email as lead_email
            FROM followups f
            JOIN leads l ON f.lead_id = l.id
            WHERE {$where}
            ORDER BY f.completed ASC, f.scheduled_at ASC
        ";

        $followupsRaw = Database::select($sql, $params);

        $overdue = [];
        $todayList = [];
        $upcoming = [];
        $completed = [];

        foreach ($followupsRaw as $f) {
            if ($f['completed']) {
                $completed[] = $f;
                continue;
            }
            $dateOnly = substr($f['scheduled_at'], 0, 10);
            if ($dateOnly < $today) {
                $overdue[] = $f;
            } elseif ($dateOnly === $today) {
                $todayList[] = $f;
            } else {
                $upcoming[] = $f;
            }
        }

        // Leads para o seletor de criação
        $leads = Database::select(
            "SELECT id, name, segment, phone, pipeline_status FROM leads WHERE tenant_id = ? ORDER BY name ASC",
            [$tenantId]
        );

        // Leads com follow-ups ativos (para filtro lateral)
        $leadsWithFollowups = Database::select(
            "SELECT DISTINCT l.id, l.name, l.segment, COUNT(f.id) as followup_count
             FROM leads l
             JOIN followups f ON f.lead_id = l.id AND f.completed = 0
             WHERE l.tenant_id = ?
             GROUP BY l.id
             ORDER BY followup_count DESC",
            [$tenantId]
        );

        // Stats
        $stats = [
            'overdue' => count($overdue),
            'today' => count($todayList),
            'upcoming' => count($upcoming),
            'completed_30d' => (int)(Database::selectFirst(
                "SELECT COUNT(*) as c FROM followups WHERE tenant_id = ? AND completed = 1 AND completed_at >= datetime('now', '-30 days')",
                [$tenantId]
            )['c'] ?? 0),
        ];

        View::render('followup/index', [
            'active' => 'followup',
            'overdue' => $overdue,
            'today' => $todayList,
            'upcoming' => $upcoming,
            'completed' => $completed,
            'leads' => $leads,
            'leadsWithFollowups' => $leadsWithFollowups,
            'stats' => $stats,
            'currentFilter' => $filter,
            'currentLeadFilter' => $leadFilter,
        ]);
    }

    public function store(): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token de segurança inválido.');
            header('Location: /follow-up');
            exit;
        }

        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('id');

        $leadId = trim($_POST['lead_id'] ?? '');
        $mode = $_POST['mode'] ?? 'single'; // single | cadence

        if (!$leadId) {
            Session::flash('error', 'Selecione um lead para criar o follow-up.');
            header('Location: /follow-up');
            exit;
        }

        // Verificar que o lead existe e pertence ao tenant
        $lead = Database::selectFirst(
            'SELECT id, name FROM leads WHERE id = ? AND tenant_id = ?',
            [$leadId, $tenantId]
        );
        if (!$lead) {
            Session::flash('error', 'Lead não encontrado.');
            header('Location: /follow-up');
            exit;
        }

        $created = 0;

        if ($mode === 'cadence') {
            // Cadência: múltiplas etapas D+N
            $steps = json_decode($_POST['steps'] ?? '[]', true);
            if (empty($steps) || !is_array($steps)) {
                Session::flash('error', 'Nenhuma etapa definida na cadência.');
                header('Location: /follow-up');
                exit;
            }

            $baseDate = new \DateTime();
            foreach ($steps as $step) {
                $days = max(0, (int)($step['days'] ?? 1));
                $title = trim($step['title'] ?? 'Follow-up D+' . $days);
                $desc = trim($step['description'] ?? '');

                $scheduledDate = (clone $baseDate)->modify("+{$days} days");
                $scheduledAt = $scheduledDate->format('Y-m-d') . ' 09:00:00';

                $id = bin2hex(random_bytes(8));
                Database::execute(
                    'INSERT INTO followups (id, tenant_id, lead_id, user_id, title, description, scheduled_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $tenantId, $leadId, $userId, $title, $desc, $scheduledAt]
                );

                AgendaEvent::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'title' => '[Follow-up] ' . $title . ' — ' . $lead['name'],
                    'description' => $desc,
                    'event_type' => 'reminder',
                    'start_time' => $scheduledAt,
                ]);

                $created++;
            }
        } else {
            // Follow-up único
            $title = trim($_POST['title'] ?? 'Follow-up');
            $desc = trim($_POST['description'] ?? '');
            $date = $_POST['scheduled_at'] ?? '';

            if (!$date) {
                Session::flash('error', 'Data do follow-up é obrigatória.');
                header('Location: /follow-up');
                exit;
            }

            $id = bin2hex(random_bytes(8));
            Database::execute(
                'INSERT INTO followups (id, tenant_id, lead_id, user_id, title, description, scheduled_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $tenantId, $leadId, $userId, $title, $desc, $date . ' 09:00:00']
            );

            AgendaEvent::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'title' => '[Follow-up] ' . $title . ' — ' . $lead['name'],
                'description' => $desc,
                'event_type' => 'reminder',
                'start_time' => $date . ' 09:00:00',
            ]);

            $created = 1;
        }

        $msg = $created === 1 ? 'Follow-up criado com sucesso!' : "{$created} follow-ups criados (cadência)!";
        Session::flash('success', $msg);
        header('Location: /follow-up');
        exit;
    }

    public function complete(string $id): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            header('Location: /follow-up');
            exit;
        }

        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('id');

        $followup = Database::selectFirst(
            'SELECT * FROM followups WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($followup) {
            $actId = bin2hex(random_bytes(8));
            Database::execute(
                'INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, content)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$actId, $tenantId, $followup['lead_id'], $userId, 'note',
                 'Follow-up Concluído', 'Follow-up finalizado: ' . $followup['title']]
            );
        }

        Database::execute(
            'UPDATE followups SET completed = 1, completed_at = datetime("now") WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        Session::flash('success', 'Follow-up concluído!');
        header('Location: /follow-up');
        exit;
    }

    public function delete(string $id): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            header('Location: /follow-up');
            exit;
        }

        $tenantId = Session::get('tenant_id');

        Database::execute(
            'DELETE FROM followups WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        Session::flash('success', 'Follow-up removido.');
        header('Location: /follow-up');
        exit;
    }

    public function formatMessage(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']);
            return;
        }

        $followupId = $body['followup_id'] ?? '';

        $sql = "
            SELECT f.*, l.name as lead_name, l.segment as lead_segment,
                   l.human_context as lead_context, l.pipeline_status,
                   l.phone as lead_phone
            FROM followups f
            JOIN leads l ON f.lead_id = l.id
            WHERE f.id = ? AND f.tenant_id = ?
        ";
        $followup = Database::selectFirst($sql, [$followupId, $tenantId]);

        if (!$followup) {
            echo json_encode(['error' => 'Follow-up não encontrado']);
            return;
        }

        try {
            $service = new LeadAnalysisService();
            $message = $service->generateFollowupMessage($followup, $tenantId);
        } catch (\Throwable $e) {
            error_log('[Followup] AI message error: ' . $e->getMessage());
            $message = "Olá! Tudo bem? Gostaria de dar continuidade à nossa conversa sobre os serviços que discutimos. Quando podemos avançar?";
        }

        // Formatar telefone para link WhatsApp
        $phone = $followup['lead_phone'] ?? '';
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if ($cleanPhone && !str_starts_with($cleanPhone, '55') && strlen($cleanPhone) <= 11) {
            $cleanPhone = '55' . $cleanPhone;
        }
        $whatsappUrl = $cleanPhone ? 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($message) : '';

        echo json_encode([
            'message' => $message,
            'phone' => $phone,
            'whatsapp_url' => $whatsappUrl,
            'lead_name' => $followup['lead_name'],
        ]);
    }
}
