<?php
/**
 * WhatsApp Conversation Detail — Intelligence Hub
 * Layout: Chat (8 cols) + Intelligence Sidebar (4 cols)
 */

$summaryData   = $summaryAnalysis['analysis_data']   ?? [];
$strategicData = $strategicAnalysis['analysis_data'] ?? [];
$scoreData     = $scoreAnalysis['analysis_data']     ?? [];
?>

<style>
    .wa-conv-root { height: calc(100vh - 96px); }
    .wa-tab-btn { transition: all 0.2s; }
    .wa-tab-btn.active { color: #22c55e; border-color: #22c55e; }
    .wa-tab-btn:not(.active) { color: #71717a; border-color: transparent; }
    .wa-tab-content { display: none; }
    .wa-tab-content.active { display: block; }
    .score-ring { transition: stroke-dashoffset 1s ease-out; }
    .wa-sidebar-scroll::-webkit-scrollbar { width: 3px; }
    .wa-sidebar-scroll::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 99px; }
    .wa-chip { transition: all 0.15s; }
    .wa-chip:hover .wa-chip-x { opacity: 1; }
    .wa-chip-x { opacity: 0; transition: opacity 0.15s; }
    .wa-msg-bubble { animation: fadeInMsg 0.15s ease-out; }
    @keyframes fadeInMsg { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .wa-ai-btn { transition: all 0.2s; }
    .wa-ai-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .breakdown-bar { transition: width 0.8s ease-out; }
</style>

<div class="wa-conv-root flex flex-col">
    <!-- Breadcrumbs -->
    <nav class="flex items-center text-zinc-500 text-sm gap-2 px-6 py-3 flex-shrink-0 border-b border-zinc-800/50">
        <a href="/whatsapp" class="hover:text-green-400 transition-all flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span> WhatsApp
        </a>
        <span class="text-zinc-700">/</span>
        <span class="text-zinc-300 font-medium"><?= htmlspecialchars($conversation['display_name']) ?></span>
        <?php if (!empty($scoreData['interest_score'])): ?>
            <span class="ml-auto px-3 py-1 rounded-pill text-[10px] font-bold
                <?php
                    $s = (int)$scoreData['interest_score'];
                    echo $s >= 70 ? 'bg-green-500/10 text-green-400 border border-green-500/20'
                       : ($s >= 40 ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                       : 'bg-red-500/10 text-red-400 border border-red-500/20');
                ?>">
                Score <?= $s ?>
            </span>
        <?php endif; ?>
    </nav>

    <!-- Main Content -->
    <div class="flex-1 flex overflow-hidden">
        <!-- ═══ CHAT PANEL (Left) ═══ -->
        <div class="flex-1 flex flex-col min-w-0 border-r border-zinc-800/50">
            <!-- Chat Header -->
            <div class="px-6 py-4 border-b border-zinc-800/50 flex items-center gap-4 flex-shrink-0 bg-zinc-900/30">
                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-green-500/20 to-emerald-600/20 border border-green-500/20 flex items-center justify-center text-lg font-bold text-green-400">
                    <?= mb_substr($conversation['display_name'], 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-bold text-white truncate"><?= htmlspecialchars($conversation['display_name']) ?></h2>
                    <p class="text-zinc-600 text-[11px] font-mono"><?= $conversation['phone'] ?? $conversation['remote_jid'] ?></p>
                </div>
                <span class="text-zinc-600 text-xs"><?= $total ?> mensagens</span>
            </div>

            <!-- Messages -->
            <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-3 wa-sidebar-scroll">
                <?php if (empty($messages)): ?>
                    <div class="h-full flex items-center justify-center text-zinc-600 italic text-sm">
                        Nenhuma mensagem sincronizada ainda.
                    </div>
                <?php else: ?>
                    <?php
                    $currentDate = '';
                    $msgsOrdered = array_reverse($messages);
                    foreach ($msgsOrdered as $msg):
                        $date = date('d/m/Y', $msg['timestamp']);
                        if ($date !== $currentDate):
                            $currentDate = $date;
                    ?>
                        <div class="text-center my-4">
                            <span class="bg-zinc-800/60 text-zinc-500 text-[10px] px-3 py-1 rounded-full uppercase tracking-widest font-bold">
                                <?= $date ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="flex <?= $msg['direction'] === 'outgoing' ? 'justify-end' : 'justify-start' ?> wa-msg-bubble">
                        <div class="max-w-[75%] px-4 py-2.5 relative <?= $msg['direction'] === 'outgoing'
                            ? 'bg-zinc-800 text-zinc-100 rounded-2xl rounded-tr-md'
                            : 'bg-green-500/8 text-green-50 border border-green-500/15 rounded-2xl rounded-tl-md' ?>">
                            <p class="text-[13px] leading-relaxed"><?= nl2br(htmlspecialchars($msg['body'] ?? '')) ?></p>
                            <div class="flex items-center justify-end gap-1 mt-1 text-[9px] text-zinc-500 font-mono">
                                <?= date('H:i', $msg['timestamp']) ?>
                                <?php if ($msg['direction'] === 'outgoing'): ?>
                                    <span class="text-green-500 text-[8px]">✓✓</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ INTELLIGENCE SIDEBAR (Right) ═══ -->
        <div class="w-[420px] min-w-[360px] flex flex-col bg-zinc-900/20 overflow-hidden">

            <!-- Leads Vinculados -->
            <div class="px-5 py-4 border-b border-zinc-800/50 flex-shrink-0">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px]">link</span> Leads Vinculados
                    </span>
                    <button onclick="LeadLinks.openModal()" class="text-[10px] font-bold text-green-500 hover:text-green-400 transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">add</span> Vincular
                    </button>
                </div>
                <div id="linked-leads-area" class="flex flex-wrap gap-2">
                    <?php if (empty($leads)): ?>
                        <span class="text-zinc-600 text-xs italic">Nenhum lead vinculado</span>
                    <?php else: ?>
                        <?php foreach ($leads as $l): ?>
                            <span class="wa-chip inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-green-500/8 text-green-400 border border-green-500/15">
                                <a href="/vault/<?= $l['id'] ?>" class="hover:underline" title="Ver no Vault"><?= htmlspecialchars($l['name']) ?></a>
                                <?php if (($l['priority_score'] ?? 0) > 0): ?>
                                    <span class="text-[9px] text-green-500/60"><?= $l['priority_score'] ?></span>
                                <?php endif; ?>
                                <button onclick="LeadLinks.unlink('<?= $l['id'] ?>')" class="wa-chip-x ml-0.5 text-zinc-500 hover:text-red-400 transition-all">
                                    <span class="material-symbols-outlined text-[12px]">close</span>
                                </button>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($suggestedLeads) && empty($leads)): ?>
                    <div class="mt-3 pt-3 border-t border-zinc-800/30">
                        <span class="text-[9px] text-zinc-600 font-bold uppercase">Sugestões</span>
                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                            <?php foreach ($suggestedLeads as $s): ?>
                                <button onclick="LeadLinks.link('<?= $s['id'] ?>')"
                                    class="px-2.5 py-1 rounded-md text-[10px] bg-zinc-800 text-zinc-400 hover:bg-green-500/10 hover:text-green-400 border border-zinc-700/50 transition-all">
                                    <?= htmlspecialchars($s['name']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab Navigation -->
            <div class="flex border-b border-zinc-800/50 flex-shrink-0 px-2">
                <button class="wa-tab-btn active flex-1 py-3 text-[10px] font-bold uppercase tracking-wider border-b-2 text-center" data-tab="summary">Resumo</button>
                <button class="wa-tab-btn flex-1 py-3 text-[10px] font-bold uppercase tracking-wider border-b-2 text-center" data-tab="strategic">Estratégia</button>
                <button class="wa-tab-btn flex-1 py-3 text-[10px] font-bold uppercase tracking-wider border-b-2 text-center" data-tab="score">Score</button>
                <button class="wa-tab-btn flex-1 py-3 text-[10px] font-bold uppercase tracking-wider border-b-2 text-center" data-tab="message">Mensagem</button>
            </div>

            <!-- Tab Content Area -->
            <div class="flex-1 overflow-y-auto wa-sidebar-scroll">

                <!-- ═══ TAB: RESUMO ═══ -->
                <div class="wa-tab-content active p-5 space-y-5" id="tab-summary">
                    <?php if (!empty($summaryData)): ?>
                        <div>
                            <p class="text-sm text-zinc-300 leading-relaxed"><?= htmlspecialchars($summaryData['summary'] ?? '') ?></p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-zinc-800/40 rounded-xl p-3 border border-zinc-800/50">
                                <span class="text-[9px] text-zinc-600 uppercase font-bold block mb-1">Interesse</span>
                                <?php $il = $summaryData['interest_level'] ?? 'N/D';
                                    $ilc = $il === 'Alto' ? 'text-green-400' : ($il === 'Médio' ? 'text-amber-400' : 'text-red-400'); ?>
                                <span class="text-sm font-bold <?= $ilc ?>"><?= $il ?></span>
                            </div>
                            <div class="bg-zinc-800/40 rounded-xl p-3 border border-zinc-800/50">
                                <span class="text-[9px] text-zinc-600 uppercase font-bold block mb-1">Urgência</span>
                                <?php $urg = $summaryData['urgency'] ?? 'N/D';
                                    $urgc = $urg === 'Alta' ? 'text-red-400' : ($urg === 'Média' ? 'text-amber-400' : 'text-zinc-400'); ?>
                                <span class="text-sm font-bold <?= $urgc ?>"><?= $urg ?></span>
                            </div>
                        </div>

                        <div class="bg-zinc-800/40 rounded-xl p-3 border border-zinc-800/50">
                            <span class="text-[9px] text-zinc-600 uppercase font-bold block mb-1">Estágio</span>
                            <span class="text-xs font-bold text-zinc-200"><?= $summaryData['conversation_stage'] ?? 'N/D' ?></span>
                        </div>

                        <?php if (!empty($summaryData['pains'])): ?>
                        <div>
                            <span class="text-[9px] text-red-400 uppercase font-bold block mb-2">Dores</span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($summaryData['pains'] as $p): ?>
                                    <span class="px-2 py-1 bg-red-500/5 text-red-400 text-[10px] rounded-md border border-red-500/10"><?= htmlspecialchars($p) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($summaryData['objections'])): ?>
                        <div>
                            <span class="text-[9px] text-amber-400 uppercase font-bold block mb-2">Objeções</span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($summaryData['objections'] as $o): ?>
                                    <span class="px-2 py-1 bg-amber-500/5 text-amber-400 text-[10px] rounded-md border border-amber-500/10"><?= htmlspecialchars($o) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($summaryData['buying_signals'])): ?>
                        <div>
                            <span class="text-[9px] text-green-400 uppercase font-bold block mb-2">Sinais de Compra</span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($summaryData['buying_signals'] as $bs): ?>
                                    <span class="px-2 py-1 bg-green-500/5 text-green-400 text-[10px] rounded-md border border-green-500/10"><?= htmlspecialchars($bs) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($summaryData['next_steps'])): ?>
                        <div class="bg-green-500/5 rounded-xl p-4 border border-green-500/10">
                            <span class="text-[9px] text-green-400 uppercase font-bold block mb-2">Próximos Passos</span>
                            <ul class="space-y-1.5">
                                <?php foreach ($summaryData['next_steps'] as $ns): ?>
                                    <li class="text-xs text-zinc-300 flex items-start gap-2">
                                        <span class="text-green-500 mt-0.5">→</span> <?= htmlspecialchars($ns) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-16">
                            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-zinc-800/50 border border-zinc-700/50 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl text-zinc-600">summarize</span>
                            </div>
                            <p class="text-zinc-500 text-sm mb-1">Nenhum resumo gerado</p>
                            <p class="text-zinc-600 text-xs mb-6">A IA analisará dores, objeções e sinais de compra.</p>
                        </div>
                    <?php endif; ?>
                    <button onclick="Intelligence.summary()" id="btn-summary" class="wa-ai-btn w-full h-10 bg-green-600 hover:bg-green-500 text-white text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-green-900/20">
                        <span class="material-symbols-outlined text-[16px]">auto_awesome</span>
                        <?= empty($summaryData) ? 'Gerar Resumo IA' : 'Atualizar Resumo' ?>
                    </button>
                </div>

                <!-- ═══ TAB: ESTRATÉGIA ═══ -->
                <div class="wa-tab-content p-5 space-y-5" id="tab-strategic">
                    <?php if (!empty($strategicData)): ?>
                        <div class="grid grid-cols-3 gap-2">
                            <?php
                            $meta = [
                                ['label' => 'Interesse', 'val' => $strategicData['interest_level'] ?? 'N/D'],
                                ['label' => 'Tom', 'val' => $strategicData['conversation_tone'] ?? 'N/D'],
                                ['label' => 'Intenção', 'val' => $strategicData['perceived_intent'] ?? 'N/D'],
                            ];
                            foreach ($meta as $m): ?>
                                <div class="bg-zinc-800/40 rounded-lg p-2.5 border border-zinc-800/50 text-center">
                                    <span class="text-[8px] text-zinc-600 uppercase font-bold block"><?= $m['label'] ?></span>
                                    <span class="text-[11px] font-bold text-zinc-200 mt-0.5 block"><?= $m['val'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($strategicData['main_objections'])): ?>
                        <div>
                            <span class="text-[9px] text-amber-400 uppercase font-bold block mb-2">Objeções & Contorno</span>
                            <div class="space-y-2">
                                <?php foreach ($strategicData['main_objections'] as $obj): ?>
                                    <div class="bg-zinc-800/30 rounded-lg p-3 border border-zinc-800/40">
                                        <div class="flex items-start justify-between gap-2 mb-1.5">
                                            <span class="text-xs text-zinc-300"><?= htmlspecialchars($obj['objection'] ?? '') ?></span>
                                            <?php $sev = $obj['severity'] ?? '';
                                                $sevc = $sev === 'Alta' ? 'text-red-400 bg-red-500/10' : ($sev === 'Média' ? 'text-amber-400 bg-amber-500/10' : 'text-zinc-400 bg-zinc-700/50'); ?>
                                            <span class="text-[8px] font-bold px-1.5 py-0.5 rounded <?= $sevc ?> flex-shrink-0"><?= $sev ?></span>
                                        </div>
                                        <p class="text-[11px] text-green-400/80 italic">💡 <?= htmlspecialchars($obj['suggested_response'] ?? '') ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($strategicData['opportunity_points'])): ?>
                        <div>
                            <span class="text-[9px] text-green-400 uppercase font-bold block mb-2">Oportunidades</span>
                            <div class="space-y-2">
                                <?php foreach ($strategicData['opportunity_points'] as $opp): ?>
                                    <div class="bg-green-500/5 rounded-lg p-3 border border-green-500/10">
                                        <span class="text-xs text-zinc-200 block mb-1"><?= htmlspecialchars($opp['opportunity'] ?? '') ?></span>
                                        <span class="text-[10px] text-green-400/70">→ <?= htmlspecialchars($opp['leverage'] ?? '') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($strategicData['loss_risk'])): ?>
                        <div class="bg-zinc-800/30 rounded-xl p-4 border border-zinc-800/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[9px] text-zinc-500 uppercase font-bold">Risco de Perda</span>
                                <?php $rl = $strategicData['loss_risk']['level'] ?? 'N/D';
                                    $rlc = $rl === 'Alto' ? 'text-red-400 bg-red-500/10' : ($rl === 'Médio' ? 'text-amber-400 bg-amber-500/10' : 'text-green-400 bg-green-500/10'); ?>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md <?= $rlc ?>"><?= $rl ?></span>
                            </div>
                            <p class="text-[11px] text-zinc-400"><?= htmlspecialchars($strategicData['loss_risk']['mitigation'] ?? '') ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($strategicData['recommended_actions'])): ?>
                        <div class="bg-green-500/5 rounded-xl p-4 border border-green-500/10">
                            <span class="text-[9px] text-green-400 uppercase font-bold block mb-2">Ações Recomendadas</span>
                            <ol class="space-y-1.5">
                                <?php foreach ($strategicData['recommended_actions'] as $i => $action): ?>
                                    <li class="text-xs text-zinc-300 flex items-start gap-2">
                                        <span class="text-green-500 font-bold text-[10px] mt-0.5"><?= $i + 1 ?>.</span>
                                        <?= htmlspecialchars($action) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-16">
                            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-zinc-800/50 border border-zinc-700/50 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl text-zinc-600">target</span>
                            </div>
                            <p class="text-zinc-500 text-sm mb-1">Análise estratégica pendente</p>
                            <p class="text-zinc-600 text-xs mb-6">Identifique riscos, objeções e oportunidades.</p>
                        </div>
                    <?php endif; ?>
                    <button onclick="Intelligence.strategic()" id="btn-strategic" class="wa-ai-btn w-full h-10 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 border border-zinc-700/50">
                        <span class="material-symbols-outlined text-[16px]">psychology</span>
                        <?= empty($strategicData) ? 'Análise Estratégica' : 'Recalcular Análise' ?>
                    </button>
                </div>

                <!-- ═══ TAB: SCORE ═══ -->
                <div class="wa-tab-content p-5 space-y-5" id="tab-score">
                    <?php if (!empty($scoreData) && isset($scoreData['interest_score'])): ?>
                        <?php $sc = (int)$scoreData['interest_score']; ?>
                        <div class="text-center">
                            <!-- Score Circle -->
                            <div class="relative w-28 h-28 mx-auto mb-3">
                                <svg class="w-28 h-28 -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="42" stroke-width="6" class="fill-none stroke-zinc-800"/>
                                    <circle cx="50" cy="50" r="42" stroke-width="6"
                                        class="fill-none score-ring <?= $sc >= 70 ? 'stroke-green-500' : ($sc >= 40 ? 'stroke-amber-500' : 'stroke-red-500') ?>"
                                        stroke-dasharray="263.9"
                                        stroke-dashoffset="<?= 263.9 * (1 - $sc / 100) ?>"
                                        stroke-linecap="round"/>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-3xl font-black <?= $sc >= 70 ? 'text-green-400' : ($sc >= 40 ? 'text-amber-400' : 'text-red-400') ?>"><?= $sc ?></span>
                                    <span class="text-[8px] text-zinc-600 uppercase font-bold">de 100</span>
                                </div>
                            </div>

                            <?php if (!empty($scoreData['trend'])): ?>
                                <?php $tr = $scoreData['trend'];
                                    $trIcon = $tr === 'Subindo' ? 'trending_up' : ($tr === 'Caindo' ? 'trending_down' : 'trending_flat');
                                    $trColor = $tr === 'Subindo' ? 'text-green-400' : ($tr === 'Caindo' ? 'text-red-400' : 'text-zinc-400'); ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold <?= $trColor ?>">
                                    <span class="material-symbols-outlined text-[14px]"><?= $trIcon ?></span> <?= $tr ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($scoreData['score_explanation'])): ?>
                        <div class="bg-zinc-800/30 rounded-xl p-3 border-l-2 border-green-500/30">
                            <p class="text-xs text-zinc-400 italic">"<?= htmlspecialchars($scoreData['score_explanation']) ?>"</p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($scoreData['breakdown'])): ?>
                        <div class="space-y-3">
                            <span class="text-[9px] text-zinc-500 uppercase font-bold">Breakdown</span>
                            <?php
                            $labels = [
                                'lead_openness' => ['Abertura', 15], 'interest_depth' => ['Profundidade', 20],
                                'solution_fit' => ['Fit', 15], 'perceived_urgency' => ['Urgência', 15],
                                'advance_signals' => ['Avanço', 20], 'resistance_signals' => ['Resistência', 15],
                                'commercial_potential' => ['Potencial', 15]
                            ];
                            foreach ($labels as $key => [$label, $maxVal]):
                                $item = $scoreData['breakdown'][$key] ?? null;
                                if (!$item) continue;
                                $val = (int)($item['score'] ?? 0);
                                $max = (int)($item['max'] ?? $maxVal);
                                $pct = $max > 0 ? min(100, abs($val) / $max * 100) : 0;
                                $isNeg = $key === 'resistance_signals';
                            ?>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-[10px] text-zinc-400"><?= $label ?></span>
                                    <span class="text-[10px] font-bold <?= $isNeg ? 'text-red-400' : 'text-zinc-200' ?>"><?= $val ?>/<?= $max ?></span>
                                </div>
                                <div class="h-1.5 bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full breakdown-bar <?= $isNeg ? 'bg-red-500' : ($pct >= 60 ? 'bg-green-500' : 'bg-amber-500') ?>"
                                         style="width: <?= $pct ?>%"></div>
                                </div>
                                <?php if (!empty($item['evidence'])): ?>
                                    <p class="text-[9px] text-zinc-600 mt-0.5 truncate" title="<?= htmlspecialchars($item['evidence']) ?>"><?= htmlspecialchars($item['evidence']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-16">
                            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-zinc-800/50 border border-zinc-700/50 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl text-zinc-600">speed</span>
                            </div>
                            <p class="text-zinc-500 text-sm mb-1">Score não calculado</p>
                            <p class="text-zinc-600 text-xs mb-6">Avalie o interesse real do lead de 0 a 100.</p>
                        </div>
                    <?php endif; ?>
                    <button onclick="Intelligence.score()" id="btn-score" class="wa-ai-btn w-full h-10 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 border border-zinc-700/50">
                        <span class="material-symbols-outlined text-[16px]">speed</span>
                        <?= empty($scoreData) ? 'Calcular Score' : 'Recalcular Score' ?>
                    </button>
                </div>

                <!-- ═══ TAB: MENSAGEM ═══ -->
                <div class="wa-tab-content p-5 space-y-5" id="tab-message">
                    <div id="msg-result" class="hidden space-y-4">
                        <div class="bg-zinc-800/30 rounded-xl p-4 border border-zinc-800/40">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[9px] text-green-400 uppercase font-bold">Mensagem Gerada</span>
                                <button onclick="Intelligence.copyMessage()" class="text-[10px] text-zinc-500 hover:text-green-400 transition-all flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">content_copy</span> Copiar
                                </button>
                            </div>
                            <div id="msg-text" class="text-sm text-zinc-200 leading-relaxed whitespace-pre-wrap"></div>
                        </div>
                        <div class="flex gap-3">
                            <div id="msg-tone" class="flex-1 bg-zinc-800/30 rounded-lg p-2.5 border border-zinc-800/40 text-center">
                                <span class="text-[8px] text-zinc-600 uppercase font-bold block">Tom</span>
                                <span class="text-[11px] font-bold text-zinc-300 msg-tone-val"></span>
                            </div>
                            <div id="msg-cta" class="flex-1 bg-zinc-800/30 rounded-lg p-2.5 border border-zinc-800/40 text-center">
                                <span class="text-[8px] text-zinc-600 uppercase font-bold block">CTA</span>
                                <span class="text-[11px] font-bold text-zinc-300 msg-cta-val"></span>
                            </div>
                        </div>
                        <div id="msg-strategy" class="bg-green-500/5 rounded-lg p-3 border border-green-500/10">
                            <span class="text-[9px] text-green-400 uppercase font-bold block mb-1">Estratégia</span>
                            <p class="text-[11px] text-zinc-400 msg-strategy-val"></p>
                        </div>
                    </div>
                    <div id="msg-empty" class="text-center py-16">
                        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-zinc-800/50 border border-zinc-700/50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl text-zinc-600">edit_note</span>
                        </div>
                        <p class="text-zinc-500 text-sm mb-1">Gere a próxima mensagem</p>
                        <p class="text-zinc-600 text-xs mb-6">IA criará uma mensagem contextual baseada na conversa.</p>
                    </div>
                    <button onclick="Intelligence.nextMessage()" id="btn-message" class="wa-ai-btn w-full h-10 bg-green-600 hover:bg-green-500 text-white text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-green-900/20">
                        <span class="material-symbols-outlined text-[16px]">smart_toy</span>
                        Gerar Mensagem
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: Buscar e Vincular Lead ═══ -->
<div id="modal-link-lead" class="hidden fixed inset-0 z-50 flex items-start justify-center pt-24 bg-black/60 backdrop-blur-sm">
    <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-zinc-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Vincular Lead do Vault</h3>
            <button onclick="LeadLinks.closeModal()" class="text-zinc-500 hover:text-white transition-all">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-5">
            <input type="text" id="lead-search-input" placeholder="Buscar por nome, empresa..."
                class="w-full bg-zinc-950 border border-zinc-700 rounded-xl px-4 py-3 text-sm text-white focus:ring-1 focus:ring-green-500/50 outline-none transition-all placeholder-zinc-600"
                oninput="LeadLinks.searchDebounced(this.value)">
            <div id="lead-search-results" class="mt-3 max-h-64 overflow-y-auto wa-sidebar-scroll space-y-1"></div>
        </div>
    </div>
</div>

<script>
const CID = '<?= $conversation['id'] ?>';
const CSRF = '<?= $csrf ?>';

// ── Scroll chat to bottom ──
document.addEventListener('DOMContentLoaded', () => {
    const chat = document.getElementById('chat-messages');
    if (chat) chat.scrollTop = chat.scrollHeight;
});

// ── Tabs ──
document.querySelectorAll('.wa-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.wa-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.wa-tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});

// ── Lead Links ──
const LeadLinks = {
    searchTimer: null,

    openModal() {
        document.getElementById('modal-link-lead').classList.remove('hidden');
        document.getElementById('lead-search-input').focus();
    },

    closeModal() {
        document.getElementById('modal-link-lead').classList.add('hidden');
        document.getElementById('lead-search-results').innerHTML = '';
        document.getElementById('lead-search-input').value = '';
    },

    searchDebounced(query) {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => this.search(query), 300);
    },

    async search(query) {
        if (query.length < 2) { document.getElementById('lead-search-results').innerHTML = ''; return; }
        try {
            const res = await fetch(`/api/leads?search=${encodeURIComponent(query)}`);
            const data = await res.json();
            const leads = Array.isArray(data) ? data : (data.leads ?? data.data ?? []);
            const container = document.getElementById('lead-search-results');
            container.innerHTML = '';
            if (leads.length === 0) {
                container.innerHTML = '<p class="text-zinc-600 text-xs text-center py-4">Nenhum lead encontrado.</p>';
                return;
            }
            leads.forEach(l => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-3 p-3 bg-zinc-800/30 hover:bg-zinc-800 rounded-xl border border-zinc-800/50 cursor-pointer transition-all';
                div.innerHTML = `
                    <div class="w-9 h-9 rounded-full bg-green-500/10 border border-green-500/20 flex items-center justify-center text-sm font-bold text-green-400">${(l.name||'?')[0]}</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-zinc-200 truncate">${l.name || 'Sem nome'}</div>
                        <div class="text-[10px] text-zinc-500">${l.segment || ''} ${l.pipeline_status ? '· ' + l.pipeline_status : ''}</div>
                    </div>
                    <span class="text-[10px] text-green-500 font-bold">Vincular</span>
                `;
                div.onclick = () => this.link(l.id);
                container.appendChild(div);
            });
        } catch (err) { console.error('Lead search error:', err); }
    },

    async link(leadId) {
        try {
            const res = await fetch(`/whatsapp/conversation/${CID}/link`, {
                method: 'POST',
                body: new URLSearchParams({ '_csrf': CSRF, 'lead_id': leadId })
            });
            const data = await res.json();
            if (data.success) window.location.reload();
            else alert(data.error || 'Erro ao vincular.');
        } catch (err) { console.error(err); }
    },

    async unlink(leadId) {
        if (!confirm('Remover este lead do vínculo?')) return;
        try {
            const res = await fetch(`/whatsapp/conversation/${CID}/unlink`, {
                method: 'POST',
                body: new URLSearchParams({ '_csrf': CSRF, 'lead_id': leadId })
            });
            const data = await res.json();
            if (data.success) window.location.reload();
        } catch (err) { console.error(err); }
    }
};

// ── Intelligence Hub ──
const Intelligence = {
    generatedMessage: '',

    async _call(endpoint, btnId) {
        const btn = document.getElementById(btnId);
        const origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Processando...';

        try {
            const res = await fetch(`/whatsapp/conversation/${CID}/${endpoint}`, {
                method: 'POST',
                body: new URLSearchParams({ '_csrf': CSRF })
            });
            const text = await res.text();
            const j = text.indexOf('{');
            const data = JSON.parse(j > 0 ? text.substring(j) : text);

            if (!data.success) {
                alert(data.error || 'Erro na operação.');
                btn.disabled = false;
                btn.innerHTML = origHTML;
                return null;
            }
            return data;
        } catch (err) {
            console.error(err);
            alert('Erro de rede.');
            btn.disabled = false;
            btn.innerHTML = origHTML;
            return null;
        }
    },

    async summary() {
        const data = await this._call('summary', 'btn-summary');
        if (data) window.location.reload();
    },

    async strategic() {
        const data = await this._call('strategic', 'btn-strategic');
        if (data) window.location.reload();
    },

    async score() {
        const data = await this._call('interest-score', 'btn-score');
        if (data) window.location.reload();
    },

    async nextMessage() {
        const data = await this._call('next-message', 'btn-message');
        if (!data) return;

        const result = data.result || {};
        this.generatedMessage = result.message || '';

        document.getElementById('msg-text').textContent = this.generatedMessage;
        document.querySelector('.msg-tone-val').textContent = result.tone || 'N/D';
        document.querySelector('.msg-cta-val').textContent = result.cta_type || 'N/D';
        document.querySelector('.msg-strategy-val').textContent = result.strategy || '';

        document.getElementById('msg-result').classList.remove('hidden');
        document.getElementById('msg-empty').classList.add('hidden');

        const btn = document.getElementById('btn-message');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">smart_toy</span> Gerar Nova Mensagem';
    },

    copyMessage() {
        if (!this.generatedMessage) return;
        navigator.clipboard.writeText(this.generatedMessage).then(() => {
            const btn = event.currentTarget;
            const orig = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">check</span> Copiado!';
            btn.classList.add('text-green-400');
            setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('text-green-400'); }, 2000);
        });
    }
};

// Backward compat (for old references)
const Analyze = { run: () => Intelligence.summary() };
const Link = { link: (id) => LeadLinks.link(id), unlink: () => LeadLinks.unlink('') };
</script>
