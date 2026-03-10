<?php $pageTitle = 'Agenda'; $pageSubtitle = 'Follow-ups Agendados'; ?>

<div class="p-6 flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">Agenda de Atividades</h2>
            <p class="text-sm text-slate-400 mt-0.5"><?= count($followups ?? []) ?> follow-ups pendentes</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Pending -->
        <div class="lg:col-span-2">
            <h3 class="text-sm font-bold text-white mb-4">Pendentes</h3>
            <div class="flex flex-col gap-3">
                <?php if (empty($followups)): ?>
                <div class="flex flex-col items-center justify-center py-12 bg-brand-surface border border-white/8 rounded-2xl text-center">
                    <span class="material-symbols-outlined text-4xl text-slate-600 mb-2">calendar_today</span>
                    <p class="text-sm text-slate-500">Nenhum follow-up agendado.</p>
                </div>
                <?php else: ?>
                <?php foreach ($followups as $fu): ?>
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-4 flex items-start gap-4 hover:border-white/15 transition-all">
                    <div class="size-10 rounded-xl bg-primary/15 border border-primary/30 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-primary text-lg">notifications_active</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-white"><?= e($fu['title']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5"><?= e($fu['lead_name']) ?> · <?= e($fu['lead_segment']) ?></p>
                            </div>
                            <span class="text-xs text-slate-500 flex-shrink-0"><?= date('d/m H:i', strtotime($fu['scheduled_at'])) ?></span>
                        </div>
                        <?php if ($fu['description']): ?>
                        <p class="text-xs text-slate-500 mt-2"><?= e($fu['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="/vault/<?= $fu['lead_id'] ?>" class="text-operon-energy hover:underline text-xs font-medium flex-shrink-0">Ver lead →</a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed -->
        <div>
            <h3 class="text-sm font-bold text-white mb-4">Concluídos</h3>
            <div class="flex flex-col gap-2">
                <?php foreach (($completed ?? []) as $fu): ?>
                <div class="bg-white/3 border border-white/6 rounded-xl p-3 opacity-70">
                    <p class="text-xs font-semibold text-slate-400 line-through"><?= e($fu['title']) ?></p>
                    <p class="text-[10px] text-slate-600 mt-0.5"><?= e($fu['lead_name']) ?></p>
                </div>
                <?php endforeach; ?>
                <?php if (empty($completed)): ?>
                <p class="text-xs text-slate-600 text-center py-6">Nenhum concluído</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
