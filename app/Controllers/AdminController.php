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

    // ─── Tenant Individual Management ───────────────────────────────

    public function tenantDetail(string $id): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
            return;
        }

        $tenant = Database::selectFirst('SELECT * FROM tenants WHERE id = ?', [$id]);
        if (!$tenant) {
            Session::flash('error', 'Empresa não encontrada.');
            View::redirect('/admin/users');
            return;
        }

        // Token quota
        $quota = TokenQuota::getOrCreate($id);

        // Users linked to this tenant
        $users = Database::select(
            'SELECT u.id, u.name, u.email, u.role, u.active, u.created_at, tu.role as pivot_role
             FROM users u
             JOIN tenant_user tu ON tu.user_id = u.id
             WHERE tu.tenant_id = ?
             ORDER BY u.name ASC',
            [$id]
        );

        // Counts
        $leadCount = (int)(Database::selectFirst('SELECT COUNT(*) as c FROM leads WHERE tenant_id = ?', [$id])['c'] ?? 0);
        $campaignCount = 0;
        try {
            $campaignCount = (int)(Database::selectFirst('SELECT COUNT(*) as c FROM email_campaigns WHERE tenant_id = ?', [$id])['c'] ?? 0);
        } catch (\Throwable $e) {}

        // Decode features
        $featuresEnabled = null;
        if (!empty($tenant['features_enabled'])) {
            $featuresEnabled = json_decode($tenant['features_enabled'], true);
        }

        // Agency settings
        $agencySettings = Database::selectFirst('SELECT * FROM agency_settings WHERE tenant_id = ?', [$id]);

        // Tier labels
        $tierLimits = [
            'starter' => 100,
            'pro' => 500,
            'elite' => 2000,
        ];

        View::render('admin/tenant_detail', [
            'active'       => 'admin_users',
            'tenant'       => $tenant,
            'quota'        => $quota,
            'users'        => $users,
            'leadCount'    => $leadCount,
            'campaignCount' => $campaignCount,
            'featuresEnabled' => $featuresEnabled,
            'agencySettings' => $agencySettings,
            'tierLimits'   => $tierLimits,
        ], 'layout.admin');
    }

    public function updateTenant(string $id): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403);
            return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/admin/tenant/' . $id);
            return;
        }

        $tenant = Database::selectFirst('SELECT id FROM tenants WHERE id = ?', [$id]);
        if (!$tenant) {
            Session::flash('error', 'Empresa não encontrada.');
            View::redirect('/admin/users');
            return;
        }

        $section = $_POST['section'] ?? 'general';

        if ($section === 'general') {
            $name = trim($_POST['name'] ?? '');
            $plan = $_POST['plan'] ?? 'starter';
            $active = !empty($_POST['active']) ? 1 : 0;
            $maxUsers = max(1, (int)($_POST['max_users'] ?? 10));
            $maxLeads = max(100, (int)($_POST['max_leads'] ?? 5000));
            $maxCampaigns = max(1, (int)($_POST['max_campaigns'] ?? 50));
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            if ($name) {
                Database::execute(
                    'UPDATE tenants SET name = ?, plan = ?, active = ?, max_users = ?, max_leads = ?, max_campaigns = ?, admin_notes = ?, updated_at = datetime("now") WHERE id = ?',
                    [$name, $plan, $active, $maxUsers, $maxLeads, $maxCampaigns, $adminNotes, $id]
                );
            }

            // Update tier in token_quotas to match plan
            TokenQuota::updateTier($id, $plan);

            Session::flash('success', 'Configurações gerais atualizadas.');
        }

        if ($section === 'credits') {
            $creditsExtra = max(0, (int)($_POST['credits_extra'] ?? 0));
            $tokensLimit = max(0, (int)($_POST['tokens_limit'] ?? 0));

            Database::execute(
                'UPDATE token_quotas SET credits_extra = ?, tokens_limit = ?, updated_at = datetime("now") WHERE tenant_id = ?',
                [$creditsExtra, $tokensLimit, $id]
            );

            Session::flash('success', 'Créditos atualizados.');
        }

        if ($section === 'features') {
            $features = $_POST['features'] ?? [];
            $featuresJson = is_array($features) ? json_encode(array_values($features)) : null;

            Database::execute(
                'UPDATE tenants SET features_enabled = ?, updated_at = datetime("now") WHERE id = ?',
                [$featuresJson, $id]
            );

            Session::flash('success', 'Funcionalidades atualizadas.');
        }

        if ($section === 'reset_credits') {
            Database::execute(
                'UPDATE token_quotas SET tokens_used = 0, updated_at = datetime("now") WHERE tenant_id = ?',
                [$id]
            );
            Session::flash('success', 'Créditos do dia resetados.');
        }

        View::redirect('/admin/tenant/' . $id);
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

        // Atualizar tier/limite de tokens se enviado
        $tier = trim($_POST['token_tier'] ?? '');
        if (in_array($tier, ['starter', 'pro', 'elite'], true)) {
            TokenQuota::updateTier($tenantId, $tier);
        }

        Session::flash('success', 'Distribuição de I.A e Roteamento de LLM atualizados.');
        View::redirect('/admin/ai-config');
    }

    // ─── Gestão de Chaves de API ─────────────────────────────────────

    public function aiKeys(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
            return;
        }

        $tenantId = Session::adminTenantId();
        $keys = \App\Models\AiApiKey::listAll($tenantId);

        // Verificar quais provedores têm chave no .env como fallback
        $envStatus = [
            'gemini' => !empty($_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY')),
            'openai' => !empty($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY')),
            'grok'   => !empty($_ENV['GROK_API_KEY'] ?? getenv('GROK_API_KEY')),
            'google_places' => !empty($_ENV['GOOGLE_MAPS_API_KEY'] ?? getenv('GOOGLE_MAPS_API_KEY')),
        ];

        View::render('admin/ai_keys', [
            'active'    => 'admin_keys',
            'keys'      => $keys,
            'envStatus' => $envStatus,
        ], 'layout.admin');
    }

    public function saveAiKey(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403);
            return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token CSRF inválido.');
            View::redirect('/admin/ai-keys');
            return;
        }

        $tenantId = Session::adminTenantId();
        $provider = trim($_POST['provider'] ?? '');
        $plainKey = trim($_POST['api_key'] ?? '');
        $label    = trim($_POST['label'] ?? '');

        if (!in_array($provider, ['gemini', 'openai', 'grok', 'google_places'], true)) {
            Session::flash('error', 'Provedor inválido.');
            View::redirect('/admin/ai-keys');
            return;
        }

        if (empty($plainKey)) {
            Session::flash('error', 'A chave de API não pode estar vazia.');
            View::redirect('/admin/ai-keys');
            return;
        }

        try {
            // Salvar como chave do tenant E como chave global (disponível para todos os tenants)
            \App\Models\AiApiKey::upsert($provider, $plainKey, $tenantId, $label);
            \App\Models\AiApiKey::upsert($provider, $plainKey, null, $label ?: 'Global');
            Session::flash('success', 'Chave de API para ' . strtoupper($provider) . ' salva com sucesso.');
        } catch (\Throwable $e) {
            error_log('[AdminController] Erro ao salvar chave: ' . $e->getMessage());
            Session::flash('error', 'Erro ao salvar chave: ' . $e->getMessage());
        }

        View::redirect('/admin/ai-keys');
    }

    public function deleteAiKey(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403);
            return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token CSRF inválido.');
            View::redirect('/admin/ai-keys');
            return;
        }

        $id = trim($_POST['key_id'] ?? '');
        if (empty($id)) {
            Session::flash('error', 'ID da chave não informado.');
            View::redirect('/admin/ai-keys');
            return;
        }

        try {
            \App\Models\AiApiKey::delete($id);
            Session::flash('success', 'Chave de API removida.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Erro ao remover chave.');
        }

        View::redirect('/admin/ai-keys');
    }

    public function testAiKey(): void
    {
        // Limpar qualquer output anterior (warnings, notices, etc.)
        if (ob_get_level()) ob_end_clean();
        ob_start();

        header('Content-Type: application/json; charset=utf-8');

        if (!Session::get('admin_auth')) {
            ob_end_clean();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Não autorizado']);
            return;
        }

        $provider = trim($_POST['provider'] ?? '');
        $tenantId = Session::adminTenantId();

        if (!in_array($provider, ['gemini', 'openai', 'grok', 'google_places'], true)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Provedor inválido.']);
            return;
        }

        try {
            // Buscar a chave do provider ESPECÍFICO solicitado
            $key = \App\Models\AiApiKey::getDecryptedKey($provider, $tenantId);

            if (empty($key)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => "Nenhuma chave encontrada para {$provider}. Salve a chave antes de testar."]);
                return;
            }

            // Criar o provider ESPECÍFICO com a chave encontrada
            if ($provider === 'google_places') {
                $service = new \App\Services\Hunter\GooglePlacesService($key, $tenantId);
                $result = $service->testConnection();

                ob_end_clean();
                echo json_encode($result);
                return;
            }

            if ($provider === 'gemini') {
                $model = config('services.gemini.model', 'gemini-2.0-flash');
                $ai = new \App\Services\AI\GeminiProvider($key, $model);
            } else {
                $model = $provider === 'grok'
                    ? config('services.grok.model', 'grok-2')
                    : config('services.openai.model', 'gpt-4o');
                $ai = new \App\Services\AI\OpenAIProvider($provider, $key, $model);
            }

            $result = $ai->generate('Responda apenas: OK', 'Diga OK.');

            // Descartar qualquer output do provider (warnings etc.)
            ob_end_clean();

            if (!empty(trim($result))) {
                echo json_encode([
                    'success'  => true,
                    'message'  => 'Conexão OK! Provider: ' . $ai->getProviderName() . ' | Modelo: ' . $ai->getModel() . ' | Resposta: ' . substr(trim($result), 0, 50),
                    'provider' => $ai->getProviderName(),
                    'model'    => $ai->getModel(),
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => "O provedor {$provider} retornou resposta vazia. Verifique se a chave tem permissões e se o modelo está disponível."]);
            }
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log("[testAiKey] Erro: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro ao testar ' . $provider . ': ' . $e->getMessage()]);
        }
    }

    // ─── Configuração de Provedores por Operação ─────────────────────

    public function providerConfigs(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
            return;
        }

        $tenantId = Session::adminTenantId();
        $configs = \App\Models\AiProviderConfig::listAll($tenantId);
        $operonConfig = require __DIR__ . '/../../config/operon.php';
        $operations = array_keys($operonConfig['token_weights'] ?? []);

        View::render('admin/provider_configs', [
            'active'     => 'admin_providers',
            'configs'    => $configs,
            'operations' => $operations,
        ], 'layout.admin');
    }

    public function saveProviderConfig(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403);
            return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token CSRF inválido.');
            View::redirect('/admin/providers');
            return;
        }

        $tenantId  = Session::adminTenantId();
        $operation = trim($_POST['operation'] ?? '');
        $provider  = trim($_POST['provider'] ?? '');
        $model     = trim($_POST['model'] ?? '');
        $priority  = (int) ($_POST['priority'] ?? 0);
        $isActive  = !empty($_POST['is_active']) ? 1 : 0;

        if (empty($operation) || empty($provider) || empty($model)) {
            Session::flash('error', 'Operação, provedor e modelo são obrigatórios.');
            View::redirect('/admin/providers');
            return;
        }

        try {
            \App\Models\AiProviderConfig::upsert([
                'tenant_id' => $tenantId,
                'operation' => $operation,
                'provider'  => $provider,
                'model'     => $model,
                'priority'  => $priority,
                'is_active' => $isActive,
            ]);
            Session::flash('success', 'Configuração do provedor para "' . $operation . '" salva.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Erro ao salvar configuração: ' . $e->getMessage());
        }

        View::redirect('/admin/providers');
    }

    public function deleteProviderConfig(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403);
            return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token CSRF inválido.');
            View::redirect('/admin/providers');
            return;
        }

        $id = trim($_POST['config_id'] ?? '');
        if (!empty($id)) {
            Database::execute('DELETE FROM ai_provider_configs WHERE id = ?', [$id]);
            Session::flash('success', 'Configuração removida (revertido ao default).');
        }

        View::redirect('/admin/providers');
    }

    // ─── Dashboard de Consumo ────────────────────────────────────────

    public function consumption(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
            return;
        }

        $tenantId = Session::adminTenantId();
        $period = $_GET['period'] ?? '30';
        $daysAgo = max(1, min(365, (int) $period));
        $since = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));

        // Resumo geral
        $summary = Database::selectFirst("
            SELECT COUNT(*) as total_ops,
                   COALESCE(SUM(tokens_used), 0) as total_credits,
                   COALESCE(SUM(real_tokens_input + real_tokens_output), 0) as total_real_tokens,
                   COALESCE(SUM(estimated_cost_usd), 0) as total_cost_usd
            FROM token_logs WHERE tenant_id = ? AND created_at >= ?
        ", [$tenantId, $since]);

        // Per-user
        $perUser = Database::select("
            SELECT tl.user_id, u.name, COUNT(*) as ops,
                   SUM(tl.tokens_used) as credits,
                   SUM(tl.real_tokens_input + tl.real_tokens_output) as real_tokens,
                   SUM(tl.estimated_cost_usd) as cost_usd
            FROM token_logs tl
            LEFT JOIN users u ON tl.user_id = u.id
            WHERE tl.tenant_id = ? AND tl.created_at >= ?
            GROUP BY tl.user_id
            ORDER BY cost_usd DESC
        ", [$tenantId, $since]);

        // Per-operation
        $perOperation = Database::select("
            SELECT operation, provider, model, COUNT(*) as calls,
                   SUM(real_tokens_input) as input_tokens,
                   SUM(real_tokens_output) as output_tokens,
                   SUM(estimated_cost_usd) as cost,
                   AVG(estimated_cost_usd) as avg_cost
            FROM token_logs WHERE tenant_id = ? AND created_at >= ?
            GROUP BY operation, provider, model
            ORDER BY cost DESC
        ", [$tenantId, $since]);

        View::render('admin/consumption', [
            'active'       => 'admin_consumption',
            'summary'      => $summary,
            'perUser'      => $perUser,
            'perOperation' => $perOperation,
            'period'       => $daysAgo,
        ], 'layout.admin');
    }

    public function consumptionApi(): void
    {
        if (!Session::get('admin_auth')) {
            http_response_code(403);
            echo json_encode(['error' => 'Não autorizado']);
            return;
        }

        header('Content-Type: application/json');

        $tenantId = Session::adminTenantId();
        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));

        $trend = Database::select("
            SELECT DATE(created_at) as day,
                   SUM(estimated_cost_usd) as cost,
                   COUNT(*) as ops,
                   SUM(real_tokens_input + real_tokens_output) as tokens
            FROM token_logs
            WHERE tenant_id = ? AND created_at >= date('now', '-' || ? || ' days')
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ", [$tenantId, $days]);

        echo json_encode(['trend' => $trend]);
    }
}
