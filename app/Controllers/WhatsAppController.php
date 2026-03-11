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

    public function __construct()
    {
        $this->ensureTablesExist();
        $this->connection = new ConnectionService();
        $this->sync       = new SyncService();
        $this->analysis   = new ConversationAnalysisService();
    }

    // ─── Auto-migration ──────────────────────────────────────────────────────

    private function ensureTablesExist(): void
    {
        try {
            $exists = Database::selectFirst(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='whatsapp_integrations'",
                []
            );

            if ($exists) return;

            $sqlPath = defined('ROOT_PATH')
                ? ROOT_PATH . '/database/migrations/005_whatsapp_module.sql'
                : dirname(__DIR__, 2) . '/database/migrations/005_whatsapp_module.sql';

            if (!file_exists($sqlPath)) {
                error_log('[WhatsAppController] Migration file not found: ' . $sqlPath);
                return;
            }

            $sql   = file_get_contents($sqlPath);
            $clean = preg_replace('/--[^\n]*/', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
                Database::execute($stmt, []);
            }

            error_log('[WhatsAppController] Migration 005_whatsapp_module aplicada automaticamente.');
        } catch (\Throwable $e) {
            error_log('[WhatsAppController] ensureTablesExist() falhou: ' . $e->getMessage());
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

        $baseUrl      = trim($_POST['base_url']      ?? '');
        $apiKey       = trim($_POST['api_key']       ?? '');
        $instanceName = trim($_POST['instance_name'] ?? '');

        if (!$baseUrl || !$apiKey || !$instanceName) {
            $this->jsonError('Preencha todos os campos obrigatórios.');
        }

        $result = $this->connection->setupIntegration($tenantId, [
            'base_url'      => $baseUrl,
            'api_key'       => $apiKey,
            'instance_name' => $instanceName,
        ]);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Falha ao configurar integração.');
        }

        $this->jsonSuccess([
            'message'     => 'Integração configurada. Gerando QR Code…',
            'integration' => $result['integration'],
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

        $result = $this->connection->disconnect($tenantId);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Falha ao desconectar.');
        }

        $this->jsonSuccess(['message' => 'WhatsApp desconectado com sucesso.']);
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

        $leadLink       = WhatsAppLeadLink::findByConversation($tenantId, $id);
        $lead           = null;
        if ($leadLink) {
            $lead = Lead::findByTenant($leadLink['lead_id'], $tenantId);
        }

        $latestAnalysis = WhatsAppConversationAnalysis::latestByConversation($id);
        $allAnalyses    = WhatsAppConversationAnalysis::allByConversation($id);

        // Sugestão de link por telefone
        $suggestedLeads = [];
        if (!$leadLink && !empty($conversation['phone'])) {
            $phone = preg_replace('/\D/', '', $conversation['phone']);
            if (strlen($phone) >= 8) {
                $suffix = substr($phone, -9);
                $suggestedLeads = Lead::searchByPhone($suffix, $tenantId, 5);
            }
        }

        View::render('whatsapp/conversation', [
            'active'          => 'whatsapp',
            'conversation'    => $conversation,
            'messages'        => $messages,
            'total'           => $total,
            'page'            => $page,
            'pages'           => (int) ceil($total / $limit),
            'leadLink'        => $leadLink,
            'lead'            => $lead,
            'latestAnalysis'  => $latestAnalysis,
            'allAnalyses'     => $allAnalyses,
            'suggestedLeads'  => $suggestedLeads,
            'csrf'            => Session::csrf(),
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

        WhatsAppLeadLink::unlink($tenantId, $id);

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
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, ...$data]);
        exit;
    }

    private function jsonError(string $message, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
