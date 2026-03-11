<?php
/**
 * Painel de Status de Indexação — Knowledge Base
 *
 * @var array|null $profile
 * @var bool       $hasIndex
 * @var int        $chunks
 * @var array      $docsByType
 * @var int        $traceCount
 */

$status       = $profile ? ($profile['indexing_status'] ?? 'pending') : 'no_profile';
$profileVer   = (int) ($profile['profile_version'] ?? 0);
$lastIndexed  = $profile['last_indexed_at'] ?? null;
$indexError   = $profile['indexing_error'] ?? null;
$chunksCount  = (int) ($profile['chunks_count'] ?? 0);

$docTypeLabels = [
    'identity'      => 'Identidade',
    'services'      => 'Serviços',
    'differentials' => 'Diferenciais',
    'icp'           => 'ICP',
    'cases'         => 'Cases',
    'objections'    => 'Objeções',
    'competitors'   => 'Concorrentes',
    'custom'        => 'Contexto Livre',
];
?>

<!-- ── Status Card ─────────────────────────────────────────────────── -->
<div class="bg-surface border border-stroke rounded-xl p-5 space-y-4">
    <h3 class="text-xs font-semibold text-muted uppercase tracking-widest">Status do Índice RAG</h3>

    <?php if (!$profile): ?>
        <div class="text-center py-6 space-y-2">
            <span class="material-symbols-outlined text-4xl text-muted">database</span>
            <p class="text-sm text-muted">Nenhum perfil configurado.</p>
            <p class="text-xs text-subtle">Preencha o formulário ao lado e clique em "Salvar e Indexar".</p>
        </div>
    <?php else: ?>

        <!-- Status visual -->
        <div class="flex items-center gap-3 p-3 bg-surface2 rounded-lg">
            <?php if ($status === 'indexed'): ?>
                <div class="w-3 h-3 rounded-full bg-mint flex-shrink-0"></div>
                <div>
                    <p class="text-sm font-semibold text-mint">Indexado</p>
                    <?php if ($lastIndexed): ?>
                    <p class="text-xs text-subtle"><?= e(date('d/m/Y H:i', strtotime($lastIndexed))) ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($status === 'processing'): ?>
                <div class="w-3 h-3 rounded-full bg-yellow-400 animate-pulse flex-shrink-0"></div>
                <div>
                    <p class="text-sm font-semibold text-yellow-400">Indexando…</p>
                    <p class="text-xs text-subtle">Atualizando automaticamente</p>
                </div>
            <?php elseif ($status === 'error'): ?>
                <div class="w-3 h-3 rounded-full bg-red-400 flex-shrink-0"></div>
                <div>
                    <p class="text-sm font-semibold text-red-400">Erro na indexação</p>
                    <?php if ($indexError): ?>
                    <p class="text-xs text-red-400/70 mt-0.5 break-all"><?= e(substr($indexError, 0, 80)) ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="w-3 h-3 rounded-full bg-muted flex-shrink-0"></div>
                <div>
                    <p class="text-sm font-semibold text-muted">Aguardando indexação</p>
                    <p class="text-xs text-subtle">Salve o perfil para indexar</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Métricas do índice -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-surface2 rounded-lg p-3 text-center">
                <p class="text-2xl font-bold text-text"><?= $chunksCount ?></p>
                <p class="text-xs text-muted mt-0.5">Chunks</p>
            </div>
            <div class="bg-surface2 rounded-lg p-3 text-center">
                <p class="text-2xl font-bold text-text"><?= count($docsByType) ?></p>
                <p class="text-xs text-muted mt-0.5">Doc types</p>
            </div>
            <div class="bg-surface2 rounded-lg p-3 text-center">
                <p class="text-2xl font-bold text-text">v<?= $profileVer ?></p>
                <p class="text-xs text-muted mt-0.5">Versão</p>
            </div>
            <div class="bg-surface2 rounded-lg p-3 text-center">
                <p class="text-2xl font-bold text-text"><?= $traceCount ?></p>
                <p class="text-xs text-muted mt-0.5">Análises</p>
            </div>
        </div>

        <!-- Doc types indexados -->
        <?php if (!empty($docsByType)): ?>
        <div class="space-y-2">
            <p class="text-xs text-muted">Seções indexadas</p>
            <?php foreach ($docsByType as $type => $count): ?>
            <div class="flex items-center justify-between">
                <span class="text-xs text-text"><?= e($docTypeLabels[$type] ?? ucfirst($type)) ?></span>
                <span class="text-xs bg-lime/10 text-lime px-2 py-0.5 rounded-full"><?= $count ?> doc</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Fallback info -->
        <?php if (!$hasIndex): ?>
        <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-3">
            <p class="text-xs text-yellow-400">
                <span class="font-semibold">Sem embeddings.</span> As análises usarão o contexto padrão da agência até que o índice seja gerado.
            </p>
        </div>
        <?php else: ?>
        <div class="bg-mint/10 border border-mint/20 rounded-lg p-3">
            <p class="text-xs text-mint">
                <span class="font-semibold">RAG ativo.</span> Todas as análises de IA estão usando o contexto personalizado desta empresa.
            </p>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ── Audit Trail (últimas análises) ────────────────────────────── -->
<?php if (!empty($traces)): ?>
<div class="bg-surface border border-stroke rounded-xl p-5 space-y-4">
    <h3 class="text-xs font-semibold text-muted uppercase tracking-widest">Audit Trail (últimas análises)</h3>

    <div class="space-y-2 max-h-80 overflow-y-auto hide-scrollbar">
        <?php foreach ($traces as $trace): ?>
        <?php
            $src = $trace['context_source'] ?? 'default';
            $srcColors = [
                'rag'     => 'text-lime',
                'legacy'  => 'text-yellow-400',
                'default' => 'text-muted',
            ];
            $srcColor = $srcColors[$src] ?? 'text-muted';
        ?>
        <div class="flex items-center justify-between py-2 border-b border-stroke/50 last:border-0">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs <?= $srcColor ?> font-mono font-semibold uppercase flex-shrink-0"><?= e($src) ?></span>
                <span class="text-xs text-text truncate"><?= e($trace['operation'] ?? '') ?></span>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <?php if ($trace['latency_ms']): ?>
                <span class="text-xs text-subtle"><?= $trace['latency_ms'] ?>ms</span>
                <?php endif; ?>
                <span class="text-xs text-subtle"><?= e(date('H:i', strtotime($trace['created_at']))) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
