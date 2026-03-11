<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Helpers;

class HunterList
{
    public static function create(string $tenantId, string $name, string $color = '#A1A1AA'): string
    {
        $id = Helpers::uuid();
        
        Database::execute(
            'INSERT INTO hunter_lists (id, tenant_id, name, color) VALUES (?, ?, ?, ?)',
            [$id, $tenantId, $name, $color]
        );
        return $id;
    }

    public static function getByTenant(string $tenantId): array
    {
        return Database::select('SELECT * FROM hunter_lists WHERE tenant_id = ? ORDER BY name ASC', [$tenantId]);
    }

    public static function getItems(string $listId, string $tenantId): array
    {
        // Join with hunter_results
        return Database::select(
            'SELECT r.* FROM hunter_results r
             INNER JOIN hunter_list_items li ON li.hunter_result_id = r.id
             WHERE li.list_id = ? AND r.tenant_id = ?
             ORDER BY li.created_at DESC',
            [$listId, $tenantId]
        );
    }

    public static function addItem(string $listId, string $resultId, string $tenantId): void
    {
        // Ensure result belongs to tenant
        $result = Database::selectFirst('SELECT id FROM hunter_results WHERE id = ? AND tenant_id = ?', [$resultId, $tenantId]);
        if (!$result) return;
        
        $id = Helpers::uuid();
        Database::execute(
            'INSERT OR IGNORE INTO hunter_list_items (id, tenant_id, list_id, hunter_result_id) VALUES (?, ?, ?, ?)',
            [$id, $tenantId, $listId, $resultId]
        );
    }

    public static function removeItem(string $listId, string $resultId, string $tenantId): void
    {
        Database::execute(
            'DELETE FROM hunter_list_items WHERE list_id = ? AND hunter_result_id = ? AND tenant_id = ?',
            [$listId, $resultId, $tenantId]
        );
    }
}
