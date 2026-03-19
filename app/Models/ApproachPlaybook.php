<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Playbook de abordagem — documento de referência (livro, framework, playbook)
 * que ensina estilo e princípios de abordagem para a IA.
 */
class ApproachPlaybook
{
    public static function allByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM approach_playbooks WHERE tenant_id = ? ORDER BY created_at DESC',
            [$tenantId]
        );
    }

    public static function activeByTenant(string $tenantId): array
    {
        return Database::select(
            'SELECT * FROM approach_playbooks WHERE tenant_id = ? AND active = 1 AND status = ? ORDER BY created_at DESC',
            [$tenantId, 'ready']
        );
    }

    public static function findById(string $id, string $tenantId): ?array
    {
        return Database::selectFirst(
            'SELECT * FROM approach_playbooks WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function insert(string $tenantId, array $data): string
    {
        $id = self::generateId();

        Database::execute(
            "INSERT INTO approach_playbooks (id, tenant_id, title, description, file_name, file_type, content, chunks, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            [
                $id,
                $tenantId,
                $data['title'] ?? '',
                $data['description'] ?? '',
                $data['file_name'] ?? '',
                $data['file_type'] ?? 'txt',
                $data['content'] ?? '',
                json_encode($data['chunks'] ?? []),
                $data['status'] ?? 'processing',
            ]
        );

        return $id;
    }

    public static function updateStatus(string $id, string $status, array $extra = []): void
    {
        $sets = ["status = ?", "updated_at = datetime('now')"];
        $params = [$status];

        if (isset($extra['chunks'])) {
            $sets[] = 'chunks = ?';
            $params[] = is_string($extra['chunks']) ? $extra['chunks'] : json_encode($extra['chunks']);
        }
        if (isset($extra['content'])) {
            $sets[] = 'content = ?';
            $params[] = $extra['content'];
        }

        $params[] = $id;
        Database::execute('UPDATE approach_playbooks SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public static function toggleActive(string $id, string $tenantId, bool $active): void
    {
        Database::execute(
            "UPDATE approach_playbooks SET active = ?, updated_at = datetime('now') WHERE id = ? AND tenant_id = ?",
            [(int)$active, $id, $tenantId]
        );
    }

    public static function delete(string $id, string $tenantId): int
    {
        return Database::execute(
            'DELETE FROM approach_playbooks WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
    }

    /**
     * Retorna o conteúdo concatenado dos playbooks ativos para injeção de contexto.
     * Limita a ~3000 palavras para não estourar o prompt.
     */
    public static function getActiveContext(string $tenantId, int $maxWords = 3000): string
    {
        $playbooks = self::activeByTenant($tenantId);
        if (empty($playbooks)) return '';

        $parts = [];
        $wordCount = 0;

        foreach ($playbooks as $pb) {
            $chunks = json_decode($pb['chunks'] ?? '[]', true);
            if (empty($chunks)) {
                $chunks = [$pb['content']];
            }

            foreach ($chunks as $chunk) {
                $words = str_word_count($chunk);
                if ($wordCount + $words > $maxWords) break 2;
                $parts[] = $chunk;
                $wordCount += $words;
            }
        }

        return implode("\n\n", $parts);
    }

    private static function generateId(): string
    {
        return sprintf(
            'pb_%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
