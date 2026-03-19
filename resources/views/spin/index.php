<?php
$pageTitle    = 'SPIN Hub';
$pageSubtitle = 'Scripts de Abordagem & Framework SPIN';
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">SPIN Hub</h2>
            <p class="text-sm text-slate-400 mt-0.5">Scripts de abordagem inteligentes com SPIN framework, playbooks e refinamento por IA</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- ═══ LEFT PANEL: Config ═══ -->
        <div class="lg:col-span-4 xl:col-span-3">
            <div class="bg-brand-surface border border-white/8 rounded-2xl p-5 sticky top-6 flex flex-col gap-5">

                <!-- Lead Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Lead</label>
                    <select id="spinLeadSelect" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all [color-scheme:dark]">
                        <option value="" class="bg-surface text-slate-400">-- Escolha um lead --</option>
                        <?php foreach ($leads as $lead): ?>
                        <option value="<?= $lead['id'] ?>"
                                data-segment="<?= e($lead['segment']) ?>"
                                data-name="<?= e($lead['name']) ?>"
                                class="bg-surface text-primary font-bold">
                            <?= e($lead['name']) ?> (<?= $lead['priority_score'] ?? 0 ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tone Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Tom da Abordagem</label>
                    <div class="grid grid-cols-2 gap-1.5" id="toneSelector">
                        <?php
                        $tones = [
                            'consultivo'  => ['icon' => 'school',        'label' => 'Consultivo'],
                            'direto'      => ['icon' => 'bolt',          'label' => 'Direto'],
                            'elegante'    => ['icon' => 'diamond',       'label' => 'Elegante'],
                            'humano'      => ['icon' => 'favorite',      'label' => 'Humano'],
                            'autoridade'  => ['icon' => 'workspace_premium', 'label' => 'Autoridade'],
                            'curto'       => ['icon' => 'short_text',    'label' => 'Curto'],
                            'storytelling'=> ['icon' => 'auto_stories',  'label' => 'Storytelling'],
                        ];
                        foreach ($tones as $key => $t): ?>
                        <button type="button" onclick="setTone('<?= $key ?>')"
                                data-tone="<?= $key ?>"
                                class="tone-btn flex items-center gap-1.5 px-2.5 py-2 rounded-lg text-[11px] font-bold transition-all border
                                       <?= $key === 'consultivo' ? 'bg-operon-energy/15 text-operon-energy border-operon-energy/30' : 'bg-white/3 text-slate-500 border-white/6 hover:bg-white/6 hover:text-slate-300' ?>">
                            <span class="material-symbols-outlined text-[14px]"><?= $t['icon'] ?></span>
                            <?= $t['label'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Custom Instructions -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Instrução Personalizada <span class="text-slate-600 font-normal">(opcional)</span></label>
                    <textarea id="customInstructions" rows="3" placeholder="Ex: quero uma abordagem mais consultiva, focando na dor de perder clientes para concorrentes..."
                              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all resize-none"></textarea>
                </div>

                <!-- Generate Button -->
                <button id="spinBtn" onclick="generateSpin()"
                        class="w-full flex items-center justify-center gap-2 py-3.5 rounded-xl bg-primary text-bg text-sm font-bold hover:brightness-110 transition-all shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-lg">auto_awesome</span>
                    Gerar SPIN + Scripts
                </button>
                <p class="text-[10px] text-slate-600 text-center -mt-2">Consome tokens de IA por geração</p>

                <!-- Divider -->
                <div class="h-px bg-white/6"></div>

                <!-- Playbooks -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Playbooks de Referência</label>
                        <button onclick="document.getElementById('playbookUploadArea').classList.toggle('hidden')"
                                class="text-[10px] text-operon-energy/60 hover:text-operon-energy font-bold flex items-center gap-1 transition-colors">
                            <span class="material-symbols-outlined text-[13px]">add</span> Adicionar
                        </button>
                    </div>

                    <!-- Upload Area (hidden by default) -->
                    <div id="playbookUploadArea" class="hidden mb-3">
                        <form id="playbookUploadForm" class="flex flex-col gap-2">
                            <input type="text" name="title" placeholder="Nome do playbook (opcional)"
                                   class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-operon-energy/30">
                            <div class="relative">
                                <input type="file" name="playbook" accept=".txt,.md,.pdf,.docx" id="playbookFileInput"
                                       class="w-full text-xs text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-white/10 file:text-slate-300 hover:file:bg-white/15 cursor-pointer">
                            </div>
                            <p class="text-[9px] text-slate-600">Aceita: .txt, .md, .pdf, .docx (max 10MB)</p>
                            <button type="button" onclick="uploadPlaybook()"
                                    class="w-full py-2 rounded-lg bg-white/8 hover:bg-white/12 text-xs font-bold text-slate-300 transition-all flex items-center justify-center gap-1.5" id="playbookUploadBtn">
                                <span class="material-symbols-outlined text-[14px]">upload_file</span> Enviar Playbook
                            </button>
                        </form>
                    </div>

                    <!-- Playbook List -->
                    <div id="playbookList" class="flex flex-col gap-1.5">
                        <?php if (empty($playbooks)): ?>
                        <p class="text-[10px] text-slate-600 text-center py-2">Nenhum playbook enviado. Envie um livro ou framework para a IA usar como referência.</p>
                        <?php else: ?>
                        <?php foreach ($playbooks as $pb): ?>
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-white/3 border border-white/5 group" data-playbook-id="<?= $pb['id'] ?>">
                            <span class="material-symbols-outlined text-[14px] <?= $pb['active'] ? 'text-operon-energy' : 'text-slate-600' ?>">
                                <?= $pb['status'] === 'ready' ? 'menu_book' : 'hourglass_top' ?>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] text-white/70 font-medium truncate"><?= e($pb['title']) ?></p>
                                <p class="text-[9px] text-slate-600"><?= e($pb['file_name']) ?> · <?= $pb['status'] === 'ready' ? count(json_decode($pb['chunks'] ?? '[]', true)) . ' chunks' : 'processando...' ?></p>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="togglePlaybook('<?= $pb['id'] ?>', <?= $pb['active'] ? 'false' : 'true' ?>)"
                                        class="size-6 rounded flex items-center justify-center text-slate-500 hover:text-white transition-colors" title="<?= $pb['active'] ? 'Desativar' : 'Ativar' ?>">
                                    <span class="material-symbols-outlined text-[13px]"><?= $pb['active'] ? 'toggle_on' : 'toggle_off' ?></span>
                                </button>
                                <button onclick="deletePlaybook('<?= $pb['id'] ?>')"
                                        class="size-6 rounded flex items-center justify-center text-slate-500 hover:text-red-400 transition-colors" title="Remover">
                                    <span class="material-symbols-outlined text-[13px]">delete</span>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SPIN Legend -->
                <div class="pt-3 border-t border-white/6">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Framework SPIN</p>
                    <?php
                    $spinDef = [
                        ['letter' => 'S', 'label' => 'Situacao',   'color' => 'text-blue-400',      'bg' => 'bg-blue-500/15', 'desc' => 'Contexto atual'],
                        ['letter' => 'P', 'label' => 'Problema',   'color' => 'text-yellow-400',    'bg' => 'bg-yellow-500/15', 'desc' => 'Dores e dificuldades'],
                        ['letter' => 'I', 'label' => 'Implicacao', 'color' => 'text-red-400',       'bg' => 'bg-red-500/15',  'desc' => 'Impacto do problema'],
                        ['letter' => 'N', 'label' => 'Necessidade','color' => 'text-operon-energy', 'bg' => 'bg-operon-energy/15', 'desc' => 'Guiar para solucao'],
                    ];
                    foreach ($spinDef as $s): ?>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="size-5 rounded-md <?= $s['bg'] ?> <?= $s['color'] ?> font-black text-[10px] flex items-center justify-center flex-shrink-0"><?= $s['letter'] ?></span>
                        <span class="text-[10px] text-slate-400"><strong class="text-white/70"><?= $s['label'] ?></strong> — <?= $s['desc'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ═══ RIGHT PANEL: Results ═══ -->
        <div class="lg:col-span-8 xl:col-span-9 flex flex-col gap-5">

            <!-- Empty state -->
            <div id="spinEmpty" class="flex flex-col items-center justify-center py-20 text-center bg-brand-surface border border-white/8 rounded-2xl">
                <span class="material-symbols-outlined text-5xl text-slate-700 mb-4">auto_awesome</span>
                <h3 class="text-base font-bold text-slate-400 mb-2">Selecione um lead para comecar</h3>
                <p class="text-sm text-slate-600 max-w-md">A IA vai gerar perguntas SPIN + scripts de abordagem personalizados com base no contexto do lead, da sua empresa e dos playbooks de referencia.</p>
            </div>

            <!-- Results container -->
            <div id="spinResults" class="hidden flex flex-col gap-5">

                <!-- SPIN Questions -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-400 text-[18px]">psychology_alt</span>
                            Perguntas SPIN
                        </h3>
                        <span id="spinLeadBadge" class="text-xs text-operon-energy bg-operon-energy/10 px-2.5 py-0.5 rounded-lg font-medium"></span>
                    </div>
                    <div id="spinQuestionsContent" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                </div>

                <!-- Scripts Section -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-operon-energy text-[18px]">edit_note</span>
                            Scripts de Abordagem
                        </h3>
                        <div class="flex items-center gap-1.5" id="toneBadgeArea"></div>
                    </div>

                    <!-- Channel tabs -->
                    <div class="flex gap-2 mb-4">
                        <?php
                        $channelDefs = [
                            'whatsapp' => ['icon' => 'chat',     'label' => 'WhatsApp'],
                            'linkedin' => ['icon' => 'business', 'label' => 'LinkedIn'],
                            'email'    => ['icon' => 'mail',     'label' => 'E-mail'],
                            'coldCall' => ['icon' => 'call',     'label' => 'Ligacao'],
                        ];
                        foreach ($channelDefs as $key => $ch): ?>
                        <button onclick="showScript('<?= $key ?>')"
                                data-script-tab="<?= $key ?>"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all script-tab
                                       <?= $key === 'whatsapp' ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-white/5 text-slate-400 border border-white/10 hover:bg-white/10' ?>">
                            <span class="material-symbols-outlined text-sm"><?= $ch['icon'] ?></span>
                            <?= $ch['label'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Script content -->
                    <div id="scriptContent" class="relative">
                        <div id="scriptText" class="bg-white/[0.02] rounded-xl px-5 py-4 text-[13px] text-slate-200 leading-relaxed min-h-[140px] whitespace-pre-wrap border border-white/5"></div>
                        <div class="absolute top-3 right-3 flex items-center gap-1">
                            <button onclick="copyScript()" class="size-7 rounded-lg bg-white/8 hover:bg-white/15 flex items-center justify-center text-slate-400 hover:text-white transition-all" title="Copiar">
                                <span class="material-symbols-outlined text-sm" id="copyIcon">content_copy</span>
                            </button>
                        </div>
                    </div>

                    <!-- ═══ Refinement Chat ═══ -->
                    <div class="mt-4 pt-4 border-t border-white/6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-[15px] text-slate-500">tune</span>
                            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Refinar Script</h4>
                        </div>

                        <!-- Quick refinement chips -->
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            <?php
                            $quickRefinements = [
                                'Mais natural',
                                'Mais curto',
                                'Mais direto',
                                'Tom premium',
                                'Mais consultivo',
                                'Foca na dor',
                                'Melhora abertura',
                                'CTA mais forte',
                                'Menos agressivo',
                                'Mais humano',
                            ];
                            foreach ($quickRefinements as $qr): ?>
                            <button onclick="quickRefine('<?= $qr ?>')"
                                    class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white/[0.03] text-slate-500 border border-white/6 hover:bg-white/8 hover:text-slate-300 hover:border-white/12 transition-all">
                                <?= $qr ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Refinement input -->
                        <div class="flex gap-2">
                            <input type="text" id="refineInput" placeholder="Ex: melhora a abertura, deixa mais natural, traz mais conexao com a dor..."
                                   class="flex-1 bg-white/[0.03] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-operon-energy/30 focus:ring-1 focus:ring-operon-energy/20 transition-all"
                                   onkeydown="if(event.key==='Enter'){refineScript();event.preventDefault();}">
                            <button onclick="refineScript()" id="refineBtn"
                                    class="px-4 py-2.5 rounded-xl bg-white/8 hover:bg-white/12 text-sm font-bold text-slate-300 hover:text-white transition-all flex items-center gap-1.5 shrink-0">
                                <span class="material-symbols-outlined text-[16px]">auto_fix_high</span>
                                Refinar
                            </button>
                        </div>

                        <!-- Refinement history -->
                        <div id="refineHistory" class="mt-3 flex flex-col gap-1.5 max-h-[200px] overflow-y-auto hidden">
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
let spinData = {};
let currentScriptChannel = 'whatsapp';
let currentTone = 'consultivo';

function setTone(tone) {
    currentTone = tone;
    document.querySelectorAll('.tone-btn').forEach(btn => {
        const isActive = btn.dataset.tone === tone;
        btn.className = btn.className.replace(/bg-operon-energy\/15 text-operon-energy border-operon-energy\/30|bg-white\/3 text-slate-500 border-white\/6 hover:bg-white\/6 hover:text-slate-300/g, '');
        btn.classList.add(...(isActive
            ? ['bg-operon-energy/15', 'text-operon-energy', 'border-operon-energy/30']
            : ['bg-white/3', 'text-slate-500', 'border-white/6', 'hover:bg-white/6', 'hover:text-slate-300']
        ));
    });
}

async function generateSpin() {
    const select = document.getElementById('spinLeadSelect');
    const leadId = select.value;
    if (!leadId) { alert('Selecione um lead.'); return; }

    const btn = document.getElementById('spinBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="ai-spinner"></div><span>Gerando...</span>';
    document.getElementById('spinEmpty').innerHTML = '<div class="flex flex-col items-center justify-center py-20"><div class="ai-spinner" style="width:40px;height:40px;border-width:3px"></div><p class="text-sm text-slate-400 mt-4 animate-pulse">Gerando SPIN + Scripts personalizados...</p></div>';
    document.getElementById('spinEmpty').classList.remove('hidden');
    document.getElementById('spinResults').classList.add('hidden');

    try {
        const res = await operonFetch('/spin', {
            method: 'POST',
            body: JSON.stringify({
                lead_id: leadId,
                tone: currentTone,
                instructions: document.getElementById('customInstructions').value.trim(),
                _csrf: getCsrfToken()
            })
        });

        if (res.error) { alert(res.error); return; }
        spinData = res;
        renderSpin(res, select.options[select.selectedIndex].text);
    } catch (e) {
        alert('Erro de conexao.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-lg">auto_awesome</span>Gerar SPIN + Scripts';
    }
}

function renderSpin(data, leadName) {
    document.getElementById('spinEmpty').classList.add('hidden');
    document.getElementById('spinResults').classList.remove('hidden');
    document.getElementById('spinLeadBadge').textContent = data.lead?.name || leadName;

    // Tone badge
    const toneLabels = { consultivo:'Consultivo', direto:'Direto', elegante:'Elegante', humano:'Humano', autoridade:'Autoridade', curto:'Curto', storytelling:'Storytelling' };
    document.getElementById('toneBadgeArea').innerHTML = `
        <span class="text-[10px] text-slate-500 font-medium">Tom:</span>
        <span class="text-[10px] text-operon-energy bg-operon-energy/10 px-2 py-0.5 rounded-md font-bold">${toneLabels[currentTone] || currentTone}</span>
    `;

    // SPIN questions
    const spin = data.spin || {};
    const phaseMap = {
        s: { label: 'Situacao', color: 'border-blue-400/30 bg-blue-500/5', badge: 'bg-blue-500/15 text-blue-400' },
        p: { label: 'Problema', color: 'border-yellow-400/30 bg-yellow-500/5', badge: 'bg-yellow-500/15 text-yellow-400' },
        i: { label: 'Implicacao', color: 'border-red-400/30 bg-red-500/5', badge: 'bg-red-500/15 text-red-400' },
        n: { label: 'Necessidade', color: 'border-operon-energy/30 bg-operon-energy/5', badge: 'bg-operon-energy/15 text-operon-energy' },
    };

    document.getElementById('spinQuestionsContent').innerHTML = Object.entries(phaseMap).map(([key, phase]) => {
        const qs = spin[key] || [];
        return `
        <div class="border ${phase.color} rounded-xl p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="size-5 rounded-md ${phase.badge} text-[10px] font-black flex items-center justify-center">${key.toUpperCase()}</span>
                <span class="text-xs font-bold text-white">${phase.label}</span>
            </div>
            <ul class="flex flex-col gap-2">
                ${qs.map(q => `<li class="text-xs text-slate-300 leading-relaxed flex gap-2"><span class="text-slate-600 flex-shrink-0 mt-0.5">›</span><span>${q}</span></li>`).join('')}
            </ul>
        </div>`;
    }).join('');

    // Clear refinement history
    document.getElementById('refineHistory').innerHTML = '';
    document.getElementById('refineHistory').classList.add('hidden');
    document.getElementById('refineInput').value = '';

    showScript('whatsapp');
}

function showScript(channel) {
    currentScriptChannel = channel;
    const scripts = spinData.scripts || {};
    document.getElementById('scriptText').textContent = scripts[channel] || 'Script nao disponivel para este canal.';

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
        const icon = document.getElementById('copyIcon');
        icon.textContent = 'check';
        icon.classList.add('text-operon-energy');
        setTimeout(() => { icon.textContent = 'content_copy'; icon.classList.remove('text-operon-energy'); }, 1500);
    });
}

function quickRefine(instruction) {
    document.getElementById('refineInput').value = instruction;
    refineScript();
}

async function refineScript() {
    const input = document.getElementById('refineInput');
    const instruction = input.value.trim();
    if (!instruction) return;

    const currentScript = document.getElementById('scriptText').textContent;
    if (!currentScript || currentScript.startsWith('Script nao')) return;

    const select = document.getElementById('spinLeadSelect');
    const leadId = select.value;
    if (!leadId) return;

    const btn = document.getElementById('refineBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="ai-spinner" style="width:14px;height:14px;border-width:2px"></div><span>Refinando...</span>';

    // Add to history
    const historyEl = document.getElementById('refineHistory');
    historyEl.classList.remove('hidden');
    historyEl.innerHTML += `
        <div class="flex items-start gap-2 p-2 rounded-lg bg-white/[0.02]">
            <span class="material-symbols-outlined text-[12px] text-slate-600 mt-0.5 shrink-0">person</span>
            <p class="text-[11px] text-slate-400">${escapeHtml(instruction)}</p>
        </div>
    `;

    try {
        const res = await operonFetch('/spin/refine', {
            method: 'POST',
            body: JSON.stringify({
                lead_id: leadId,
                channel: currentScriptChannel,
                current_script: currentScript,
                instruction: instruction,
                tone: currentTone,
                _csrf: getCsrfToken()
            })
        });

        if (res.error) { alert(res.error); return; }

        // Update script display
        document.getElementById('scriptText').textContent = res.script;
        spinData.scripts = spinData.scripts || {};
        spinData.scripts[currentScriptChannel] = res.script;

        // Add AI response to history
        historyEl.innerHTML += `
            <div class="flex items-start gap-2 p-2 rounded-lg bg-operon-energy/[0.03]">
                <span class="material-symbols-outlined text-[12px] text-operon-energy/40 mt-0.5 shrink-0">auto_awesome</span>
                <p class="text-[11px] text-slate-400">Script atualizado com sucesso.</p>
            </div>
        `;
        historyEl.scrollTop = historyEl.scrollHeight;

        input.value = '';
    } catch (e) {
        alert('Erro ao refinar script.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">auto_fix_high</span>Refinar';
    }
}

// ── Playbook Management ──

async function uploadPlaybook() {
    const form = document.getElementById('playbookUploadForm');
    const fileInput = document.getElementById('playbookFileInput');
    if (!fileInput.files.length) { alert('Selecione um arquivo.'); return; }

    const btn = document.getElementById('playbookUploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="ai-spinner" style="width:12px;height:12px;border-width:2px"></div><span>Processando...</span>';

    const formData = new FormData();
    formData.append('playbook', fileInput.files[0]);
    formData.append('title', form.querySelector('[name="title"]').value);
    formData.append('_csrf', getCsrfToken());

    try {
        const response = await fetch('/spin/playbook/upload', { method: 'POST', body: formData });
        const res = await response.json();

        if (res.error) { alert(res.error); return; }

        // Add to list
        const list = document.getElementById('playbookList');
        const emptyMsg = list.querySelector('p');
        if (emptyMsg) emptyMsg.remove();

        const pb = res.playbook;
        list.insertAdjacentHTML('beforeend', `
            <div class="flex items-center gap-2 p-2 rounded-lg bg-white/3 border border-white/5 group" data-playbook-id="${pb.id}">
                <span class="material-symbols-outlined text-[14px] text-operon-energy">menu_book</span>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] text-white/70 font-medium truncate">${escapeHtml(pb.title)}</p>
                    <p class="text-[9px] text-slate-600">${escapeHtml(pb.file_name)} · ${pb.chunks} chunks</p>
                </div>
                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="togglePlaybook('${pb.id}', false)" class="size-6 rounded flex items-center justify-center text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-[13px]">toggle_on</span></button>
                    <button onclick="deletePlaybook('${pb.id}')" class="size-6 rounded flex items-center justify-center text-slate-500 hover:text-red-400 transition-colors"><span class="material-symbols-outlined text-[13px]">delete</span></button>
                </div>
            </div>
        `);

        // Reset form
        form.reset();
        document.getElementById('playbookUploadArea').classList.add('hidden');
    } catch (e) {
        alert('Erro ao enviar playbook.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">upload_file</span> Enviar Playbook';
    }
}

async function deletePlaybook(id) {
    if (!confirm('Remover este playbook?')) return;
    try {
        await operonFetch('/spin/playbook/delete', { method: 'POST', body: JSON.stringify({ id, _csrf: getCsrfToken() }) });
        const el = document.querySelector(`[data-playbook-id="${id}"]`);
        if (el) el.remove();
    } catch (e) { alert('Erro ao remover.'); }
}

async function togglePlaybook(id, active) {
    try {
        await operonFetch('/spin/playbook/toggle', { method: 'POST', body: JSON.stringify({ id, active, _csrf: getCsrfToken() }) });
        const el = document.querySelector(`[data-playbook-id="${id}"]`);
        if (el) {
            const icon = el.querySelector('.material-symbols-outlined');
            icon.classList.toggle('text-operon-energy', active);
            icon.classList.toggle('text-slate-600', !active);
        }
    } catch (e) { alert('Erro ao atualizar.'); }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Auto-select lead from URL
(function() {
    const params = new URLSearchParams(window.location.search);
    const leadId = params.get('lead_id');
    if (leadId) {
        const select = document.getElementById('spinLeadSelect');
        select.value = leadId;
    }
})();
</script>
