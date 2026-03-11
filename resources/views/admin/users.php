<?php
$pageTitle    = 'Usuários (Admin)';
$pageSubtitle = 'Gerenciamento de acessos e equipe';
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-text">Gestão de Equipe</h2>
            <p class="text-sm text-subtle mt-0.5">Membros vinculados e seus níveis de acesso.</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="bg-lime text-bg px-5 py-2.5 rounded-pill text-sm font-bold hover:brightness-110 shadow-glow flex items-center gap-2 transition-all opacity-50 cursor-not-allowed">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                Convidar Membro
            </button>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="bg-surface border border-stroke rounded-cardLg overflow-hidden shadow-soft">
        
        <?php $flashError = \App\Core\Session::getFlash('error'); if ($flashError): ?>
        <div class="m-5 flex items-center gap-3 px-4 py-3 rounded-xl border border-red-500/20 bg-red-500/10 text-red-500 text-sm font-medium">
            <span class="material-symbols-outlined text-lg">error</span>
            <?= e($flashError) ?>
        </div>
        <?php endif; ?>

        <?php $flashSuccess = \App\Core\Session::getFlash('success'); if ($flashSuccess): ?>
        <div class="m-5 flex items-center gap-3 px-4 py-3 rounded-xl border border-lime/20 bg-lime/10 text-lime text-sm font-medium">
            <span class="material-symbols-outlined text-lg">check_circle</span>
            <?= e($flashSuccess) ?>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-surface2 border-b border-stroke">
                    <tr>
                        <th class="px-6 py-4 font-bold tracking-wider uppercase text-[10px] text-muted">Usuário</th>
                        <th class="px-6 py-4 font-bold tracking-wider uppercase text-[10px] text-muted">Cargo</th>
                        <th class="px-6 py-4 font-bold tracking-wider uppercase text-[10px] text-muted">Data de Ingresso</th>
                        <th class="px-6 py-4 font-bold tracking-wider uppercase text-[10px] text-muted">Acesso</th>
                        <th class="px-6 py-4 font-bold tracking-wider uppercase text-[10px] text-muted text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stroke">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-subtle">
                            Nenhum usuário foi encontrado.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-surface2 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-full bg-surface3 border border-stroke flex items-center justify-center text-text font-bold">
                                        <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-text"><?= e($u['name']) ?></div>
                                        <div class="text-xs text-subtle"><?= e($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="px-2.5 py-1 text-[10px] font-bold tracking-widest uppercase rounded-pill bg-lime/10 border border-lime/20 text-lime">Administrador</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 text-[10px] font-bold tracking-widest uppercase rounded-pill bg-surface3 border border-stroke text-muted">Agente Comercial</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted">
                                <?= e(date('d/m/Y', strtotime($u['created_at']))) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($u['active']): ?>
                                    <div class="flex items-center gap-1.5 text-xs text-mint">
                                        <span class="size-2 rounded-full bg-mint animate-pulse"></span> Ativo
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center gap-1.5 text-xs text-red-500">
                                        <span class="size-2 rounded-full bg-red-500"></span> Desativado
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="/admin/users/toggle" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                    <?php if ($u['active']): ?>
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-400 hover:underline" <?= $u['id'] === \App\Core\Session::get('id') ? 'disabled title="Você não pode desativar a si mesmo."' : '' ?>>
                                            Suspender
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="text-xs text-mint hover:text-mint/80 hover:underline">
                                            Reativar
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>
