<?php
$pageTitle    = 'Nexus';
$pageSubtitle = 'Central de Inteligência Comercial';
?>

<div class="p-6 md:p-8 flex flex-col gap-8">

    <!-- ── Header Row ─────────────────────────────────────── -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="text-[32px] font-bold text-text tracking-tight">Nexus Intelligence</h2>
            <p class="text-base text-muted mt-1">
                <?= date('l, d \d\e F \d\e Y') ?> <span class="mx-2 text-stroke">•</span> <?= count($recentLeads ?? []) ?> novos leads hoje
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/genesis" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-surface border border-stroke text-sm font-medium text-text hover:bg-surface2 transition-all">
                <span class="material-symbols-outlined text-lg">upload_file</span>
                Importar Leads
            </a>
            <a href="/vault" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow">
                <span class="material-symbols-outlined text-lg">add</span>
                Novo Lead
            </a>
        </div>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
        <?php
        $kpis = [
            [
                'label'  => 'Total de Leads',
                'value'  => $metrics['total_leads'],
                'icon'   => 'group',
                'color'  => 'text-text',
                'bg'     => 'bg-surface2 border-stroke',
                'trend'  => '+12%',
            ],
            [
                'label'  => 'Qualificados',
                'value'  => $metrics['qualified_leads'],
                'icon'   => 'verified',
                'color'  => 'text-lime',
                'bg'     => 'bg-lime/10 border-lime/20',
                'trend'  => '+8%',
            ],
            [
                'label'  => 'Em Proposta',
                'value'  => $metrics['proposals'],
                'icon'   => 'description',
                'color'  => 'text-text',
                'bg'     => 'bg-surface2 border-stroke',
                'trend'  => '+3',
            ],
            [
                'label'  => 'Convertidos',
                'value'  => $metrics['won'],
                'icon'   => 'emoji_events',
                'color'  => 'text-mint',
                'bg'     => 'bg-mint/10 border-mint/20',
                'trend'  => $metrics['conversion_rate'] . '%',
            ],
        ];
        ?>

        <?php foreach ($kpis as $kpi): ?>
        <div class="bg-surface border border-stroke rounded-card p-6 shadow-soft hover:bg-surface2 transition-colors group">
            <div class="flex items-start justify-between mb-4">
                <div class="size-12 rounded-full <?= $kpi['bg'] ?> border flex items-center justify-center">
                    <span class="material-symbols-outlined <?= $kpi['color'] ?> text-[24px]"><?= $kpi['icon'] ?></span>
                </div>
                <span class="text-xs font-bold text-bg bg-mint px-2.5 py-1 rounded-pill"><?= $kpi['trend'] ?></span>
            </div>
            <div class="text-[40px] leading-none font-bold text-text tracking-tight" data-countup="<?= $kpi['value'] ?>"><?= number_format($kpi['value'], 0, ',', '.') ?></div>
            <p class="text-sm text-subtle font-medium mt-2"><?= $kpi['label'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Pipeline + Hot Leads ───────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Pipeline Funnel -->
        <div class="lg:col-span-2 bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-bold text-text">Pipeline de Vendas</h3>
                <a href="/vault" class="h-8 px-4 flex items-center rounded-pill bg-surface2 border border-stroke text-xs text-text hover:text-lime transition-colors">Ver Vault</a>
            </div>
            
            <div class="flex flex-col gap-5">
                <?php
                $stageData = [
                    'new'        => ['label' => 'Prospecção',  'color' => 'bg-surface3', 'pct' => 100],
                    'contacted'  => ['label' => 'Contactado',  'color' => 'bg-stroke',  'pct' => 75],
                    'qualified'  => ['label' => 'Qualificado', 'color' => 'bg-muted','pct' => 50],
                    'proposal'   => ['label' => 'Proposta',    'color' => 'bg-text','pct' => 30],
                    'closed_won' => ['label' => 'Ganho',       'color' => 'bg-lime', 'pct' => 15], // Striped pattern can be added via CSS on bg-lime
                ];
                foreach ($stageData as $stageKey => $stage):
                    $count = $stats[$stageKey] ?? 0;
                    $total = max($stats['total'] ?? 1, 1);
                    $pct   = round(($count / $total) * 100);
                ?>
                <div>
                    <div class="flex justify-between items-end mb-2">
                        <span class="text-sm font-medium text-muted"><?= $stage['label'] ?></span>
                        <span class="text-base font-bold text-text"><?= $count ?> <span class="text-xs text-subtle font-normal ml-1"><?= $pct ?>%</span></span>
                    </div>
                    <div class="w-full h-3 bg-surface2 rounded-full overflow-hidden border border-stroke">
                        <div class="h-full <?= $stage['color'] ?> rounded-full transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Hot Leads -->
        <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft flex flex-col">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-text flex items-center gap-2">Hot Leads <span class="size-2 bg-lime rounded-full animate-pulse"></span></h3>
                <a href="/vault?min_score=70" class="size-8 flex items-center justify-center rounded-full bg-surface2 border border-stroke text-muted hover:text-lime transition-all"><span class="material-symbols-outlined text-[18px]">arrow_forward</span></a>
            </div>
            <div class="flex flex-col gap-3 flex-1 overflow-y-auto">
                <?php if (empty($hotLeads)): ?>
                <div class="flex-1 flex flex-col items-center justify-center text-center">
                    <span class="material-symbols-outlined text-4xl text-stroke mb-2 block">ac_unit</span>
                    <p class="text-sm text-subtle">Nenhum lead quente no radar.</p>
                </div>
                <?php else: ?>
                <?php foreach ($hotLeads as $lead): ?>
                <a href="/vault/<?= $lead['id'] ?>" class="flex items-center gap-4 p-4 rounded-card bg-surface2 border border-stroke hover:bg-surface3 transition-all group">
                    <div class="size-10 rounded-full bg-bg border border-stroke flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-bold text-muted group-hover:text-lime"><?= substr($lead['name'], 0, 1) ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-text truncate group-hover:text-lime transition-colors"><?= e($lead['name']) ?></p>
                        <p class="text-[11px] text-muted truncate mt-0.5"><?= e($lead['segment']) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="inline-block px-3 py-1 rounded-pill text-xs font-bold bg-lime/10 text-lime border border-lime/20"><?= $lead['priority_score'] ?? 0 ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Token Economy Widget ──────────────────────────── -->
    <?php if (!empty($tokenBalance)): ?>
    <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="size-16 bg-lime/10 rounded-full border border-lime/20 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-lime text-[32px]">bolt</span>
                </div>
                <div>
                    <p class="text-xl font-bold text-text">Operon Tokens</p>
                    <p class="text-sm text-muted mt-1">Capacidade de IA generativa (Plano <?= ucfirst($tokenBalance['tier'] ?? 'starter') ?>)</p>
                </div>
            </div>
            
            <div class="flex-1 max-w-md w-full">
                <div class="flex justify-between items-end mb-2">
                    <span class="text-sm font-medium text-subtle">Tokens Consumidos</span>
                    <div class="text-right">
                        <span class="text-lg font-bold text-lime"><?= $tokenBalance['used'] ?? 0 ?></span>
                        <span class="text-xs text-muted ml-1">/ <?= $tokenBalance['limit'] ?? 0 ?></span>
                    </div>
                </div>
                <div class="w-full h-3 bg-surface2 rounded-full overflow-hidden border border-stroke relative">
                    <?php $pct = min(100, round((($tokenBalance['used'] ?? 0) / max($tokenBalance['limit'] ?? 1, 1)) * 100)); ?>
                    <!-- Striped texture logic can be applied, or simple fill -->
                    <div class="absolute inset-y-0 left-0 bg-lime rounded-full transition-all duration-1000"
                         style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ── Recent Activity ───────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-text">Monitoramento de Entradas</h3>
            <a href="/vault" class="h-8 px-4 flex items-center rounded-pill bg-surface2 border border-stroke text-xs text-text hover:text-lime transition-colors">Ver Vault</a>
        </div>

        <?php if (empty($recentLeads)): ?>
        <div class="flex flex-col items-center justify-center py-12">
            <span class="material-symbols-outlined text-4xl text-stroke mb-3 block">inbox</span>
            <p class="text-sm text-subtle mb-4">A base está vazia. O radar não detectou leads recentes.</p>
            <a href="/genesis" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-text hover:bg-surface3 transition-colors">
                <span class="material-symbols-outlined text-lg">upload_file</span> Iniciar Captura
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="border-b border-stroke">
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] pb-4 px-2">Alvo / Empresa</th>
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] pb-4 px-2 hidden md:table-cell">Setor</th>
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] pb-4 px-2">Score</th>
                        <th class="text-left text-[11px] font-bold text-muted uppercase tracking-[0.1em] pb-4 px-2 hidden lg:table-cell">Estágio</th>
                        <th class="text-right text-[11px] font-bold text-muted uppercase tracking-[0.1em] pb-4 px-2">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stroke">
                    <?php foreach ($recentLeads as $lead): ?>
                    <tr class="hover:bg-surface2 transition-colors group">
                        <td class="py-4 px-2">
                            <div class="font-bold text-text truncate max-w-[200px] group-hover:text-lime transition-colors"><?= e($lead['name']) ?></div>
                        </td>
                        <td class="py-4 px-2 hidden md:table-cell">
                            <span class="text-muted text-xs bg-bg border border-stroke px-2 py-1 rounded-md"><?= e($lead['segment']) ?></span>
                        </td>
                        <td class="py-4 px-2">
                            <span class="inline-block px-2.5 py-1 rounded-pill text-xs font-bold text-lime bg-lime/10 border border-lime/20">
                                <?= $lead['priority_score'] ?? '—' ?>
                            </span>
                        </td>
                        <td class="py-4 px-2 hidden lg:table-cell">
                            <span class="text-xs text-text flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-stroke"></span> <?= e(stageLabel($lead['pipeline_status'] ?? 'new')) ?></span>
                        </td>
                        <td class="py-4 px-2 text-right">
                            <a href="/vault/<?= $lead['id'] ?>" class="inline-flex size-8 items-center justify-center rounded-full bg-bg border border-stroke text-muted hover:text-lime transition-colors">
                                <span class="material-symbols-outlined text-sm">visibility</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
