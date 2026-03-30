<?php
/**
 * Form Builder — Construtor visual de formulários com IA
 * Paleta: lime/surface (coerente com plataforma), sem roxo
 */
$pageTitle = 'Builder — ' . ($form['title'] ?? 'Formulário');
$form = $form ?? [];
$questions = $questions ?? [];
$questionTypes = $questionTypes ?? [];
$csrfToken = csrf_token();
$rawSettings = $form['settings'] ?? '{}';
$settings = json_decode($rawSettings, true);
if (!is_array($settings) || empty($settings)) $settings = [];
$displayMode = $settings['display_mode'] ?? 'continuous';
?>

<div class="flex h-[calc(100vh-72px)] overflow-hidden">

    <!-- Left: Form Builder -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Builder Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke bg-bg flex-shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <a href="/forms" class="p-2 rounded-lg hover:bg-surface2 text-muted hover:text-text transition-all flex-shrink-0">
                    <span class="material-symbols-outlined text-lg">arrow_back</span>
                </a>
                <div class="min-w-0">
                    <input type="text" id="formTitle" value="<?= e($form['title'] ?? '') ?>" placeholder="Título do formulário"
                           class="text-base font-bold text-text bg-transparent border-none outline-none w-full truncate placeholder-subtle"
                           onchange="saveFormMeta()">
                    <input type="text" id="formDescription" value="<?= e($form['description'] ?? '') ?>" placeholder="Descrição breve (opcional)"
                           class="text-xs text-muted bg-transparent border-none outline-none w-full truncate placeholder-subtle mt-0.5"
                           onchange="saveFormMeta()">
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <!-- Display Mode -->
                <div class="flex items-center h-9 bg-surface2 border border-stroke rounded-pill overflow-hidden">
                    <button onclick="setDisplayMode('continuous')" id="modeContBtn" class="flex items-center gap-1 h-full px-3 text-[10px] font-bold uppercase tracking-wider transition-all <?= $displayMode === 'continuous' ? 'bg-lime text-bg' : 'text-muted hover:text-text' ?>">
                        <span class="material-symbols-outlined text-xs">view_stream</span> Corrido
                    </button>
                    <button onclick="setDisplayMode('paginated')" id="modePagBtn" class="flex items-center gap-1 h-full px-3 text-[10px] font-bold uppercase tracking-wider transition-all <?= $displayMode === 'paginated' ? 'bg-lime text-bg' : 'text-muted hover:text-text' ?>">
                        <span class="material-symbols-outlined text-xs">view_carousel</span> Etapas
                    </button>
                </div>

                <!-- Status Badge -->
                <div id="statusBadge" class="px-3 py-1 text-[10px] font-bold rounded-pill uppercase tracking-wider cursor-pointer transition-all
                    <?= $form['status'] === 'published' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20' ?>"
                     onclick="togglePublish()">
                    <?= $form['status'] === 'published' ? 'Publicado' : 'Rascunho' ?>
                </div>
                <?php if ($form['status'] === 'published'): ?>
                <button onclick="copyLink()" class="flex items-center gap-1.5 h-9 px-4 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-emerald-400 transition-all" title="Copiar link público">
                    <span class="material-symbols-outlined text-sm">link</span>
                    Link
                </button>
                <?php endif; ?>
                <a href="/forms/<?= e($form['id']) ?>/fill" class="flex items-center gap-1.5 h-9 px-4 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-text transition-all">
                    <span class="material-symbols-outlined text-sm">assignment</span>
                    Preencher
                </a>
                <button onclick="saveAllQuestions()" id="saveBtn" class="flex items-center gap-1.5 h-9 px-5 rounded-pill bg-lime text-bg text-xs font-bold hover:brightness-110 transition-all shadow-glow">
                    <span class="material-symbols-outlined text-sm">save</span>
                    Salvar
                </button>
            </div>
        </div>

        <!-- Builder Content -->
        <div class="flex-1 overflow-y-auto p-6" id="builderArea">

            <!-- AI Quick Actions -->
            <div class="mb-6 bg-surface border border-stroke rounded-2xl p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-lime/10 flex items-center justify-center border border-lime/20">
                        <span class="material-symbols-outlined text-lime text-base">auto_awesome</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-text">Gerador de Perguntas com IA</h3>
                        <p class="text-xs text-muted">Gere perguntas inteligentes baseadas no contexto da sua empresa</p>
                    </div>
                    <button onclick="toggleAiChat()" class="ml-auto h-8 px-4 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-text transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">chat</span> Chat IA
                    </button>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button onclick="aiGenerate('consultivo')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">psychology</span> Consultivo
                    </button>
                    <button onclick="aiGenerate('direto')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">bolt</span> Direto
                    </button>
                    <button onclick="aiGenerate('discovery')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">explore</span> Discovery
                    </button>
                    <button onclick="aiGenerate('high_ticket')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">diamond</span> High Ticket
                    </button>
                    <button onclick="aiGenerate('estrategico')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">strategy</span> Estratégico
                    </button>
                    <button onclick="aiGenerate('diagnostico')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">stethoscope</span> Diagnóstico
                    </button>
                    <button onclick="aiGenerate('humano')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">emoji_people</span> Mais Humano
                    </button>
                    <button onclick="aiGenerate('foco_dor')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">healing</span> Foco em Dor
                    </button>
                    <button onclick="aiGenerate('foco_oportunidade')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">trending_up</span> Oportunidade
                    </button>
                    <button onclick="aiGenerate('qualificacao_rapida')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">speed</span> Rápido
                    </button>
                    <button onclick="aiGenerate('profundidade')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">layers</span> Profundidade
                    </button>
                    <button onclick="aiGenerate('sofisticado')" class="ai-style-btn h-8 px-3.5 rounded-pill bg-surface2 border border-stroke text-xs font-medium text-muted hover:text-lime hover:border-lime/30 transition-all">
                        <span class="material-symbols-outlined text-xs mr-1 align-middle">workspace_premium</span> Sofisticado
                    </button>
                </div>
            </div>

            <!-- AI Progress Overlay -->
            <div id="aiProgressOverlay" class="hidden mb-6">
                <div class="bg-surface border border-lime/20 rounded-2xl p-6 shadow-glow">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-lime/10 flex items-center justify-center border border-lime/20">
                            <span class="material-symbols-outlined text-lime text-lg animate-pulse">auto_awesome</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-text">Gerando perguntas com IA</h3>
                            <p id="aiProgressLabel" class="text-xs text-muted mt-0.5">Iniciando...</p>
                        </div>
                        <span id="aiProgressPct" class="text-lg font-black text-lime">0%</span>
                    </div>
                    <div class="w-full h-2 bg-surface3 rounded-full overflow-hidden">
                        <div id="aiProgressBar" class="h-full bg-lime rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
                    </div>
                    <div id="aiProgressSteps" class="mt-4 space-y-2">
                        <!-- Steps rendered by JS -->
                    </div>
                </div>
            </div>

            <!-- Questions List -->
            <div id="questionsContainer" class="space-y-3">
                <!-- Rendered by JS -->
            </div>

            <!-- Add Question Button -->
            <button onclick="addQuestion()" class="flex items-center gap-2 w-full justify-center py-3 mt-4 rounded-xl border-2 border-dashed border-stroke hover:border-lime/40 text-muted hover:text-lime text-sm font-medium transition-all">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                Adicionar Pergunta
            </button>
        </div>
    </div>

    <!-- Right: AI Chat Panel -->
    <div id="aiChatPanel" class="hidden w-[380px] border-l border-stroke bg-surface flex flex-col flex-shrink-0">
        <div class="flex items-center justify-between px-5 py-4 border-b border-stroke flex-shrink-0">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-lime/10 flex items-center justify-center border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-sm">auto_awesome</span>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-text">Assistente IA</h4>
                    <p class="text-[10px] text-muted">Refine seu formulário</p>
                </div>
            </div>
            <button onclick="toggleAiChat()" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-3">
            <div class="flex gap-2">
                <div class="w-7 h-7 rounded-full bg-lime/10 flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-xs">auto_awesome</span>
                </div>
                <div class="bg-surface2 rounded-xl rounded-tl-sm px-3 py-2 text-xs text-muted leading-relaxed max-w-[280px]">
                    Olá! Sou seu assistente para formulários de qualificação. Me diga como quer ajustar suas perguntas:<br><br>
                    <span class="text-text font-medium">Exemplos:</span><br>
                    - "quero perguntas mais consultivas"<br>
                    - "foco em identificar dor do lead"<br>
                    - "menos perguntas, mais diretas"<br>
                    - "adicione seção sobre orçamento"
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-stroke flex-shrink-0">
            <div class="flex items-center gap-2">
                <input type="text" id="chatInput" placeholder="Ex: quero perguntas mais curtas..."
                       class="flex-1 h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all"
                       onkeydown="if(event.key==='Enter')sendChatMessage()">
                <button onclick="sendChatMessage()" id="chatSendBtn" class="w-10 h-10 rounded-xl bg-lime/20 hover:bg-lime/30 flex items-center justify-center text-lime transition-all border border-lime/20">
                    <span class="material-symbols-outlined text-base">send</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const FORM_ID = '<?= e($form['id']) ?>';
const CSRF = '<?= $csrfToken ?>';
const QUESTION_TYPES = <?= json_encode($questionTypes) ?>;
let currentDisplayMode = '<?= $displayMode ?>';

let questions = <?= json_encode(array_map(function($q) {
    $q['options'] = json_decode($q['options'] ?? '[]', true) ?: [];
    return $q;
}, $questions)) ?>;

let unsavedChanges = false;

// ─── Render ──────────────────────────────────────────
function renderQuestions() {
    const container = document.getElementById('questionsContainer');
    if (questions.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-muted">
                <span class="material-symbols-outlined text-4xl mb-3 block">quiz</span>
                <p class="text-sm font-medium mb-1">Nenhuma pergunta ainda</p>
                <p class="text-xs">Use o gerador IA ou adicione perguntas manualmente</p>
            </div>`;
        return;
    }

    let html = '';
    let currentSection = '';

    questions.forEach((q, i) => {
        if (q.section_title && q.section_title !== currentSection) {
            currentSection = q.section_title;
            html += `
            <div class="flex items-center gap-2 pt-4 pb-1">
                <input type="text" value="${escHtml(currentSection)}" placeholder="Nome da seção"
                       class="text-xs font-bold text-lime uppercase tracking-wider bg-transparent border-none outline-none flex-1"
                       onchange="updateQuestion(${i}, 'section_title', this.value)">
                <div class="flex-1 h-px bg-stroke"></div>
            </div>`;
        }

        const typeInfo = QUESTION_TYPES[q.type] || { label: q.type, icon: 'help' };
        const hasOptions = ['single_choice', 'multiple_choice', 'select'].includes(q.type);

        html += `
        <div class="question-card bg-surface border border-stroke rounded-xl p-4 hover:border-white/10 transition-all group" data-index="${i}">
            <div class="flex items-start gap-3">
                <div class="flex flex-col items-center gap-1 pt-1 flex-shrink-0">
                    <span class="text-[10px] font-bold text-subtle w-5 text-center">${i + 1}</span>
                    <button class="cursor-grab text-subtle hover:text-muted" title="Arrastar">
                        <span class="material-symbols-outlined text-sm">drag_indicator</span>
                    </button>
                </div>

                <div class="flex-1 min-w-0 space-y-3">
                    <input type="text" value="${escHtml(q.label)}" placeholder="Texto da pergunta..."
                           class="w-full text-sm font-medium text-text bg-transparent border-none outline-none placeholder-subtle"
                           onchange="updateQuestion(${i}, 'label', this.value)">

                    <div class="flex items-center gap-3 flex-wrap">
                        <select class="h-8 px-3 bg-surface2 border border-stroke rounded-lg text-xs text-muted focus:border-lime/40 outline-none appearance-none cursor-pointer"
                                onchange="updateQuestion(${i}, 'type', this.value)">
                            ${Object.entries(QUESTION_TYPES).map(([k, v]) =>
                                `<option value="${k}" ${k === q.type ? 'selected' : ''}>${v.label}</option>`
                            ).join('')}
                        </select>

                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" ${q.is_required ? 'checked' : ''}
                                   class="sr-only peer"
                                   onchange="updateQuestion(${i}, 'is_required', this.checked ? 1 : 0)">
                            <div class="w-8 h-4 rounded-full bg-surface3 peer-checked:bg-lime/30 transition-all relative">
                                <div class="w-3 h-3 rounded-full bg-muted peer-checked:bg-lime absolute top-0.5 left-0.5 peer-checked:left-[18px] transition-all"></div>
                            </div>
                            <span class="text-[10px] font-medium text-muted uppercase tracking-wider">Obrigatória</span>
                        </label>

                        <input type="text" value="${escHtml(q.section_title || '')}" placeholder="Seção..."
                               class="h-8 px-3 bg-surface2 border border-stroke rounded-lg text-xs text-muted focus:border-lime/40 outline-none w-32"
                               onchange="updateQuestion(${i}, 'section_title', this.value)">
                    </div>

                    ${hasOptions ? renderOptions(q, i) : ''}

                    <div class="flex gap-2">
                        <input type="text" value="${escHtml(q.placeholder || '')}" placeholder="Placeholder..."
                               class="flex-1 h-8 px-3 bg-surface2/50 border border-stroke/50 rounded-lg text-xs text-subtle focus:border-lime/40 outline-none"
                               onchange="updateQuestion(${i}, 'placeholder', this.value)">
                        <input type="text" value="${escHtml(q.help_text || '')}" placeholder="Texto de ajuda..."
                               class="flex-1 h-8 px-3 bg-surface2/50 border border-stroke/50 rounded-lg text-xs text-subtle focus:border-lime/40 outline-none"
                               onchange="updateQuestion(${i}, 'help_text', this.value)">
                    </div>
                </div>

                <div class="flex flex-col gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                    ${i > 0 ? `<button onclick="moveQuestion(${i}, -1)" class="p-1 rounded hover:bg-surface3 text-subtle hover:text-text transition-all"><span class="material-symbols-outlined text-sm">arrow_upward</span></button>` : ''}
                    ${i < questions.length - 1 ? `<button onclick="moveQuestion(${i}, 1)" class="p-1 rounded hover:bg-surface3 text-subtle hover:text-text transition-all"><span class="material-symbols-outlined text-sm">arrow_downward</span></button>` : ''}
                    <button onclick="duplicateQuestion(${i})" class="p-1 rounded hover:bg-surface3 text-subtle hover:text-text transition-all"><span class="material-symbols-outlined text-sm">content_copy</span></button>
                    <button onclick="removeQuestion(${i})" class="p-1 rounded hover:bg-red-500/10 text-subtle hover:text-red-400 transition-all"><span class="material-symbols-outlined text-sm">delete</span></button>
                </div>
            </div>
        </div>`;
    });

    container.innerHTML = html;
}

function renderOptions(q, qIndex) {
    const opts = q.options || [];
    let html = '<div class="space-y-1.5">';
    opts.forEach((opt, oi) => {
        html += `
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-subtle">${q.type === 'single_choice' ? 'radio_button_unchecked' : 'check_box_outline_blank'}</span>
            <input type="text" value="${escHtml(opt)}" placeholder="Opção ${oi + 1}"
                   class="flex-1 h-7 px-2 bg-surface2/50 border-b border-stroke/50 text-xs text-text outline-none focus:border-lime/40"
                   onchange="updateOption(${qIndex}, ${oi}, this.value)">
            <button onclick="removeOption(${qIndex}, ${oi})" class="text-subtle hover:text-red-400 transition-all">
                <span class="material-symbols-outlined text-xs">close</span>
            </button>
        </div>`;
    });
    html += `
        <button onclick="addOption(${qIndex})" class="flex items-center gap-1 text-xs text-muted hover:text-lime transition-all mt-1">
            <span class="material-symbols-outlined text-xs">add</span> Adicionar opção
        </button>
    </div>`;
    return html;
}

// ─── Question Operations ─────────────────────────────
function addQuestion() {
    questions.push({
        section_title: '', label: '', type: 'short_text', options: [],
        placeholder: '', help_text: '', is_required: 0, sort_order: questions.length,
    });
    unsavedChanges = true;
    renderQuestions();
    const area = document.getElementById('builderArea');
    area.scrollTop = area.scrollHeight;
}

function updateQuestion(index, field, value) {
    questions[index][field] = value;
    if (field === 'type' && ['single_choice', 'multiple_choice', 'select'].includes(value) && (!questions[index].options || !questions[index].options.length)) {
        questions[index].options = ['Opção 1', 'Opção 2'];
    }
    unsavedChanges = true;
    if (field === 'type') renderQuestions();
}

function removeQuestion(index) { questions.splice(index, 1); unsavedChanges = true; renderQuestions(); }
function duplicateQuestion(index) {
    const copy = JSON.parse(JSON.stringify(questions[index]));
    copy.label += ' (cópia)';
    questions.splice(index + 1, 0, copy);
    unsavedChanges = true; renderQuestions();
}
function moveQuestion(index, dir) {
    const n = index + dir;
    if (n < 0 || n >= questions.length) return;
    [questions[index], questions[n]] = [questions[n], questions[index]];
    unsavedChanges = true; renderQuestions();
}
function addOption(qi) {
    if (!questions[qi].options) questions[qi].options = [];
    questions[qi].options.push('Nova opção'); unsavedChanges = true; renderQuestions();
}
function updateOption(qi, oi, val) { questions[qi].options[oi] = val; unsavedChanges = true; }
function removeOption(qi, oi) { questions[qi].options.splice(oi, 1); unsavedChanges = true; renderQuestions(); }

// ─── Display Mode ────────────────────────────────────
async function setDisplayMode(mode) {
    currentDisplayMode = mode;
    document.getElementById('modeContBtn').className = 'flex items-center gap-1 h-full px-3 text-[10px] font-bold uppercase tracking-wider transition-all ' + (mode === 'continuous' ? 'bg-lime text-bg' : 'text-muted hover:text-text');
    document.getElementById('modePagBtn').className = 'flex items-center gap-1 h-full px-3 text-[10px] font-bold uppercase tracking-wider transition-all ' + (mode === 'paginated' ? 'bg-lime text-bg' : 'text-muted hover:text-text');

    const settings = { display_mode: mode };
    await fetch('/forms/' + FORM_ID + '/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(CSRF) + '&ajax=1&settings=' + encodeURIComponent(JSON.stringify(settings)),
    });
    showToast('Modo "' + (mode === 'continuous' ? 'Corrido' : 'Etapas') + '" salvo. Recarregue o link público.', 'success');
}

// ─── Save ────────────────────────────────────────────
async function saveAllQuestions() {
    const btn = document.getElementById('saveBtn');
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Salvando...';
    btn.disabled = true;
    try {
        const res = await fetch('/forms/' + FORM_ID + '/questions', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ questions, _csrf: CSRF }),
        });
        const data = await res.json();
        if (data.ok) { showToast('Formulário salvo!', 'success'); unsavedChanges = false; }
        else showToast('Erro ao salvar', 'error');
    } catch (e) { showToast('Erro de conexão', 'error'); }
    btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Salvar';
    btn.disabled = false;
}

async function saveFormMeta() {
    const title = document.getElementById('formTitle').value;
    const description = document.getElementById('formDescription').value;
    await fetch('/forms/' + FORM_ID + '/update', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(CSRF) + '&ajax=1&title=' + encodeURIComponent(title) + '&description=' + encodeURIComponent(description),
    });
}

async function togglePublish() {
    const res = await fetch('/forms/' + FORM_ID + '/toggle', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(CSRF),
    });
    const data = await res.json();
    if (data.ok) location.reload();
}

function copyLink() {
    const slug = '<?= e($form['public_slug'] ?? '') ?>';
    navigator.clipboard.writeText(window.location.origin + '/f/' + slug);
    showToast('Link copiado!', 'success');
}

// ─── AI Generate with Progress ───────────────────────
const AI_PROGRESS_STEPS = [
    { label: 'Analisando o contexto da empresa...', icon: 'search', pct: 15 },
    { label: 'Mapeando perfil do cliente ideal...', icon: 'person_search', pct: 30 },
    { label: 'Estruturando perguntas de qualificação...', icon: 'edit_note', pct: 50 },
    { label: 'Refinando lógica de qualificação...', icon: 'tune', pct: 70 },
    { label: 'Organizando seções e tipos de campo...', icon: 'dashboard_customize', pct: 85 },
    { label: 'Finalizando formulário...', icon: 'check_circle', pct: 95 },
];

function showAiProgress() {
    const overlay = document.getElementById('aiProgressOverlay');
    overlay.classList.remove('hidden');
    document.querySelectorAll('.ai-style-btn').forEach(b => { b.disabled = true; b.classList.add('opacity-50', 'pointer-events-none'); });

    const stepsContainer = document.getElementById('aiProgressSteps');
    stepsContainer.innerHTML = AI_PROGRESS_STEPS.map((s, i) => `
        <div id="aiStep${i}" class="flex items-center gap-3 opacity-30 transition-all duration-500">
            <div class="w-6 h-6 rounded-lg bg-surface3 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-xs text-subtle" id="aiStepIcon${i}">${s.icon}</span>
            </div>
            <span class="text-xs text-subtle" id="aiStepLabel${i}">${s.label}</span>
        </div>
    `).join('');

    animateProgress(0);
}

function animateProgress(stepIndex) {
    if (stepIndex >= AI_PROGRESS_STEPS.length) return;
    const step = AI_PROGRESS_STEPS[stepIndex];

    // Activate step
    const stepEl = document.getElementById('aiStep' + stepIndex);
    if (stepEl) {
        stepEl.classList.remove('opacity-30');
        stepEl.classList.add('opacity-100');
        const icon = document.getElementById('aiStepIcon' + stepIndex);
        if (icon) icon.classList.replace('text-subtle', 'text-lime');
        const label = document.getElementById('aiStepLabel' + stepIndex);
        if (label) label.classList.replace('text-subtle', 'text-muted');
    }

    // Update bar
    document.getElementById('aiProgressBar').style.width = step.pct + '%';
    document.getElementById('aiProgressPct').textContent = step.pct + '%';
    document.getElementById('aiProgressLabel').textContent = step.label;

    // Mark previous as complete
    if (stepIndex > 0) {
        const prevIcon = document.getElementById('aiStepIcon' + (stepIndex - 1));
        if (prevIcon) { prevIcon.textContent = 'check_circle'; prevIcon.classList.replace('text-lime', 'text-emerald-400'); }
    }

    window._aiProgressTimer = setTimeout(() => animateProgress(stepIndex + 1), 1200 + Math.random() * 800);
}

function hideAiProgress(success) {
    clearTimeout(window._aiProgressTimer);
    if (success) {
        document.getElementById('aiProgressBar').style.width = '100%';
        document.getElementById('aiProgressPct').textContent = '100%';
        document.getElementById('aiProgressLabel').textContent = 'Concluído!';
    }
    setTimeout(() => {
        document.getElementById('aiProgressOverlay').classList.add('hidden');
        document.querySelectorAll('.ai-style-btn').forEach(b => { b.disabled = false; b.classList.remove('opacity-50', 'pointer-events-none'); });
    }, success ? 800 : 300);
}

async function aiGenerate(style) {
    showAiProgress();

    try {
        const res = await fetch('/forms/' + FORM_ID + '/ai/generate', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ style, context: '', _csrf: CSRF }),
        });
        const data = await res.json();
        if (data.ok && data.questions) {
            questions = data.questions.map((q, i) => ({
                section_title: q.section_title || '', label: q.label || '',
                type: q.type || 'short_text', options: q.options || [],
                placeholder: q.placeholder || '', help_text: q.help_text || '',
                is_required: q.is_required ?? 1, sort_order: i,
            }));
            unsavedChanges = true;
            hideAiProgress(true);
            setTimeout(() => {
                renderQuestions();
                showToast(data.questions.length + ' perguntas geradas com sucesso!', 'success');
            }, 900);
        } else {
            hideAiProgress(false);
            showToast(data.error || 'Erro ao gerar', 'error');
        }
    } catch (e) {
        hideAiProgress(false);
        showToast('Erro de conexão', 'error');
    }
}

// ─── AI Chat ─────────────────────────────────────────
function toggleAiChat() { document.getElementById('aiChatPanel').classList.toggle('hidden'); }

async function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;

    addChatMessage(message, 'user');
    input.value = '';

    const sendBtn = document.getElementById('chatSendBtn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">progress_activity</span>';

    try {
        const res = await fetch('/forms/' + FORM_ID + '/ai/refine', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, current_questions: questions, _csrf: CSRF }),
        });
        const data = await res.json();
        if (data.ok && data.questions) {
            questions = data.questions.map((q, i) => ({
                section_title: q.section_title || '', label: q.label || '',
                type: q.type || 'short_text', options: q.options || [],
                placeholder: q.placeholder || '', help_text: q.help_text || '',
                is_required: q.is_required ?? 1, sort_order: i,
            }));
            unsavedChanges = true;
            renderQuestions();
            addChatMessage(data.explanation || 'Perguntas atualizadas!', 'ai');
        } else {
            addChatMessage(data.error || 'Erro ao processar', 'ai');
        }
    } catch (e) { addChatMessage('Erro de conexão com a IA', 'ai'); }

    sendBtn.disabled = false;
    sendBtn.innerHTML = '<span class="material-symbols-outlined text-base">send</span>';
}

function addChatMessage(text, sender) {
    const container = document.getElementById('chatMessages');
    const isAi = sender === 'ai';
    const msgHtml = isAi
        ? `<div class="flex gap-2">
            <div class="w-7 h-7 rounded-full bg-lime/10 flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                <span class="material-symbols-outlined text-lime text-xs">auto_awesome</span>
            </div>
            <div class="bg-surface2 rounded-xl rounded-tl-sm px-3 py-2 text-xs text-muted leading-relaxed max-w-[280px]">${escHtml(text)}</div>
           </div>`
        : `<div class="flex gap-2 justify-end">
            <div class="bg-lime/10 border border-lime/20 rounded-xl rounded-tr-sm px-3 py-2 text-xs text-text leading-relaxed max-w-[260px]">${escHtml(text)}</div>
           </div>`;
    container.insertAdjacentHTML('beforeend', msgHtml);
    container.scrollTop = container.scrollHeight;
}

// ─── Helpers ─────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(msg, type) {
    const colors = { success: 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400', error: 'bg-red-500/20 border-red-500/30 text-red-400', info: 'bg-surface2 border-stroke text-muted' };
    const icons = { success: 'check_circle', error: 'error', info: 'info' };
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-6 right-6 z-50 border px-4 py-2.5 rounded-xl text-sm font-medium shadow-lg backdrop-blur-sm ' + (colors[type] || colors.info);
    toast.innerHTML = '<span class="material-symbols-outlined text-sm align-middle mr-1">' + (icons[type] || 'info') + '</span> ' + escHtml(msg);
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

window.addEventListener('beforeunload', (e) => { if (unsavedChanges) { e.preventDefault(); e.returnValue = ''; } });
renderQuestions();
</script>
