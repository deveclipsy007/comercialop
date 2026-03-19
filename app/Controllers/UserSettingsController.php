<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\TokenQuota;
use App\Models\User;

class UserSettingsController
{
    public function __construct()
    {
        Session::requireAuth();
    }

    public function switchCompany(): void
    {
        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token de segurança inválido. Tente novamente.');
            header('Location: /');
            exit;
        }

        $userId = Session::get('id');
        $newTenantId = $_POST['tenant_id'] ?? '';

        if (!$userId || !$newTenantId) {
            Session::flash('error', 'Dados incompletos para troca de empresa.');
            header('Location: /');
            exit;
        }

        // Verifica de verdade se o usuário tem vínculo com a empresa destino na pivot table
        if (!User::hasTenantAccess((string)$userId, $newTenantId)) {
            // Tentativa maliciosa ou empresa removida
            Session::flash('error', 'Acesso negado à empresa selecionada.');
            header('Location: /');
            exit;
        }

        // Busca qual o cargo do usuário nessa nova empresa
        $link = Database::selectFirst(
            'SELECT role FROM tenant_user WHERE user_id = ? AND tenant_id = ?',
            [$userId, $newTenantId]
        );
        $newRole = $link['role'] ?? 'agent';

        // Executa a Troca Real de Contexto no Session Engine
        Session::switchTenant($newTenantId, $newRole);
        
        Session::flash('success', 'Contexto alterado com sucesso.');
        header('Location: /');
        exit;
    }

    public function createCompany(): void
    {
        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token de segurança inválido.');
            header('Location: /');
            exit;
        }

        $userId = Session::get('id');
        $companyName = trim($_POST['company_name'] ?? '');

        if (!$userId || !$companyName) {
            Session::flash('error', 'Nome da empresa é obrigatório.');
            header('Location: /');
            exit;
        }

        // Fetch real-time limits
        $user = Database::selectFirst(
            'SELECT max_tenants, can_create_tenants FROM users WHERE id = ?',
            [$userId]
        );

        if (!$user || !($user['can_create_tenants'] ?? 0)) {
            Session::flash('error', 'Você não tem permissão para criar novas empresas.');
            header('Location: /');
            exit;
        }

        // Check current tenant count
        $currentCount = (int)Database::selectFirst(
            'SELECT COUNT(*) as c FROM tenant_user WHERE user_id = ?',
            [$userId]
        )['c'];

        if ($currentCount >= (int)($user['max_tenants'] ?? 1)) {
            Session::flash('error', 'Você atingiu seu limite máximo de empresas (' . $user['max_tenants'] . ').');
            header('Location: /');
            exit;
        }

        try {
            // Generate IDs and Slug
            $newTenantId = bin2hex(random_bytes(8));
            $slugBase = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $companyName));
            $slug = $slugBase . '-' . bin2hex(random_bytes(2));

            // 1. Create Tenant
            Database::execute(
                'INSERT INTO tenants (id, name, slug, plan, active) VALUES (?, ?, ?, ?, ?)',
                [$newTenantId, $companyName, $slug, 'starter', 1]
            );

            // 2. Create Pivot Link (Admin)
            Database::execute(
                'INSERT INTO tenant_user (id, user_id, tenant_id, role) VALUES (?, ?, ?, ?)',
                [bin2hex(random_bytes(8)), $userId, $newTenantId, 'admin']
            );

            // 3. Initialize Token Quotas
            Database::execute(
                'INSERT INTO token_quotas (id, tenant_id, tokens_used, tokens_limit, tier, reset_at) VALUES (?, ?, ?, ?, ?, datetime("now", "+30 days"))',
                [bin2hex(random_bytes(8)), $newTenantId, 0, 100, 'starter']
            );

            // 4. Initialize Agency Settings
            Database::execute(
                'INSERT INTO agency_settings (id, tenant_id, agency_name, updated_at) VALUES (?, ?, ?, datetime("now"))',
                [bin2hex(random_bytes(8)), $newTenantId, $companyName]
            );

            // Switch to the new tenant immediately
            Session::switchTenant($newTenantId, 'admin');

            Session::flash('success', "Empresa '$companyName' criada e ativada com sucesso!");
            header('Location: /');
            exit;

        } catch (\Exception $e) {
            Session::flash('error', 'Erro ao criar empresa: ' . $e->getMessage());
            header('Location: /');
            exit;
        }
    }

    public function costs(): void
    {
        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('id');
        $tokenBalance  = TokenQuota::getBalance($tenantId);
        $recentEntries = TokenQuota::recentEntries($tenantId, 20, $userId);

        // Custo USD total do usuário nos últimos 30 dias
        $costSummary = Database::selectFirst(
            "SELECT COALESCE(SUM(estimated_cost_usd), 0) as total_cost,
                    COALESCE(SUM(real_tokens_input + real_tokens_output), 0) as total_real_tokens
             FROM token_logs
             WHERE tenant_id = ? AND user_id = ? AND created_at >= datetime('now', '-30 days')",
            [$tenantId, $userId]
        );

        View::render('settings/costs', [
            'active' => 'settings',
            'pageTitle' => 'Controle de Custos',
            'pageSubtitle' => 'Gerenciamento financeiro e limites de uso',
            'tokenBalance'  => $tokenBalance,
            'recentEntries' => $recentEntries,
            'costSummary'   => $costSummary,
        ]);
    }

    public function profile(): void
    {
        $userId = Session::get('id');
        $user = User::findById((string) $userId);
        
        View::render('settings/profile', [
            'active' => 'settings', 
            'pageTitle' => 'Meu Perfil', 
            'pageSubtitle' => 'Gerencie suas informações pessoais',
            'user' => $user
        ]);
    }

    public function updateProfile(): void
    {
        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Sessão expirada. Tente novamente.');
            header('Location: /profile');
            exit;
        }

        $userId = Session::get('id');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$name || !$email) {
            Session::flash('error', 'Nome e email são obrigatórios.');
            header('Location: /profile');
            exit;
        }

        $existing = User::findByEmail($email);
        if ($existing && $existing['id'] !== $userId) {
            Session::flash('error', 'Este email já está sendo utilizado por outra conta.');
            header('Location: /profile');
            exit;
        }

        User::updateProfile((string) $userId, [
            'name' => $name,
            'email' => $email
        ]);

        Session::set('name', $name);
        Session::flash('success', 'Perfil atualizado com sucesso.');
        header('Location: /profile');
        exit;
    }

    public function updateUserWhiteLabel(): void
    {
        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            header('Location: /profile');
            exit;
        }

        $userId = Session::get('id');
        $user = User::findById((string) $userId);

        if (!$user || !($user['wl_allow_setup'] ?? 0)) {
            Session::flash('error', 'Sem permissão para alterar a aparência.');
            header('Location: /profile');
            exit;
        }

        $wlColor = trim($_POST['wl_color'] ?? '#a3e635');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $wlColor)) {
            $wlColor = '#a3e635';
        }

        $wlLogo = trim($_POST['wl_logo'] ?? '');

        Database::execute(
            'UPDATE users SET wl_color = ?, wl_logo = ?, updated_at = datetime("now") WHERE id = ?',
            [$wlColor, $wlLogo !== '' ? $wlLogo : null, $userId]
        );

        // Update active session so changes are immediate
        $_SESSION['auth_user']['wl_color'] = $wlColor;
        $_SESSION['auth_user']['wl_logo'] = $wlLogo !== '' ? $wlLogo : null;

        Session::flash('success', 'Aparência atualizada!');
        header('Location: /profile');
        exit;
    }

    public function logs(): void
    {
        $tenantId = Session::get('tenant_id');
        // Fetch real activities with user names
        $logs = Database::select(
            'SELECT a.*, u.name as user_name 
             FROM lead_activities a 
             LEFT JOIN users u ON a.user_id = u.id 
             WHERE a.tenant_id = ? 
             ORDER BY a.created_at DESC 
             LIMIT 50',
            [$tenantId]
        );
        $tokenLogs = Database::select(
            'SELECT * FROM token_logs WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20',
            [$tenantId]
        );

        View::render('settings/logs', [
            'active' => 'settings', 
            'pageTitle' => 'Logs do Sistema', 
            'pageSubtitle' => 'Auditoria e histórico de atividades',
            'logs' => $logs,
            'tokenLogs' => $tokenLogs
        ]);
    }

    public function settings(): void
    {
        $tenantId = Session::get('tenant_id');
        $tenant = Database::selectFirst('SELECT * FROM tenants WHERE id = ?', [$tenantId]);
        $agencySettings = json_decode($tenant['settings'] ?? '{}', true);
        
        View::render('settings/settings', [
            'active' => 'settings', 
            'pageTitle' => 'Configurações', 
            'pageSubtitle' => 'Preferências do sistema e personalização',
            'tenant' => $tenant,
            'agencySettings' => $agencySettings
        ]);
    }

    public function integrations(): void
    {
        $tenantId = Session::get('tenant_id');
        $tenant = Database::selectFirst('SELECT * FROM tenants WHERE id = ?', [$tenantId]);
        $agencySettings = json_decode($tenant['settings'] ?? '{}', true);
        $integrations = $agencySettings['integrations'] ?? [];

        View::render('settings/integrations', [
            'active' => 'settings', 
            'pageTitle' => 'Integrações', 
            'pageSubtitle' => 'Conecte o Operon com outras ferramentas',
            'integrations' => $integrations
        ]);
    }
}
