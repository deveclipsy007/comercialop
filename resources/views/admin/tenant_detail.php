<?php
/**
 * Admin — Gestão Individual de Empresa Vinculada
 * Editar plano, créditos, acessos, funcionalidades e limites por empresa.
 */
$pageTitle = 'Empresa — ' . ($tenant['name'] ?? '');
$tenant = $tenant ?? [];
$quota = $quota ?? [];
$users = $users ?? [];
$leadCount = $leadCount ?? 0;
$campaignCount = $campaignCount ?? 0;
$featuresEnabled = $featuresEnabled ?? null;
$agencySettings = $agencySettings ?? [];
$tierLimits = $tierLimits ?? [];

$plan = $tenant['plan'] ?? 'starter';
$planLabels = ['starter' => 'Starter', 'pro' => 'Pro', 'elite' => 'Elite'];
$planColors = ['starter' => 'text-muted', 'pro' => 'text-lime', 'elite' => 'text-amber-400'];

$tokensLimit = (int)($quota['tokens_limit'] ?? 100);
$tokensUsed = (int)($quota['tokens_used'] ?? 0);
$creditsExtra = (int)($quota['credits_extra'] ?? 0);
$tokensRemaining = max(0, $tokensLimit - $tokensUsed);
$usagePercent = $tokensLimit > 0 ? min(100, round($tokensUsed / $tokensLimit * 100)) : 0;

// All available features
$allFeatures = [
    'dashboard'  => ['icon' => 'grid_view',       'label' => 'Dashboard / Nexus'],
    'vault'      => ['icon' => 'contacts',         'label' => 'Vault (CRM/Leads)'],
    'hunter'     => ['icon' => 'travel_explore',    'label' => 'Hunter Protocol'],
    'atlas'      => ['icon' => 'map',               'label' => 'Atlas de Vendas'],
    'meridian'   => ['icon' => 'target',            'label' => 'Meridian'],
    'genesis'    => ['icon' => 'upload_file',       'label' => 'Genesis (Importação)'],
    'spin'       => ['icon' => 'psychology_alt',    'label' => 'SPIN Hub'],
    'copilot'    => ['icon' => 'smart_toy',         'label' => 'Copilot IA'],
    'knowledge'  => ['icon' => 'neurology',         'label' => 'Knowledge Base'],
    'whatsapp'   => ['icon' => 'chat',              'label' => 'WhatsApp'],
    'emails'     => ['icon' => 'mail',              'label' => 'E-mail'],
    'forms'      => ['icon' => 'dynamic_form',      'label' => 'Formulários'],
    'playbook'   => ['icon' => 'auto_stories',      'label' => 'Playbook'],
    'agenda'     => ['icon' => 'calendar_today',    'label' => 'Agenda / Follow-up'],
];
?>

<div class="p-4 md:p-8 max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-4 mb-8">
        <a href="javascript:history.back()" class="size-10 rounded-full bg-surface2 border border-stroke flex items-center justify-center text-muted hover:text-text transition-colors">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-1">
                <h2 class="text-xl font-black text-text"><?= e($tenant['name']) ?></h2>
                <span class="px-3 py-0.5 text-[10px] font-bold rounded-pill uppercase tracking-wider <?= $planColors[$plan] ?> border" style="border-color: currentColor; opacity: 0.8">
                    <?= $planLabels[$plan] ?? 'Starter' ?>
                </span>
                <?php if ($tenant['active']): ?>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">ATIVA</span>
                <?php else: ?>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-red-500/10 text-red-400 border border-red-500/20">INATIVA</span>
                <?php endif; ?>
            </div>
            <p class="text-sm text-muted">Slug: <span class="font-mono text-subtle"><?= e($tenant['slug'] ?? '') ?></span> &middot; Criado em <?= e(date('d/m/Y', strtotime($tenant['created_at'] ?? 'now'))) ?></p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-8">
        <div class="bg-surface border border-stroke rounded-2xl p-4 text-center">
            <span class="material-symbols-outlined text-lg text-lime mb-1">groups</span>
            <p class="text-xl font-bold text-text"><?= count($users) ?></p>
            <p class="text-[10px] text-muted">Usuários</p>
        </div>
        <div class="bg-surface border border-stroke rounded-2xl p-4 text-center">
            <span class="material-symbols-outlined text-lg text-blue-400 mb-1">contacts</span>
            <p class="text-xl font-bold text-text"><?= number_format($leadCount) ?></p>
            <p class="text-[10px] text-muted">Leads</p>
        </div>
        <div class="bg-surface border border-stroke rounded-2xl p-4 text-center">
            <span class="material-symbols-outlined text-lg text-emerald-400 mb-1">token</span>
            <p class="text-xl font-bold text-text"><?= number_format($tokensRemaining) ?></p>
            <p class="text-[10px] text-muted">Créditos Restantes</p>
        </div>
        <div class="bg-surface border border-stroke rounded-2xl p-4 text-center">
            <span class="material-symbols-outlined text-lg text-yellow-400 mb-1">campaign</span>
            <p class="text-xl font-bold text-text"><?= $campaignCount ?></p>
            <p class="text-[10px] text-muted">Campanhas</p>
        </div>
        <div class="bg-surface border border-stroke rounded-2xl p-4 text-center">
            <div class="relative inline-block">
                <svg class="w-12 h-12" viewBox="0 0 36 36">
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#2A2A2A" stroke-width="3"/>
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?= $usagePercent > 80 ? '#EF4444' : ($usagePercent > 50 ? '#F59E0B' : '#32D583') ?>" stroke-width="3" stroke-dasharray="<?= $usagePercent ?>, 100" stroke-linecap="round"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-text"><?= $usagePercent ?>%</span>
            </div>
            <p class="text-[10px] text-muted mt-1">Uso Hoje</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: General + Credits -->
        <div class="lg:col-span-2 space-y-6">

            <!-- General Settings -->
            <div class="bg-surface border border-stroke rounded-2xl overflow-hidden shadow-soft">
                <div class="px-6 py-4 border-b border-stroke flex items-center gap-2">
                    <span class="material-symbols-outlined text-lime text-base">settings</span>
                    <h3 class="text-sm font-bold text-text">Configurações Gerais</h3>
                </div>
                <form method="POST" action="/admin/tenant/<?= e($tenant['id']) ?>/update" class="p-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="general">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Nome da Empresa</label>
                            <input type="text" name="name" value="<?= e($tenant['name']) ?>" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Plano</label>
                            <select name="plan" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                                <option value="starter" <?= $plan === 'starter' ? 'selected' : '' ?>>Starter — <?= $tierLimits['starter'] ?? 100 ?> créditos/dia</option>
                                <option value="pro" <?= $plan === 'pro' ? 'selected' : '' ?>>Pro — <?= $tierLimits['pro'] ?? 500 ?> créditos/dia</option>
                                <option value="elite" <?= $plan === 'elite' ? 'selected' : '' ?>>Elite — <?= $tierLimits['elite'] ?? 2000 ?> créditos/dia</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Máx. Usuários</label>
                            <input type="number" name="max_users" value="<?= (int)($tenant['max_users'] ?? 10) ?>" min="1" max="500" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Máx. Leads</label>
                            <input type="number" name="max_leads" value="<?= (int)($tenant['max_leads'] ?? 5000) ?>" min="100" max="100000" step="100" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Máx. Campanhas</label>
                            <input type="number" name="max_campaigns" value="<?= (int)($tenant['max_campaigns'] ?? 50) ?>" min="1" max="1000" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-stroke bg-surface2 cursor-pointer hover:border-lime/50 transition-all select-none">
                            <input type="checkbox" name="active" value="1" <?= $tenant['active'] ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-10 h-5 bg-surface3 rounded-full relative transition-all peer-checked:bg-emerald-500 flex-shrink-0">
                                <div class="absolute top-0.5 left-0.5 size-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></div>
                            </div>
                            <div>
                                <span class="block text-sm font-bold text-text">Empresa Ativa</span>
                                <span class="block text-[10px] text-subtle">Se desativada, ninguém consegue acessar esta empresa.</span>
                            </div>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Notas Administrativas</label>
                        <textarea name="admin_notes" rows="3" placeholder="Anotações internas sobre esta empresa..." class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none resize-none"><?= e($tenant['admin_notes'] ?? '') ?></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="h-10 px-6 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">save</span> Salvar Configurações
                        </button>
                    </div>
                </form>
            </div>

            <!-- Features / Funcionalidades -->
            <div class="bg-surface border border-stroke rounded-2xl overflow-hidden shadow-soft">
                <div class="px-6 py-4 border-b border-stroke flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-base">toggle_on</span>
                        <h3 class="text-sm font-bold text-text">Funcionalidades Habilitadas</h3>
                    </div>
                    <span class="text-[10px] text-subtle">Se nenhuma selecionada, todas ficam liberadas (padrão).</span>
                </div>
                <form method="POST" action="/admin/tenant/<?= e($tenant['id']) ?>/update" class="p-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="features">

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mb-4">
                        <?php foreach ($allFeatures as $key => $feat):
                            $isEnabled = $featuresEnabled === null || in_array($key, $featuresEnabled ?? []);
                        ?>
                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer hover:border-lime/40 transition-all select-none feature-item <?= $isEnabled ? 'border-lime/30 bg-lime/5' : 'border-stroke bg-surface2' ?>">
                            <input type="checkbox" name="features[]" value="<?= $key ?>" <?= $isEnabled ? 'checked' : '' ?> class="sr-only feature-check" onchange="toggleFeature(this)">
                            <span class="material-symbols-outlined text-sm <?= $isEnabled ? 'text-lime' : 'text-muted' ?>"><?= $feat['icon'] ?></span>
                            <span class="text-xs font-medium text-text"><?= $feat['label'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="h-10 px-6 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">save</span> Salvar Funcionalidades
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Credits + Users -->
        <div class="space-y-6">

            <!-- Credits -->
            <div class="bg-surface border border-stroke rounded-2xl overflow-hidden shadow-soft">
                <div class="px-6 py-4 border-b border-stroke flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-400 text-base">token</span>
                    <h3 class="text-sm font-bold text-text">Créditos & Quota</h3>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Usage Bar -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs text-muted">Uso Diário</span>
                            <span class="text-xs font-bold <?= $usagePercent > 80 ? 'text-red-400' : 'text-emerald-400' ?>"><?= number_format($tokensUsed) ?> / <?= number_format($tokensLimit) ?></span>
                        </div>
                        <div class="w-full h-3 bg-surface3 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 <?= $usagePercent > 80 ? 'bg-red-400' : ($usagePercent > 50 ? 'bg-yellow-400' : 'bg-emerald-400') ?>" style="width: <?= $usagePercent ?>%"></div>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Plano Base</span><span class="text-text font-medium"><?= $planLabels[$plan] ?? 'Starter' ?></span></div>
                        <div class="flex justify-between"><span class="text-muted">Créditos do Plano</span><span class="text-text font-medium"><?= number_format($tierLimits[$plan] ?? 100) ?>/dia</span></div>
                        <div class="flex justify-between"><span class="text-muted">Créditos Extras</span><span class="text-lime font-medium">+<?= number_format($creditsExtra) ?></span></div>
                        <div class="flex justify-between border-t border-stroke pt-2"><span class="text-muted font-bold">Limite Total</span><span class="text-text font-bold"><?= number_format($tokensLimit) ?>/dia</span></div>
                    </div>

                    <form method="POST" action="/admin/tenant/<?= e($tenant['id']) ?>/update" class="space-y-3 pt-3 border-t border-stroke">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="credits">
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Limite Total de Créditos/Dia</label>
                            <input type="number" name="tokens_limit" value="<?= $tokensLimit ?>" min="0" step="50" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Créditos Extras (bônus)</label>
                            <input type="number" name="credits_extra" value="<?= $creditsExtra ?>" min="0" step="10" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                            <p class="text-[10px] text-subtle mt-1">Créditos adicionais além do plano base.</p>
                        </div>
                        <button type="submit" class="w-full h-10 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">save</span> Atualizar Créditos
                        </button>
                    </form>

                    <!-- Reset button -->
                    <form method="POST" action="/admin/tenant/<?= e($tenant['id']) ?>/update" class="pt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="reset_credits">
                        <button type="submit" onclick="return confirm('Resetar créditos usados hoje para 0?')" class="w-full h-9 bg-surface2 border border-stroke text-xs text-muted hover:text-text rounded-pill transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">restart_alt</span> Resetar Créditos do Dia
                        </button>
                    </form>
                </div>
            </div>

            <!-- Users in this Tenant -->
            <div class="bg-surface border border-stroke rounded-2xl overflow-hidden shadow-soft">
                <div class="px-6 py-4 border-b border-stroke flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-muted text-base">groups</span>
                        <h3 class="text-sm font-bold text-text">Membros</h3>
                    </div>
                    <span class="text-xs text-subtle"><?= count($users) ?> / <?= (int)($tenant['max_users'] ?? 10) ?></span>
                </div>
                <div class="max-h-[320px] overflow-y-auto divide-y divide-stroke">
                    <?php if (empty($users)): ?>
                    <div class="p-6 text-center text-muted text-sm">
                        <span class="material-symbols-outlined text-2xl mb-2 block">person_off</span>
                        Nenhum usuário vinculado.
                    </div>
                    <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <a href="/admin/users/<?= e($u['id']) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-surface2/50 transition-colors group">
                        <div class="w-8 h-8 rounded-lg bg-surface3 flex items-center justify-center text-xs font-bold text-muted flex-shrink-0">
                            <?= strtoupper(mb_substr($u['name'] ?? '?', 0, 2)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-text truncate group-hover:text-lime transition-colors"><?= e($u['name']) ?></p>
                            <p class="text-[10px] text-muted truncate"><?= e($u['email']) ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill <?= ($u['pivot_role'] ?? '') === 'admin' ? 'bg-lime/10 text-lime border border-lime/20' : 'bg-surface3 text-subtle' ?>">
                                <?= ($u['pivot_role'] ?? '') === 'admin' ? 'Admin' : 'Agente' ?>
                            </span>
                            <?php if (!$u['active']): ?>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-red-500/10 text-red-400 border border-red-500/20">Inativo</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Agency Info (if exists) -->
            <?php if ($agencySettings): ?>
            <div class="bg-surface border border-stroke rounded-2xl p-5">
                <h3 class="text-sm font-bold text-text mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-muted">business</span>
                    Perfil da Empresa
                </h3>
                <div class="space-y-2 text-xs">
                    <?php if (!empty($agencySettings['agency_name'])): ?>
                    <div class="flex justify-between"><span class="text-muted">Nome</span><span class="text-text"><?= e($agencySettings['agency_name']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($agencySettings['agency_niche'])): ?>
                    <div class="flex justify-between"><span class="text-muted">Nicho</span><span class="text-text"><?= e($agencySettings['agency_niche']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($agencySettings['agency_city'])): ?>
                    <div class="flex justify-between"><span class="text-muted">Cidade</span><span class="text-text"><?= e($agencySettings['agency_city']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($agencySettings['offer_summary'])): ?>
                    <div class="pt-2 border-t border-stroke">
                        <span class="text-muted block mb-1">Oferta</span>
                        <span class="text-text"><?= e(mb_substr($agencySettings['offer_summary'], 0, 150)) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleFeature(input) {
    const label = input.closest('.feature-item');
    const icon = label.querySelector('.material-symbols-outlined');
    if (input.checked) {
        label.classList.add('border-lime/30', 'bg-lime/5');
        label.classList.remove('border-stroke', 'bg-surface2');
        icon.classList.add('text-lime');
        icon.classList.remove('text-muted');
    } else {
        label.classList.remove('border-lime/30', 'bg-lime/5');
        label.classList.add('border-stroke', 'bg-surface2');
        icon.classList.remove('text-lime');
        icon.classList.add('text-muted');
    }
}
</script>
