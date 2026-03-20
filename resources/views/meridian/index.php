<?php
$pageTitle    = 'Meridian';
$pageSubtitle = 'Inteligência Estratégica de Nichos';
$historyJson  = json_encode($history ?? [], JSON_UNESCAPED_UNICODE);
?>

<style>
.meridian-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.meridian-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
.score-ring { position: relative; width: 72px; height: 72px; }
.score-ring svg { transform: rotate(-90deg); }
.score-ring .value { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; }
.verdict-badge { animation: pulseGlow 2s ease-in-out infinite; }
@keyframes pulseGlow { 0%,100% { box-shadow: 0 0 0 0 rgba(225,251,21,0.15); } 50% { box-shadow: 0 0 0 8px rgba(225,251,21,0); } }
.analysis-block { opacity: 0; transform: translateY(12px); animation: fadeUp 0.4s forwards; }
@keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
.tab-active { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: white; }
.skeleton { background: linear-gradient(90deg, #1a1a1a 25%, #222 50%, #1a1a1a 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
</style>

<div class="h-full flex overflow-hidden">

    <!-- ── Left Panel (Input + History) ── -->
    <div class="w-80 flex-shrink-0 border-r border-white/5 bg-surface2 flex flex-col h-full overflow-y-auto hide-scrollbar z-10 relative">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="size-10 rounded-xl bg-lime/10 border border-lime/20 flex items-center justify-center text-lime">
                    <span class="material-symbols-outlined shrink-0" style="font-variation-settings: 'FILL' 1;">target</span>
                </div>
                <div>
                    <h2 class="text-sm font-black text-text leading-tight">Meridian</h2>
                    <p class="text-[10px] text-muted">Inteligência de Nichos</p>
                </div>
            </div>

            <?php if (!$hasProfile): ?>
            <!-- No Profile Warning -->
            <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 mb-6">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-amber-400 text-lg mt-0.5">warning</span>
                    <div>
                        <p class="text-xs font-bold text-amber-300 mb-1">Knowledge Base não configurado</p>
                        <p class="text-[11px] text-amber-200/70 leading-relaxed">O Meridian precisa do perfil da sua empresa para gerar análises contextualizadas.</p>
                        <a href="/knowledge" class="inline-flex items-center gap-1 mt-2 text-[11px] font-bold text-lime hover:underline">
                            <span class="material-symbols-outlined text-xs">arrow_forward</span>
                            Configurar agora
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Analysis Form -->
            <form id="meridianForm" class="space-y-5">
                <div class="space-y-4">
                    <h3 class="text-[10px] font-bold text-subtle uppercase tracking-wider mb-2">Análise de Nicho</h3>

                    <div>
                        <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Nicho de Mercado *</label>
                        <input type="text" id="m_niche" placeholder="Ex: Clínicas Odontológicas" required
                               class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Subnicho (opcional)</label>
                        <input type="text" id="m_subniche" placeholder="Ex: Ortodontia, Implantes"
                               class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Região (opcional)</label>
                        <input type="text" id="m_region" placeholder="Ex: São Paulo, Interior de SP"
                               class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Foco especial (opcional)</label>
                        <textarea id="m_focus" rows="2" placeholder="Ex: Quero entender objeções de preço nesse nicho"
                                  class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 transition-all resize-none"></textarea>
                    </div>
                </div>

                <div class="pt-4 sticky bottom-0 bg-surface2 pb-4">
                    <button type="button" id="btnAnalyze" onclick="runAnalysis()"
                            class="w-full h-12 rounded-xl bg-lime text-bg text-sm font-black shadow-glow flex items-center justify-center gap-2 hover:brightness-110 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            <?= !$hasProfile ? 'disabled' : '' ?>>
                        <span class="material-symbols-outlined text-[18px]" id="analyzeIcon">insights</span>
                        <span id="btnAnalyzeLabel">Analisar Nicho</span>
                    </button>
                    <p class="text-[10px] text-center text-subtle mt-3 flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[12px] text-lime">bolt</span>
                        Consome tokens
                    </p>
                </div>
            </form>

            <!-- History -->
            <?php if (!empty($history)): ?>
            <div class="mt-6 pt-6 border-t border-white/5">
                <h3 class="text-[10px] font-bold text-subtle uppercase tracking-wider mb-3">Análises Recentes</h3>
                <div class="space-y-2" id="historyList">
                    <?php foreach ($history as $h): ?>
                    <button onclick="loadHistory('<?= e($h['id']) ?>', '<?= e($h['niche']) ?>')"
                            class="w-full text-left p-3 rounded-xl bg-surface3/50 border border-white/5 hover:border-lime/20 hover:bg-surface3 transition-all group">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-text group-hover:text-lime transition-colors truncate max-w-[160px]"><?= e($h['niche']) ?></span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-bold <?= ($h['adherence_score'] ?? 0) >= 70 ? 'text-emerald-400' : (($h['adherence_score'] ?? 0) >= 40 ? 'text-amber-400' : 'text-red-400') ?>"><?= $h['adherence_score'] ?? 0 ?>%</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (!empty($h['sub_niche'])): ?>
                            <span class="text-[10px] text-muted truncate"><?= e($h['sub_niche']) ?></span>
                            <span class="text-[10px] text-subtle">·</span>
                            <?php endif; ?>
                            <span class="text-[10px] text-subtle"><?= date('d/m H:i', strtotime($h['created_at'])) ?></span>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Main Area (Results) ── -->
    <div class="flex-1 flex flex-col h-full bg-bg relative">
        <!-- Topbar -->
        <div class="h-16 border-b border-white/5 flex items-center justify-between px-6 bg-surface/50 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-lime text-xl">target</span>
                <div>
                    <h1 class="text-sm font-black text-text" id="resultTitle">Meridian — Análise de Nicho</h1>
                    <p class="text-[10px] text-muted" id="resultSubtitle">Selecione um nicho e execute a análise</p>
                </div>
            </div>
            <div class="flex items-center gap-2" id="resultTabs" style="display:none;">
                <button class="tab-btn tab-active px-3 py-1.5 rounded-lg border border-transparent text-xs font-bold text-muted transition-all" onclick="switchResultTab('overview')">Visão Geral</button>
                <button class="tab-btn px-3 py-1.5 rounded-lg border border-transparent text-xs font-bold text-muted hover:text-text transition-all" onclick="switchResultTab('icp')">ICP & Dores</button>
                <button class="tab-btn px-3 py-1.5 rounded-lg border border-transparent text-xs font-bold text-muted hover:text-text transition-all" onclick="switchResultTab('strategy')">Estratégia</button>
                <button class="tab-btn px-3 py-1.5 rounded-lg border border-transparent text-xs font-bold text-muted hover:text-text transition-all" onclick="switchResultTab('action')">Ação</button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 hide-scrollbar" id="resultContent">
            <!-- Empty State -->
            <div id="emptyState" class="flex flex-col items-center justify-center h-full text-center">
                <div class="size-20 rounded-2xl bg-surface2 border border-white/5 flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-4xl text-subtle" style="font-variation-settings: 'FILL' 1;">target</span>
                </div>
                <h2 class="text-lg font-black text-text mb-2">Meridian</h2>
                <p class="text-sm text-muted max-w-md leading-relaxed mb-6">
                    Analise nichos de mercado de forma estratégica, cruzando com o perfil da sua empresa para gerar inteligência comercial contextualizada.
                </p>
                <div class="grid grid-cols-2 gap-3 max-w-lg">
                    <div class="p-3 rounded-xl bg-surface2 border border-white/5 text-left">
                        <span class="material-symbols-outlined text-lime text-sm mb-1">analytics</span>
                        <p class="text-[11px] font-bold text-text">Aderência</p>
                        <p class="text-[10px] text-muted">O nicho faz sentido para sua empresa?</p>
                    </div>
                    <div class="p-3 rounded-xl bg-surface2 border border-white/5 text-left">
                        <span class="material-symbols-outlined text-lime text-sm mb-1">person_search</span>
                        <p class="text-[11px] font-bold text-text">ICP do Nicho</p>
                        <p class="text-[10px] text-muted">Quem é o cliente ideal dentro do nicho</p>
                    </div>
                    <div class="p-3 rounded-xl bg-surface2 border border-white/5 text-left">
                        <span class="material-symbols-outlined text-lime text-sm mb-1">lightbulb</span>
                        <p class="text-[11px] font-bold text-text">Oportunidades</p>
                        <p class="text-[10px] text-muted">Caminhos de entrada e abordagem</p>
                    </div>
                    <div class="p-3 rounded-xl bg-surface2 border border-white/5 text-left">
                        <span class="material-symbols-outlined text-lime text-sm mb-1">shield</span>
                        <p class="text-[11px] font-bold text-text">Objeções</p>
                        <p class="text-[10px] text-muted">Barreiras e como contorná-las</p>
                    </div>
                </div>
            </div>

            <!-- Loading State with Progress Bar -->
            <div id="loadingState" style="display:none;" class="flex flex-col items-center justify-center h-full text-center">
                <div class="size-16 rounded-2xl bg-lime/10 border border-lime/20 flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-3xl text-lime animate-spin" id="loadingIcon">target</span>
                </div>
                <h2 class="text-base font-black text-text mb-2" id="loadingTitle">Iniciando análise...</h2>
                <p class="text-sm text-muted max-w-md leading-relaxed mb-6" id="loadingMessage">Preparando motor de inteligência estratégica.</p>

                <!-- Progress Bar -->
                <div class="w-80 mb-4">
                    <div class="h-2 rounded-full bg-white/5 overflow-hidden">
                        <div id="progressBar" class="h-full rounded-full bg-gradient-to-r from-lime/80 to-lime transition-all duration-700 ease-out" style="width: 0%"></div>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-[10px] text-muted" id="progressStep">Etapa 1 de 8</span>
                        <span class="text-[10px] font-bold text-lime" id="progressPercent">0%</span>
                    </div>
                </div>

                <!-- Status Log -->
                <div class="w-80 mt-2 space-y-1.5" id="statusLog">
                </div>
            </div>

            <!-- Results -->
            <div id="analysisResults" style="display:none;">
                <!-- Tab: Overview -->
                <div id="tab-overview" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 analysis-block" style="animation-delay: 0s;">
                        <div class="col-span-1 md:col-span-1 rounded-2xl border border-white/5 bg-surface2 p-5" id="verdictCard"></div>
                        <div class="rounded-2xl border border-white/5 bg-surface2 p-5" id="adherenceCard"></div>
                        <div class="rounded-2xl border border-white/5 bg-surface2 p-5" id="potentialCard"></div>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.1s;" id="nicheOverviewCard"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 analysis-block" style="animation-delay: 0.15s;">
                        <div class="rounded-2xl border border-white/5 bg-surface2 p-5" id="strengthsCard"></div>
                        <div class="rounded-2xl border border-white/5 bg-surface2 p-5" id="gapsCard"></div>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.2s;" id="financialCard"></div>
                </div>

                <!-- Tab: ICP & Dores -->
                <div id="tab-icp" class="space-y-6" style="display:none;">
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" id="icpCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.1s;" id="painPointsCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.15s;" id="objectionsCard"></div>
                </div>

                <!-- Tab: Estratégia -->
                <div id="tab-strategy" class="space-y-6" style="display:none;">
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" id="valuePropositionCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.1s;" id="positioningCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.15s;" id="competitiveCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.2s;" id="subNichesCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.25s;" id="barriersCard"></div>
                </div>

                <!-- Tab: Ação -->
                <div id="tab-action" class="space-y-6" style="display:none;">
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" id="entryOpportunitiesCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.1s;" id="channelsCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.15s;" id="approachCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.2s;" id="quickWinsCard"></div>
                    <div class="rounded-2xl border border-white/5 bg-surface2 p-5 analysis-block" style="animation-delay: 0.25s;" id="nextStepsCard"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
let currentAnalysis = null;

// ── Progress Steps ──
const PROGRESS_STEPS = [
    { pct: 5,  icon: 'hub',              title: 'Conectando ao motor de IA...',              msg: 'Inicializando provedor de inteligência artificial.' },
    { pct: 12, icon: 'database',          title: 'Carregando perfil da empresa...',           msg: 'Recuperando identidade, serviços, diferenciais e cases.' },
    { pct: 22, icon: 'search',            title: 'Recuperando contexto estratégico...',       msg: 'Buscando chunks relevantes na Knowledge Base (RAG).' },
    { pct: 30, icon: 'psychology',         title: 'Construindo prompt estratégico...',         msg: 'Cruzando perfil da empresa com o nicho selecionado.' },
    { pct: 40, icon: 'neurology',          title: 'IA analisando nicho...',                   msg: 'Gerando análise de mercado, aderência e potencial.' },
    { pct: 55, icon: 'analytics',          title: 'Mapeando dores e oportunidades...',        msg: 'Identificando pain points, ICP ideal e sinais de compra.' },
    { pct: 70, icon: 'strategy',           title: 'Elaborando estratégia comercial...',       msg: 'Definindo proposta de valor, canais e abordagem.' },
    { pct: 82, icon: 'account_tree',       title: 'Finalizando cenário competitivo...',       msg: 'Analisando concorrentes, barreiras e subnichos.' },
    { pct: 92, icon: 'fact_check',         title: 'Montando plano de ação...',                msg: 'Calculando análise financeira e quick wins.' },
];

let progressTimer = null;
let progressStopped = false;

function startProgress() {
    progressStopped = false;
    document.getElementById('statusLog').innerHTML = '';
    updateProgress(0, 'Iniciando análise...', 'Preparando motor de inteligência estratégica.', 'target', 'Etapa 0 de 9');

    // Variable delays: first steps faster, AI processing steps slower
    const delays = [1200, 1500, 2000, 2500, 8000, 8000, 6000, 5000, 3000];

    function runStep(idx) {
        if (progressStopped || idx >= PROGRESS_STEPS.length) return;
        progressTimer = setTimeout(() => {
            if (progressStopped) return;
            const step = PROGRESS_STEPS[idx];
            addStatusLog(step.icon, step.msg);
            updateProgress(step.pct, step.title, step.msg, step.icon, `Etapa ${idx + 1} de 9`);
            runStep(idx + 1);
        }, delays[idx] || 3000);
    }

    runStep(0);
}

function updateProgress(pct, title, msg, icon, stepLabel) {
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressPercent').textContent = pct + '%';
    document.getElementById('loadingTitle').textContent = title;
    document.getElementById('loadingMessage').textContent = msg;
    document.getElementById('loadingIcon').textContent = icon;
    document.getElementById('progressStep').textContent = stepLabel;
}

function addStatusLog(icon, msg) {
    const log = document.getElementById('statusLog');
    const entry = document.createElement('div');
    entry.className = 'flex items-center gap-2 text-left opacity-0 animate-fadeInUp';
    entry.style.animationDuration = '0.3s';
    entry.style.animationFillMode = 'forwards';
    entry.innerHTML = `<span class="material-symbols-outlined text-lime text-xs shrink-0">check_circle</span><span class="text-[10px] text-muted">${msg}</span>`;
    log.appendChild(entry);

    // Keep only the last 5 entries visible
    const entries = log.querySelectorAll('div');
    if (entries.length > 5) {
        entries[0].remove();
    }
}

function completeProgress() {
    progressStopped = true;
    if (progressTimer) { clearTimeout(progressTimer); progressTimer = null; }
    updateProgress(100, 'Análise concluída!', 'Relatório estratégico pronto. Abrindo resultados...', 'check_circle', 'Concluído');
    addStatusLog('verified', 'Relatório estratégico gerado com sucesso.');
    document.getElementById('loadingIcon').classList.remove('animate-spin');
}

function resetProgress() {
    progressStopped = true;
    if (progressTimer) { clearTimeout(progressTimer); progressTimer = null; }
}

// ── Run Analysis ──
async function runAnalysis() {
    const niche = document.getElementById('m_niche').value.trim();
    if (!niche) return;

    const btn = document.getElementById('btnAnalyze');
    const icon = document.getElementById('analyzeIcon');
    const label = document.getElementById('btnAnalyzeLabel');

    btn.disabled = true;
    icon.textContent = 'progress_activity';
    icon.classList.add('animate-spin');
    label.textContent = 'Analisando...';

    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('analysisResults').style.display = 'none';
    document.getElementById('loadingState').style.display = 'flex';

    // Start animated progress
    startProgress();

    try {
        const res = await fetch('/meridian/analyze', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                _csrf: CSRF,
                niche: niche,
                sub_niche: document.getElementById('m_subniche').value.trim(),
                region: document.getElementById('m_region').value.trim(),
                focus: document.getElementById('m_focus').value.trim(),
            })
        });
        const data = await res.json();

        if (data.error) {
            resetProgress();
            showToast(data.message || 'Erro na análise', 'error');
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('emptyState').style.display = 'flex';
            return;
        }

        // Complete progress to 100%
        completeProgress();

        currentAnalysis = data.analysis;

        // Wait a moment at 100% then show results
        setTimeout(() => {
            renderAnalysis(data.analysis, niche);
        }, 1200);

    } catch (e) {
        resetProgress();
        showToast('Erro de conexão. Tente novamente.', 'error');
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('emptyState').style.display = 'flex';
    } finally {
        btn.disabled = false;
        icon.textContent = 'insights';
        icon.classList.remove('animate-spin');
        label.textContent = 'Analisar Nicho';
    }
}

// ── Load from History ──
async function loadHistory(id, niche) {
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('analysisResults').style.display = 'none';
    document.getElementById('loadingState').style.display = 'flex';

    try {
        const res = await fetch('/meridian/history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            currentAnalysis = data.analysis;
            renderAnalysis(data.analysis, data.niche || niche);
        } else {
            showToast(data.message || 'Erro ao carregar análise', 'error');
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('emptyState').style.display = 'flex';
        }
    } catch (e) {
        showToast('Erro de conexão', 'error');
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('emptyState').style.display = 'flex';
    }
}

// ── Render Analysis ──
function renderAnalysis(a, niche) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('resultTabs').style.display = 'flex';
    document.getElementById('resultTitle').textContent = niche;
    document.getElementById('resultSubtitle').textContent = 'Análise estratégica contextualizada';

    switchResultTab('overview');

    const sevColors = { alta: 'red', média: 'amber', baixa: 'emerald' };
    const effColors = { alta: 'emerald', média: 'amber', baixa: 'red' };
    const potColors = { alto: 'emerald', médio: 'amber', baixo: 'red' };
    const diffColors = { 'fácil': 'emerald', 'média': 'amber', 'difícil': 'red' };
    const matColors = { baixa: 'red', média: 'amber', alta: 'emerald' };
    const compColors = { baixa: 'emerald', média: 'amber', alta: 'red' };
    const trendIcons = { crescente: 'trending_up', estável: 'trending_flat', decrescente: 'trending_down' };
    const trendColors = { crescente: 'emerald', estável: 'amber', decrescente: 'red' };
    const confColors = { alta: 'emerald', média: 'amber', baixa: 'red' };

    // ── Verdict ──
    const v = a.verdict || {};
    const recColors = { atacar: 'emerald', explorar: 'amber', evitar: 'red', monitorar: 'blue' };
    const recLabels = { atacar: 'Atacar', explorar: 'Explorar', evitar: 'Evitar', monitorar: 'Monitorar' };
    const recIcons  = { atacar: 'rocket_launch', explorar: 'explore', evitar: 'block', monitorar: 'visibility' };
    const rec = (v.recommendation || 'explorar').toLowerCase();
    const rc = recColors[rec] || 'amber';
    document.getElementById('verdictCard').innerHTML = `
        <div class="flex flex-col items-center text-center">
            <div class="verdict-badge size-14 rounded-2xl bg-${rc}-500/10 border border-${rc}-500/30 flex items-center justify-center mb-3">
                <span class="material-symbols-outlined text-2xl text-${rc}-400" style="font-variation-settings: 'FILL' 1;">${recIcons[rec] || 'explore'}</span>
            </div>
            <span class="text-xs font-black uppercase tracking-wider text-${rc}-400 mb-1">${recLabels[rec] || rec}</span>
            ${v.confidence ? `<span class="text-[10px] text-${confColors[v.confidence] || 'muted'}-400 mb-2">Confiança: ${v.confidence}</span>` : ''}
            <p class="text-[11px] text-muted leading-relaxed mb-2">${v.summary || ''}</p>
            ${v.one_liner ? `<p class="text-[10px] font-bold text-text italic">"${v.one_liner}"</p>` : ''}
        </div>
    `;

    // ── Scores ──
    const adh = a.adherence || {};
    renderScoreCard('adherenceCard', 'Aderência', adh.score || 0, adh.reasoning || '');
    const pot = a.potential || {};
    renderScoreCard('potentialCard', 'Potencial', pot.score || 0, pot.reasoning || '');

    // ── Niche Overview (expanded) ──
    const ov = a.niche_overview || {};
    document.getElementById('nicheOverviewCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">info</span>
            <h3 class="text-sm font-black text-text">Visão Geral do Nicho</h3>
        </div>
        <p class="text-sm text-muted leading-relaxed mb-4">${ov.description || ''}</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <p class="text-[10px] text-subtle uppercase font-bold mb-1">Mercado</p>
                <p class="text-xs font-bold text-text">${ov.market_size || 'N/D'}</p>
            </div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <p class="text-[10px] text-subtle uppercase font-bold mb-1">Tendência</p>
                <p class="text-xs font-bold text-${trendColors[ov.growth_trend] || 'muted'}-400 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">${trendIcons[ov.growth_trend] || 'trending_flat'}</span>
                    ${ov.growth_trend || 'N/D'}
                </p>
            </div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <p class="text-[10px] text-subtle uppercase font-bold mb-1">Maturidade Digital</p>
                <p class="text-xs font-bold text-${matColors[ov.digital_maturity] || 'muted'}-400">${ov.digital_maturity || 'N/D'}</p>
            </div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <p class="text-[10px] text-subtle uppercase font-bold mb-1">Concorrência</p>
                <p class="text-xs font-bold text-${compColors[ov.competition_level] || 'muted'}-400">${ov.competition_level || 'N/D'}</p>
            </div>
        </div>
        <div class="space-y-3">
            ${ov.growth_reasoning ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Análise de Crescimento</p><p class="text-xs text-muted leading-relaxed">${ov.growth_reasoning}</p></div>` : ''}
            ${ov.digital_maturity_detail ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Detalhamento Digital</p><p class="text-xs text-muted leading-relaxed">${ov.digital_maturity_detail}</p></div>` : ''}
            ${ov.competition_detail ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Cenário Competitivo</p><p class="text-xs text-muted leading-relaxed">${ov.competition_detail}</p></div>` : ''}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                ${ov.average_ticket ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Ticket Médio</p><p class="text-xs font-bold text-lime">${ov.average_ticket}</p></div>` : ''}
                ${ov.seasonality ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Sazonalidade</p><p class="text-xs text-muted">${ov.seasonality}</p></div>` : ''}
                ${ov.regulation_notes ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Regulamentação</p><p class="text-xs text-muted">${ov.regulation_notes}</p></div>` : ''}
            </div>
        </div>
    `;

    // ── Strengths & Fit Factors ──
    const strengths = adh.strengths || [];
    const fitFactors = adh.fit_factors || [];
    document.getElementById('strengthsCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-emerald-400 text-lg">thumb_up</span>
            <h3 class="text-sm font-black text-text">Pontos Fortes</h3>
        </div>
        <div class="space-y-2 mb-4">
            ${strengths.map(s => `<div class="flex items-start gap-2 p-2.5 rounded-lg bg-emerald-500/5 border border-emerald-500/10"><span class="material-symbols-outlined text-emerald-400 text-sm mt-0.5 shrink-0">check_circle</span><p class="text-xs text-muted leading-relaxed">${s}</p></div>`).join('')}
        </div>
        ${fitFactors.length ? `
        <div class="pt-3 border-t border-white/5">
            <p class="text-[10px] font-bold text-subtle uppercase mb-2">Fatores de Encaixe</p>
            <div class="space-y-1.5">
                ${fitFactors.map(f => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-lime text-sm shrink-0">verified</span>${f}</div>`).join('')}
            </div>
        </div>` : ''}
    `;

    // ── Gaps ──
    const gaps = adh.gaps || [];
    document.getElementById('gapsCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-amber-400 text-lg">warning</span>
            <h3 class="text-sm font-black text-text">Lacunas & Riscos</h3>
        </div>
        <div class="space-y-2">
            ${gaps.map(g => `<div class="flex items-start gap-2 p-2.5 rounded-lg bg-amber-500/5 border border-amber-500/10"><span class="material-symbols-outlined text-amber-400 text-sm mt-0.5 shrink-0">flag</span><p class="text-xs text-muted leading-relaxed">${g}</p></div>`).join('')}
        </div>
    `;

    // ── Financial Analysis (NEW) ──
    const fin = a.financial_analysis || {};
    document.getElementById('financialCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">payments</span>
            <h3 class="text-sm font-black text-text">Análise Financeira</h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            ${fin.estimated_cac ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">CAC Estimado</p><p class="text-xs font-bold text-text">${fin.estimated_cac}</p></div>` : ''}
            ${fin.estimated_ltv ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">LTV Estimado</p><p class="text-xs font-bold text-lime">${fin.estimated_ltv}</p></div>` : ''}
            ${fin.payback_period ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Payback</p><p class="text-xs font-bold text-text">${fin.payback_period}</p></div>` : ''}
            ${fin.margin_potential ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Margem</p><p class="text-xs font-bold text-text">${fin.margin_potential}</p></div>` : ''}
        </div>
        ${fin.pricing_strategy ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Estratégia de Preço</p><p class="text-xs text-muted leading-relaxed">${fin.pricing_strategy}</p></div>` : ''}
        ${pot.revenue_estimate ? `<div class="p-3 rounded-xl bg-lime/5 border border-lime/20 mt-3"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Potencial de Receita</p><p class="text-xs font-bold text-lime leading-relaxed">${pot.revenue_estimate}</p></div>` : ''}
        ${pot.time_to_first_deal ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5 mt-3"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Tempo até 1o Fechamento</p><p class="text-xs text-muted leading-relaxed">${pot.time_to_first_deal}</p></div>` : ''}
    `;

    // ── ICP (expanded) ──
    const icp = a.ideal_icp || {};
    document.getElementById('icpCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">person_search</span>
            <h3 class="text-sm font-black text-text">Cliente Ideal no Nicho</h3>
        </div>
        <p class="text-sm text-muted leading-relaxed mb-4">${icp.profile || ''}</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Porte</p><p class="text-xs font-bold text-text">${icp.company_size || 'N/D'}</p></div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Faturamento</p><p class="text-xs font-bold text-text">${icp.revenue_range || 'N/D'}</p></div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Decisor</p><p class="text-xs font-bold text-text">${icp.decision_maker || 'N/D'}</p></div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Budget</p><p class="text-xs font-bold text-text">${icp.budget_range || 'N/D'}</p></div>
        </div>
        ${(icp.influencers || []).length ? `<div class="mb-4"><p class="text-[10px] font-bold text-subtle uppercase mb-2">Influenciadores da Decisão</p><div class="flex flex-wrap gap-2">${(icp.influencers || []).map(i => `<span class="text-[10px] px-2.5 py-1 rounded-full bg-surface3 border border-white/5 text-muted">${i}</span>`).join('')}</div></div>` : ''}
        ${(icp.buying_signals || []).length ? `<div class="mb-4"><p class="text-[10px] font-bold text-subtle uppercase mb-2">Sinais de Compra</p><div class="space-y-1.5">${(icp.buying_signals || []).map(s => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-emerald-400 text-sm shrink-0">signal_cellular_alt</span>${s}</div>`).join('')}</div></div>` : ''}
        ${(icp.red_flags || []).length ? `<div class="mb-4"><p class="text-[10px] font-bold text-subtle uppercase mb-2">Red Flags (Evitar)</p><div class="space-y-1.5">${(icp.red_flags || []).map(r => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-red-400 text-sm shrink-0">do_not_disturb_on</span>${r}</div>`).join('')}</div></div>` : ''}
        ${(icp.where_they_are || []).length ? `<div><p class="text-[10px] font-bold text-subtle uppercase mb-2">Onde Encontrá-los</p><div class="space-y-1.5">${(icp.where_they_are || []).map(w => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-lime text-sm shrink-0">location_on</span>${w}</div>`).join('')}</div></div>` : ''}
    `;

    // ── Pain Points (expanded) ──
    const pains = a.pain_points || [];
    document.getElementById('painPointsCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-red-400 text-lg">healing</span>
            <h3 class="text-sm font-black text-text">Dores do Nicho</h3>
            <span class="text-[10px] text-muted ml-auto">${pains.length} dores mapeadas</span>
        </div>
        <div class="space-y-3">
            ${pains.map(p => `
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-xs font-bold text-text">${p.pain || ''}</p>
                    <div class="flex items-center gap-2">
                        ${p.frequency ? `<span class="text-[10px] text-subtle">${p.frequency}</span>` : ''}
                        <span class="text-[10px] font-bold uppercase text-${sevColors[p.severity] || 'muted'}-400 px-2 py-0.5 rounded-full bg-${sevColors[p.severity] || 'muted'}-500/10">${p.severity || ''}</span>
                    </div>
                </div>
                ${p.impact ? `<p class="text-[10px] text-red-300/70 mb-1.5"><span class="font-bold">Impacto:</span> ${p.impact}</p>` : ''}
                <p class="text-[11px] text-muted leading-relaxed"><span class="text-lime font-bold">Solução:</span> ${p.company_solution || ''}</p>
            </div>`).join('')}
        </div>
    `;

    // ── Objections (expanded) ──
    const objections = a.objections || [];
    document.getElementById('objectionsCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-amber-400 text-lg">shield</span>
            <h3 class="text-sm font-black text-text">Objeções Prováveis</h3>
            <span class="text-[10px] text-muted ml-auto">${objections.length} objeções mapeadas</span>
        </div>
        <div class="space-y-3">
            ${objections.map(o => `
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-xs font-bold text-red-300">"${o.objection || ''}"</p>
                    <div class="flex items-center gap-2">
                        ${o.frequency ? `<span class="text-[10px] text-subtle">${o.frequency}</span>` : ''}
                        ${o.severity ? `<span class="text-[10px] font-bold uppercase text-${sevColors[o.severity] || 'muted'}-400">${o.severity}</span>` : ''}
                    </div>
                </div>
                <p class="text-[11px] text-muted leading-relaxed mb-1"><span class="text-emerald-400 font-bold">Contra-argumento:</span> ${o.counter || ''}</p>
                ${o.prevention ? `<p class="text-[10px] text-subtle leading-relaxed"><span class="font-bold text-muted">Prevenção:</span> ${o.prevention}</p>` : ''}
            </div>`).join('')}
        </div>
    `;

    // ── Value Proposition (expanded) ──
    const vp = a.value_proposition || {};
    document.getElementById('valuePropositionCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">diamond</span>
            <h3 class="text-sm font-black text-text">Proposta de Valor</h3>
        </div>
        <div class="p-4 rounded-xl bg-lime/5 border border-lime/20 mb-4">
            <p class="text-sm font-bold text-lime leading-relaxed">${vp.main_argument || ''}</p>
        </div>
        ${vp.elevator_pitch ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5 mb-4"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Elevator Pitch (30s)</p><p class="text-xs text-text leading-relaxed italic">"${vp.elevator_pitch}"</p></div>` : ''}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><p class="text-[10px] font-bold text-subtle uppercase mb-2">Argumentos de Apoio</p><div class="space-y-1.5">${(vp.supporting_points || []).map(p => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-lime text-sm shrink-0">arrow_right</span>${p}</div>`).join('')}</div></div>
            <div><p class="text-[10px] font-bold text-subtle uppercase mb-2">Provas & Cases</p><div class="space-y-1.5">${(vp.proof_elements || []).map(p => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-emerald-400 text-sm shrink-0">verified</span>${p}</div>`).join('')}</div></div>
        </div>
        ${(vp.emotional_triggers || []).length ? `<div><p class="text-[10px] font-bold text-subtle uppercase mb-2">Gatilhos Emocionais</p><div class="flex flex-wrap gap-2">${(vp.emotional_triggers || []).map(t => `<span class="text-[10px] px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">${t}</span>`).join('')}</div></div>` : ''}
    `;

    // ── Positioning (expanded) ──
    const pos = a.positioning || {};
    document.getElementById('positioningCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">flag</span>
            <h3 class="text-sm font-black text-text">Posicionamento</h3>
        </div>
        <div class="space-y-3">
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Ângulo Recomendado</p><p class="text-xs text-text leading-relaxed">${pos.recommended_angle || ''}</p></div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Diferenciação</p><p class="text-xs text-text leading-relaxed">${pos.differentiation || ''}</p></div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Caminho de Autoridade</p><p class="text-xs text-text leading-relaxed">${pos.authority_path || ''}</p></div>
            ${pos.brand_perception ? `<div class="p-3 rounded-xl bg-lime/5 border border-lime/20"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Percepção Desejada</p><p class="text-xs font-bold text-lime leading-relaxed">${pos.brand_perception}</p></div>` : ''}
        </div>
        ${(pos.content_pillars || []).length ? `<div class="mt-4 pt-3 border-t border-white/5"><p class="text-[10px] font-bold text-subtle uppercase mb-2">Pilares de Conteúdo</p><div class="flex flex-wrap gap-2">${(pos.content_pillars || []).map(p => `<span class="text-[10px] px-2.5 py-1 rounded-full bg-surface3 border border-white/10 text-text">${p}</span>`).join('')}</div></div>` : ''}
    `;

    // ── Competitive Landscape (NEW) ──
    const comp = a.competitive_landscape || {};
    document.getElementById('competitiveCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-amber-400 text-lg">groups</span>
            <h3 class="text-sm font-black text-text">Cenário Competitivo</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-[10px] font-bold text-subtle uppercase mb-2">Concorrentes Diretos</p>
                <div class="space-y-1.5">${(comp.direct_competitors || []).map(c => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-red-400 text-sm shrink-0">person</span>${c}</div>`).join('')}</div>
            </div>
            <div>
                <p class="text-[10px] font-bold text-subtle uppercase mb-2">Concorrentes Indiretos</p>
                <div class="space-y-1.5">${(comp.indirect_competitors || []).map(c => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-amber-400 text-sm shrink-0">swap_horiz</span>${c}</div>`).join('')}</div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-3 border-t border-white/5">
            <div>
                <p class="text-[10px] font-bold text-subtle uppercase mb-2">Suas Vantagens</p>
                <div class="space-y-1.5">${(comp.competitive_advantages || []).map(c => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-emerald-400 text-sm shrink-0">shield</span>${c}</div>`).join('')}</div>
            </div>
            <div>
                <p class="text-[10px] font-bold text-subtle uppercase mb-2">Pontos de Vulnerabilidade</p>
                <div class="space-y-1.5">${(comp.vulnerability_points || []).map(c => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-red-400 text-sm shrink-0">warning</span>${c}</div>`).join('')}</div>
            </div>
        </div>
    `;

    // ── Sub-niches (expanded) ──
    const subs = a.sub_niches || [];
    document.getElementById('subNichesCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">account_tree</span>
            <h3 class="text-sm font-black text-text">Subnichos</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            ${subs.map(s => `
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-bold text-text">${s.name || ''}</p>
                    <span class="text-[10px] font-bold uppercase text-${potColors[s.potential] || 'muted'}-400">${s.potential || ''}</span>
                </div>
                ${s.size ? `<p class="text-[10px] text-subtle mb-1">${s.size}</p>` : ''}
                <p class="text-[11px] text-muted leading-relaxed">${s.why || ''}</p>
                ${s.specific_angle ? `<p class="text-[10px] text-lime mt-1.5"><span class="font-bold">Ângulo:</span> ${s.specific_angle}</p>` : ''}
            </div>`).join('')}
        </div>
    `;

    // ── Barriers (expanded) ──
    const barriers = a.barriers || [];
    const typeLabels = { técnica: 'TEC', comercial: 'COM', cultural: 'CUL', regulatória: 'REG', financeira: 'FIN' };
    document.getElementById('barriersCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-red-400 text-lg">block</span>
            <h3 class="text-sm font-black text-text">Barreiras de Entrada</h3>
        </div>
        <div class="space-y-3">
            ${barriers.map(b => `
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                        ${b.type ? `<span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-white/5 text-subtle">${typeLabels[b.type] || b.type}</span>` : ''}
                        <p class="text-xs font-bold text-text">${b.barrier || ''}</p>
                    </div>
                    <span class="text-[10px] font-bold uppercase text-${sevColors[b.severity] || 'muted'}-400">${b.severity || ''}</span>
                </div>
                <p class="text-[11px] text-muted leading-relaxed"><span class="text-lime font-bold">Mitigação:</span> ${b.mitigation || ''}</p>
                ${b.timeline ? `<p class="text-[10px] text-subtle mt-1"><span class="font-bold">Prazo:</span> ${b.timeline}</p>` : ''}
            </div>`).join('')}
        </div>
    `;

    // ── Entry Opportunities (expanded) ──
    const opps = a.entry_opportunities || [];
    document.getElementById('entryOpportunitiesCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-emerald-400 text-lg">lightbulb</span>
            <h3 class="text-sm font-black text-text">Oportunidades de Entrada</h3>
        </div>
        <div class="space-y-3">
            ${opps.map(o => `
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-xs font-bold text-text">${o.opportunity || ''}</p>
                    <span class="text-[10px] font-bold uppercase text-${diffColors[o.difficulty] || 'muted'}-400">${o.difficulty || ''}</span>
                </div>
                <p class="text-[11px] text-muted leading-relaxed mb-1">${o.approach || ''}</p>
                <div class="flex items-center gap-4 mt-2">
                    ${o.investment ? `<p class="text-[10px] text-subtle"><span class="font-bold">Investimento:</span> ${o.investment}</p>` : ''}
                    ${o.expected_return ? `<p class="text-[10px] text-lime"><span class="font-bold">Retorno:</span> ${o.expected_return}</p>` : ''}
                </div>
            </div>`).join('')}
        </div>
    `;

    // ── Channels (expanded) ──
    const channels = a.channels || [];
    document.getElementById('channelsCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">hub</span>
            <h3 class="text-sm font-black text-text">Canais de Entrada</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            ${channels.map(c => `
            <div class="p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-bold text-text">${c.channel || ''}</p>
                    <div class="flex items-center gap-2">
                        ${c.cost ? `<span class="text-[10px] text-subtle">Custo: ${c.cost}</span>` : ''}
                        <span class="text-[10px] font-bold uppercase text-${effColors[c.effectiveness] || 'muted'}-400">${c.effectiveness || ''}</span>
                    </div>
                </div>
                <p class="text-[11px] text-muted leading-relaxed">${c.approach_tip || ''}</p>
                ${c.best_for ? `<p class="text-[10px] text-subtle mt-1"><span class="font-bold">Melhor para:</span> ${c.best_for}</p>` : ''}
            </div>`).join('')}
        </div>
    `;

    // ── Approach Strategy (expanded) ──
    const appr = a.approach_strategy || {};
    document.getElementById('approachCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">record_voice_over</span>
            <h3 class="text-sm font-black text-text">Estratégia de Abordagem</h3>
        </div>
        <div class="space-y-3 mb-4">
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Tipo de Abordagem</p><p class="text-xs text-text">${appr.recommended_type || ''}</p></div>
            <div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Ângulo de Abertura</p><p class="text-xs text-text leading-relaxed">${appr.opening_angle || ''}</p></div>
            <div class="p-4 rounded-xl bg-lime/5 border border-lime/20"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Mensagem-Chave</p><p class="text-sm font-bold text-lime leading-relaxed">${appr.key_message || ''}</p></div>
            ${appr.first_contact_script ? `<div class="p-3 rounded-xl bg-surface3 border border-lime/10"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Modelo de 1a Mensagem</p><p class="text-xs text-text leading-relaxed italic">"${appr.first_contact_script}"</p></div>` : ''}
            ${appr.nurturing_strategy ? `<div class="p-3 rounded-xl bg-surface3 border border-white/5"><p class="text-[10px] text-subtle uppercase font-bold mb-1">Estratégia de Nutrição</p><p class="text-xs text-muted leading-relaxed">${appr.nurturing_strategy}</p></div>` : ''}
        </div>
        ${(appr.content_ideas || []).length ? `<div class="mb-4"><p class="text-[10px] font-bold text-subtle uppercase mb-2">Ideias de Conteúdo</p><div class="space-y-1.5">${(appr.content_ideas || []).map(c => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-lime text-sm shrink-0">edit_note</span>${c}</div>`).join('')}</div></div>` : ''}
        ${(appr.closing_tips || []).length ? `<div class="pt-3 border-t border-white/5"><p class="text-[10px] font-bold text-subtle uppercase mb-2">Dicas de Fechamento</p><div class="space-y-1.5">${(appr.closing_tips || []).map(t => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-emerald-400 text-sm shrink-0">handshake</span>${t}</div>`).join('')}</div></div>` : ''}
    `;

    // ── Quick Wins (NEW) ──
    const qw = a.quick_wins || [];
    const effortColors = { baixo: 'emerald', médio: 'amber' };
    document.getElementById('quickWinsCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-emerald-400 text-lg">bolt</span>
            <h3 class="text-sm font-black text-text">Quick Wins (7 dias)</h3>
        </div>
        <div class="space-y-3">
            ${qw.map((q, i) => `
            <div class="flex items-start gap-3 p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="size-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-[10px] font-black text-emerald-400">${i+1}</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs font-bold text-text mb-1">${q.action || ''}</p>
                    <p class="text-[11px] text-muted">${q.expected_result || ''}</p>
                </div>
                ${q.effort ? `<span class="text-[10px] font-bold uppercase text-${effortColors[q.effort] || 'muted'}-400 shrink-0">${q.effort}</span>` : ''}
            </div>`).join('')}
        </div>
    `;

    // ── Next Steps + 90-day Plan + Priority Signals ──
    const steps = v.next_steps || [];
    const signals = a.priority_signals || [];
    document.getElementById('nextStepsCard').innerHTML = `
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lime text-lg">rocket_launch</span>
            <h3 class="text-sm font-black text-text">Próximos Passos & Plano de Ação</h3>
        </div>
        <div class="space-y-2 mb-4">
            ${steps.map((s, i) => `
            <div class="flex items-start gap-3 p-3 rounded-xl bg-surface3 border border-white/5">
                <div class="size-6 rounded-lg bg-lime/10 border border-lime/20 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-[10px] font-black text-lime">${i+1}</span>
                </div>
                <p class="text-xs text-text leading-relaxed">${s}</p>
            </div>`).join('')}
        </div>
        ${v['90_day_plan'] ? `
        <div class="p-4 rounded-xl bg-lime/5 border border-lime/20 mb-4">
            <p class="text-[10px] text-subtle uppercase font-bold mb-2">Plano de 90 Dias</p>
            <p class="text-xs text-text leading-relaxed whitespace-pre-line">${v['90_day_plan']}</p>
        </div>` : ''}
        ${signals.length ? `
        <div class="pt-4 border-t border-white/5">
            <p class="text-[10px] font-bold text-subtle uppercase mb-3">Sinais de Prioridade</p>
            <div class="space-y-1.5">
                ${signals.map(s => `<div class="flex items-start gap-2 text-xs text-muted"><span class="material-symbols-outlined text-amber-400 text-sm shrink-0">priority_high</span>${s}</div>`).join('')}
            </div>
        </div>` : ''}
    `;

    document.getElementById('analysisResults').style.display = 'block';
}

// ── Score Card Helper ──
function renderScoreCard(containerId, label, score, reasoning, icon) {
    const color = score >= 70 ? 'emerald' : (score >= 40 ? 'amber' : 'red');
    const circumference = 2 * Math.PI * 28;
    const offset = circumference - (score / 100) * circumference;

    document.getElementById(containerId).innerHTML = `
        <div class="flex flex-col items-center text-center">
            <div class="score-ring mb-3">
                <svg width="72" height="72">
                    <circle cx="36" cy="36" r="28" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="5"/>
                    <circle cx="36" cy="36" r="28" fill="none" stroke="var(--tw-${color === 'emerald' ? 'emerald' : color}-400, ${color === 'emerald' ? '#34d399' : color === 'amber' ? '#fbbf24' : '#f87171'})"
                        stroke-width="5" stroke-linecap="round"
                        stroke-dasharray="${circumference}" stroke-dashoffset="${offset}"
                        style="transition: stroke-dashoffset 1s ease;"/>
                </svg>
                <span class="value text-${color}-400">${score}</span>
            </div>
            <p class="text-xs font-black text-text mb-1">${label}</p>
            <p class="text-[10px] text-muted leading-relaxed">${reasoning}</p>
        </div>
    `;
}

// ── Tab Switching ──
function switchResultTab(tab) {
    ['overview', 'icp', 'strategy', 'action'].forEach(t => {
        const el = document.getElementById('tab-' + t);
        if (el) el.style.display = t === tab ? 'block' : 'none';
    });

    document.querySelectorAll('#resultTabs .tab-btn').forEach((btn, i) => {
        const tabs = ['overview', 'icp', 'strategy', 'action'];
        btn.classList.toggle('tab-active', tabs[i] === tab);
    });
}

// ── Toast ──
function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-[200] flex items-center gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-lg backdrop-blur-sm animate-fadeInUp ${type === 'error' ? 'flash-error' : 'flash-success'}`;
    toast.innerHTML = `<span class="material-symbols-outlined text-lg">${type === 'error' ? 'error' : 'check_circle'}</span>${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
</script>
