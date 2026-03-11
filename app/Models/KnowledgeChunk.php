<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Fragmento de texto (chunk) derivado de um knowledge_document.
 *
 * Tamanho alvo: ~300 palavras com 50 palavras de overlap.
 * Cada chunk recebe um embedding individual em knowledge_embeddings.
 */
class KnowledgeChunk
{
    // ─── Queries ───────────────────────────────────────────────────

    /**
     * Retorna todos os chunks do tenant sem o embedding (mais leve).
     * Use KnowledgeEmbedding::allByTenant() para o payload vetorial completo.
     */
    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM knowledge_chunks WHERE tenant_id = ? ORDER BY document_id, chunk_index ASC',
            [$tenantId]
        );
    }

    public static function byDocument(string $documentId): array
    {
        return Database::select(
            'SELECT * FROM knowledge_chunks WHERE document_id = ? ORDER BY chunk_index ASC',
            [$documentId]
        );
    }

    /**
     * Insere um chunk e retorna o id gerado.
     *
     * @param array $data [doc_type, chunk_index, content, word_count]
     */
    public static function insert(string $tenantId, string $documentId, array $data): string
    {
        $id = self::generateId();

        Database::execute(
            "INSERT INTO knowledge_chunks
                (id, tenant_id, document_id, doc_type, chunk_index, content, word_count, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [
                $id,
                $tenantId,
                $documentId,
                $data['doc_type'],
                (int) $data['chunk_index'],
                $data['content'],
                (int) ($data['word_count'] ?? 0),
            ]
        );

        return $id;
    }

    public static function deleteByDocument(string $documentId): int
    {
        return Database::execute(
            'DELETE FROM knowledge_chunks WHERE document_id = ?',
            [$documentId]
        );
    }

    public static function deleteByTenant(string $tenantId): int
    {
        return Database::execute(
            'DELETE FROM knowledge_chunks WHERE tenant_id = ?',
            [$tenantId]
        );
    }

    public static function countByTenant(string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as c FROM knowledge_chunks WHERE tenant_id = ?',
            [$tenantId]
        );
        return (int) ($row['c'] ?? 0);
    }

    // ─── Helpers ───────────────────────────────────────────────────

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
}
