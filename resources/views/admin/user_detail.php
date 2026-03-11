<?php
$pageTitle    = 'Detalhes do Usuário';
$pageSubtitle = 'Configurações de acesso e White Label';
?>

<div class="p-6 flex flex-col gap-6">

    <div class="flex items-center gap-4">
        <a href="/admin/users" class="size-10 rounded-full bg-surface2 border border-stroke flex items-center justify-center text-muted hover:text-text transition-colors">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
        </a>
        <div>
            <h2 class="text-xl font-black text-text"><?= e($user['name']) ?></h2>
            <p class="text-sm text-subtle mt-0.5"><?= e($user['email']) ?> — <?= $user['role'] === 'admin' ? 'Administrador' : 'Agente' ?></p>
        </div>
    </div>

    <?php $flashSuccess = \App\Core\Session::getFlash('success'); if ($flashSuccess): ?>
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl border border-lime/20 bg-lime/10 text-lime text-sm font-medium">
        <span class="material-symbols-outlined text-lg">check_circle</span>
        <?= e($flashSuccess) ?>
    </div>
    <?php endif; ?>

    <?php $flashError = \App\Core\Session::getFlash('error'); if ($flashError): ?>
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl border border-red-500/20 bg-red-500/10 text-red-500 text-sm font-medium">
        <span class="material-symbols-outlined text-lg">error</span>
        <?= e($flashError) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Info Card -->
        <div class="bg-surface border border-stroke rounded-cardLg p-6 h-fit">
            <h3 class="text-sm font-bold text-text mb-4">Informações da Conta</h3>
            <div class="space-y-4">
                <div>
                    <span class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1">Status</span>
                    <?php if ($user['active']): ?>
                        <div class="inline-flex items-center gap-1.5 text-xs text-mint font-bold px-2.5 py-1 rounded-pill bg-mint/10 border border-mint/20">
                            <span class="size-2 rounded-full bg-mint animate-pulse"></span> Ativo
                        </div>
                    <?php else: ?>
                        <div class="inline-flex items-center gap-1.5 text-xs text-red-500 font-bold px-2.5 py-1 rounded-pill bg-red-500/10 border border-red-500/20">
                            <span class="size-2 rounded-full bg-red-500"></span> Desativado
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1">Membro desde</span>
                    <span class="text-sm text-text"><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></span>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1">Última atualização</span>
                    <span class="text-sm text-text"><?= e(date('d/m/Y H:i', strtotime($user['updated_at']))) ?></span>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-stroke">
                <form method="POST" action="/admin/users/toggle" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                    <?php if ($user['active']): ?>
                        <button type="submit" class="w-full py-2.5 rounded-pill border border-red-500/20 bg-red-500/10 text-red-500 text-sm font-bold hover:bg-red-500/20 transition-all flex items-center justify-center gap-2" <?= $user['id'] === \App\Core\Session::get('id') ? 'disabled title="Você não pode desativar a si mesmo."' : '' ?>>
                            <span class="material-symbols-outlined text-[18px]">block</span>
                            Suspender Acesso
                        </button>
                    <?php else: ?>
                        <button type="submit" class="w-full py-2.5 rounded-pill border border-mint/20 bg-mint/10 text-mint text-sm font-bold hover:bg-mint/20 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            Reativar Acesso
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- White Label Settings -->
        <div class="lg:col-span-2 bg-surface border border-stroke rounded-cardLg overflow-hidden shadow-soft">
            <div class="px-6 py-5 border-b border-stroke">
                <h3 class="text-[15px] font-black justify-start items-center gap-2 flex text-text">
                    <span class="material-symbols-outlined text-lime">palette</span>
                    Personalização White Label
                </h3>
                <p class="text-xs text-subtle mt-1">Configure o visual, logomarca e módulos acessíveis para este cliente.</p>
            </div>

            <form method="POST" action="/admin/users/<?= e($user['id']) ?>/whitelabel" class="p-6" id="wl-form">
                <?= csrf_field() ?>
                
                <div class="space-y-6">
                    <!-- Cores -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Cor Principal (Primária)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="wl_color" value="<?= e($user['wl_color'] ?? '#a3e635') ?>" class="size-11 rounded-xl cursor-pointer bg-surface2 border border-stroke p-1">
                                <input type="text" value="<?= e($user['wl_color'] ?? '#a3e635') ?>" class="w-28 h-11 bg-surface2 border border-stroke rounded-xl px-3 text-sm text-text focus:outline-none uppercase font-mono" readonly>
                            </div>
                            <p class="text-[10px] text-subtle mt-1.5">Define a cor de todos os botões e detalhes do painel do cliente.</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Logomarca (URL)</label>
                            <input type="url" name="wl_logo" value="<?= e($user['wl_logo'] ?? '') ?>" placeholder="https://..." class="w-full h-11 bg-surface2 border border-stroke rounded-xl px-4 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors">
                            <p class="text-[10px] text-subtle mt-1.5">Link direto para imagem (SVG ou PNG c/ fundo transparente). Se vazio, usa o logo padrão.</p>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-stroke">
                        <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-3">Módulos Permitidos</label>
                        <p class="text-xs text-subtle mb-4">Escolha as ferramentas que este cliente terá acesso no menu lateral.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php 
                            $features = [
                                'dashboard' => ['icon' => 'dashboard', 'label' => 'Nexus (Dashboard)'],
                                'vault' => ['icon' => 'database', 'label' => 'Lead Vault'],
                                'atlas' => ['icon' => 'map', 'label' => 'Atlas de Vendas'],
                                'hunter' => ['icon' => 'radar', 'label' => 'Hunter Protocol'],
                                'spin' => ['icon' => 'track_changes', 'label' => 'SPIN Hub'],
                                'agenda' => ['icon' => 'calendar_month', 'label' => 'Agenda e Follow-up']
                            ];
                            $userFeatures = is_array($user['wl_features']) ? $user['wl_features'] : [];
                            
                            // Se estiver vazio logo na criacao, ativar padrão
                            if (empty($userFeatures) && !isset($user['wl_features'])) {
                                $userFeatures = ['dashboard', 'vault', 'agenda'];
                            }

                            foreach ($features as $key => $feat): 
                                $isChecked = in_array($key, $userFeatures);
                            ?>
                            <label class="flex items-center gap-3 p-3 rounded-2xl border <?= $isChecked ? 'border-primary/50 bg-primary/10' : 'border-stroke bg-surface2' ?> cursor-pointer hover:border-lime/50 transition-all select-none feature-toggle h-full">
                                <input type="checkbox" name="wl_features[]" value="<?= $key ?>" <?= $isChecked ? 'checked' : '' ?> class="hidden feature-checkbox" onchange="toggleFeatureStyle(this)">
                                <span class="size-8 rounded-xl bg-surface border border-stroke flex items-center justify-center flex-shrink-0 text-text">
                                    <span class="material-symbols-outlined text-[18px] <?= $isChecked ? 'text-lime' : '' ?>"><?= $feat['icon'] ?></span>
                                </span>
                                <span class="text-sm font-bold text-text"><?= $feat['label'] ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-stroke flex items-start gap-4">
                        <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                            <input type="checkbox" name="wl_allow_setup" value="1" <?= ($user['wl_allow_setup'] ?? 0) ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-surface3 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-lime border border-stroke"></div>
                        </label>
                        <div>
                            <span class="block text-sm font-bold text-text mb-0.5">Permitir Customização pelo Usuário</span>
                            <span class="block text-xs text-subtle">Se ativo, o cliente verá a aba "Aparência" nas Configurações dele e poderá alterar a própria cor e logo. (Módulos continuam sendo restritos ao Admin).</span>
                        </div>
                    </div>

                    <div class="pt-6 mt-6 border-t border-stroke flex justify-end">
                        <button type="submit" class="h-11 px-6 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Salvar White Label
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ─── Multi-Company Section ──────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-cardLg overflow-hidden shadow-soft">
        <div class="px-6 py-5 border-b border-stroke flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-lime">domain</span>
                <div>
                    <h3 class="text-[15px] font-black text-text">Multi-Empresa</h3>
                    <p class="text-xs text-subtle mt-0.5">Defina quantas empresas este usuário pode operar.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-pill bg-surface2 border border-stroke">
                <span class="text-xs text-muted">Vinculado a</span>
                <span class="text-sm font-black text-lime"><?= count($linkedTenants ?? []) ?></span>
                <span class="text-xs text-muted">/ <?= (int)($user['max_tenants'] ?? 1) ?> empresa(s)</span>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Limit Controls (part of the main WL form) -->
            <div class="md:col-span-1 space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Limite Máximo de Empresas</label>
                    <input type="number" name="max_tenants" form="wl-form" value="<?= (int)($user['max_tenants'] ?? 1) ?>" min="1" max="50"
                        class="w-full h-11 bg-surface2 border border-stroke rounded-xl px-4 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors">
                    <p class="text-[10px] text-subtle mt-1.5">Quantidade máxima de empresas nas quais pode atuar.</p>
                </div>
                <label class="flex items-center gap-3 p-3 rounded-xl border border-stroke bg-surface2 cursor-pointer hover:border-lime/50 transition-all select-none">
                    <input type="checkbox" name="can_create_tenants" value="1" form="wl-form" <?= ($user['can_create_tenants'] ?? 0) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-10 h-5 bg-surface3 rounded-full relative transition-all peer-checked:bg-lime flex-shrink-0">
                        <div class="absolute top-0.5 left-0.5 size-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-text">Pode criar novas empresas</span>
                        <span class="block text-[10px] text-subtle">Se desmarcado, só pode operar empresas atribuídas.</span>
                    </div>
                </label>

                <div class="pt-2">
                    <button type="submit" form="wl-form" class="w-full h-11 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Salvar Acesso
                    </button>
                    <p class="text-[9px] text-center text-subtle mt-2 italic">Dica: Isso também salva as cores e módulos acima.</p>
                </div>
            </div>

            <!-- Linked Companies List -->
            <div class="md:col-span-2">
                <p class="text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-3">Empresas Vinculadas</p>
                <?php if (empty($linkedTenants)): ?>
                    <div class="p-4 rounded-xl border border-dashed border-stroke text-center">
                        <span class="material-symbols-outlined text-muted text-2xl">domain_disabled</span>
                        <p class="text-xs text-subtle mt-2">Nenhuma empresa vinculada ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach($linkedTenants as $t): ?>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-surface2 border border-stroke">
                            <div class="size-9 rounded-xl bg-lime/10 border border-lime/20 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-[18px] text-lime">business</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-text truncate"><?= e($t['name']) ?></p>
                                <p class="text-[10px] text-muted capitalize">Papel: <?= e($t['pivot_role'] ?? 'agent') ?></p>
                            </div>
                            <form method="POST" action="/admin/users/<?= e($user['id']) ?>/unlink-tenant" onsubmit="return confirm('Remover acesso desta empresa?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="tenant_id" value="<?= e($t['id']) ?>">
                                <button type="submit" class="size-8 rounded-lg border border-red-500/20 bg-red-500/10 text-red-400 hover:bg-red-500/30 transition-all flex items-center justify-center" title="Remover vínculo">
                                    <span class="material-symbols-outlined text-[16px]">link_off</span>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Link to new company -->
                <?php if(count($linkedTenants) < (int)($user['max_tenants'] ?? 1)): ?>
                <div class="mt-4 pt-4 border-t border-stroke">
                    <form method="POST" action="/admin/users/<?= e($user['id']) ?>/link-tenant" class="flex gap-3 items-end">
                        <?= csrf_field() ?>
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Vincular Nova Empresa</label>
                            <select name="tenant_id" class="w-full h-11 bg-surface2 border border-stroke rounded-xl px-4 text-sm text-text focus:outline-none focus:border-lime/50">
                                <option value="">Selecionar empresa...</option>
                                <?php foreach(($allTenants ?? []) as $tenant): 
                                    $alreadyLinked = array_filter($linkedTenants, fn($lt) => $lt['id'] === $tenant['id']);
                                    if (!$alreadyLinked):
                                ?>
                                    <option value="<?= e($tenant['id']) ?>"><?= e($tenant['name']) ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="h-11 px-5 bg-surface2 border border-lime/30 text-lime text-sm font-bold rounded-xl hover:bg-lime/10 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">add_link</span> Vincular
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
function toggleFeatureStyle(input) {
    const label = input.closest('label');
    const icon = label.querySelector('.material-symbols-outlined');
    if (input.checked) {
        label.classList.add('border-primary/50', 'bg-primary/10');
        label.classList.remove('border-stroke', 'bg-surface2');
        icon.classList.add('text-lime');
    } else {
        label.classList.remove('border-primary/50', 'bg-primary/10');
        label.classList.add('border-stroke', 'bg-surface2');
        icon.classList.remove('text-lime');
    }
}
document.querySelector('input[type="color"]').addEventListener('input', function(e) {
    this.nextElementSibling.value = e.target.value.toUpperCase();
});
</script>
