<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Lead;
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

        $result = $this->sync->syncTenant($tenantId);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Falha na sincronização.');
        }

        $this->jsonSuccess([
            'message'       => 'Sincronização concluída.',
            'conversations' => $result['conversations_synced'] ?? 0,
            'messages'      => $result['messages_synced']      ?? 0,
            'contacts'      => $result['contacts_synced']      ?? 0,
            'auto_links'    => $result['auto_links']           ?? 0,
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

        $conversation = WhatsAppConversation::findByIdAndTenant($id, $tenantId);
        if (!$conversation) {
            $this->jsonError('Conversa não encontrada.', 404);
        }

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
                $fromMe  = $msg['key']['fromMe'] ?? false;
                $ts      = $msg['messageTimestamp'] ?? time();

                WhatsAppMessage::insertIgnore($conv['id'], $tenantId, [
                    'remote_id'    => $remoteId,
                    'direction'    => $fromMe ? 'outgoing' : 'incoming',
                    'body'         => $body,
                    'message_type' => 'text',
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
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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
}
