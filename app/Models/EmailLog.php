<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EmailLog
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

    private static function generateTrackingToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function allByTenant(string $tenantId, int $limit = 50, int $offset = 0): array
    {
        return Database::select(
            'SELECT el.*, l.name as lead_name, u.name as user_name
             FROM email_log el
             LEFT JOIN leads l ON l.id = el.lead_id
             LEFT JOIN users u ON u.id = el.user_id
             WHERE el.tenant_id = ?
             ORDER BY el.created_at DESC
             LIMIT ? OFFSET ?',
            [$tenantId, $limit, $offset]
        );
    }

    public static function allByCampaign(string $campaignId, string $tenantId): array
    {
        return Database::select(
            'SELECT el.*, l.name as lead_name
             FROM email_log el
             LEFT JOIN leads l ON l.id = el.lead_id
             WHERE el.campaign_id = ? AND el.tenant_id = ?
             ORDER BY el.created_at DESC',
            [$campaignId, $tenantId]
        );
    }

    public static function allByLead(string $leadId, string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM email_log WHERE lead_id = ? AND tenant_id = ? ORDER BY created_at DESC',
            [$leadId, $tenantId]
        );
    }

    public static function find(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM email_log WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function findByTrackingToken(string $token): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM email_log WHERE tracking_token = ?',
            [$token]
        );
    }

    public static function create(string $tenantId, array $data): string
    {
        $id = self::generateId();
        $trackingToken = self::generateTrackingToken();

        Database::execute(
            'INSERT INTO email_log (id, tenant_id, account_id, campaign_id, step_id, lead_id, user_id, to_email, to_name, from_email, subject, body, status, tracking_token, scheduled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $data['account_id'] ?? null,
                $data['campaign_id'] ?? null,
                $data['step_id'] ?? null,
                $data['lead_id'] ?? null,
                $data['user_id'] ?? null,
                $data['to_email'] ?? '',
                $data['to_name'] ?? '',
                $data['from_email'] ?? '',
                $data['subject'] ?? '',
                $data['body'] ?? '',
                $data['status'] ?? 'queued',
                $trackingToken,
                $data['scheduled_at'] ?? null,
            ]
        );
        return $id;
    }

    public static function updateStatus(string $id, string $status, ?string $errorMessage = null): void
    {
        $extra = '';
        $params = [$status];
        if ($status === 'sent') {
            $extra = ", sent_at = datetime('now')";
        }
        if ($errorMessage) {
            $extra .= ", error_message = ?";
            $params[] = $errorMessage;
        }
        $params[] = $id;

        Database::execute(
            "UPDATE email_log SET status = ?{$extra} WHERE id = ?",
            $params
        );
    }

    public static function markOpened(string $trackingToken): void
    {
        Database::execute(
            "UPDATE email_log SET opened_at = datetime('now') WHERE tracking_token = ? AND opened_at IS NULL",
            [$trackingToken]
        );
    }

    public static function markClicked(string $trackingToken): void
    {
        Database::execute(
            "UPDATE email_log SET clicked_at = datetime('now') WHERE tracking_token = ? AND clicked_at IS NULL",
            [$trackingToken]
        );
    }

    public static function getStats(string $tenantId, ?string $campaignId = null): array
    {
        $where = 'tenant_id = ?';
        $params = [$tenantId];
        if ($campaignId) {
            $where .= ' AND campaign_id = ?';
            $params[] = $campaignId;
        }

        $stats = Database::selectFirst(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN replied_at IS NOT NULL THEN 1 ELSE 0 END) as replied,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
             FROM email_log WHERE $where",
            $params
        );

        return [
            'total' => (int)($stats['total'] ?? 0),
            'sent' => (int)($stats['sent'] ?? 0),
            'queued' => (int)($stats['queued'] ?? 0),
            'failed' => (int)($stats['failed'] ?? 0),
            'opened' => (int)($stats['opened'] ?? 0),
            'clicked' => (int)($stats['clicked'] ?? 0),
            'replied' => (int)($stats['replied'] ?? 0),
            'bounced' => (int)($stats['bounced'] ?? 0),
            'open_rate' => ($stats['sent'] ?? 0) > 0 ? round(($stats['opened'] ?? 0) / $stats['sent'] * 100, 1) : 0,
            'click_rate' => ($stats['sent'] ?? 0) > 0 ? round(($stats['clicked'] ?? 0) / $stats['sent'] * 100, 1) : 0,
        ];
    }
}
