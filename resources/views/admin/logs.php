<?php
$pageTitle    = 'Logs Globais (Admin)';
$pageSubtitle = 'Monitoramento do ecossistema';
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-text">Audit & Logs Globais</h2>
            <p class="text-sm text-subtle mt-0.5">Acompanhe as trilhas do consumo de IA e ações no sistema da sua agência.</p>
        </div>
        <div class="flex items-center gap-2 text-xs text-muted bg-surface2 border border-stroke px-3 py-1.5 rounded-pill shadow-soft">
            <span class="material-symbols-outlined text-[14px]">history</span>
            Visão Global
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        
        <!-- Token Consumption Left -->
        <div class="bg-surface border border-stroke rounded-cardLg overflow-hidden shadow-soft flex flex-col h-[700px]">
            <div class="px-6 py-5 border-b border-stroke bg-surface2 font-bold text-sm text-text flex items-center gap-2 flex-shrink-0">
                <span class="material-symbols-outlined text-lime text-base">psychology</span>
                Consumo de Tokens (Operações)
            </div>
            
            <div class="flex-1 overflow-y-auto px-4 py-2 custom-scrollbar">
                <?php if (empty($tokenLogs)): ?>
                    <div class="p-5 text-center text-sm text-subtle">Não há registros de tokens recentes.</div>
                <?php else: ?>
                    <ul class="relative border-l border-stroke/70 ml-3 py-4 space-y-5">
                    <?php foreach ($tokenLogs as $tl): ?>
                        <li class="pl-6 relative group">
                            <!-- timeline dot -->
                            <div class="absolute w-2.5 h-2.5 bg-lime rounded-full -left-[5.5px] top-1.5 border-2 border-surface2 shadow-glow group-hover:scale-125 transition-transform"></div>
                            
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[13px] font-bold text-text mb-1 flex items-center gap-1.5">
                                        <?= e($tl['operation']) ?>
                                    </p>
                                    <p class="text-[11px] text-muted leading-relaxed max-w-[90%]">
                                        <?php if (!empty($tl['lead_name'])): ?>
                                        Afetou o Lead: <span class="text-text font-medium"><?= e($tl['lead_name']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="flex-shrink-0 text-[10px] font-bold tracking-widest text-lime bg-lime/10 px-2 py-1 rounded-pill uppercase border border-lime/20 shadow-glow-sm">
                                    -<?= $tl['tokens_used'] ?> pts
                                </span>
                            </div>
                            <span class="text-[9px] text-subtle font-medium mt-1.5 block uppercase tracking-wider"><?= e(date('d/m/y H:i', strtotime($tl['created_at']))) ?></span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Activities Right -->
        <div class="bg-surface border border-stroke rounded-cardLg overflow-hidden shadow-soft flex flex-col h-[700px]">
            <div class="px-6 py-5 border-b border-stroke bg-surface2 font-bold text-sm text-text flex items-center gap-2 flex-shrink-0">
                <span class="material-symbols-outlined text-mint text-base">manage_history</span>
                Atividades Operacionais
            </div>
            
            <div class="flex-1 overflow-y-auto px-4 py-2 custom-scrollbar">
                <?php if (empty($activities)): ?>
                    <div class="p-5 text-center text-sm text-subtle">Não há atividades registradas baseadas nos usuários.</div>
                <?php else: ?>
                    <ul class="relative border-l border-stroke/70 ml-3 py-4 space-y-6">
                    <?php foreach ($activities as $act): ?>
                        <?php
                            $colors = [
                                'note' => 'text-muted bg-surface3',
                                'call' => 'text-lime bg-lime/10',
                                'email' => 'text-operon-energy bg-operon-energy/10',
                                'whatsapp' => 'text-mint bg-mint/10',
                                'stage_change' => 'text-text bg-stroke',
                                'ai_analysis'  => 'text-lime bg-lime/10 shadow-glow-sm',
                            ];
                            $colorCls = $colors[$act['type']] ?? 'text-muted bg-surface3';
                        ?>
                        <li class="pl-6 relative group">
                            <!-- timeline dot -->
                            <div class="absolute w-2 h-2 bg-text/50 rounded-full -left-[4.5px] top-2 border border-surface2 group-hover:bg-text transition-colors"></div>
                            
                            <div class="bg-surface2 border border-stroke p-4 rounded-xl shadow-sm">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold uppercase tracking-widest <?= $colorCls ?> border border-stroke/50 px-2 py-0.5 rounded-pill">
                                        <?= e($act['type']) ?>
                                    </span>
                                    <span class="text-[10px] text-subtle"><?= e(date('d M y H:i', strtotime($act['created_at']))) ?></span>
                                </div>
                                <p class="text-[13px] font-bold text-text mb-1"><?= e($act['title']) ?></p>
                                
                                <div class="text-[11px] text-muted leading-relaxed line-clamp-3 mb-3">
                                    <?= nl2br(e($act['content'] ?? '')) ?>
                                </div>

                                <div class="flex flex-col gap-1 mt-3 pt-3 border-t border-stroke/50">
                                    <span class="text-[10px] text-subtle flex items-center gap-1.5"><span class="material-symbols-outlined text-[12px]">person</span> Autor: <strong class="text-text font-medium"><?= e($act['user_name'] ?? 'Sistema') ?></strong></span>
                                    <span class="text-[10px] text-subtle flex items-center gap-1.5"><span class="material-symbols-outlined text-[12px]">target</span> Lead: <strong class="text-text font-medium"><?= e($act['lead_name'] ?? '—') ?></strong></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
