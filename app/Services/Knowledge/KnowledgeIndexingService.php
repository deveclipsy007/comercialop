<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\CompanyProfile;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEmbedding;
use App\Services\AI\EmbeddingProvider;

/**
 * Orquestra o pipeline completo de indexação do conhecimento empresarial.
 *
 * Fluxo:
 *   1. Lê o perfil do tenant de company_profiles
 *   2. Deleta documentos/chunks/embeddings anteriores (wipe-and-reindex)
 *   3. Gera documentos semânticos via ChunkingService
 *   4. Quebra em chunks com overlap
 *   5. Gera embeddings em lote via EmbeddingProvider
 *   6. Persiste tudo no banco
 *   7. Atualiza status em company_profiles
 *
 * Decisão de wipe-and-reindex:
 *   Garante que não há chunks órfãos de versões anteriores.
 *   Para perfis com até 80 chunks e API de embedding rápida (Gemini ~200ms/chunk),
 *   re-indexação completa leva ~10-20s — aceitável para operação administrativa.
 */
class KnowledgeIndexingService
{
    private ChunkingService   $chunker;
    private ?EmbeddingProvider $embedder = null;

    public function __construct()
    {
        $this->chunker = new ChunkingService();
    }

    /**
     * Re-indexa o conhecimento completo de um tenant.
     * Define set_time_limit(120) para evitar timeout em perfis grandes.
     *
     * @return array [
     *   'success'        => bool,
     *   'chunks_indexed' => int,
     *   'chunks_failed'  => int,
     *   'docs_created'   => int,
     *   'error'          => ?string,
     * ]
     */
    public function indexTenant(string $tenantId): array
    {
        set_time_limit(120);

        // Create embedder with tenant context so it can resolve API keys from DB
        $this->embedder = new EmbeddingProvider($tenantId);

        $profile = CompanyProfile::findByTenant($tenantId);

        if (!$profile) {
            return [
                'success'        => false,
                'chunks_indexed' => 0,
                'chunks_failed'  => 0,
                'docs_created'   => 0,
                'error'          => 'Perfil da empresa não encontrado para este tenant.',
            ];
        }

        return $this->indexProfile($profile, $tenantId);
    }

    /**
     * Re-indexa a partir de um perfil já carregado (evita query dupla).
     *
     * @return array [success, chunks_indexed, chunks_failed, docs_created, error]
     */
    public function indexProfile(array $profile, string $tenantId): array
    {
        // Ensure embedder has tenant context
        if (!$this->embedder) {
            $this->embedder = new EmbeddingProvider($tenantId);
        }

        error_log(sprintf(
            '[KnowledgeIndexing] Iniciando indexação para tenant=%s profile_version=%d',
            $tenantId,
            $profile['profile_version'] ?? 1
        ));

        // Marca como "processando" antes de iniciar
        CompanyProfile::setStatus($tenantId, CompanyProfile::STATUS_PROCESSING);

        try {
            // 1. Limpa índice anterior (chunks e embeddings em cascade via FK)
            $this->clearExistingIndex($tenantId);

            // 2. Gera documentos semânticos e chunks
            $allChunks = $this->chunker->profileToChunks($profile);

            if (empty($allChunks)) {
                CompanyProfile::setStatus($tenantId, CompanyProfile::STATUS_ERROR,
                    'Nenhum conteúdo encontrado no perfil para indexar.', 0);
                return [
                    'success'        => false,
                    'chunks_indexed' => 0,
                    'chunks_failed'  => 0,
                    'docs_created'   => 0,
                    'error'          => 'Nenhum conteúdo para indexar.',
                ];
            }

            // 3. Persiste documentos e chunks, obtém map de chunkId → metadados
            [$docCount, $chunkIdMetaMap] = $this->persistDocumentsAndChunks(
                $tenantId,
                $profile['id'],
                (int) ($profile['profile_version'] ?? 1),
                $allChunks
            );

            // 4. Gera e persiste embeddings
            $embeddingResult = $this->persistEmbeddings($tenantId, $chunkIdMetaMap);

            $totalIndexed = $embeddingResult['indexed'];
            $totalFailed  = $embeddingResult['failed'];

            // 5. Atualiza status no perfil
            if ($totalFailed > 0 && $totalIndexed === 0) {
                CompanyProfile::setStatus($tenantId, CompanyProfile::STATUS_ERROR,
                    "Falha ao gerar embeddings ({$totalFailed} chunks falharam).", 0);
            } else {
                CompanyProfile::setStatus($tenantId, CompanyProfile::STATUS_INDEXED,
                    null, $totalIndexed);
            }

            error_log(sprintf(
                '[KnowledgeIndexing] Concluído: docs=%d chunks_indexados=%d chunks_falhos=%d',
                $docCount, $totalIndexed, $totalFailed
            ));

            return [
                'success'        => $totalIndexed > 0,
                'chunks_indexed' => $totalIndexed,
                'chunks_failed'  => $totalFailed,
                'docs_created'   => $docCount,
                'error'          => $totalFailed > 0 ? "{$totalFailed} chunk(s) falharam no embedding." : null,
            ];

        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            error_log('[KnowledgeIndexing] Erro fatal: ' . $errorMsg);
            CompanyProfile::setStatus($tenantId, CompanyProfile::STATUS_ERROR, $errorMsg, 0);

            return [
                'success'        => false,
                'chunks_indexed' => 0,
                'chunks_failed'  => 0,
                'docs_created'   => 0,
                'error'          => $errorMsg,
            ];
        }
    }

    // ─── Helpers internos ──────────────────────────────────────────

    /**
     * Remove todos os documentos do tenant.
     * A cascade de FK em knowledge_chunks e knowledge_embeddings
     * garante que nada fica órfão.
     */
    private function clearExistingIndex(string $tenantId): void
    {
        KnowledgeDocument::deleteByTenant($tenantId);
        error_log('[KnowledgeIndexing] Índice anterior removido para tenant=' . $tenantId);
    }

    /**
     * Persiste documentos e chunks no banco.
     *
     * Agrupa os chunks por doc_type para criar um documento por grupo,
     * depois insere cada chunk com o documentId correto.
     *
     * @param  array $allChunks Saída de ChunkingService::profileToChunks()
     * @return array [int $docCount, array $chunkIdMetaMap]
     *               chunkIdMetaMap: [chunkId => [doc_type, content]]
     */
    private function persistDocumentsAndChunks(
        string $tenantId,
        string $profileId,
        int    $profileVersion,
        array  $allChunks
    ): array {
        // Agrupa chunks por doc_type para criar um doc por tipo
        $byDocType = [];
        foreach ($allChunks as $chunk) {
            $byDocType[$chunk['doc_type']][] = $chunk;
        }

        $chunkIdMetaMap = [];
        $docCount       = 0;

        foreach ($byDocType as $docType => $chunks) {
            // Cria documento (usa o conteúdo completo do primeiro chunk como referência)
            $docId = KnowledgeDocument::insert($tenantId, $profileId, [
                'doc_type'        => $docType,
                'title'           => $chunks[0]['title'] ?? $docType,
                'content'         => $chunks[0]['doc_content'] ?? $chunks[0]['content'],
                'profile_version' => $profileVersion,
            ]);
            $docCount++;

            // Cria chunks vinculados ao documento
            foreach ($chunks as $chunk) {
                $chunkId = KnowledgeChunk::insert($tenantId, $docId, [
                    'doc_type'    => $docType,
                    'chunk_index' => $chunk['chunk_index'],
                    'content'     => $chunk['content'],
                    'word_count'  => $chunk['word_count'],
                ]);

                $chunkIdMetaMap[$chunkId] = [
                    'doc_type' => $docType,
                    'content'  => $chunk['content'],
                ];
            }
        }

        return [$docCount, $chunkIdMetaMap];
    }

    /**
     * Gera embeddings em lote e persiste no banco.
     * Falhas parciais são toleradas — chunks sem embedding simplesmente não
     * participam de buscas vetoriais, mas o sistema continua funcional.
     *
     * @param  array $chunkIdMetaMap [chunkId => [doc_type, content]]
     * @return array [indexed => int, failed => int]
     */
    private function persistEmbeddings(string $tenantId, array $chunkIdMetaMap): array
    {
        if (empty($chunkIdMetaMap)) {
            return ['indexed' => 0, 'failed' => 0];
        }

        $chunkIds  = array_keys($chunkIdMetaMap);
        $texts     = array_map(fn($meta) => $meta['content'], $chunkIdMetaMap);
        $model     = $this->embedder->getModel();
        $dims      = $this->embedder->getDimensions();

        // embedBatch retorna array indexado igual ao input
        $vectors = $this->embedder->embedBatch(array_values($texts));

        $indexed = 0;
        $failed  = 0;

        foreach ($chunkIds as $pos => $chunkId) {
            $vector = $vectors[$pos] ?? [];

            if (empty($vector)) {
                $failed++;
                error_log('[KnowledgeIndexing] Embedding vazio para chunk=' . $chunkId);
                continue;
            }

            KnowledgeEmbedding::upsert($tenantId, $chunkId, $vector, $model, $dims);
            $indexed++;
        }

        return ['indexed' => $indexed, 'failed' => $failed];
    }
}
