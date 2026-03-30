<?php
/**
 * Campaign Detail — Editor de campanha com steps, leads e métricas.
 */
$pageTitle = 'Campanha — ' . ($campaign['name'] ?? '');
$campaign  = $campaign ?? [];
$steps     = $steps ?? [];
$templates = $templates ?? [];
$accounts  = $accounts ?? [];
$logs      = $logs ?? [];
$stats     = $stats ?? [];
$leads     = $leads ?? [];
$csrfToken = csrf_token();

$statusMap = [
    'draft' => ['label' => 'Rascunho', 'color' => 'text-muted', 'bg' => 'bg-surface3 border-stroke', 'icon' => 'edit_note'],
    'active' => ['label' => 'Ativa', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10 border-emerald-500/20', 'icon' => 'play_circle'],
    'paused' => ['label' => 'Pausada', 'color' => 'text-yellow-400', 'bg' => 'bg-yellow-500/10 border-yellow-500/20', 'icon' => 'pause_circle'],
    'completed' => ['label' => 'Concluída', 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10 border-blue-500/20', 'icon' => 'check_circle'],
    'cancelled' => ['label' => 'Cancelada', 'color' => 'text-red-400', 'bg' => 'bg-red-500/10 border-red-500/20', 'icon' => 'cancel'],
];
$st = $statusMap[$campaign['status']] ?? $statusMap['draft'];
?>

<div class="flex flex-col h-[calc(100vh-72px)] overflow-hidden">

    <!-- Top Bar -->
    <div class="flex items-center justify-between border-b border-stroke px-6 py-3 flex-shrink-0 bg-bg">
        <div class="flex items-center gap-3">
            <a href="/emails/campaigns" class="p-2 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
            </a>
            <h2 class="text-sm font-bold text-text"><?= e($campaign['name']) ?></h2>
            <span class="flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-pill border <?= $st['bg'] ?> <?= $st['color'] ?>">
                <span class="material-symbols-outlined text-xs"><?= $st['icon'] ?></span>
                <?= $st['label'] ?>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($campaign['status'] === 'draft'): ?>
            <button onclick="executeCampaign()" id="executeBtn" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-lime text-bg text-xs font-bold hover:brightness-110 transition-all shadow-glow">
                <span class="material-symbols-outlined text-sm">send</span> Iniciar Envio
            </button>
            <?php endif; ?>
            <button onclick="deleteCampaign()" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-red-400 hover:border-red-500/30 transition-all">
                <span class="material-symbols-outlined text-sm">delete</span> Excluir
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <?php
                $miniStats = [
                    ['label' => 'Leads', 'value' => $campaign['total_leads'] ?? 0, 'icon' => 'groups'],
                    ['label' => 'Enviados', 'value' => $stats['sent'] ?? 0, 'icon' => 'send'],
                    ['label' => 'Abertos', 'value' => ($stats['open_rate'] ?? 0) . '%', 'icon' => 'visibility'],
                    ['label' => 'Clicados', 'value' => ($stats['click_rate'] ?? 0) . '%', 'icon' => 'ads_click'],
                    ['label' => 'Falhas', 'value' => $stats['failed'] ?? 0, 'icon' => 'error'],
                ];
                foreach ($miniStats as $ms):
                ?>
                <div class="bg-surface border border-stroke rounded-xl p-3 text-center">
                    <span class="material-symbols-outlined text-sm text-muted"><?= $ms['icon'] ?></span>
                    <p class="text-lg font-bold text-text"><?= $ms['value'] ?></p>
                    <p class="text-[10px] text-muted"><?= $ms['label'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Steps -->
                <div class="bg-surface border border-stroke rounded-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-stroke">
                        <h3 class="text-sm font-bold text-text flex items-center gap-2">
                            <span class="material-symbols-outlined text-base text-lime">format_list_numbered</span>
                            Etapas da Sequência
                        </h3>
                        <button onclick="openAddStep()" class="flex items-center gap-1 h-8 px-3 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-lime hover:border-lime/30 transition-all">
                            <span class="material-symbols-outlined text-xs">add</span> Etapa
                        </button>
                    </div>
                    <div class="p-4 space-y-3">
                        <?php if (empty($steps)): ?>
                        <div class="text-center py-6 text-muted text-sm">
                            <span class="material-symbols-outlined text-2xl mb-2 block">playlist_add</span>
                            Nenhuma etapa. Adicione a primeira etapa da sequência.
                        </div>
                        <?php else: ?>
                        <?php foreach ($steps as $i => $step): ?>
                        <div class="bg-surface2/50 border border-white/5 rounded-xl p-4 group hover:border-white/10 transition-all">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-lime/10 flex items-center justify-center text-xs font-bold text-lime"><?= $i + 1 ?></div>
                                    <div>
                                        <p class="text-xs font-bold text-text"><?= e($step['subject'] ?: 'Etapa ' . ($i + 1)) ?></p>
                                        <?php if ($i > 0): ?>
                                        <p class="text-[10px] text-subtle">
                                            <?= (int)$step['delay_days'] ?>d <?= (int)$step['delay_hours'] ?>h após etapa anterior
                                            <?php if ($step['condition_type'] !== 'always'): ?>
                                            | Condição: <?= e($step['condition_type']) ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button onclick="editStep(<?= e(json_encode($step)) ?>)" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text">
                                        <span class="material-symbols-outlined text-xs">edit</span>
                                    </button>
                                    <button onclick="deleteStep('<?= e($step['id']) ?>')" class="p-1.5 rounded-lg hover:bg-red-500/10 text-muted hover:text-red-400">
                                        <span class="material-symbols-outlined text-xs">delete</span>
                                    </button>
                                </div>
                            </div>
                            <p class="text-xs text-muted line-clamp-2"><?= e(mb_substr(strip_tags($step['body']), 0, 120)) ?></p>
                            <div class="flex items-center gap-3 mt-2 text-[10px] text-subtle">
                                <span>Enviados: <?= (int)$step['sent_count'] ?></span>
                                <span>Abertos: <?= (int)$step['opened_count'] ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Leads -->
                <div class="bg-surface border border-stroke rounded-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-stroke">
                        <h3 class="text-sm font-bold text-text flex items-center gap-2">
                            <span class="material-symbols-outlined text-base text-muted">groups</span>
                            Leads da Campanha
                        </h3>
                        <button onclick="openAddLeads()" class="flex items-center gap-1 h-8 px-3 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-lime hover:border-lime/30 transition-all">
                            <span class="material-symbols-outlined text-xs">person_add</span> Leads
                        </button>
                    </div>
                    <div class="max-h-[400px] overflow-y-auto">
                        <?php if (empty($leads)): ?>
                        <div class="text-center py-6 text-muted text-sm p-4">
                            <span class="material-symbols-outlined text-2xl mb-2 block">person_search</span>
                            Nenhum lead adicionado. Adicione leads para a campanha.
                        </div>
                        <?php else: ?>
                        <div class="divide-y divide-stroke">
                            <?php foreach ($leads as $lead): ?>
                            <div class="flex items-center gap-3 px-5 py-3">
                                <div class="w-8 h-8 rounded-lg bg-surface3 flex items-center justify-center text-xs font-bold text-muted">
                                    <?= strtoupper(mb_substr($lead['name'] ?? '?', 0, 2)) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-text truncate"><?= e($lead['name']) ?></p>
                                    <p class="text-xs text-muted truncate"><?= e($lead['email'] ?? 'Sem e-mail') ?></p>
                                </div>
                                <span class="text-[10px] text-subtle"><?= e($lead['segment'] ?? '') ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <?php if (!empty($logs)): ?>
            <div class="bg-surface border border-stroke rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-stroke">
                    <h3 class="text-sm font-bold text-text flex items-center gap-2">
                        <span class="material-symbols-outlined text-base text-muted">history</span>
                        Log de Envios
                    </h3>
                </div>
                <div class="divide-y divide-stroke max-h-[300px] overflow-y-auto">
                    <?php foreach ($logs as $log): ?>
                    <div class="flex items-center gap-3 px-5 py-2.5 text-xs">
                        <span class="material-symbols-outlined text-sm <?= $log['status'] === 'sent' ? 'text-emerald-400' : ($log['status'] === 'failed' ? 'text-red-400' : 'text-muted') ?>">
                            <?= $log['status'] === 'sent' ? 'check_circle' : ($log['status'] === 'failed' ? 'error' : 'schedule') ?>
                        </span>
                        <span class="text-text font-medium"><?= e($log['to_email']) ?></span>
                        <span class="text-subtle truncate flex-1"><?= e($log['subject']) ?></span>
                        <?php if ($log['opened_at']): ?>
                        <span class="text-emerald-400" title="Aberto"><span class="material-symbols-outlined text-xs">visibility</span></span>
                        <?php endif; ?>
                        <span class="text-subtle"><?= $log['created_at'] ? date('d/m H:i', strtotime($log['created_at'])) : '' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Add/Edit Step -->
<div id="stepModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-2xl mx-4 shadow-soft max-h-[90vh] overflow-y-auto" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke sticky top-0 bg-surface z-10">
            <h3 id="stepModalTitle" class="text-base font-bold text-text">Nova Etapa</h3>
            <button onclick="closeModal('stepModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="stepId" value="">
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Assunto</label>
                <input type="text" id="stepSubject" placeholder="Assunto do e-mail" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
            </div>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-[11px] font-bold text-muted uppercase tracking-wider">Corpo do E-mail</label>
                    <span class="text-[10px] text-subtle">Variáveis: {{nome}}, {{empresa}}, {{email}}</span>
                </div>
                <textarea id="stepBody" rows="8" placeholder="Conteúdo do e-mail..." class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none resize-y font-mono"></textarea>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Delay (dias)</label>
                    <input type="number" id="stepDelayDays" value="0" min="0" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Delay (horas)</label>
                    <input type="number" id="stepDelayHours" value="0" min="0" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Condição</label>
                    <select id="stepCondition" class="w-full h-10 px-3 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                        <option value="always">Sempre enviar</option>
                        <option value="not_opened">Se não abriu</option>
                        <option value="not_replied">Se não respondeu</option>
                        <option value="not_clicked">Se não clicou</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Usar Template (opcional)</label>
                <select id="stepTemplate" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none" onchange="loadTemplate(this.value)">
                    <option value="">— Sem template —</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?= e($tpl['id']) ?>" data-subject="<?= e($tpl['subject']) ?>" data-body="<?= e($tpl['body']) ?>"><?= e($tpl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button onclick="closeModal('stepModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
                <button onclick="saveStep()" class="h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Leads -->
<div id="leadsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-lg mx-4 shadow-soft max-h-[90vh] overflow-y-auto" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke sticky top-0 bg-surface z-10">
            <h3 class="text-base font-bold text-text">Selecionar Leads</h3>
            <button onclick="closeModal('leadsModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="text" id="leadSearch" placeholder="Buscar leads por nome ou e-mail..." oninput="searchLeads(this.value)" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
            <div id="leadResults" class="max-h-[300px] overflow-y-auto space-y-1">
                <p class="text-xs text-muted text-center py-4">Digite para buscar leads...</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button onclick="closeModal('leadsModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Fechar</button>
                <button onclick="saveLeadSelection()" class="h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Salvar Seleção</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrfToken ?>';
const CAMPAIGN_ID = '<?= e($campaign['id']) ?>';
let selectedLeadIds = <?= json_encode(json_decode($campaign['lead_ids'] ?? '[]', true) ?: []) ?>;

function openModal(id) { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeModal(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }

// Steps
function openAddStep() {
    document.getElementById('stepModalTitle').textContent = 'Nova Etapa';
    document.getElementById('stepId').value = '';
    document.getElementById('stepSubject').value = '';
    document.getElementById('stepBody').value = '';
    document.getElementById('stepDelayDays').value = 0;
    document.getElementById('stepDelayHours').value = 0;
    document.getElementById('stepCondition').value = 'always';
    document.getElementById('stepTemplate').value = '';
    openModal('stepModal');
}

function editStep(step) {
    document.getElementById('stepModalTitle').textContent = 'Editar Etapa';
    document.getElementById('stepId').value = step.id;
    document.getElementById('stepSubject').value = step.subject || '';
    document.getElementById('stepBody').value = step.body || '';
    document.getElementById('stepDelayDays').value = step.delay_days || 0;
    document.getElementById('stepDelayHours').value = step.delay_hours || 0;
    document.getElementById('stepCondition').value = step.condition_type || 'always';
    openModal('stepModal');
}

function loadTemplate(tplId) {
    const opt = document.querySelector('#stepTemplate option[value="' + tplId + '"]');
    if (opt && tplId) {
        document.getElementById('stepSubject').value = opt.dataset.subject || '';
        document.getElementById('stepBody').value = opt.dataset.body || '';
    }
}

async function saveStep() {
    const stepId = document.getElementById('stepId').value;
    const url = stepId ? '/emails/step/' + stepId + '/update' : '/emails/campaign/' + CAMPAIGN_ID + '/step';
    const data = {
        _csrf: CSRF,
        subject: document.getElementById('stepSubject').value,
        body: document.getElementById('stepBody').value,
        delay_days: document.getElementById('stepDelayDays').value,
        delay_hours: document.getElementById('stepDelayHours').value,
        condition_type: document.getElementById('stepCondition').value,
        template_id: document.getElementById('stepTemplate').value || null,
    };
    try {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.ok) location.reload(); else showToast(result.error || 'Erro.', 'error');
    } catch (err) { showToast('Erro: ' + err.message, 'error'); }
}

async function deleteStep(id) {
    if (!confirm('Excluir esta etapa?')) return;
    try {
        const res = await fetch('/emails/step/' + id + '/delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ _csrf: CSRF }) });
        const data = await res.json();
        if (data.ok) location.reload();
    } catch (err) { showToast('Erro: ' + err.message, 'error'); }
}

// Leads
function openAddLeads() { openModal('leadsModal'); }

let searchTimeout;
function searchLeads(q) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        if (q.length < 2) {
            document.getElementById('leadResults').innerHTML = '<p class="text-xs text-muted text-center py-4">Digite pelo menos 2 caracteres...</p>';
            return;
        }
        try {
            const res = await fetch('/vault?search=' + encodeURIComponent(q) + '&ajax=1');
            const data = await res.json();
            const leads = data.leads || [];
            if (!leads.length) {
                document.getElementById('leadResults').innerHTML = '<p class="text-xs text-muted text-center py-4">Nenhum lead encontrado.</p>';
                return;
            }
            document.getElementById('leadResults').innerHTML = leads.map(l =>
                '<label class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface2 cursor-pointer transition-all">' +
                '<input type="checkbox" class="lead-check rounded border-stroke" value="' + l.id + '" ' + (selectedLeadIds.includes(l.id) ? 'checked' : '') + '>' +
                '<div class="flex-1 min-w-0">' +
                '<p class="text-sm font-medium text-text truncate">' + (l.name || '') + '</p>' +
                '<p class="text-xs text-muted truncate">' + (l.email || 'Sem e-mail') + '</p>' +
                '</div></label>'
            ).join('');
        } catch (err) {
            document.getElementById('leadResults').innerHTML = '<p class="text-xs text-red-400 text-center py-4">Erro ao buscar.</p>';
        }
    }, 300);
}

async function saveLeadSelection() {
    const checks = document.querySelectorAll('.lead-check:checked');
    const ids = Array.from(checks).map(c => c.value);
    selectedLeadIds = [...new Set([...selectedLeadIds, ...ids])];

    // Remove unchecked
    const unchecked = document.querySelectorAll('.lead-check:not(:checked)');
    const uncheckedIds = Array.from(unchecked).map(c => c.value);
    selectedLeadIds = selectedLeadIds.filter(id => !uncheckedIds.includes(id));

    try {
        const res = await fetch('/emails/campaign/' + CAMPAIGN_ID + '/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: CSRF, lead_ids: selectedLeadIds, total_leads: selectedLeadIds.length }),
        });
        const data = await res.json();
        if (data.ok) { location.reload(); } else { showToast(data.error || 'Erro.', 'error'); }
    } catch (err) { showToast('Erro: ' + err.message, 'error'); }
}

// Campaign actions
async function executeCampaign() {
    if (!confirm('Iniciar o envio da campanha para todos os leads selecionados? Isso começará a enviar e-mails imediatamente.')) return;
    const btn = document.getElementById('executeBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Enviando...';

    try {
        const res = await fetch('/emails/campaign/' + CAMPAIGN_ID + '/execute', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: CSRF }),
        });
        const data = await res.json();
        if (data.ok) {
            showToast('Enviados: ' + data.sent + '/' + data.total, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.error || 'Erro no envio.', 'error');
        }
    } catch (err) {
        showToast('Erro: ' + err.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm">send</span> Iniciar Envio';
}

async function deleteCampaign() {
    if (!confirm('Excluir esta campanha permanentemente?')) return;
    try {
        const res = await fetch('/emails/campaign/' + CAMPAIGN_ID + '/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: CSRF }),
        });
        const data = await res.json();
        if (data.ok) window.location.href = '/emails/campaigns';
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

['stepModal', 'leadsModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', function(e) { if (e.target === this) closeModal(id); });
});
</script>
