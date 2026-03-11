<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Helpers;

class HunterSearch
{
    /**
     * @param array $data ['user_id', 'term', 'segment', 'location', 'filters']
     */
    public static function create(string $tenantId, array $data): string
    {
        $id = Helpers::uuid();
        $filtersJson = json_encode($data['filters'] ?? []);
        
        Database::execute(
            'INSERT INTO hunter_searches (id, tenant_id, user_id, term, segment, location, filters, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $data['user_id'],
                $data['term'] ?? null,
                $data['segment'] ?? null,
                $data['location'] ?? null,
                $filtersJson,
                'processing'
            ]
        );
        return $id;
    }

    public static function updateStatus(string $id, string $tenantId, string $status, ?string $message = null): void
    {
        Database::execute(
            'UPDATE hunter_searches SET status = ?, message = ?, updated_at = datetime("now") WHERE id = ? AND tenant_id = ?',
            [$status, $message, $id, $tenantId]
        );
    }

    public static function findById(string $id, string $tenantId): ?array
    {
        $row = Database::selectFirst('SELECT * FROM hunter_searches WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($row) {
            $row['filters'] = json_decode($row['filters'] ?? '[]', true);
        }
        return $row;
    }
    
    public static function recentByTenant(string $tenantId, int $limit = 20): array
    {
        return Database::select('SELECT * FROM hunter_searches WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?', [$tenantId, $limit]);
    }
}
