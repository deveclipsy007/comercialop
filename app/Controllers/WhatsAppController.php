<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationAnalysis;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppIntegrationLog;
use App\Models\WhatsAppLeadLink;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\ConnectionService;
use App\Services\WhatsApp\ConversationAnalysisService;
use App\Services\WhatsApp\SyncService;

/**
 * Controller do módulo WhatsApp — leitura, sync e análise de conversas.
 *
 * Rotas:
 *   GET  /whatsapp                              → index()
 *   POST /whatsapp/setup                        → setup()
 *   POST /whatsapp/connect                      → connect()     (QR Code)
 *   GET  /whatsapp/status                       → getStatus()
 *   POST /whatsapp/sync                         → sync()
 *   POST /whatsapp/disconnect                   → disconnect()
 *   GET  /whatsapp/conversations                → conversations() (JSON)
 *   GET  /whatsapp/notifications                → notifications() (JSON)
 *   GET  /whatsapp/conversation/:id             → conversation()
 *   POST /whatsapp/conversation/:id/link        → linkLead()
 *   POST /whatsapp/conversation/:id/unlink      → unlinkLead()
 *   POST /whatsapp/conversation/:id/analyze     → analyze()
 *   GET  /whatsapp/webhook                      → webhookHandler()
 *   POST /whatsapp/webhook                      → webhookHandler()
 */
class WhatsAppController
{
    private ConnectionService          $connection;
    private SyncService                $sync;
    private ConversationAnalysisService $analysis;
    private \App\Services\WhatsApp\WhatsAppIntelligenceService $intelligence;

    public function __construct()
    {
        $this->ensureTablesExist();
        $this->connection   = new ConnectionService();
        $this->sync         = new SyncService();
        $this->analysis     = new ConversationAnalysisService();
        $this->intelligence = new \App\Services\WhatsApp\WhatsAppIntelligenceService();
    }

    // ─── Auto-migration ──────────────────────────────────────────────────────

    private function ensureTablesExist(): void
    {
        try {
            $exists = Database::selectFirst(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='whatsapp_integrations'",
                []
            );

            $basePath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);

            if (!$exists) {
                $candidates = [
                    $basePath . '/database/migrations/008_whatsapp_full.sql',
                    $basePath . '/database/migrations/005_whatsapp_module.sql',
                ];

                $sqlPath = null;
                foreach ($candidates as $candidate) {
                    if (file_exists($candidate)) {
                        $sqlPath = $candidate;
                        break;
                    }
                }

                if (!$sqlPath) {
                    error_log('[WhatsAppController] Migration file not found. Tried: ' . implode(', ', $candidates));
                    return;
                }

                $this->executeSqlFile($sqlPath);
                error_log('[WhatsAppController] WhatsApp migration applied from: ' . basename($sqlPath));
            }

            // Migration 009: Intelligence Hub (1:N links + analysis columns)
            $this->ensureIntelligenceMigration($basePath);
            $this->ensureConversationStateMigration();

        } catch (\Throwable $e) {
            error_log('[WhatsAppController] ensureTablesExist() falhou: ' . $e->getMessage());
        }
    }

    private function ensureIntelligenceMigration(string $basePath): void
    {
        // Check if 1:N migration already applied by looking at the UNIQUE constraint
        $schema = Database::selectFirst(
            "SELECT sql FROM sqlite_master WHERE type='table' AND name='whatsapp_lead_links'", []
        );
        $needsRebuild = $schema && str_contains($schema['sql'] ?? '', 'UNIQUE(tenant_id, conversation_id)')
                      && !str_contains($schema['sql'] ?? '', 'UNIQUE(tenant_id, conversation_id, lead_id)');

        if ($needsRebuild) {
            $sqlPath = $basePath . '/database/migrations/009_whatsapp_intelligence.sql';
            if (file_exists($sqlPath)) {
                $this->executeSqlFile($sqlPath);
                error_log('[WhatsAppController] Intelligence migration (1:N links) applied.');
            }
        }

        // Add missing columns to whatsapp_conversation_analyses (safe: catches duplicate column errors)
        $columns = ['tenant_id TEXT DEFAULT ""', 'analysis_type TEXT DEFAULT "full"', 'interest_score INTEGER DEFAULT NULL'];
        foreach ($columns as $col) {
            try {
                Database::execute("ALTER TABLE whatsapp_conversation_analyses ADD COLUMN {$col}", []);
            } catch (\Throwable $e) {
                // Column already exists — ignore
            }
        }
    }

    private function ensureConversationStateMigration(): void
    {
        try {
            Database::execute('ALTER TABLE whatsapp_conversations ADD COLUMN last_read_ts INTEGER DEFAULT 0', []);
        } catch (\Throwable $e) {
            // Column already exists — ignore
        }

        try {
            Database::execute(
                'UPDATE whatsapp_conversations
                 SET last_read_ts = COALESCE(
                     (
                         SELECT MAX(m.timestamp)
                         FROM whatsapp_messages m
                         WHERE m.conversation_id = whatsapp_conversations.id
                           AND m.tenant_id = whatsapp_conversations.tenant_id
                     ),
                     CAST(strftime("%s", "now") AS INTEGER)
                 )
                 WHERE last_read_ts IS NULL
                    OR last_read_ts = 0
                    OR (
                        COALESCE(unread_count, 0) = 0
                        AND COALESCE((
                            SELECT MAX(m.timestamp)
                            FROM whatsapp_messages m
                            WHERE m.conversation_id = whatsapp_conversations.id
                              AND m.tenant_id = whatsapp_conversations.tenant_id
                        ), 0) > 0
                        AND ABS(
                            COALESCE(last_read_ts, 0) - COALESCE((
                                SELECT MAX(m.timestamp)
                                FROM whatsapp_messages m
                                WHERE m.conversation_id = whatsapp_conversations.id
                                  AND m.tenant_id = whatsapp_conversations.tenant_id
                            ), 0)
                        ) <= 10800
                    )',
                []
            );
        } catch (\Throwable $e) {
            error_log('[WhatsAppController] ensureConversationStateMigration() falhou: ' . $e->getMessage());
        }
    }

    private function executeSqlFile(string $path): void
    {
        $sql   = file_get_contents($path);
        $clean = preg_replace('/--[^\n]*/', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
            Database::execute($stmt, []);
        }
    }

    // ─── GET /whatsapp ───────────────────────────────────────────────────────

    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $integration = WhatsAppIntegration::findByTenant($tenantId);

        if (!$integration) {
            View::render('whatsapp/index', [
                'active'      => 'whatsapp',
                'integration' => null,
                'csrf'        => Session::csrf(),
            ]);
            return;
        }

        // Estatísticas de sincronia
        $totalConversations = WhatsAppConversation::countByTenant($tenantId);
        $recentLogs         = WhatsAppIntegrationLog::recentByTenant($tenantId, 5);

        View::render('whatsapp/index', [
            'active'             => 'whatsapp',
            'integration'        => $integration,
            'totalConversations' => $totalConversations,
            'recentLogs'         => $recentLogs,
            'csrf'               => Session::csrf(),
        ]);
    }

    // ─── POST /whatsapp/setup ────────────────────────────────────────────────

    public function setup(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $instanceName = trim($_POST['instance_name'] ?? '');
        // Sanitize: remove spaces and special characters for a safe URL slug
        $instanceName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $instanceName);

        if (!$instanceName) {
            $this->jsonError('Informe um nome para a instância.');
        }

        // Usar config do servidor (base_url e api_key vêm de config/services.php)
        $result = $this->connection->setupAndConnect($tenantId, $instanceName);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Falha ao configurar integração.');
        }

        $this->jsonSuccess([
            'message'     => 'Instância criada com sucesso.',
            'integration' => $result['integration'],
            'qr_code'     => $result['qr_code'] ?? null,
            'qr_status'   => $result['qr_status'] ?? 'unknown',
        ]);
    }

    // ─── POST /whatsapp/connect ──────────────────────────────────────────────

    public function connect(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $result = $this->connection->generateQrCode($tenantId);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Não foi possível gerar QR Code.');
        }

        $this->jsonSuccess([
            'qr_code' => $result['qr_code'],
            'status'  => $result['status'],
        ]);
    }

    // ─── GET /whatsapp/status ────────────────────────────────────────────────

    public function getStatus(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $result = $this->connection->getStatus($tenantId);

        $this->jsonSuccess([
            'status'             => $result['status'],
            'connection_status'  => $result['connection_status'] ?? 'disconnected',
            'instance_name'      => $result['instance_name']     ?? null,
            'last_sync_at'       => $result['last_sync_at']      ?? null,
            'total_conversations'=> WhatsAppConversation::countByTenant($tenantId),
            'has_integration'    => $result['has_integration']   ?? false,
        ]);
    }

    // ─── POST /whatsapp/sync ─────────────────────────────────────────────────

    public function sync(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        set_time_limit(120);

        $integration = WhatsAppIntegration::findByTenant($tenantId);
        $syncMeta = $this->runSmartSync($tenantId, $integration, [
            'requested'    => true,
            'force'        => true,
            'trigger'      => 'manual_sync',
            'min_interval' => 0,
        ]);

        if (($syncMeta['reason'] ?? '') === 'not_connected') {
            $this->jsonError('Instância não conectada.');
        }

        if (($syncMeta['reason'] ?? '') === 'sync_failed') {
            $this->jsonError($syncMeta['error'] ?? 'Falha na sincronização.');
        }

        $this->jsonSuccess([
            'message'       => ($syncMeta['reason'] ?? '') === 'locked'
                ? 'Já existe uma sincronização em andamento.'
                : 'Sincronização concluída.',
            'conversations' => $syncMeta['stats']['conversations_synced'] ?? 0,
            'messages'      => $syncMeta['stats']['messages_synced']      ?? 0,
            'contacts'      => $syncMeta['stats']['contacts_synced']      ?? 0,
            'auto_links'    => $syncMeta['stats']['auto_links']           ?? 0,
            'sync_meta'     => $syncMeta,
        ]);
    }

    // ─── POST /whatsapp/disconnect ───────────────────────────────────────────

    public function disconnect(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        try {
            $this->connection->disconnect($tenantId);
        } catch (\Exception $e) {
            // Se falhar na API, ainda assim tentamos limpar localmente se o usuário quiser resetar
            error_log("[WhatsApp] API Logout failed: " . $e->getMessage());
        }

        $integration = WhatsAppIntegration::findByTenant($tenantId);
        if ($integration) {
            WhatsAppIntegration::delete($integration['id']);
        }

        $this->jsonSuccess(['message' => 'WhatsApp desconectado e registros limpos.']);
    }

    // ─── GET /whatsapp/conversations ─────────────────────────────────────────

    public function conversations(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        $shouldRefresh = (int) ($_GET['refresh'] ?? 0) === 1;
        $syncMeta = $shouldRefresh
            ? $this->runSmartSync($tenantId, $integration, [
                'requested'    => true,
                'trigger'      => 'conversations',
                'min_interval' => 10,
            ])
            : $this->syncMetaSnapshot($integration, ['reason' => 'idle']);

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $search  = trim($_GET['search'] ?? '');
        $linked  = isset($_GET['linked']) ? (bool) $_GET['linked'] : null;
        $limit   = 30;
        $offset  = ($page - 1) * $limit;

        $conversations = WhatsAppConversation::allByTenant($tenantId, [
            'search' => $search,
            'linked' => $linked,
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        $total = WhatsAppConversation::countByTenant($tenantId, [
            'search' => $search,
            'linked' => $linked,
        ]);

        $this->jsonSuccess([
            'conversations' => $conversations,
            'total'         => $total,
            'page'          => $page,
            'pages'         => (int) ceil($total / $limit),
            'last_sync_at'  => $syncMeta['last_sync_at'] ?? ($integration['last_sync_at'] ?? null),
            'sync_meta'     => $syncMeta,
        ]);
    }

    // ─── GET /whatsapp/notifications ────────────────────────────────────────

    public function notifications(): void
    {
        Session::requireAuth();

        $tenantId = Session::get('tenant_id');
        $userId   = (string) Session::get('id');
        $user     = User::findById($userId);
        $prefs    = json_decode($user['preferences'] ?? '{}', true) ?: [];
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        $whatsappEnabled = (int) ($prefs['notify_whatsapp_new'] ?? 1) === 1;
        $followupEnabled = (int) ($prefs['notify_followup_due'] ?? 1) === 1;
        $agendaTodayEnabled = (int) ($prefs['notify_agenda_today'] ?? 1) === 1;
        $agendaOneHourEnabled = (int) ($prefs['notify_agenda_1h'] ?? 1) === 1;

        $channels = [
            'whatsapp' => [
                'enabled' => $whatsappEnabled,
                'has_integration' => (bool) $integration,
                'total' => 0,
                'items' => [],
                'last_sync_at' => $integration['last_sync_at'] ?? null,
            ],
            'agenda' => [
                'enabled' => $followupEnabled || $agendaTodayEnabled || $agendaOneHourEnabled,
                'total' => 0,
                'items' => [],
            ],
        ];

        $syncMeta = $this->syncMetaSnapshot($integration, [
            'reason' => $integration ? 'idle' : 'no_integration',
            'trigger' => 'notifications',
        ]);
        $whatsappItems = [];

        if ($whatsappEnabled && $integration) {
            $syncMeta = $this->runSmartSync($tenantId, $integration, [
                'requested'    => true,
                'trigger'      => 'notifications',
                'min_interval' => 15,
            ]);
            $integration = WhatsAppIntegration::findByTenant($tenantId) ?? $integration;
            WhatsAppConversation::recalculateUnreadByTenant($tenantId);

            $channels['whatsapp']['last_sync_at'] = $syncMeta['last_sync_at'] ?? ($integration['last_sync_at'] ?? null);
            $channels['whatsapp']['total'] = WhatsAppConversation::unreadCountByTenant($tenantId);
            $whatsappItems = array_map(
                fn(array $item): array => $this->mapWhatsAppNotificationItem($item),
                WhatsAppConversation::latestUnreadByTenant($tenantId, 8)
            );
            $channels['whatsapp']['items'] = array_map(
                fn(array $item): array => $this->stripNotificationSortMeta($item),
                $whatsappItems
            );
        }

        $agendaBundle = $this->buildAgendaNotificationBundle($tenantId, [
            'followups' => $followupEnabled,
            'today' => $agendaTodayEnabled,
            'one_hour' => $agendaOneHourEnabled,
        ]);
        $channels['agenda']['total'] = $agendaBundle['total'];
        $channels['agenda']['items'] = array_map(
            fn(array $item): array => $this->stripNotificationSortMeta($item),
            $agendaBundle['items']
        );

        $items = array_merge($whatsappItems, $agendaBundle['items']);
        usort($items, function (array $a, array $b): int {
            $left = [$a['_sort_group'] ?? 99, $a['_sort_time'] ?? PHP_INT_MAX, $a['title'] ?? ''];
            $right = [$b['_sort_group'] ?? 99, $b['_sort_time'] ?? PHP_INT_MAX, $b['title'] ?? ''];
            return $left <=> $right;
        });

        $this->jsonSuccess([
            'enabled'         => $whatsappEnabled || $channels['agenda']['enabled'],
            'has_integration' => (bool) $integration,
            'total'           => $channels['whatsapp']['total'] + $channels['agenda']['total'],
            'items'           => array_map(
                fn(array $item): array => $this->stripNotificationSortMeta($item),
                array_slice($items, 0, 10)
            ),
            'counts'          => [
                'whatsapp' => $channels['whatsapp']['total'],
                'agenda' => $channels['agenda']['total'],
            ],
            'channels'        => $channels,
            'last_sync_at'    => $channels['whatsapp']['last_sync_at'],
            'sync_meta'       => $syncMeta,
        ]);
    }

    // ─── GET /whatsapp/conversation/:id ─────────────────────────────────────

    public function conversation(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            http_response_code(404);
            View::render('errors/404', ['active' => 'whatsapp']);
            return;
        }

        WhatsAppConversation::markRead($id, $tenantId);
        $conversation['unread_count'] = 0;

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $limit    = 50;
        $offset   = ($page - 1) * $limit;
        $messages = WhatsAppMessage::findByConversation($id, $limit, $offset);
        $total    = WhatsAppMessage::countByConversation($id);

        // 1:N Lead Links
        $leadLinks  = WhatsAppLeadLink::findAllByConversation($tenantId, $id);
        $leads      = [];
        foreach ($leadLinks as $link) {
            $l = Lead::findByTenant($link['lead_id'], $tenantId);
            if ($l) $leads[] = $l;
        }
        // Backward compat
        $leadLink = !empty($leadLinks) ? $leadLinks[0] : null;
        $lead     = !empty($leads)     ? $leads[0]     : null;

        // Análises por tipo
        $summaryAnalysis   = WhatsAppConversationAnalysis::latestByType($id, 'summary');
        $strategicAnalysis = WhatsAppConversationAnalysis::latestByType($id, 'strategic');
        $scoreAnalysis     = WhatsAppConversationAnalysis::latestByType($id, 'interest_score');
        $latestAnalysis    = WhatsAppConversationAnalysis::latestByConversation($id);

        // Sugestão de link por telefone (apenas se não tem nenhum lead vinculado)
        $suggestedLeads = [];
        if (empty($leadLinks) && !empty($conversation['phone'])) {
            $phone = preg_replace('/\D/', '', $conversation['phone']);
            if (strlen($phone) >= 8) {
                $suffix = substr($phone, -9);
                $suggestedLeads = Lead::searchByPhone($suffix, $tenantId, 5);
            }
        }

        View::render('whatsapp/conversation', [
            'active'             => 'whatsapp',
            'conversation'       => $conversation,
            'messages'           => $messages,
            'total'              => $total,
            'page'               => $page,
            'pages'              => (int) ceil($total / $limit),
            'leadLinks'          => $leadLinks,
            'leads'              => $leads,
            'leadLink'           => $leadLink,
            'lead'               => $lead,
            'summaryAnalysis'    => $summaryAnalysis,
            'strategicAnalysis'  => $strategicAnalysis,
            'scoreAnalysis'      => $scoreAnalysis,
            'latestAnalysis'     => $latestAnalysis,
            'suggestedLeads'     => $suggestedLeads,
            'csrf'               => Session::csrf(),
        ]);
    }

    // ─── GET /whatsapp/conversation/:id/messages ─────────────────────────────

    public function conversationMessages(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        $shouldRefresh = (int) ($_GET['refresh'] ?? 0) === 1;
        $syncMeta = $shouldRefresh
            ? $this->runSmartSync($tenantId, $integration, [
                'requested'    => true,
                'trigger'      => 'messages',
                'min_interval' => 8,
            ])
            : $this->syncMetaSnapshot($integration, ['reason' => 'idle']);

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        WhatsAppConversation::markRead($id, $tenantId);

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $messages = WhatsAppMessage::findByConversation($id, $limit, $offset);
        $total    = WhatsAppMessage::countByConversation($id);

        $this->jsonSuccess([
            'messages' => $messages,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int) ceil($total / $limit),
            'last_sync_at' => $syncMeta['last_sync_at'] ?? ($integration['last_sync_at'] ?? null),
            'sync_meta'    => $syncMeta,
        ]);
    }

    // ─── POST /whatsapp/conversation/:id/link ────────────────────────────────

    public function linkLead(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $leadId = trim($_POST['lead_id'] ?? '');
        if (!$leadId) {
            $this->jsonError('lead_id obrigatório.');
        }

        // Valida que a conversa pertence ao tenant
        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        // Valida que o lead pertence ao tenant
        $lead = Lead::findByTenant($leadId, $tenantId);
        if (!$lead) {
            $this->jsonError('Lead não encontrado.', 404);
        }

        WhatsAppLeadLink::link($tenantId, $id, $leadId, 'manual');

        $this->jsonSuccess([
            'message'  => 'Lead vinculado com sucesso.',
            'lead_id'  => $leadId,
            'lead_name'=> $lead['name'] ?? '',
        ]);
    }

    // ─── POST /whatsapp/conversation/:id/unlink ──────────────────────────────

    public function unlinkLead(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        $leadId = trim($_POST['lead_id'] ?? '');
        if ($leadId) {
            // Remove link específico (1:N)
            WhatsAppLeadLink::unlinkOne($tenantId, $id, $leadId);
        } else {
            // Remove todos os links (backward compat)
            WhatsAppLeadLink::unlink($tenantId, $id);
        }

        $this->jsonSuccess(['message' => 'Vínculo removido.']);
    }

    // ─── POST /whatsapp/conversation/:id/analyze ─────────────────────────────

    public function analyze(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $result = $this->analysis->analyze($id, $tenantId);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Falha na análise.');
        }

        $this->jsonSuccess([
            'analysis' => $result['analysis'],
            'version'  => $result['version'],
            'cached'   => $result['cached'] ?? false,
            'message'  => ($result['cached'] ?? false)
                ? 'Análise já está atualizada (sem novas mensagens).'
                : 'Análise de IA concluída.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INTELLIGENCE HUB — 4 novos endpoints de IA
    // ═══════════════════════════════════════════════════════════════════════════

    // ─── POST /whatsapp/conversation/:id/summary ──────────────────────────────

    public function summary(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        try {
            $result = $this->intelligence->generateSummary($id, $tenantId);
            if (!$result['success']) {
                $this->jsonError($result['error'] ?? 'Falha ao gerar resumo.');
            }
            $this->jsonSuccess($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    // ─── POST /whatsapp/conversation/:id/next-message ─────────────────────────

    public function nextMessage(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        try {
            $result = $this->intelligence->generateNextMessage($id, $tenantId);
            if (!$result['success']) {
                $this->jsonError($result['error'] ?? 'Falha ao gerar mensagem.');
            }
            $this->jsonSuccess($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    // ─── POST /whatsapp/conversation/:id/strategic ────────────────────────────

    public function strategicAnalysis(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        try {
            $result = $this->intelligence->runStrategicAnalysis($id, $tenantId);
            if (!$result['success']) {
                $this->jsonError($result['error'] ?? 'Falha na análise estratégica.');
            }
            $this->jsonSuccess($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    // ─── POST /whatsapp/conversation/:id/interest-score ───────────────────────

    public function interestScore(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        try {
            $result = $this->intelligence->calculateInterestScore($id, $tenantId);
            if (!$result['success']) {
                $this->jsonError($result['error'] ?? 'Falha ao calcular score.');
            }
            $this->jsonSuccess($result);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    // ─── POST /whatsapp/conversation/:id/prepare-send ─────────────────────────

    public function prepareSend(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            $this->jsonError('Mensagem vazia. Gere ou digite uma mensagem primeiro.');
        }

        // Extrair número do remote_jid ou phone
        $rawPhone = $conversation['phone'] ?? '';
        if (empty($rawPhone)) {
            $jid = $conversation['remote_jid'] ?? '';
            // JID format: 5511999887766@s.whatsapp.net
            $rawPhone = preg_replace('/@.*$/', '', $jid);
        }

        // Formatar número para padrão internacional
        $formattedPhone = $this->formatPhoneInternational($rawPhone);

        // Validações
        $validationErrors = [];

        if (empty($formattedPhone)) {
            $validationErrors[] = 'Número de telefone não encontrado para este contato.';
        } elseif (strlen(preg_replace('/\D/', '', $formattedPhone)) < 10) {
            $validationErrors[] = 'Número de telefone parece incompleto: ' . $formattedPhone;
        }

        // Verificar status da conexão WhatsApp
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        $channelConnected = false;
        $connectionStatus = 'Desconectado';

        if ($integration && ($integration['status'] ?? '') === 'connected' && ($integration['active'] ?? 0)) {
            $channelConnected = true;
            $connectionStatus = 'Conectado';
        } elseif ($integration) {
            $connectionStatus = ucfirst($integration['status'] ?? 'desconectado');
        }

        // Montar URL wa.me
        $canSend = empty($validationErrors) && !empty($formattedPhone);
        $cleanNumber = preg_replace('/\D/', '', $formattedPhone);
        $whatsappUrl = $canSend
            ? 'https://wa.me/' . $cleanNumber . '?text=' . rawurlencode($message)
            : '';

        // Mensagem de validação
        $validationMessage = '';
        if (!empty($validationErrors)) {
            $validationMessage = implode(' ', $validationErrors);
        } elseif (!$channelConnected) {
            $validationMessage = 'Canal WhatsApp não está conectado, mas você ainda pode enviar via wa.me.';
        }

        // Log da geração
        error_log(sprintf(
            '[WhatsApp PrepSend] tenant=%s conv=%s phone=%s canSend=%s',
            $tenantId, $id, $formattedPhone, $canSend ? 'yes' : 'no'
        ));

        $this->jsonSuccess([
            'generated_message'     => $message,
            'contact_phone'         => $rawPhone,
            'formatted_phone'       => $formattedPhone,
            'channel_connected'     => $channelConnected,
            'connection_status'     => $connectionStatus,
            'can_open_whatsapp_link'=> $canSend,
            'whatsapp_url'          => $whatsappUrl,
            'validation_message'    => $validationMessage,
        ]);
    }

    /**
     * Formata número de telefone para padrão internacional brasileiro.
     * Remove caracteres especiais, adiciona DDI +55 se necessário.
     */
    private function formatPhoneInternational(string $phone): string
    {
        if (empty($phone)) return '';

        // Remove tudo que não é dígito
        $digits = preg_replace('/\D/', '', $phone);

        if (empty($digits)) return '';

        // Se já começa com 55 e tem 12-13 dígitos (55 + DDD + número), está ok
        if (str_starts_with($digits, '55') && strlen($digits) >= 12 && strlen($digits) <= 13) {
            return '+' . $digits;
        }

        // Se tem 10-11 dígitos (DDD + número sem DDI), adicionar 55
        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            return '+55' . $digits;
        }

        // Se já tem DDI de outro país (começa com outro código e tem muitos dígitos)
        if (strlen($digits) >= 12) {
            return '+' . $digits;
        }

        // Fallback: retorna com + na frente
        return '+' . $digits;
    }

    // ─── GET /whatsapp/webhook ───────────────────────────────────────────────
    // ─── POST /whatsapp/webhook ──────────────────────────────────────────────

    public function webhookHandler(): void
    {
        // Valida secret header
        $integration = null;

        // Tenta identificar o tenant pelo header X-Api-Key ou parâmetro ?tenant
        $headerKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
        $tenantId  = $_GET['tenant'] ?? '';

        if ($tenantId) {
            $integration = WhatsAppIntegration::findByTenant($tenantId);
        }

        if ($integration && $headerKey) {
            // Valida contra o webhook_secret salvo
            $secret = $integration['webhook_secret'] ?? '';
            if ($secret && !hash_equals($secret, $headerKey)) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }
        }

        // Recebe payload
        $body    = file_get_contents('php://input');
        $payload = json_decode($body, true) ?? [];
        $event   = $payload['event'] ?? '';

        // Responde imediatamente (Evolution API não espera processamento pesado)
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['received' => true]);

        if (!$tenantId || !$event) return;

        // Log do evento
        WhatsAppIntegrationLog::log(
            $tenantId,
            $integration['id'] ?? null,
            'webhook.' . $event,
            WhatsAppIntegrationLog::DIR_INBOUND,
            WhatsAppIntegrationLog::STATUS_SUCCESS,
            array_slice($payload, 0, 10) // limita payload no log
        );

        // connection.update → atualiza status da instância
        if ($event === 'connection.update') {
            $status = $payload['data']['state'] ?? '';
            if ($status && $integration) {
                $map = [
                    'open'      => 'connected',
                    'close'     => 'disconnected',
                    'connecting'=> 'connecting',
                ];
                $internal = $map[$status] ?? $status;
                WhatsAppIntegration::updateStatus($integration['id'], $internal);
            }
        }

        // messages.upsert → persiste mensagem recebida em tempo real
        if (in_array($event, ['messages.upsert', 'messages.update'], true)) {
            $messages = $payload['data'] ?? [];
            if (!is_array($messages)) $messages = [$messages];

            foreach ($messages as $msg) {
                $remoteJid = $msg['key']['remoteJid'] ?? '';
                $remoteId  = $msg['key']['id'] ?? '';
                if (!$remoteJid || !$remoteId) continue;

                // Upsert conversation
                $conv = WhatsAppConversation::findByJid($tenantId, $remoteJid);
                if (!$conv) {
                    WhatsAppConversation::upsertByJid($tenantId, $integration['id'] ?? '', [
                        'remote_jid'           => $remoteJid,
                        'display_name'         => $msg['pushName'] ?? $remoteJid,
                        'last_message_preview' => '',
                    ]);
                    $conv = WhatsAppConversation::findByJid($tenantId, $remoteJid);
                }

                if (!$conv) continue;

                $body    = $msg['message']['conversation']
                    ?? $msg['message']['extendedTextMessage']['text']
                    ?? '';
                $msgType = 'text';
                if (isset($msg['message']['imageMessage'])) {
                    $msgType = 'image';
                    $body = $body ?: '📷 Imagem';
                } elseif (isset($msg['message']['audioMessage'])) {
                    $msgType = 'audio';
                    $body = $body ?: '🎵 Áudio';
                } elseif (isset($msg['message']['videoMessage'])) {
                    $msgType = 'video';
                    $body = $body ?: '🎬 Vídeo';
                } elseif (isset($msg['message']['documentMessage'])) {
                    $msgType = 'file';
                    $body = $body ?: '📎 Arquivo';
                }
                $fromMe  = $msg['key']['fromMe'] ?? false;
                $ts      = $msg['messageTimestamp'] ?? time();

                $inserted = WhatsAppMessage::insertIgnore($conv['id'], $tenantId, [
                    'remote_id'    => $remoteId,
                    'direction'    => $fromMe ? 'outgoing' : 'incoming',
                    'body'         => $body,
                    'message_type' => $msgType,
                    'timestamp'    => (int) $ts,
                    'status'       => 'received',
                ]);

                // Atualiza preview da conversa
                if ($body) {
                    WhatsAppConversation::upsertByJid($tenantId, $integration['id'] ?? '', [
                        'remote_jid'           => $remoteJid,
                        'last_message_preview' => mb_substr($body, 0, 120),
                        'last_message_at'      => date('Y-m-d H:i:s', (int) $ts),
                    ]);
                }

                if (!$fromMe) {
                    WhatsAppConversation::recalculateUnread($conv['id'], $tenantId);
                }
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function mapWhatsAppNotificationItem(array $item): array
    {
        $conversationId = (string) ($item['id'] ?? '');
        $label = trim((string) ($item['display_name'] ?? $item['phone'] ?? $item['remote_jid'] ?? ''));
        $preview = trim((string) ($item['last_message_preview'] ?? ''));
        $lastMessageAt = $item['last_message_at'] ?? null;
        $timestamp = $lastMessageAt ? strtotime((string) $lastMessageAt) : false;

        return [
            'id' => 'whatsapp:' . $conversationId,
            'entity_id' => $conversationId,
            'source' => 'whatsapp',
            'source_label' => 'WhatsApp',
            'title' => $label !== '' ? $label : 'Conversa sem nome',
            'preview' => $preview !== '' ? $preview : 'Nova mensagem recebida.',
            'meta' => !empty($item['lead_name'])
                ? 'Lead: ' . $item['lead_name']
                : (!empty($item['phone']) ? $item['phone'] : ''),
            'href' => '/whatsapp?conversation=' . rawurlencode($conversationId),
            'icon' => 'chat',
            'tone' => 'lime',
            'time' => $lastMessageAt,
            'badge_count' => max(0, (int) ($item['unread_count'] ?? 0)),
            'status_badge' => null,
            'toast_key' => 'whatsapp:' . $conversationId . ':' . ($lastMessageAt ?? ''),
            'toast_title' => 'Nova mensagem no WhatsApp',
            'should_toast' => max(0, (int) ($item['unread_count'] ?? 0)) > 0,
            '_sort_group' => 10,
            '_sort_time' => $timestamp !== false ? (0 - $timestamp) : PHP_INT_MAX,
        ];
    }

    private function buildAgendaNotificationBundle(string $tenantId, array $options): array
    {
        $followupsEnabled = (bool) ($options['followups'] ?? false);
        $todayEnabled = (bool) ($options['today'] ?? false);
        $oneHourEnabled = (bool) ($options['one_hour'] ?? false);

        if (!$followupsEnabled && !$todayEnabled && !$oneHourEnabled) {
            return [
                'total' => 0,
                'items' => [],
            ];
        }

        $now = new \DateTimeImmutable('now');
        $items = [];

        if ($followupsEnabled) {
            $followups = Database::select(
                "SELECT f.*, l.name AS lead_name
                 FROM followups f
                 LEFT JOIN leads l ON l.id = f.lead_id
                 WHERE f.tenant_id = ? AND f.completed = 0
                 ORDER BY f.scheduled_at ASC
                 LIMIT 40",
                [$tenantId]
            );

            foreach ($followups as $followup) {
                $mapped = $this->mapFollowupNotificationItem($followup, $now, $oneHourEnabled);
                if ($mapped) {
                    $items[] = $mapped;
                }
            }
        }

        if ($todayEnabled || $oneHourEnabled) {
            $events = Database::select(
                'SELECT * FROM agenda_events WHERE tenant_id = ? ORDER BY start_time ASC LIMIT 40',
                [$tenantId]
            );

            foreach ($events as $event) {
                $mapped = $this->mapAgendaEventNotificationItem($event, $now, $todayEnabled, $oneHourEnabled);
                if ($mapped) {
                    $items[] = $mapped;
                }
            }
        }

        usort($items, function (array $a, array $b): int {
            $left = [$a['_sort_group'] ?? 99, $a['_sort_time'] ?? PHP_INT_MAX, $a['title'] ?? ''];
            $right = [$b['_sort_group'] ?? 99, $b['_sort_time'] ?? PHP_INT_MAX, $b['title'] ?? ''];
            return $left <=> $right;
        });

        return [
            'total' => count($items),
            'items' => array_slice($items, 0, 8),
        ];
    }

    private function mapAgendaEventNotificationItem(
        array $event,
        \DateTimeImmutable $now,
        bool $includeToday,
        bool $includeOneHour
    ): ?array {
        try {
            $startAt = new \DateTimeImmutable((string) ($event['start_time'] ?? ''));
        } catch (\Throwable) {
            return null;
        }

        $state = $this->buildAgendaNotificationState($startAt, $now, $includeToday, $includeOneHour, false);
        if (!$state) {
            return null;
        }

        $entityId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['event_type'] ?? 'reminder');
        $label = $eventType === 'appointment' ? 'Compromisso' : 'Lembrete';
        $description = trim((string) ($event['description'] ?? ''));
        $preview = $description !== ''
            ? $description
            : ($state['category'] === 'due_soon'
                ? 'Falta menos de 1 hora para este item da agenda.'
                : 'Compromisso previsto para hoje na agenda.');

        return [
            'id' => 'agenda:' . $entityId,
            'entity_id' => $entityId,
            'source' => 'agenda',
            'source_label' => 'Agenda',
            'title' => trim((string) ($event['title'] ?? '')) ?: ($label . ' sem título'),
            'preview' => $preview,
            'meta' => $label,
            'href' => '/agenda?event=' . rawurlencode($entityId) . '&type=' . rawurlencode($eventType),
            'icon' => $eventType === 'appointment' ? 'event' : 'push_pin',
            'tone' => $state['tone'],
            'time' => $startAt->format('Y-m-d H:i:s'),
            'badge_count' => null,
            'status_badge' => $state['badge'],
            'toast_key' => 'agenda:' . $entityId . ':' . $state['category'] . ':' . $startAt->format('YmdHi'),
            'toast_title' => $label . ' em breve',
            'should_toast' => $state['should_toast'],
            '_sort_group' => $state['sort_group'],
            '_sort_time' => $state['sort_time'],
        ];
    }

    private function mapFollowupNotificationItem(
        array $followup,
        \DateTimeImmutable $now,
        bool $includeOneHour
    ): ?array {
        try {
            $startAt = new \DateTimeImmutable((string) ($followup['scheduled_at'] ?? ''));
        } catch (\Throwable) {
            return null;
        }

        $state = $this->buildAgendaNotificationState($startAt, $now, true, $includeOneHour, true);
        if (!$state) {
            return null;
        }

        $entityId = (string) ($followup['id'] ?? '');
        $leadName = trim((string) ($followup['lead_name'] ?? ''));
        $description = trim((string) ($followup['description'] ?? ''));
        $preview = $description !== ''
            ? $description
            : ($leadName !== '' ? 'Lead: ' . $leadName : 'Follow-up pendente na agenda comercial.');

        return [
            'id' => 'followup:' . $entityId,
            'entity_id' => $entityId,
            'source' => 'followup',
            'source_label' => 'Follow-up',
            'title' => 'Follow-up: ' . (trim((string) ($followup['title'] ?? '')) ?: 'Sem título'),
            'preview' => $preview,
            'meta' => $leadName !== '' ? 'Lead: ' . $leadName : 'Lead não identificado',
            'href' => '/agenda?event=' . rawurlencode($entityId) . '&type=followup',
            'icon' => 'notification_important',
            'tone' => $state['tone'],
            'time' => $startAt->format('Y-m-d H:i:s'),
            'badge_count' => null,
            'status_badge' => $state['badge'],
            'toast_key' => 'followup:' . $entityId . ':' . $state['category'] . ':' . $startAt->format('YmdHi'),
            'toast_title' => $state['category'] === 'due_soon' ? 'Follow-up em breve' : 'Follow-up pendente',
            'should_toast' => $state['should_toast'],
            '_sort_group' => $state['sort_group'],
            '_sort_time' => $state['sort_time'],
        ];
    }

    private function buildAgendaNotificationState(
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $now,
        bool $includeToday,
        bool $includeOneHour,
        bool $allowOverdue
    ): ?array {
        $diffSeconds = $startAt->getTimestamp() - $now->getTimestamp();
        $isToday = $startAt->format('Y-m-d') === $now->format('Y-m-d');

        if ($includeOneHour && $diffSeconds >= 0 && $diffSeconds <= 3600) {
            if ($diffSeconds <= 300) {
                $badge = 'Agora';
            } elseif ($diffSeconds >= 3540) {
                $badge = '1h';
            } else {
                $badge = (string) max(1, (int) ceil($diffSeconds / 60)) . 'm';
            }

            return [
                'category' => 'due_soon',
                'badge' => $badge,
                'tone' => 'amber',
                'sort_group' => 0,
                'sort_time' => $startAt->getTimestamp(),
                'should_toast' => true,
            ];
        }

        if ($allowOverdue && $diffSeconds < 0) {
            return [
                'category' => 'overdue',
                'badge' => 'Atrasado',
                'tone' => 'red',
                'sort_group' => 1,
                'sort_time' => $startAt->getTimestamp(),
                'should_toast' => false,
            ];
        }

        if ($includeToday && $isToday && $diffSeconds >= 0) {
            return [
                'category' => 'today',
                'badge' => 'Hoje',
                'tone' => 'blue',
                'sort_group' => 25,
                'sort_time' => $startAt->getTimestamp(),
                'should_toast' => false,
            ];
        }

        return null;
    }

    private function stripNotificationSortMeta(array $item): array
    {
        unset($item['_sort_group'], $item['_sort_time']);
        return $item;
    }

    private function jsonSuccess(array $data, int $code = 200): never
    {
        // Limpar qualquer output buffered (warnings PHP, etc.) que possa corromper o JSON
        while (ob_get_level()) ob_end_clean();
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, ...$data]);
        exit;
    }

    private function jsonError(string $message, int $code = 400): never
    {
        while (ob_get_level()) ob_end_clean();
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }

    private function syncMetaSnapshot(?array $integration, array $overrides = []): array
    {
        return array_merge([
            'requested'    => false,
            'performed'    => false,
            'reason'       => $integration ? 'idle' : 'no_integration',
            'last_sync_at' => $integration['last_sync_at'] ?? null,
        ], $overrides);
    }

    private function runSmartSync(string $tenantId, ?array $integration, array $options = []): array
    {
        $force = (bool) ($options['force'] ?? false);
        $minInterval = max(0, (int) ($options['min_interval'] ?? 12));
        $trigger = (string) ($options['trigger'] ?? 'background');
        $meta = $this->syncMetaSnapshot($integration, [
            'requested' => (bool) ($options['requested'] ?? true),
            'trigger'   => $trigger,
        ]);

        if (!$integration) {
            return $meta;
        }

        if (($integration['status'] ?? '') !== 'connected') {
            $meta['reason'] = 'not_connected';
            return $meta;
        }

        $lastSyncAt = $integration['last_sync_at'] ?? null;
        if (!$force && $lastSyncAt) {
            $lastSyncTs = strtotime((string) $lastSyncAt);
            if ($lastSyncTs !== false && (time() - $lastSyncTs) < $minInterval) {
                $meta['reason'] = 'throttled';
                return $meta;
            }
        }

        $lock = $this->acquireSyncLock($tenantId);
        if (!$lock['acquired']) {
            $meta['reason'] = 'locked';
            return $meta;
        }

        try {
            set_time_limit(120);
            $result = $this->sync->syncTenant($tenantId);
            $latestIntegration = WhatsAppIntegration::findByTenant($tenantId) ?? $integration;
            $meta['last_sync_at'] = $latestIntegration['last_sync_at'] ?? ($result['last_sync_at'] ?? $meta['last_sync_at']);

            if (!($result['success'] ?? false)) {
                $meta['reason'] = 'sync_failed';
                $meta['error'] = $result['error'] ?? 'Falha na sincronização.';
                return $meta;
            }

            $meta['performed'] = true;
            $meta['reason'] = 'synced';
            $meta['stats'] = [
                'conversations_synced' => (int) ($result['conversations_synced'] ?? 0),
                'messages_synced'      => (int) ($result['messages_synced'] ?? 0),
                'contacts_synced'      => (int) ($result['contacts_synced'] ?? 0),
                'auto_links'           => (int) ($result['auto_links'] ?? 0),
            ];
            return $meta;
        } catch (\Throwable $e) {
            error_log('[WhatsApp smart sync] falhou: ' . $e->getMessage());
            $meta['reason'] = 'sync_failed';
            $meta['error'] = $e->getMessage();
            return $meta;
        } finally {
            $this->releaseSyncLock($lock['handle'] ?? null);
        }
    }

    private function acquireSyncLock(string $tenantId): array
    {
        $lockDir = STORAGE_PATH . '/locks';
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $safeTenantId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $tenantId);
        $lockPath = $lockDir . '/whatsapp-sync-' . $safeTenantId . '.lock';
        $handle = @fopen($lockPath, 'c+');
        if (!$handle) {
            return ['acquired' => false, 'handle' => null];
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            @fclose($handle);
            return ['acquired' => false, 'handle' => null];
        }

        return ['acquired' => true, 'handle' => $handle];
    }

    private function releaseSyncLock(mixed $handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}
