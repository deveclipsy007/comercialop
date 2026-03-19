<?php
$pageTitle = 'Copilot';
$pageSubtitle = 'Assistente Estratégico';
$leads = $leads ?? [];
$funnelStats = $funnelStats ?? [];
$leadInteractions = $leadInteractions ?? [];

// Build funnel data
$funnelMap = [];
foreach ($funnelStats as $fs) { $funnelMap[$fs['pipeline_status']] = (int)$fs['count']; }
$totalLeads = array_sum($funnelMap);
$stages = [
    'new' => ['label' => 'Novos', 'color' => '#60a5fa', 'accent' => 'blue'],
    'contacted' => ['label' => 'Contatados', 'color' => '#22d3ee', 'accent' => 'cyan'],
    'qualified' => ['label' => 'Qualificados', 'color' => '#fbbf24', 'accent' => 'amber'],
    'proposal' => ['label' => 'Proposta', 'color' => '#fb923c', 'accent' => 'orange'],
    'closed_won' => ['label' => 'Ganhos', 'color' => '#E1FB15', 'accent' => 'lime'],
    'closed_lost' => ['label' => 'Perdidos', 'color' => '#f87171', 'accent' => 'red'],
];

// Temperature stats
$tempStats = ['HOT' => 0, 'WARM' => 0, 'COLD' => 0];
foreach ($leads as $l) {
    $ctx = json_decode($l['human_context'] ?? '{}', true);
    $t = $ctx['temperature'] ?? 'COLD';
    if (isset($tempStats[$t])) $tempStats[$t]++;
}
?>

<style>
/* ═══ Copilot Premium Styles ═══ */
.copilot-layout { display: flex; height: calc(100vh - 72px); overflow: hidden; }
@media (min-width: 1024px) { .copilot-layout { height: calc(100vh - 88px); } }
/* Override parent main scroll for copilot full-page */
#mainContent:has(.copilot-layout) { overflow: hidden !important; }

/* Context Panel */
.ctx-panel {
    width: 320px; min-width: 320px; border-right: 1px solid #2A2A2A;
    background: #131313; display: flex; flex-direction: column;
    overflow: hidden; transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
}
.ctx-panel.collapsed { width: 0; min-width: 0; padding: 0; border: 0; overflow: hidden; }

/* Strategic Panel */
.strat-panel {
    width: 340px; min-width: 340px; border-left: 1px solid #2A2A2A;
    background: #131313; display: flex; flex-direction: column;
    overflow: hidden; transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
}
.strat-panel.collapsed { width: 0; min-width: 0; padding: 0; border: 0; overflow: hidden; }

/* Chat */
.chat-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #000000; min-width: 0; }

/* Funnel Shape */
.funnel-bar {
    height: 26px; border-radius: 6px; transition: all 0.3s ease;
    cursor: pointer; position: relative; overflow: hidden;
}
.funnel-bar:hover { filter: brightness(1.2); transform: scaleX(1.02); }
.funnel-bar::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.08) 50%, transparent 100%);
}

/* Message animations */
@keyframes msgIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
.msg-animate { animation: msgIn 0.3s cubic-bezier(0.4,0,0.2,1); }

/* Typing dots */
@keyframes typingDot { 0%,60%,100% { opacity: 0.3; } 30% { opacity: 1; } }
.typing-dot { width: 6px; height: 6px; border-radius: 50%; background: #E1FB15; }
.typing-dot:nth-child(1) { animation: typingDot 1.4s infinite 0s; }
.typing-dot:nth-child(2) { animation: typingDot 1.4s infinite 0.2s; }
.typing-dot:nth-child(3) { animation: typingDot 1.4s infinite 0.4s; }

/* Slash command dropdown */
.cmd-dropdown {
    position: absolute; bottom: 100%; left: 0; right: 0;
    background: #1A1A1A; border: 1px solid #2A2A2A;
    border-radius: 12px; margin-bottom: 8px; overflow: hidden;
    box-shadow: 0 -8px 32px rgba(0,0,0,0.4);
    max-height: 320px; overflow-y: auto;
    z-index: 50;
}
.cmd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; cursor: pointer; transition: all 0.15s;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.cmd-item:last-child { border-bottom: 0; }
.cmd-item:hover, .cmd-item.active { background: rgba(225,251,21,0.06); }
.cmd-item:hover .cmd-label, .cmd-item.active .cmd-label { color: #E1FB15; }

/* AI Response formatting */
.ai-msg h3 { font-size: 14px; font-weight: 700; color: #E1FB15; margin: 12px 0 6px 0; }
.ai-msg h3:first-child { margin-top: 0; }
.ai-msg ul, .ai-msg ol { padding-left: 18px; margin: 6px 0; }
.ai-msg li { margin: 3px 0; line-height: 1.5; }
.ai-msg p { margin: 6px 0; line-height: 1.6; }
.ai-msg p:first-child { margin-top: 0; }
.ai-msg code { background: #131313; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
.ai-msg pre { background: #131313; padding: 12px; border-radius: 8px; overflow-x: auto; margin: 8px 0; }
.ai-msg pre code { background: none; padding: 0; }
.ai-msg strong, .ai-msg b { color: #E1FB15; }
.ai-msg blockquote { border-left: 3px solid #E1FB15; padding-left: 12px; margin: 8px 0; color: #A1A1AA; }

/* Copy draft card */
.copy-draft-card {
    margin: 12px 0 4px;
    border: 1px solid rgba(225,251,21,0.18);
    background: linear-gradient(180deg, rgba(225,251,21,0.05) 0%, rgba(255,255,255,0.02) 100%);
    border-radius: 16px;
    padding: 12px;
}
.copy-draft-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.copy-draft-title { font-size: 11px; font-weight: 700; color: #E1FB15; letter-spacing: 0.08em; text-transform: uppercase; }
.copy-draft-subtitle { font-size: 10px; color: #71717A; margin-top: 2px; }
.copy-draft-input {
    width: 100%;
    min-height: 180px;
    max-height: 420px;
    resize: vertical;
    border-radius: 12px;
    border: 1px solid #2A2A2A;
    background: rgba(0,0,0,0.34);
    color: #F4F4F5;
    padding: 14px;
    line-height: 1.65;
    font-size: 13px;
    outline: none;
}
.copy-draft-input:focus {
    border-color: rgba(225,251,21,0.35);
    box-shadow: 0 0 0 3px rgba(225,251,21,0.08);
}
.copy-draft-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
}
.copy-draft-btn {
    height: 30px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid #2A2A2A;
    background: #131313;
    color: #D4D4D8;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s ease;
}
.copy-draft-btn:hover { border-color: rgba(225,251,21,0.25); color: #E1FB15; }
.copy-draft-btn.-primary {
    background: rgba(225,251,21,0.08);
    border-color: rgba(225,251,21,0.2);
    color: #E1FB15;
}

/* Drag & Drop overlay */
.drop-overlay {
    position: absolute; inset: 0; background: rgba(0,0,0,0.8);
    border: 2px dashed #E1FB15; border-radius: 16px;
    display: none; align-items: center; justify-content: center;
    z-index: 100; backdrop-filter: blur(4px);
}
.drop-overlay.active { display: flex; }

/* Lead node in relationship map */
.lead-node {
    transition: all 0.2s ease; cursor: pointer;
}
.lead-node:hover { transform: scale(1.15); filter: brightness(1.3); }

/* Scrollbar */
.copilot-scroll::-webkit-scrollbar { width: 4px; }
.copilot-scroll::-webkit-scrollbar-track { background: transparent; }
.copilot-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.copilot-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

/* Glow effect */
.glow-lime { box-shadow: 0 0 20px rgba(225,251,21,0.15); }

/* Responsive */
@media (max-width: 1200px) {
    .strat-panel.auto-hide { display: none; }
}
@media (max-width: 900px) {
    .ctx-panel { position: absolute; left: 0; top: 0; bottom: 0; z-index: 40; box-shadow: 8px 0 32px rgba(0,0,0,0.5); }
    .ctx-panel.collapsed { transform: translateX(-100%); }
}

/* Pulse ring */
@keyframes pulseRing {
    0% { box-shadow: 0 0 0 0 rgba(225,251,21,0.3); }
    70% { box-shadow: 0 0 0 8px rgba(225,251,21,0); }
    100% { box-shadow: 0 0 0 0 rgba(225,251,21,0); }
}
.pulse-ring { animation: pulseRing 2s infinite; }

/* Filter chip active */
.filter-active { background: rgba(225,251,21,0.1) !important; border-color: rgba(225,251,21,0.3) !important; color: #E1FB15 !important; }
</style>

<div class="copilot-layout">

    <!-- ═══════════════════════════════════════════════ -->
    <!-- LEFT: Context Panel                            -->
    <!-- ═══════════════════════════════════════════════ -->
    <aside id="ctxPanel" class="ctx-panel copilot-scroll">

        <!-- Panel Header -->
        <div class="px-4 py-3 border-b border-stroke flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lime text-[16px]">target</span>
                <span class="text-[10px] font-bold text-muted uppercase tracking-[0.15em]">Contexto</span>
            </div>
            <button onclick="Copilot.togglePanel('ctx')" class="size-6 rounded-md bg-surface2 text-muted hover:text-text flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[14px]">chevron_left</span>
            </button>
        </div>

        <!-- Lead Selector -->
        <div class="p-4 border-b border-stroke">
            <label class="block text-[9px] font-bold text-muted uppercase tracking-[0.15em] mb-2">Lead ativo</label>
            <div class="relative">
                <input type="text" id="leadSearchInput" placeholder="Buscar lead por nome ou segmento..."
                       class="w-full h-10 bg-surface2 border border-stroke rounded-lg pl-9 pr-3 text-xs text-text placeholder:text-subtle focus:outline-none focus:border-lime/40 focus:bg-surface2/80 transition-all"
                       autocomplete="off">
                <span class="material-symbols-outlined text-[15px] text-muted absolute left-2.5 top-[11px]">person_search</span>
            </div>
            <div id="leadDropdown" class="hidden mt-1 max-h-52 overflow-y-auto bg-surface2 border border-stroke rounded-lg shadow-xl copilot-scroll z-30 relative"></div>

            <!-- Selected lead card -->
            <div id="selectedLeadCard" class="hidden mt-3 p-3.5 bg-gradient-to-br from-lime/5 to-transparent border border-lime/20 rounded-xl glow-lime transition-all">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="size-7 rounded-full bg-lime/15 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lime text-[14px]">person</span>
                        </div>
                        <p id="selectedLeadName" class="text-sm font-bold text-lime truncate"></p>
                    </div>
                    <button onclick="Copilot.clearLead()" class="size-5 rounded-full bg-surface2 text-muted hover:text-red-400 flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-[11px]">close</span>
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-1.5 text-[10px]">
                    <div class="flex items-center gap-1.5 text-muted"><span class="material-symbols-outlined text-[11px]">category</span><span id="selectedLeadSegment"></span></div>
                    <div class="flex items-center gap-1.5 text-muted"><span class="material-symbols-outlined text-[11px]">conversion_path</span><span id="selectedLeadStage"></span></div>
                    <div class="flex items-center gap-1.5 text-muted"><span class="material-symbols-outlined text-[11px]">phone</span><span id="selectedLeadPhone"></span></div>
                    <div class="flex items-center gap-1.5 text-muted"><span class="material-symbols-outlined text-[11px]">mail</span><span id="selectedLeadEmail" class="truncate"></span></div>
                    <div class="flex items-center gap-1.5 text-muted"><span class="material-symbols-outlined text-[11px]">thermostat</span><span id="selectedLeadTemp"></span></div>
                    <div class="flex items-center gap-1.5 text-muted"><span class="material-symbols-outlined text-[11px]">score</span>Score: <span id="selectedLeadScore" class="text-lime font-bold"></span></div>
                </div>
            </div>
        </div>

        <!-- Response Filters -->
        <div class="p-4 border-b border-stroke">
            <label class="block text-[9px] font-bold text-muted uppercase tracking-[0.15em] mb-2.5">Foco da resposta</label>
            <div class="grid grid-cols-2 gap-1.5" id="filterChips">
                <?php
                $filters = [
                    'closing'    => ['icon' => 'handshake',   'label' => 'Fechamento',   'desc' => 'Estrategias de close'],
                    'objections' => ['icon' => 'shield',      'label' => 'Objecoes',     'desc' => 'Contornar objecoes'],
                    'followup'   => ['icon' => 'replay',      'label' => 'Follow-up',    'desc' => 'Reengajamento'],
                    'diagnosis'  => ['icon' => 'stethoscope', 'label' => 'Diagnostico',  'desc' => 'Analise de cenario'],
                    'potential'  => ['icon' => 'trending_up', 'label' => 'Potencial',    'desc' => 'Fit e prioridade'],
                    'whatsapp'   => ['icon' => 'chat',        'label' => 'WhatsApp',     'desc' => 'Msg pronta'],
                    'strategic'  => ['icon' => 'strategy',    'label' => 'Estrategia',   'desc' => 'Visao de alto nivel'],
                    'summary'    => ['icon' => 'summarize',   'label' => 'Resumo',       'desc' => 'Bullet points'],
                ];
                foreach ($filters as $key => $f): ?>
                <button type="button" data-filter="<?= $key ?>" onclick="Copilot.toggleFilter('<?= $key ?>')"
                        class="filter-chip h-8 px-2.5 bg-surface2 border border-stroke rounded-lg text-[10px] font-medium text-muted hover:text-text hover:border-white/20 transition-all flex items-center gap-1.5"
                        title="<?= $f['desc'] ?>">
                    <span class="material-symbols-outlined text-[13px]"><?= $f['icon'] ?></span>
                    <?= $f['label'] ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Leads -->
        <div class="flex-1 overflow-y-auto p-4 copilot-scroll">
            <label class="block text-[9px] font-bold text-muted uppercase tracking-[0.15em] mb-2">Leads recentes</label>
            <div class="space-y-0.5" id="recentLeadsList">
                <?php foreach (array_slice($leads, 0, 20) as $l):
                    $ctx = json_decode($l['human_context'] ?? '{}', true);
                    $temp = $ctx['temperature'] ?? 'COLD';
                    $dotColor = ['HOT'=>'bg-red-500','WARM'=>'bg-amber-500','COLD'=>'bg-blue-400'][$temp] ?? 'bg-muted';
                    $stLabel = $stages[$l['pipeline_status']]['label'] ?? $l['pipeline_status'];
                ?>
                <button onclick='Copilot.selectLead(<?= htmlspecialchars(json_encode($l), ENT_QUOTES) ?>)'
                        class="w-full flex items-center gap-2 px-2.5 py-2 rounded-lg text-left hover:bg-surface2 transition-colors group">
                    <span class="size-2 rounded-full <?= $dotColor ?> shrink-0"></span>
                    <span class="text-xs text-muted group-hover:text-text truncate flex-1"><?= htmlspecialchars($l['name']) ?></span>
                    <span class="text-[9px] text-subtle opacity-0 group-hover:opacity-100 transition-opacity"><?= $stLabel ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- CENTER: Chat                                   -->
    <!-- ═══════════════════════════════════════════════ -->
    <main class="chat-main" id="chatMain">

        <!-- Chat Header -->
        <div class="flex items-center justify-between px-5 py-2.5 border-b border-stroke flex-shrink-0">
            <div class="flex items-center gap-3">
                <!-- Toggle context panel (mobile) -->
                <button onclick="Copilot.togglePanel('ctx')" id="btnToggleCtx" class="size-8 rounded-lg bg-surface2 border border-stroke text-muted hover:text-text flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[16px]">left_panel_open</span>
                </button>
                <div class="size-10 bg-lime/10 rounded-full flex items-center justify-center border border-lime/20 pulse-ring">
                    <span class="material-symbols-outlined text-lime text-[20px]">smart_toy</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-text flex items-center gap-1.5">
                        Operon Intelligence
                        <span class="size-1.5 bg-lime rounded-full animate-pulse"></span>
                    </p>
                    <p id="chatStatus" class="text-[10px] text-muted transition-all">Pronto para ajudar</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <!-- Active context badges -->
                <span id="activeFilterBadge" class="hidden h-6 px-2.5 bg-lime/10 border border-lime/20 rounded-full text-[10px] font-bold text-lime items-center gap-1">
                    <span class="material-symbols-outlined text-[11px]" id="activeFilterIcon"></span>
                    <span id="activeFilterLabel"></span>
                </span>
                <span id="activeLeadBadge" class="hidden h-6 px-2.5 bg-surface2 border border-stroke rounded-full text-[10px] font-bold text-muted items-center gap-1">
                    <span class="material-symbols-outlined text-[11px]">person</span>
                    <span id="activeLeadBadgeName"></span>
                </span>
                <!-- Toggle strategic panel -->
                <button onclick="Copilot.togglePanel('strat')" class="size-8 rounded-lg bg-surface2 border border-stroke text-muted hover:text-text flex items-center justify-center transition-colors" title="Painel estrategico">
                    <span class="material-symbols-outlined text-[16px]">right_panel_open</span>
                </button>
                <button onclick="Copilot.clearChat()" class="size-8 rounded-lg bg-surface2 border border-stroke text-muted hover:text-text flex items-center justify-center transition-colors" title="Limpar conversa">
                    <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
                </button>
            </div>
        </div>

        <!-- Messages -->
        <div id="chatMessages" class="flex-1 overflow-y-auto px-5 py-5 space-y-4 copilot-scroll relative" ondragover="Copilot.dragOver(event)" ondragleave="Copilot.dragLeave(event)" ondrop="Copilot.drop(event)">

            <!-- Drag & Drop overlay -->
            <div id="dropOverlay" class="drop-overlay">
                <div class="text-center">
                    <span class="material-symbols-outlined text-lime text-[48px] mb-2">upload_file</span>
                    <p class="text-lime font-bold text-sm">Solte o arquivo aqui</p>
                    <p class="text-muted text-xs mt-1">PDF, DOC, TXT, CSV, audio ou imagem</p>
                </div>
            </div>

            <!-- Welcome Message -->
            <div class="flex gap-3 msg-animate">
                <div class="size-8 bg-lime/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-sm">smart_toy</span>
                </div>
                <div class="max-w-2xl">
                    <div class="bg-surface2 border border-stroke rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-text">
                        <p class="mb-2">Sou o <b class="text-lime">Operon Intelligence</b>, seu assistente estrategico de vendas B2B.</p>
                        <div class="grid grid-cols-2 gap-2 mt-3">
                            <div class="flex items-center gap-2 text-[11px] text-muted">
                                <span class="material-symbols-outlined text-[13px] text-lime/60">person_search</span>
                                Selecione um lead no painel
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-muted">
                                <span class="material-symbols-outlined text-[13px] text-lime/60">filter_alt</span>
                                Escolha um foco de resposta
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-muted">
                                <span class="material-symbols-outlined text-[13px] text-lime/60">slash_forward</span>
                                Use <code class="text-lime/80 bg-surface px-1 rounded text-[10px]">/</code> para comandos
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-muted">
                                <span class="material-symbols-outlined text-[13px] text-lime/60">attach_file</span>
                                Arraste arquivos para o chat
                            </div>
                        </div>
                    </div>
                    <p class="text-[9px] text-subtle mt-1 ml-1">agora</p>
                </div>
            </div>
        </div>

        <!-- Quick Commands (scrollable pills) -->
        <div id="quickCommands" class="px-5 pb-2 flex-shrink-0">
            <div class="flex gap-1.5 overflow-x-auto pb-1 copilot-scroll" style="-ms-overflow-style:none;scrollbar-width:none;">
                <?php
                $commands = [
                    ['icon' => 'summarize',   'text' => 'Resuma esse lead',             'slash' => '/resumo'],
                    ['icon' => 'chat',        'text' => 'Gere mensagem de WhatsApp',    'slash' => '/whatsapp'],
                    ['icon' => 'replay',      'text' => 'Sugira proximo follow-up',     'slash' => '/followup'],
                    ['icon' => 'shield',      'text' => 'Liste objecoes provaveis',     'slash' => '/objecoes'],
                    ['icon' => 'handshake',   'text' => 'Monte abordagem inicial',      'slash' => '/abordagem'],
                    ['icon' => 'trending_up', 'text' => 'Analise potencial comercial',  'slash' => '/potencial'],
                    ['icon' => 'route',       'text' => 'Me diga o proximo passo',      'slash' => '/proximo'],
                    ['icon' => 'compare',     'text' => 'Compare com outros leads',     'slash' => '/comparar'],
                    ['icon' => 'star',        'text' => 'Gere score de interesse',      'slash' => '/score'],
                ];
                foreach ($commands as $cmd): ?>
                <button onclick="Copilot.sendMessage('<?= $cmd['text'] ?>')"
                        class="flex-shrink-0 h-7 px-2.5 bg-surface2/80 border border-stroke/80 rounded-full text-[10px] text-muted hover:text-lime hover:border-lime/30 transition-all flex items-center gap-1 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[12px]"><?= $cmd['icon'] ?></span>
                    <?= $cmd['text'] ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Input Area -->
        <div class="px-5 py-3 border-t border-stroke flex-shrink-0 relative">
            <!-- Slash command dropdown -->
            <div id="cmdDropdown" class="cmd-dropdown hidden"></div>

            <!-- File preview -->
            <div id="filePreview" class="hidden mb-2 flex items-center gap-2 px-3 py-2 bg-surface2 border border-lime/20 rounded-lg">
                <span class="material-symbols-outlined text-lime text-[16px]" id="filePreviewIcon">description</span>
                <span id="filePreviewName" class="text-xs text-text truncate flex-1"></span>
                <span id="filePreviewSize" class="text-[10px] text-muted"></span>
                <button type="button" onclick="Copilot.clearFile()" class="size-5 rounded-full bg-surface text-muted hover:text-red-400 flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[12px]">close</span>
                </button>
            </div>

            <form id="chatForm" class="flex gap-2 items-end">
                <div class="flex-1 relative">
                    <textarea id="chatInput" placeholder="Formule seu comando ou digite / para atalhos..." rows="1"
                              class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-3 pr-20 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/40 focus:shadow-[0_0_0_3px_rgba(225,251,21,0.08)] transition-all resize-none"
                              style="min-height:46px;max-height:140px"></textarea>
                    <div class="absolute right-2 bottom-2 flex items-center gap-1">
                        <!-- Audio record -->
                        <button type="button" onclick="Copilot.toggleRecording()" id="recordBtn"
                                class="size-8 rounded-lg bg-surface border border-stroke text-muted hover:text-lime hover:border-lime/30 flex items-center justify-center transition-colors" title="Gravar audio">
                            <span class="material-symbols-outlined text-[16px]" id="recordIcon">mic</span>
                        </button>
                        <!-- File upload -->
                        <label class="size-8 rounded-lg bg-surface border border-stroke text-muted hover:text-lime hover:border-lime/30 flex items-center justify-center cursor-pointer transition-colors" title="Anexar arquivo">
                            <span class="material-symbols-outlined text-[16px]">attach_file</span>
                            <input type="file" id="fileInput" class="hidden" accept=".pdf,.doc,.docx,.txt,.csv,.xlsx,.mp3,.wav,.ogg,.m4a,.png,.jpg,.jpeg">
                        </label>
                    </div>
                </div>
                <button type="submit" id="sendBtn" class="size-11 bg-lime rounded-xl flex items-center justify-center hover:brightness-110 shadow-[0_0_20px_rgba(225,251,21,0.2)] hover:shadow-[0_0_28px_rgba(225,251,21,0.3)] transition-all flex-shrink-0 active:scale-95">
                    <span class="material-symbols-outlined text-bg text-[20px]">arrow_upward</span>
                </button>
            </form>

            <div class="flex items-center justify-between mt-1.5">
                <p class="text-[9px] text-subtle">Shift+Enter para nova linha &middot; / para comandos &middot; arraste arquivos</p>
                <p id="charCount" class="text-[9px] text-subtle hidden"><span id="charNum">0</span>/4000</p>
            </div>
        </div>
    </main>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- RIGHT: Strategic Panel                         -->
    <!-- ═══════════════════════════════════════════════ -->
    <aside id="stratPanel" class="strat-panel copilot-scroll">

        <!-- Panel Header -->
        <div class="px-4 py-3 border-b border-stroke flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lime text-[16px]">analytics</span>
                <span class="text-[10px] font-bold text-muted uppercase tracking-[0.15em]">Visao Estrategica</span>
            </div>
            <button onclick="Copilot.togglePanel('strat')" class="size-6 rounded-md bg-surface2 text-muted hover:text-text flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            </button>
        </div>

        <!-- Temperature Overview -->
        <div class="p-4 border-b border-stroke">
            <label class="block text-[9px] font-bold text-muted uppercase tracking-[0.15em] mb-3">Temperatura dos Leads</label>
            <div class="flex gap-2">
                <div class="flex-1 bg-surface2 rounded-lg p-2.5 text-center border border-stroke hover:border-red-500/30 transition-colors cursor-pointer" onclick="Copilot.sendMessage('Analise meus leads HOT e sugira acao imediata')">
                    <p class="text-lg font-black text-red-400"><?= $tempStats['HOT'] ?></p>
                    <p class="text-[9px] text-muted uppercase tracking-wider">Hot</p>
                </div>
                <div class="flex-1 bg-surface2 rounded-lg p-2.5 text-center border border-stroke hover:border-amber-500/30 transition-colors cursor-pointer" onclick="Copilot.sendMessage('Analise meus leads WARM e sugira como esquentar')">
                    <p class="text-lg font-black text-amber-400"><?= $tempStats['WARM'] ?></p>
                    <p class="text-[9px] text-muted uppercase tracking-wider">Warm</p>
                </div>
                <div class="flex-1 bg-surface2 rounded-lg p-2.5 text-center border border-stroke hover:border-blue-500/30 transition-colors cursor-pointer" onclick="Copilot.sendMessage('Analise meus leads COLD e sugira estrategia de reativacao')">
                    <p class="text-lg font-black text-blue-400"><?= $tempStats['COLD'] ?></p>
                    <p class="text-[9px] text-muted uppercase tracking-wider">Cold</p>
                </div>
            </div>
        </div>

        <!-- Funnel Visualization -->
        <div class="p-4 border-b border-stroke">
            <div class="flex items-center justify-between mb-3">
                <label class="text-[9px] font-bold text-muted uppercase tracking-[0.15em]">Funil Comercial</label>
                <span class="text-[10px] text-lime font-bold"><?= $totalLeads ?> leads</span>
            </div>
            <div class="space-y-1.5">
                <?php
                $funnelWidth = 100;
                foreach ($stages as $status => $st):
                    $count = $funnelMap[$status] ?? 0;
                    if ($status === 'closed_lost' && $count === 0) continue;
                ?>
                <div onclick="Copilot.sendMessage('Analise os <?= $count ?> leads no estagio <?= $st['label'] ?> e sugira acoes')" class="group">
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="text-[10px] text-muted group-hover:text-text transition-colors"><?= $st['label'] ?></span>
                        <span class="text-[10px] font-bold text-muted"><?= $count ?></span>
                    </div>
                    <div class="funnel-bar" style="width:<?= $funnelWidth ?>%;background:<?= $st['color'] ?>;opacity:0.8;margin:0 auto;">
                        <?php if ($count > 0): ?>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-[10px] font-bold text-bg/80"><?= $totalLeads > 0 ? round(($count/$totalLeads)*100) : 0 ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                    $funnelWidth = max($funnelWidth - 12, 30);
                endforeach; ?>
            </div>

            <?php if ($totalLeads > 0 && ($funnelMap['closed_won'] ?? 0) > 0): ?>
            <div class="mt-3 pt-3 border-t border-stroke/50">
                <div class="flex items-center justify-between text-[10px]">
                    <span class="text-muted">Taxa de conversao</span>
                    <span class="text-lime font-bold"><?= round((($funnelMap['closed_won'] ?? 0) / $totalLeads) * 100, 1) ?>%</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lead Relationship Map -->
        <div class="p-4 border-b border-stroke">
            <div class="flex items-center justify-between mb-3">
                <label class="text-[9px] font-bold text-muted uppercase tracking-[0.15em]">Mapa de Leads</label>
                <button onclick="Copilot.sendMessage('Faca uma analise comparativa dos meus leads por segmento e prioridade')" class="text-[9px] text-lime hover:underline">Analisar</button>
            </div>
            <div class="relative bg-surface2 rounded-xl border border-stroke p-3 min-h-[180px] overflow-hidden" id="leadMap">
                <!-- Generated via JS -->
                <canvas id="leadMapCanvas" width="280" height="170" class="w-full h-full"></canvas>
            </div>
            <div class="flex items-center justify-center gap-4 mt-2 text-[9px] text-subtle">
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-red-500"></span>Hot</span>
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-amber-500"></span>Warm</span>
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-blue-400"></span>Cold</span>
            </div>
        </div>

        <!-- Segment Distribution -->
        <div class="p-4 flex-1 overflow-y-auto copilot-scroll">
            <label class="block text-[9px] font-bold text-muted uppercase tracking-[0.15em] mb-2">Por Segmento</label>
            <?php
            $segmentCounts = [];
            foreach ($leads as $l) {
                $seg = $l['segment'] ?: 'Sem segmento';
                $segmentCounts[$seg] = ($segmentCounts[$seg] ?? 0) + 1;
            }
            arsort($segmentCounts);
            foreach (array_slice($segmentCounts, 0, 8, true) as $seg => $cnt):
                $segPct = $totalLeads > 0 ? ($cnt / $totalLeads) * 100 : 0;
            ?>
            <div class="mb-2 cursor-pointer group" onclick="Copilot.sendMessage('Analise os leads do segmento <?= htmlspecialchars($seg) ?>')">
                <div class="flex items-center justify-between mb-0.5">
                    <span class="text-[10px] text-muted group-hover:text-text truncate transition-colors"><?= htmlspecialchars($seg) ?></span>
                    <span class="text-[10px] text-subtle"><?= $cnt ?></span>
                </div>
                <div class="h-1.5 bg-surface rounded-full overflow-hidden">
                    <div class="h-full bg-lime/40 rounded-full transition-all group-hover:bg-lime/60" style="width:<?= max($segPct, 3) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </aside>
</div>

<script>
const Copilot = {
    selectedLead: null,
    activeFilter: null,
    history: [],
    attachedFile: null,
    isRecording: false,
    mediaRecorder: null,
    audioChunks: [],
    cmdIndex: -1,
    allLeads: <?= json_encode(array_map(fn($l) => [
        'id' => $l['id'], 'name' => $l['name'], 'segment' => $l['segment'] ?? '',
        'phone' => $l['phone'] ?? '', 'email' => $l['email'] ?? '',
        'pipeline_status' => $l['pipeline_status'], 'priority_score' => $l['priority_score'] ?? 0,
        'human_context' => $l['human_context'] ?? '{}'
    ], $leads)) ?>,

    stageLabels: {new:'Novo',contacted:'Contatado',qualified:'Qualificado',proposal:'Proposta',closed_won:'Ganho',closed_lost:'Perdido'},

    // Slash commands
    slashCommands: [
        { cmd: '/resumo',     icon: 'summarize',   label: 'Resumir lead',              action: 'Resuma esse lead com bullet points praticos' },
        { cmd: '/whatsapp',   icon: 'chat',        label: 'Mensagem WhatsApp',         action: 'Gere uma mensagem profissional de WhatsApp para esse lead' },
        { cmd: '/followup',   icon: 'replay',      label: 'Estrategia de follow-up',   action: 'Sugira a melhor estrategia de follow-up para esse lead' },
        { cmd: '/objecoes',   icon: 'shield',      label: 'Listar objecoes',           action: 'Liste as objecoes provaveis desse lead e como contorna-las' },
        { cmd: '/abordagem',  icon: 'handshake',   label: 'Abordagem inicial',         action: 'Monte uma abordagem inicial estrategica para esse lead' },
        { cmd: '/potencial',  icon: 'trending_up', label: 'Potencial comercial',       action: 'Analise o potencial comercial desse lead' },
        { cmd: '/proximo',    icon: 'route',       label: 'Proximo passo',             action: 'Me diga o proximo passo mais inteligente para esse lead' },
        { cmd: '/comparar',   icon: 'compare',     label: 'Comparar leads',            action: 'Compare esse lead com outros leads semelhantes' },
        { cmd: '/score',      icon: 'star',        label: 'Score de interesse',        action: 'Gere um score de interesse detalhado para esse lead' },
        { cmd: '/email',      icon: 'mail',        label: 'Email comercial',           action: 'Gere um email comercial profissional para esse lead' },
        { cmd: '/diagnostico',icon: 'stethoscope', label: 'Diagnostico completo',      action: 'Faca um diagnostico comercial completo desse lead' },
        { cmd: '/pitch',      icon: 'campaign',    label: 'Pitch de vendas',           action: 'Monte um pitch de vendas personalizado para esse lead' },
        { cmd: '/timeline',   icon: 'timeline',    label: 'Timeline de acoes',         action: 'Crie uma timeline de acoes para fechar esse lead' },
        { cmd: '/funil',      icon: 'filter_alt',  label: 'Analise do funil',          action: 'Analise meu funil comercial e sugira otimizacoes' },
    ],

    init() {
        const form = document.getElementById('chatForm');
        const input = document.getElementById('chatInput');
        const searchInput = document.getElementById('leadSearchInput');

        form.addEventListener('submit', (e) => { e.preventDefault(); this.send(); });

        // Auto-resize + char count
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 140) + 'px';
            this.handleSlashCmd(input.value);
            const len = input.value.length;
            const cc = document.getElementById('charCount');
            if (len > 100) { cc.classList.remove('hidden'); document.getElementById('charNum').textContent = len; }
            else { cc.classList.add('hidden'); }
        });

        // Keyboard
        input.addEventListener('keydown', (e) => {
            const dropdown = document.getElementById('cmdDropdown');
            if (!dropdown.classList.contains('hidden')) {
                const items = dropdown.querySelectorAll('.cmd-item');
                if (e.key === 'ArrowDown') { e.preventDefault(); this.cmdIndex = Math.min(this.cmdIndex + 1, items.length - 1); this.highlightCmd(items); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); this.cmdIndex = Math.max(this.cmdIndex - 1, 0); this.highlightCmd(items); }
                else if (e.key === 'Enter' && this.cmdIndex >= 0) { e.preventDefault(); items[this.cmdIndex]?.click(); }
                else if (e.key === 'Escape') { dropdown.classList.add('hidden'); this.cmdIndex = -1; }
                return;
            }
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.send(); }
        });

        // Lead search
        searchInput.addEventListener('input', () => this.filterLeads(searchInput.value));
        searchInput.addEventListener('focus', () => { if (searchInput.value.length === 0) this.filterLeads(''); });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#leadSearchInput') && !e.target.closest('#leadDropdown'))
                document.getElementById('leadDropdown').classList.add('hidden');
            if (!e.target.closest('#chatInput') && !e.target.closest('#cmdDropdown'))
                document.getElementById('cmdDropdown').classList.add('hidden');
        });

        // File upload
        document.getElementById('fileInput').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) this.attachFile(file);
        });

        // Draw lead map
        this.drawLeadMap();
    },

    // ── Slash Commands ──
    handleSlashCmd(value) {
        const dropdown = document.getElementById('cmdDropdown');
        if (!value.startsWith('/')) { dropdown.classList.add('hidden'); this.cmdIndex = -1; return; }

        const q = value.toLowerCase();
        const matches = this.slashCommands.filter(c => c.cmd.startsWith(q) || c.label.toLowerCase().includes(q.slice(1)));

        if (matches.length === 0) { dropdown.classList.add('hidden'); return; }

        dropdown.innerHTML = matches.map((c, i) => `
            <div class="cmd-item ${i === 0 ? 'active' : ''}" onclick="Copilot.executeCmd('${c.cmd}')">
                <span class="material-symbols-outlined text-[16px] text-lime/60">${c.icon}</span>
                <div class="flex-1 min-w-0">
                    <span class="cmd-label text-xs font-medium text-text block">${c.cmd}</span>
                    <span class="text-[10px] text-subtle">${c.label}</span>
                </div>
            </div>
        `).join('');
        dropdown.classList.remove('hidden');
        this.cmdIndex = 0;
    },

    highlightCmd(items) {
        items.forEach((el, i) => el.classList.toggle('active', i === this.cmdIndex));
        items[this.cmdIndex]?.scrollIntoView({ block: 'nearest' });
    },

    executeCmd(cmd) {
        const c = this.slashCommands.find(x => x.cmd === cmd);
        if (!c) return;
        document.getElementById('chatInput').value = '';
        document.getElementById('cmdDropdown').classList.add('hidden');
        this.cmdIndex = -1;
        this.sendMessage(c.action);
    },

    // ── Panel Toggle ──
    togglePanel(panel) {
        if (panel === 'ctx') document.getElementById('ctxPanel').classList.toggle('collapsed');
        if (panel === 'strat') document.getElementById('stratPanel').classList.toggle('collapsed');
    },

    // ── Lead Management ──
    filterLeads(query) {
        const dropdown = document.getElementById('leadDropdown');
        const q = query.toLowerCase().trim();
        const filtered = q
            ? this.allLeads.filter(l => l.name.toLowerCase().includes(q) || (l.segment||'').toLowerCase().includes(q)).slice(0, 12)
            : this.allLeads.slice(0, 12);

        if (filtered.length === 0) { dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-subtle">Nenhum lead encontrado</div>'; dropdown.classList.remove('hidden'); return; }

        dropdown.innerHTML = filtered.map(l => {
            const ctx = JSON.parse(l.human_context || '{}');
            const temp = ctx.temperature || 'COLD';
            const dot = {HOT:'bg-red-500',WARM:'bg-amber-500',COLD:'bg-blue-400'}[temp] || 'bg-muted';
            return `<button type="button" onclick='Copilot.selectLead(${JSON.stringify(l).replace(/'/g,"&#39;")})'
                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left hover:bg-lime/5 text-xs transition-colors border-b border-stroke/30 last:border-0">
                <span class="size-2 rounded-full ${dot} shrink-0"></span>
                <span class="text-text font-medium truncate flex-1">${this.esc(l.name)}</span>
                <span class="text-[9px] text-subtle">${this.esc(l.segment||'')}</span>
                <span class="text-[9px] px-1.5 py-0.5 rounded bg-surface text-subtle">${this.stageLabels[l.pipeline_status]||l.pipeline_status}</span>
            </button>`;
        }).join('');
        dropdown.classList.remove('hidden');
    },

    selectLead(lead) {
        this.selectedLead = lead;
        document.getElementById('leadDropdown').classList.add('hidden');
        document.getElementById('leadSearchInput').value = '';

        const ctx = typeof lead.human_context === 'string' ? JSON.parse(lead.human_context || '{}') : (lead.human_context || {});
        const temp = ctx.temperature || 'COLD';

        document.getElementById('selectedLeadCard').classList.remove('hidden');
        document.getElementById('selectedLeadName').textContent = lead.name;
        document.getElementById('selectedLeadSegment').textContent = lead.segment || 'Sem segmento';
        document.getElementById('selectedLeadStage').textContent = this.stageLabels[lead.pipeline_status] || lead.pipeline_status;
        document.getElementById('selectedLeadPhone').textContent = lead.phone || 'Sem telefone';
        document.getElementById('selectedLeadEmail').textContent = lead.email || 'Sem email';
        document.getElementById('selectedLeadTemp').textContent = temp;
        document.getElementById('selectedLeadScore').textContent = (lead.priority_score || 0) + '/100';

        // Header badges
        const badge = document.getElementById('activeLeadBadge');
        badge.classList.remove('hidden'); badge.classList.add('flex');
        document.getElementById('activeLeadBadgeName').textContent = lead.name.length > 18 ? lead.name.substring(0, 18) + '...' : lead.name;

        document.getElementById('chatStatus').textContent = 'Contexto: ' + lead.name;

        // Highlight in map
        this.drawLeadMap(lead.id);
    },

    clearLead() {
        this.selectedLead = null;
        document.getElementById('selectedLeadCard').classList.add('hidden');
        const badge = document.getElementById('activeLeadBadge');
        badge.classList.add('hidden'); badge.classList.remove('flex');
        document.getElementById('chatStatus').textContent = 'Pronto para ajudar';
        this.drawLeadMap();
    },

    // ── Filters ──
    toggleFilter(key) {
        const chips = document.querySelectorAll('.filter-chip');
        const badge = document.getElementById('activeFilterBadge');

        if (this.activeFilter === key) {
            this.activeFilter = null;
            chips.forEach(c => c.classList.remove('filter-active'));
            badge.classList.add('hidden'); badge.classList.remove('flex');
        } else {
            this.activeFilter = key;
            chips.forEach(c => c.classList.toggle('filter-active', c.dataset.filter === key));
            const activeChip = document.querySelector(`[data-filter="${key}"]`);
            const icon = activeChip?.querySelector('.material-symbols-outlined')?.textContent || '';
            document.getElementById('activeFilterIcon').textContent = icon;
            document.getElementById('activeFilterLabel').textContent = activeChip?.textContent?.trim() || key;
            badge.classList.remove('hidden'); badge.classList.add('flex');
        }
    },

    // ── File Handling ──
    attachFile(file) {
        if (file.size > 10 * 1024 * 1024) { alert('Arquivo maximo: 10MB'); return; }
        this.attachedFile = file;
        const iconMap = { 'audio': 'mic', 'image': 'image', 'application/pdf': 'picture_as_pdf' };
        let icon = 'description';
        for (const [k, v] of Object.entries(iconMap)) { if (file.type.includes(k)) { icon = v; break; } }
        document.getElementById('filePreviewIcon').textContent = icon;
        document.getElementById('filePreviewName').textContent = file.name;
        document.getElementById('filePreviewSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
        document.getElementById('filePreview').classList.remove('hidden');
    },

    clearFile() {
        this.attachedFile = null;
        document.getElementById('fileInput').value = '';
        document.getElementById('filePreview').classList.add('hidden');
    },

    // ── Drag & Drop ──
    dragOver(e) { e.preventDefault(); document.getElementById('dropOverlay').classList.add('active'); },
    dragLeave(e) { if (!e.currentTarget.contains(e.relatedTarget)) document.getElementById('dropOverlay').classList.remove('active'); },
    drop(e) {
        e.preventDefault();
        document.getElementById('dropOverlay').classList.remove('active');
        const file = e.dataTransfer.files[0];
        if (file) this.attachFile(file);
    },

    // ── Audio Recording ──
    async toggleRecording() {
        const btn = document.getElementById('recordBtn');
        const icon = document.getElementById('recordIcon');

        if (this.isRecording) {
            this.isRecording = false;
            this.mediaRecorder?.stop();
            btn.classList.remove('!bg-red-500/20', '!border-red-500/40', '!text-red-400');
            icon.textContent = 'mic';
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];
            this.mediaRecorder.ondataavailable = (e) => this.audioChunks.push(e.data);
            this.mediaRecorder.onstop = () => {
                const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                const file = new File([blob], 'audio_' + Date.now() + '.webm', { type: 'audio/webm' });
                this.attachFile(file);
                stream.getTracks().forEach(t => t.stop());
            };
            this.mediaRecorder.start();
            this.isRecording = true;
            btn.classList.add('!bg-red-500/20', '!border-red-500/40', '!text-red-400');
            icon.textContent = 'stop';
        } catch (e) {
            console.error('Mic error:', e);
        }
    },

    // ── Send Message ──
    sendMessage(text) {
        document.getElementById('chatInput').value = text;
        this.send();
    },

    async send() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg && !this.attachedFile) return;
        const requestContext = { filter: this.activeFilter, prompt: msg };

        const messages = document.getElementById('chatMessages');

        // File context
        let fileText = '';
        if (this.attachedFile) {
            const file = this.attachedFile;
            if (file.type.startsWith('text/') || /\.(csv|txt|doc)$/i.test(file.name)) {
                fileText = await file.text();
                fileText = '\n\n[Arquivo: ' + file.name + ']\n' + fileText.substring(0, 3000);
            } else {
                fileText = '\n\n[Arquivo anexado: ' + file.name + ' (' + (file.size/1024).toFixed(1) + 'KB) — tipo: ' + file.type + ']';
            }
        }

        const displayMsg = msg + (this.attachedFile ? '\n\ud83d\udcce ' + this.attachedFile.name : '');
        const fullMsg = msg + fileText;

        // User bubble
        const ts = new Date().toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
        messages.innerHTML += `
            <div class="flex gap-3 justify-end msg-animate">
                <div class="max-w-lg">
                    <div class="bg-lime/10 border border-lime/15 rounded-2xl rounded-tr-sm px-4 py-3 text-sm text-text whitespace-pre-wrap">${this.esc(displayMsg)}</div>
                    <p class="text-[9px] text-subtle mt-1 mr-1 text-right">${ts}</p>
                </div>
            </div>`;

        this.history.push({ role: 'user', content: fullMsg });
        input.value = '';
        input.style.height = 'auto';
        this.clearFile();

        // Typing indicator
        const loaderId = 'loader-' + Date.now();
        messages.innerHTML += `
            <div id="${loaderId}" class="flex gap-3 msg-animate">
                <div class="size-8 bg-lime/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-sm">smart_toy</span>
                </div>
                <div class="bg-surface2 border border-stroke rounded-2xl rounded-tl-sm px-4 py-3 flex items-center gap-2">
                    <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
                </div>
            </div>`;
        messages.scrollTop = messages.scrollHeight;
        document.getElementById('chatStatus').textContent = 'Pensando...';
        document.getElementById('sendBtn').disabled = true;

        try {
            const payload = {
                message: fullMsg,
                _csrf: getCsrfToken(),
                history: this.history.slice(-8),
            };
            if (this.selectedLead) payload.lead_id = this.selectedLead.id;
            if (this.activeFilter) payload.filter = this.activeFilter;

            const res = await operonFetch('/api/copilot', { method: 'POST', body: JSON.stringify(payload) });
            document.getElementById(loaderId)?.remove();

            if (res.error === 'tokens_depleted') {
                this.appendSystemMsg('Creditos esgotados. Recarregue seus tokens para continuar.', 'warning');
            } else {
                const reply = res.reply || res.error || 'Erro ao processar.';
                this.history.push({ role: 'assistant', content: reply });

                const rts = new Date().toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
                messages.innerHTML += `
                    <div class="flex gap-3 msg-animate">
                        <div class="size-8 bg-lime/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                            <span class="material-symbols-outlined text-lime text-sm">smart_toy</span>
                        </div>
                        <div class="max-w-2xl">
                            <div class="bg-surface2 border border-stroke rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-text ai-msg">${this.renderAssistantReply(reply, requestContext)}</div>
                            <div class="flex items-center gap-2 mt-1 ml-1">
                                <p class="text-[9px] text-subtle">${rts}</p>
                                <button onclick="Copilot.copyMsg(this)" class="text-[9px] text-subtle hover:text-lime transition-colors flex items-center gap-0.5" title="Copiar">
                                    <span class="material-symbols-outlined text-[11px]">content_copy</span>
                                </button>
                            </div>
                        </div>
                    </div>`;
                this.refreshDraftInputs(messages);
            }
        } catch (e) {
            document.getElementById(loaderId)?.remove();
            this.appendSystemMsg('Falha na conexao. Tente novamente.', 'error');
        }

        messages.scrollTop = messages.scrollHeight;
        document.getElementById('chatStatus').textContent = this.selectedLead ? 'Contexto: ' + this.selectedLead.name : 'Pronto para ajudar';
        document.getElementById('sendBtn').disabled = false;
        document.getElementById('chatInput').focus();
    },

    appendSystemMsg(text, type) {
        const messages = document.getElementById('chatMessages');
        const colors = {
            error: { bg: 'bg-red-500/5', border: 'border-red-500/20', text: 'text-red-300', icon: 'error', iconBg: 'bg-red-500/10', iconBorder: 'border-red-500/20', iconColor: 'text-red-400' },
            warning: { bg: 'bg-amber-500/5', border: 'border-amber-500/20', text: 'text-amber-300', icon: 'warning', iconBg: 'bg-amber-500/10', iconBorder: 'border-amber-500/20', iconColor: 'text-amber-400' },
        };
        const c = colors[type] || colors.error;
        messages.innerHTML += `
            <div class="flex gap-3 msg-animate">
                <div class="size-8 ${c.iconBg} rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border ${c.iconBorder}">
                    <span class="material-symbols-outlined ${c.iconColor} text-sm">${c.icon}</span>
                </div>
                <div class="${c.bg} border ${c.border} rounded-2xl rounded-tl-sm px-4 py-3 text-sm ${c.text}">${this.esc(text)}</div>
            </div>`;
    },

    copyMsg(btn) {
        const msgEl = btn.closest('.max-w-2xl')?.querySelector('.ai-msg');
        if (!msgEl) return;
        const draftEl = msgEl.querySelector('.copy-draft-input');
        const content = draftEl ? draftEl.value : msgEl.innerText;

        navigator.clipboard.writeText(content).then(() => {
            const icon = btn.querySelector('.material-symbols-outlined');
            icon.textContent = 'check';
            setTimeout(() => icon.textContent = 'content_copy', 1500);
        });
    },

    clearChat() {
        this.history = [];
        const messages = document.getElementById('chatMessages');
        messages.innerHTML = `
            <div class="flex gap-3 msg-animate">
                <div class="size-8 bg-lime/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-sm">smart_toy</span>
                </div>
                <div class="bg-surface2 border border-stroke rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-text max-w-lg">
                    Conversa limpa. Como posso ajudar?
                </div>
            </div>`;
    },

    renderAssistantReply(text, context = {}) {
        const artifact = this.extractCopyArtifact(text, context);
        if (!artifact) return this.formatReply(text);

        const token = '__COPY_DRAFT_CARD__';
        let source = text;

        if (artifact.rawBlock) {
            source = source.replace(artifact.rawBlock, '\n\n' + token + '\n\n');
        } else {
            source += '\n\n' + token;
        }
        source = source.trim();

        let html = this.formatReply(source);
        const card = this.renderCopyDraftCard(artifact);
        html = html.replace(new RegExp(`<p>${token}</p>`, 'g'), card);
        html = html.replace(new RegExp(token, 'g'), card);
        return html;
    },

    renderCopyDraftCard(artifact) {
        return `
            <div class="copy-draft-card">
                <div class="copy-draft-header">
                    <div>
                        <div class="copy-draft-title">${this.esc(artifact.title)}</div>
                        <div class="copy-draft-subtitle">Edite, copie ou importe no campo abaixo.</div>
                    </div>
                </div>
                <textarea class="copy-draft-input" oninput="Copilot.autoGrowDraft(this)">${this.esc(artifact.text)}</textarea>
                <div class="copy-draft-actions">
                    <button type="button" class="copy-draft-btn -primary" onclick="Copilot.copyDraft(this)">
                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                        <span class="copy-draft-label">Copiar</span>
                    </button>
                    <button type="button" class="copy-draft-btn" onclick="Copilot.importDraft(this)">
                        <span class="material-symbols-outlined text-[14px]">south</span>
                        <span class="copy-draft-label">Importar</span>
                    </button>
                </div>
            </div>`;
    },

    extractCopyArtifact(text, context = {}) {
        const normalized = (text || '').replace(/\r\n/g, '\n').trim();
        if (!normalized) return null;

        const delimitedMatch = normalized.match(/(?:^|\n)(?:-{3,}|\*{3,})\s*\n([\s\S]*?)\n(?:-{3,}|\*{3,})(?=\n|$)/);
        if (delimitedMatch) {
            const candidate = delimitedMatch[1].trim();
            if (this.looksLikeCopyText(candidate, context, true)) {
                return {
                    title: this.getCopyArtifactTitle(context),
                    text: candidate,
                    rawBlock: delimitedMatch[0],
                };
            }
        }

        if (!this.hasCopyIntent(context, normalized)) return null;

        const paragraphs = normalized.split(/\n{2,}/).map(p => p.trim()).filter(Boolean);
        if (paragraphs.length === 0) return null;

        let start = 0;
        let end = paragraphs.length;

        if (/^(claro|perfeito|otimo|aqui est[aá]|segue|abaixo|montei|criei|preparei)/i.test(paragraphs[0])) {
            start = 1;
        }

        while (end > start && /^(essa|esse|esta|este|se quiser|posso|observa[cç][aã]o|dica|ajuste|adapta[cç][aã]o|nota)/i.test(paragraphs[end - 1])) {
            end--;
        }

        const candidate = paragraphs.slice(start, end).join('\n\n').trim();
        if (!this.looksLikeCopyText(candidate, context, false)) return null;

        return {
            title: this.getCopyArtifactTitle(context),
            text: candidate,
            rawBlock: null,
        };
    },

    getCopyArtifactTitle(context = {}) {
        const hay = `${context.filter || ''} ${context.prompt || ''}`.toLowerCase();
        if (hay.includes('whatsapp')) return 'Mensagem pronta para WhatsApp';
        if (hay.includes('email')) return 'Email pronto para editar';
        if (hay.match(/follow[\s-]?up/)) return 'Follow-up pronto para editar';
        if (hay.match(/abordagem|pitch|roteiro|script/)) return 'Roteiro pronto para editar';
        return 'Texto pronto para editar';
    },

    hasCopyIntent(context = {}, text = '') {
        const hay = `${context.filter || ''} ${context.prompt || ''} ${text.slice(0, 180)}`.toLowerCase();
        return /(whatsapp|mensagem|abordagem|email|follow[\s-]?up|pitch|roteiro|script|texto pronto|pronta para copiar|copiar|enviar)/i.test(hay);
    },

    looksLikeCopyText(candidate, context = {}, fromDelimiter = false) {
        if (!candidate) return false;

        const trimmed = candidate.trim();
        const length = trimmed.length;
        const lineCount = trimmed.split('\n').filter(line => line.trim()).length;
        const hasGreeting = /^(ola|oi|bom dia|boa tarde|boa noite)\b/i.test(trimmed);
        const hasClosing = /(atenciosamente|abracos?|aguardo seu retorno|fico a disposi[cç][aã]o|podemos conversar|me chama)/i.test(trimmed);

        if (fromDelimiter) return length >= 60 && lineCount >= 2;

        if (length < 90 || lineCount < 3) return false;
        return hasGreeting || hasClosing || this.hasCopyIntent(context);
    },

    refreshDraftInputs(scope = document) {
        scope.querySelectorAll('.copy-draft-input').forEach((el) => this.autoGrowDraft(el));
    },

    autoGrowDraft(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 420) + 'px';
    },

    copyDraft(btn) {
        const textarea = btn.closest('.copy-draft-card')?.querySelector('.copy-draft-input');
        if (!textarea) return;

        navigator.clipboard.writeText(textarea.value).then(() => {
            this.flashDraftButton(btn, 'check', 'Copiado');
        });
    },

    importDraft(btn) {
        const textarea = btn.closest('.copy-draft-card')?.querySelector('.copy-draft-input');
        const input = document.getElementById('chatInput');
        if (!textarea || !input) return;

        input.value = textarea.value;
        input.dispatchEvent(new Event('input'));
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
        input.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        this.flashDraftButton(btn, 'check', 'Importado');
    },

    flashDraftButton(btn, iconName, label) {
        if (!btn) return;
        const icon = btn.querySelector('.material-symbols-outlined');
        const labelEl = btn.querySelector('.copy-draft-label');
        const original = btn.dataset.originalLabel || labelEl?.textContent || '';

        if (!btn.dataset.originalLabel) btn.dataset.originalLabel = original;
        if (icon) icon.textContent = iconName;
        if (labelEl) labelEl.textContent = label;

        setTimeout(() => {
            if (icon) icon.textContent = btn.classList.contains('-primary') ? 'content_copy' : 'south';
            if (labelEl) labelEl.textContent = btn.dataset.originalLabel;
        }, 1500);
    },

    // ── Markdown Formatter ──
    formatReply(text) {
        let html = this.esc(text);
        // Code blocks
        html = html.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
        // Headers
        html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.+)$/gm, '<h3>$1</h3>');
        // Bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Inline code
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');
        // Blockquote
        html = html.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
        // Unordered lists
        html = html.replace(/^[•\-\*] (.+)$/gm, '<li>$1</li>');
        html = html.replace(/((?:<li>.*<\/li>\n?)+)/g, '<ul>$1</ul>');
        // Ordered lists
        html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
        // Paragraphs (double newline)
        html = html.replace(/\n\n/g, '</p><p>');
        // Single newlines inside text (not after block elements)
        html = html.replace(/(?<!\>)\n(?!\<)/g, '<br>');
        // Wrap in paragraph
        if (!html.startsWith('<')) html = '<p>' + html + '</p>';
        return html;
    },

    esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    },

    // ── Lead Map Canvas ──
    drawLeadMap(highlightId) {
        const canvas = document.getElementById('leadMapCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        const leads = this.allLeads.slice(0, 40);
        if (leads.length === 0) {
            ctx.fillStyle = 'rgba(255,255,255,0.2)';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sem leads para visualizar', W/2, H/2);
            return;
        }

        // Use deterministic positions based on lead properties
        const nodes = leads.map((l, i) => {
            const hctx = typeof l.human_context === 'string' ? JSON.parse(l.human_context || '{}') : {};
            const temp = hctx.temperature || 'COLD';
            const colors = { HOT: '#ef4444', WARM: '#f59e0b', COLD: '#60a5fa' };
            const stageX = { new: 0.15, contacted: 0.3, qualified: 0.5, proposal: 0.7, closed_won: 0.88, closed_lost: 0.88 };
            const x = (stageX[l.pipeline_status] || 0.5) * W + (Math.sin(i * 2.7) * 20);
            const y = 20 + ((i * 37) % (H - 40)) + Math.cos(i * 1.3) * 15;
            const r = Math.max(4, Math.min(10, (l.priority_score || 30) / 12));
            return { x, y: Math.max(r+2, Math.min(H-r-2, y)), r, color: colors[temp], id: l.id, name: l.name, active: l.id === highlightId };
        });

        // Draw connections between same-segment leads
        ctx.strokeStyle = 'rgba(255,255,255,0.04)';
        ctx.lineWidth = 0.5;
        const segMap = {};
        leads.forEach((l, i) => { if (l.segment) { (segMap[l.segment] = segMap[l.segment] || []).push(i); }});
        Object.values(segMap).forEach(indices => {
            for (let i = 1; i < indices.length; i++) {
                const a = nodes[indices[i-1]], b = nodes[indices[i]];
                ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
            }
        });

        // Draw nodes
        nodes.forEach(n => {
            ctx.beginPath();
            ctx.arc(n.x, n.y, n.active ? n.r + 3 : n.r, 0, Math.PI * 2);
            ctx.fillStyle = n.active ? '#E1FB15' : n.color;
            ctx.globalAlpha = n.active ? 1 : 0.6;
            ctx.fill();
            if (n.active) {
                ctx.strokeStyle = '#E1FB15';
                ctx.lineWidth = 2;
                ctx.globalAlpha = 0.4;
                ctx.beginPath();
                ctx.arc(n.x, n.y, n.r + 7, 0, Math.PI * 2);
                ctx.stroke();
                // Name label
                ctx.globalAlpha = 1;
                ctx.fillStyle = '#E1FB15';
                ctx.font = 'bold 9px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(n.name.substring(0, 15), n.x, n.y - n.r - 6);
            }
            ctx.globalAlpha = 1;
        });

        // Stage labels at bottom
        ctx.font = '8px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillStyle = 'rgba(255,255,255,0.2)';
        const stagePos = { 'Novo': 0.15, 'Contato': 0.3, 'Qualif.': 0.5, 'Proposta': 0.7, 'Ganho': 0.88 };
        Object.entries(stagePos).forEach(([label, xPct]) => {
            ctx.fillText(label, xPct * W, H - 3);
        });
    },
};

Copilot.init();
</script>
