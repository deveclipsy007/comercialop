<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ApiKeyEncryptor;

/**
 * Gerenciamento de chaves API de provedores de IA.
 * Chaves armazenadas encriptadas (AES-256-CBC).
 */
class AiApiKey
{
    /**
     * Retorna a chave decriptada para um provedor.
     * Prioridade: tenant-level → global (tenant_id IS NULL) → .env fallback.
     */
    public static function getDecryptedKey(string $provider, ?string $tenantId = null): string
    {
        // 1. Buscar chave do tenant
        if ($tenantId) {
            $row = Database::selectFirst(
                'SELECT encrypted_key FROM ai_api_keys WHERE tenant_id = ? AND provider = ? AND is_active = 1',
                [$tenantId, $provider]
            );
            if ($row && !empty($row['encrypted_key'])) {
                try {
                    self::touchLastUsed($tenantId, $provider);
                    return ApiKeyEncryptor::decrypt($row['encrypted_key']);
                } catch (\Throwable $e) {
                    error_log("[AiApiKey] Falha ao decriptar chave tenant={$tenantId} provider={$provider}: " . $e->getMessage());
                }
            }
        }

        // 2. Buscar chave global (tenant_id IS NULL)
        $global = Database::selectFirst(
            'SELECT encrypted_key FROM ai_api_keys WHERE tenant_id IS NULL AND provider = ? AND is_active = 1',
            [$provider]
        );
        if ($global && !empty($global['encrypted_key'])) {
            try {
                self::touchLastUsed(null, $provider);
                return ApiKeyEncryptor::decrypt($global['encrypted_key']);
            } catch (\Throwable $e) {
                error_log("[AiApiKey] Falha ao decriptar chave global provider={$provider}: " . $e->getMessage());
            }
        }

        // 3. Fallback para .env
        $envMap = [
            'gemini' => 'GEMINI_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'grok'   => 'GROK_API_KEY',
            'google_places' => 'GOOGLE_MAPS_API_KEY',
        ];
        return env($envMap[$provider] ?? '', '');
    }

    /**
     * Insere ou atualiza uma chave API (já encriptada).
     */
    public static function upsert(string $provider, string $plainKey, ?string $tenantId = null, string $label = ''): void
    {
        $encrypted = ApiKeyEncryptor::encrypt($plainKey);
        $id = bin2hex(random_bytes(8));

        if ($tenantId) {
            $existing = Database::selectFirst(
                'SELECT id FROM ai_api_keys WHERE tenant_id = ? AND provider = ?',
                [$tenantId, $provider]
            );
        } else {
            $existing = Database::selectFirst(
                'SELECT id FROM ai_api_keys WHERE tenant_id IS NULL AND provider = ?',
                [$provider]
            );
        }

        if ($existing) {
            Database::execute(
                'UPDATE ai_api_keys SET encrypted_key = ?, label = ?, is_active = 1, updated_at = datetime("now") WHERE id = ?',
                [$encrypted, $label ?: null, $existing['id']]
            );
        } else {
            Database::execute(
                'INSERT INTO ai_api_keys (id, tenant_id, provider, encrypted_key, label, is_active) VALUES (?, ?, ?, ?, ?, 1)',
                [$id, $tenantId, $provider, $encrypted, $label ?: null]
            );
        }
    }

    /**
     * Lista todas as chaves de um tenant (sem revelar chave real).
     */
    public static function listAll(?string $tenantId = null): array
    {
        if ($tenantId) {
            $rows = Database::select(
                'SELECT id, tenant_id, provider, label, is_active, last_used_at, created_at, updated_at
                 FROM ai_api_keys WHERE tenant_id = ? OR tenant_id IS NULL ORDER BY provider ASC',
                [$tenantId]
            );
        } else {
            $rows = Database::select(
                'SELECT id, tenant_id, provider, label, is_active, last_used_at, created_at, updated_at
                 FROM ai_api_keys ORDER BY provider ASC',
                []
            );
        }

        // Mascarar: indicar apenas se a chave existe
        foreach ($rows as &$row) {
            $row['has_key'] = true;
            $row['key_preview'] = '••••••••••••';
        }
        return $rows;
    }

    /**
     * Desativa uma chave (soft delete).
     */
    public static function deactivate(string $id): void
    {
        Database::execute(
            'UPDATE ai_api_keys SET is_active = 0, updated_at = datetime("now") WHERE id = ?',
            [$id]
        );
    }

    /**
     * Remove uma chave definitivamente.
     */
    public static function delete(string $id): void
    {
        Database::execute('DELETE FROM ai_api_keys WHERE id = ?', [$id]);
    }

    /**
     * Verifica se um provedor tem chave configurada (DB ou .env).
     */
    public static function hasKey(string $provider, ?string $tenantId = null): bool
    {
        $key = self::getDecryptedKey($provider, $tenantId);
        return !empty($key);
    }

    /**
     * Atualiza last_used_at.
     */
    private static function touchLastUsed(?string $tenantId, string $provider): void
    {
        try {
            if ($tenantId) {
                Database::execute(
                    'UPDATE ai_api_keys SET last_used_at = datetime("now") WHERE tenant_id = ? AND provider = ?',
                    [$tenantId, $provider]
                );
            } else {
                Database::execute(
                    'UPDATE ai_api_keys SET last_used_at = datetime("now") WHERE tenant_id IS NULL AND provider = ?',
                    [$provider]
                );
            }
        } catch (\Throwable $e) {
            // Non-critical
        }
    }
}
