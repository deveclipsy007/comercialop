<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class WhatsAppMessage
{
    public static function findByConversation(string $conversationId, int $limit = 50, int $offset = 0): array
    {
        return Database::select(
            'SELECT * FROM whatsapp_messages WHERE conversation_id = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?',
            [$conversationId, $limit, $offset]
        );
    }

    public static function countByConversation(string $conversationId): int
    {
        $res = Database::selectFirst(
            'SELECT COUNT(*) as total FROM whatsapp_messages WHERE conversation_id = ?',
            [$conversationId]
        );
        return (int)($res['total'] ?? 0);
    }

    public static function insertIgnore(string $conversationId, string $tenantId, array $data): bool
    {
        return Database::execute(
            'INSERT OR IGNORE INTO whatsapp_messages (id, conversation_id, tenant_id, remote_id, direction, body, message_type, timestamp, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                bin2hex(random_bytes(8)),
                $conversationId,
                $tenantId,
                $data['remote_id'],
                $data['direction'],
                $data['body'],
                $data['message_type'] ?? 'text',
                $data['timestamp'],
                $data['status'] ?? 'received'
            ]
        ) > 0;
    }
}
