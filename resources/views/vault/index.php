<?php
$pageTitle    = 'Vault';
$pageSubtitle = 'Pipeline de Prospecção';

$stageColors = [
    'new'        => 'bg-slate-400',
    'contacted'  => 'bg-blue-400',
    'qualified'  => 'bg-violet-400',
    'proposal'   => 'bg-yellow-400',
    'closed_won' => 'bg-operon-energy',
    'closed_lost'=> 'bg-red-400',
];
$stageDotColors = [
    'new'        => '#94A3B8',
    'contacted'  => '#60A5FA',
    'qualified'  => '#A78BFA',
    'proposal'   => '#FBBF24',
    'closed_won' => '#18C29C',
    'closed_lost'=> '#F87171',
];
?>

<!-- Subheader -->
<div class="flex items-center justify-between border-b border-slate-800 bg-operon-charcoal px-6 py-0">
    <div class="flex gap-6">
        <a href="?view=kanban<?= !empty($filters['segment']) ? '&segment=' . urlencode($filters['segment']) : '' ?>"
           class="border-b-2 px-1 py-4 text-sm font-semibold flex items-center gap-2 transition-colors
                  <?= $view === 'kanban' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-300' ?>">
            <span class="material-symbols-outlined text-lg">view_kanban</span>
            Kanban
        </a>
        <a href="?view=list<?= !empty($filters['segment']) ? '&segment=' . urlencode($filters['segment']) : '' ?>"
           class="border-b-2 px-1 py-4 text-sm font-semibold flex items-center gap-2 transition-colors
                  <?= $view === 'list' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-300' ?>">
            <span class="material-symbols-outlined text-lg">list_alt</span>
            Lista
        </a>
    </div>
    <div class="flex items-center gap-2">
        <!-- Search -->
        <form method="GET" action="/vault" class="flex items-center gap-2">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input type="text" name="q" value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="Buscar lead..."
                   class="bg-white/5 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 w-40">
            <button type="submit" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-lg">search</span>
            </button>
        </form>
        <!-- New Lead -->
        <button onclick="openModal('newLeadModal')"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:brightness-110 transition-all shadow-md shadow-primary/20">
            <span class="material-symbols-outlined text-base">add</span>
            Novo Lead
        </button>
    </div>
</div>

<!-- ── Kanban View ─────────────────────────────────────────── -->
<?php if ($view === 'kanban'): ?>
<div class="flex-1 overflow-x-auto p-6 h-full">
    <div class="flex h-full gap-5 min-w-max">
        <?php foreach ($columns as $stageKey => $col): ?>
        <div class="flex w-72 flex-col gap-3 kanban-column" data-stage="<?= $stageKey ?>">

            <!-- Column header -->
            <div class="flex items-center justify-between px-1">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full <?= $stageColors[$stageKey] ?? 'bg-slate-400' ?>"></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400"><?= e($col['label']) ?></h3>
                </div>
                <span class="text-[10px] font-bold bg-white/8 text-slate-400 px-2 py-0.5 rounded-md column-count"><?= count($col['leads']) ?></span>
            </div>

            <!-- Cards container -->
            <div class="flex flex-col gap-2 kanban-cards overflow-y-auto flex-1 pr-0.5" style="max-height: calc(100vh - 220px);">
                <?php foreach ($col['leads'] as $lead):
                    $score = $lead['priority_score'] ?? 0;
                ?>
                <div class="kanban-card group flex flex-col gap-3 rounded-xl border border-white/8 bg-brand-surface p-4 hover:border-white/15 transition-all"
                     data-lead-id="<?= $lead['id'] ?>" data-stage="<?= $stageKey ?>">

                    <?php
                        $rawTags = $lead['tags'] ?? [];
                        $tags = is_string($rawTags) ? (json_decode($rawTags, true) ?: []) : (is_array($rawTags) ? $rawTags : []);
                        
                        $rawCtx = $lead['human_context'] ?? [];
                        $context = is_string($rawCtx) ? (json_decode($rawCtx, true) ?: []) : (is_array($rawCtx) ? $rawCtx : []);
                        
                        $temp = $context['temperature'] ?? null;
                    ?>

                    <!-- Top row -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <a href="/vault/<?= $lead['id'] ?>" class="text-sm font-bold text-white truncate block hover:text-operon-energy transition-colors">
                                <?= e($lead['name']) ?>
                            </a>
                            <p class="text-[11px] text-slate-500 truncate mt-0.5"><?= e($lead['segment']) ?></p>
                        </div>
                        <span class="inline-block px-2 py-0.5 rounded-lg text-[11px] font-black flex-shrink-0 <?= scoreBg($score) ?>"><?= $score ?></span>
                    </div>

                    <!-- Context & Tags -->
                    <?php if ($temp || !empty($tags)): ?>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <?php if ($temp): 
                            $tempColors = [
                                'Quente' => 'bg-red-500/20 text-red-400 border border-red-500/30',
                                'Morno'  => 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
                                'Frio'   => 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
                            ];
                            $tempClass = $tempColors[$temp] ?? 'bg-slate-500/20 text-slate-400 border border-slate-500/30';
                        ?>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $tempClass ?> flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[10px]">thermostat</span>
                                <?= e($temp) ?>
                            </span>
                        <?php endif; ?>

                        <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                            <span class="px-1.5 py-0.5 bg-zinc-700/50 text-zinc-300 rounded text-[10px] truncate max-w-[80px]">
                                #<?= e($tag) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (count($tags) > 3): ?>
                            <span class="px-1.5 py-0.5 bg-zinc-700/50 text-zinc-400 rounded text-[10px]">
                                +<?= count($tags) - 3 ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Contact info -->
                    <?php if (!empty($lead['phone']) || !empty($lead['website'])): ?>
                    <div class="flex items-center gap-3 text-[11px] text-slate-500">
                        <?php if (!empty($lead['phone'])): ?>
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">phone</span>
                            <?= e($lead['phone']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($lead['website'])): ?>
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">language</span>
                            Online
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Footer actions -->
                    <div class="flex items-center justify-between pt-1 border-t border-white/6">
                        <span class="text-[10px] text-slate-600"><?= timeAgo($lead['created_at']) ?></span>
                        <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="/vault/<?= $lead['id'] ?>" class="size-6 rounded-lg bg-white/5 flex items-center justify-center hover:bg-white/10 text-slate-400 hover:text-white transition-all" title="Ver detalhes">
                                <span class="material-symbols-outlined text-xs">open_in_new</span>
                            </a>
                            <?php if (!empty($lead['phone'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/\D/', '', $lead['phone']) ?>" target="_blank"
                               class="size-6 rounded-lg bg-green-500/10 flex items-center justify-center hover:bg-green-500/20 text-green-400 transition-all" title="WhatsApp">
                                <span class="material-symbols-outlined text-xs">chat</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Empty state -->
                <?php if (empty($col['leads'])): ?>
                <div class="rounded-xl border border-dashed border-white/10 p-6 text-center">
                    <p class="text-xs text-slate-600">Arraste leads aqui</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── List View ──────────────────────────────────────────── -->
<?php else: ?>
<div class="p-6">
    <div class="bg-brand-surface border border-white/8 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/8">
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider px-5 py-3">Empresa</th>
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Segmento</th>
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider px-4 py-3">Score</th>
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Status</th>
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Contato</th>
                        <th class="text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider px-5 py-3">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/4">
                    <?php foreach ($leads as $lead): 
                        $rawTags = $lead['tags'] ?? [];
                        $tags = is_string($rawTags) ? (json_decode($rawTags, true) ?: []) : (is_array($rawTags) ? $rawTags : []);
                        
                        $rawCtx = $lead['human_context'] ?? [];
                        $context = is_string($rawCtx) ? (json_decode($rawCtx, true) ?: []) : (is_array($rawCtx) ? $rawCtx : []);
                        
                        $temp = $context['temperature'] ?? null;
                    ?>
                    <tr class="hover:bg-white/3 transition-colors group">
                        <td class="px-5 py-3.5">
                            <div class="font-semibold text-white truncate max-w-[200px]"><?= e($lead['name']) ?></div>
                            <div class="text-[11px] text-slate-500 mb-1"><?= timeAgo($lead['created_at']) ?></div>
                             <?php if ($temp || !empty($tags)): ?>
                                <div class="flex flex-wrap items-center gap-1 mt-1">
                                    <?php if ($temp): 
                                        $tempColors = [
                                            'Quente' => 'bg-red-500/20 text-red-400 border border-red-500/30',
                                            'Morno'  => 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
                                            'Frio'   => 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
                                        ];
                                        $tempClass = $tempColors[$temp] ?? 'bg-slate-500/20 text-slate-400 border border-slate-500/30';
                                    ?>
                                        <span class="px-1 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider <?= $tempClass ?> flex items-center gap-0.5" title="Temperatura">
                                            <span class="material-symbols-outlined text-[10px]">thermostat</span>
                                            <?= e($temp) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                        <span class="px-1.5 py-0.5 bg-zinc-700/50 text-zinc-300 rounded text-[9px] truncate max-w-[80px]">
                                            #<?= e($tag) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($tags) > 3): ?>
                                        <span class="px-1.5 py-0.5 bg-zinc-700/50 text-zinc-400 rounded text-[9px]">
                                            +<?= count($tags) - 3 ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5 hidden md:table-cell">
                            <span class="text-xs text-slate-400"><?= e($lead['segment']) ?></span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-black <?= scoreBg($lead['priority_score'] ?? 0) ?>">
                                <?= $lead['priority_score'] ?? '—' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 hidden lg:table-cell">
                            <div class="flex items-center gap-1.5">
                                <span class="size-1.5 rounded-full inline-block" style="background:<?= $stageDotColors[$lead['pipeline_status']] ?? '#94A3B8' ?>"></span>
                                <span class="text-xs text-slate-400"><?= e(stageLabel($lead['pipeline_status'] ?? 'new')) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 hidden lg:table-cell">
                            <span class="text-xs text-slate-500"><?= e($lead['phone'] ?? '—') ?></span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <?php if (!empty($lead['phone'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $lead['phone']) ?>" target="_blank"
                                   class="size-7 rounded-lg bg-green-500/10 flex items-center justify-center hover:bg-green-500/20 text-green-400 transition-all" title="WhatsApp">
                                    <span class="material-symbols-outlined text-sm">chat</span>
                                </a>
                                <?php endif; ?>
                                <a href="/vault/<?= $lead['id'] ?>"
                                   class="size-7 rounded-lg bg-operon-energy/10 flex items-center justify-center hover:bg-operon-energy/20 text-operon-energy transition-all" title="Ver detalhes">
                                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($leads)): ?>
                    <tr><td colspan="6" class="text-center py-12 text-slate-500 text-sm">
                        <span class="material-symbols-outlined text-4xl block mb-2 text-slate-600">inbox</span>
                        Nenhum lead encontrado.
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── New Lead Modal ─────────────────────────────────────── -->
<div id="newLeadModal" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="w-full max-w-lg bg-brand-surface border border-white/10 rounded-2xl shadow-2xl animate-popIn">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/8">
            <h3 class="text-sm font-bold text-white">Novo Lead no Vault</h3>
            <button onclick="closeModal('newLeadModal')" class="text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="/vault" class="p-5 flex flex-col gap-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nome da Empresa *</label>
                    <input type="text" name="name" required placeholder="Ex: Clínica Dr. Silva"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                </div>
                <div class="col-span-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Segmento *</label>
                    <input type="text" name="segment" required placeholder="Ex: Saúde / Odontologia"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Website</label>
                    <input type="url" name="website" placeholder="https://..."
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Telefone / WhatsApp</label>
                    <input type="text" name="phone" placeholder="(11) 99999-9999"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                </div>
                <div class="col-span-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Endereço</label>
                    <input type="text" name="address" placeholder="Rua, Nº, Bairro, Cidade - Estado"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t border-white/8 mt-2">
                <button type="button" onclick="closeModal('newLeadModal')" class="px-4 py-2 rounded-xl bg-white/5 text-slate-400 text-sm font-medium hover:bg-white/10 transition-all">
                    Cancelar
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-white text-sm font-bold hover:brightness-110 transition-all shadow-md shadow-primary/20">
                    Adicionar ao Vault
                </button>
            </div>
        </form>
    </div>
</div>
