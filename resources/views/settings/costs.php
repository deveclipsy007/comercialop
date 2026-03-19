<div class="max-w-7xl mx-auto p-6 md:p-8 space-y-8">
    <div class="mb-2">
        <h1 class="text-2xl font-bold text-text">Custos e Tokens</h1>
        <p class="text-sm text-muted mt-1">Acompanhe o consumo da sua cota de inteligência artificial.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="col-span-1 md:col-span-2 space-y-6">
            <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
                <div class="flex items-center gap-4 mb-8">
                    <div class="size-12 bg-lime/10 rounded-full flex items-center justify-center border border-lime/20 flex-shrink-0">
                        <span class="material-symbols-outlined text-lime text-[24px]">payments</span>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-text leading-tight">Visão Geral de Custos</h3>
                        <p class="text-sm text-subtle mt-1">Uso de tokens e limites da inteligência artificial</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="bg-surface2 border border-stroke rounded-card p-6">
                        <p class="text-[11px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Uso Atual (Créditos)</p>
                        <p class="text-3xl font-bold text-text tracking-tight"><?= number_format($tokenBalance['used'] ?? 0, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-surface2 border border-stroke rounded-card p-6">
                        <p class="text-[11px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Limite Mensal</p>
                        <p class="text-3xl font-bold text-text tracking-tight"><?= number_format($tokenBalance['limit'] ?? 0, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-surface2 border border-stroke rounded-card p-6">
                        <p class="text-[11px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Tokens Reais (30d)</p>
                        <p class="text-3xl font-bold text-text tracking-tight"><?= number_format($costSummary['total_real_tokens'] ?? 0, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-surface2 border border-stroke rounded-card p-6 relative overflow-hidden">
                        <div class="absolute -right-3 -top-3 size-14 bg-lime/5 rounded-full blur-xl pointer-events-none"></div>
                        <p class="text-[11px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Custo Est. (30d)</p>
                        <p class="text-3xl font-bold text-lime tracking-tight">$<?= number_format($costSummary['total_cost'] ?? 0, 4, '.', ',') ?></p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between items-end mb-3">
                            <span class="text-sm font-bold text-text">Uso da Cota</span>
                            <span class="text-lg font-bold text-lime"><?= $tokenBalance['percent'] ?? 0 ?>%</span>
                        </div>
                        <div class="w-full h-3 bg-surface2 border border-stroke rounded-full overflow-hidden">
                            <div class="h-full bg-lime rounded-full transition-all duration-1000 shadow-[0_0_10px_rgba(225,251,21,0.5)]" style="width: <?= min(100, $tokenBalance['percent'] ?? 0) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div class="size-12 bg-surface2 rounded-full flex items-center justify-center border border-stroke flex-shrink-0">
                            <span class="material-symbols-outlined text-muted text-[24px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-text leading-tight">Histórico de Consumo</h3>
                            <p class="text-sm text-subtle mt-1">Gasto de tokens nas automações recentes</p>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($recentEntries)): ?>
                <div class="text-center py-12 bg-surface2 border border-dashed border-stroke rounded-card">
                    <span class="material-symbols-outlined text-5xl text-stroke mb-4 block">data_usage</span>
                    <p class="text-sm font-medium text-subtle">Nenhum consumo de token detectado no momento.</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach($recentEntries as $entry): ?>
                    <div class="flex items-center justify-between p-4 bg-surface2 border border-stroke rounded-card hover:bg-surface3 transition-colors">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-text mb-0.5"><?= e($entry['operation'] ?? 'Operação IA') ?></p>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-[11px] font-medium text-muted"><?= (new DateTime($entry['created_at']))->format('d/m/Y H:i') ?></p>
                                <?php if (!empty($entry['provider'])): ?>
                                <span class="text-[10px] font-bold text-subtle bg-surface border border-stroke rounded-full px-2 py-0.5"><?= e(ucfirst($entry['provider'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['model'])): ?>
                                <span class="text-[10px] font-mono text-subtle"><?= e($entry['model']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0 ml-4">
                            <p class="text-sm font-bold text-lime tracking-wide">-<?= e((string)($entry['tokens_consumed'] ?? $entry['tokens_used'] ?? 0)) ?> cr</p>
                            <?php if (!empty($entry['estimated_cost_usd'])): ?>
                            <p class="text-[10px] font-bold text-muted">$<?= number_format((float)$entry['estimated_cost_usd'], 4, '.', ',') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-span-1 space-y-6">
            <div class="bg-surface2 text-text border border-stroke rounded-cardLg p-7 relative overflow-hidden shadow-soft">
                <div class="absolute -top-12 -right-12 size-48 bg-lime/5 rounded-full blur-3xl mix-blend-screen pointer-events-none"></div>
                <h4 class="text-[11px] font-bold tracking-[0.1em] text-lime uppercase mb-4">Plano Atual</h4>
                <p class="text-[32px] font-bold mb-2 tracking-tight"><?= ucfirst(e($tokenBalance['tier'] ?? 'Starter')) ?></p>
                <p class="text-sm text-subtle mb-8 leading-relaxed">Baseado na licença do seu Tenant ativo. Upgrade de limites pode ser solicitado ao administrador.</p>
                <button class="w-full h-12 bg-surface border border-stroke hover:bg-surface3 text-text text-sm font-bold rounded-pill transition-all shadow-sm">
                    Gerenciar Plano
                </button>
            </div>
        </div>

    </div>
</div>
