<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Helpers;

class HunterPreset
{
    public static function create(string $tenantId, string $userId, string $name, array $filters, bool $isDefault = false): string
    {
        $id = Helpers::uuid();
        $filtersJson = json_encode($filters);
        $defaultVal = $isDefault ? 1 : 0;
        
        Database::execute(
            'INSERT INTO hunter_presets (id, tenant_id, user_id, name, filters, is_default) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $tenantId, $userId, $name, $filtersJson, $defaultVal]
        );
        return $id;
    }

    public static function getByTenant(string $tenantId): array
    {
        $rows = Database::select('SELECT * FROM hunter_presets WHERE tenant_id = ? ORDER BY name ASC', [$tenantId]);
        foreach ($rows as &$r) {
            $r['filters'] = json_decode($r['filters'] ?? '[]', true);
        }
        return $rows;
    }

    public static function delete(string $id, string $tenantId): void
    {
        Database::execute('DELETE FROM hunter_presets WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
}
