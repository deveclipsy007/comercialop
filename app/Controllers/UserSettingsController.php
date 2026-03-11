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

    public function costs(): void
    {
        $tenantId = Session::get('tenant_id');
        $tokenBalance = TokenQuota::getBalance($tenantId);
        $recentEntries = TokenQuota::recentEntries($tenantId, 10);
        
        View::render('settings/costs', [
            'active' => 'settings', 
            'pageTitle' => 'Controle de Custos', 
            'pageSubtitle' => 'Gerenciamento financeiro e limites de uso',
            'tokenBalance' => $tokenBalance,
            'recentEntries' => $recentEntries
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
