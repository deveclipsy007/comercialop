<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class AgendaEvent
{
    public static function create(array $data): string
    {
        $id = bin2hex(random_bytes(8));
        Database::execute(
            'INSERT INTO agenda_events (id, tenant_id, user_id, title, description, event_type, start_time, end_time, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))',
            [
                $id,
                $data['tenant_id'],
                $data['user_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['event_type'] ?? 'reminder',
                $data['start_time'],
                $data['end_time'] ?? null,
            ]
        );
        return $id;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM agenda_events WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM agenda_events WHERE tenant_id = ? ORDER BY start_time ASC',
            [$tenantId]
        );
    }
}
