<?php
/**
 * Formulários Dinâmicos de Qualificação — Listagem
 */
$pageTitle = 'Formulários de Qualificação';
$forms = $forms ?? [];
?>

<div class="p-4 md:p-8 max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <h2 class="text-xl font-black text-text">Formulários de Qualificação</h2>
                <span class="px-3 py-0.5 text-[10px] font-bold rounded-pill bg-purple-500/10 text-purple-400 border border-purple-500/20 uppercase tracking-wider"><?= count($forms) ?> formulários</span>
            </div>
            <p class="text-sm text-muted">Crie formulários inteligentes para qualificação de leads, discovery calls e diagnósticos comerciais.</p>
        </div>
        <a href="/forms/new" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow flex-shrink-0">
            <span class="material-symbols-outlined text-sm">add</span>
            Novo Formulário
        </a>
    </div>

    <!-- Empty State -->
    <?php if (empty($forms)): ?>
    <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-2xl bg-surface2 border border-stroke flex items-center justify-center mb-6">
            <span class="material-symbols-outlined text-4xl text-muted">dynamic_form</span>
        </div>
        <h3 class="text-lg font-bold text-text mb-2">Nenhum formulário criado</h3>
        <p class="text-sm text-muted mb-6 max-w-md">Crie seu primeiro formulário de qualificação para começar a captar dados estratégicos dos seus leads.</p>
        <a href="/forms/new" class="flex items-center gap-2 h-11 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow">
            <span class="material-symbols-outlined text-sm">add</span>
            Criar Formulário
        </a>
    </div>
    <?php else: ?>

    <!-- Forms Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($forms as $form): ?>
        <div class="bg-surface border border-stroke rounded-2xl overflow-hidden hover:border-white/10 transition-all group">
            <!-- Card Header -->
            <div class="p-5 pb-3">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
                        <?= $form['status'] === 'published' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-yellow-500/10 text-yellow-400' ?>">
                        <span class="material-symbols-outlined text-lg"><?= $form['status'] === 'published' ? 'check_circle' : 'edit_note' ?></span>
                    </div>
                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase tracking-wider
                        <?= $form['status'] === 'published'
                            ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                            : ($form['status'] === 'archived'
                                ? 'bg-red-500/10 text-red-400 border border-red-500/20'
                                : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20') ?>">
                        <?= $form['status'] === 'published' ? 'Publicado' : ($form['status'] === 'archived' ? 'Arquivado' : 'Rascunho') ?>
                    </span>
                </div>
                <h3 class="text-sm font-bold text-text mb-1 group-hover:text-lime transition-colors truncate"><?= e($form['title']) ?></h3>
                <?php if (!empty($form['description'])): ?>
                <p class="text-xs text-muted line-clamp-2"><?= e($form['description']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="flex items-center gap-4 px-5 py-3 border-t border-stroke/50 bg-surface2/30">
                <div class="flex items-center gap-1.5 text-xs text-subtle">
                    <span class="material-symbols-outlined text-sm">help</span>
                    <?= $form['question_count'] ?> perguntas
                </div>
                <div class="flex items-center gap-1.5 text-xs text-subtle">
                    <span class="material-symbols-outlined text-sm">person</span>
                    <?= $form['response_count'] ?> respostas
                </div>
                <div class="flex items-center gap-1.5 text-xs text-subtle ml-auto">
                    <span class="material-symbols-outlined text-sm">schedule</span>
                    <?= timeAgo($form['updated_at']) ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1 px-4 py-3 border-t border-stroke/50">
                <a href="/forms/<?= e($form['id']) ?>/builder" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:text-lime hover:bg-lime/5 transition-all" title="Editar">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    Editar
                </a>
                <a href="/forms/<?= e($form['id']) ?>/fill" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:text-text hover:bg-surface3 transition-all" title="Preencher">
                    <span class="material-symbols-outlined text-sm">assignment</span>
                    Preencher
                </a>
                <?php if ($form['status'] === 'published'): ?>
                <button onclick="copyPublicLink('<?= e($form['public_slug']) ?>')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:text-emerald-400 hover:bg-emerald-500/5 transition-all" title="Copiar Link">
                    <span class="material-symbols-outlined text-sm">link</span>
                    Link
                </button>
                <?php endif; ?>
                <div class="ml-auto flex items-center gap-1">
                    <form method="POST" action="/forms/<?= e($form['id']) ?>/duplicate" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="p-1.5 rounded-lg text-muted hover:text-text hover:bg-surface3 transition-all" title="Duplicar">
                            <span class="material-symbols-outlined text-sm">content_copy</span>
                        </button>
                    </form>
                    <button onclick="confirmDelete('<?= e($form['id']) ?>', '<?= e($form['title']) ?>')" class="p-1.5 rounded-lg text-muted hover:text-red-400 hover:bg-red-500/10 transition-all" title="Excluir">
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Delete confirm -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-sm mx-4 p-6 shadow-soft text-center">
        <div class="w-14 h-14 mx-auto rounded-full bg-red-500/10 flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-2xl text-red-400">delete_forever</span>
        </div>
        <h3 class="text-base font-bold text-text mb-1">Excluir formulário?</h3>
        <p class="text-sm text-muted mb-5" id="deleteFormName"></p>
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 h-10 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
            <form id="deleteForm" method="POST" class="flex-1">
                <?= csrf_field() ?>
                <button type="submit" class="w-full h-10 rounded-pill bg-red-500 text-white text-sm font-bold hover:brightness-110 transition-all">Excluir</button>
            </form>
        </div>
    </div>
</div>

<script>
function copyPublicLink(slug) {
    const url = window.location.origin + '/f/' + slug;
    navigator.clipboard.writeText(url).then(() => {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-6 right-6 z-50 bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 px-4 py-2.5 rounded-xl text-sm font-medium shadow-lg backdrop-blur-sm';
        toast.innerHTML = '<span class="material-symbols-outlined text-sm align-middle mr-1">check</span> Link copiado!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    });
}

function confirmDelete(id, title) {
    document.getElementById('deleteFormName').textContent = '"' + title + '"';
    document.getElementById('deleteForm').action = '/forms/' + id + '/delete';
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
