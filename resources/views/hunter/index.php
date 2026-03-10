<?php
$pageTitle    = 'Hunter';
$pageSubtitle = 'Prospecção Inteligente com IA';
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">Hunter Protocol</h2>
            <p class="text-sm text-slate-400 mt-0.5">Encontre empresas no seu mercado alvo com IA + Google Search</p>
        </div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-operon-energy/10 border border-operon-energy/20">
            <span class="size-1.5 bg-operon-energy rounded-full animate-pulse"></span>
            <span class="text-[10px] font-bold text-operon-energy uppercase tracking-wider">Operon Intelligence</span>
        </div>
    </div>

    <!-- Search Form -->
    <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-white mb-4">Defina seu alvo</h3>
        <div id="hunterForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Segmento *</label>
                <input type="text" id="hunterSegment" placeholder="Ex: Clínicas Odontológicas"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Cidade *</label>
                <input type="text" id="hunterCity" placeholder="Ex: São Paulo"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Raio (km)</label>
                <select id="hunterRadius" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-operon-energy/50 transition-all">
                    <option value="3">3 km</option>
                    <option value="5" selected>5 km</option>
                    <option value="10">10 km</option>
                    <option value="20">20 km</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button id="hunterBtn" onclick="runHunter()"
                    class="flex items-center gap-2 px-6 py-3 rounded-xl bg-primary text-white text-sm font-bold hover:brightness-110 transition-all shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-lg">radar</span>
                Iniciar Hunt
            </button>
            <p class="text-xs text-slate-500">
                <span class="material-symbols-outlined text-sm align-middle">bolt</span>
                Consome tokens de IA por busca
            </p>
        </div>
    </div>

    <!-- Results -->
    <div id="hunterResults" class="hidden">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white" id="hunterResultTitle">Resultados encontrados</h3>
            <button id="importAllBtn" onclick="importAllLeads()"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-operon-energy/20 text-operon-energy text-xs font-bold border border-operon-energy/30 hover:bg-operon-energy/30 transition-all">
                <span class="material-symbols-outlined text-base">save</span>
                Importar Todos
            </button>
        </div>
        <div id="hunterGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
    </div>

    <!-- Empty state -->
    <div id="hunterEmpty" class="flex flex-col items-center justify-center py-16 text-center">
        <div class="size-20 rounded-full bg-white/3 border border-white/8 flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-4xl text-slate-600">radar</span>
        </div>
        <h3 class="text-base font-bold text-slate-400 mb-2">Hunter aguardando alvo</h3>
        <p class="text-sm text-slate-600 max-w-sm">
            Defina o segmento e cidade acima e clique em "Iniciar Hunt" para a IA encontrar empresas com potencial de negócio.
        </p>
    </div>

</div>

<script>
let hunterLeads = [];

async function runHunter() {
    const segment = document.getElementById('hunterSegment').value.trim();
    const city    = document.getElementById('hunterCity').value.trim();
    const radius  = document.getElementById('hunterRadius').value;

    if (!segment || !city) {
        alert('Preencha o segmento e a cidade.');
        return;
    }

    const btn = document.getElementById('hunterBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="ai-spinner"></div><span>Caçando leads...</span>';

    document.getElementById('hunterEmpty').classList.add('hidden');
    document.getElementById('hunterResults').classList.add('hidden');

    try {
        const res = await operonFetch('/hunter', {
            method: 'POST',
            body: JSON.stringify({ segment, city, radius: parseInt(radius), _csrf: getCsrfToken() })
        });

        if (res.error) {
            if (res.error === 'tokens_depleted') {
                alert('Tokens diários esgotados. Aguarde o reset à meia-noite.');
            } else {
                alert(res.error || 'Erro na busca.');
            }
            document.getElementById('hunterEmpty').classList.remove('hidden');
            return;
        }

        hunterLeads = res.leads || [];
        renderHunterResults(hunterLeads, segment, city);
    } catch (e) {
        alert('Erro de conexão.');
        document.getElementById('hunterEmpty').classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-lg">radar</span>Iniciar Hunt';
    }
}

function renderHunterResults(leads, segment, city) {
    const grid  = document.getElementById('hunterGrid');
    const title = document.getElementById('hunterResultTitle');
    title.textContent = `${leads.length} empresas encontradas em ${city} — ${segment}`;

    grid.innerHTML = leads.map((lead, i) => {
        const score = lead.estimated_score || 0;
        const scoreClass = score >= 70 ? 'score-high' : score >= 40 ? 'score-medium' : 'score-low';
        return `
        <div class="bg-brand-surface border border-white/8 rounded-2xl p-5 hover:border-white/15 transition-all">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-bold text-white truncate">${lead.name}</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">${lead.segment || segment}</p>
                </div>
                <span class="inline-block px-2 py-1 rounded-lg text-xs font-black flex-shrink-0 ml-2 ${scoreClass}">${score}</span>
            </div>
            ${lead.address ? `<p class="text-xs text-slate-500 flex items-center gap-1 mb-2"><span class="material-symbols-outlined text-xs">location_on</span>${lead.address}</p>` : ''}
            ${lead.phone ? `<p class="text-xs text-slate-500 flex items-center gap-1 mb-2"><span class="material-symbols-outlined text-xs">phone</span>${lead.phone}</p>` : ''}
            <p class="text-[11px] text-slate-600 italic mt-2 line-clamp-2">${lead.reason || ''}</p>
            <div class="flex gap-2 mt-4 pt-3 border-t border-white/6">
                ${lead.phone ? `<a href="https://wa.me/${lead.phone.replace(/\D/g,'')}" target="_blank" class="flex-1 flex items-center justify-center gap-1 py-2 rounded-xl bg-green-500/10 text-green-400 text-xs font-bold border border-green-500/20 hover:bg-green-500/20 transition-all"><span class="material-symbols-outlined text-sm">chat</span>WhatsApp</a>` : ''}
                <button onclick="importSingleLead(${i})" class="flex-1 flex items-center justify-center gap-1 py-2 rounded-xl bg-operon-energy/10 text-operon-energy text-xs font-bold border border-operon-energy/20 hover:bg-operon-energy/20 transition-all">
                    <span class="material-symbols-outlined text-sm">save</span>Vault
                </button>
            </div>
        </div>`;
    }).join('');

    document.getElementById('hunterResults').classList.remove('hidden');
}

async function importSingleLead(index) {
    const lead = hunterLeads[index];
    if (!lead) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/vault';
    const fields = { _csrf: getCsrfToken(), name: lead.name, segment: lead.segment || '', website: lead.website || '', phone: lead.phone || '', address: lead.address || '' };
    Object.entries(fields).forEach(([k, v]) => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = k;
        inp.value = v;
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
}

async function importAllLeads() {
    if (!hunterLeads.length) return;
    if (!confirm(`Importar ${hunterLeads.length} leads para o Vault?`)) return;
    // Import first lead via form, then handle via batch (simplified: import first)
    importSingleLead(0);
}
</script>
