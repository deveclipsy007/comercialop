<?php
$pageTitle    = 'Consumo de IA (Admin)';
$pageSubtitle = 'Dashboard de consumo e custos de inteligência artificial';

$totalOps       = $summary['total_ops'] ?? 0;
$totalCredits   = $summary['total_credits'] ?? 0;
$totalRealTok   = $summary['total_real_tokens'] ?? 0;
$totalCostUsd   = $summary['total_cost_usd'] ?? 0.0;
$activePeriod   = $period ?? 30;
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-xl font-black text-text">Dashboard de Consumo</h2>
            <p class="text-sm text-subtle mt-0.5">Visão consolidada do uso de IA por usuário, operação e custo estimado.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $days => $label): ?>
            <a href="/admin/consumption?period=<?= $days ?>"
               class="px-4 py-2 rounded-pill text-xs font-bold transition-all
                      <?= $activePeriod == $days ? 'bg-lime text-bg shadow-glow' : 'bg-surface2 border border-stroke text-muted hover:text-text' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface border border-stroke rounded-cardLg p-5 shadow-soft">
            <div class="flex items-center gap-3 mb-3">
                <div class="size-10 bg-lime/10 rounded-full flex items-center justify-center border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-lg">bolt</span>
                </div>
                <span class="text-[10px] font-bold text-muted uppercase tracking-widest">Operações</span>
            </div>
            <p class="text-2xl font-black text-text"><?= number_format($totalOps, 0, ',', '.') ?></p>
        </div>

        <div class="bg-surface border border-stroke rounded-cardLg p-5 shadow-soft">
            <div class="flex items-center gap-3 mb-3">
                <div class="size-10 bg-orange-400/10 rounded-full flex items-center justify-center border border-orange-400/20">
                    <span class="material-symbols-outlined text-orange-400 text-lg">token</span>
                </div>
                <span class="text-[10px] font-bold text-muted uppercase tracking-widest">Créditos Gastos</span>
            </div>
            <p class="text-2xl font-black text-text"><?= number_format($totalCredits, 0, ',', '.') ?></p>
        </div>

        <div class="bg-surface border border-stroke rounded-cardLg p-5 shadow-soft">
            <div class="flex items-center gap-3 mb-3">
                <div class="size-10 bg-blue-400/10 rounded-full flex items-center justify-center border border-blue-400/20">
                    <span class="material-symbols-outlined text-blue-400 text-lg">data_usage</span>
                </div>
                <span class="text-[10px] font-bold text-muted uppercase tracking-widest">Tokens Reais</span>
            </div>
            <p class="text-2xl font-black text-text"><?= number_format($totalRealTok, 0, ',', '.') ?></p>
        </div>

        <div class="bg-surface border border-stroke rounded-cardLg p-5 shadow-soft relative overflow-hidden">
            <div class="absolute -right-4 -top-4 size-20 bg-lime/5 rounded-full blur-xl pointer-events-none"></div>
            <div class="flex items-center gap-3 mb-3">
                <div class="size-10 bg-lime/10 rounded-full flex items-center justify-center border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-lg">attach_money</span>
                </div>
                <span class="text-[10px] font-bold text-muted uppercase tracking-widest">Custo USD Est.</span>
            </div>
            <p class="text-2xl font-black text-lime">$<?= number_format($totalCostUsd, 4, '.', ',') ?></p>
        </div>
    </div>

    <!-- Chart -->
    <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft">
        <h3 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-lime text-base">timeline</span>
            Tendência de Custo (<?= $activePeriod ?>d)
        </h3>
        <div class="h-64">
            <canvas id="costChart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Per User -->
        <div class="bg-surface border border-stroke rounded-cardLg shadow-soft overflow-hidden">
            <div class="px-5 py-4 border-b border-stroke">
                <h3 class="text-sm font-bold text-text flex items-center gap-2">
                    <span class="material-symbols-outlined text-lime text-base">group</span>
                    Consumo por Usuário
                </h3>
            </div>
            <?php if (empty($perUser)): ?>
            <div class="p-8 text-center text-sm text-subtle">Sem dados no período.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface2">
                            <th class="text-left px-4 py-2 text-[10px] font-bold text-muted uppercase">Usuário</th>
                            <th class="text-right px-4 py-2 text-[10px] font-bold text-muted uppercase">Ops</th>
                            <th class="text-right px-4 py-2 text-[10px] font-bold text-muted uppercase">Tokens</th>
                            <th class="text-right px-4 py-2 text-[10px] font-bold text-muted uppercase">USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perUser as $u): ?>
                        <tr class="border-t border-stroke/50 hover:bg-surface2/50">
                            <td class="px-4 py-2.5 font-bold text-text"><?= e($u['name'] ?? 'N/D') ?></td>
                            <td class="px-4 py-2.5 text-right text-muted"><?= number_format($u['ops'] ?? 0, 0, ',', '.') ?></td>
                            <td class="px-4 py-2.5 text-right text-muted"><?= number_format($u['real_tokens'] ?? 0, 0, ',', '.') ?></td>
                            <td class="px-4 py-2.5 text-right font-bold text-lime">$<?= number_format($u['cost_usd'] ?? 0, 4, '.', ',') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Per Operation -->
        <div class="bg-surface border border-stroke rounded-cardLg shadow-soft overflow-hidden">
            <div class="px-5 py-4 border-b border-stroke">
                <h3 class="text-sm font-bold text-text flex items-center gap-2">
                    <span class="material-symbols-outlined text-lime text-base">functions</span>
                    Consumo por Operação
                </h3>
            </div>
            <?php if (empty($perOperation)): ?>
            <div class="p-8 text-center text-sm text-subtle">Sem dados no período.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface2">
                            <th class="text-left px-4 py-2 text-[10px] font-bold text-muted uppercase">Operação</th>
                            <th class="text-left px-4 py-2 text-[10px] font-bold text-muted uppercase">Provider</th>
                            <th class="text-right px-4 py-2 text-[10px] font-bold text-muted uppercase">Calls</th>
                            <th class="text-right px-4 py-2 text-[10px] font-bold text-muted uppercase">USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perOperation as $op): ?>
                        <tr class="border-t border-stroke/50 hover:bg-surface2/50">
                            <td class="px-4 py-2.5">
                                <span class="font-bold text-text"><?= e($op['operation'] ?? '') ?></span>
                                <?php if (!empty($op['model'])): ?>
                                <span class="block text-[10px] text-subtle font-mono"><?= e($op['model']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-muted"><?= e($op['provider'] ?? '—') ?></td>
                            <td class="px-4 py-2.5 text-right text-muted"><?= number_format($op['calls'] ?? 0, 0, ',', '.') ?></td>
                            <td class="px-4 py-2.5 text-right font-bold text-lime">$<?= number_format($op['cost'] ?? 0, 4, '.', ',') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const resp = await fetch('/admin/consumption/api?days=<?= $activePeriod ?>');
        const data = await resp.json();
        const trend = data.trend || [];

        const ctx = document.getElementById('costChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: trend.map(d => d.day),
                datasets: [
                    {
                        label: 'Custo USD',
                        data: trend.map(d => parseFloat(d.cost || 0)),
                        backgroundColor: 'rgba(225, 251, 21, 0.3)',
                        borderColor: '#E1FB15',
                        borderWidth: 1,
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Operações',
                        data: trend.map(d => parseInt(d.ops || 0)),
                        type: 'line',
                        borderColor: '#60A5FA',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#60A5FA',
                        yAxisID: 'y1',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: '#A1A1AA', font: { size: 11 } } }
                },
                scales: {
                    x: { ticks: { color: '#71717A', font: { size: 10 } }, grid: { color: '#2A2A2A' } },
                    y: {
                        position: 'left',
                        ticks: { color: '#E1FB15', font: { size: 10 }, callback: v => '$' + v.toFixed(4) },
                        grid: { color: '#2A2A2A' }
                    },
                    y1: {
                        position: 'right',
                        ticks: { color: '#60A5FA', font: { size: 10 } },
                        grid: { display: false }
                    }
                }
            }
        });
    } catch (e) {
        console.error('Chart error:', e);
    }
});
</script>
