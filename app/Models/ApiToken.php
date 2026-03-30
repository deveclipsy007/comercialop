<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Helpers;

class ApiToken
{
    /**
     * Cria um novo token de API para a extensão Chrome.
     * Retorna o token raw (única vez) + metadados.
     */
    public static function create(string $userId, string $tenantId, string $deviceName = 'Operon Capture'): array
    {
        $id       = Helpers::uuid();
        $rawToken = bin2hex(random_bytes(32)); // 64 chars
        $hash     = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

        Database::execute(
            "INSERT INTO api_tokens (id, user_id, tenant_id, token_hash, device_name, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$id, $userId, $tenantId, $hash, $deviceName, $expiresAt]
        );

        return [
            'id'         => $id,
            'token'      => $rawToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Valida um token raw e retorna dados do usuário/tenant.
     * Retorna null se inválido, expirado ou revogado.
     */
    public static function findByToken(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);

        $token = Database::selectFirst(
            "SELECT t.*, u.name as user_name, u.email as user_email,
                    COALESCE(tu.role, u.role, 'agent') as user_role
             FROM api_tokens t
             JOIN users u ON t.user_id = u.id
             LEFT JOIN tenant_user tu
                    ON tu.user_id = t.user_id
                   AND tu.tenant_id = t.tenant_id
             WHERE t.token_hash = ?
               AND t.revoked = 0
               AND t.expires_at > datetime('now')",
            [$hash]
        );

        return $token ?: null;
    }

    /**
     * Atualiza last_used_at e last_ip.
     */
    public static function touchLastUsed(string $tokenId): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        Database::execute(
            "UPDATE api_tokens SET last_used_at = datetime('now'), last_ip = ? WHERE id = ?",
            [$ip, $tokenId]
        );
    }

    /**
     * Revoga um token específico.
     */
    public static function revoke(string $tokenId): bool
    {
        return Database::execute(
            "UPDATE api_tokens SET revoked = 1 WHERE id = ?",
            [$tokenId]
        );
    }

    /**
     * Atualiza a empresa ativa do token atual.
     */
    public static function switchTenant(string $tokenId, string $tenantId): bool
    {
        return Database::execute(
            "UPDATE api_tokens
                SET tenant_id = ?, last_used_at = datetime('now')
              WHERE id = ? AND revoked = 0",
            [$tenantId, $tokenId]
        ) > 0;
    }

    /**
     * Revoga todos os tokens de um usuário.
     */
    public static function revokeAllForUser(string $userId): void
    {
        Database::execute(
            "UPDATE api_tokens SET revoked = 1 WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Lista tokens ativos de um usuário.
     */
    public static function listByUser(string $userId): array
    {
        return Database::select(
            "SELECT id, device_name, last_used_at, last_ip, created_at, expires_at
             FROM api_tokens
             WHERE user_id = ? AND revoked = 0 AND expires_at > datetime('now')
             ORDER BY created_at DESC",
            [$userId]
        );
    }
}
