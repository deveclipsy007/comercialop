<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Configuração de qual provedor/modelo serve cada operação de IA.
 * Suporta fallback por priority (menor = maior prioridade).
 */
class AiProviderConfig
{
    /**
     * Retorna configs para uma operação, ordenadas por priority.
     * Busca tenant-level primeiro, depois global (tenant_id IS NULL).
     */
    public static function getForOperation(string $operation, ?string $tenantId = null): array
    {
        $configs = [];

        // 1. Config específica do tenant para essa operação
        if ($tenantId) {
            $configs = Database::select(
                'SELECT * FROM ai_provider_configs
                 WHERE tenant_id = ? AND operation = ? AND is_active = 1
                 ORDER BY priority ASC',
                [$tenantId, $operation]
            );
        }

        // 2. Se não encontrou, buscar config global para essa operação
        if (empty($configs)) {
            $configs = Database::select(
                'SELECT * FROM ai_provider_configs
                 WHERE tenant_id IS NULL AND operation = ? AND is_active = 1
                 ORDER BY priority ASC',
                [$operation]
            );
        }

        // 3. Se ainda vazio, buscar config 'default'
        if (empty($configs)) {
            if ($tenantId) {
                $configs = Database::select(
                    'SELECT * FROM ai_provider_configs
                     WHERE tenant_id = ? AND operation = "default" AND is_active = 1
                     ORDER BY priority ASC',
                    [$tenantId]
                );
            }
            if (empty($configs)) {
                $configs = Database::select(
                    'SELECT * FROM ai_provider_configs
                     WHERE tenant_id IS NULL AND operation = "default" AND is_active = 1
                     ORDER BY priority ASC',
                    []
                );
            }
        }

        return $configs;
    }

    /**
     * Retorna a config primária (priority mais baixa) para uma operação.
     */
    public static function getPrimary(string $operation, ?string $tenantId = null): ?array
    {
        $configs = self::getForOperation($operation, $tenantId);
        return $configs[0] ?? null;
    }

    /**
     * Lista todas as configs de um tenant.
     */
    public static function listAll(?string $tenantId = null): array
    {
        if ($tenantId) {
            return Database::select(
                'SELECT * FROM ai_provider_configs
                 WHERE tenant_id = ? OR tenant_id IS NULL
                 ORDER BY operation ASC, priority ASC',
                [$tenantId]
            );
        }
        return Database::select(
            'SELECT * FROM ai_provider_configs ORDER BY operation ASC, priority ASC',
            []
        );
    }

    /**
     * Insere ou atualiza config por operação+tenant.
     */
    public static function upsert(array $data): void
    {
        $tenantId  = $data['tenant_id'] ?? null;
        $operation = $data['operation'];
        $provider  = $data['provider'];
        $model     = $data['model'];
        $priority  = (int)($data['priority'] ?? 0);
        $isActive  = (int)($data['is_active'] ?? 1);

        // Buscar existente
        if ($tenantId) {
            $existing = Database::selectFirst(
                'SELECT id FROM ai_provider_configs WHERE tenant_id = ? AND operation = ? AND provider = ?',
                [$tenantId, $operation, $provider]
            );
        } else {
            $existing = Database::selectFirst(
                'SELECT id FROM ai_provider_configs WHERE tenant_id IS NULL AND operation = ? AND provider = ?',
                [$operation, $provider]
            );
        }

        if ($existing) {
            Database::execute(
                'UPDATE ai_provider_configs SET model = ?, priority = ?, is_active = ?, updated_at = datetime("now") WHERE id = ?',
                [$model, $priority, $isActive, $existing['id']]
            );
        } else {
            $id = bin2hex(random_bytes(8));
            Database::execute(
                'INSERT INTO ai_provider_configs (id, tenant_id, operation, provider, model, priority, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $tenantId, $operation, $provider, $model, $priority, $isActive]
            );
        }
    }

    /**
     * Desativa uma config.
     */
    public static function deactivate(string $id): void
    {
        Database::execute(
            'UPDATE ai_provider_configs SET is_active = 0, updated_at = datetime("now") WHERE id = ?',
            [$id]
        );
    }

    /**
     * Remove uma config definitivamente.
     */
    public static function delete(string $id): void
    {
        Database::execute('DELETE FROM ai_provider_configs WHERE id = ?', [$id]);
    }
}
