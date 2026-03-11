<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Documento de conhecimento derivado de uma seção do perfil da empresa.
 *
 * Cada doc_type representa uma seção semântica diferente:
 * identity, services, differentials, icp, cases, objections, competitors, custom.
 *
 * Deletar um documento cascata os seus chunks e embeddings via FK.
 */
class KnowledgeDocument
{
    public const TYPES = [
        'identity',
        'services',
        'differentials',
        'icp',
        'cases',
        'objections',
        'competitors',
        'custom',
    ];

    // ─── Queries ───────────────────────────────────────────────────

    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM knowledge_documents WHERE tenant_id = ? ORDER BY doc_type ASC',
            [$tenantId]
        );
    }

    public static function allByProfile(string $profileId): array
    {
        return Database::select(
            'SELECT * FROM knowledge_documents WHERE profile_id = ? ORDER BY doc_type ASC',
            [$profileId]
        );
    }

    public static function findById(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM knowledge_documents WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    /**
     * Insere um documento novo e retorna o id gerado.
     *
     * @param array $data [doc_type, title, content, profile_version]
     */
    public static function insert(string $tenantId, string $profileId, array $data): string
    {
        $id = self::generateId();

        Database::execute(
            "INSERT INTO knowledge_documents
                (id, tenant_id, profile_id, profile_version, doc_type, title, content, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [
                $id,
                $tenantId,
                $profileId,
                (int) ($data['profile_version'] ?? 1),
                $data['doc_type'],
                $data['title'],
                $data['content'],
            ]
        );

        return $id;
    }

    /**
     * Remove todos os documentos de um tenant.
     * A cascata de FK remove automaticamente chunks e embeddings associados.
     */
    public static function deleteByTenant(string $tenantId): int
    {
        return Database::execute(
            'DELETE FROM knowledge_documents WHERE tenant_id = ?',
            [$tenantId]
        );
    }

    public static function deleteById(string $id, string $tenantId): int
    {
        return Database::execute(
            'DELETE FROM knowledge_documents WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function countByTenant(string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as c FROM knowledge_documents WHERE tenant_id = ?',
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
