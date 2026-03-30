<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EmailCampaign
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

    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT c.*, ea.email_address as account_email
             FROM email_campaigns c
             LEFT JOIN email_accounts ea ON ea.id = c.account_id
             WHERE c.tenant_id = ?
             ORDER BY c.updated_at DESC',
            [$tenantId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM email_campaigns WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function create(string $tenantId, string $userId, array $data): string
    {
        $id = self::generateId();
        Database::execute(
            'INSERT INTO email_campaigns (id, tenant_id, created_by, account_id, name, description, campaign_type, lead_ids, settings)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $userId,
                $data['account_id'] ?? null,
                $data['name'] ?? 'Nova Campanha',
                $data['description'] ?? '',
                $data['campaign_type'] ?? 'one_time',
                json_encode($data['lead_ids'] ?? []),
                json_encode($data['settings'] ?? []),
            ]
        );
        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowed = ['name', 'description', 'status', 'campaign_type', 'account_id', 'lead_ids', 'total_leads', 'sent_count', 'opened_count', 'clicked_count', 'replied_count', 'bounced_count', 'scheduled_at', 'started_at', 'completed_at', 'settings', 'target_filter'];

        foreach ($allowed as $col) {
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
            'UPDATE email_campaigns SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $values
        ) > 0;
    }

    public static function delete(string $id, string $tenantId): bool
    {
        // Delete steps first
        Database::execute('DELETE FROM email_campaign_steps WHERE campaign_id = ? AND tenant_id = ?', [$id, $tenantId]);
        return Database::execute(
            'DELETE FROM email_campaigns WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    // ─── Steps ─────────────────────────────────────────────────

    public static function getSteps(string $campaignId, string $tenantId): array
    {
        return Database::select(
            'SELECT s.*, t.name as template_name
             FROM email_campaign_steps s
             LEFT JOIN email_templates t ON t.id = s.template_id
             WHERE s.campaign_id = ? AND s.tenant_id = ?
             ORDER BY s.step_order ASC',
            [$campaignId, $tenantId]
        );
    }

    public static function createStep(string $tenantId, string $campaignId, array $data): string
    {
        $id = self::generateId();
        $maxOrder = Database::selectFirst(
            'SELECT COALESCE(MAX(step_order), 0) as max_order FROM email_campaign_steps WHERE campaign_id = ?',
            [$campaignId]
        );

        Database::execute(
            'INSERT INTO email_campaign_steps (id, tenant_id, campaign_id, template_id, step_order, subject, body, delay_days, delay_hours, condition_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $campaignId,
                $data['template_id'] ?? null,
                ($maxOrder['max_order'] ?? 0) + 1,
                $data['subject'] ?? '',
                $data['body'] ?? '',
                (int)($data['delay_days'] ?? 0),
                (int)($data['delay_hours'] ?? 0),
                $data['condition_type'] ?? 'always',
            ]
        );
        return $id;
    }

    public static function updateStep(string $stepId, string $tenantId, array $data): bool
    {
        $fields = [];
        $values = [];
        foreach (['subject', 'body', 'delay_days', 'delay_hours', 'condition_type', 'is_active', 'template_id'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }
        if (empty($fields)) return false;

        $fields[] = "updated_at = datetime('now')";
        $values[] = $stepId;
        $values[] = $tenantId;

        return Database::execute(
            'UPDATE email_campaign_steps SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?',
            $values
        ) > 0;
    }

    public static function deleteStep(string $stepId, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM email_campaign_steps WHERE id = ? AND tenant_id = ?',
            [$stepId, $tenantId]
        ) > 0;
    }
}
