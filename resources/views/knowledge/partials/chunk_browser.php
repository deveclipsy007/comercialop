<?php
/**
 * Browser de Documentos Indexados — Knowledge Base
 *
 * @var array $documents
 * @var bool  $hasIndex
 */

$docTypeLabels = [
    'identity'      => ['label' => 'Identidade',    'icon' => 'apartment'],
    'services'      => ['label' => 'Serviços',       'icon' => 'design_services'],
    'differentials' => ['label' => 'Diferenciais',   'icon' => 'star'],
    'icp'           => ['label' => 'ICP',            'icon' => 'person_search'],
    'cases'         => ['label' => 'Cases',          'icon' => 'emoji_events'],
    'objections'    => ['label' => 'Objeções',       'icon' => 'shield_question'],
    'competitors'   => ['label' => 'Concorrentes',   'icon' => 'groups'],
    'custom'        => ['label' => 'Ctx. Livre',     'icon' => 'text_snippet'],
];

if (empty($documents)): ?>
<div class="bg-surface border border-stroke rounded-xl p-5">
    <h3 class="text-xs font-semibold text-muted uppercase tracking-widest mb-4">Documentos Indexados</h3>
    <div class="text-center py-8 space-y-2">
        <span class="material-symbols-outlined text-4xl text-muted">folder_open</span>
        <p class="text-sm text-muted">Nenhum documento indexado.</p>
    </div>
</div>
<?php return; endif; ?>

<div class="bg-surface border border-stroke rounded-xl p-5 space-y-4">
    <h3 class="text-xs font-semibold text-muted uppercase tracking-widest">Documentos Indexados</h3>

    <div class="space-y-2 max-h-[480px] overflow-y-auto hide-scrollbar">
        <?php foreach ($documents as $doc):
            $typeInfo = $docTypeLabels[$doc['doc_type']] ?? ['label' => ucfirst($doc['doc_type']), 'icon' => 'description'];
        ?>
        <div class="bg-surface2 border border-stroke rounded-lg p-3 hover:border-lime/30 transition-all group"
             data-doc-row data-doc-id="<?= e($doc['id']) ?>">

            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-base text-lime flex-shrink-0"><?= $typeInfo['icon'] ?></span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-text truncate"><?= e($doc['title']) ?></p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[10px] bg-lime/10 text-lime px-1.5 py-0.5 rounded"><?= e($typeInfo['label']) ?></span>
                            <span class="text-[10px] text-subtle">v<?= (int)($doc['profile_version'] ?? 1) ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($hasIndex): ?>
                <button data-delete-doc="<?= e($doc['id']) ?>"
                        title="Remover documento"
                        class="text-muted hover:text-red-400 opacity-0 group-hover:opacity-100 transition-all flex-shrink-0">
                    <span class="material-symbols-outlined text-base">delete</span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Preview do conteúdo -->
            <?php $preview = mb_substr(strip_tags($doc['content'] ?? ''), 0, 100); ?>
            <?php if ($preview): ?>
            <p class="text-[11px] text-subtle mt-2 leading-relaxed line-clamp-2"><?= e($preview) ?>…</p>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
</div>
