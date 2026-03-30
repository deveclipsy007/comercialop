<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class PlaybookModule
{
    public static function allByTenant(string $tenantId, bool $publishedOnly = false): array
    {
        $sql = 'SELECT * FROM playbook_modules WHERE tenant_id = ?';
        if ($publishedOnly) {
            $sql .= ' AND is_published = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, created_at ASC';

        return Database::select($sql, [$tenantId]);
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM playbook_modules WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function create(string $tenantId, array $data): string
    {
        $id = self::generateId();
        $maxOrder = Database::selectFirst(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM playbook_modules WHERE tenant_id = ?',
            [$tenantId]
        );

        Database::execute(
            "INSERT INTO playbook_modules (id, tenant_id, title, description, icon, color, sort_order, is_published, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            [
                $id,
                $tenantId,
                $data['title'] ?? 'Novo Módulo',
                $data['description'] ?? '',
                $data['icon'] ?? 'menu_book',
                $data['color'] ?? '#E1FB15',
                (int) ($data['sort_order'] ?? $maxOrder['next_order'] ?? 0),
                (int) ($data['is_published'] ?? 0),
            ]
        );

        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach (['title', 'description', 'icon', 'color'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (array_key_exists('sort_order', $data)) {
            $fields[] = 'sort_order = ?';
            $params[] = (int) $data['sort_order'];
        }

        if (array_key_exists('is_published', $data)) {
            $fields[] = 'is_published = ?';
            $params[] = (int) $data['is_published'];
        }

        if (empty($fields)) return false;

        $fields[] = "updated_at = datetime('now')";
        $params[] = $id;
        $params[] = $tenantId;

        return Database::execute(
            'UPDATE playbook_modules SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $params
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM playbook_modules WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    public static function reorder(string $tenantId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $moduleId) {
            Database::execute(
                'UPDATE playbook_modules SET sort_order = ? WHERE id = ? AND tenant_id = ?',
                [$index, $moduleId, $tenantId]
            );
        }
    }

    private static function generateId(): string
    {
        return 'pbm_' . bin2hex(random_bytes(12));
    }
}
