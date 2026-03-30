<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class PlaybookProgress
{
    public static function markCompleted(string $tenantId, string $userId, string $moduleId, string $blockId): void
    {
        $existing = Database::selectFirst(
            'SELECT id FROM playbook_progress WHERE user_id = ? AND block_id = ?',
            [$userId, $blockId]
        );

        if ($existing) {
            Database::execute(
                "UPDATE playbook_progress SET completed = 1, completed_at = datetime('now') WHERE id = ?",
                [$existing['id']]
            );
        } else {
            $id = 'pbp_' . bin2hex(random_bytes(12));
            Database::execute(
                "INSERT INTO playbook_progress (id, tenant_id, user_id, module_id, block_id, completed, completed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))",
                [$id, $tenantId, $userId, $moduleId, $blockId]
            );
        }
    }

    public static function unmarkCompleted(string $userId, string $blockId): void
    {
        Database::execute(
            'UPDATE playbook_progress SET completed = 0, completed_at = NULL WHERE user_id = ? AND block_id = ?',
            [$userId, $blockId]
        );
    }

    public static function getCompletedBlockIds(string $userId, string $tenantId): array
    {
        $rows = Database::select(
            'SELECT block_id FROM playbook_progress WHERE user_id = ? AND tenant_id = ? AND completed = 1',
            [$userId, $tenantId]
        );
        return array_column($rows, 'block_id');
    }

    public static function getModuleProgress(string $userId, string $tenantId, string $moduleId): array
    {
        $total = Database::selectFirst(
            'SELECT COUNT(*) as total FROM playbook_blocks WHERE module_id = ? AND tenant_id = ?',
            [$moduleId, $tenantId]
        );
        $completed = Database::selectFirst(
            'SELECT COUNT(*) as done FROM playbook_progress WHERE user_id = ? AND module_id = ? AND tenant_id = ? AND completed = 1',
            [$userId, $moduleId, $tenantId]
        );

        $t = (int) ($total['total'] ?? 0);
        $d = (int) ($completed['done'] ?? 0);

        return [
            'total' => $t,
            'completed' => $d,
            'percent' => $t > 0 ? round(($d / $t) * 100) : 0,
        ];
    }
}
