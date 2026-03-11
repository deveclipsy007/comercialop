<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Rastreabilidade de cada chamada de IA que usa contexto RAG.
 *
 * Registra: qual empresa, quais chunks foram usados, qual provider,
 * latência, custo de tokens, e a fonte do contexto.
 *
 * context_source:
 *   'rag'     → chunks recuperados via similaridade de cosseno
 *   'legacy'  → agency_settings injetado diretamente (sem RAG)
 *   'default' → sem contexto cadastrado (fallback de sistema)
 */
class AnalysisTrace
{
    public const SOURCE_RAG     = 'rag';
    public const SOURCE_LEGACY  = 'legacy';
    public const SOURCE_DEFAULT = 'default';

    // ─── Escritas ──────────────────────────────────────────────────

    /**
     * Registra um trace de análise. Silencia erros de DB para não
     * quebrar o fluxo principal de análise.
     *
     * @param array $chunksUsed [{chunk_id, doc_type, score}]
     */
    public static function log(
        string  $tenantId,
        ?string $leadId,
        string  $operation,
        string  $contextSource,
        ?string $queryText = null,
        array   $chunksUsed = [],
        ?string $provider = null,
        ?string $model = null,
        int     $latencyMs = 0,
        int     $tokenCost = 0
    ): void {
        try {
            $id = self::generateId();
            Database::execute(
                "INSERT INTO analysis_traces
                    (id, tenant_id, lead_id, operation, context_source,
                     query_text, chunks_used, provider, model,
                     latency_ms, token_cost, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))",
                [
                    $id,
                    $tenantId,
                    $leadId,
                    $operation,
                    $contextSource,
                    $queryText,
                    empty($chunksUsed) ? null : json_encode($chunksUsed),
                    $provider,
                    $model,
                    $latencyMs,
                    $tokenCost,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[AnalysisTrace] Falha ao registrar trace: ' . $e->getMessage());
        }
    }

    // ─── Leituras ──────────────────────────────────────────────────

    public static function recentByTenant(string $tenantId, int $limit = 50): array
    {
        return array_map(
            [self::class, 'decode'],
            Database::select(
                'SELECT * FROM analysis_traces WHERE tenant_id = ?
                 ORDER BY created_at DESC LIMIT ?',
                [$tenantId, $limit]
            )
        );
    }

    public static function recentByLead(string $leadId, int $limit = 20): array
    {
        return array_map(
            [self::class, 'decode'],
            Database::select(
                'SELECT * FROM analysis_traces WHERE lead_id = ?
                 ORDER BY created_at DESC LIMIT ?',
                [$leadId, $limit]
            )
        );
    }

    public static function countByTenant(string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as c FROM analysis_traces WHERE tenant_id = ?',
            [$tenantId]
        );
        return (int) ($row['c'] ?? 0);
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private static function decode(array $row): array
    {
        if (isset($row['chunks_used']) && is_string($row['chunks_used'])) {
            $row['chunks_used'] = json_decode($row['chunks_used'], true) ?? [];
        }
        $row['latency_ms']  = (int) ($row['latency_ms'] ?? 0);
        $row['token_cost']  = (int) ($row['token_cost'] ?? 0);
        return $row;
    }

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
