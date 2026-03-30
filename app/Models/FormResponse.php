<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class FormResponse
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

    public static function allByForm(string $formId, string $tenantId): array
    {
        return Database::select(
            'SELECT fr.*, l.name as lead_name, l.company as lead_company
             FROM form_responses fr
             LEFT JOIN leads l ON l.id = fr.lead_id
             WHERE fr.form_id = ? AND fr.tenant_id = ?
             ORDER BY fr.created_at DESC',
            [$formId, $tenantId]
        );
    }

    public static function allByLead(string $leadId, string $tenantId): array
    {
        return Database::select(
            'SELECT fr.*, qf.title as form_title
             FROM form_responses fr
             JOIN qualification_forms qf ON qf.id = fr.form_id
             WHERE fr.lead_id = ? AND fr.tenant_id = ?
             ORDER BY fr.created_at DESC',
            [$leadId, $tenantId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM form_responses WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function create(string $tenantId, array $data): string
    {
        $id = self::generateId();

        Database::execute(
            'INSERT INTO form_responses (id, form_id, tenant_id, lead_id, filled_by, respondent_name, respondent_email, answers, source, score, ai_summary)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $data['form_id'],
                $tenantId,
                $data['lead_id'] ?? null,
                $data['filled_by'] ?? null,
                $data['respondent_name'] ?? '',
                $data['respondent_email'] ?? '',
                is_array($data['answers'] ?? null) ? json_encode($data['answers']) : ($data['answers'] ?? '{}'),
                $data['source'] ?? 'public',
                (int)($data['score'] ?? 0),
                $data['ai_summary'] ?? '',
            ]
        );

        return $id;
    }

    public static function updateAiSummary(string $id, string $tenantId, string $summary, int $score = 0): bool
    {
        return Database::execute(
            'UPDATE form_responses SET ai_summary = ?, score = ? WHERE id = ? AND tenant_id = ?',
            [$summary, $score, $id, $tenantId]
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM form_responses WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }
}
