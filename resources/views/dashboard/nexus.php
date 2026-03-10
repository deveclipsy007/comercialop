<?php
$pageTitle    = 'Nexus';
$pageSubtitle = 'Central de Inteligência Comercial';
?>

<div class="p-6 flex flex-col gap-6">

    <!-- ── Header Row ─────────────────────────────────────── -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">Nexus Intelligence</h2>
            <p class="text-sm text-slate-400 mt-0.5">
                <?= date('l, d \d\e F \d\e Y') ?> · <?= count($recentLeads ?? []) ?> novos leads hoje
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/genesis" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-sm font-medium text-slate-300 hover:bg-white/10 transition-all">
                <span class="material-symbols-outlined text-lg">upload_file</span>
                Importar Leads
            </a>
            <a href="/vault" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:brightness-110 transition-all shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-lg">add</span>
                Novo Lead
            </a>
        </div>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $kpis = [
            [
                'label'  => 'Total de Leads',
                'value'  => $metrics['total_leads'],
                'icon'   => 'group',
                'color'  => 'text-blue-400',
                'bg'     => 'bg-blue-500/10 border-blue-500/20',
                'trend'  => '+12%',
            ],
            [
                'label'  => 'Qualificados',
                'value'  => $metrics['qualified_leads'],
                'icon'   => 'verified',
                'color'  => 'text-operon-energy',
                'bg'     => 'bg-operon-energy/10 border-operon-energy/20',
                'trend'  => '+8%',
            ],
            [
                'label'  => 'Em Proposta',
                'value'  => $metrics['proposals'],
                'icon'   => 'description',
                'color'  => 'text-yellow-400',
                'bg'     => 'bg-yellow-500/10 border-yellow-500/20',
                'trend'  => '+3',
            ],
            [
                'label'  => 'Convertidos',
                'value'  => $metrics['won'],
                'icon'   => 'emoji_events',
                'color'  => 'text-primary',
                'bg'     => 'bg-primary/10 border-primary/20',
                'trend'  => $metrics['conversion_rate'] . '%',
            ],
        ];
        ?>

        <?php foreach ($kpis as $kpi): ?>
        <div class="bg-brand-surface border border-white/8 rounded-2xl p-5 hover:border-white/15 transition-all group">
            <div class="flex items-start justify-between mb-3">
                <div class="size-10 rounded-xl <?= $kpi['bg'] ?> border flex items-center justify-center">
                    <span class="material-symbols-outlined <?= $kpi['color'] ?> text-xl"><?= $kpi['icon'] ?></span>
                </div>
                <span class="text-xs font-bold text-operon-energy bg-operon-energy/10 px-2 py-0.5 rounded-lg"><?= $kpi['trend'] ?></span>
            </div>
            <div class="text-2xl font-black text-white" data-countup="<?= $kpi['value'] ?>"><?= number_format($kpi['value'], 0, ',', '.') ?></div>
            <p class="text-xs text-slate-400 font-medium mt-1"><?= $kpi['label'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Pipeline + Hot Leads ───────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Pipeline Funnel -->
        <div class="lg:col-span-2 bg-brand-surface border border-white/8 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-sm font-bold text-white">Pipeline de Vendas</h3>
                <a href="/vault" class="text-xs text-operon-energy hover:underline font-medium">Ver Vault →</a>
            </div>
            <?php
            $stageData = [
                'new'        => ['label' => 'Prospecção',  'color' => 'bg-slate-400', 'pct' => 100],
                'contacted'  => ['label' => 'Contactado',  'color' => 'bg-blue-400',  'pct' => 75],
                'qualified'  => ['label' => 'Qualificado', 'color' => 'bg-violet-400','pct' => 50],
                'proposal'   => ['label' => 'Proposta',    'color' => 'bg-yellow-400','pct' => 30],
                'closed_won' => ['label' => 'Ganho',       'color' => 'bg-operon-energy', 'pct' => 15],
            ];
            foreach ($stageData as $stageKey => $stage):
                $count = $stats[$stageKey] ?? 0;
                $total = max($stats['total'] ?? 1, 1);
                $pct   = round(($count / $total) * 100);
            ?>
            <div class="mb-3">
                <div class="flex justify-between text-xs mb-1.5">
                    <span class="text-slate-400 font-medium"><?= $stage['label'] ?></span>
                    <span class="text-white font-bold"><?= $count ?> <span class="text-slate-500 font-normal">(<?= $pct ?>%)</span></span>
                </div>
                <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden">
                    <div class="h-full <?= $stage['color'] ?> rounded-full transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Hot Leads -->
        <div class="bg-brand-surface border border-white/8 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-white">🔥 Leads Quentes</h3>
                <a href="/vault?min_score=70" class="text-xs text-operon-energy hover:underline font-medium">Ver todos →</a>
            </div>
            <div class="flex flex-col gap-3">
                <?php if (empty($hotLeads)): ?>
                <p class="text-xs text-slate-500 text-center py-4">Nenhum lead qualificado ainda.</p>
                <?php else: ?>
                <?php foreach ($hotLeads as $lead): ?>
                <a href="/vault/<?= $lead['id'] ?>" class="flex items-center gap-3 p-3 rounded-xl bg-white/4 border border-white/6 hover:bg-white/8 hover:border-white/12 transition-all group">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white truncate group-hover:text-operon-energy transition-colors"><?= e($lead['name']) ?></p>
                        <p class="text-[11px] text-slate-500 truncate"><?= e($lead['segment']) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="inline-block px-2 py-1 rounded-lg text-xs font-black <?= scoreBg($lead['priority_score'] ?? 0) ?>"><?= $lead['priority_score'] ?? 0 ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Recent Activity ───────────────────────────────── -->
    <div class="bg-brand-surface border border-white/8 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-bold text-white">Leads Recentes</h3>
            <a href="/vault" class="text-xs text-operon-energy hover:underline font-medium">Ver todos →</a>
        </div>

        <?php if (empty($recentLeads)): ?>
        <div class="text-center py-8">
            <span class="material-symbols-outlined text-4xl text-slate-600 mb-2 block">inbox</span>
            <p class="text-sm text-slate-500">Nenhum lead cadastrado ainda.</p>
            <a href="/genesis" class="inline-flex items-center gap-1.5 mt-3 text-xs text-operon-energy hover:underline font-medium">
                <span class="material-symbols-outlined text-sm">upload_file</span>
                Importar via Genesis
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/6">
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider pb-3">Empresa</th>
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider pb-3 hidden md:table-cell">Segmento</th>
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider pb-3">Score</th>
                        <th class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider pb-3 hidden lg:table-cell">Status</th>
                        <th class="text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider pb-3">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/4">
                    <?php foreach ($recentLeads as $lead): ?>
                    <tr class="hover:bg-white/3 transition-colors">
                        <td class="py-3 pr-4">
                            <div class="font-semibold text-white truncate max-w-[160px]"><?= e($lead['name']) ?></div>
                        </td>
                        <td class="py-3 pr-4 hidden md:table-cell">
                            <span class="text-slate-400 text-xs"><?= e($lead['segment']) ?></span>
                        </td>
                        <td class="py-3 pr-4">
                            <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-black <?= scoreBg($lead['priority_score'] ?? 0) ?>">
                                <?= $lead['priority_score'] ?? '—' ?>
                            </span>
                        </td>
                        <td class="py-3 pr-4 hidden lg:table-cell">
                            <span class="text-xs text-slate-400"><?= e(stageLabel($lead['pipeline_status'] ?? 'new')) ?></span>
                        </td>
                        <td class="py-3 text-right">
                            <a href="/vault/<?= $lead['id'] ?>" class="text-xs text-operon-energy hover:underline font-medium">Ver →</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Token Economy Widget ──────────────────────────── -->
    <?php if (!empty($tokenBalance)): ?>
    <div class="bg-brand-surface border border-operon-energy/20 rounded-2xl p-5">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-operon-energy/15 rounded-xl border border-operon-energy/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-operon-energy text-xl">bolt</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-white">Tokens de IA</p>
                    <p class="text-xs text-slate-400">Plano <?= ucfirst($tokenBalance['tier'] ?? 'starter') ?> · Reset à meia-noite</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-lg font-black text-operon-energy"><?= $tokenBalance['remaining'] ?? 0 ?></p>
                <p class="text-[11px] text-slate-500">disponíveis de <?= $tokenBalance['limit'] ?? 0 ?></p>
            </div>
        </div>
        <div class="mt-4 w-full h-2 bg-white/5 rounded-full overflow-hidden">
            <?php $pct = min(100, round((($tokenBalance['used'] ?? 0) / max($tokenBalance['limit'] ?? 1, 1)) * 100)); ?>
            <div class="h-full rounded-full token-bar-fill <?= $pct >= 90 ? 'critical' : ($pct >= 70 ? 'warning' : '') ?>"
                 style="width: <?= $pct ?>%"></div>
        </div>
    </div>
    <?php endif; ?>

</div>
