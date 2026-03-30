<?php
/**
 * Playbook de Vendas — Painel Admin (layout.admin)
 * Criação e gestão completa de módulos e blocos dentro do admin.
 */
$pageTitle = 'Playbook de Vendas';
$modules = $modules ?? [];
$blockTypes = $blockTypes ?? [];
?>

<div class="p-4 md:p-8 max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <h2 class="text-xl font-black text-text">Playbook de Vendas</h2>
                <span class="px-3 py-0.5 text-[10px] font-bold rounded-pill bg-lime/10 text-lime border border-lime/20 uppercase tracking-wider">Editor</span>
            </div>
            <p class="text-sm text-muted">Monte o playbook comercial da operação. Organize módulos, conteúdos, vídeos, scripts e checklists para a equipe.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/playbook" target="_blank" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">visibility</span>
                Pré-visualizar
            </a>
            <button onclick="openNewModuleModal()" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow">
                <span class="material-symbols-outlined text-sm">add</span>
                Novo Módulo
            </button>
        </div>
    </div>

    <!-- Stats -->
    <?php
    $totalModules = count($modules);
    $publishedModules = count(array_filter($modules, fn($m) => $m['is_published']));
    $totalBlocks = array_sum(array_map(fn($m) => count($m['blocks'] ?? []), $modules));
    ?>
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="bg-surface border border-stroke rounded-2xl p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-lime/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-lime text-lg">library_books</span>
            </div>
            <div>
                <p class="text-lg font-bold text-text"><?= $totalModules ?></p>
                <p class="text-xs text-muted">Módulos criados</p>
            </div>
        </div>
        <div class="bg-surface border border-stroke rounded-2xl p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-emerald-400 text-lg">check_circle</span>
            </div>
            <div>
                <p class="text-lg font-bold text-text"><?= $publishedModules ?></p>
                <p class="text-xs text-muted">Publicados</p>
            </div>
        </div>
        <div class="bg-surface border border-stroke rounded-2xl p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-400 text-lg">widgets</span>
            </div>
            <div>
                <p class="text-lg font-bold text-text"><?= $totalBlocks ?></p>
                <p class="text-xs text-muted">Blocos de conteúdo</p>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <?php if (empty($modules)): ?>
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 rounded-2xl bg-surface2 border border-stroke flex items-center justify-center mb-6">
            <span class="material-symbols-outlined text-4xl text-muted">auto_stories</span>
        </div>
        <h3 class="text-lg font-bold text-text mb-2">Nenhum módulo criado ainda</h3>
        <p class="text-sm text-muted mb-6 max-w-md">Comece montando a estrutura do seu playbook. Crie módulos como "ICP e Perfil Ideal", "Processo de Qualificação", "Scripts", etc.</p>
        <button onclick="openNewModuleModal()" class="flex items-center gap-2 h-11 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow">
            <span class="material-symbols-outlined text-sm">add</span>
            Criar Primeiro Módulo
        </button>
    </div>
    <?php else: ?>

    <!-- Modules List -->
    <div id="modulesContainer" class="space-y-5">
        <?php foreach ($modules as $module): ?>
        <div class="module-card bg-surface border border-stroke rounded-2xl overflow-hidden" data-module-id="<?= e($module['id']) ?>">

            <!-- Module Header -->
            <div class="flex items-center justify-between px-5 py-4 bg-surface2/50 border-b border-stroke cursor-pointer" onclick="toggleModuleContent('<?= e($module['id']) ?>')">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: <?= e($module['color'] ?? '#E1FB15') ?>"></div>
                    <span class="material-symbols-outlined text-lg" style="color: <?= e($module['color'] ?? '#E1FB15') ?>"><?= e($module['icon'] ?? 'menu_book') ?></span>
                    <div>
                        <h3 class="text-sm font-bold text-text"><?= e($module['title']) ?></h3>
                        <?php if (!empty($module['description'])): ?>
                        <p class="text-xs text-muted mt-0.5"><?= e($module['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill <?= $module['is_published'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20' ?>">
                        <?= $module['is_published'] ? 'PUBLICADO' : 'RASCUNHO' ?>
                    </span>
                    <span class="text-xs text-subtle"><?= count($module['blocks'] ?? []) ?> bloco(s)</span>
                </div>
                <div class="flex items-center gap-1">
                    <button onclick="event.stopPropagation(); togglePublish('<?= e($module['id']) ?>')" class="p-2 rounded-lg hover:bg-surface3 transition-all text-muted hover:text-text" title="<?= $module['is_published'] ? 'Despublicar' : 'Publicar' ?>">
                        <span class="material-symbols-outlined text-base"><?= $module['is_published'] ? 'visibility_off' : 'visibility' ?></span>
                    </button>
                    <button onclick="event.stopPropagation(); openEditModuleModal('<?= e($module['id']) ?>', <?= e(json_encode($module)) ?>)" class="p-2 rounded-lg hover:bg-surface3 transition-all text-muted hover:text-text" title="Editar">
                        <span class="material-symbols-outlined text-base">edit</span>
                    </button>
                    <button onclick="event.stopPropagation(); deleteModule('<?= e($module['id']) ?>')" class="p-2 rounded-lg hover:bg-red-500/10 transition-all text-muted hover:text-red-400" title="Excluir">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                    <span class="material-symbols-outlined text-base text-muted transition-transform" id="chevron-<?= e($module['id']) ?>">expand_more</span>
                </div>
            </div>

            <!-- Module Blocks -->
            <div id="content-<?= e($module['id']) ?>" class="hidden">
                <div class="p-5 space-y-2" id="blocks-<?= e($module['id']) ?>">
                    <?php if (empty($module['blocks'])): ?>
                    <div class="text-center py-6 text-muted text-sm" id="empty-<?= e($module['id']) ?>">
                        <span class="material-symbols-outlined text-2xl mb-2 block">note_add</span>
                        Nenhum bloco neste módulo.
                    </div>
                    <?php else: ?>
                    <?php foreach ($module['blocks'] as $block): ?>
                    <div class="block-card flex items-start gap-3 p-3 bg-surface2/60 rounded-xl border border-white/5 hover:border-white/10 transition-all group" data-block-id="<?= e($block['id']) ?>">
                        <div class="w-8 h-8 rounded-lg bg-surface3 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-sm text-muted"><?= e($blockTypes[$block['type']]['icon'] ?? 'article') ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-subtle"><?= e($blockTypes[$block['type']]['label'] ?? $block['type']) ?></span>
                            </div>
                            <h4 class="text-sm font-semibold text-text truncate"><?= e($block['title'] ?: '(Sem título)') ?></h4>
                            <?php if (!empty($block['content'])): ?>
                            <p class="text-xs text-muted mt-0.5 truncate"><?= e(mb_substr(strip_tags($block['content']), 0, 120)) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="openEditBlockModal('<?= e($block['id']) ?>', <?= e(json_encode($block)) ?>)" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text" title="Editar">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <button onclick="deleteBlock('<?= e($block['id']) ?>')" class="p-1.5 rounded-lg hover:bg-red-500/10 text-muted hover:text-red-400" title="Excluir">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="px-5 pb-4 pt-1">
                    <button onclick="openNewBlockModal('<?= e($module['id']) ?>')" class="flex items-center gap-2 w-full justify-center py-2.5 rounded-xl border-2 border-dashed border-stroke hover:border-lime/40 text-muted hover:text-lime text-sm font-medium transition-all">
                        <span class="material-symbols-outlined text-base">add_circle</span>
                        Adicionar Bloco
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
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-lg mx-4 shadow-soft" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke">
            <h3 id="moduleModalTitle" class="text-base font-bold text-text">Novo Módulo</h3>
            <button onclick="closeModal('moduleModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="moduleForm" method="POST" action="/admin/playbook/module" class="p-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="ajax" value="1">
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Título do Módulo</label>
                <input type="text" name="title" id="moduleTitle" required placeholder="Ex: Processo de Qualificação"
                       class="w-full h-11 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Descrição</label>
                <textarea name="description" id="moduleDescription" rows="2" placeholder="Breve descrição do módulo..."
                          class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Ícone</label>
                    <select name="icon" id="moduleIcon" class="w-full h-11 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                        <option value="menu_book">Livro</option>
                        <option value="psychology">Mente</option>
                        <option value="rocket_launch">Foguete</option>
                        <option value="handshake">Handshake</option>
                        <option value="groups">Equipe</option>
                        <option value="target">Alvo</option>
                        <option value="call">Ligação</option>
                        <option value="chat">Chat</option>
                        <option value="checklist">Checklist</option>
                        <option value="star">Estrela</option>
                        <option value="trophy">Troféu</option>
                        <option value="school">Treinamento</option>
                        <option value="person_search">Prospecção</option>
                        <option value="trending_up">Crescimento</option>
                        <option value="shield">Objeções</option>
                        <option value="description">Documentos</option>
                        <option value="play_circle">Vídeo</option>
                        <option value="lightbulb">Dicas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Cor</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="color" id="moduleColor" value="#E1FB15" class="w-11 h-11 rounded-xl border border-stroke bg-surface2 cursor-pointer">
                        <span id="moduleColorHex" class="text-xs text-muted font-mono">#E1FB15</span>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('moduleModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
                <button type="submit" class="h-10 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New/Edit Block -->
<div id="blockModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-2xl mx-4 shadow-soft max-h-[90vh] overflow-y-auto" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke sticky top-0 bg-surface z-10">
            <h3 id="blockModalTitle" class="text-base font-bold text-text">Novo Bloco</h3>
            <button onclick="closeModal('blockModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="blockForm" method="POST" action="/admin/playbook/block" class="p-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="module_id" id="blockModuleId" value="">

            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-2">Tipo de Conteúdo</label>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($blockTypes as $typeKey => $typeInfo): ?>
                    <label class="flex items-center gap-2 p-3 bg-surface2 border border-stroke rounded-xl cursor-pointer hover:border-lime/30 transition-all has-[:checked]:border-lime/50 has-[:checked]:bg-lime/5">
                        <input type="radio" name="type" value="<?= $typeKey ?>" class="sr-only" <?= $typeKey === 'text' ? 'checked' : '' ?>>
                        <span class="material-symbols-outlined text-sm text-muted"><?= $typeInfo['icon'] ?></span>
                        <span class="text-xs font-medium text-text"><?= $typeInfo['label'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Título</label>
                <input type="text" name="title" id="blockTitle" required placeholder="Título do bloco"
                       class="w-full h-11 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Conteúdo</label>
                <div id="contentHint" class="text-xs text-subtle mb-2">Escreva o texto, instrução ou orientação deste bloco.</div>
                <textarea name="content" id="blockContent" rows="8" placeholder="Digite o conteúdo aqui..."
                          class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 focus:ring-1 focus:ring-lime/20 outline-none transition-all resize-y font-mono"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('blockModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
                <button type="submit" class="h-10 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
let editingModuleId = null;
let editingBlockId = null;

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
    document.getElementById('moduleForm').action = '/admin/playbook/module';
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
    document.getElementById('moduleForm').action = '/admin/playbook/module/' + id + '/update';
    document.getElementById('moduleTitle').value = module.title || '';
    document.getElementById('moduleDescription').value = module.description || '';
    document.getElementById('moduleIcon').value = module.icon || 'menu_book';
    document.getElementById('moduleColor').value = module.color || '#E1FB15';
    document.getElementById('moduleColorHex').textContent = module.color || '#E1FB15';
    openModal('moduleModal');
}

function deleteModule(id) {
    if (!confirm('Excluir este módulo e todos os seus blocos?')) return;
    submitAction('/admin/playbook/module/' + id + '/delete', {});
}

function togglePublish(id) {
    fetch('/admin/playbook/module/' + id + '/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(csrfToken) + '&ajax=1'
    })
    .then(r => r.json())
    .then(data => { if (data.ok) location.reload(); });
}

function openNewBlockModal(moduleId) {
    editingBlockId = null;
    document.getElementById('blockModalTitle').textContent = 'Novo Bloco';
    document.getElementById('blockForm').action = '/admin/playbook/block';
    document.getElementById('blockModuleId').value = moduleId;
    document.getElementById('blockTitle').value = '';
    document.getElementById('blockContent').value = '';
    document.querySelector('#blockForm input[name="type"][value="text"]').checked = true;
    updateContentHint('text');
    openModal('blockModal');
}

function openEditBlockModal(id, block) {
    editingBlockId = id;
    document.getElementById('blockModalTitle').textContent = 'Editar Bloco';
    document.getElementById('blockForm').action = '/admin/playbook/block/' + id + '/update';
    document.getElementById('blockModuleId').value = block.module_id || '';
    document.getElementById('blockTitle').value = block.title || '';
    document.getElementById('blockContent').value = block.content || '';
    const typeRadio = document.querySelector('#blockForm input[name="type"][value="' + block.type + '"]');
    if (typeRadio) typeRadio.checked = true;
    updateContentHint(block.type);
    openModal('blockModal');
}

function deleteBlock(id) {
    if (!confirm('Excluir este bloco?')) return;
    submitAction('/admin/playbook/block/' + id + '/delete', {});
}

const typeHints = {
    text: 'Escreva o texto, instrução ou orientação deste bloco.',
    video: 'Cole a URL do vídeo (YouTube, Vimeo, Loom, etc).',
    document: 'Cole o link do documento ou arquivo para download.',
    checklist: 'Escreva um item por linha. Cada linha será um item da checklist.',
    script: 'Escreva o script de abordagem ou template de mensagem.',
    tip: 'Escreva uma dica, boa prática ou ponto de atenção importante.'
};

document.querySelectorAll('#blockForm input[name="type"]').forEach(radio => {
    radio.addEventListener('change', () => updateContentHint(radio.value));
});

function updateContentHint(type) {
    const el = document.getElementById('contentHint');
    if (el) el.textContent = typeHints[type] || '';
}

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

document.getElementById('moduleColor').addEventListener('input', function() {
    document.getElementById('moduleColorHex').textContent = this.value;
});

['moduleModal', 'blockModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>
