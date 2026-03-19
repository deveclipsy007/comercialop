<?php
$pageTitle    = 'Hunter Protocol';
$pageSubtitle = 'Máquina de Prospecção Inteligente';

// Load saved results
$savedResultsJson = json_encode($savedResults ?? []);

?>

<style>
/* Drawer styles & smooth transitions */
.hunter-drawer {
    transform: translateX(100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.hunter-drawer.open {
    transform: translateX(0);
}
.hunter-drawer-backdrop {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.hunter-drawer-backdrop.open {
    opacity: 1;
    pointer-events: auto;
}
.tab-btn.active {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.2);
    color: white;
}
</style>

<div class="h-full flex overflow-hidden">

    <!-- ── Left Sidebar (Filters) ── -->
    <div class="w-80 flex-shrink-0 border-r border-white/5 bg-surface2 flex flex-col h-full overflow-y-auto hide-scrollbar z-10 relative">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="size-10 rounded-xl bg-lime/10 border border-lime/20 flex flex-center text-lime">
                    <span class="material-symbols-outlined shrink-0" style="font-variation-settings: 'FILL' 1;">radar</span>
                </div>
                <div>
                    <h2 class="text-sm font-black text-text leading-tight">Hunter Config</h2>
                    <p class="text-[10px] text-muted">Defina seus alvos</p>
                </div>
            </div>

            <form id="hunterForm" class="space-y-5">
                <!-- Basic Filters -->
                <div class="space-y-4">
                    <h3 class="text-[10px] font-bold text-subtle uppercase tracking-wider mb-2">Geral</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Segmento ou Nicho *</label>
                        <input type="text" id="h_segment" placeholder="Ex: Clínicas Odontológicas" required
                               class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Estado *</label>
                        <div class="relative">
                            <input type="text" id="h_state" placeholder="Ex: São Paulo" required autocomplete="off"
                                   class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 transition-all">
                            <input type="hidden" id="h_state_uf" value="">
                            <div id="h_state_dropdown" class="absolute left-0 right-0 top-full mt-1 bg-surface2 border border-stroke rounded-xl shadow-lg z-30 max-h-48 overflow-y-auto hidden"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Cidade *</label>
                            <div class="relative">
                                <input type="text" id="h_city" placeholder="Selecione o estado" required autocomplete="off"
                                       class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 transition-all disabled:opacity-50" disabled>
                                <div id="h_city_dropdown" class="absolute left-0 right-0 top-full mt-1 bg-surface2 border border-stroke rounded-xl shadow-lg z-30 max-h-48 overflow-y-auto hidden"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted mb-1.5 ml-1">Raio</label>
                            <select id="h_radius" class="w-full bg-surface3 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text focus:outline-none focus:border-lime/50 transition-all appearance-none [color-scheme:dark]">
                                <option value="5" selected>5 km</option>
                                <option value="15">15 km</option>
                                <option value="30">30 km</option>
                                <option value="100">100 km</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Advanced Exclusions -->
                <div class="space-y-4 pt-4 border-t border-white/5">
                    <h3 class="text-[10px] font-bold text-subtle uppercase tracking-wider mb-2">Exclusões & Filtros</h3>
                    
                    <label class="flex items-center justify-between group cursor-pointer p-2 rounded-lg hover:bg-white/5 transition-colors">
                        <span class="text-xs text-muted group-hover:text-text transition-colors">Sem Website</span>
                        <div class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" value="sem-site" class="sr-only peer h-exclusion">
                            <div class="w-8 h-4 bg-surface3 rounded-full peer peer-checked:after:translate-x-4 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-muted peer-checked:after:bg-lime after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-lime/20 border border-stroke peer-checked:border-lime/50 outline-none"></div>
                        </div>
                    </label>

                    <label class="flex items-center justify-between group cursor-pointer p-2 rounded-lg hover:bg-white/5 transition-colors">
                        <span class="text-xs text-muted group-hover:text-text transition-colors">Franquias/Redes</span>
                        <div class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" value="franquias" class="sr-only peer h-exclusion">
                            <div class="w-8 h-4 bg-surface3 rounded-full peer peer-checked:after:translate-x-4 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-muted peer-checked:after:bg-lime after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-lime/20 border border-stroke peer-checked:border-lime/50 outline-none"></div>
                        </div>
                    </label>
                </div>

                <div class="pt-6 sticky bottom-0 bg-surface2 pb-4">
                    <button type="button" id="btnHunterSearch" onclick="runSearch()"
                            class="w-full h-12 rounded-xl bg-lime text-bg text-sm font-black shadow-glow flex items-center justify-center gap-2 hover:brightness-110 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-[18px]" id="radarIcon">travel_explore</span>
                        <span id="btnHunterLabel">Acionar Radar</span>
                    </button>
                    <p class="text-[10px] text-center text-subtle mt-3 flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[12px] text-operon-energy">bolt</span>
                        Consome tokens
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Main Area (Results) ── -->
    <div class="flex-1 flex flex-col h-full bg-bg relative">
        <!-- Topbar Action -->
        <div class="h-16 border-b border-white/5 flex items-center justify-between px-6 bg-surface/50 backdrop-blur-md">
            <div class="flex items-center gap-4">
                <button class="tab-btn active px-4 py-1.5 rounded-lg border border-transparent text-sm font-bold text-muted transition-all" onclick="switchTab('new')">
                    Novos (<span id="countNew">0</span>)
                </button>
                <button class="tab-btn px-4 py-1.5 rounded-lg border border-transparent text-sm font-bold text-muted hover:text-text transition-all" onclick="switchTab('saved')">
                    Salvos (<span id="countSaved"><?= count($savedResults ?? []) ?></span>)
                </button>
            </div>
            <div class="flex gap-2">
                <button class="size-9 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-lime shadow-inner cursor-default" title="Visualização em Cards">
                    <span class="material-symbols-outlined text-lg">grid_view</span>
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 hide-scrollbar relative">
            <div id="resultsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <!-- Initial Empty State -->
                <div class="col-span-full h-64 flex flex-col items-center justify-center text-center">
                    <div class="size-16 rounded-full bg-white/5 flex items-center justify-center mb-4 border border-white/5">
                        <span class="material-symbols-outlined text-3xl text-muted">troubleshoot</span>
                    </div>
                    <h3 class="text-text font-bold text-lg mb-1">Nenhum alvo no radar</h3>
                    <p class="text-sm text-subtle max-w-sm">Configure os filtros na barra lateral e acione o radar para encontrar oportunidades B2B.</p>
                </div>
            </div>
            
            <div id="loadingOverlay" class="absolute inset-0 bg-bg/80 backdrop-blur-sm z-20 flex-col items-center justify-center hidden">
                <div class="ai-spinner mb-4 size-10"></div>
                <h3 class="text-white font-bold animate-pulse">Varrendo a região...</h3>
                <p class="text-xs text-lime mt-2 font-mono" id="loadingText">Extraindo dados e indexando potenciais leads</p>
            </div>
        </div>
    </div>

</div>

<!-- ── Data Drawer (Slide-over) ── -->
<div id="drawerBackdrop" class="hunter-drawer-backdrop fixed inset-0 bg-black/60 backdrop-blur-sm z-40" onclick="closeDrawer()"></div>

<div id="hunterDrawer" class="hunter-drawer fixed right-0 top-0 bottom-0 w-[500px] max-w-full bg-surface2 border-l border-stroke shadow-2xl z-50 flex flex-col">
    <!-- Drawer Header -->
    <div class="h-20 border-b border-stroke flex items-center justify-between px-6 bg-surface shrink-0">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-xl bg-lime/10 border border-lime/20 flex items-center justify-center text-lime" id="drawerIcon">
                <span class="material-symbols-outlined">domain</span>
            </div>
            <div class="overflow-hidden">
                <h2 class="text-base font-black text-text truncate w-64" id="drawerTitle">Nome da Empresa</h2>
                <p class="text-xs text-subtle truncate w-64" id="drawerSubtitle">Segmento</p>
            </div>
        </div>
        <button onclick="closeDrawer()" class="size-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-muted hover:text-white transition-all">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>

    <!-- Drawer Body -->
    <div class="flex-1 overflow-y-auto p-6 space-y-8 hide-scrollbar" id="drawerContent">
        
        <!-- Score & Status -->
        <div class="flex gap-4">
            <div class="flex-1 bg-surface3 border border-stroke rounded-2xl p-4 relative overflow-hidden">
                <div class="absolute -right-4 -top-4 size-20 bg-lime/10 rounded-full blur-xl"></div>
                <p class="text-[10px] uppercase font-bold text-muted mb-1 relative z-10">ICP Match Score</p>
                <div class="flex items-end gap-2 relative z-10">
                    <span class="text-3xl font-black text-lime" id="drawerScore">-</span>
                    <span class="text-xs text-subtle mb-1.5">/ 100</span>
                </div>
            </div>
            <div class="flex-1 bg-surface3 border border-stroke rounded-2xl p-4 flex flex-col justify-center">
                <p class="text-[10px] uppercase font-bold text-muted mb-1">Prioridade IA</p>
                <div id="drawerPriority" class="text-sm font-bold text-text flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-slate-500"></span> Desconhecida
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div>
            <h3 class="text-[11px] font-bold text-subtle uppercase tracking-wider mb-3">Informações de Contato</h3>
            <div class="grid grid-cols-1 gap-2 text-sm">
                <div class="flex items-start gap-3 p-3 bg-surface border border-white/5 rounded-xl">
                    <span class="material-symbols-outlined text-muted text-[18px] shrink-0">location_on</span>
                    <span class="text-slate-300" id="drawerAddress">-</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-surface border border-white/5 rounded-xl">
                    <span class="material-symbols-outlined text-muted text-[18px]">call</span>
                    <span class="text-slate-300 font-mono" id="drawerPhone">-</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-surface border border-white/5 rounded-xl">
                    <span class="material-symbols-outlined text-muted text-[18px]">language</span>
                    <a href="#" target="_blank" class="text-lime hover:underline truncate" id="drawerWebsite">-</a>
                </div>
                <div class="flex items-center gap-3 p-3 bg-surface border border-white/5 rounded-xl">
                    <span class="material-symbols-outlined text-muted text-[18px]">mail</span>
                    <a href="#" target="_blank" class="text-lime hover:underline truncate" id="drawerEmail">-</a>
                </div>
                <div class="flex items-center gap-3 p-3 bg-surface border border-white/5 rounded-xl">
                    <span class="material-symbols-outlined text-muted text-[18px]">tag</span>
                    <a href="#" target="_blank" class="text-[#E1306C] hover:underline" id="drawerInsta">-</a>
                </div>
            </div>
        </div>

        <!-- AI Analysis Area -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[11px] font-bold text-operon-energy uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[14px]">smart_toy</span> IA Discovery
                </h3>
            </div>
            
            <div id="drawerAiBox" class="bg-operon-energy/5 border border-operon-energy/20 rounded-2xl p-5 space-y-5 hidden">
                <div>
                    <h4 class="text-xs font-bold text-operon-energy mb-2">Resumo Executivo</h4>
                    <p class="text-sm text-slate-300 leading-relaxed" id="drawerSummary"></p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="text-xs font-bold text-red-400 mb-2">Dores Prováveis</h4>
                        <ul class="text-xs text-slate-400 space-y-1.5 list-disc pl-4" id="drawerPains"></ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-lime mb-2">Oportunidades</h4>
                        <ul class="text-xs text-slate-400 space-y-1.5 list-disc pl-4" id="drawerOpps"></ul>
                    </div>
                </div>
                <div class="pt-2 border-t border-operon-energy/10">
                    <h4 class="text-xs font-bold text-operon-energy mb-2 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">chat</span> Abordagem Sugerida</h4>
                    <p class="text-xs text-slate-300 italic p-3 bg-black/20 rounded-xl border border-white/5" id="drawerApproach"></p>
                </div>
            </div>

            <div id="drawerAiAnalyzeBtn" class="bg-surface3 border border-stroke rounded-2xl p-6 text-center">
                <div class="size-12 rounded-full bg-operon-energy/10 border border-operon-energy/20 text-operon-energy flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined">network_node</span>
                </div>
                <h4 class="text-sm font-bold text-white mb-1">Analisar Potencial</h4>
                <p class="text-xs text-subtle mb-4">A IA vai cruzar os dados dessa empresa com o seu ICP e gerar um diagnóstico de vendas.</p>
                <button onclick="runAnalysis()" id="btnRunAnalysis" class="px-5 py-2.5 rounded-xl bg-operon-energy/20 text-operon-energy text-sm font-bold border border-operon-energy/30 hover:bg-operon-energy/30 transition-all flex items-center justify-center gap-2 mx-auto w-full disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[16px]">bolt</span>
                    Gerar Análise (Consome Token)
                </button>
            </div>
        </div>

    </div>

    <!-- Drawer Footer Actions -->
    <div class="h-20 border-t border-stroke p-4 bg-surface2 shrink-0 flex gap-3">
        <button id="btnDrawerSave" onclick="toggleSaveCurrent()" class="flex-1 rounded-xl border border-white/10 bg-surface hover:bg-white/5 text-sm font-bold text-text transition-all flex items-center justify-center gap-2 disabled:opacity-50">
            <span class="material-symbols-outlined" id="saveIcon">bookmark_add</span>
            <span id="saveText">Salvar</span>
        </button>
        <button id="btnDrawerImport" onclick="importCurrent()" class="flex-[2] rounded-xl bg-lime text-bg text-sm font-black shadow-glow hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
            <span class="material-symbols-outlined">login</span>
            Importar para Vault
        </button>
    </div>
</div>

<script>
// State
let currentResults = [];
let savedResults = <?= $savedResultsJson ?>;
let activeTab = 'new'; // 'new' or 'saved'
let activeResultId = null;

const csrf = '<?= csrf_token() ?>';

// UI Elements
const grid = document.getElementById('resultsGrid');
const loading = document.getElementById('loadingOverlay');

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active', 'text-white'));
    event.currentTarget.classList.add('active', 'text-white');
    renderGrid();
}

function renderGrid() {
    const data = activeTab === 'new' ? currentResults : savedResults;
    grid.innerHTML = '';
    
    if (data.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full h-64 flex flex-col items-center justify-center text-center">
                <div class="size-16 rounded-full bg-white/5 flex items-center justify-center mb-4 border border-white/5">
                    <span class="material-symbols-outlined text-3xl text-muted">search_off</span>
                </div>
                <h3 class="text-text font-bold text-lg mb-1">Nenhuma empresa aqui</h3>
                <p class="text-sm text-subtle">Sua área de empresas ${activeTab === 'new' ? 'novas' : 'salvas'} está vazia.</p>
            </div>
        `;
        return;
    }

    data.forEach(item => {
        const rating = item.google_rating ? `<span class="text-yellow-400 text-xs font-bold flex items-center gap-0.5"><span class="material-symbols-outlined text-[14px]" style="font-variation-settings:'FILL' 1">star</span> ${item.google_rating}</span>` : '';
        
        let priorityBadge = '';
        if (item.priority_level) {
            const colors = {hot: 'bg-red-500/20 text-red-500', warm: 'bg-yellow-500/20 text-yellow-500', cold: 'bg-blue-500/20 text-blue-500'};
            const labels = {hot: 'Hot', warm: 'Warm', cold: 'Cold'};
            priorityBadge = `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${colors[item.priority_level] || colors.cold}">${labels[item.priority_level] || 'Cold'}</span>`;
        }

        const isSaved = item.is_saved == 1;

        grid.innerHTML += `
            <div class="bg-surface2 border border-stroke rounded-2xl p-5 hover:border-lime/30 hover:bg-surface3 transition-all cursor-pointer group relative overflow-hidden flex flex-col h-full" onclick="openDrawer('${item.id}', '${activeTab}')">
                
                ${item.is_imported == 1 ? '<div class="absolute top-0 right-0 py-1 flex justify-center w-24 bg-lime text-bg text-[9px] font-black uppercase tracking-wider rotate-45 translate-x-7 translate-y-3 z-10 shadow-md">No Vault</div>' : ''}

                <div class="flex items-start justify-between mb-3 relative z-10 pr-6">
                    <div>
                        <h4 class="text-white font-bold text-sm leading-tight group-hover:text-lime transition-colors">${item.name}</h4>
                        <p class="text-[11px] text-muted">${item.segment || 'Sem segmento'}</p>
                    </div>
                </div>
                
                <div class="flex-1 flex flex-col gap-2 mt-2">
                    <div class="flex items-center gap-4">
                        ${rating}
                        ${priorityBadge}
                    </div>
                    ${item.phone ? `<div class="text-xs text-slate-400 font-mono flex items-center gap-1 truncate"><span class="material-symbols-outlined text-[12px]">call</span> ${item.phone}</div>` : ''}
                    <div class="text-xs text-slate-500 truncate flex items-center gap-1 mt-auto">
                        <span class="material-symbols-outlined text-[12px]">location_on</span> ${item.city || 'Desconhecida'}
                    </div>
                </div>
            </div>
        `;
    });
}

function updateCounts() {
    document.getElementById('countNew').innerText = currentResults.length;
    document.getElementById('countSaved').innerText = savedResults.length;
}

// ── Search Action ──
async function runSearch() {
    const btn = document.getElementById('btnHunterSearch');
    const segment = document.getElementById('h_segment').value.trim();
    const state = document.getElementById('h_state').value.trim();
    const city = document.getElementById('h_city').value.trim();

    if(!segment || !city || !state) {
        alert("Preencha segmento, estado e cidade.");
        return;
    }

    const payload = {
        _csrf: csrf,
        segment: segment,
        city: city + ', ' + state,
        radius: document.getElementById('h_radius').value
    };

    // Get exclusions
    document.querySelectorAll('.h-exclusion:checked').forEach(el => {
        if(!payload.exclusions) payload.exclusions = [];
        payload.exclusions.push(el.value);
    });

    btn.disabled = true;
    document.getElementById('radarIcon').className = 'ai-spinner size-[18px]';
    document.getElementById('radarIcon').innerText = '';
    document.getElementById('btnHunterLabel').innerText = 'Varrendo Radares...';

    loading.classList.remove('hidden');
    loading.classList.add('flex');

    try {
        const res = await fetch('/hunter', {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: {'Content-Type': 'application/json'}
        });
        const data = await res.json();
        
        if (data.error) throw new Error(data.message || data.error);
        if (data.success && data.results) {
            currentResults = data.results;
            activeTab = 'new';
            document.querySelectorAll('.tab-btn')[0].click(); // force switch to new
            updateCounts();
        }
    } catch (e) {
        alert(e.message || "Erro na busca.");
    } finally {
        btn.disabled = false;
        document.getElementById('radarIcon').className = 'material-symbols-outlined text-[18px]';
        document.getElementById('radarIcon').innerText = 'travel_explore';
        document.getElementById('btnHunterLabel').innerText = 'Acionar Radar';

        loading.classList.add('hidden');
        loading.classList.remove('flex');
    }
}

// ── Drawer & Analysis ──
function openDrawer(id, tab) {
    activeResultId = id;
    const sourceArr = tab === 'new' ? currentResults : savedResults;
    const item = sourceArr.find(i => i.id === id);
    if(!item) return;

    // Populate data
    document.getElementById('drawerTitle').innerText = item.name;
    document.getElementById('drawerSubtitle').innerText = item.segment || 'Empresa Local';
    document.getElementById('drawerAddress').innerText = item.address || 'Não cadastrado';
    document.getElementById('drawerPhone').innerText = item.phone || 'Nenhum contato base';
    
    if(item.website) {
        let el = document.getElementById('drawerWebsite');
        el.innerText = item.website;
        el.href = item.website.startsWith('http') ? item.website : 'https://' + item.website;
    } else {
        document.getElementById('drawerWebsite').innerText = 'Sem site';
        document.getElementById('drawerWebsite').href = '#';
    }

    if(item.email) {
        let el = document.getElementById('drawerEmail');
        el.innerText = item.email;
        el.href = 'mailto:' + item.email;
    } else {
        document.getElementById('drawerEmail').innerText = 'Não encontrado';
        document.getElementById('drawerEmail').href = '#';
    }

    if(item.instagram) {
        let el = document.getElementById('drawerInsta');
        el.innerText = item.instagram;
        el.href = item.instagram.startsWith('http') ? item.instagram : `https://instagram.com/${item.instagram.replace('@','')}`;
    } else {
        document.getElementById('drawerInsta').innerText = 'Não encontrado';
        document.getElementById('drawerInsta').href = '#';
    }

    // Save state
    const isSaved = item.is_saved == 1;
    document.getElementById('saveIcon').innerText = isSaved ? 'bookmark_remove' : 'bookmark_add';
    document.getElementById('saveText').innerText = isSaved ? 'Remover' : 'Salvar';

    // Import state
    const btnImp = document.getElementById('btnDrawerImport');
    if(item.is_imported == 1) {
        btnImp.disabled = true;
        btnImp.innerHTML = '<span class="material-symbols-outlined">check_circle</span> Já no Vault';
    } else {
        btnImp.disabled = false;
        btnImp.innerHTML = '<span class="material-symbols-outlined">login</span> Importar para Vault';
    }

    // AI Box State
    // We don't have analysis yet synchronously, so we hide it and show "Analyze" button
    document.getElementById('drawerAiBox').classList.add('hidden');
    document.getElementById('drawerAiAnalyzeBtn').classList.remove('hidden');
    document.getElementById('drawerScore').innerText = '-';
    document.getElementById('drawerPriority').innerHTML = '<span class="size-2 rounded-full bg-slate-500"></span> Desconhecida';

    // Open
    document.getElementById('drawerBackdrop').classList.add('open');
    document.getElementById('hunterDrawer').classList.add('open');
}

function closeDrawer() {
    document.getElementById('drawerBackdrop').classList.remove('open');
    document.getElementById('hunterDrawer').classList.remove('open');
}

async function runAnalysis() {
    if(!activeResultId) return;
    const btn = document.getElementById('btnRunAnalysis');
    btn.disabled = true;
    btn.innerHTML = '<div class="ai-spinner"></div> Gerando Diagnóstico...';

    try {
        const res = await fetch('/hunter/analyze', {
            method: 'POST',
            body: JSON.stringify({_csrf: csrf, result_id: activeResultId}),
            headers: {'Content-Type': 'application/json'}
        });
        const data = await res.json();
        
        if (data.error) throw new Error(data.message || data.error);
        
        if (data.success) {
            // Update UI with enriched data
            const r = data.result;
            if(r.phone) document.getElementById('drawerPhone').innerText = r.phone;
            if(r.website && r.website !== 'Sem site') {
                document.getElementById('drawerWebsite').innerText = r.website;
                document.getElementById('drawerWebsite').href = r.website.startsWith('http') ? r.website : 'https://' + r.website;
            }
            if(r.instagram && r.instagram !== 'Não encontrado') {
                document.getElementById('drawerInsta').innerText = r.instagram;
                document.getElementById('drawerInsta').href = r.instagram.startsWith('http') ? r.instagram : `https://instagram.com/${r.instagram.replace('@','')}`;
            }

            // Update AI Stats
            const a = data.analysis;
            document.getElementById('drawerAiAnalyzeBtn').classList.add('hidden');
            document.getElementById('drawerAiBox').classList.remove('hidden');
            
            document.getElementById('drawerScore').innerText = a.icp_match_score || 0;
            
            const prioColors = {hot: 'bg-red-500', warm: 'bg-yellow-500', cold: 'bg-blue-500'};
            const prioLabels = {hot: 'Hot Lead', warm: 'Positivo', cold: 'Baixo Potencial'};
            const level = a.priority_level || 'cold';
            document.getElementById('drawerPriority').innerHTML = `<span class="size-2 rounded-full ${prioColors[level]}"></span> <span class="text-${prioColors[level].replace('bg-','')}">${prioLabels[level]}</span>`;

            document.getElementById('drawerSummary').innerText = a.executive_summary;
            document.getElementById('drawerApproach').innerText = a.recommended_approach;
            
            let htmlP = '';
            (a.pain_points || []).forEach(p => htmlP += `<li>${p}</li>`);
            document.getElementById('drawerPains').innerHTML = htmlP;

            let htmlO = '';
            (a.opportunities || []).forEach(o => htmlO += `<li>${o}</li>`);
            document.getElementById('drawerOpps').innerHTML = htmlO;

            // Sync with memory arrays
            updateItemInArrays(r);
        }
    } catch (e) {
        alert(e.message || "Erro na análise.");
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">bolt</span> Tentar Novamente';
    }
}

async function toggleSaveCurrent() {
    if(!activeResultId) return;
    const sourceArr = activeTab === 'new' ? currentResults : savedResults;
    const item = sourceArr.find(i => i.id === activeResultId);
    if(!item) return;

    const willSave = item.is_saved == 0;
    
    try {
        await fetch('/hunter/save', {
            method: 'POST',
            body: JSON.stringify({_csrf: csrf, result_id: activeResultId, saving: willSave}),
            headers: {'Content-Type': 'application/json'}
        });
        
        item.is_saved = willSave ? 1 : 0;
        
        // Update arrays
        if(willSave) {
            if(!savedResults.find(i => i.id === item.id)) savedResults.unshift(item);
        } else {
            savedResults = savedResults.filter(i => i.id !== item.id);
        }
        
        // Update UI
        document.getElementById('saveIcon').innerText = willSave ? 'bookmark_remove' : 'bookmark_add';
        document.getElementById('saveText').innerText = willSave ? 'Remover' : 'Salvar';
        updateCounts();
        
        if (activeTab === 'saved') renderGrid(); // Re-render if looking at saved list
        
    } catch(e) {
        console.error(e);
    }
}

async function importCurrent() {
    if(!activeResultId) return;
    const btn = document.getElementById('btnDrawerImport');
    btn.disabled = true;
    btn.innerHTML = '<div class="ai-spinner"></div>';
    
    try {
        const res = await fetch('/hunter/import', {
            method: 'POST',
            body: JSON.stringify({_csrf: csrf, result_id: activeResultId}),
            headers: {'Content-Type': 'application/json'}
        });
        const data = await res.json();
        
        if(data.error) throw new Error(data.message || data.error);
        if(data.success) {
            btn.innerHTML = '<span class="material-symbols-outlined">check_circle</span> Importado!';
            
            // Mark as imported in arrays
            [currentResults, savedResults].forEach(arr => {
                const i = arr.find(x => x.id === activeResultId);
                if(i) {
                    i.is_imported = 1;
                    i.imported_lead_id = data.lead_id;
                }
            });
            renderGrid();
            
            setTimeout(() => {
                window.location.href = `/vault/${data.lead_id}`;
            }, 1000);
        }
    } catch(e) {
        alert(e.message || "Falha na importação");
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">login</span> Importar para Vault';
    }
}

function updateItemInArrays(updatedItem) {
    [currentResults, savedResults].forEach(arr => {
        const index = arr.findIndex(x => x.id === updatedItem.id);
        if(index > -1) {
            arr[index] = {...arr[index], ...updatedItem};
        }
    });
}

// ── Estado / Cidade Autocomplete (IBGE API) ──
const ESTADOS_BR = [
    {uf:"AC",nome:"Acre"},{uf:"AL",nome:"Alagoas"},{uf:"AP",nome:"Amapá"},{uf:"AM",nome:"Amazonas"},
    {uf:"BA",nome:"Bahia"},{uf:"CE",nome:"Ceará"},{uf:"DF",nome:"Distrito Federal"},{uf:"ES",nome:"Espírito Santo"},
    {uf:"GO",nome:"Goiás"},{uf:"MA",nome:"Maranhão"},{uf:"MT",nome:"Mato Grosso"},{uf:"MS",nome:"Mato Grosso do Sul"},
    {uf:"MG",nome:"Minas Gerais"},{uf:"PA",nome:"Pará"},{uf:"PB",nome:"Paraíba"},{uf:"PR",nome:"Paraná"},
    {uf:"PE",nome:"Pernambuco"},{uf:"PI",nome:"Piauí"},{uf:"RJ",nome:"Rio de Janeiro"},{uf:"RN",nome:"Rio Grande do Norte"},
    {uf:"RS",nome:"Rio Grande do Sul"},{uf:"RO",nome:"Rondônia"},{uf:"RR",nome:"Roraima"},{uf:"SC",nome:"Santa Catarina"},
    {uf:"SP",nome:"São Paulo"},{uf:"SE",nome:"Sergipe"},{uf:"TO",nome:"Tocantins"}
];

let cidadesCache = {};

const stateInput = document.getElementById('h_state');
const stateUf = document.getElementById('h_state_uf');
const stateDropdown = document.getElementById('h_state_dropdown');
const cityInput = document.getElementById('h_city');
const cityDropdown = document.getElementById('h_city_dropdown');

function normalizeStr(s) {
    return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function showDropdown(dropdown, items, onSelect) {
    if (items.length === 0) { dropdown.classList.add('hidden'); return; }
    dropdown.innerHTML = items.map((item, i) =>
        `<div class="px-4 py-2.5 text-sm text-text hover:bg-lime/10 hover:text-lime cursor-pointer transition-colors ${i === 0 ? 'rounded-t-xl' : ''} ${i === items.length-1 ? 'rounded-b-xl' : ''}" data-value="${item.value}" data-label="${item.label}">${item.label}</div>`
    ).join('');
    dropdown.classList.remove('hidden');
    dropdown.querySelectorAll('div').forEach(el => {
        el.addEventListener('mousedown', (e) => {
            e.preventDefault();
            onSelect(el.dataset.value, el.dataset.label);
            dropdown.classList.add('hidden');
        });
    });
}

// Estado autocomplete
stateInput.addEventListener('input', () => {
    const query = normalizeStr(stateInput.value);
    if (query.length === 0) { stateDropdown.classList.add('hidden'); return; }
    const matches = ESTADOS_BR.filter(e => normalizeStr(e.nome).includes(query) || normalizeStr(e.uf).includes(query)).slice(0, 10);
    showDropdown(stateDropdown, matches.map(e => ({value: e.uf, label: `${e.nome} (${e.uf})`})), (uf, label) => {
        stateInput.value = label;
        stateUf.value = uf;
        cityInput.value = '';
        cityInput.disabled = false;
        cityInput.placeholder = 'Digite a cidade...';
        loadCidades(uf);
    });
});
stateInput.addEventListener('blur', () => setTimeout(() => stateDropdown.classList.add('hidden'), 200));

// Carregar cidades do IBGE
async function loadCidades(uf) {
    if (cidadesCache[uf]) return;
    try {
        const res = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios?orderBy=nome`);
        const data = await res.json();
        cidadesCache[uf] = data.map(c => c.nome);
    } catch(e) {
        console.error('Erro ao carregar cidades IBGE:', e);
        cidadesCache[uf] = [];
    }
}

// Cidade autocomplete
cityInput.addEventListener('input', () => {
    const uf = stateUf.value;
    if (!uf || !cidadesCache[uf]) { cityDropdown.classList.add('hidden'); return; }
    const query = normalizeStr(cityInput.value);
    if (query.length === 0) { cityDropdown.classList.add('hidden'); return; }
    const matches = cidadesCache[uf].filter(c => normalizeStr(c).includes(query)).slice(0, 10);
    showDropdown(cityDropdown, matches.map(c => ({value: c, label: c})), (val) => {
        cityInput.value = val;
    });
});
cityInput.addEventListener('blur', () => setTimeout(() => cityDropdown.classList.add('hidden'), 200));

// Fechar dropdowns ao clicar fora
document.addEventListener('click', (e) => {
    if (!e.target.closest('#h_state') && !e.target.closest('#h_state_dropdown')) stateDropdown.classList.add('hidden');
    if (!e.target.closest('#h_city') && !e.target.closest('#h_city_dropdown')) cityDropdown.classList.add('hidden');
});
</script>
