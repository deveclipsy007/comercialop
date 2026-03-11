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

        $tenantId = Session::adminTenantId();
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

        $tenantId = Session::adminTenantId();

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

        $tenantId = Session::adminTenantId();
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
        $tenantId = Session::adminTenantId();

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

    public function userDetail(string $id): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
            return;
        }

        $tenantId = Session::adminTenantId();
        $user = Database::selectFirst('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);

        if (!$user) {
            Session::flash('error', 'Usuário não encontrado.');
            View::redirect('/admin/users');
            return;
        }

        $raw = $user['wl_features'] ?? null;
        $user['wl_features'] = (!empty($raw) && is_string($raw)) ? (json_decode($raw, true) ?? []) : [];

        // Loading Multi-Company Data
        $linkedTenants = \App\Models\User::getLinkedTenants($id);
        $allTenants = Database::select('SELECT id, name FROM tenants ORDER BY name ASC');

        View::render('admin/user_detail', [
            'active' => 'admin_users',
            'user'   => $user,
            'linkedTenants' => $linkedTenants,
            'allTenants' => $allTenants
        ], 'layout.admin');
    }

    public function updateUserWhiteLabel(string $id): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403); return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        $tenantId = Session::adminTenantId();
        $user = Database::selectFirst('SELECT id FROM users WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);

        if (!$user) {
            Session::flash('error', 'Usuário não encontrado.');
            View::redirect('/admin/users');
            return;
        }

        $wlColor = trim($_POST['wl_color'] ?? '#a3e635');
        // validacao simples de hex
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $wlColor)) {
            $wlColor = '#a3e635';
        }

        $wlFeatures = $_POST['wl_features'] ?? [];
        $allowSetup = !empty($_POST['wl_allow_setup']) ? 1 : 0;
        $wlLogo = trim($_POST['wl_logo'] ?? '');

        // Multi-Company Limits Update
        $maxTenants = max(1, (int)($_POST['max_tenants'] ?? 1));
        $canCreateTenants = !empty($_POST['can_create_tenants']) ? 1 : 0;

        // Atualizar Colunas de White Label e Multi-Company
        Database::execute(
            'UPDATE users SET wl_color = ?, wl_logo = ?, wl_features = ?, wl_allow_setup = ?, max_tenants = ?, can_create_tenants = ?, updated_at = datetime("now") WHERE id = ?',
            [
                $wlColor,
                $wlLogo !== '' ? $wlLogo : null,
                json_encode($wlFeatures),
                $allowSetup,
                $maxTenants,
                $canCreateTenants,
                $id
            ]
        );

        Session::flash('success', 'Configurações de acesso e White Label salvas com sucesso.');
        View::redirect('/admin/users/' . $id);
    }

    public function linkTenant(string $id): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403); return;
        }
        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        $tenantId = Session::adminTenantId();
        $user = Database::selectFirst('SELECT id, max_tenants FROM users WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if (!$user) {
            Session::flash('error', 'Usuário não encontrado.');
            View::redirect('/admin/users');
            return;
        }

        $newTenantId = $_POST['tenant_id'] ?? '';
        if (!$newTenantId) {
            Session::flash('error', 'Empresa não selecionada.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        // Verificar limite
        $currentCount = (int)Database::selectFirst('SELECT COUNT(*) as c FROM tenant_user WHERE user_id = ?', [$id])['c'];
        if ($currentCount >= (int)($user['max_tenants'] ?? 1)) {
            Session::flash('error', 'Usuário já atingiu o limite máximo de empresas.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        // Verificar se empresa existe
        $tenant = Database::selectFirst('SELECT id FROM tenants WHERE id = ?', [$newTenantId]);
        if (!$tenant) {
            Session::flash('error', 'Empresa não encontrada.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        Database::execute(
            'INSERT OR IGNORE INTO tenant_user (id, user_id, tenant_id, role) VALUES (?, ?, ?, ?)',
            [bin2hex(random_bytes(8)), $id, $newTenantId, 'agent']
        );

        Session::flash('success', 'Empresa vinculada com sucesso.');
        View::redirect('/admin/users/' . $id);
    }

    public function unlinkTenant(string $id): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403); return;
        }
        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        $tenantIdToRemove = $_POST['tenant_id'] ?? '';
        if (!$tenantIdToRemove) {
            Session::flash('error', 'Empresa não identificada.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        // Verificar se é a última empresa — não pode remover a última
        $count = (int)Database::selectFirst('SELECT COUNT(*) as c FROM tenant_user WHERE user_id = ?', [$id])['c'];
        if ($count <= 1) {
            Session::flash('error', 'O usuário precisa manter ao menos um vínculo de empresa.');
            View::redirect('/admin/users/' . $id);
            return;
        }

        Database::execute(
            'DELETE FROM tenant_user WHERE user_id = ? AND tenant_id = ?',
            [$id, $tenantIdToRemove]
        );

        Session::flash('success', 'Vínculo com a empresa removido.');
        View::redirect('/admin/users/' . $id);
    }

    public function logs(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
        }

        $tenantId = Session::adminTenantId();
        
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

        $tenantId = Session::adminTenantId();
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

        $tenantId = Session::adminTenantId();
        
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
