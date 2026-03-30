<?php
/**
 * Lead Infographic v3 — Premium relational canvas visualization
 * Refined: background, visual hierarchy, navigation, legend sidebar
 */

$analysis   = $lead['analysis'] ?? [];
$social     = $lead['social_presence'] ?? [];
$ctx        = $lead['human_context'] ?? [];
$tags       = $lead['tags'] ?? [];
$score      = (int)($lead['priority_score'] ?? 0);
$ps         = $lead['pagespeed_data'] ?? [];
$cnpj       = $lead['cnpj_data'] ?? [];

// Build comprehensive node data
$nodeData = [
    'lead' => [
        'name'      => $lead['name'] ?? '',
        'segment'   => $lead['segment'] ?? '',
        'score'     => $score,
        'fitScore'  => (int)($lead['fit_score'] ?? 0),
        'stage'     => stageLabel($lead['pipeline_status'] ?? 'new'),
        'stageKey'  => $lead['pipeline_status'] ?? 'new',
        'category'  => $lead['category'] ?? '',
        'rating'    => (float)($lead['rating'] ?? 0),
        'reviews'   => (int)($lead['review_count'] ?? 0),
        'origin'    => $lead['origin'] ?? $lead['source'] ?? '',
        'assignedTo'=> $lead['assigned_to'] ?? '',
    ],
    'contact' => [
        'phone'   => $lead['phone'] ?? '',
        'email'   => $lead['email'] ?? '',
        'website' => $lead['website'] ?? '',
        'address' => $lead['address'] ?? '',
        'maps'    => $lead['google_maps_url'] ?? '',
        'hours'   => ($lead['opening_hours'] ?? '') ? ($lead['opening_hours'] . ' - ' . ($lead['closing_hours'] ?? '')) : '',
    ],
    'social' => [
        'instagram' => $social['instagram'] ?? '',
        'facebook'  => $social['facebook'] ?? '',
        'linkedin'  => $social['linkedin'] ?? '',
    ],
    'context' => [
        'temperature' => $ctx['temperature'] ?? '',
        'timing'      => $ctx['timingStatus'] ?? '',
        'objection'   => $ctx['objectionCategory'] ?? '',
        'notes'       => $ctx['notes'] ?? '',
    ],
    'analysis' => [
        'hasAnalysis'     => !empty($analysis),
        'digitalMaturity' => $analysis['digitalMaturity'] ?? '',
        'urgencyLevel'    => $analysis['urgencyLevel'] ?? '',
        'summary'         => $analysis['summary'] ?? '',
        'scoreExplanation'=> $analysis['scoreExplanation'] ?? '',
        'operonFit'       => $analysis['operonFit'] ?? '',
        'opportunities'   => $analysis['opportunities'] ?? [],
        'problems'        => $analysis['diagnosis'] ?? [],
        'recommendations' => $analysis['recommendations'] ?? [],
        'extractedContact'=> $analysis['extractedContact'] ?? [],
    ],
    'intelligence' => [],
    'tags'     => $tags,
    'meta' => [
        'id'         => $lead['id'] ?? '',
        'createdAt'  => $lead['created_at'] ?? '',
        'updatedAt'  => $lead['updated_at'] ?? '',
        'followupAt' => $lead['next_followup_at'] ?? '',
    ],
    'pagespeed' => [
        'performance'   => (int)($ps['performanceScore'] ?? 0),
        'seo'           => (int)($ps['seoScore'] ?? 0),
        'accessibility' => (int)($ps['accessibilityScore'] ?? 0),
        'loadTime'      => $ps['loadTime'] ?? '',
    ],
    'cnpj' => $cnpj,
    'activities' => [],
    'activityItems' => [],
    'whatsapp' => [],
];

// Intelligence history
foreach ($intelligenceHistory ?? [] as $key => $run) {
    $resultDecoded = $run['result_data_decoded'] ?? null;
    $nodeData['intelligence'][] = [
        'key'    => $key,
        'status' => $run['status'] ?? 'pending',
        'date'   => $run['completed_at'] ?? $run['created_at'] ?? '',
        'hasResult' => !empty($resultDecoded),
    ];
}

// Activities
$actTypes = ['note' => 0, 'call' => 0, 'email' => 0, 'whatsapp' => 0, 'stage_change' => 0, 'ai_analysis' => 0, 'attachment' => 0];
foreach ($activities ?? [] as $act) {
    $t = $act['type'] ?? 'note';
    if (isset($actTypes[$t])) $actTypes[$t]++;
    $nodeData['activityItems'][] = [
        'type'    => $t,
        'title'   => $act['title'] ?? '',
        'content' => mb_substr($act['content'] ?? '', 0, 120),
        'date'    => $act['created_at'] ?? '',
        'user'    => $act['user_name'] ?? '',
        'meta'    => $act['metadata'] ?? null,
    ];
}
$nodeData['activities'] = $actTypes;

// WhatsApp conversations
foreach ($waConversations ?? [] as $conv) {
    $nodeData['whatsapp'][] = [
        'id'          => $conv['conversation_id'] ?? '',
        'name'        => $conv['display_name'] ?? $conv['phone'] ?? '',
        'phone'       => $conv['phone'] ?? '',
        'lastMessage' => $conv['last_message_at'] ?? '',
        'unread'      => (int)($conv['unread_count'] ?? 0),
        'summary'     => $conv['_summary']['summary'] ?? '',
        'pains'       => $conv['_summary']['pains'] ?? [],
        'score'       => (int)($conv['_interest']['interest_score'] ?? 0),
    ];
}

$jsonData = json_encode($nodeData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>

<!-- Infographic Overlay v3 -->
<div id="lead-infographic-overlay" class="fixed inset-0 z-[9999] hidden" style="background:#050508;">

    <!-- ═══ TOP BAR ═══ -->
    <div class="absolute top-0 left-0 right-0 z-30 flex items-center justify-between h-[56px] px-4"
         style="background:rgba(5,5,8,0.92); backdrop-filter:blur(12px); border-bottom:1px solid rgba(225,251,21,0.06);">

        <!-- Left: back button -->
        <button onclick="LeadInfographic.close()"
                class="flex items-center gap-2.5 h-9 pl-2 pr-4 rounded-lg transition-all
                       bg-white/[0.04] hover:bg-white/[0.08] border border-white/[0.06] hover:border-lime/20 group">
            <span class="material-symbols-outlined text-white/40 group-hover:text-lime text-[18px] transition-colors">arrow_back</span>
            <span class="text-[12px] font-semibold text-white/50 group-hover:text-white/80 tracking-wide transition-colors">Voltar ao lead</span>
        </button>

        <!-- Center: title -->
        <div class="absolute left-1/2 -translate-x-1/2 flex items-center gap-2.5">
            <div class="size-2 rounded-full bg-lime/60 animate-pulse"></div>
            <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/25">Mapa Relacional</span>
            <span class="text-white/10">·</span>
            <span class="text-[12px] font-semibold text-white/60"><?= e($lead['name']) ?></span>
        </div>

        <!-- Right: controls -->
        <div class="flex items-center gap-1">
            <button onclick="LeadInfographic.toggleLegend()" id="btn-legend"
                    class="h-8 px-3 rounded-lg text-[11px] font-semibold transition-all flex items-center gap-1.5
                           text-white/40 hover:text-lime hover:bg-lime/[0.06] border border-transparent hover:border-lime/10">
                <span class="material-symbols-outlined text-[15px]">info</span>
                <span>Guia</span>
            </button>
            <div class="w-px h-4 bg-white/[0.06] mx-1"></div>
            <button onclick="LeadInfographic.resetView()" class="infog-ctrl" title="Centralizar">
                <span class="material-symbols-outlined text-[16px]">center_focus_strong</span>
            </button>
            <button onclick="LeadInfographic.zoomIn()" class="infog-ctrl" title="Zoom +">
                <span class="material-symbols-outlined text-[16px]">add</span>
            </button>
            <button onclick="LeadInfographic.zoomOut()" class="infog-ctrl" title="Zoom −">
                <span class="material-symbols-outlined text-[16px]">remove</span>
            </button>
            <div class="w-px h-4 bg-white/[0.06] mx-1"></div>
            <button onclick="LeadInfographic.close()"
                    class="size-8 rounded-lg flex items-center justify-center text-white/30 hover:text-red-400 hover:bg-red-400/[0.08] transition-all"
                    title="Fechar (ESC)">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
    </div>

    <!-- ═══ LEGEND / GUIDE SIDEBAR ═══ -->
    <div id="infographic-legend"
         class="absolute top-[56px] right-0 w-[300px] z-20 overflow-y-auto hidden"
         style="height:calc(100% - 56px); background:rgba(8,8,12,0.96); backdrop-filter:blur(16px); border-left:1px solid rgba(225,251,21,0.06);">

        <div class="p-5 space-y-5">

            <!-- Section: About -->
            <div class="space-y-2">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-lime/50 text-[14px]">auto_awesome</span>
                    <h3 class="text-[10px] font-bold text-lime/40 uppercase tracking-[0.16em]">Guia do Mapa</h3>
                </div>
                <p class="text-[11px] text-white/35 leading-[1.6]">
                    Visualização relacional de todos os dados, conexões e metadados vinculados a este lead. Cada nó representa um grupo de informações.
                </p>
            </div>

            <div class="h-px w-full" style="background:linear-gradient(90deg,transparent,rgba(225,251,21,0.08),transparent);"></div>

            <!-- Section: Node Types -->
            <div>
                <h4 class="text-[10px] font-bold text-white/30 uppercase tracking-[0.14em] mb-3">Tipos de Nó</h4>
                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="size-9 shrink-0 rounded-full flex items-center justify-center text-[10px] font-bold mt-0.5"
                             style="border:2px solid #E1FB15; color:#E1FB15; background:rgba(225,251,21,0.05);">◉</div>
                        <div>
                            <p class="text-[11px] text-white/70 font-semibold">Hub Central</p>
                            <p class="text-[10px] text-white/25 leading-snug">O lead — centro de todas as conexões do ecossistema</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="size-8 shrink-0 rounded-full flex items-center justify-center text-[9px] mt-0.5"
                             style="border:1.5px solid #34D399; color:#34D399; background:rgba(52,211,153,0.05);">◆</div>
                        <div>
                            <p class="text-[11px] text-white/70 font-semibold">Cluster Primário</p>
                            <p class="text-[10px] text-white/25 leading-snug">Grupo principal — contato, pipeline, diagnóstico, etc</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="size-7 shrink-0 rounded-full flex items-center justify-center text-[8px] mt-0.5"
                             style="border:1px solid rgba(138,138,138,0.4); color:#8A8A8A; background:rgba(138,138,138,0.03);">○</div>
                        <div>
                            <p class="text-[11px] text-white/70 font-semibold">Nó Secundário</p>
                            <p class="text-[10px] text-white/25 leading-snug">Detalhe derivado ou sub-conexão de um cluster</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="h-px w-full" style="background:linear-gradient(90deg,transparent,rgba(255,255,255,0.04),transparent);"></div>

            <!-- Section: Connection Types -->
            <div>
                <h4 class="text-[10px] font-bold text-white/30 uppercase tracking-[0.14em] mb-3">Tipos de Conexão</h4>
                <div class="space-y-2.5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-0 shrink-0" style="border-top:2px dashed rgba(225,251,21,0.5);"></div>
                        <div>
                            <p class="text-[10px] text-white/50 font-medium">Conexão Direta</p>
                            <p class="text-[9px] text-white/20">Dado pertence diretamente ao lead</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-0 shrink-0" style="border-top:1px solid rgba(138,138,138,0.3);"></div>
                        <div>
                            <p class="text-[10px] text-white/50 font-medium">Conexão Derivada</p>
                            <p class="text-[9px] text-white/20">Dado relacionado ou extraído indiretamente</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="h-px w-full" style="background:linear-gradient(90deg,transparent,rgba(255,255,255,0.04),transparent);"></div>

            <!-- Section: Proximity / Importance -->
            <div>
                <h4 class="text-[10px] font-bold text-white/30 uppercase tracking-[0.14em] mb-3">Nível de Proximidade</h4>
                <div class="space-y-2">
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center size-5 shrink-0 rounded-full" style="background:rgba(225,251,21,0.15); box-shadow:0 0 8px rgba(225,251,21,0.3);">
                            <div class="size-2 rounded-full bg-lime"></div>
                        </div>
                        <div>
                            <span class="text-[10px] text-white/50 font-medium">Alta</span>
                            <span class="text-[9px] text-white/20 ml-1">— Dados estratégicos, decisivos para a venda</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center size-5 shrink-0 rounded-full" style="background:rgba(251,191,36,0.12);">
                            <div class="size-2 rounded-full" style="background:#FBBF24;"></div>
                        </div>
                        <div>
                            <span class="text-[10px] text-white/50 font-medium">Média</span>
                            <span class="text-[9px] text-white/20 ml-1">— Contexto relevante e complementar</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center size-5 shrink-0 rounded-full" style="background:rgba(85,85,85,0.12);">
                            <div class="size-2 rounded-full" style="background:#555;"></div>
                        </div>
                        <div>
                            <span class="text-[10px] text-white/50 font-medium">Info</span>
                            <span class="text-[9px] text-white/20 ml-1">— Metadados e registros de apoio</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 p-2.5 rounded-lg" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.03);">
                    <p class="text-[9px] text-white/20 leading-[1.5]">
                        <span class="text-white/35 font-medium">Anel 1</span> (mais próximo): clusters primários com dados diretos do lead.
                        <span class="text-white/35 font-medium">Anel 2</span> (externo): detalhes derivados, inteligência e sub-conexões.
                    </p>
                </div>
            </div>

            <div class="h-px w-full" style="background:linear-gradient(90deg,transparent,rgba(255,255,255,0.04),transparent);"></div>

            <!-- Section: Color Map -->
            <div>
                <h4 class="text-[10px] font-bold text-white/30 uppercase tracking-[0.14em] mb-3">Categorias por Cor</h4>
                <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#34D399;"></div><span class="text-[10px] text-white/40">Contato</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#A78BFA;"></div><span class="text-[10px] text-white/40">Pipeline</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#FBBF24;"></div><span class="text-[10px] text-white/40">Termômetro</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#E1FB15;"></div><span class="text-[10px] text-white/40">IA / Análise</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#F472B6;"></div><span class="text-[10px] text-white/40">Social</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#22D3EE;"></div><span class="text-[10px] text-white/40">Timeline</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#4ADE80;"></div><span class="text-[10px] text-white/40">WhatsApp</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#FB923C;"></div><span class="text-[10px] text-white/40">Inteligência</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#F87171;"></div><span class="text-[10px] text-white/40">Problemas</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#2DD4BF;"></div><span class="text-[10px] text-white/40">Recomendações</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#818CF8;"></div><span class="text-[10px] text-white/40">Tags</span></div>
                    <div class="flex items-center gap-2"><div class="size-2.5 rounded-full" style="background:#60A5FA;"></div><span class="text-[10px] text-white/40">CNPJ</span></div>
                </div>
            </div>

            <div class="h-px w-full" style="background:linear-gradient(90deg,transparent,rgba(255,255,255,0.04),transparent);"></div>

            <!-- Section: Interaction -->
            <div>
                <h4 class="text-[10px] font-bold text-white/30 uppercase tracking-[0.14em] mb-3">Como Navegar</h4>
                <div class="space-y-2 text-[10px]">
                    <div class="flex items-center gap-2.5">
                        <div class="size-6 shrink-0 rounded bg-white/[0.03] flex items-center justify-center border border-white/[0.04]">
                            <span class="material-symbols-outlined text-white/25 text-[12px]">mouse</span>
                        </div>
                        <p class="text-white/35"><span class="text-white/50 font-medium">Clique</span> no nó para expandir detalhes</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="size-6 shrink-0 rounded bg-white/[0.03] flex items-center justify-center border border-white/[0.04]">
                            <span class="material-symbols-outlined text-white/25 text-[12px]">drag_pan</span>
                        </div>
                        <p class="text-white/35"><span class="text-white/50 font-medium">Arraste</span> nós ou o fundo para mover</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="size-6 shrink-0 rounded bg-white/[0.03] flex items-center justify-center border border-white/[0.04]">
                            <span class="material-symbols-outlined text-white/25 text-[12px]">unfold_more</span>
                        </div>
                        <p class="text-white/35"><span class="text-white/50 font-medium">Scroll</span> para zoom in / out</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="size-6 shrink-0 rounded bg-white/[0.03] flex items-center justify-center border border-white/[0.04]">
                            <span class="material-symbols-outlined text-white/25 text-[12px]">ads_click</span>
                        </div>
                        <p class="text-white/35"><span class="text-white/50 font-medium">Duplo clique</span> centraliza no nó</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="size-6 shrink-0 rounded bg-white/[0.03] flex items-center justify-center border border-white/[0.04]">
                            <span class="text-[10px] text-white/25 font-mono font-bold">ESC</span>
                        </div>
                        <p class="text-white/35"><span class="text-white/50 font-medium">Escape</span> fecha o infográfico</p>
                    </div>
                </div>
            </div>

            <div class="h-px w-full" style="background:linear-gradient(90deg,transparent,rgba(255,255,255,0.04),transparent);"></div>

            <!-- Section: Stats -->
            <div>
                <h4 class="text-[10px] font-bold text-white/30 uppercase tracking-[0.14em] mb-3">Resumo do Ecossistema</h4>
                <div id="legend-stats" class="space-y-1.5"></div>
            </div>

        </div>
    </div>

    <!-- ═══ CANVAS ═══ -->
    <canvas id="lead-infographic-canvas" class="absolute top-[56px] left-0"></canvas>
</div>

<style>
.infog-ctrl {
    width:32px; height:32px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    color:rgba(255,255,255,0.3); transition:all 0.15s;
}
.infog-ctrl:hover { color:rgba(255,255,255,0.7); background:rgba(255,255,255,0.05); }
</style>

<script>
const LeadInfographic = (() => {
    let canvas, ctx, W, H;
    let data = <?= $jsonData ?>;
    let nodes = [], edges = [];
    let offsetX = 0, offsetY = 0, scale = 1;
    let dragging = false, dragNode = null, dragStartX = 0, dragStartY = 0;
    let panStartX = 0, panStartY = 0;
    let hoveredNode = null, animFrame = null;
    let isOpen = false, legendOpen = false;
    let canvasW = 0, initialized = false;
    const BAR_H = 56;

    // ── Color palette ──────────────────────────────────────────
    const C = {
        bg:      '#050508',
        surface: '#0C0C10',
        card:    '#101015',
        stroke:  '#1A1A22',
        lime:    '#E1FB15',
        text:    '#D8D8D8',
        muted:   '#7A7A8A',
        subtle:  '#4A4A55',
        mint:    '#34D399',
        red:     '#F87171',
        amber:   '#FBBF24',
        blue:    '#60A5FA',
        purple:  '#A78BFA',
        pink:    '#F472B6',
        cyan:    '#22D3EE',
        orange:  '#FB923C',
        green:   '#4ADE80',
        indigo:  '#818CF8',
        rose:    '#FB7185',
        teal:    '#2DD4BF',
    };

    const stageColors = { new: C.blue, analyzed: C.green, contacted: C.amber, qualified: C.cyan, proposal: C.purple, closed_won: C.lime, closed_lost: C.red };
    const tempColors  = { HOT: C.red, WARM: C.amber, COLD: C.blue };
    const tempLabels  = { HOT: 'Quente', WARM: 'Morno', COLD: 'Frio' };
    const timingLabels = { IMMEDIATE: 'Imediato', SHORT_TERM: 'Curto Prazo', LONG_TERM: 'Longo Prazo' };
    const objLabels   = { PRICE: 'Preço/Orçamento', COMPETITOR: 'Concorrente', TIMING: 'Timing', TRUST: 'Confiança' };
    const intNames    = { value_proposition: 'Perfil & Oportunidade', target_audience: 'Público-Alvo & Encaixe', competitors: 'Concorrentes & Posição' };

    // ── Lifecycle ──────────────────────────────────────────────
    function open() {
        const overlay = document.getElementById('lead-infographic-overlay');
        // Move overlay to body to escape any parent stacking context
        if (overlay.parentElement !== document.body) {
            document.body.appendChild(overlay);
        }
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        isOpen = true;
        if (!initialized) { initCanvas(); initialized = true; }
        resize();
        buildNodes();
        updateLegendStats();
        render();
    }

    function close() {
        document.getElementById('lead-infographic-overlay').classList.add('hidden');
        document.body.style.overflow = '';
        isOpen = false;
        if (animFrame) cancelAnimationFrame(animFrame);
    }

    function initCanvas() {
        canvas = document.getElementById('lead-infographic-canvas');
        ctx = canvas.getContext('2d');
        canvas.addEventListener('mousedown', onMouseDown);
        canvas.addEventListener('mousemove', onMouseMove);
        canvas.addEventListener('mouseup', onMouseUp);
        canvas.addEventListener('wheel', onWheel, { passive: false });
        canvas.addEventListener('dblclick', onDblClick);
        canvas.addEventListener('touchstart', onTouchStart, { passive: false });
        canvas.addEventListener('touchmove', onTouchMove, { passive: false });
        canvas.addEventListener('touchend', onTouchEnd);
        window.addEventListener('resize', () => { if (isOpen) { resize(); buildNodes(); } });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && isOpen) close(); });
    }

    function resize() {
        W = window.innerWidth;
        H = window.innerHeight - BAR_H;
        canvasW = legendOpen ? W - 300 : W;
        canvas.width = canvasW * devicePixelRatio;
        canvas.height = H * devicePixelRatio;
        canvas.style.width = canvasW + 'px';
        canvas.style.height = H + 'px';
        ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
    }

    function toggleLegend() {
        legendOpen = !legendOpen;
        document.getElementById('infographic-legend').classList.toggle('hidden', !legendOpen);
        const btn = document.getElementById('btn-legend');
        btn.classList.toggle('!text-lime', legendOpen);
        btn.classList.toggle('!bg-lime/[0.06]', legendOpen);
        btn.classList.toggle('!border-lime/10', legendOpen);
        resize();
        buildNodes();
    }

    // ── Build nodes ───────────────────────────────────────────
    function buildNodes() {
        nodes = [];
        edges = [];
        const cx = canvasW / 2, cy = H / 2;

        // ── CENTRAL NODE ──
        const centerItems = [
            { label: 'Score', value: data.lead.score + '/100', color: scoreColor(data.lead.score) },
            { label: 'Fit Score', value: data.lead.fitScore + '/100', color: scoreColor(data.lead.fitScore) },
            { label: 'Estágio', value: data.lead.stage, color: stageColors[data.lead.stageKey] || C.muted },
        ];
        if (data.lead.category) centerItems.push({ label: 'Categoria', value: data.lead.category });
        if (data.lead.rating) centerItems.push({ label: 'Avaliação', value: data.lead.rating.toFixed(1) + ' (' + data.lead.reviews + ' reviews)' });
        if (data.lead.origin) centerItems.push({ label: 'Origem', value: data.lead.origin });

        addNode('center', data.lead.name, data.lead.segment, 'hub', cx, cy, 68, C.lime, '◉', centerItems, 'alta', 'Hub central do ecossistema');

        // ── RING 1: Primary clusters ──
        const ring1 = [];

        // Contact
        const contactItems = [];
        if (data.contact.phone) contactItems.push({ label: 'Telefone', value: data.contact.phone });
        if (data.contact.email) contactItems.push({ label: 'E-mail', value: data.contact.email });
        if (data.contact.website) contactItems.push({ label: 'Website', value: trunc(data.contact.website.replace(/^https?:\/\/(www\.)?/, ''), 35) });
        if (data.contact.address) contactItems.push({ label: 'Endereço', value: data.contact.address });
        if (data.contact.maps) contactItems.push({ label: 'Maps', value: 'Ver no Google Maps' });
        if (data.contact.hours) contactItems.push({ label: 'Horário', value: data.contact.hours });
        if (contactItems.length) ring1.push({ id: 'contact', title: 'Contato', icon: 'call', color: C.mint, items: contactItems, importance: 'alta', relation: 'Dados de contato direto do lead' });

        // Pipeline
        ring1.push({
            id: 'pipeline', title: 'Pipeline', icon: 'monitoring', color: stageColors[data.lead.stageKey] || C.purple, importance: 'alta', relation: 'Posição atual no funil de vendas',
            items: [
                { label: 'Status', value: data.lead.stage, color: stageColors[data.lead.stageKey] },
                { label: 'Score', value: data.lead.score + '/100', color: scoreColor(data.lead.score) },
                { label: 'Fit Score', value: data.lead.fitScore + '/100' },
                ...(data.meta.followupAt ? [{ label: 'Próx. Follow-up', value: fmtDate(data.meta.followupAt), color: C.amber }] : []),
                { label: 'Criado em', value: fmtDate(data.meta.createdAt) },
                { label: 'Atualizado', value: fmtDate(data.meta.updatedAt) },
            ]
        });

        // Thermometer
        const ctxItems = [];
        if (data.context.temperature) ctxItems.push({ label: 'Temperatura', value: tempLabels[data.context.temperature] || data.context.temperature, color: tempColors[data.context.temperature] || C.muted });
        if (data.context.timing) ctxItems.push({ label: 'Timing', value: timingLabels[data.context.timing] || data.context.timing });
        if (data.context.objection) ctxItems.push({ label: 'Objeção', value: objLabels[data.context.objection] || data.context.objection, color: C.red });
        if (data.context.notes) ctxItems.push({ label: 'Observações', value: trunc(data.context.notes, 90) });
        if (ctxItems.length) ring1.push({ id: 'context', title: 'Termômetro', icon: 'thermostat', color: C.amber, items: ctxItems, importance: 'alta', relation: 'Contexto humano e percepção do vendedor' });

        // AI Analysis
        if (data.analysis.hasAnalysis) {
            const aItems = [];
            if (data.analysis.digitalMaturity) aItems.push({ label: 'Maturidade', value: data.analysis.digitalMaturity, color: C.lime });
            if (data.analysis.urgencyLevel) aItems.push({ label: 'Urgência', value: data.analysis.urgencyLevel });
            if (data.analysis.operonFit) aItems.push({ label: 'Fit Operon', value: trunc(data.analysis.operonFit, 60) });
            if (data.analysis.scoreExplanation) aItems.push({ label: 'Score', value: trunc(data.analysis.scoreExplanation, 60) });
            if (data.analysis.summary) aItems.push({ label: 'Resumo', value: trunc(data.analysis.summary, 100) });
            ring1.push({ id: 'analysis', title: 'Diagnóstico IA', icon: 'psychology', color: C.lime, items: aItems, importance: 'alta', relation: 'Análise de inteligência artificial' });
        }

        // Social
        const socialItems = [];
        if (data.social.instagram) socialItems.push({ label: 'Instagram', value: '@' + data.social.instagram });
        if (data.social.facebook) socialItems.push({ label: 'Facebook', value: data.social.facebook });
        if (data.social.linkedin) socialItems.push({ label: 'LinkedIn', value: data.social.linkedin });
        const extractedSocial = data.analysis.extractedContact || {};
        if (extractedSocial.instagram && !data.social.instagram) socialItems.push({ label: 'IG (IA)', value: extractedSocial.instagram, color: C.muted });
        if (socialItems.length) ring1.push({ id: 'social', title: 'Presença Social', icon: 'share', color: C.pink, items: socialItems, importance: 'media', relation: 'Presença digital e redes sociais' });

        // Activities
        const totalAct = Object.values(data.activities).reduce((s, v) => s + v, 0);
        if (totalAct > 0) {
            const actItems = [];
            if (data.activities.note) actItems.push({ label: 'Notas', value: data.activities.note + ' registro(s)', color: C.text });
            if (data.activities.call) actItems.push({ label: 'Ligações', value: data.activities.call + ' registro(s)', color: C.cyan });
            if (data.activities.whatsapp) actItems.push({ label: 'WhatsApp', value: data.activities.whatsapp + ' registro(s)', color: C.green });
            if (data.activities.email) actItems.push({ label: 'E-mails', value: data.activities.email + ' registro(s)', color: C.blue });
            if (data.activities.stage_change) actItems.push({ label: 'Mudanças', value: data.activities.stage_change + ' transição(ões)', color: C.purple });
            if (data.activities.ai_analysis) actItems.push({ label: 'Análises IA', value: data.activities.ai_analysis + ' execução(ões)', color: C.lime });
            if (data.activities.attachment) actItems.push({ label: 'Arquivos', value: data.activities.attachment + ' anexo(s)', color: C.orange });
            ring1.push({ id: 'activities', title: 'Timeline (' + totalAct + ')', icon: 'history', color: C.cyan, items: actItems, importance: 'media', relation: 'Histórico de interações e registros' });
        }

        // Place ring 1
        const r1 = Math.min(240, Math.min(canvasW, H) * 0.22);
        ring1.forEach((n, i) => {
            const angle = (i / ring1.length) * Math.PI * 2 - Math.PI / 2;
            addNode(n.id, n.title, '', 'cluster', cx + Math.cos(angle) * r1, cy + Math.sin(angle) * r1, 42, n.color, n.icon, n.items, n.importance, n.relation);
            edges.push({ from: 'center', to: n.id, color: n.color, type: 'primary', label: n.relation });
        });

        // ── RING 2: Secondary nodes ──
        const ring2 = [];

        // WhatsApp conversations
        data.whatsapp.forEach((wa, i) => {
            const waItems = [
                { label: 'Contato', value: wa.name },
                { label: 'Telefone', value: wa.phone },
                { label: 'Última msg', value: fmtDate(wa.lastMessage) },
            ];
            if (wa.unread) waItems.push({ label: 'Não lidas', value: wa.unread + ' mensagem(ns)', color: C.green });
            if (wa.summary) waItems.push({ label: 'Resumo', value: trunc(wa.summary, 80) });
            if (wa.score) waItems.push({ label: 'Score', value: wa.score + '/100', color: scoreColor(wa.score) });
            if (wa.pains && wa.pains.length) waItems.push({ label: 'Dores', value: wa.pains.slice(0, 3).join(', '), color: C.red });
            ring2.push({ id: 'wa_' + i, title: 'WA: ' + trunc(wa.name, 12), icon: 'chat', color: C.green, items: waItems, importance: 'alta', relation: 'Conversa WhatsApp', parent: 'activities' });
        });

        // Intelligence modules
        data.intelligence.forEach((intel, i) => {
            const iItems = [
                { label: 'Módulo', value: intNames[intel.key] || intel.key },
                { label: 'Status', value: intel.status === 'completed' ? 'Concluído' : intel.status === 'failed' ? 'Falhou' : 'Pendente', color: intel.status === 'completed' ? C.lime : intel.status === 'failed' ? C.red : C.amber },
            ];
            if (intel.date) iItems.push({ label: 'Data', value: fmtDate(intel.date) });
            ring2.push({ id: 'intel_' + i, title: intNames[intel.key] || intel.key, icon: 'biotech', color: C.orange, items: iItems, importance: 'media', relation: 'Inteligência profunda', parent: 'analysis' });
        });

        // Opportunities
        if (data.analysis.opportunities && data.analysis.opportunities.length) {
            const oppItems = data.analysis.opportunities.slice(0, 5).map(o => {
                const txt = typeof o === 'string' ? o : (o.title || o.description || JSON.stringify(o));
                return { label: '', value: trunc(txt, 60), color: C.lime };
            });
            ring2.push({ id: 'opportunities', title: 'Oportunidades (' + data.analysis.opportunities.length + ')', icon: 'lightbulb', color: C.lime, items: oppItems, importance: 'alta', relation: 'Oportunidades identificadas pela IA', parent: 'analysis' });
        }

        // Problems
        if (data.analysis.problems && data.analysis.problems.length) {
            const probItems = data.analysis.problems.slice(0, 5).map(p => {
                const txt = typeof p === 'string' ? p : (p.title || p.description || JSON.stringify(p));
                return { label: '', value: trunc(txt, 60), color: C.red };
            });
            ring2.push({ id: 'problems', title: 'Problemas (' + data.analysis.problems.length + ')', icon: 'warning', color: C.red, items: probItems, importance: 'alta', relation: 'Problemas detectados pela IA', parent: 'analysis' });
        }

        // Recommendations
        if (data.analysis.recommendations && data.analysis.recommendations.length) {
            const recItems = data.analysis.recommendations.slice(0, 5).map(r => {
                const txt = typeof r === 'string' ? r : (r.title || r.description || JSON.stringify(r));
                return { label: '', value: trunc(txt, 60) };
            });
            ring2.push({ id: 'recommendations', title: 'Recomendações', icon: 'target', color: C.teal, items: recItems, importance: 'media', relation: 'Ações recomendadas pela IA', parent: 'analysis' });
        }

        // Tags
        if (data.tags.length) {
            ring2.push({ id: 'tags', title: 'Tags (' + data.tags.length + ')', icon: 'label', color: C.indigo, items: data.tags.map(t => ({ label: '', value: t, color: C.indigo })), importance: 'info', relation: 'Classificações e marcadores', parent: 'center' });
        }

        // PageSpeed
        if (data.pagespeed.performance > 0) {
            ring2.push({ id: 'pagespeed', title: 'PageSpeed', icon: 'speed', color: C.cyan, importance: 'info', relation: 'Performance do site', parent: 'contact',
                items: [
                    { label: 'Performance', value: data.pagespeed.performance + '%', color: scoreColor(data.pagespeed.performance) },
                    { label: 'SEO', value: data.pagespeed.seo + '%', color: scoreColor(data.pagespeed.seo) },
                    { label: 'Acessibilidade', value: data.pagespeed.accessibility + '%' },
                    ...(data.pagespeed.loadTime ? [{ label: 'Tempo', value: data.pagespeed.loadTime }] : []),
                ]
            });
        }

        // CNPJ
        if (data.cnpj && Object.keys(data.cnpj).length > 0) {
            const cnpjItems = [];
            if (data.cnpj.razao_social) cnpjItems.push({ label: 'Razão Social', value: trunc(data.cnpj.razao_social, 40) });
            if (data.cnpj.cnpj) cnpjItems.push({ label: 'CNPJ', value: data.cnpj.cnpj });
            if (data.cnpj.situacao) cnpjItems.push({ label: 'Situação', value: data.cnpj.situacao, color: data.cnpj.situacao === 'ATIVA' ? C.lime : C.red });
            if (data.cnpj.porte) cnpjItems.push({ label: 'Porte', value: data.cnpj.porte });
            if (data.cnpj.capital_social) cnpjItems.push({ label: 'Capital', value: 'R$ ' + Number(data.cnpj.capital_social).toLocaleString('pt-BR') });
            if (cnpjItems.length) ring2.push({ id: 'cnpj', title: 'Dados CNPJ', icon: 'apartment', color: C.blue, items: cnpjItems, importance: 'info', relation: 'Dados oficiais CNPJ', parent: 'contact' });
        }

        // Recent activities
        const recentActs = data.activityItems.filter(a => a.title || a.content).slice(0, 6);
        if (recentActs.length) {
            const actDetailItems = recentActs.map(a => {
                const typeIcons = { note: 'edit_note', call: 'call', email: 'mail', whatsapp: 'chat', stage_change: 'swap_horiz', ai_analysis: 'psychology', attachment: 'attach_file' };
                return { label: fmtDate(a.date), value: trunc(a.title || a.content, 50), color: C.text, icon: typeIcons[a.type] };
            });
            ring2.push({ id: 'recent_acts', title: 'Últimas Atividades', icon: 'schedule', color: C.cyan, items: actDetailItems, importance: 'media', relation: 'Registros recentes', parent: 'activities' });
        }

        // Place ring 2
        const r2 = Math.min(420, Math.min(canvasW, H) * 0.38);
        ring2.forEach((n, i) => {
            const angle = (i / ring2.length) * Math.PI * 2 - Math.PI / 2 + (Math.PI / ring2.length * 0.5);
            addNode(n.id, n.title, '', 'leaf', cx + Math.cos(angle) * r2, cy + Math.sin(angle) * r2, 30, n.color, n.icon, n.items, n.importance, n.relation);
            const parentId = n.parent && nodes.find(nd => nd.id === n.parent) ? n.parent : findClosestCluster(cx + Math.cos(angle) * r2, cy + Math.sin(angle) * r2);
            edges.push({ from: parentId, to: n.id, color: n.color + '30', type: 'secondary', label: n.relation });
        });
    }

    function addNode(id, title, subtitle, type, x, y, radius, color, icon, items, importance, relation) {
        nodes.push({ id, title, subtitle, type, x, y, ox: x, oy: y, radius, color, icon, items: items || [], importance: importance || 'info', relation: relation || '', expanded: false });
    }

    function findClosestCluster(x, y) {
        let best = 'center', bestDist = Infinity;
        for (const n of nodes) {
            if (n.type !== 'cluster') continue;
            const d = Math.hypot(n.x - x, n.y - y);
            if (d < bestDist) { bestDist = d; best = n.id; }
        }
        return best;
    }

    function scoreColor(s) { return s >= 70 ? C.lime : s >= 40 ? C.amber : C.red; }

    // ── Rendering ─────────────────────────────────────────────
    function render() {
        ctx.save();
        ctx.clearRect(0, 0, canvasW, H);

        // Background: subtle radial gradient
        const bgGrad = ctx.createRadialGradient(canvasW / 2, H / 2, 0, canvasW / 2, H / 2, Math.max(canvasW, H) * 0.7);
        bgGrad.addColorStop(0, '#0A0A10');
        bgGrad.addColorStop(0.5, '#070709');
        bgGrad.addColorStop(1, '#030305');
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, canvasW, H);

        drawGrid();

        ctx.translate(offsetX, offsetY);
        ctx.scale(scale, scale);

        // Edges
        for (const e of edges) {
            const from = nodes.find(n => n.id === e.from);
            const to = nodes.find(n => n.id === e.to);
            if (from && to) drawEdge(from, to, e);
        }

        // Nodes: leaf → cluster → hub (z-order)
        for (const type of ['leaf', 'cluster', 'hub']) {
            for (const n of nodes) { if (n.type === type) drawNode(n); }
        }

        // Expanded panel
        const exp = nodes.find(n => n.expanded);
        if (exp) drawExpandedPanel(exp);

        ctx.restore();
        if (isOpen) animFrame = requestAnimationFrame(render);
    }

    function drawGrid() {
        // Dot grid pattern
        const gap = 50 * scale;
        const ox = ((offsetX % gap) + gap) % gap;
        const oy = ((offsetY % gap) + gap) % gap;
        ctx.fillStyle = 'rgba(255,255,255,0.018)';
        for (let x = ox; x < canvasW; x += gap) {
            for (let y = oy; y < H; y += gap) {
                ctx.beginPath();
                ctx.arc(x, y, 0.8, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }

    function drawEdge(from, to, e) {
        const isPrimary = e.type === 'primary';
        ctx.beginPath();
        ctx.strokeStyle = e.color || C.stroke;
        ctx.lineWidth = isPrimary ? 1.5 : 0.8;
        ctx.setLineDash(isPrimary ? [6, 4] : [3, 3]);
        ctx.globalAlpha = isPrimary ? 0.55 : 0.3;

        // Subtle curve
        const mx = (from.x + to.x) / 2, my = (from.y + to.y) / 2;
        const dx = to.x - from.x, dy = to.y - from.y;
        const curveOffset = isPrimary ? 0.08 : 0.06;
        ctx.moveTo(from.x, from.y);
        ctx.quadraticCurveTo(mx - dy * curveOffset, my + dx * curveOffset, to.x, to.y);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.globalAlpha = 1;

        // Small end dot
        ctx.beginPath();
        ctx.arc(to.x, to.y, isPrimary ? 3 : 2, 0, Math.PI * 2);
        ctx.fillStyle = e.color || C.stroke;
        ctx.globalAlpha = isPrimary ? 0.5 : 0.3;
        ctx.fill();
        ctx.globalAlpha = 1;

        // Edge label on hover
        if (hoveredNode === to.id && e.label && isPrimary) {
            const lx = mx - dy * 0.04, ly = my + dx * 0.04;
            ctx.font = '500 9px Inter, system-ui, sans-serif';
            ctx.fillStyle = C.muted;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            // Label pill bg
            const tw = ctx.measureText(e.label).width;
            ctx.fillStyle = 'rgba(10,10,16,0.85)';
            roundRect(ctx, lx - tw / 2 - 6, ly - 14, tw + 12, 18, 6);
            ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.06)';
            ctx.lineWidth = 0.5;
            roundRect(ctx, lx - tw / 2 - 6, ly - 14, tw + 12, 18, 6);
            ctx.stroke();

            ctx.fillStyle = C.muted;
            ctx.fillText(e.label, lx, ly - 4);
        }
    }

    function drawNode(n) {
        const isHovered = hoveredNode === n.id;
        const isExpanded = n.expanded;
        const r = n.radius + (isHovered ? 4 : 0);

        // Outer glow
        if (n.type === 'hub' || isHovered || isExpanded) {
            const glowR = n.type === 'hub' ? 55 : (n.importance === 'alta' ? 35 : 25);
            const grad = ctx.createRadialGradient(n.x, n.y, r * 0.5, n.x, n.y, r + glowR);
            const alpha = n.type === 'hub' ? '18' : (isHovered ? '15' : '0A');
            grad.addColorStop(0, n.color + alpha);
            grad.addColorStop(1, 'transparent');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(n.x, n.y, r + glowR, 0, Math.PI * 2);
            ctx.fill();
        }

        // Importance ring (alta only, non-hub)
        if (n.importance === 'alta' && n.type !== 'hub') {
            ctx.beginPath();
            ctx.arc(n.x, n.y, r + 5, 0, Math.PI * 2);
            ctx.strokeStyle = n.color + '18';
            ctx.lineWidth = 1.5;
            ctx.setLineDash([2, 3]);
            ctx.stroke();
            ctx.setLineDash([]);
        }

        // Circle body
        ctx.beginPath();
        ctx.arc(n.x, n.y, r, 0, Math.PI * 2);

        // Fill gradient
        const fillGrad = ctx.createRadialGradient(n.x, n.y - r * 0.3, 0, n.x, n.y, r);
        if (n.type === 'hub') {
            fillGrad.addColorStop(0, '#0E0E14');
            fillGrad.addColorStop(1, '#080810');
        } else {
            fillGrad.addColorStop(0, '#101016');
            fillGrad.addColorStop(1, '#0A0A0E');
        }
        ctx.fillStyle = fillGrad;
        ctx.fill();

        // Border
        const borderAlpha = (isHovered || isExpanded) ? '' : '40';
        ctx.strokeStyle = n.color + borderAlpha;
        ctx.lineWidth = n.type === 'hub' ? 2 : (isHovered ? 1.8 : 1);
        ctx.stroke();

        // Hub-specific decorations
        if (n.type === 'hub') {
            // Inner subtle ring
            ctx.beginPath();
            ctx.arc(n.x, n.y, r - 10, 0, Math.PI * 2);
            ctx.strokeStyle = n.color + '0C';
            ctx.lineWidth = 0.8;
            ctx.stroke();

            // Score arc
            drawScoreArc(n.x, n.y, r - 4, data.lead.score);

            // Text
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = 'bold 13px Inter, system-ui, sans-serif';
            ctx.fillStyle = C.text;
            ctx.fillText(trunc(n.title, 16), n.x, n.y - 10);

            ctx.font = '10px Inter, system-ui, sans-serif';
            ctx.fillStyle = C.muted;
            ctx.fillText(n.subtitle || '', n.x, n.y + 5);

            ctx.font = 'bold 10px Inter, system-ui, sans-serif';
            ctx.fillStyle = stageColors[data.lead.stageKey] || C.muted;
            ctx.fillText(data.lead.stage, n.x, n.y + 18);
        } else {
            // Material icon inside node
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const iconSize = n.type === 'cluster' ? 20 : 16;
            ctx.font = iconSize + 'px "Material Symbols Outlined"';
            ctx.fillStyle = (isHovered || isExpanded) ? n.color : n.color + '90';
            ctx.fillText(n.icon, n.x, n.y + 1);

            // Title below node
            ctx.font = '600 ' + (n.type === 'cluster' ? 10.5 : 9.5) + 'px Inter, system-ui, sans-serif';
            ctx.fillStyle = (isHovered || isExpanded) ? n.color : 'rgba(255,255,255,0.6)';
            ctx.fillText(trunc(n.title, 18), n.x, n.y + r + 13);

            // Relation on hover
            if (isHovered && n.relation) {
                ctx.font = '9px Inter, system-ui, sans-serif';
                ctx.fillStyle = C.subtle;
                ctx.fillText(trunc(n.relation, 32), n.x, n.y + r + 25);
            }

            // Item count badge
            if (n.items.length > 0) {
                const bx = n.x + r * 0.55, by = n.y - r * 0.55;
                ctx.beginPath();
                ctx.arc(bx, by, 8, 0, Math.PI * 2);
                ctx.fillStyle = n.color;
                ctx.fill();
                ctx.font = 'bold 7px Inter, system-ui, sans-serif';
                ctx.fillStyle = '#000';
                ctx.fillText(n.items.length, bx, by + 0.5);
            }

            // Importance indicator
            const impColors = { alta: C.lime, media: C.amber, info: C.subtle };
            const ix = n.x - r * 0.55, iy = n.y - r * 0.55;
            ctx.beginPath();
            ctx.arc(ix, iy, 3.5, 0, Math.PI * 2);
            ctx.fillStyle = impColors[n.importance] || C.subtle;
            if (n.importance === 'alta') { ctx.shadowColor = impColors.alta + '80'; ctx.shadowBlur = 5; }
            ctx.fill();
            ctx.shadowBlur = 0;
        }
    }

    function drawScoreArc(x, y, r, score) {
        const start = -Math.PI / 2;
        // Background track
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255,255,255,0.04)';
        ctx.lineWidth = 2.5;
        ctx.stroke();
        // Score fill
        const end = start + (score / 100) * Math.PI * 2;
        ctx.beginPath();
        ctx.arc(x, y, r, start, end);
        ctx.strokeStyle = scoreColor(score);
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.stroke();
        ctx.lineCap = 'butt';
    }

    function drawExpandedPanel(n) {
        if (!n.items.length && !n.relation) return;

        const pw = 270, lineH = 20, headerH = 55;
        const ph = headerH + n.items.length * lineH + 14;
        const maxH = 380;
        const clampH = Math.min(ph, maxH);

        // Position
        let px = n.x + n.radius + 20;
        if ((px + pw) * scale + offsetX > canvasW - 20) px = n.x - n.radius - pw - 20;
        const py = n.y - clampH / 2;

        // Shadow
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        ctx.filter = 'blur(8px)';
        roundRect(ctx, px - 2, py + 2, pw + 4, clampH + 4, 12);
        ctx.fill();
        ctx.filter = 'none';

        // Panel body
        ctx.fillStyle = '#09090E';
        roundRect(ctx, px, py, pw, clampH, 12);
        ctx.fill();
        ctx.strokeStyle = n.color + '20';
        ctx.lineWidth = 1;
        roundRect(ctx, px, py, pw, clampH, 12);
        ctx.stroke();

        // Header accent line
        ctx.fillStyle = n.color + '15';
        ctx.fillRect(px + 1, py + 1, pw - 2, 3);

        // Icon + Title
        ctx.textAlign = 'left';
        ctx.textBaseline = 'top';

        // Material icon
        ctx.font = '15px "Material Symbols Outlined"';
        ctx.fillStyle = n.color + '80';
        ctx.fillText(n.icon, px + 14, py + 15);

        ctx.font = 'bold 11px Inter, system-ui, sans-serif';
        ctx.fillStyle = n.color;
        ctx.fillText(n.title, px + 36, py + 16);

        // Relation
        if (n.relation) {
            ctx.font = '9px Inter, system-ui, sans-serif';
            ctx.fillStyle = C.subtle;
            ctx.fillText(n.relation, px + 14, py + 34);
        }

        // Importance badge
        const impLabels = { alta: 'ALTA', media: 'MÉDIA', info: 'INFO' };
        const impColors = { alta: C.lime, media: C.amber, info: C.subtle };
        const badgeText = impLabels[n.importance] || 'INFO';
        ctx.font = 'bold 7px Inter, system-ui, sans-serif';
        const tw = ctx.measureText(badgeText).width;
        ctx.fillStyle = (impColors[n.importance] || C.subtle) + '15';
        roundRect(ctx, px + pw - tw - 24, py + 14, tw + 12, 15, 4);
        ctx.fill();
        ctx.fillStyle = impColors[n.importance] || C.subtle;
        ctx.fillText(badgeText, px + pw - tw - 18, py + 18);

        // Divider
        ctx.fillStyle = 'rgba(255,255,255,0.04)';
        ctx.fillRect(px + 14, py + headerH - 4, pw - 28, 1);

        // Items
        const maxItems = Math.floor((clampH - headerH - 10) / lineH);
        n.items.slice(0, maxItems).forEach((item, i) => {
            const iy = py + headerH + 4 + i * lineH;

            if (item.label) {
                ctx.font = '9px Inter, system-ui, sans-serif';
                ctx.fillStyle = C.subtle;
                ctx.textAlign = 'left';
                ctx.fillText(item.label, px + 14, iy);

                ctx.font = '10px Inter, system-ui, sans-serif';
                ctx.fillStyle = item.color || C.text;
                ctx.fillText(trunc(String(item.value), 30), px + 98, iy);
            } else {
                ctx.font = '10px Inter, system-ui, sans-serif';
                ctx.fillStyle = item.color || C.text;
                ctx.textAlign = 'left';
                ctx.fillText('›  ' + trunc(String(item.value), 36), px + 14, iy);
            }
        });

        if (n.items.length > maxItems) {
            ctx.font = '9px Inter, system-ui, sans-serif';
            ctx.fillStyle = C.subtle;
            ctx.textAlign = 'left';
            ctx.fillText('+ ' + (n.items.length - maxItems) + ' mais...', px + 14, py + clampH - 12);
        }

        ctx.textAlign = 'center';
    }

    // ── Helpers ────────────────────────────────────────────────
    function roundRect(c, x, y, w, h, r) {
        c.beginPath();
        c.moveTo(x + r, y); c.lineTo(x + w - r, y);
        c.quadraticCurveTo(x + w, y, x + w, y + r);
        c.lineTo(x + w, y + h - r);
        c.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        c.lineTo(x + r, y + h);
        c.quadraticCurveTo(x, y + h, x, y + h - r);
        c.lineTo(x, y + r);
        c.quadraticCurveTo(x, y, x + r, y);
        c.closePath();
    }

    function trunc(s, max) { if (!s) return ''; return s.length > max ? s.substring(0, max - 1) + '…' : s; }
    function fmtDate(d) {
        if (!d) return '—';
        try { return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: '2-digit' }); }
        catch { return d.substring(0, 10); }
    }

    // ── Interaction ───────────────────────────────────────────
    function screenToWorld(sx, sy) { return { x: (sx - offsetX) / scale, y: (sy - offsetY) / scale }; }

    function hitTest(sx, sy) {
        const { x, y } = screenToWorld(sx, sy);
        for (let i = nodes.length - 1; i >= 0; i--) {
            const d = Math.hypot(nodes[i].x - x, nodes[i].y - y);
            if (d <= nodes[i].radius + 8) return nodes[i];
        }
        return null;
    }

    function onMouseDown(e) {
        const rect = canvas.getBoundingClientRect();
        const mx = e.clientX - rect.left, my = e.clientY - rect.top;
        const hit = hitTest(mx, my);
        if (hit) {
            dragNode = hit;
            const w = screenToWorld(mx, my);
            dragStartX = w.x - hit.x;
            dragStartY = w.y - hit.y;
        } else {
            dragging = true;
            panStartX = mx - offsetX;
            panStartY = my - offsetY;
            nodes.forEach(n => n.expanded = false);
        }
    }

    function onMouseMove(e) {
        const rect = canvas.getBoundingClientRect();
        const mx = e.clientX - rect.left, my = e.clientY - rect.top;
        if (dragNode) {
            const w = screenToWorld(mx, my);
            dragNode.x = w.x - dragStartX;
            dragNode.y = w.y - dragStartY;
            canvas.style.cursor = 'grabbing';
        } else if (dragging) {
            offsetX = mx - panStartX;
            offsetY = my - panStartY;
            canvas.style.cursor = 'grabbing';
        } else {
            const hit = hitTest(mx, my);
            hoveredNode = hit ? hit.id : null;
            canvas.style.cursor = hit ? 'pointer' : 'default';
        }
    }

    function onMouseUp() {
        if (dragNode) {
            if (Math.abs(dragNode.x - dragNode.ox) < 4 && Math.abs(dragNode.y - dragNode.oy) < 4) {
                nodes.forEach(n => { if (n.id !== dragNode.id) n.expanded = false; });
                dragNode.expanded = !dragNode.expanded;
            }
        }
        dragNode = null;
        dragging = false;
        canvas.style.cursor = 'default';
    }

    function onWheel(e) {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const mx = e.clientX - rect.left, my = e.clientY - rect.top;
        const factor = e.deltaY > 0 ? 0.92 : 1.08;
        const ns = Math.max(0.25, Math.min(3.5, scale * factor));
        offsetX = mx - (mx - offsetX) * (ns / scale);
        offsetY = my - (my - offsetY) * (ns / scale);
        scale = ns;
    }

    function onDblClick(e) {
        const rect = canvas.getBoundingClientRect();
        const hit = hitTest(e.clientX - rect.left, e.clientY - rect.top);
        if (hit) { offsetX = canvasW / 2 - hit.x * scale; offsetY = H / 2 - hit.y * scale; }
    }

    // Touch
    let lastTouchDist = 0;
    function onTouchStart(e) {
        if (e.touches.length === 1) { e.preventDefault(); onMouseDown({ clientX: e.touches[0].clientX, clientY: e.touches[0].clientY }); }
        else if (e.touches.length === 2) { lastTouchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY); }
    }
    function onTouchMove(e) {
        if (e.touches.length === 1) { e.preventDefault(); onMouseMove({ clientX: e.touches[0].clientX, clientY: e.touches[0].clientY }); }
        else if (e.touches.length === 2) {
            e.preventDefault();
            const d = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
            if (lastTouchDist > 0) scale = Math.max(0.25, Math.min(3.5, scale * (d / lastTouchDist)));
            lastTouchDist = d;
        }
    }
    function onTouchEnd() { lastTouchDist = 0; onMouseUp(); }

    function resetView() { scale = 1; offsetX = 0; offsetY = 0; buildNodes(); }
    function zoomIn() { scale = Math.min(3.5, scale * 1.25); }
    function zoomOut() { scale = Math.max(0.25, scale * 0.8); }

    function updateLegendStats() {
        const el = document.getElementById('legend-stats');
        if (!el) return;
        const totalConnections = edges.length;
        const clusters = nodes.filter(n => n.type === 'cluster').length;
        const leaves = nodes.filter(n => n.type === 'leaf').length;
        const totalItems = nodes.reduce((s, n) => s + n.items.length, 0);
        const highImp = nodes.filter(n => n.importance === 'alta').length;

        const stat = (icon, label, value) =>
            `<div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-white/15 text-[13px]">${icon}</span>
                <span class="text-[10px] text-white/30">${label}</span>
                <span class="text-[10px] text-white/55 font-semibold ml-auto">${value}</span>
            </div>`;

        el.innerHTML = [
            stat('hub', 'Total de nós', nodes.length),
            stat('timeline', 'Conexões', totalConnections),
            stat('category', 'Clusters primários', clusters),
            stat('scatter_plot', 'Nós secundários', leaves),
            stat('database', 'Dados exibidos', totalItems),
            stat('priority_high', 'Alta prioridade', highImp),
        ].join('');
    }

    return { open, close, resetView, zoomIn, zoomOut, toggleLegend };
})();
</script>
