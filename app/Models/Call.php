<?php

namespace App\Models;

use App\Core\Database;

class Call
{
    public const STATUS_UPLOADING    = 'uploading';
    public const STATUS_STORED       = 'stored';
    public const STATUS_TRANSCRIBING = 'transcribing';
    public const STATUS_TRANSCRIBED  = 'transcribed';
    public const STATUS_ANALYZING    = 'analyzing';
    public const STATUS_COMPLETED    = 'completed';
    public const STATUS_FAILED       = 'failed';

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::insert('calls', $data);
    }

    public static function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('calls', $data, 'id = ?', [$id]);
    }

    public static function findById(int $id, string $tenantId): ?array
    {
        $result = Database::select('SELECT * FROM calls WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        return $result ? $result[0] : null;
    }

    public static function findByLead(int $leadId, string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM calls WHERE lead_id = ? AND tenant_id = ? ORDER BY created_at DESC',
            [$leadId, $tenantId]
        );
    }

    public static function updateStatus(int $id, string $status, array $extra = []): bool
    {
        $data = array_merge(['status' => $status], $extra);
        return self::update($id, $data);
    }
}
