<?php
$pageTitle    = 'Atlas de Vendas';
$pageSubtitle = 'Mapa de Oportunidades';
$mapLeadsJsonString = $mapLeadsJson ?? '[]';
$extraScripts = <<<HTML
<!-- Leaflet.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('atlasMap');
    if (!mapEl) return;

    const map = L.map('atlasMap', {
        center: [-23.55, -46.63],
        zoom: 12,
        zoomControl: true,
    });

    // CartoDB Dark Matter tiles (free, no API key)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap © CARTO',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(map);

    const leads = {$mapLeadsJsonString};

    // Custom marker icon
    function makeIcon(score) {
        const color = score >= 70 ? '#18C29C' : score >= 40 ? '#F59E0B' : '#E11D48';
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">
            <path d="M14 0C6.268 0 0 6.268 0 14c0 10.5 14 22 14 22s14-11.5 14-22C28 6.268 21.732 0 14 0z" fill="\${color}" opacity="0.9"/>
            <circle cx="14" cy="14" r="6" fill="white" opacity="0.9"/>
        </svg>`;
        return L.divIcon({
            html: svg,
            iconSize: [28, 36],
            iconAnchor: [14, 36],
            popupAnchor: [0, -36],
            className: '',
        });
    }

    const bounds = [];
    leads.forEach(lead => {
        // Geocode by address (demo: offset from SP center for seeds)
        // In production, use Nominatim or stored lat/lng
        const lat = -23.55 + (Math.random() - 0.5) * 0.3;
        const lng = -46.63 + (Math.random() - 0.5) * 0.4;
        const score = lead.priority_score || 0;

        const marker = L.marker([lat, lng], { icon: makeIcon(score) }).addTo(map);
        marker.bindPopup(`
            <div style="min-width:180px">
                <div style="font-weight:700;font-size:13px;color:#fff;margin-bottom:4px">\${lead.name}</div>
                <div style="font-size:11px;color:#94a3b8;margin-bottom:8px">\${lead.segment}</div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:11px;color:#18C29C">Score: \${score}</span>
                    <a href="/vault/\${lead.id}" style="font-size:11px;color:#18C29C;text-decoration:none;font-weight:600">Ver →</a>
                </div>
            </div>
        `);
        bounds.push([lat, lng]);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [30, 30] });
    }
});
</script>
HTML;
?>

<div class="flex h-full overflow-hidden">

    <!-- Sidebar Stats -->
    <div class="w-72 flex-shrink-0 border-r border-slate-800 bg-operon-teal flex flex-col overflow-y-auto p-4 gap-4">

        <!-- Stats -->
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">Resumo do Atlas</p>
            <div class="grid grid-cols-2 gap-2">
                <?php
                $cards = [
                    ['label' => 'Total Leads', 'value' => $stats['total'], 'color' => 'text-blue-400'],
                    ['label' => 'No Mapa',     'value' => $stats['mapped'], 'color' => 'text-operon-energy'],
                    ['label' => 'Score Alto',  'value' => $stats['high'], 'color' => 'text-yellow-400'],
                    ['label' => 'Segmentos',   'value' => count($stats['segments']), 'color' => 'text-violet-400'],
                ];
                foreach ($cards as $c): ?>
                <div class="bg-white/5 border border-white/8 rounded-xl p-3">
                    <div class="text-lg font-black <?= $c['color'] ?>"><?= $c['value'] ?></div>
                    <div class="text-[10px] text-slate-500 mt-0.5"><?= $c['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Segments -->
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">Top Segmentos</p>
            <div class="flex flex-col gap-2">
                <?php foreach ($stats['segments'] as $seg => $count): ?>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 truncate max-w-[160px]"><?= e($seg) ?></span>
                    <span class="text-xs font-bold text-white"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Legend -->
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">Legenda Score</p>
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-operon-energy inline-block flex-shrink-0"></span>
                    <span class="text-xs text-slate-400">Alto (&ge;70) — Prioridade máxima</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-yellow-400 inline-block flex-shrink-0"></span>
                    <span class="text-xs text-slate-400">Médio (40-69) — Oportunidade</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-red-400 inline-block flex-shrink-0"></span>
                    <span class="text-xs text-slate-400">Baixo (&lt;40) — Nutrir</span>
                </div>
            </div>
        </div>

        <!-- Lead List -->
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">Leads no Atlas (<?= count($mapLeads ?? []) ?>)</p>
            <div class="flex flex-col gap-1.5">
                <?php foreach (array_slice($mapLeads ?? [], 0, 10) as $lead): ?>
                <a href="/vault/<?= $lead['id'] ?>"
                   class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl hover:bg-white/8 transition-colors group">
                    <span class="size-1.5 rounded-full flex-shrink-0 <?= ($lead['priority_score'] ?? 0) >= 70 ? 'bg-operon-energy' : (($lead['priority_score'] ?? 0) >= 40 ? 'bg-yellow-400' : 'bg-red-400') ?>"></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-slate-300 group-hover:text-white truncate"><?= e($lead['name']) ?></div>
                        <div class="text-[10px] text-slate-500 truncate"><?= e($lead['segment']) ?></div>
                    </div>
                    <span class="text-[11px] font-black <?= scoreColor($lead['priority_score'] ?? 0) ?>"><?= $lead['priority_score'] ?? 0 ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Map -->
    <div class="flex-1 relative">
        <div id="atlasMap" class="w-full h-full"></div>

        <!-- Map controls overlay -->
        <div class="absolute top-4 right-4 flex flex-col gap-2 z-[1000]">
            <button onclick="openModal('newLeadModal')"
                    class="flex items-center gap-2 px-3 py-2 rounded-xl bg-primary text-bg text-xs font-bold hover:brightness-110 transition-all shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-base">add_location</span>
                Adicionar Lead
            </button>
        </div>
    </div>
</div>

<!-- New Lead Modal (reused) -->
<div id="newLeadModal" class="fixed inset-0 z-[2000] modal-backdrop hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="w-full max-w-md bg-brand-surface border border-white/10 rounded-2xl shadow-2xl animate-popIn">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/8">
            <h3 class="text-sm font-bold text-white">Adicionar Lead ao Atlas</h3>
            <button onclick="closeModal('newLeadModal')" class="text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="/vault" class="p-5 flex flex-col gap-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nome *</label>
                <input type="text" name="name" required placeholder="Ex: Restaurante Dom Pepe"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Segmento *</label>
                <input type="text" name="segment" required placeholder="Ex: Alimentação"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Endereço (para o mapa)</label>
                <input type="text" name="address" placeholder="Rua, Nº, Bairro, Cidade - UF"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t border-white/8">
                <button type="button" onclick="closeModal('newLeadModal')" class="px-4 py-2 rounded-xl bg-white/5 text-slate-400 text-sm hover:bg-white/10 transition-all">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-bg text-sm font-bold hover:brightness-110 transition-all">Adicionar</button>
            </div>
        </form>
    </div>
</div>
