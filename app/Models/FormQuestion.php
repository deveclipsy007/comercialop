<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class FormQuestion
{
    /** Tipos de campo suportados */
    public const TYPES = [
        'short_text'      => ['label' => 'Texto Curto',       'icon' => 'short_text'],
        'long_text'       => ['label' => 'Texto Longo',       'icon' => 'notes'],
        'single_choice'   => ['label' => 'Escolha Única',     'icon' => 'radio_button_checked'],
        'multiple_choice' => ['label' => 'Múltipla Escolha',  'icon' => 'check_box'],
        'number'          => ['label' => 'Número',            'icon' => 'tag'],
        'date'            => ['label' => 'Data',              'icon' => 'calendar_today'],
        'select'          => ['label' => 'Seleção (Dropdown)','icon' => 'arrow_drop_down_circle'],
        'checkbox'        => ['label' => 'Checkbox (Sim/Não)','icon' => 'toggle_on'],
        'rating'          => ['label' => 'Avaliação (1-5)',   'icon' => 'star'],
        'email'           => ['label' => 'E-mail',            'icon' => 'mail'],
        'phone'           => ['label' => 'Telefone',          'icon' => 'phone'],
        'url'             => ['label' => 'URL / Link',        'icon' => 'link'],
    ];

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

    public static function allByForm(string $formId, string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM form_questions WHERE form_id = ? AND tenant_id = ? ORDER BY sort_order ASC, created_at ASC',
            [$formId, $tenantId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM form_questions WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function create(string $tenantId, string $formId, array $data): string
    {
        $id = self::generateId();

        Database::execute(
            'INSERT INTO form_questions (id, form_id, tenant_id, section_title, label, type, options, placeholder, help_text, is_required, sort_order, metadata)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $formId,
                $tenantId,
                $data['section_title'] ?? '',
                $data['label'] ?? '',
                $data['type'] ?? 'short_text',
                is_array($data['options'] ?? null) ? json_encode($data['options']) : ($data['options'] ?? '[]'),
                $data['placeholder'] ?? '',
                $data['help_text'] ?? '',
                (int)($data['is_required'] ?? 0),
                (int)($data['sort_order'] ?? 0),
                is_array($data['metadata'] ?? null) ? json_encode($data['metadata']) : ($data['metadata'] ?? '{}'),
            ]
        );

        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['section_title', 'label', 'type', 'placeholder', 'help_text'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }

        if (isset($data['options'])) {
            $fields[] = "options = ?";
            $values[] = is_array($data['options']) ? json_encode($data['options']) : $data['options'];
        }
        if (isset($data['metadata'])) {
            $fields[] = "metadata = ?";
            $values[] = is_array($data['metadata']) ? json_encode($data['metadata']) : $data['metadata'];
        }
        if (isset($data['is_required'])) {
            $fields[] = "is_required = ?";
            $values[] = (int)$data['is_required'];
        }
        if (isset($data['sort_order'])) {
            $fields[] = "sort_order = ?";
            $values[] = (int)$data['sort_order'];
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $values[] = $tenantId;

        return Database::execute(
            'UPDATE form_questions SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $values
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM form_questions WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    public static function deleteAllByForm(string $formId, string $tenantId): int
    {
        return Database::execute(
            'DELETE FROM form_questions WHERE form_id = ? AND tenant_id = ?',
            [$formId, $tenantId]
        );
    }

    public static function reorder(string $formId, string $tenantId, array $ids): void
    {
        foreach ($ids as $i => $qId) {
            Database::execute(
                'UPDATE form_questions SET sort_order = ? WHERE id = ? AND form_id = ? AND tenant_id = ?',
                [$i, $qId, $formId, $tenantId]
            );
        }
    }
}
