<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class WhatsAppConversation
{
    public static function findByJid(string $tenantId, string $jid): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM whatsapp_conversations WHERE tenant_id = ? AND remote_jid = ?',
            [$tenantId, $jid]
        );
    }

    public static function findByIdAndTenant(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM whatsapp_conversations WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function upsertByJid(string $tenantId, string $integrationId, array $data): string
    {
        $existing = self::findByJid($tenantId, $data['remote_jid']);
        
        if ($existing) {
            $updates = [];
            $params = [];
            
            if (isset($data['display_name'])) {
                $updates[] = 'display_name = ?';
                $params[] = $data['display_name'];
            }
            if (isset($data['last_message_preview'])) {
                $updates[] = 'last_message_preview = ?';
                $params[] = $data['last_message_preview'];
            }
            if (isset($data['last_message_at'])) {
                $updates[] = 'last_message_at = ?';
                $params[] = $data['last_message_at'];
            }
            if (isset($data['phone'])) {
                $updates[] = 'phone = ?';
                $params[] = $data['phone'];
            }
            if (isset($data['unread_count'])) {
                $updates[] = 'unread_count = ?';
                $params[] = (int) $data['unread_count'];
            }
            if (isset($data['last_read_ts'])) {
                $updates[] = 'last_read_ts = ?';
                $params[] = (int) $data['last_read_ts'];
            }
            
            if (empty($updates)) return $existing['id'];

            $params[] = $existing['id'];
            Database::execute(
                'UPDATE whatsapp_conversations SET ' . implode(', ', $updates) . ', updated_at = datetime("now") WHERE id = ?',
                $params
            );
            return $existing['id'];
        }

        $id = bin2hex(random_bytes(8));
        Database::execute(
            'INSERT INTO whatsapp_conversations (id, tenant_id, integration_id, remote_jid, display_name, phone, last_message_preview, last_message_at, last_read_ts)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $integrationId,
                $data['remote_jid'],
                $data['display_name'] ?? null,
                $data['phone'] ?? null,
                $data['last_message_preview'] ?? null,
                $data['last_message_at'] ?? null,
                (int) ($data['last_read_ts'] ?? 0),
            ]
        );
        return $id;
    }

    public static function allByTenant(string $tenantId, array $filters = []): array
    {
        $sql = 'SELECT c.*,
                       MIN(leads.name) as lead_name,
                       GROUP_CONCAT(l.lead_id) as linked_lead_ids,
                       GROUP_CONCAT(leads.name) as linked_lead_names,
                       COUNT(l.lead_id) as linked_count
                FROM whatsapp_conversations c
                LEFT JOIN whatsapp_lead_links l ON l.conversation_id = c.id AND l.tenant_id = c.tenant_id
                LEFT JOIN leads ON leads.id = l.lead_id
                WHERE c.tenant_id = ?';
        $params = [$tenantId];

        if (!empty($filters['search'])) {
            $sql .= ' AND (c.display_name LIKE ? OR c.phone LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['linked'])) {
            $sql .= $filters['linked']
                ? ' AND c.id IN (SELECT conversation_id FROM whatsapp_lead_links WHERE tenant_id = ?)'
                : ' AND c.id NOT IN (SELECT conversation_id FROM whatsapp_lead_links WHERE tenant_id = ?)';
            $params[] = $tenantId;
        }

        $sql .= ' GROUP BY c.id ORDER BY c.last_message_at DESC';

        if (isset($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
            if (isset($filters['offset'])) {
                $sql .= ' OFFSET ' . (int)$filters['offset'];
            }
        }

        return Database::select($sql, $params);
    }

    public static function countByTenant(string $tenantId, array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) as total FROM whatsapp_conversations c 
                LEFT JOIN whatsapp_lead_links l ON l.conversation_id = c.id
                WHERE c.tenant_id = ?';
        $params = [$tenantId];

        if (!empty($filters['search'])) {
            $sql .= ' AND (c.display_name LIKE ? OR c.phone LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['linked'])) {
            $sql .= $filters['linked'] ? ' AND l.lead_id IS NOT NULL' : ' AND l.lead_id IS NULL';
        }

        $res = Database::selectFirst($sql, $params);
        return (int)($res['total'] ?? 0);
    }

    public static function incrementUnread(string $id, string $tenantId, int $delta = 1): void
    {
        Database::execute(
            'UPDATE whatsapp_conversations
             SET unread_count = COALESCE(unread_count, 0) + ?,
                 updated_at = datetime("now")
             WHERE id = ? AND tenant_id = ?',
            [max(1, $delta), $id, $tenantId]
        );
    }

    public static function resetUnread(string $id, string $tenantId): void
    {
        self::markRead($id, $tenantId);
    }

    public static function markRead(string $id, string $tenantId, ?int $readTs = null): void
    {
        Database::execute(
            'UPDATE whatsapp_conversations
             SET unread_count = 0,
                 last_read_ts = ?,
                 updated_at = datetime("now")
             WHERE id = ? AND tenant_id = ?',
            [$readTs ?? time(), $id, $tenantId]
        );
    }

    public static function recalculateUnread(string $id, string $tenantId): void
    {
        Database::execute(
            'UPDATE whatsapp_conversations
             SET unread_count = (
                 SELECT COUNT(*)
                 FROM whatsapp_messages m
                 WHERE m.conversation_id = whatsapp_conversations.id
                   AND m.tenant_id = whatsapp_conversations.tenant_id
                   AND m.direction = "incoming"
                   AND m.timestamp > COALESCE(whatsapp_conversations.last_read_ts, 0)
             )
             WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function recalculateUnreadByTenant(string $tenantId): void
    {
        Database::execute(
            'UPDATE whatsapp_conversations
             SET unread_count = (
                 SELECT COUNT(*)
                 FROM whatsapp_messages m
                 WHERE m.conversation_id = whatsapp_conversations.id
                   AND m.tenant_id = whatsapp_conversations.tenant_id
                   AND m.direction = "incoming"
                   AND m.timestamp > COALESCE(whatsapp_conversations.last_read_ts, 0)
             )
             WHERE tenant_id = ?',
            [$tenantId]
        );
    }

    public static function unreadCountByTenant(string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COALESCE(SUM(unread_count), 0) as total
             FROM whatsapp_conversations
             WHERE tenant_id = ?',
            [$tenantId]
        );

        return (int) ($row['total'] ?? 0);
    }

    public static function latestUnreadByTenant(string $tenantId, int $limit = 8): array
    {
        return Database::select(
            'SELECT c.*,
                    MIN(leads.name) as lead_name,
                    COUNT(l.lead_id) as linked_count
             FROM whatsapp_conversations c
             LEFT JOIN whatsapp_lead_links l ON l.conversation_id = c.id AND l.tenant_id = c.tenant_id
             LEFT JOIN leads ON leads.id = l.lead_id
             WHERE c.tenant_id = ?
               AND COALESCE(c.unread_count, 0) > 0
             GROUP BY c.id
             ORDER BY c.last_message_at DESC, c.updated_at DESC
             LIMIT ?',
            [$tenantId, max(1, $limit)]
        );
    }
}
