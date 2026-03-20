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
        $row = Database::selectFirst('SELECT * FROM hunter_results WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        return $row ? self::hydrate($row) : null;
    }
    
    public static function getBySearchId(string $searchId, string $tenantId): array
    {
        return array_map(
            [self::class, 'hydrate'],
            Database::select('SELECT * FROM hunter_results WHERE search_id = ? AND tenant_id = ? ORDER BY created_at ASC', [$searchId, $tenantId])
        );
    }

    public static function getSaved(string $tenantId): array
    {
        return array_map(
            [self::class, 'hydrate'],
            Database::select('SELECT * FROM hunter_results WHERE tenant_id = ? AND is_saved = 1 ORDER BY created_at DESC', [$tenantId])
        );
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $allowed = [
            'name', 'segment', 'address', 'city', 'phone', 'website', 'email', 'instagram',
            'google_rating', 'google_reviews', 'data_source', 'raw_source_data',
            'is_saved', 'is_imported', 'imported_lead_id',
        ];

        $sets = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }

            $sets[] = $field . ' = ?';
            $params[] = $field === 'raw_source_data' && is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : $value;
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "updated_at = datetime('now')";
        $params[] = $id;
        $params[] = $tenantId;

        return Database::execute(
            'UPDATE hunter_results SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?',
            $params
        ) > 0;
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

    private static function hydrate(array $row): array
    {
        $raw = $row['raw_source_data'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true) ?: [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $row['raw_source_data'] = $raw;
        $row['place_id'] = $raw['place_id'] ?? null;
        $row['google_maps_url'] = $raw['google_maps_url'] ?? null;
        $row['category'] = $raw['category'] ?? ($row['segment'] ?? null);
        $row['state'] = $raw['state'] ?? null;
        $row['status'] = $raw['status'] ?? null;
        $row['status_label'] = $raw['status_label'] ?? null;
        $row['open_now'] = $raw['open_now'] ?? null;
        $row['opening_hours'] = is_array($raw['opening_hours'] ?? null) ? $raw['opening_hours'] : [];
        $row['opening_hours_text'] = $raw['opening_hours_text'] ?? null;
        $row['verification'] = is_array($raw['verification'] ?? null) ? $raw['verification'] : [];
        $row['field_statuses'] = is_array($raw['field_statuses'] ?? null) ? $raw['field_statuses'] : [];
        $row['digital_presence'] = is_array($raw['digital_presence'] ?? null) ? $raw['digital_presence'] : [];
        $row['import_notes'] = is_array($raw['import_notes'] ?? null) ? $raw['import_notes'] : [];
        $row['website_scan'] = is_array($raw['website_scan'] ?? null) ? $raw['website_scan'] : [];
        $row['chain_signals'] = is_array($raw['chain_signals'] ?? null) ? $raw['chain_signals'] : [];

        return $row;
    }
}
