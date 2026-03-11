<?php
namespace App\Models;

use App\Core\Database;

class DeepIntelligenceRun
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    public static function create(string $tenantId, int $leadId, string $type, int $userId): int
    {
        return Database::insert('lead_deep_intelligence_runs', [
            'tenant_id'         => $tenantId,
            'lead_id'           => $leadId,
            'intelligence_type' => $type,
            'status'            => self::STATUS_PENDING,
            'requested_by'      => $userId,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s')
        ]);
    }

    public static function updateStatus(int $id, string $status, array $extra = []): void
    {
        $data = array_merge(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], $extra);
        if ($status === self::STATUS_COMPLETED || $status === self::STATUS_FAILED) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        Database::update('lead_deep_intelligence_runs', $data, 'id = ?', [$id]);
    }

    public static function findByLead(int $leadId, string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM lead_deep_intelligence_runs WHERE lead_id = ? AND tenant_id = ? ORDER BY created_at DESC',
            [$leadId, $tenantId]
        );
    }
}
