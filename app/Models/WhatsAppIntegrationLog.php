<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class WhatsAppIntegrationLog
{
    public const DIR_INBOUND = 'inbound';
    public const DIR_OUTBOUND = 'outbound';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    public static function log(string $tenantId, ?string $integrationId, string $operation, string $direction, string $status, ?array $payload = null): bool
    {
        return Database::execute(
            'INSERT INTO whatsapp_integration_logs (id, tenant_id, integration_id, operation, direction, status, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                bin2hex(random_bytes(8)),
                $tenantId,
                $integrationId,
                $operation,
                $direction,
                $status,
                $payload ? json_encode($payload) : null
            ]
        ) > 0;
    }

    public static function recentByTenant(string $tenantId, int $limit = 10): array
    {
        return Database::select(
            'SELECT * FROM whatsapp_integration_logs WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?',
            [$tenantId, $limit]
        );
    }
}
