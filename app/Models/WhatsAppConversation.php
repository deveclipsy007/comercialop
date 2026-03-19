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
            'INSERT INTO whatsapp_conversations (id, tenant_id, integration_id, remote_jid, display_name, phone, last_message_preview, last_message_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $tenantId,
                $integrationId,
                $data['remote_jid'],
                $data['display_name'] ?? null,
                $data['phone'] ?? null,
                $data['last_message_preview'] ?? null,
                $data['last_message_at'] ?? null
            ]
        );
        return $id;
    }

    public static function allByTenant(string $tenantId, array $filters = []): array
    {
        $sql = 'SELECT c.*,
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
}
