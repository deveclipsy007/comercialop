<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Core\Database;
use App\Models\KnowledgeEmbedding;
use App\Helpers\VectorMath;
use App\Services\AI\EmbeddingProvider;

/**
 * Recuperação de contexto relevante via similaridade vetorial (RAG).
 *
 * Cadeia de fallback (garantia de que sempre retorna algo):
 *   1. Vector search  → embedding da query + cosine similarity contra todos os chunks
 *   2. Keyword search → LIKE %palavra% nas knowledge_chunks (se embedding falhar)
 *   3. Legacy         → lê agency_settings e retorna como pseudo-chunk
 *
 * Performance:
 *   Típico: 30-80 chunks × 768 dims = ~60k mul/div por query → < 1ms em PHP.
 *   Para > 5000 chunks considere mover para pgvector ou Elasticsearch.
 */
class RAGRetrievalService
{
    // Palavras portuguesas sem valor semântico para filtrar em keyword search
    private const STOP_WORDS = [
        'o', 'a', 'os', 'as', 'um', 'uma', 'de', 'do', 'da', 'dos', 'das',
        'em', 'no', 'na', 'nos', 'nas', 'por', 'para', 'com', 'sem', 'que',
        'se', 'e', 'ou', 'mas', 'ao', 'à', 'é', 'são', 'foi', 'ser', 'ter',
        'me', 'te', 'se', 'nos', 'vos', 'lhe', 'lhes', 'isso', 'este', 'esta',
    ];

    private EmbeddingProvider $embedder;

    public function __construct()
    {
        $this->embedder = new EmbeddingProvider();
    }

    /**
     * Recupera os top-K chunks mais relevantes para a query no contexto do tenant.
     *
     * @return array Cada item: [chunk_id, doc_type, content, score, source]
     *               source: 'vector' | 'keyword' | 'legacy'
     */
    public function retrieve(string $query, string $tenantId, int $topK = 5): array
    {
        // Tentativa 1: Busca vetorial
        if ($this->hasIndex($tenantId)) {
            $results = $this->vectorSearch($query, $tenantId, $topK);
            if (!empty($results)) {
                return $results;
            }
            // Vector search pode falhar se embedding da query falhar
            $results = $this->keywordSearch($query, $tenantId, $topK);
            if (!empty($results)) {
                return $results;
            }
        }

        // Tentativa 2: Sem índice RAG — tenta keyword nos chunks existentes
        if (KnowledgeEmbedding::countByTenant($tenantId) === 0) {
            $chunksExist = Database::selectFirst(
                'SELECT id FROM knowledge_chunks WHERE tenant_id = ? LIMIT 1',
                [$tenantId]
            );
            if ($chunksExist) {
                $results = $this->keywordSearch($query, $tenantId, $topK);
                if (!empty($results)) return $results;
            }
        }

        // Tentativa 3: Fallback para agency_settings (legado)
        return $this->legacyFallback($tenantId);
    }

    /**
     * Retorna true se o tenant possui pelo menos um embedding indexado.
     */
    public function hasIndex(string $tenantId): bool
    {
        return KnowledgeEmbedding::existsForTenant($tenantId);
    }

    // ─── Estratégias de busca ──────────────────────────────────────

    /**
     * Busca vetorial por similaridade de cosseno.
     *
     * Carrega todos os embeddings do tenant na memória, computa similaridade
     * com o embedding da query e retorna top-K ordenados por score DESC.
     *
     * Retorna [] se o embedding da query falhar (EmbeddingProvider silencia erros).
     */
    private function vectorSearch(string $query, string $tenantId, int $topK): array
    {
        $queryVector = $this->embedder->embed($query);

        if (empty($queryVector)) {
            error_log('[RAGRetrieval] Embedding da query falhou — caindo para keyword search');
            return [];
        }

        $rows   = KnowledgeEmbedding::allByTenant($tenantId);
        $scored = [];

        foreach ($rows as $row) {
            // json_decode aqui: embedding vem como string JSON do banco
            $chunkVector = json_decode($row['embedding'], true);
            if (!is_array($chunkVector) || empty($chunkVector)) {
                continue;
            }

            $score = VectorMath::cosineSimilarity($queryVector, $chunkVector);

            $scored[] = [
                'chunk_id' => $row['chunk_id'],
                'doc_type' => $row['doc_type'],
                'content'  => $row['content'],
                'score'    => $score,
                'source'   => 'vector',
            ];
        }

        if (empty($scored)) {
            return [];
        }

        // Ordena por score decrescente
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * Busca por palavras-chave nos chunks via SQL LIKE.
     * Usado como fallback quando a API de embedding não está disponível.
     *
     * Extrai palavras com mais de 3 caracteres que não sejam stop words.
     * Usa OR para ampliar o recall (pelo menos uma palavra presente).
     */
    private function keywordSearch(string $query, string $tenantId, int $topK): array
    {
        $keywords = $this->extractKeywords($query);

        if (empty($keywords)) {
            return [];
        }

        // Monta cláusulas LIKE para cada palavra
        $conditions = array_map(fn($kw) => "content LIKE ?", $keywords);
        $params     = array_map(fn($kw) => "%{$kw}%", $keywords);

        $sql    = "SELECT id as chunk_id, doc_type, content FROM knowledge_chunks
                   WHERE tenant_id = ? AND (" . implode(' OR ', $conditions) . ")
                   LIMIT ?";
        $params = array_merge([$tenantId], $params, [$topK]);

        $rows = Database::select($sql, $params);

        return array_map(fn($row) => array_merge($row, [
            'score'  => 0.0,
            'source' => 'keyword',
        ]), $rows);
    }

    /**
     * Fallback final: lê agency_settings e empacota como pseudo-chunk.
     * Mantém compatibilidade total com o sistema legado (SmartContextService).
     */
    private function legacyFallback(string $tenantId): array
    {
        $settings = Database::selectFirst(
            'SELECT * FROM agency_settings WHERE tenant_id = ?',
            [$tenantId]
        );

        if (!$settings) {
            return [];
        }

        // Decodifica campos JSON do legado
        foreach (['differentials', 'services', 'cases'] as $field) {
            if (isset($settings[$field]) && is_string($settings[$field])) {
                $settings[$field] = json_decode($settings[$field], true) ?? [];
            }
        }

        // Serializa como texto para uso como chunk
        $content = $this->serializeAgencySettings($settings);

        return [[
            'chunk_id' => 'legacy_' . $tenantId,
            'doc_type' => 'legacy',
            'content'  => $content,
            'score'    => 0.0,
            'source'   => 'legacy',
        ]];
    }

    // ─── Utilidades ────────────────────────────────────────────────

    /**
     * Extrai palavras significativas da query para keyword search.
     * Remove stop words e palavras muito curtas.
     *
     * @return string[]
     */
    private function extractKeywords(string $query): array
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($query)));
        return array_values(array_filter($words, function (string $w): bool {
            return mb_strlen($w) > 3 && !in_array($w, self::STOP_WORDS, true);
        }));
    }

    private function serializeAgencySettings(array $s): string
    {
        $parts = [];

        if (!empty($s['agency_name']))  $parts[] = "Agência: {$s['agency_name']}.";
        if (!empty($s['agency_niche'])) $parts[] = "Nicho: {$s['agency_niche']}.";
        if (!empty($s['offer_summary'])) $parts[] = "Oferta: {$s['offer_summary']}";
        if (!empty($s['icp_profile']))  $parts[] = "ICP: {$s['icp_profile']}";
        if (!empty($s['custom_context'])) $parts[] = $s['custom_context'];

        $diffs = is_array($s['differentials'] ?? null) ? $s['differentials'] : [];
        foreach ($diffs as $d) {
            if (!empty($d)) $parts[] = '- ' . (is_string($d) ? $d : ($d['name'] ?? ''));
        }

        return implode("\n", array_filter($parts));
    }
}
