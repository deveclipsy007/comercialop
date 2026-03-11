<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Helpers;

class HunterResult
{
    /**
     * @param array $data ['search_id', 'name', 'segment', 'address', 'city', 'phone', 'website', 'email', 'instagram', 'google_rating', 'google_reviews', 'data_source', 'raw_source_data']
     */
    public static function create(string $tenantId, array $data): string
    {
        $id = Helpers::uuid();
        
        Database::execute(
            'INSERT INTO hunter_results (
                id, tenant_id, search_id, name, segment, address, city, phone, website, email, 
                instagram, google_rating, google_reviews, data_source, raw_source_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $data['search_id'] ?? null,
                $data['name'],
                $data['segment'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['phone'] ?? null,
                $data['website'] ?? null,
                $data['email'] ?? null,
                $data['instagram'] ?? null,
                $data['google_rating'] ?? null,
                $data['google_reviews'] ?? null,
                $data['data_source'] ?? 'manual',
                is_array($data['raw_source_data'] ?? null) ? json_encode($data['raw_source_data']) : ($data['raw_source_data'] ?? null)
            ]
        );
        return $id;
    }

    public static function findById(string $id, string $tenantId): ?array
    {
        return Database::selectFirst('SELECT * FROM hunter_results WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
    
    public static function getBySearchId(string $searchId, string $tenantId): array
    {
        return Database::select('SELECT * FROM hunter_results WHERE search_id = ? AND tenant_id = ? ORDER BY created_at ASC', [$searchId, $tenantId]);
    }

    public static function getSaved(string $tenantId): array
    {
        return Database::select('SELECT * FROM hunter_results WHERE tenant_id = ? AND is_saved = 1 ORDER BY created_at DESC', [$tenantId]);
    }

    public static function toggleSave(string $id, string $tenantId, bool $saving = true): void
    {
        $val = $saving ? 1 : 0;
        Database::execute('UPDATE hunter_results SET is_saved = ?, updated_at = datetime("now") WHERE id = ? AND tenant_id = ?', [$val, $id, $tenantId]);
    }
    
    public static function markImported(string $id, string $tenantId, string $leadId): void
    {
        Database::execute(
            'UPDATE hunter_results SET is_imported = 1, imported_lead_id = ?, updated_at = datetime("now") WHERE id = ? AND tenant_id = ?', 
            [$leadId, $id, $tenantId]
        );
    }
    
    public static function delete(string $id, string $tenantId): void
    {
        Database::execute('DELETE FROM hunter_results WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
}
