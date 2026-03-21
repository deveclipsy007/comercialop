<?php

declare(strict_types=1);

namespace App\Services\Extension;

use App\Core\Database;
use App\Core\Helpers;
use App\Models\ApiToken;
use App\Models\User;

class ExtensionAuthService
{
    /**
     * Autentica o usuário e gera um Bearer token.
     * Retorna null se credenciais inválidas.
     */
    public static function authenticate(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);

        if (!$user) return null;
        if (!password_verify($password, $user['password'])) return null;
        if (empty($user['active'])) return null;

        $tokenData = ApiToken::create($user['id'], $user['tenant_id']);

        return [
            'token'      => $tokenData['token'],
            'expires_at' => $tokenData['expires_at'],
            'user'       => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'] ?? 'agent',
            ],
            'tenant_id'  => $user['tenant_id'],
        ];
    }

    /**
     * Resolve um Bearer token e retorna contexto de autenticação.
     */
    public static function resolveFromBearerToken(string $rawToken): ?array
    {
        $token = ApiToken::findByToken($rawToken);
        if (!$token) return null;

        ApiToken::touchLastUsed($token['id']);

        return [
            'token_id'  => $token['id'],
            'user_id'   => $token['user_id'],
            'tenant_id' => $token['tenant_id'],
            'user_name' => $token['user_name'],
            'email'     => $token['user_email'],
            'role'      => $token['user_role'],
        ];
    }

    /**
     * Revoga o token (logout).
     */
    public static function logout(string $tokenId): void
    {
        ApiToken::revoke($tokenId);
    }

    /**
     * Verifica rate limit para tentativas de login.
     * Retorna true se dentro do limite.
     */
    public static function checkRateLimit(string $ip, string $endpoint, int $maxAttempts = 5, int $windowMinutes = 15): bool
    {
        $row = Database::selectFirst(
            "SELECT COUNT(*) as cnt FROM rate_limit_log
             WHERE ip_address = ? AND endpoint = ?
             AND attempted_at > datetime('now', ?)",
            [$ip, $endpoint, "-{$windowMinutes} minutes"]
        );

        return ((int)($row['cnt'] ?? 0)) < $maxAttempts;
    }

    /**
     * Registra uma tentativa de acesso.
     */
    public static function logAttempt(string $ip, string $endpoint): void
    {
        Database::execute(
            "INSERT INTO rate_limit_log (id, ip_address, endpoint) VALUES (?, ?, ?)",
            [Helpers::uuid(), $ip, $endpoint]
        );
    }

    /**
     * Limpa logs antigos de rate limit (manutenção).
     */
    public static function cleanOldLogs(): void
    {
        Database::execute(
            "DELETE FROM rate_limit_log WHERE attempted_at < datetime('now', '-1 day')"
        );
    }
}
