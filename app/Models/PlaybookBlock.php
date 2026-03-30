<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class PlaybookBlock
{
    public const TYPES = [
        'text'      => ['label' => 'Texto / Instrução',  'icon' => 'article'],
        'video'     => ['label' => 'Vídeo',              'icon' => 'play_circle'],
        'document'  => ['label' => 'Documento / Link',   'icon' => 'description'],
        'checklist' => ['label' => 'Checklist',           'icon' => 'checklist'],
        'script'    => ['label' => 'Script / Template',   'icon' => 'code'],
        'tip'       => ['label' => 'Dica / Boa Prática', 'icon' => 'lightbulb'],
    ];

    public static function allByModule(string $moduleId, string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM playbook_blocks WHERE module_id = ? AND tenant_id = ? ORDER BY sort_order ASC, created_at ASC',
            [$moduleId, $tenantId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM playbook_blocks WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function create(string $tenantId, string $moduleId, array $data): string
    {
        $id = self::generateId();
        $maxOrder = Database::selectFirst(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM playbook_blocks WHERE module_id = ? AND tenant_id = ?',
            [$moduleId, $tenantId]
        );

        Database::execute(
            "INSERT INTO playbook_blocks (id, tenant_id, module_id, type, title, content, metadata, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            [
                $id,
                $tenantId,
                $moduleId,
                $data['type'] ?? 'text',
                $data['title'] ?? '',
                $data['content'] ?? '',
                json_encode(json_decode($data['metadata'] ?? '{}', true) ?: [], JSON_UNESCAPED_UNICODE),
                (int) ($data['sort_order'] ?? $maxOrder['next_order'] ?? 0),
            ]
        );

        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach (['type', 'title', 'content'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (array_key_exists('metadata', $data)) {
            $fields[] = 'metadata = ?';
            $params[] = json_encode(json_decode($data['metadata'], true) ?: [], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('sort_order', $data)) {
            $fields[] = 'sort_order = ?';
            $params[] = (int) $data['sort_order'];
        }

        if (empty($fields)) return false;

        $fields[] = "updated_at = datetime('now')";
        $params[] = $id;
        $params[] = $tenantId;

        return Database::execute(
            'UPDATE playbook_blocks SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $params
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM playbook_blocks WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    public static function reorder(string $moduleId, string $tenantId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $blockId) {
            Database::execute(
                'UPDATE playbook_blocks SET sort_order = ? WHERE id = ? AND module_id = ? AND tenant_id = ?',
                [$index, $blockId, $moduleId, $tenantId]
            );
        }
    }

    public static function countByModule(string $moduleId, string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as total FROM playbook_blocks WHERE module_id = ? AND tenant_id = ?',
            [$moduleId, $tenantId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private static function generateId(): string
    {
        return 'pbb_' . bin2hex(random_bytes(12));
    }
}
