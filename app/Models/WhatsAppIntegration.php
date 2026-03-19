<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class WhatsAppIntegration
{
    public static function findByTenant(string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM whatsapp_integrations WHERE tenant_id = ? AND active = 1',
            [$tenantId]
        );
    }

    public static function upsert(string $tenantId, array $data): string
    {
        $existing = self::findByTenant($tenantId);
        
        if ($existing) {
            Database::execute(
                'UPDATE whatsapp_integrations SET 
                    instance_name = ?, 
                    base_url = ?, 
                    api_key = ?, 
                    updated_at = datetime("now") 
                 WHERE id = ?',
                [
                    $data['instance_name'],
                    $data['base_url'],
                    $data['api_key'],
                    $existing['id']
                ]
            );
            return $existing['id'];
        }

        $id = bin2hex(random_bytes(8));
        Database::execute(
            'INSERT INTO whatsapp_integrations (id, tenant_id, instance_name, base_url, api_key, status)
             VALUES (?, ?, ?, ?, ?, "disconnected")',
            [
                $id,
                $tenantId,
                $data['instance_name'],
                $data['base_url'],
                $data['api_key']
            ]
        );
        return $id;
    }

    public static function updateStatus(string $id, string $status): bool
    {
        return Database::execute(
            'UPDATE whatsapp_integrations SET status = ?, updated_at = datetime("now") WHERE id = ?',
            [$status, $id]
        ) > 0;
    }

    public static function updateSyncTime(string $id): bool
    {
        return Database::execute(
            'UPDATE whatsapp_integrations SET last_sync_at = datetime("now") WHERE id = ?',
            [$id]
        ) > 0;
    }
    public static function delete(string $id): bool
    {
        return Database::execute(
            'DELETE FROM whatsapp_integrations WHERE id = ?',
            [$id]
        ) > 0;
    }
}
