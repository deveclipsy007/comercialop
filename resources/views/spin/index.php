<?php
$pageTitle    = 'SPIN Hub';
$pageSubtitle = 'Framework de Perguntas de Alto Impacto';
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">SPIN Hub</h2>
            <p class="text-sm text-slate-400 mt-0.5">Gere perguntas e scripts de abordagem personalizados por IA</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Config Panel -->
        <div class="lg:col-span-1">
            <div class="bg-brand-surface border border-white/8 rounded-2xl p-5 sticky top-6">
                <h3 class="text-sm font-bold text-white mb-4">Configurar Análise</h3>

                <div class="flex flex-col gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Selecione o Lead</label>
                        <select id="spinLeadSelect" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all [color-scheme:dark]">
                            <option value="" class="bg-surface text-slate-400">— Escolha um lead —</option>
                            <?php foreach ($leads as $lead): ?>
                            <option value="<?= $lead['id'] ?>" data-segment="<?= e($lead['segment']) ?>" data-name="<?= e($lead['name']) ?>" class="bg-surface text-primary font-bold">
                                <?= e($lead['name']) ?> (Score: <?= $lead['priority_score'] ?? 0 ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button id="spinBtn" onclick="generateSpin()"
                            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-primary text-bg text-sm font-bold hover:brightness-110 transition-all shadow-lg shadow-primary/20">
                        <span class="material-symbols-outlined text-lg">track_changes</span>
                        Gerar SPIN + Scripts
                    </button>

                    <div class="text-xs text-slate-600 text-center">
                        Consome tokens de IA por geração
                    </div>
                </div>

                <!-- SPIN Legend -->
                <div class="mt-6 pt-4 border-t border-white/8">
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-3">Framework SPIN</p>
                    <?php
                    $spinDef = [
                        ['letter' => 'S', 'label' => 'Situação',  'color' => 'text-blue-400',   'bg' => 'bg-blue-500/15', 'desc' => 'Compreender o contexto atual'],
                        ['letter' => 'P', 'label' => 'Problema',  'color' => 'text-yellow-400', 'bg' => 'bg-yellow-500/15','desc' => 'Identificar dores e dificuldades'],
                        ['letter' => 'I', 'label' => 'Implicação','color' => 'text-red-400',    'bg' => 'bg-red-500/15',  'desc' => 'Amplificar o impacto do problema'],
                        ['letter' => 'N', 'label' => 'Necessidade','color' => 'text-operon-energy','bg' => 'bg-operon-energy/15','desc' => 'Guiar para a solução'],
                    ];
                    foreach ($spinDef as $s): ?>
                    <div class="flex items-start gap-2.5 mb-2">
                        <span class="size-6 rounded-lg <?= $s['bg'] ?> <?= $s['color'] ?> font-black text-xs flex items-center justify-center flex-shrink-0"><?= $s['letter'] ?></span>
                        <div>
                            <p class="text-xs font-bold text-white"><?= $s['label'] ?></p>
                            <p class="text-[10px] text-slate-500"><?= $s['desc'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Results Panel -->
        <div class="lg:col-span-2 flex flex-col gap-5">

            <!-- Loading / Empty state -->
            <div id="spinEmpty" class="flex flex-col items-center justify-center py-16 text-center bg-brand-surface border border-white/8 rounded-2xl">
                <span class="material-symbols-outlined text-5xl text-slate-600 mb-3">track_changes</span>
                <h3 class="text-base font-bold text-slate-400 mb-2">Selecione um lead para começar</h3>
                <p class="text-sm text-slate-600 max-w-xs">A IA vai gerar perguntas SPIN personalizadas e scripts de abordagem para cada canal.</p>
            </div>

            <!-- SPIN Questions -->
            <div id="spinQuestions" class="hidden">
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-5 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-white">Perguntas SPIN</h3>
                        <span id="spinLeadBadge" class="text-xs text-operon-energy bg-operon-energy/10 px-2 py-0.5 rounded-lg font-medium"></span>
                    </div>
                    <div id="spinQuestionsContent" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                </div>

                <!-- Scripts -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-white mb-4">Scripts de Abordagem</h3>
                    <div class="flex gap-2 mb-4">
                        <?php foreach (['whatsapp' => 'chat', 'linkedin' => 'business', 'email' => 'mail', 'coldCall' => 'call'] as $key => $icon): ?>
                        <button onclick="showScript('<?= $key ?>')"
                                data-script-tab="<?= $key ?>"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all script-tab
                                       <?= $key === 'whatsapp' ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-slate-400 border border-white/10 hover:bg-white/10' ?>">
                            <span class="material-symbols-outlined text-sm"><?= $icon ?></span>
                            <?= ucfirst($key === 'coldCall' ? 'Ligação' : $key) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div id="scriptContent" class="relative">
                        <div id="scriptText" class="bg-white/3 rounded-xl px-4 py-3.5 text-sm text-slate-300 leading-relaxed min-h-[120px] whitespace-pre-wrap font-mono"></div>
                        <button onclick="copyScript()" class="absolute top-3 right-3 size-7 rounded-lg bg-white/10 flex items-center justify-center text-slate-400 hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-sm">content_copy</span>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
let spinData = {};
let currentScriptChannel = 'whatsapp';

async function generateSpin() {
    const select = document.getElementById('spinLeadSelect');
    const leadId = select.value;
    if (!leadId) { alert('Selecione um lead.'); return; }

    const btn = document.getElementById('spinBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="ai-spinner"></div><span>Gerando...</span>';
    document.getElementById('spinEmpty').innerHTML = '<div class="flex flex-col items-center justify-center py-16"><div class="ai-spinner" style="width:40px;height:40px;border-width:3px"></div><p class="text-sm text-slate-400 mt-4 animate-pulse">Operon Intelligence gerando perguntas SPIN...</p></div>';
    document.getElementById('spinEmpty').classList.remove('hidden');
    document.getElementById('spinQuestions').classList.add('hidden');

    try {
        const res = await operonFetch('/spin', {
            method: 'POST',
            body: JSON.stringify({ lead_id: leadId, _csrf: getCsrfToken() })
        });

        if (res.error) { alert(res.error); return; }
        spinData = res;
        renderSpin(res, select.options[select.selectedIndex].text);
    } catch (e) {
        alert('Erro de conexão.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-lg">track_changes</span>Gerar SPIN + Scripts';
    }
}

function renderSpin(data, leadName) {
    document.getElementById('spinEmpty').classList.add('hidden');
    document.getElementById('spinQuestions').classList.remove('hidden');
    document.getElementById('spinLeadBadge').textContent = data.lead?.name || leadName;

    const spin = data.spin || {};
    const phaseMap = {
        s: { label: 'Situação', color: 'border-blue-400/30 bg-blue-500/5', badge: 'bg-blue-500/15 text-blue-400' },
        p: { label: 'Problema', color: 'border-yellow-400/30 bg-yellow-500/5', badge: 'bg-yellow-500/15 text-yellow-400' },
        i: { label: 'Implicação', color: 'border-red-400/30 bg-red-500/5', badge: 'bg-red-500/15 text-red-400' },
        n: { label: 'Necessidade', color: 'border-operon-energy/30 bg-operon-energy/5', badge: 'bg-operon-energy/15 text-operon-energy' },
    };

    document.getElementById('spinQuestionsContent').innerHTML = Object.entries(phaseMap).map(([key, phase]) => {
        const qs = spin[key] || [];
        return `
        <div class="border ${phase.color} rounded-xl p-4 border">
            <div class="flex items-center gap-2 mb-3">
                <span class="size-5 rounded-md ${phase.badge} text-[10px] font-black flex items-center justify-center">${key.toUpperCase()}</span>
                <span class="text-xs font-bold text-white">${phase.label}</span>
            </div>
            <ul class="flex flex-col gap-2">
                ${qs.map(q => `<li class="text-xs text-slate-300 leading-relaxed flex gap-2"><span class="text-slate-600 flex-shrink-0 mt-0.5">›</span>${q}</li>`).join('')}
            </ul>
        </div>`;
    }).join('');

    // Load first script
    showScript('whatsapp');
}

function showScript(channel) {
    currentScriptChannel = channel;
    const scripts = spinData.scripts || {};
    document.getElementById('scriptText').textContent = scripts[channel] || 'Script não disponível.';

    document.querySelectorAll('.script-tab').forEach(tab => {
        const isActive = tab.dataset.scriptTab === channel;
        tab.className = tab.className.replace(/bg-primary\/20 text-primary border-primary\/30|bg-white\/5 text-slate-400 border-white\/10 hover:bg-white\/10/g, '');
        if (isActive) {
            tab.classList.add('bg-primary/20', 'text-primary', 'border-primary/30');
        } else {
            tab.classList.add('bg-white/5', 'text-slate-400', 'border-white/10', 'hover:bg-white/10');
        }
    });
}

function copyScript() {
    const text = document.getElementById('scriptText').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('[onclick="copyScript()"]');
        if (btn) {
            btn.innerHTML = '<span class="material-symbols-outlined text-sm text-operon-energy">check</span>';
            setTimeout(() => { btn.innerHTML = '<span class="material-symbols-outlined text-sm">content_copy</span>'; }, 1500);
        }
    });
}
</script>
