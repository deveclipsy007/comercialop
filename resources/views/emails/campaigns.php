<?php
/**
 * Email Campaigns — Listagem e criação de campanhas.
 */
$pageTitle = 'Campanhas de E-mail';
$campaigns = $campaigns ?? [];
$accounts  = $accounts ?? [];
$csrfToken = csrf_token();
$hasAccounts = !empty($accounts);
?>

<div class="flex flex-col h-[calc(100vh-72px)] overflow-hidden">

    <!-- Sub-nav -->
    <div class="flex items-center justify-between border-b border-stroke px-6 py-3 flex-shrink-0 bg-bg">
        <div class="flex items-center gap-2">
            <a href="/emails" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface border border-stroke text-xs text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">dashboard</span> Visão Geral
            </a>
            <a href="/emails/templates" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface border border-stroke text-xs text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">draft</span> Templates
            </a>
            <a href="/emails/campaigns" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-lime text-bg text-xs font-bold">
                <span class="material-symbols-outlined text-sm">campaign</span> Campanhas
            </a>
        </div>
        <button onclick="openNewCampaign()" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-lime text-bg text-xs font-bold hover:brightness-110 transition-all shadow-glow" <?= !$hasAccounts ? 'disabled title="Conecte um e-mail primeiro"' : '' ?>>
            <span class="material-symbols-outlined text-sm">add</span> Nova Campanha
        </button>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
        <div class="max-w-6xl mx-auto">

            <?php if (!$hasAccounts): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-16 h-16 rounded-2xl bg-surface2 border border-stroke flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl text-muted">mail_lock</span>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Conecte um e-mail primeiro</h3>
                <p class="text-sm text-muted mb-6">Para criar campanhas, conecte uma conta de e-mail na <a href="/emails" class="text-lime underline">página principal</a>.</p>
            </div>

            <?php elseif (empty($campaigns)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-16 h-16 rounded-2xl bg-surface2 border border-stroke flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl text-muted">campaign</span>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Nenhuma campanha criada</h3>
                <p class="text-sm text-muted mb-6 max-w-md">Crie campanhas de e-mail com sequências, follow-ups automáticos e métricas de acompanhamento.</p>
                <button onclick="openNewCampaign()" class="h-10 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow">
                    Criar Primeira Campanha
                </button>
            </div>

            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($campaigns as $camp):
                    $statusMap = [
                        'draft' => ['label' => 'Rascunho', 'color' => 'text-muted', 'bg' => 'bg-surface3', 'icon' => 'edit_note'],
                        'active' => ['label' => 'Ativa', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10', 'icon' => 'play_circle'],
                        'paused' => ['label' => 'Pausada', 'color' => 'text-yellow-400', 'bg' => 'bg-yellow-500/10', 'icon' => 'pause_circle'],
                        'completed' => ['label' => 'Concluída', 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10', 'icon' => 'check_circle'],
                        'cancelled' => ['label' => 'Cancelada', 'color' => 'text-red-400', 'bg' => 'bg-red-500/10', 'icon' => 'cancel'],
                    ];
                    $st = $statusMap[$camp['status']] ?? $statusMap['draft'];
                    $openRate = ($camp['sent_count'] ?? 0) > 0 ? round(($camp['opened_count'] ?? 0) / $camp['sent_count'] * 100, 1) : 0;
                ?>
                <a href="/emails/campaign/<?= e($camp['id']) ?>" class="block bg-surface border border-stroke rounded-2xl p-5 hover:border-white/10 transition-all group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <h3 class="text-sm font-bold text-text"><?= e($camp['name']) ?></h3>
                            <span class="flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-pill <?= $st['bg'] ?> <?= $st['color'] ?>">
                                <span class="material-symbols-outlined text-xs"><?= $st['icon'] ?></span>
                                <?= $st['label'] ?>
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-surface3 text-subtle">
                                <?= $camp['campaign_type'] === 'sequence' ? 'Sequência' : 'Envio Único' ?>
                            </span>
                        </div>
                        <span class="material-symbols-outlined text-sm text-muted group-hover:text-lime transition-colors">arrow_forward</span>
                    </div>
                    <?php if (!empty($camp['description'])): ?>
                    <p class="text-xs text-muted mb-3"><?= e($camp['description']) ?></p>
                    <?php endif; ?>
                    <div class="grid grid-cols-4 gap-4">
                        <div>
                            <p class="text-xs text-subtle">Leads</p>
                            <p class="text-sm font-bold text-text"><?= (int)$camp['total_leads'] ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-subtle">Enviados</p>
                            <p class="text-sm font-bold text-text"><?= (int)$camp['sent_count'] ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-subtle">Abertos</p>
                            <p class="text-sm font-bold text-text"><?= $openRate ?>%</p>
                        </div>
                        <div>
                            <p class="text-xs text-subtle">Conta</p>
                            <p class="text-sm font-medium text-text truncate"><?= e($camp['account_email'] ?? 'Não definida') ?></p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Nova Campanha -->
<div id="campaignModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-lg mx-4 shadow-soft" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke">
            <h3 class="text-base font-bold text-text">Nova Campanha</h3>
            <button onclick="closeModal('campaignModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Nome da Campanha</label>
                <input type="text" id="campName" placeholder="Ex: Prospecção Q1 2026" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Descrição</label>
                <textarea id="campDesc" rows="2" placeholder="Objetivo da campanha..." class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Tipo</label>
                    <select id="campType" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                        <option value="one_time">Envio Único</option>
                        <option value="sequence">Sequência (Follow-ups)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Conta de E-mail</label>
                    <select id="campAccount" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= e($acc['id']) ?>"><?= e($acc['email_address']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button onclick="closeModal('campaignModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">Cancelar</button>
                <button onclick="saveCampaign()" class="h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">Criar</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrfToken ?>';

function openModal(id) { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeModal(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }

function openNewCampaign() { openModal('campaignModal'); }

async function saveCampaign() {
    const data = {
        _csrf: CSRF,
        name: document.getElementById('campName').value,
        description: document.getElementById('campDesc').value,
        campaign_type: document.getElementById('campType').value,
        account_id: document.getElementById('campAccount').value,
    };
    try {
        const res = await fetch('/emails/campaign', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.ok) { window.location.href = '/emails/campaign/' + result.id; } else { showToast(result.error || 'Erro.', 'error'); }
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

document.getElementById('campaignModal')?.addEventListener('click', function(e) { if (e.target === this) closeModal('campaignModal'); });
</script>
