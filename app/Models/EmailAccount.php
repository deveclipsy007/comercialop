<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EmailAccount
{
    private static function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM email_accounts WHERE tenant_id = ? ORDER BY created_at DESC',
            [$tenantId]
        );
    }

    public static function allByUser(string $tenantId, string $userId): array
    {
        return Database::select(
            'SELECT * FROM email_accounts WHERE tenant_id = ? AND user_id = ? ORDER BY created_at DESC',
            [$tenantId, $userId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM email_accounts WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function findActive(string $tenantId, string $userId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM email_accounts WHERE tenant_id = ? AND user_id = ? AND is_active = 1 AND is_verified = 1 ORDER BY created_at DESC LIMIT 1',
            [$tenantId, $userId]
        );
    }

    public static function create(string $tenantId, string $userId, array $data): string
    {
        $id = self::generateId();
        Database::execute(
            'INSERT INTO email_accounts (id, tenant_id, user_id, email_address, display_name, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, daily_limit, hourly_limit, delay_seconds)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $userId,
                $data['email_address'] ?? '',
                $data['display_name'] ?? '',
                $data['smtp_host'] ?? '',
                (int)($data['smtp_port'] ?? 587),
                $data['smtp_encryption'] ?? 'tls',
                $data['smtp_username'] ?? '',
                $data['smtp_password'] ?? '',
                (int)($data['daily_limit'] ?? 50),
                (int)($data['hourly_limit'] ?? 15),
                (int)($data['delay_seconds'] ?? 30),
            ]
        );
        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowed = ['email_address', 'display_name', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'daily_limit', 'hourly_limit', 'delay_seconds', 'warmup_enabled', 'is_active', 'is_verified', 'sent_today', 'sent_this_hour', 'last_sent_at', 'last_reset_date', 'reputation_score', 'warmup_day', 'settings'];

        foreach ($allowed as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }
        if (empty($fields)) return false;

        $fields[] = "updated_at = datetime('now')";
        $values[] = $id;
        $values[] = $tenantId;

        return Database::execute(
            'UPDATE email_accounts SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $values
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM email_accounts WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    /**
     * Check if account can send right now (rate limits + warmup).
     */
    public static function canSend(string $id, string $tenantId): array
    {
        $account = self::find($id, $tenantId);
        if (!$account) return ['allowed' => false, 'reason' => 'Conta não encontrada.'];
        if (!$account['is_active']) return ['allowed' => false, 'reason' => 'Conta desativada.'];
        if (!$account['is_verified']) return ['allowed' => false, 'reason' => 'Conta não verificada.'];

        // Reset daily/hourly counters if needed
        $today = date('Y-m-d');
        if (($account['last_reset_date'] ?? '') !== $today) {
            Database::execute(
                'UPDATE email_accounts SET sent_today = 0, sent_this_hour = 0, last_reset_date = ? WHERE id = ?',
                [$today, $id]
            );
            $account['sent_today'] = 0;
            $account['sent_this_hour'] = 0;
        }

        // Warmup: progressive daily limit
        $effectiveDaily = $account['daily_limit'];
        if ($account['warmup_enabled'] && $account['warmup_day'] < 30) {
            $warmupLimits = [5, 8, 12, 16, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100, 110, 120, 130, 140, 150, 160, 170, 180, 200];
            $day = min($account['warmup_day'], 29);
            $effectiveDaily = min($warmupLimits[$day], $account['daily_limit']);
        }

        if ($account['sent_today'] >= $effectiveDaily) {
            return ['allowed' => false, 'reason' => "Limite diário atingido ({$effectiveDaily} e-mails)."];
        }
        if ($account['sent_this_hour'] >= $account['hourly_limit']) {
            return ['allowed' => false, 'reason' => "Limite por hora atingido ({$account['hourly_limit']})."];
        }

        // Delay between sends
        if ($account['last_sent_at']) {
            $elapsed = time() - strtotime($account['last_sent_at']);
            if ($elapsed < $account['delay_seconds']) {
                $wait = $account['delay_seconds'] - $elapsed;
                return ['allowed' => false, 'reason' => "Aguarde {$wait}s entre envios."];
            }
        }

        return ['allowed' => true, 'effective_daily_limit' => $effectiveDaily, 'sent_today' => $account['sent_today']];
    }

    /**
     * Increment send counters after successful send.
     */
    public static function recordSend(string $id): void
    {
        Database::execute(
            "UPDATE email_accounts SET sent_today = sent_today + 1, sent_this_hour = sent_this_hour + 1, last_sent_at = datetime('now'), warmup_day = CASE WHEN warmup_enabled = 1 AND warmup_day < 30 THEN warmup_day + 1 ELSE warmup_day END, updated_at = datetime('now') WHERE id = ?",
            [$id]
        );
    }
}
