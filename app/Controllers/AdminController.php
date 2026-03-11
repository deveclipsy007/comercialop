<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\TokenQuota;

class AdminController
{
    public function index(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
        }

        $tenantId = Session::get('tenant_id');
        $settings = Database::selectFirst('SELECT * FROM agency_settings WHERE tenant_id = ?', [$tenantId]);

        // decodificar campos JSON, se existirem
        if ($settings) {
            $settings['differentials'] = json_decode($settings['differentials'] ?? '[]', true);
            $settings['services']      = json_decode($settings['services'] ?? '[]', true);
        }

        View::render('admin/index', [
            'active'   => 'admin_config',
            'settings' => $settings,
        ], 'layout.admin');
    }

    public function save(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403); return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/admin');
        }

        $tenantId = Session::get('tenant_id');

        $differentials = array_filter(array_map('trim', explode("\n", $_POST['differentials'] ?? '')));
        $servicesRaw   = $_POST['services'] ?? [];
        $services = [];
        foreach ($servicesRaw as $svc) {
            if (!empty($svc['name'])) {
                $services[] = ['name' => trim($svc['name']), 'price' => (int) ($svc['price'] ?? 0)];
            }
        }

        $data = [
            'agency_name'    => trim($_POST['agency_name'] ?? ''),
            'agency_city'    => trim($_POST['agency_city'] ?? ''),
            'agency_niche'   => trim($_POST['agency_niche'] ?? ''),
            'offer_summary'  => trim($_POST['offer_summary'] ?? ''),
            'icp_profile'    => trim($_POST['icp_profile'] ?? ''),
            'custom_context' => trim($_POST['custom_context'] ?? ''),
            'differentials'  => json_encode(array_values($differentials)),
            'services'       => json_encode($services),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $existing = Database::selectFirst("SELECT id FROM agency_settings WHERE tenant_id = ?", [$tenantId]);

        if ($existing) {
            $sets  = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            $vals  = array_values($data);
            $vals[] = $tenantId;
            Database::execute("UPDATE agency_settings SET {$sets} WHERE tenant_id = ?", $vals);
        } else {
            $data['id']        = 'settings_' . uniqid();
            $data['tenant_id'] = $tenantId;
            $cols = implode(', ', array_keys($data));
            $phs  = implode(', ', array_fill(0, count($data), '?'));
            Database::execute("INSERT INTO agency_settings ({$cols}) VALUES ({$phs})", array_values($data));
        }

        Session::flash('success', 'Configurações salvas! O contexto da agência foi atualizado para todas as análises de IA.');
        View::redirect('/admin');
    }

    public function users(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
        }

        $tenantId = Session::get('tenant_id');
        $users = Database::select('SELECT id, name, email, role, active, created_at FROM users WHERE tenant_id = ? ORDER BY name ASC', [$tenantId]);

        View::render('admin/users', [
            'active' => 'admin_users',
            'users'  => $users
        ], 'layout.admin');
    }

    public function toggleUser(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403); return;
        }

        $id = $_POST['user_id'] ?? '';
        $tenantId = Session::get('tenant_id');

        // Impede que o próprio admin se desative
        if ($id === Session::get('id')) {
            Session::flash('error', 'Você não pode desativar sua própria conta.');
            View::redirect('/admin/users');
        }

        $user = Database::selectFirst('SELECT id, active FROM users WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($user) {
            $newStatus = $user['active'] ? 0 : 1;
            Database::execute('UPDATE users SET active = ? WHERE id = ?', [$newStatus, $id]);
            Session::flash('success', $newStatus ? 'Usuário ativado.' : 'Usuário desativado.');
        }

        View::redirect('/admin/users');
    }

    public function logs(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
        }

        $tenantId = Session::get('tenant_id');
        
        // Pega os logs globais do sistema de tokens da agência com as infos dos leads afetados
        $logs = Database::select('
            SELECT tl.*, l.name as lead_name 
            FROM token_logs tl 
            LEFT JOIN leads l ON tl.lead_id = l.id 
            WHERE tl.tenant_id = ? 
            ORDER BY tl.created_at DESC 
            LIMIT 100
        ', [$tenantId]);

        // Também pega logs gerais do sistema, se tivermos a lead_activities adaptável pra log geral (no caso as atividades da base)
        $activities = Database::select('
            SELECT la.*, u.name as user_name, l.name as lead_name
            FROM lead_activities la
            LEFT JOIN users u ON la.user_id = u.id
            LEFT JOIN leads l ON la.lead_id = l.id
            WHERE la.tenant_id = ? 
            ORDER BY la.created_at DESC 
            LIMIT 50
        ', [$tenantId]);

        View::render('admin/logs', [
            'active'     => 'admin_logs',
            'tokenLogs'  => $logs,
            'activities' => $activities
        ], 'layout.admin');
        Session::flash('success', 'Distribuição de modelos (LLM Routing) e Configurações de Token atualizados.');
        View::redirect('/admin/ai-config');
    }

    public function aiConfigs(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
        }

        $tenantId = Session::get('tenant_id');
        $tokenBalance = TokenQuota::getBalance($tenantId);
        
        $settings = Database::selectFirst('SELECT settings FROM tenants WHERE id = ?', [$tenantId]);
        
        $dist = ['gemini' => 80, 'openai' => 20];
        if ($settings && !empty($settings['settings'])) {
            $json = json_decode($settings['settings'], true);
            if (isset($json['ai_distribution'])) {
                $dist = $json['ai_distribution'];
            }
        }

        $config = require __DIR__ . '/../../config/operon.php';

        View::render('admin/ai_config', [
            'active'         => 'admin_ai',
            'config'         => $config,
            'tokenBalance'   => $tokenBalance,
            'aiDistribution' => $dist
        ], 'layout.admin');
    }

    public function saveAiConfigs(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403); return;
        }

        $tenantId = Session::get('tenant_id');
        
        $gemini = (int)($_POST['dist_gemini'] ?? 80);
        $openai = (int)($_POST['dist_openai'] ?? 20);

        if ($gemini + $openai !== 100) {
            $openai = 100 - $gemini;
        }

        $tenantInfo = Database::selectFirst('SELECT settings FROM tenants WHERE id = ?', [$tenantId]);
        $currentSettings = json_decode($tenantInfo['settings'] ?? '{}', true) ?: [];
        
        $currentSettings['ai_distribution'] = ['gemini' => $gemini, 'openai' => $openai];

        Database::execute('UPDATE tenants SET settings = ? WHERE id = ?', [
            json_encode($currentSettings), $tenantId
        ]);

        Session::flash('success', 'Distribuição de I.A e Roteamento de LLM atualizados.');
        View::redirect('/admin/ai-config');
    }
}
