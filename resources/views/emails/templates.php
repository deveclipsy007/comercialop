<?php
/**
 * Email Templates — Gestão de modelos de e-mail reutilizáveis.
 */
$pageTitle = 'Templates de E-mail';
$templates = $templates ?? [];
$categories = $categories ?? [];
$csrfToken = csrf_token();
?>

<div class="flex flex-col h-[calc(100vh-72px)] overflow-hidden">

    <!-- Sub-nav -->
    <div class="flex items-center justify-between border-b border-stroke px-6 py-3 flex-shrink-0 bg-bg">
        <div class="flex items-center gap-2">
            <a href="/emails" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface border border-stroke text-xs text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">dashboard</span> Visão Geral
            </a>
            <a href="/emails/templates" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-lime text-bg text-xs font-bold">
                <span class="material-symbols-outlined text-sm">draft</span> Templates
            </a>
            <a href="/emails/campaigns" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface border border-stroke text-xs text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">campaign</span> Campanhas
            </a>
        </div>
        <button onclick="openNewTemplate()" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-lime text-bg text-xs font-bold hover:brightness-110 transition-all shadow-glow">
            <span class="material-symbols-outlined text-sm">add</span> Novo Template
        </button>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
        <div class="max-w-6xl mx-auto">

            <?php if (empty($templates)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-16 h-16 rounded-2xl bg-surface2 border border-stroke flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl text-muted">draft</span>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Nenhum template criado</h3>
                <p class="text-sm text-muted mb-6 max-w-md">Crie modelos de e-mail reutilizáveis para prospecção, follow-up e propostas.</p>
                <button onclick="openNewTemplate()" class="h-10 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow">
                    Criar Primeiro Template
                </button>
            </div>
            <?php else: ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($templates as $tpl):
                    $cat = $categories[$tpl['category']] ?? $categories['custom'];
                ?>
                <div class="bg-surface border border-stroke rounded-2xl p-5 hover:border-white/10 transition-all group">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: <?= $cat['color'] ?>15">
                                <span class="material-symbols-outlined text-sm" style="color: <?= $cat['color'] ?>"><?= $cat['icon'] ?></span>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill border" style="color: <?= $cat['color'] ?>; border-color: <?= $cat['color'] ?>33; background: <?= $cat['color'] ?>10"><?= e($cat['label']) ?></span>
                        </div>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="editTemplate(<?= e(json_encode($tpl)) ?>)" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text" title="Editar">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <button onclick="deleteTemplate('<?= e($tpl['id']) ?>')" class="p-1.5 rounded-lg hover:bg-red-500/10 text-muted hover:text-red-400" title="Excluir">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                        </div>
                    </div>
                    <h4 class="text-sm font-bold text-text mb-1 truncate"><?= e($tpl['name']) ?></h4>
                    <p class="text-xs text-muted mb-2 truncate"><?= e($tpl['subject'] ?: '(sem assunto)') ?></p>
                    <p class="text-xs text-subtle line-clamp-2"><?= e(mb_substr(strip_tags($tpl['body']), 0, 100)) ?></p>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-stroke">
                        <span class="text-[10px] text-subtle">Usado <?= (int)$tpl['use_count'] ?>x</span>
                        <span class="text-[10px] text-subtle"><?= $tpl['updated_at'] ? date('d/m/Y', strtotime($tpl['updated_at'])) : '' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Template Editor -->
<div id="templateModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-2xl mx-4 shadow-soft max-h-[90vh] overflow-y-auto" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke sticky top-0 bg-surface z-10">
            <h3 id="tplModalTitle" class="text-base font-bold text-text">Novo Template</h3>
            <button onclick="closeModal('templateModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="tplId" value="">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Nome do Template</label>
                    <input type="text" id="tplName" placeholder="Ex: Prospecção Inicial" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Categoria</label>
                    <select id="tplCategory" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                        <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>"><?= $cat['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Assunto</label>
                <input type="text" id="tplSubject" placeholder="Assunto do e-mail" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
            </div>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-[11px] font-bold text-muted uppercase tracking-wider">Corpo do E-mail</label>
                    <span class="text-[10px] text-subtle">Variáveis: {{nome}}, {{empresa}}, {{email}}</span>
                </div>
                <textarea id="tplBody" rows="10" placeholder="Escreva o corpo do e-mail..." class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none resize-y font-mono"></textarea>
            </div>
            <div class="flex items-center justify-between pt-2">
                <button onclick="aiGenerateTemplate()" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-lime hover:border-lime/30 transition-all">
                    <span class="material-symbols-outlined text-sm">auto_awesome</span> Gerar com IA
                </button>
                <div class="flex items-center gap-2">
                    <button onclick="closeModal('templateModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
                    <button onclick="saveTemplate()" class="h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrfToken ?>';

function openModal(id) { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeModal(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }

function openNewTemplate() {
    document.getElementById('tplModalTitle').textContent = 'Novo Template';
    document.getElementById('tplId').value = '';
    document.getElementById('tplName').value = '';
    document.getElementById('tplSubject').value = '';
    document.getElementById('tplBody').value = '';
    document.getElementById('tplCategory').value = 'custom';
    openModal('templateModal');
}

function editTemplate(tpl) {
    document.getElementById('tplModalTitle').textContent = 'Editar Template';
    document.getElementById('tplId').value = tpl.id;
    document.getElementById('tplName').value = tpl.name || '';
    document.getElementById('tplSubject').value = tpl.subject || '';
    document.getElementById('tplBody').value = tpl.body || '';
    document.getElementById('tplCategory').value = tpl.category || 'custom';
    openModal('templateModal');
}

async function saveTemplate() {
    const id = document.getElementById('tplId').value;
    const url = id ? '/emails/template/' + id + '/update' : '/emails/template';
    const data = {
        _csrf: CSRF,
        name: document.getElementById('tplName').value,
        subject: document.getElementById('tplSubject').value,
        body: document.getElementById('tplBody').value,
        category: document.getElementById('tplCategory').value,
    };
    try {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.ok) { location.reload(); } else { showToast(result.error || 'Erro ao salvar.', 'error'); }
    } catch (err) { showToast('Erro: ' + err.message, 'error'); }
}

async function deleteTemplate(id) {
    if (!confirm('Excluir este template?')) return;
    try {
        const res = await fetch('/emails/template/' + id + '/delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ _csrf: CSRF }) });
        const data = await res.json();
        if (data.ok) location.reload(); else showToast(data.error || 'Erro.', 'error');
    } catch (err) { showToast('Erro: ' + err.message, 'error'); }
}

async function aiGenerateTemplate() {
    const purpose = document.getElementById('tplCategory').value || 'prospecção';
    try {
        const res = await fetch('/emails/ai/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: CSRF, purpose: purpose, tone: 'profissional', context: '' }),
        });
        const data = await res.json();
        if (data.subject) {
            document.getElementById('tplSubject').value = data.subject;
            document.getElementById('tplBody').value = data.body || '';
            showToast('Gerado com IA!', 'success');
        } else {
            showToast(data.error || 'Erro na geração.', 'error');
        }
    } catch (err) { showToast('Erro: ' + err.message, 'error'); }
}

function showToast(msg, type = 'success') {
    const existing = document.querySelector('.toast-msg');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast-msg fixed top-4 right-4 z-[200] flex items-center gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-lg backdrop-blur-sm animate-fadeInUp ' +
        (type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400');
    toast.innerHTML = '<span class="material-symbols-outlined text-lg">' + (type === 'success' ? 'check_circle' : 'error') + '</span> ' + msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

document.getElementById('templateModal')?.addEventListener('click', function(e) { if (e.target === this) closeModal('templateModal'); });
</script>
