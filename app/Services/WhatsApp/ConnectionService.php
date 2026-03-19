<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\App;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppIntegrationLog;

class ConnectionService
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
     * Configura a integração do tenant e cria a instância na Evolution API.
     * Retorna o QR Code imediatamente se a criação for bem-sucedida.
     */
    public function setupAndConnect(string $tenantId, string $instanceName): array
    {
        try {
            // 1. Persistir integração localmente
            $integrationId = WhatsAppIntegration::upsert($tenantId, [
                'instance_name' => $instanceName,
                'base_url'      => $this->baseUrl,
                'api_key'       => $this->globalApiKey,
            ]);

            // 2. Criar instância na Evolution API
            try {
                $response = $this->request('POST', '/instance/create', [
                    'instanceName' => $instanceName,
                    'integration'  => 'WHATSAPP-BAILEYS',
                    'token'        => $instanceName . '_token',
                    'qrcode'       => true,
                ]);
            } catch (\Exception $e) {
                // Se a instância já existe, prosseguimos para obter o QR
                if (str_contains(strtolower($e->getMessage()), 'already exist')) {
                    error_log("[Evolution API] Instance {$instanceName} already exists. Proceeding to connect...");
                    $response = ['status' => 'already_exists'];
                } else {
                    throw $e;
                }
            }

            WhatsAppIntegrationLog::log($tenantId, $integrationId, 'instance_create', 'outbound', 'success', $response);

            // 3. Imediatamente obter o QR Code
            $qrResult = $this->generateQrCode($tenantId);

            return [
                'success'     => true,
                'integration' => WhatsAppIntegration::findByTenant($tenantId),
                'qr_code'     => $qrResult['qr_code'] ?? null,
                'qr_status'   => $qrResult['status'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            WhatsAppIntegrationLog::log($tenantId, null, 'instance_create', 'outbound', 'error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Configura ou atualiza a integração do tenant e cria a instância na Evolution API.
     * (mantido para retrocompatibilidade)
     */
    public function setupIntegration(string $tenantId, array $data): array
    {
        return $this->setupAndConnect($tenantId, $data['instance_name']);
    }

    /**
     * Gera ou recupera o QR Code da instância.
     */
    public function generateQrCode(string $tenantId): array
    {
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        if (!$integration) return ['success' => false, 'error' => 'Integração não configurada.'];

        try {
            $response = $this->request('GET', "/instance/connect/{$integration['instance_name']}");

            if (isset($response['base64'])) {
                return [
                    'success' => true,
                    'qr_code' => $response['base64'],
                    'status'  => 'awaiting_scan'
                ];
            }

            // Pode retornar code em vez de base64 dependendo da versão da Evolution API
            if (isset($response['code'])) {
                return [
                    'success' => true,
                    'qr_code' => $response['code'],
                    'status'  => 'awaiting_scan'
                ];
            }

            // Se já está conectado
            if (isset($response['instance']['state']) && $response['instance']['state'] === 'open') {
                WhatsAppIntegration::updateStatus($integration['id'], 'connected');
                return [
                    'success' => true,
                    'status'  => 'already_connected',
                    'qr_code' => null,
                ];
            }

            return ['success' => false, 'error' => 'Falha ao obter QR Code. Resposta: ' . json_encode($response)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica o status da conexão.
     */
    public function getStatus(string $tenantId): array
    {
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        if (!$integration) return ['success' => false, 'status' => 'not_configured'];

        try {
            $response = $this->request('GET', "/instance/connectionState/{$integration['instance_name']}");
            $state = $response['instance']['state'] ?? 'disconnected';

            $internalStatus = 'disconnected';
            if ($state === 'open') $internalStatus = 'connected';
            if ($state === 'connecting') $internalStatus = 'connecting';

            WhatsAppIntegration::updateStatus($integration['id'], $internalStatus);

            return [
                'success'           => true,
                'status'            => $internalStatus,
                'connection_status' => $state,
                'instance_name'     => $integration['instance_name'],
                'last_sync_at'      => $integration['last_sync_at'],
                'has_integration'   => true
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Desconecta e remove a instância.
     */
    public function disconnect(string $tenantId): array
    {
        $integration = WhatsAppIntegration::findByTenant($tenantId);
        if (!$integration) return ['success' => false, 'error' => 'Integração não encontrada.'];

        try {
            // Tenta logout primeiro (pode falhar se já desconectado)
            try {
                $this->request('DELETE', "/instance/logout/{$integration['instance_name']}");
            } catch (\Exception $e) {
                error_log("[Evolution API] Logout failed (may already be disconnected): " . $e->getMessage());
            }

            // Depois deleta a instância
            try {
                $this->request('DELETE', "/instance/delete/{$integration['instance_name']}");
            } catch (\Exception $e) {
                error_log("[Evolution API] Delete failed: " . $e->getMessage());
            }

            WhatsAppIntegration::updateStatus($integration['id'], 'disconnected');

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Helper para requisições cURL à Evolution API.
     * Corrigido: SSL cert path para macOS/Homebrew + removido curl_close() deprecated.
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        $headers = [
            'apikey: ' . $this->globalApiKey,
            'Content-Type: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Tentar localizar bundle de certificados (macOS/Homebrew/Linux)
        $certPaths = [
            '/etc/ssl/cert.pem',                            // macOS system default
            '/opt/homebrew/etc/ca-certificates/cert.pem',   // Homebrew Apple Silicon
            '/usr/local/etc/ca-certificates/cert.pem',      // Homebrew Intel
            '/opt/homebrew/share/ca-certificates/cert.pem', // Homebrew alt
            '/etc/ssl/certs/ca-certificates.crt',           // Debian/Ubuntu
            '/etc/pki/tls/certs/ca-bundle.crt',             // CentOS/RHEL
        ];

        $certFound = false;
        foreach ($certPaths as $certPath) {
            if (file_exists($certPath)) {
                curl_setopt($ch, CURLOPT_CAINFO, $certPath);
                $certFound = true;
                break;
            }
        }

        // Fallback: se nenhum cert encontrado em ambiente de desenvolvimento, desabilitar SSL verify
        if (!$certFound) {
            $appEnv = $_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? 'development');
            if ($appEnv !== 'production') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                error_log("[Evolution API] WARNING: SSL verification disabled — no cert bundle found. Not safe for production.");
            }
        }

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        // curl_close() é no-op desde PHP 8.0 e deprecated em 8.5.
        // O recurso cURL é liberado automaticamente pelo GC.
        unset($ch);

        if ($error) {
            error_log("[Evolution API] Curl Error on {$method} {$url}: {$error}");
            throw new \Exception("Erro de conexão com Evolution API: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? "HTTP Error {$httpCode}";
            if (is_array($msg)) $msg = json_encode($msg);
            error_log("[Evolution API] Error ({$httpCode}) on {$method} {$url}: " . $response);
            throw new \Exception("Evolution API ({$httpCode}): {$msg}");
        }

        return $decoded ?: [];
    }
}
