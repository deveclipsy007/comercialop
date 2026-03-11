<?php
$pageTitle    = 'Vault';
$pageSubtitle = 'Pipeline de Prospecção';

$stageColors = [
    'new'        => 'bg-surface3',
    'contacted'  => 'bg-stroke',
    'qualified'  => 'bg-muted',
    'proposal'   => 'bg-text',
    'closed_won' => 'bg-lime',
    'closed_lost'=> 'bg-red-500',
];
$stageDotColors = [
    'new'        => '#202020',
    'contacted'  => '#2A2A2A',
    'qualified'  => '#A1A1AA',
    'proposal'   => '#F5F5F5',
    'closed_won' => '#E1FB15',
    'closed_lost'=> '#EF4444',
];
?>

<!-- Subheader -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-stroke bg-bg px-6 py-4 gap-4">
    <div class="flex gap-2">
        <a href="?view=kanban<?= !empty($filters['segment']) ? '&segment=' . urlencode($filters['segment']) : '' ?>"
           class="h-10 px-5 text-sm font-medium flex items-center gap-2 transition-all rounded-pill
                  <?= $view === 'kanban' ? 'bg-surface2 text-text border border-stroke' : 'bg-transparent text-muted hover:text-text hover:bg-surface' ?>">
            <span class="material-symbols-outlined text-[18px]">view_kanban</span>
            Kanban
        </a>
        <a href="?view=list<?= !empty($filters['segment']) ? '&segment=' . urlencode($filters['segment']) : '' ?>"
           class="h-10 px-5 text-sm font-medium flex items-center gap-2 transition-all rounded-pill
                  <?= $view === 'list' ? 'bg-surface2 text-text border border-stroke' : 'bg-transparent text-muted hover:text-text hover:bg-surface' ?>">
            <span class="material-symbols-outlined text-[18px]">list_alt</span>
            Lista
        </a>
    </div>
    <div class="flex items-center gap-3">
        <!-- Search -->
        <form method="GET" action="/vault" class="flex items-center gap-2">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-muted text-[18px]">search</span>
                <input type="text" name="q" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="Buscar lead..."
                       class="h-10 pl-11 pr-4 bg-surface border border-stroke rounded-pill text-sm text-text placeholder-muted focus:outline-none focus:border-lime/50 w-48 transition-all">
            </div>
        </form>
        <!-- New Lead -->
        <button onclick="openModal('newLeadModal')"
                class="flex items-center gap-2 h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow flex-shrink-0">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Novo Lead
        </button>
    </div>
</div>

<!-- ── Kanban View ─────────────────────────────────────────── -->
<?php if ($view === 'kanban'): ?>
<div class="flex-1 overflow-x-auto p-6 md:p-8 h-full">
    <div class="flex h-full gap-6 min-w-max">
        <?php foreach ($columns as $stageKey => $col): ?>
        <div class="flex w-80 flex-col gap-4 kanban-column" data-stage="<?= $stageKey ?>">

            <!-- Column header -->
            <div class="flex items-center justify-between px-2">
                <div class="flex items-center gap-2.5">
                    <span class="size-2.5 rounded-full <?= $stageColors[$stageKey] ?? 'bg-surface3' ?>"></span>
                    <h3 class="text-xs font-bold uppercase tracking-[0.1em] text-text"><?= e($col['label']) ?></h3>
                </div>
                <span class="text-[10px] font-bold bg-surface2 border border-stroke text-muted px-2.5 py-1 rounded-pill column-count"><?= count($col['leads']) ?></span>
            </div>

            <!-- Cards container -->
            <div class="flex flex-col gap-3 kanban-cards overflow-y-auto flex-1 pr-1" style="max-height: calc(100vh - 220px);">
                <?php foreach ($col['leads'] as $lead):
                    $score = $lead['priority_score'] ?? 0;
                ?>
                <div class="kanban-card group flex flex-col gap-4 rounded-card border border-stroke bg-surface p-5 shadow-soft hover:bg-surface2 transition-all cursor-grab active:cursor-grabbing"
                     data-lead-id="<?= $lead['id'] ?>" data-stage="<?= $stageKey ?>">

                    <?php
                        $rawTags = $lead['tags'] ?? [];
                        $tags = is_string($rawTags) ? (json_decode($rawTags, true) ?: []) : (is_array($rawTags) ? $rawTags : []);
                        
                        $rawCtx = $lead['human_context'] ?? [];
                        $context = is_string($rawCtx) ? (json_decode($rawCtx, true) ?: []) : (is_array($rawCtx) ? $rawCtx : []);
                        
                        $temp = $context['temperature'] ?? null;
                    ?>

                    <!-- Top row -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <a href="/vault/<?= $lead['id'] ?>" class="text-base font-bold text-text truncate block group-hover:text-lime transition-colors">
                                <?= e($lead['name']) ?>
                            </a>
                            <p class="text-xs text-muted truncate mt-0.5"><?= e($lead['segment']) ?></p>
                        </div>
                        <?php if ($score === '🔥100'): ?>
                            <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[11px] font-bold bg-lime text-bg flex-shrink-0 shadow-glow"><?= ltrim($score, '🔥') ?></span>
                        <?php else: ?>
                            <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[11px] font-bold bg-lime/10 text-lime border border-lime/20 flex-shrink-0"><?= $score ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Context & Tags -->
                    <?php if ($temp || !empty($tags)): ?>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <?php if ($temp): 
                            $tempColors = [
                                'Quente' => 'bg-red-500/10 text-red-500 border border-red-500/20',
                                'Morno'  => 'bg-amber-500/10 text-amber-500 border border-amber-500/20',
                                'Frio'   => 'bg-blue-500/10 text-blue-500 border border-blue-500/20'
                            ];
                            $tempClass = $tempColors[$temp] ?? 'bg-surface3 border border-stroke text-muted';
                        ?>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $tempClass ?> flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">thermostat</span>
                                <?= e($temp) ?>
                            </span>
                        <?php endif; ?>

                        <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                            <span class="px-2 py-0.5 bg-bg/50 border border-stroke text-muted rounded-md text-[10px] truncate max-w-[80px]">
                                #<?= e($tag) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (count($tags) > 3): ?>
                            <span class="px-2 py-0.5 bg-bg/50 border border-stroke text-muted rounded-md text-[10px]">
                                +<?= count($tags) - 3 ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Contact info -->
                    <?php if (!empty($lead['phone']) || !empty($lead['website'])): ?>
                    <div class="flex items-center gap-3 text-xs text-subtle">
                        <?php if (!empty($lead['phone'])): ?>
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">phone</span>
                            <?= e($lead['phone']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($lead['website'])): ?>
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">language</span>
                            Online
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Footer actions -->
                    <div class="flex items-center justify-between pt-4 border-t border-stroke">
                        <span class="text-[10px] font-medium text-subtle"><?= timeAgo($lead['created_at']) ?></span>
                        <div class="flex items-center gap-2 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="/vault/<?= $lead['id'] ?>" class="size-8 rounded-full bg-surface2 flex items-center justify-center hover:bg-surface3 border border-stroke text-muted hover:text-lime transition-all" title="Ver detalhes">
                                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                            </a>
                            <?php if (!empty($lead['phone'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/\D/', '', $lead['phone']) ?>" target="_blank"
                               class="size-8 rounded-full bg-mint/10 border border-mint/20 flex items-center justify-center hover:bg-mint/20 text-mint transition-all" title="WhatsApp">
                                <span class="material-symbols-outlined text-[16px]">chat</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Empty state -->
                <?php if (empty($col['leads'])): ?>
                <div class="rounded-card border border-dashed border-stroke p-8 text-center bg-surface w-full h-32 flex flex-col items-center justify-center">
                    <p class="text-xs font-medium text-subtle">Solte cards aqui</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── List View ──────────────────────────────────────────── -->
<?php else: ?>
<div class="p-6 md:p-8">
    <div class="bg-surface border border-stroke rounded-cardLg overflow-hidden shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-stroke bg-bg/50">
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] px-6 py-4">Empresa</th>
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] px-4 py-4 hidden md:table-cell">Setor</th>
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] px-4 py-4">Score</th>
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] px-4 py-4 hidden lg:table-cell">Estágio</th>
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] px-4 py-4 hidden lg:table-cell">Contato</th>
                        <th class="text-right text-[11px] font-bold text-muted uppercase tracking-[0.1em] px-6 py-4">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stroke">
                    <?php foreach ($leads as $lead): 
                        $rawTags = $lead['tags'] ?? [];
                        $tags = is_string($rawTags) ? (json_decode($rawTags, true) ?: []) : (is_array($rawTags) ? $rawTags : []);
                        
                        $rawCtx = $lead['human_context'] ?? [];
                        $context = is_string($rawCtx) ? (json_decode($rawCtx, true) ?: []) : (is_array($rawCtx) ? $rawCtx : []);
                        
                        $temp = $context['temperature'] ?? null;
                    ?>
                    <tr class="hover:bg-surface2 transition-colors group">
                        <td class="px-6 py-4">
                            <div class="font-bold text-text truncate max-w-[240px] group-hover:text-lime transition-colors"><?= e($lead['name']) ?></div>
                            <div class="text-[11px] text-subtle mt-1 mb-2 font-medium"><?= timeAgo($lead['created_at']) ?></div>
                             <?php if ($temp || !empty($tags)): ?>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <?php if ($temp): 
                                        $tempColors = [
                                            'Quente' => 'bg-red-500/10 text-red-500 border border-red-500/20',
                                            'Morno'  => 'bg-amber-500/10 text-amber-500 border border-amber-500/20',
                                            'Frio'   => 'bg-blue-500/10 text-blue-500 border border-blue-500/20'
                                        ];
                                        $tempClass = $tempColors[$temp] ?? 'bg-surface3 border border-stroke text-muted';
                                    ?>
                                        <span class="px-1.5 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider <?= $tempClass ?> flex items-center gap-0.5" title="Temperatura">
                                            <span class="material-symbols-outlined text-[10px]">thermostat</span>
                                            <?= e($temp) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                        <span class="px-2 py-0.5 bg-bg border border-stroke text-muted rounded-md text-[9px] truncate max-w-[80px]">
                                            #<?= e($tag) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($tags) > 3): ?>
                                        <span class="px-2 py-0.5 bg-bg border border-stroke text-muted rounded-md text-[9px]">
                                            +<?= count($tags) - 3 ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 hidden md:table-cell">
                            <span class="inline-flex text-[11px] font-medium text-muted bg-bg border border-stroke px-2 py-1 rounded-md"><?= e($lead['segment']) ?></span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center justify-center px-2 py-1 rounded-md text-[11px] font-bold bg-lime/10 text-lime border border-lime/20">
                                <?= $lead['priority_score'] ?? '—' ?>
                            </span>
                        </td>
                        <td class="px-4 py-4 hidden lg:table-cell">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full inline-block" style="background:<?= $stageDotColors[$lead['pipeline_status']] ?? '#202020' ?>"></span>
                                <span class="text-xs font-medium text-muted"><?= e(stageLabel($lead['pipeline_status'] ?? 'new')) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-4 hidden lg:table-cell">
                            <span class="text-xs text-subtle font-medium"><?= e($lead['phone'] ?? '—') ?></span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <?php if (!empty($lead['phone'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $lead['phone']) ?>" target="_blank"
                                   class="size-8 rounded-full bg-mint/10 border border-mint/20 flex items-center justify-center hover:bg-mint/20 text-mint transition-all" title="WhatsApp">
                                    <span class="material-symbols-outlined text-sm">chat</span>
                                </a>
                                <?php endif; ?>
                                <a href="/vault/<?= $lead['id'] ?>"
                                   class="size-8 rounded-full bg-surface2 border border-stroke text-muted flex items-center justify-center hover:text-lime hover:bg-surface3 transition-all" title="Ver detalhes">
                                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($leads)): ?>
                    <tr><td colspan="6" class="text-center py-16 text-muted text-sm">
                        <span class="material-symbols-outlined text-[48px] block mb-3 text-stroke">inbox</span>
                        O pipeline está vazio.
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── New Lead Modal ─────────────────────────────────────── -->
<div id="newLeadModal" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4 bg-bg/80 backdrop-blur-md">
    <div class="w-full max-w-lg bg-surface border border-stroke rounded-cardLg shadow-soft animate-popIn">
        <div class="flex items-center justify-between px-6 py-5 border-b border-stroke">
            <h3 class="text-base font-bold text-text">Novo Lead no Vault</h3>
            <button onclick="closeModal('newLeadModal')" class="size-8 flex items-center justify-center rounded-full bg-surface2 border border-stroke text-muted hover:text-text transition-colors">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <form method="POST" action="/vault" class="p-6 flex flex-col gap-5">
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-5">
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Nome da Empresa *</label>
                    <input type="text" name="name" required placeholder="Ex: Clínica Dr. Silva"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text placeholder-muted focus:outline-none focus:border-lime/50 transition-all">
                </div>
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Segmento *</label>
                    <input type="text" name="segment" required placeholder="Ex: Saúde / Odontologia"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text placeholder-muted focus:outline-none focus:border-lime/50 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Website</label>
                    <input type="url" name="website" placeholder="https://..."
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text placeholder-muted focus:outline-none focus:border-lime/50 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Telefone / WhatsApp</label>
                    <input type="text" name="phone" placeholder="(11) 99999-9999"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text placeholder-muted focus:outline-none focus:border-lime/50 transition-all">
                </div>
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Endereço</label>
                    <input type="text" name="address" placeholder="Rua, Nº, Bairro, Cidade - Estado"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text placeholder-muted focus:outline-none focus:border-lime/50 transition-all">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-stroke mt-2">
                <button type="button" onclick="closeModal('newLeadModal')" class="h-10 px-5 rounded-pill bg-surface2 border border-stroke text-muted text-sm font-medium hover:text-text hover:bg-surface3 transition-all">
                    Cancelar
                </button>
                <button type="submit" class="flex items-center gap-2 h-10 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 shadow-glow transition-all">
                    Adicionar Formulário
                </button>
            </div>
        </form>
    </div>
</div>
