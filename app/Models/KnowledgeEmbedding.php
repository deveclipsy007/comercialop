<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Embedding vetorial por chunk de conhecimento.
 *
 * Armazenado como TEXT JSON no SQLite (ex: "[0.123, -0.456, ...]").
 * A decodificação para float[] acontece em allByTenant() via json_decode().
 *
 * Para PostgreSQL com pgvector:
 *   - Troque TEXT por vector(768)
 *   - Substitua allByTenant() por query com operador <=>
 *   - Retire o loop de cosine similarity do RAGRetrievalService
 */
class KnowledgeEmbedding
{
    // ─── Queries ───────────────────────────────────────────────────

    /**
     * Retorna todos os embeddings do tenant com o conteúdo e doc_type do chunk
     * para permitir retrieval completo em memória.
     *
     * Shape de cada linha:
     *   id, tenant_id, chunk_id, model, dimensions,
     *   embedding (string JSON → use json_decode),
     *   content (do chunk), doc_type (do chunk)
     */
    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT ke.id, ke.tenant_id, ke.chunk_id, ke.model, ke.dimensions,
                    ke.embedding,
                    kc.content  AS content,
                    kc.doc_type AS doc_type
             FROM knowledge_embeddings ke
             JOIN knowledge_chunks kc ON kc.id = ke.chunk_id
             WHERE ke.tenant_id = ?',
            [$tenantId]
        );
    }

    /**
     * INSERT OR REPLACE para upsert de embedding.
     * Usa UNIQUE(chunk_id) como chave de conflito.
     */
    public static function upsert(
        string $tenantId,
        string $chunkId,
        array $vector,
        string $model,
        int $dimensions
    ): void {
        $id        = self::generateId();
        $embedding = json_encode($vector);

        // SQLite: INSERT OR REPLACE
        // PostgreSQL: reescrever como INSERT ... ON CONFLICT (chunk_id) DO UPDATE
        Database::execute(
            "INSERT OR REPLACE INTO knowledge_embeddings
                (id, tenant_id, chunk_id, model, dimensions, embedding, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            [$id, $tenantId, $chunkId, $model, $dimensions, $embedding]
        );
    }

    public static function deleteByTenant(string $tenantId): int
    {
        return Database::execute(
            'DELETE FROM knowledge_embeddings WHERE tenant_id = ?',
            [$tenantId]
        );
    }

    public static function countByTenant(string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as c FROM knowledge_embeddings WHERE tenant_id = ?',
            [$tenantId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function existsForTenant(string $tenantId): bool
    {
        return self::countByTenant($tenantId) > 0;
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
