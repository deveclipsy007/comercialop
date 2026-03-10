<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class TokenQuota
{
    public static function getByTenant(string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM token_quotas WHERE tenant_id = ?',
            [$tenantId]
        );
    }

    public static function getOrCreate(string $tenantId, string $tier = 'starter'): array
    {
        $quota = self::getByTenant($tenantId);
        if ($quota) return $quota;

        $limits      = config('operon.token_limits');
        $dailyLimit  = $limits[$tier] ?? 100;
        $tz          = config('operon.token_timezone', 'America/Sao_Paulo');
        $resetAt     = (new \DateTime('tomorrow midnight', new \DateTimeZone($tz)))->format('Y-m-d H:i:s');

        Database::execute(
            'INSERT INTO token_quotas (tenant_id, tier, tokens_used, tokens_limit, reset_at)
             VALUES (?, ?, 0, ?, ?)',
            [$tenantId, $tier, $dailyLimit, $resetAt]
        );

        return self::getByTenant($tenantId);
    }

    public static function consume(string $tenantId, int $cost): bool
    {
        $quota = self::getOrCreate($tenantId);

        // Verificar reset
        $tz  = new \DateTimeZone(config('operon.token_timezone', 'America/Sao_Paulo'));
        $now = new \DateTime('now', $tz);

        if ($now >= new \DateTime($quota['reset_at'], $tz)) {
            // Reset diário
            $resetAt = (new \DateTime('tomorrow midnight', $tz))->format('Y-m-d H:i:s');
            Database::execute(
                'UPDATE token_quotas SET tokens_used = 0, reset_at = ? WHERE tenant_id = ?',
                [$resetAt, $tenantId]
            );
            $quota['tokens_used'] = 0;
        }

        // Verificar saldo
        if ((int) $quota['tokens_used'] + $cost > (int) $quota['tokens_limit']) {
            return false;
        }

        // Debitar
        Database::execute(
            'UPDATE token_quotas SET tokens_used = tokens_used + ? WHERE tenant_id = ?',
            [$cost, $tenantId]
        );

        return true;
    }

    public static function getBalance(string $tenantId): array
    {
        $quota = self::getOrCreate($tenantId);
        $used  = (int) $quota['tokens_used'];
        $limit = (int) $quota['tokens_limit'];
        $remaining = max(0, $limit - $used);
        $percent   = $limit > 0 ? round(($used / $limit) * 100) : 0;

        return [
            'used'      => $used,
            'limit'     => $limit,
            'remaining' => $remaining,
            'percent'   => $percent,
            'tier'      => $quota['tier'],
            'status'    => $remaining === 0 ? 'depleted' : ($percent >= 80 ? 'warning' : 'ok'),
            'reset_at'  => $quota['reset_at'],
        ];
    }

    public static function logEntry(string $tenantId, string $operation, int $cost, array $meta = []): void
    {
        Database::execute(
            'INSERT INTO token_entries (tenant_id, operation, tokens_consumed, meta, created_at)
             VALUES (?, ?, ?, ?, datetime("now"))',
            [
                $tenantId,
                $operation,
                $cost,
                json_encode($meta),
            ]
        );
    }

    public static function recentEntries(string $tenantId, int $limit = 20): array
    {
        return Database::select(
            'SELECT * FROM token_entries WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?',
            [$tenantId, $limit]
        );
    }
}
