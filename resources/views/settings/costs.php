<div class="max-w-7xl mx-auto p-6 md:p-8 space-y-8">
    <div class="mb-2">
        <h1 class="text-2xl font-bold text-text">Créditos da Plataforma</h1>
        <p class="text-sm text-muted mt-1">Acompanhe o consumo dos seus créditos de inteligência artificial.</p>
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

                <?php
                $crUsed = $tokenBalance['used'] ?? 0;
                $crLimit = $tokenBalance['limit'] ?? 100;
                $crRemaining = ($tokenBalance['remaining'] ?? ($crLimit - $crUsed));
                ?>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
                    <div class="bg-surface2 border border-stroke rounded-card p-6">
                        <p class="text-[11px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Créditos Usados</p>
                        <p class="text-3xl font-bold text-text tracking-tight"><?= number_format($crUsed, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-surface2 border border-stroke rounded-card p-6">
                        <p class="text-[11px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Limite Diário</p>
                        <p class="text-3xl font-bold text-text tracking-tight"><?= number_format($crLimit, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-surface2 border border-stroke rounded-card p-6 relative overflow-hidden">
                        <div class="absolute -right-3 -top-3 size-14 bg-lime/5 rounded-full blur-xl pointer-events-none"></div>
                        <p class="text-[11px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Créditos Restantes</p>
                        <p class="text-3xl font-bold text-lime tracking-tight"><?= number_format($crRemaining, 0, ',', '.') ?></p>
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
                            <?php
                            $opLabels = [
                                'lead_analysis' => 'Análise de Lead', 'deep_analysis' => 'Análise Profunda',
                                'deal_insights' => 'Insights de Negócio', 'hunter' => 'Hunter - Prospecção',
                                'hunter_search' => 'Hunter - Busca', 'spin_questions' => 'SPIN - Perguntas',
                                'copilot_message' => 'Copilot', 'social_analysis' => 'Análise Social',
                                'wa_summary' => 'WhatsApp - Resumo', 'wa_strategic' => 'WhatsApp - Estratégia',
                                'wa_next_message' => 'WhatsApp - Mensagem', 'wa_interest_score' => 'WhatsApp - Score',
                                'knowledge_index' => 'Knowledge Base', 'audio_strategy' => 'Análise de Áudio',
                                'operon_diagnostico' => 'Diagnóstico 4D', 'operon_potencial' => 'Potencial 4D',
                                'operon_autoridade' => 'Autoridade 4D', 'operon_script' => 'Script 4D',
                            ];
                            $opName = $opLabels[$entry['operation'] ?? ''] ?? ucfirst(str_replace('_', ' ', $entry['operation'] ?? 'Operação IA'));
                            ?>
                            <p class="text-sm font-bold text-text mb-0.5"><?= e($opName) ?></p>
                            <p class="text-[11px] font-medium text-muted"><?= (new DateTime($entry['created_at']))->format('d/m/Y H:i') ?></p>
                        </div>
                        <div class="text-right flex-shrink-0 ml-4">
                            <p class="text-sm font-bold text-lime tracking-wide">-<?= e((string)($entry['tokens_consumed'] ?? $entry['tokens_used'] ?? 0)) ?> créditos</p>
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
                <p class="text-sm text-subtle mb-6 leading-relaxed">Seus créditos são renovados diariamente à meia-noite (horário de Brasília). Upgrade de limites pode ser solicitado ao administrador.</p>

                <div class="space-y-3 text-xs text-subtle mb-6">
                    <p class="font-bold text-muted uppercase text-[10px] tracking-wider">Custo por funcionalidade</p>
                    <?php
                    $featureCosts = [
                        ['Busca Hunter', 15], ['Análise de Lead', 7], ['Análise Profunda', 20],
                        ['SPIN Perguntas', 4], ['Copilot', 3], ['WhatsApp IA', 7],
                    ];
                    foreach ($featureCosts as [$feat, $cost]):
                    ?>
                    <div class="flex justify-between items-center">
                        <span><?= $feat ?></span>
                        <span class="font-bold text-text"><?= $cost ?> cr</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>
