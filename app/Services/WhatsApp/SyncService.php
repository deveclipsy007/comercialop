<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\App;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppLeadLink;
use App\Models\Lead;

class SyncService
{
    private string $baseUrl;
    private string $globalApiKey;

    public function __construct()
    {
        $config = App::config('services.evolution');
        $this->baseUrl      = rtrim($config['base_url'], '/');
        $this->globalApiKey = $config['api_key'];
    }

    /**
     * Sincroniza conversas e mensagens de um tenant.
     */
    public function syncTenant(string $tenantId): array
    {
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        if (!$integration || $integration['status'] !== 'connected') {
            return ['success' => false, 'error' => 'Instância não conectada.'];
        }

        try {
            $stats = [
                'conversations_synced' => 0,
                'messages_synced'      => 0,
                'contacts_synced'      => 0,
                'auto_links'           => 0,
            ];

            $instanceName = $integration['instance_name'];

            // 1. Sincronizar Conversas (Chats) — GET /chat/findChats/{instance}
            $chats = $this->request('POST', "/chat/findChats/{$instanceName}");

            if (!is_array($chats)) {
                $chats = [];
            }

            foreach ($chats as $chat) {
                $jid = $chat['remoteJid'] ?? $chat['id'] ?? '';
                if (!$jid || str_contains($jid, '@g.us')) continue; // Ignorar grupos

                // Extrair nome: pushName do chat, do lastMessage, ou fallback para JID
                $displayName = $chat['name']
                    ?? $chat['pushName']
                    ?? $chat['contact']['pushName']
                    ?? $chat['lastMessage']['pushName']
                    ?? explode('@', $jid)[0];

                // Extrair preview da última mensagem (varia entre versões da API)
                $lastMsgPreview = null;
                $lm = $chat['lastMessage'] ?? null;
                if (is_array($lm)) {
                    $lastMsgPreview = $lm['message']['conversation']
                        ?? $lm['message']['extendedTextMessage']['text']
                        ?? $lm['message']['imageMessage']['caption']
                        ?? null;
                    // Fallback por tipo de mensagem
                    if (!$lastMsgPreview) {
                        $type = $lm['messageType'] ?? '';
                        $typeLabels = ['imageMessage' => '📷 Imagem', 'audioMessage' => '🎵 Áudio', 'videoMessage' => '🎬 Vídeo', 'documentMessage' => '📎 Arquivo'];
                        $lastMsgPreview = $typeLabels[$type] ?? null;
                    }
                } elseif (is_string($lm)) {
                    $lastMsgPreview = $lm;
                }

                // Extrair timestamp da última mensagem
                $lastMsgAt = null;
                if (isset($chat['lastMessage']['messageTimestamp'])) {
                    $lastMsgAt = date('Y-m-d H:i:s', (int) $chat['lastMessage']['messageTimestamp']);
                } elseif (isset($chat['updatedAt'])) {
                    $lastMsgAt = $chat['updatedAt'];
                }

                $convId = WhatsAppConversation::upsertByJid($tenantId, $integration['id'], [
                    'remote_jid'           => $jid,
                    'display_name'         => $displayName,
                    'phone'                => explode('@', $jid)[0],
                    'last_message_preview' => $lastMsgPreview ? mb_substr($lastMsgPreview, 0, 120) : null,
                    'last_message_at'      => $lastMsgAt,
                ]);

                $stats['conversations_synced']++;

                // 2. Sincronizar Últimas Mensagens — POST /chat/findMessages/{instance}
                try {
                    $messages = $this->request('POST', "/chat/findMessages/{$instanceName}", [
                        'where' => [
                            'key' => [
                                'remoteJid' => $jid,
                            ]
                        ],
                        'limit' => 30,
                    ]);

                    // Evolution API v2 retorna: { "messages": { "total": N, "records": [...] } }
                    if (isset($messages['messages']['records']) && is_array($messages['messages']['records'])) {
                        $messages = $messages['messages']['records'];
                    } elseif (isset($messages['messages']) && is_array($messages['messages'])) {
                        $messages = $messages['messages'];
                    }

                    if (!is_array($messages)) {
                        $messages = [];
                    }

                    foreach ($messages as $msg) {
                        $remoteId = $msg['key']['id'] ?? '';
                        if (!$remoteId) continue;

                        // Extrair corpo da mensagem (suporta text, extendedText, imageCaption)
                        $body = $msg['message']['conversation']
                            ?? $msg['message']['extendedTextMessage']['text']
                            ?? $msg['message']['imageMessage']['caption']
                            ?? $msg['message']['videoMessage']['caption']
                            ?? $msg['message']['documentMessage']['caption']
                            ?? null;

                        // Determinar tipo de mensagem
                        $msgType = $msg['messageType'] ?? 'text';
                        if (isset($msg['message']['imageMessage'])) $msgType = 'image';
                        elseif (isset($msg['message']['audioMessage'])) $msgType = 'audio';
                        elseif (isset($msg['message']['videoMessage'])) $msgType = 'video';
                        elseif (isset($msg['message']['documentMessage'])) $msgType = 'file';

                        // Para mensagens sem texto (imagem sem caption, audio, etc), gerar placeholder
                        if (!$body && $msgType !== 'text') {
                            $typeLabels = ['image' => '📷 Imagem', 'audio' => '🎵 Áudio', 'video' => '🎬 Vídeo', 'file' => '📎 Arquivo'];
                            $body = $typeLabels[$msgType] ?? '📎 Mídia';
                        }

                        if ($body) {
                            $inserted = WhatsAppMessage::insertIgnore($convId, $tenantId, [
                                'remote_id'    => $remoteId,
                                'direction'    => ($msg['key']['fromMe'] ?? false) ? 'outgoing' : 'incoming',
                                'body'         => $body,
                                'message_type' => $msgType,
                                'timestamp'    => (int)($msg['messageTimestamp'] ?? time()),
                            ]);
                            if ($inserted) $stats['messages_synced']++;
                        }
                    }
                } catch (\Exception $e) {
                    // Log mas continua com as próximas conversas
                    error_log("[SyncService] Failed to fetch messages for {$jid}: " . $e->getMessage());
                }

                // 3. Tentar Auto-link com Lead por telefone
                $this->attemptAutoLink($tenantId, $convId, $jid, $stats);
            }

            WhatsAppIntegration::updateSyncTime($integration['id']);

            return ['success' => true, ...$stats];
        } catch (\Exception $e) {
            error_log("[SyncService] syncTenant error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tenta vincular automaticamente uma conversa a um lead existente pelo telefone.
     */
    private function attemptAutoLink(string $tenantId, string $convId, string $jid, array &$stats): void
    {
        $existingLink = WhatsAppLeadLink::findByConversation($tenantId, $convId);
        if ($existingLink) return;

        $phone = explode('@', $jid)[0];
        $cleanPhone = preg_replace('/\D/', '', $phone);
        $suffix = substr($cleanPhone, -9);

        if (strlen($suffix) >= 8) {
            $leads = Lead::searchByPhone($suffix, $tenantId, 1);
            if (!empty($leads)) {
                WhatsAppLeadLink::link($tenantId, $convId, $leads[0]['id'], 'auto');
                $stats['auto_links']++;
            }
        }
    }

    /**
     * Requisição cURL à Evolution API.
     * Suporta GET e POST com body JSON.
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . $this->globalApiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Localizar bundle de certificados (macOS/Homebrew/Linux)
        $certPaths = [
            '/etc/ssl/cert.pem',
            '/opt/homebrew/etc/ca-certificates/cert.pem',
            '/usr/local/etc/ca-certificates/cert.pem',
            '/opt/homebrew/share/ca-certificates/cert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];

        $certFound = false;
        foreach ($certPaths as $certPath) {
            if (file_exists($certPath)) {
                curl_setopt($ch, CURLOPT_CAINFO, $certPath);
                $certFound = true;
                break;
            }
        }

        if (!$certFound) {
            $appEnv = $_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? 'development');
            if ($appEnv !== 'production') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
        }

        // Enviar body JSON se houver dados
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        unset($ch);

        if ($error) {
            error_log("[SyncService] cURL Error on {$method} {$url}: {$error}");
            throw new \Exception("Erro de conexão com Evolution API: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? "HTTP Error {$httpCode}";
            if (is_array($msg)) $msg = json_encode($msg);
            error_log("[SyncService] API Error ({$httpCode}) on {$method} {$url}: " . ($response ?: 'empty'));
            throw new \Exception("Evolution API ({$httpCode}): {$msg}");
        }

        return $decoded ?? [];
    }
}
