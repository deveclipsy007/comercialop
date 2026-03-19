<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Script de abordagem persistido — permite histórico e refinamento iterativo.
 */
class ApproachScript
{
    public static function findById(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM approach_scripts WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function latestForLead(string $leadId, string $tenantId, string $channel = 'whatsapp'): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM approach_scripts WHERE lead_id = ? AND tenant_id = ? AND channel = ? ORDER BY created_at DESC LIMIT 1',
            [$leadId, $tenantId, $channel]
        );
    }

    public static function historyForLead(string $leadId, string $tenantId, int $limit = 10): array
    {
        return Database::select(
            'SELECT * FROM approach_scripts WHERE lead_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT ?',
            [$leadId, $tenantId, $limit]
        );
    }

    public static function insert(string $tenantId, array $data): string
    {
        $id = self::generateId();

        Database::execute(
            "INSERT INTO approach_scripts (id, tenant_id, lead_id, channel, tone, script, context, version, parent_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [
                $id,
                $tenantId,
                $data['lead_id'] ?? null,
                $data['channel'] ?? 'whatsapp',
                $data['tone'] ?? 'consultivo',
                $data['script'] ?? '',
                json_encode($data['context'] ?? []),
                (int)($data['version'] ?? 1),
                $data['parent_id'] ?? null,
            ]
        );

        return $id;
    }

    private static function generateId(): string
    {
        return sprintf(
            'as_%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
