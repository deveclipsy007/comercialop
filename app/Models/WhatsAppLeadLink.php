<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class WhatsAppLeadLink
{
    /**
     * Retorna o primeiro link de uma conversa (backward compat).
     */
    public static function findByConversation(string $tenantId, string $conversationId): ?array
    {
        return Database::selectFirst(
            'SELECT wll.*, leads.name as lead_name, leads.pipeline_status, leads.priority_score
             FROM whatsapp_lead_links wll
             LEFT JOIN leads ON leads.id = wll.lead_id
             WHERE wll.tenant_id = ? AND wll.conversation_id = ?
             ORDER BY wll.created_at ASC LIMIT 1',
            [$tenantId, $conversationId]
        );
    }

    /**
     * Retorna TODOS os links de uma conversa (1:N).
     */
    public static function findAllByConversation(string $tenantId, string $conversationId): array
    {
        return Database::select(
            'SELECT wll.*, leads.name as lead_name, leads.pipeline_status, leads.priority_score, leads.website, leads.tags
             FROM whatsapp_lead_links wll
             LEFT JOIN leads ON leads.id = wll.lead_id
             WHERE wll.tenant_id = ? AND wll.conversation_id = ?
             ORDER BY wll.created_at ASC',
            [$tenantId, $conversationId]
        );
    }

    /**
     * Vincula um lead a uma conversa. INSERT OR IGNORE evita duplicatas.
     */
    public static function link(string $tenantId, string $conversationId, string $leadId, string $type = 'manual'): bool
    {
        return Database::execute(
            'INSERT OR IGNORE INTO whatsapp_lead_links (id, tenant_id, conversation_id, lead_id, link_type)
             VALUES (?, ?, ?, ?, ?)',
            [bin2hex(random_bytes(8)), $tenantId, $conversationId, $leadId, $type]
        ) > 0;
    }

    /**
     * Remove TODOS os links de uma conversa (backward compat).
     */
    public static function unlink(string $tenantId, string $conversationId): bool
    {
        return Database::execute(
            'DELETE FROM whatsapp_lead_links WHERE tenant_id = ? AND conversation_id = ?',
            [$tenantId, $conversationId]
        ) > 0;
    }

    /**
     * Remove um link específico (1 lead de 1 conversa).
     */
    public static function unlinkOne(string $tenantId, string $conversationId, string $leadId): bool
    {
        return Database::execute(
            'DELETE FROM whatsapp_lead_links WHERE tenant_id = ? AND conversation_id = ? AND lead_id = ?',
            [$tenantId, $conversationId, $leadId]
        ) > 0;
    }

    /**
     * Retorna TODAS as conversas vinculadas a um lead (reverse lookup).
     */
    public static function findAllByLead(string $tenantId, string $leadId): array
    {
        return Database::select(
            'SELECT wll.*, c.display_name, c.phone, c.remote_jid, c.last_message_at, c.unread_count
             FROM whatsapp_lead_links wll
             LEFT JOIN whatsapp_conversations c ON c.id = wll.conversation_id
             WHERE wll.tenant_id = ? AND wll.lead_id = ?
             ORDER BY c.last_message_at DESC',
            [$tenantId, $leadId]
        );
    }

    /**
     * Conta quantos leads estão vinculados a uma conversa.
     */
    public static function countByConversation(string $tenantId, string $conversationId): int
    {
        $res = Database::selectFirst(
            'SELECT COUNT(*) as total FROM whatsapp_lead_links WHERE tenant_id = ? AND conversation_id = ?',
            [$tenantId, $conversationId]
        );
        return (int)($res['total'] ?? 0);
    }
}
