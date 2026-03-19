<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class WhatsAppConversationAnalysis
{
    /**
     * Retorna a última análise de uma conversa (qualquer tipo).
     */
    public static function latestByConversation(string $conversationId): ?array
    {
        $row = Database::selectFirst(
            'SELECT * FROM whatsapp_conversation_analyses WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1',
            [$conversationId]
        );
        return $row ? self::decodeRow($row) : null;
    }

    /**
     * Retorna a última análise de um tipo específico.
     */
    public static function latestByType(string $conversationId, string $type): ?array
    {
        $row = Database::selectFirst(
            'SELECT * FROM whatsapp_conversation_analyses WHERE conversation_id = ? AND analysis_type = ? ORDER BY created_at DESC LIMIT 1',
            [$conversationId, $type]
        );
        return $row ? self::decodeRow($row) : null;
    }

    /**
     * Retorna todas as análises de uma conversa.
     */
    public static function allByConversation(string $conversationId): array
    {
        $rows = Database::select(
            'SELECT * FROM whatsapp_conversation_analyses WHERE conversation_id = ? ORDER BY created_at DESC',
            [$conversationId]
        );
        return array_map([self::class, 'decodeRow'], $rows);
    }

    /**
     * Armazena análise (backward compat — tipo 'full').
     */
    public static function store(string $conversationId, array $analysisData, int $tokens = 0, string $tenantId = ''): string
    {
        return self::storeTyped($conversationId, $tenantId, 'full', $analysisData, $tokens);
    }

    /**
     * Armazena análise com tipo específico.
     */
    public static function storeTyped(
        string $conversationId,
        string $tenantId,
        string $analysisType,
        array  $analysisData,
        int    $tokens = 0,
        ?int   $interestScore = null
    ): string {
        $id = bin2hex(random_bytes(8));

        // Buscar version do mesmo tipo
        $latest = self::latestByType($conversationId, $analysisType);
        $version = $latest ? ((int)($latest['version'] ?? 0) + 1) : 1;

        Database::execute(
            'INSERT INTO whatsapp_conversation_analyses
             (id, conversation_id, tenant_id, analysis, version, tokens_used, analysis_type, interest_score)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $conversationId,
                $tenantId,
                json_encode($analysisData, JSON_UNESCAPED_UNICODE),
                $version,
                $tokens,
                $analysisType,
                $interestScore
            ]
        );
        return $id;
    }

    /**
     * Decodifica a coluna JSON `analysis`.
     */
    private static function decodeRow(array $row): array
    {
        if (isset($row['analysis']) && is_string($row['analysis'])) {
            $row['analysis_data'] = json_decode($row['analysis'], true) ?? [];
        } else {
            $row['analysis_data'] = [];
        }
        return $row;
    }
}
