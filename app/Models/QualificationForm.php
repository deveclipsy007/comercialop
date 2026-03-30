<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class QualificationForm
{
    // ─── ID ──────────────────────────────────────────────
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

    private static function generateSlug(): string
    {
        return bin2hex(random_bytes(8)); // 16-char hex slug
    }

    // ─── CRUD ────────────────────────────────────────────

    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM qualification_forms WHERE tenant_id = ? ORDER BY updated_at DESC',
            [$tenantId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM qualification_forms WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM qualification_forms WHERE public_slug = ? AND status = ?',
            [$slug, 'published']
        );
    }

    public static function create(string $tenantId, string $userId, array $data): string
    {
        $id = self::generateId();
        $slug = self::generateSlug();

        Database::execute(
            'INSERT INTO qualification_forms (id, tenant_id, created_by, title, description, status, public_slug, settings)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $userId,
                $data['title'] ?? 'Novo Formulário',
                $data['description'] ?? '',
                $data['status'] ?? 'draft',
                $slug,
                $data['settings'] ?? '{}',
            ]
        );

        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['title', 'description', 'status', 'settings'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }

        if (empty($fields)) return false;

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        $values[] = $tenantId;

        return Database::execute(
            'UPDATE qualification_forms SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $values
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM qualification_forms WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    public static function duplicate(string $id, string $tenantId, string $userId): ?string
    {
        $form = self::find($id, $tenantId);
        if (!$form) return null;

        $newId = self::create($tenantId, $userId, [
            'title' => $form['title'] . ' (cópia)',
            'description' => $form['description'],
            'status' => 'draft',
            'settings' => $form['settings'],
        ]);

        // Duplicate questions
        $questions = FormQuestion::allByForm($id, $tenantId);
        foreach ($questions as $q) {
            FormQuestion::create($tenantId, $newId, [
                'section_title' => $q['section_title'],
                'label' => $q['label'],
                'type' => $q['type'],
                'options' => $q['options'],
                'placeholder' => $q['placeholder'],
                'help_text' => $q['help_text'],
                'is_required' => $q['is_required'],
                'sort_order' => $q['sort_order'],
                'metadata' => $q['metadata'],
            ]);
        }

        return $newId;
    }

    public static function responseCount(string $formId, string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as cnt FROM form_responses WHERE form_id = ? AND tenant_id = ?',
            [$formId, $tenantId]
        );
        return (int)($row['cnt'] ?? 0);
    }
}
