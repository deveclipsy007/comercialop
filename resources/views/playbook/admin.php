<?php
/**
 * Playbook de Vendas — Editor (Admin)
 * Criação e edição de módulos e blocos de conteúdo.
 */
$modules = $modules ?? [];
$blockTypes = $blockTypes ?? [];
?>

<div class="min-h-screen p-4 md:p-8 max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-xl bg-lime/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lime text-xl">auto_stories</span>
                </div>
                <h1 class="text-2xl font-bold text-text">Playbook de Vendas</h1>
                <span class="px-3 py-1 text-xs font-bold rounded-pill bg-lime/10 text-lime border border-lime/20">EDITOR</span>
            </div>
            <p class="text-sm text-muted ml-[52px]">Monte e organize o playbook comercial da sua operação. Adicione módulos, conteúdos, vídeos, scripts e checklists.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/playbook" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">visibility</span>
                Ver como equipe
            </a>
            <button onclick="openNewModuleModal()" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">
                <span class="material-symbols-outlined text-sm">add</span>
                Novo Módulo
            </button>
        </div>
    </div>

    <!-- Modules List -->
    <?php if (empty($modules)): ?>
    <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-2xl bg-surface2 border border-stroke flex items-center justify-center mb-6">
            <span class="material-symbols-outlined text-4xl text-muted">auto_stories</span>
        </div>
        <h2 class="text-xl font-bold text-text mb-2">Nenhum módulo criado ainda</h2>
        <p class="text-sm text-muted mb-6 max-w-md">Comece criando o primeiro módulo do seu playbook. Você pode adicionar conteúdos como textos, vídeos, scripts, checklists e documentos.</p>
        <button onclick="openNewModuleModal()" class="flex items-center gap-2 h-11 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">
            <span class="material-symbols-outlined text-sm">add</span>
            Criar Primeiro Módulo
        </button>
    </div>
    <?php else: ?>

    <div id="modulesContainer" class="space-y-6">
        <?php foreach ($modules as $i => $module): ?>
        <div class="module-card bg-surface border border-stroke rounded-2xl overflow-hidden" data-module-id="<?= e($module['id']) ?>">
            <!-- Module Header -->
            <div class="flex items-center justify-between px-6 py-4 bg-surface2/50 border-b border-stroke cursor-pointer" onclick="toggleModuleContent('<?= e($module['id']) ?>')">
                <div class="flex items-center gap-4">
                    <div class="w-3 h-3 rounded-full flex-shrink-0" style="background: <?= e($module['color'] ?? '#E1FB15') ?>"></div>
                    <span class="material-symbols-outlined text-xl" style="color: <?= e($module['color'] ?? '#E1FB15') ?>"><?= e($module['icon'] ?? 'menu_book') ?></span>
                    <div>
                        <h3 class="text-base font-bold text-text"><?= e($module['title']) ?></h3>
                        <?php if (!empty($module['description'])): ?>
                        <p class="text-xs text-muted mt-0.5"><?= e($module['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill <?= $module['is_published'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20' ?>">
                        <?= $module['is_published'] ? 'PUBLICADO' : 'RASCUNHO' ?>
                    </span>
                    <span class="text-xs text-subtle"><?= count($module['blocks'] ?? []) ?> bloco(s)</span>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="event.stopPropagation(); togglePublish('<?= e($module['id']) ?>', this)" class="p-2 rounded-lg hover:bg-surface3 transition-all text-muted hover:text-text" title="<?= $module['is_published'] ? 'Despublicar' : 'Publicar' ?>">
                        <span class="material-symbols-outlined text-lg"><?= $module['is_published'] ? 'visibility_off' : 'visibility' ?></span>
                    </button>
                    <button onclick="event.stopPropagation(); openEditModuleModal('<?= e($module['id']) ?>', <?= e(json_encode($module)) ?>)" class="p-2 rounded-lg hover:bg-surface3 transition-all text-muted hover:text-text" title="Editar módulo">
                        <span class="material-symbols-outlined text-lg">edit</span>
                    </button>
                    <button onclick="event.stopPropagation(); deleteModule('<?= e($module['id']) ?>')" class="p-2 rounded-lg hover:bg-red-500/10 transition-all text-muted hover:text-red-400" title="Excluir módulo">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                    <span class="material-symbols-outlined text-lg text-muted module-chevron transition-transform" id="chevron-<?= e($module['id']) ?>">expand_more</span>
                </div>
            </div>

            <!-- Module Content (Blocks) -->
            <div id="content-<?= e($module['id']) ?>" class="module-content hidden">
                <div class="p-6 space-y-3" id="blocks-<?= e($module['id']) ?>">
                    <?php if (empty($module['blocks'])): ?>
                    <div class="text-center py-8 text-muted text-sm" id="empty-<?= e($module['id']) ?>">
                        <span class="material-symbols-outlined text-2xl mb-2 block">note_add</span>
                        Nenhum bloco neste módulo. Adicione conteúdo abaixo.
                    </div>
                    <?php else: ?>
                    <?php foreach ($module['blocks'] as $block): ?>
                    <div class="block-card flex items-start gap-4 p-4 bg-surface2/60 rounded-xl border border-white/5 hover:border-white/10 transition-all group" data-block-id="<?= e($block['id']) ?>">
                        <div class="w-9 h-9 rounded-lg bg-surface3 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-base text-muted"><?= e($blockTypes[$block['type']]['icon'] ?? 'article') ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-subtle"><?= e($blockTypes[$block['type']]['label'] ?? $block['type']) ?></span>
                            </div>
                            <h4 class="text-sm font-semibold text-text truncate"><?= e($block['title'] ?: '(Sem título)') ?></h4>
                            <?php if (!empty($block['content'])): ?>
                            <p class="text-xs text-muted mt-1 line-clamp-2"><?= e(mb_substr(strip_tags($block['content']), 0, 150)) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="openEditBlockModal('<?= e($block['id']) ?>', <?= e(json_encode($block)) ?>)" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all" title="Editar">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <button onclick="deleteBlock('<?= e($block['id']) ?>')" class="p-1.5 rounded-lg hover:bg-red-500/10 text-muted hover:text-red-400 transition-all" title="Excluir">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Block Button -->
                <div class="px-6 pb-5 pt-2">
                    <button onclick="openNewBlockModal('<?= e($module['id']) ?>')" class="flex items-center gap-2 w-full justify-center py-3 rounded-xl border-2 border-dashed border-stroke hover:border-lime/40 text-muted hover:text-lime text-sm font-medium transition-all">
                        <span class="material-symbols-outlined text-lg">add_circle</span>
                        Adicionar Bloco de Conteúdo
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: New/Edit Module -->
<div id="moduleModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-lg mx-4 shadow-soft animate-fadeInUp">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke">
            <h3 id="moduleModalTitle" class="text-lg font-bold text-text">Novo Módulo</h3>
            <button onclick="closeModal('moduleModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="moduleForm" method="POST" action="/playbook/module" class="p-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="ajax" value="1">
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1.5">Título do Módulo</label>
                <input type="text" name="title" id="moduleTitle" required placeholder="Ex: Processo de Qualificação" class="w-full h-11 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1.5">Descrição</label>
                <textarea name="description" id="moduleDescription" rows="2" placeholder="Breve descrição do módulo..." class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1.5">Ícone</label>
                    <select name="icon" id="moduleIcon" class="w-full h-11 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                        <option value="menu_book">menu_book - Livro</option>
                        <option value="psychology">psychology - Mente</option>
                        <option value="rocket_launch">rocket_launch - Foguete</option>
                        <option value="handshake">handshake - Handshake</option>
                        <option value="groups">groups - Equipe</option>
                        <option value="target">target - Alvo</option>
                        <option value="call">call - Ligação</option>
                        <option value="chat">chat - Chat</option>
                        <option value="checklist">checklist - Checklist</option>
                        <option value="star">star - Estrela</option>
                        <option value="trophy">trophy - Troféu</option>
                        <option value="school">school - Treinamento</option>
                        <option value="person_search">person_search - Prospecção</option>
                        <option value="trending_up">trending_up - Crescimento</option>
                        <option value="shield">shield - Objeções</option>
                        <option value="description">description - Documentos</option>
                        <option value="play_circle">play_circle - Vídeo</option>
                        <option value="lightbulb">lightbulb - Dicas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1.5">Cor do Módulo</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="color" id="moduleColor" value="#E1FB15" class="w-11 h-11 rounded-xl border border-stroke bg-surface2 cursor-pointer">
                        <span id="moduleColorHex" class="text-xs text-muted">#E1FB15</span>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('moduleModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
                <button type="submit" class="h-10 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Salvar Módulo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New/Edit Block -->
<div id="blockModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-2xl mx-4 shadow-soft animate-fadeInUp max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke sticky top-0 bg-surface z-10">
            <h3 id="blockModalTitle" class="text-lg font-bold text-text">Novo Bloco</h3>
            <button onclick="closeModal('blockModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="blockForm" method="POST" action="/playbook/block" class="p-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="module_id" id="blockModuleId" value="">

            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1.5">Tipo de Conteúdo</label>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($blockTypes as $typeKey => $typeInfo): ?>
                    <label class="block-type-option flex items-center gap-2 p-3 bg-surface2 border border-stroke rounded-xl cursor-pointer hover:border-lime/30 transition-all has-[:checked]:border-lime/50 has-[:checked]:bg-lime/5">
                        <input type="radio" name="type" value="<?= $typeKey ?>" class="sr-only" <?= $typeKey === 'text' ? 'checked' : '' ?>>
                        <span class="material-symbols-outlined text-base text-muted"><?= $typeInfo['icon'] ?></span>
                        <span class="text-xs font-medium text-text"><?= $typeInfo['label'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1.5">Título</label>
                <input type="text" name="title" id="blockTitle" required placeholder="Título do bloco" class="w-full h-11 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all">
            </div>

            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1.5">Conteúdo</label>
                <div id="contentHint" class="text-xs text-subtle mb-2">Escreva o texto, instrução ou orientação deste bloco.</div>
                <textarea name="content" id="blockContent" rows="8" placeholder="Digite o conteúdo aqui..." class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all resize-y font-mono"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('blockModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
                <button type="submit" class="h-10 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Salvar Bloco</button>
            </div>
        </form>
    </div>
</div>

<style>
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.animate-fadeInUp { animation: fadeInUp 0.3s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.module-content.expanded { display: block; }
</style>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
let editingModuleId = null;
let editingBlockId = null;

// ─── Module Management ──────────────────────────────────────
function toggleModuleContent(moduleId) {
    const content = document.getElementById('content-' + moduleId);
    const chevron = document.getElementById('chevron-' + moduleId);
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        content.classList.add('hidden');
        chevron.style.transform = '';
    }
}

function openNewModuleModal() {
    editingModuleId = null;
    document.getElementById('moduleModalTitle').textContent = 'Novo Módulo';
    document.getElementById('moduleForm').action = '/playbook/module';
    document.getElementById('moduleTitle').value = '';
    document.getElementById('moduleDescription').value = '';
    document.getElementById('moduleIcon').value = 'menu_book';
    document.getElementById('moduleColor').value = '#E1FB15';
    document.getElementById('moduleColorHex').textContent = '#E1FB15';
    openModal('moduleModal');
}

function openEditModuleModal(id, module) {
    editingModuleId = id;
    document.getElementById('moduleModalTitle').textContent = 'Editar Módulo';
    document.getElementById('moduleForm').action = '/playbook/module/' + id + '/update';
    document.getElementById('moduleTitle').value = module.title || '';
    document.getElementById('moduleDescription').value = module.description || '';
    document.getElementById('moduleIcon').value = module.icon || 'menu_book';
    document.getElementById('moduleColor').value = module.color || '#E1FB15';
    document.getElementById('moduleColorHex').textContent = module.color || '#E1FB15';
    openModal('moduleModal');
}

function deleteModule(id) {
    if (!confirm('Excluir este módulo e todos os seus blocos?')) return;
    submitAction('/playbook/module/' + id + '/delete', {});
}

function togglePublish(id, btn) {
    fetch('/playbook/module/' + id + '/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(csrfToken) + '&ajax=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) location.reload();
    });
}

// ─── Block Management ───────────────────────────────────────
function openNewBlockModal(moduleId) {
    editingBlockId = null;
    document.getElementById('blockModalTitle').textContent = 'Novo Bloco';
    document.getElementById('blockForm').action = '/playbook/block';
    document.getElementById('blockModuleId').value = moduleId;
    document.getElementById('blockTitle').value = '';
    document.getElementById('blockContent').value = '';
    document.querySelector('input[name="type"][value="text"]').checked = true;
    updateContentHint('text');
    openModal('blockModal');
}

function openEditBlockModal(id, block) {
    editingBlockId = id;
    document.getElementById('blockModalTitle').textContent = 'Editar Bloco';
    document.getElementById('blockForm').action = '/playbook/block/' + id + '/update';
    document.getElementById('blockModuleId').value = block.module_id || '';
    document.getElementById('blockTitle').value = block.title || '';
    document.getElementById('blockContent').value = block.content || '';
    const typeRadio = document.querySelector('input[name="type"][value="' + block.type + '"]');
    if (typeRadio) typeRadio.checked = true;
    updateContentHint(block.type);
    openModal('blockModal');
}

function deleteBlock(id) {
    if (!confirm('Excluir este bloco?')) return;
    submitAction('/playbook/block/' + id + '/delete', {});
}

// ─── Content Type Hints ─────────────────────────────────────
const typeHints = {
    text: 'Escreva o texto, instrução ou orientação deste bloco.',
    video: 'Cole a URL do vídeo (YouTube, Vimeo, Loom, etc).',
    document: 'Cole o link do documento ou arquivo para download.',
    checklist: 'Escreva um item por linha. Cada linha será um item da checklist.',
    script: 'Escreva o script de abordagem ou template de mensagem.',
    tip: 'Escreva uma dica, boa prática ou ponto de atenção importante.'
};

document.querySelectorAll('input[name="type"]').forEach(radio => {
    radio.addEventListener('change', () => updateContentHint(radio.value));
});

function updateContentHint(type) {
    document.getElementById('contentHint').textContent = typeHints[type] || '';
}

// ─── Helpers ────────────────────────────────────────────────
function openModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function submitAction(url, extra) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_csrf'; csrf.value = csrfToken;
    form.appendChild(csrf);

    Object.entries(extra).forEach(([k, v]) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = k; input.value = v;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

// Form AJAX handlers
document.getElementById('moduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new URLSearchParams(new FormData(this));
    fetch(this.action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { if (data.ok) location.reload(); })
        .catch(() => this.submit());
});

document.getElementById('blockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new URLSearchParams(new FormData(this));
    fetch(this.action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { if (data.ok) location.reload(); })
        .catch(() => this.submit());
});

// Color picker live update
document.getElementById('moduleColor').addEventListener('input', function() {
    document.getElementById('moduleColorHex').textContent = this.value;
});

// Close modals on backdrop click
['moduleModal', 'blockModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>
