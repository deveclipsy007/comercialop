<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EmailTemplate
{
    private static function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public const CATEGORIES = [
        'prospecting' => ['label' => 'Prospecção', 'icon' => 'person_search', 'color' => '#E1FB15'],
        'follow_up'   => ['label' => 'Follow-up', 'icon' => 'replay', 'color' => '#32D583'],
        'proposal'    => ['label' => 'Proposta', 'icon' => 'description', 'color' => '#3B82F6'],
        'onboarding'  => ['label' => 'Onboarding', 'icon' => 'handshake', 'color' => '#F59E0B'],
        'reactivation' => ['label' => 'Reativação', 'icon' => 'autorenew', 'color' => '#EF4444'],
        'custom'      => ['label' => 'Personalizado', 'icon' => 'edit_note', 'color' => '#A1A1AA'],
    ];

    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM email_templates WHERE tenant_id = ? ORDER BY updated_at DESC',
            [$tenantId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM email_templates WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function create(string $tenantId, string $userId, array $data): string
    {
        $id = self::generateId();
        Database::execute(
            'INSERT INTO email_templates (id, tenant_id, created_by, name, subject, body, category, variables)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $userId,
                $data['name'] ?? 'Novo Template',
                $data['subject'] ?? '',
                $data['body'] ?? '',
                $data['category'] ?? 'custom',
                json_encode($data['variables'] ?? []),
            ]
        );
        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $fields = [];
        $values = [];
        foreach (['name', 'subject', 'body', 'category', 'variables', 'is_shared'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = is_array($data[$col]) ? json_encode($data[$col]) : $data[$col];
            }
        }
        if (empty($fields)) return false;

        $fields[] = "updated_at = datetime('now')";
        $values[] = $id;
        $values[] = $tenantId;

        return Database::execute(
            'UPDATE email_templates SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $values
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM email_templates WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    public static function incrementUseCount(string $id): void
    {
        Database::execute(
            "UPDATE email_templates SET use_count = use_count + 1 WHERE id = ?",
            [$id]
        );
    }
}
