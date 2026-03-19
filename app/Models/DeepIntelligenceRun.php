<?php
namespace App\Models;

use App\Core\Database;

class DeepIntelligenceRun
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    public static function create(string $tenantId, string $leadId, string $type, string $userId): string
    {
        Database::execute(
            'INSERT INTO lead_deep_intelligence_runs (tenant_id, lead_id, intelligence_type, status, requested_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tenantId, $leadId, $type, self::STATUS_PENDING, $userId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        return Database::lastInsertId();
    }

    public static function updateStatus(string $id, string $status, array $extra = []): void
    {
        $sets = ['status = ?', 'updated_at = ?'];
        $params = [$status, date('Y-m-d H:i:s')];

        if ($status === self::STATUS_COMPLETED || $status === self::STATUS_FAILED) {
            $sets[] = 'completed_at = ?';
            $params[] = date('Y-m-d H:i:s');
        }

        foreach ($extra as $col => $val) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }

        $params[] = $id;
        Database::execute(
            'UPDATE lead_deep_intelligence_runs SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }

    public static function findByLead(string $leadId, string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM lead_deep_intelligence_runs WHERE lead_id = ? AND tenant_id = ? ORDER BY created_at DESC',
            [$leadId, $tenantId]
        );
    }
}
