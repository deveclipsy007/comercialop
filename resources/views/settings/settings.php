<?php
$timezone = $agencySettings['timezone'] ?? 'America/Sao_Paulo';
$userPrefs = $userPrefs ?? [];
$teamMembers = $teamMembers ?? [];
$customFields = $customFields ?? [];
$currentUserId = $currentUserId ?? '';
$userRole = $userRole ?? 'agent';
$isAdmin = in_array($userRole, ['admin', 'owner'], true);
?>
<div class="max-w-6xl mx-auto p-6 md:p-8 space-y-8">
    <div class="mb-2">
        <h1 class="text-2xl font-bold text-text">Configurações Gerais</h1>
        <p class="text-sm text-muted mt-1">Gerencie as preferências da plataforma, notificações e campos personalizados.</p>
    </div>

    <?php $flash = \App\Core\Session::getFlash('success'); if ($flash): ?>
        <div class="bg-lime/10 border border-lime/30 text-lime text-sm px-4 py-3 rounded-lg"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php $flashErr = \App\Core\Session::getFlash('error'); if ($flashErr): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">

        <!-- Sidebar Navigation -->
        <div class="col-span-1 border-b md:border-b-0 md:border-r border-stroke pb-6 md:pb-0 md:pr-6">
            <ul class="space-y-1.5" id="settingsTabs">
                <li>
                    <button data-tab="general" class="settings-tab w-full text-left block px-4 py-2.5 text-sm font-bold text-lime bg-lime/10 rounded-lg transition-colors">Geral</button>
                </li>
                <li>
                    <button data-tab="notifications" class="settings-tab w-full text-left block px-4 py-2.5 text-sm font-medium text-muted hover:text-text hover:bg-surface2 rounded-lg transition-colors">Notificações</button>
                </li>
                <li>
                    <button data-tab="team" class="settings-tab w-full text-left block px-4 py-2.5 text-sm font-medium text-muted hover:text-text hover:bg-surface2 rounded-lg transition-colors">Equipe</button>
                </li>
                <li>
                    <button data-tab="custom-fields" class="settings-tab w-full text-left block px-4 py-2.5 text-sm font-medium text-muted hover:text-text hover:bg-surface2 rounded-lg transition-colors">Campos Personalizados</button>
                </li>
            </ul>
        </div>

        <!-- Content Panels -->
        <div class="col-span-1 md:col-span-3 space-y-6">

            <!-- ═══ TAB: Geral ═══ -->
            <div id="panel-general" class="settings-panel">
                <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">
                    <h3 class="text-lg font-bold text-text mb-8 flex items-center gap-2 pb-4 border-b border-stroke">
                        <span class="material-symbols-outlined text-muted text-[20px]">tune</span> Preferências da Plataforma
                    </h3>

                    <form method="POST" action="/settings" class="space-y-6">
                        <?= csrf_field() ?>

                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Fuso Horário</label>
                            <select name="timezone" class="w-full h-12 bg-surface2 border border-stroke rounded-pill px-5 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer [color-scheme:dark]">
                                <?php
                                $timezones = [
                                    'America/Sao_Paulo'    => 'Brasília (GMT-3)',
                                    'America/Manaus'       => 'Manaus (GMT-4)',
                                    'America/Rio_Branco'   => 'Rio Branco (GMT-5)',
                                    'America/Noronha'      => 'Fernando de Noronha (GMT-2)',
                                    'America/Belem'        => 'Belém (GMT-3)',
                                    'America/Cuiaba'       => 'Cuiabá (GMT-4)',
                                    'America/Fortaleza'    => 'Fortaleza (GMT-3)',
                                    'America/Recife'       => 'Recife (GMT-3)',
                                    'America/Bahia'        => 'Bahia (GMT-3)',
                                    'America/Campo_Grande' => 'Campo Grande (GMT-4)',
                                    'America/Porto_Velho'  => 'Porto Velho (GMT-4)',
                                    'America/Boa_Vista'    => 'Boa Vista (GMT-4)',
                                    'UTC'                  => 'UTC (GMT+0)',
                                    'America/New_York'     => 'New York (GMT-5)',
                                    'America/Los_Angeles'  => 'Los Angeles (GMT-8)',
                                    'Europe/Lisbon'        => 'Lisboa (GMT+0)',
                                    'Europe/London'        => 'Londres (GMT+0)',
                                ];
                                foreach ($timezones as $tz => $label): ?>
                                    <option value="<?= $tz ?>" class="bg-surface2 text-lime font-bold" <?= $timezone === $tz ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-subtle mt-2 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[14px]">info</span>
                                Afeta datas exibidas na agenda, follow-ups e logs do sistema.
                            </p>
                        </div>

                        <div class="pt-6 mt-6 border-t border-stroke flex justify-end">
                            <button type="submit" class="h-11 px-6 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all">
                                Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ═══ TAB: Notificações ═══ -->
            <div id="panel-notifications" class="settings-panel hidden">
                <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">
                    <h3 class="text-lg font-bold text-text mb-8 flex items-center gap-2 pb-4 border-b border-stroke">
                        <span class="material-symbols-outlined text-muted text-[20px]">notifications</span> Preferências de Notificação
                    </h3>

                    <form method="POST" action="/settings/notifications" class="space-y-5">
                        <?= csrf_field() ?>

                        <p class="text-sm text-muted mb-6">Escolha quais alertas deseja receber na plataforma. As notificações aparecem no ícone do sino no topo da página.</p>

                        <?php
                        $notifOptions = [
                            ['key' => 'notify_followup_due',  'label' => 'Follow-ups vencendo',       'desc' => 'Alertar quando um follow-up agendado estiver próximo ou vencido.'],
                            ['key' => 'notify_lead_assigned', 'label' => 'Lead atribuído a mim',      'desc' => 'Alertar quando um lead for atribuído a você.'],
                            ['key' => 'notify_stage_change',  'label' => 'Mudança de etapa no funil',  'desc' => 'Alertar quando um lead mudar de etapa no pipeline.'],
                            ['key' => 'notify_whatsapp_new',  'label' => 'Nova mensagem WhatsApp',     'desc' => 'Alertar quando uma nova conversa WhatsApp for recebida.'],
                            ['key' => 'notify_agenda_today',  'label' => 'Agenda do dia',              'desc' => 'Mostrar no sino os compromissos e lembretes agendados para hoje.'],
                            ['key' => 'notify_agenda_1h',     'label' => 'Compromisso em 1 hora',      'desc' => 'Destacar e avisar quando faltar até 1 hora para um compromisso ou lembrete.'],
                            ['key' => 'notify_quota_warning', 'label' => 'Limite de créditos',         'desc' => 'Alertar quando seus créditos estiverem próximos do limite (90%).'],
                        ];
                        foreach ($notifOptions as $opt):
                            $checked = ($userPrefs[$opt['key']] ?? 1) ? 'checked' : '';
                        ?>
                        <label class="flex items-start gap-4 p-4 bg-surface2/50 border border-stroke rounded-lg cursor-pointer hover:border-lime/30 transition-colors group">
                            <div class="relative flex-shrink-0 mt-0.5">
                                <input type="checkbox" name="<?= $opt['key'] ?>" value="1" <?= $checked ?>
                                       class="peer sr-only">
                                <div class="w-10 h-6 bg-surface2 border border-stroke rounded-full peer-checked:bg-lime/20 peer-checked:border-lime/50 transition-all"></div>
                                <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-muted rounded-full peer-checked:translate-x-4 peer-checked:bg-lime transition-all shadow-sm"></div>
                            </div>
                            <div>
                                <span class="block text-sm font-semibold text-text"><?= $opt['label'] ?></span>
                                <span class="block text-xs text-muted mt-0.5"><?= $opt['desc'] ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>

                        <div class="pt-6 mt-4 border-t border-stroke flex justify-end">
                            <button type="submit" class="h-11 px-6 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all">
                                Salvar Notificações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ═══ TAB: Equipe ═══ -->
            <div id="panel-team" class="settings-panel hidden">
                <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">
                    <h3 class="text-lg font-bold text-text mb-6 flex items-center gap-2 pb-4 border-b border-stroke">
                        <span class="material-symbols-outlined text-muted text-[20px]">group</span> Membros da Equipe
                    </h3>

                    <?php if (empty($teamMembers)): ?>
                        <p class="text-sm text-muted">Nenhum membro encontrado nesta empresa.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($teamMembers as $member):
                                $isCurrentUser = ($member['id'] === $currentUserId);
                                $roleBadge = match($member['tenant_role'] ?? $member['role']) {
                                    'admin', 'owner' => '<span class="text-[10px] font-bold uppercase tracking-wider bg-lime/15 text-lime px-2 py-0.5 rounded-full">Admin</span>',
                                    'viewer' => '<span class="text-[10px] font-bold uppercase tracking-wider bg-blue-500/15 text-blue-400 px-2 py-0.5 rounded-full">Viewer</span>',
                                    default => '<span class="text-[10px] font-bold uppercase tracking-wider bg-surface2 text-muted px-2 py-0.5 rounded-full">Agente</span>',
                                };
                                $statusDot = $member['active'] ? 'bg-lime' : 'bg-red-400';
                            ?>
                            <div class="flex items-center justify-between p-4 bg-surface2/50 border border-stroke rounded-lg <?= $isCurrentUser ? 'ring-1 ring-lime/20' : '' ?>">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-surface2 border border-stroke flex items-center justify-center text-sm font-bold text-muted uppercase">
                                        <?= mb_substr($member['name'], 0, 2) ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-text"><?= htmlspecialchars($member['name']) ?></span>
                                            <?= $roleBadge ?>
                                            <?php if ($isCurrentUser): ?>
                                                <span class="text-[10px] text-muted">(você)</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-xs text-muted"><?= htmlspecialchars($member['email']) ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full <?= $statusDot ?>"></div>
                                        <span class="text-xs text-muted"><?= $member['active'] ? 'Ativo' : 'Inativo' ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-6 pt-4 border-t border-stroke">
                            <p class="text-xs text-muted flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[14px]">info</span>
                                <?php if ($isAdmin): ?>
                                    Para adicionar ou gerenciar membros, acesse o painel de <a href="/admin/users" class="text-lime hover:underline">Administração</a>.
                                <?php else: ?>
                                    Solicite ao administrador da empresa para adicionar novos membros.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ TAB: Campos Personalizados ═══ -->
            <div id="panel-custom-fields" class="settings-panel hidden">
                <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">
                    <h3 class="text-lg font-bold text-text mb-6 flex items-center gap-2 pb-4 border-b border-stroke">
                        <span class="material-symbols-outlined text-muted text-[20px]">edit_note</span> Campos Personalizados
                    </h3>

                    <p class="text-sm text-muted mb-6">Crie campos extras que aparecerão nos formulários de leads. Limite: 20 campos por empresa.</p>

                    <!-- Existing Custom Fields -->
                    <?php if (!empty($customFields)): ?>
                    <div class="space-y-3 mb-8">
                        <?php foreach ($customFields as $cf):
                            $typeLabel = match($cf['field_type']) {
                                'text' => 'Texto',
                                'number' => 'Número',
                                'select' => 'Seleção',
                                'date' => 'Data',
                                'boolean' => 'Sim/Não',
                                default => $cf['field_type'],
                            };
                            $opts = $cf['options'] ? json_decode($cf['options'], true) : [];
                        ?>
                        <div class="flex items-center justify-between p-4 bg-surface2/50 border border-stroke rounded-lg group">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-muted text-[18px]">
                                    <?= match($cf['field_type']) {
                                        'number' => 'tag',
                                        'select' => 'list',
                                        'date' => 'calendar_today',
                                        'boolean' => 'toggle_on',
                                        default => 'text_fields',
                                    } ?>
                                </span>
                                <div>
                                    <span class="text-sm font-semibold text-text"><?= htmlspecialchars($cf['field_label']) ?></span>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] font-bold uppercase tracking-wider bg-surface2 text-muted px-2 py-0.5 rounded-full"><?= $typeLabel ?></span>
                                        <?php if ($cf['required']): ?>
                                            <span class="text-[10px] font-bold uppercase tracking-wider bg-amber-500/15 text-amber-400 px-2 py-0.5 rounded-full">Obrigatório</span>
                                        <?php endif; ?>
                                        <?php if (!empty($opts)): ?>
                                            <span class="text-[10px] text-muted"><?= count($opts) ?> opções</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="/settings/custom-field/delete" class="opacity-0 group-hover:opacity-100 transition-opacity"
                                  onsubmit="return confirm('Remover o campo \'<?= htmlspecialchars($cf['field_label'], ENT_QUOTES) ?>\'? Os valores existentes nos leads serão perdidos.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="field_id" value="<?= $cf['id'] ?>">
                                <button type="submit" class="p-2 text-muted hover:text-red-400 transition-colors" title="Remover campo">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Add New Field Form -->
                    <?php if (count($customFields) < 20): ?>
                    <div class="border border-dashed border-stroke rounded-lg p-6">
                        <h4 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-lime text-[18px]">add_circle</span>
                            Novo Campo
                        </h4>
                        <form method="POST" action="/settings/custom-field" class="space-y-4" id="newFieldForm">
                            <?= csrf_field() ?>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1.5">Nome do Campo *</label>
                                    <input type="text" name="field_label" required maxlength="50" placeholder="Ex: CNPJ, Faturamento Anual..."
                                           class="w-full h-10 bg-surface2 border border-stroke rounded-lg px-4 text-sm text-text placeholder:text-subtle focus:outline-none focus:border-lime/50 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1.5">Tipo</label>
                                    <select name="field_type" id="newFieldType"
                                            class="w-full h-10 bg-surface2 border border-stroke rounded-lg px-4 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer [color-scheme:dark]">
                                        <option value="text">Texto</option>
                                        <option value="number">Número</option>
                                        <option value="select">Seleção (dropdown)</option>
                                        <option value="date">Data</option>
                                        <option value="boolean">Sim/Não</option>
                                    </select>
                                </div>
                            </div>

                            <div id="selectOptionsRow" class="hidden">
                                <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1.5">Opções (separadas por vírgula)</label>
                                <input type="text" name="field_options" placeholder="Ex: Pequeno, Médio, Grande"
                                       class="w-full h-10 bg-surface2 border border-stroke rounded-lg px-4 text-sm text-text placeholder:text-subtle focus:outline-none focus:border-lime/50 transition-colors">
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="field_required" value="1" class="w-4 h-4 rounded border-stroke bg-surface2 text-lime focus:ring-lime/50">
                                <span class="text-sm text-muted">Campo obrigatório</span>
                            </label>

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="h-10 px-5 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all">
                                    Criar Campo
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="text-sm text-muted bg-surface2/50 border border-stroke rounded-lg p-4 text-center">
                        Limite de 20 campos atingido. Remova campos existentes para criar novos.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    // Tab navigation
    const tabs = document.querySelectorAll('.settings-tab');
    const panels = document.querySelectorAll('.settings-panel');

    function switchTab(tabName) {
        tabs.forEach(t => {
            if (t.dataset.tab === tabName) {
                t.className = 'settings-tab w-full text-left block px-4 py-2.5 text-sm font-bold text-lime bg-lime/10 rounded-lg transition-colors';
            } else {
                t.className = 'settings-tab w-full text-left block px-4 py-2.5 text-sm font-medium text-muted hover:text-text hover:bg-surface2 rounded-lg transition-colors';
            }
        });
        panels.forEach(p => {
            p.classList.toggle('hidden', p.id !== 'panel-' + tabName);
        });
        // Update URL hash without scrolling
        history.replaceState(null, '', '#' + tabName);
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    // Check URL hash on load
    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById('panel-' + hash)) {
        switchTab(hash);
    }

    // Toggle select options visibility
    const fieldType = document.getElementById('newFieldType');
    const optionsRow = document.getElementById('selectOptionsRow');
    if (fieldType && optionsRow) {
        fieldType.addEventListener('change', () => {
            optionsRow.classList.toggle('hidden', fieldType.value !== 'select');
        });
    }
})();
</script>
