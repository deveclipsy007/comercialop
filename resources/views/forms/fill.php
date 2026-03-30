<?php
/**
 * Preenchimento Interno — Vendedor preenche durante call/reunião
 * Layout: app (com sidebar, auth)
 */
$pageTitle = 'Preencher — ' . ($form['title'] ?? 'Formulário');
$form = $form ?? [];
$questions = $questions ?? [];
$lead = $lead ?? null;
$leads = $leads ?? [];
?>

<div class="p-4 md:p-8 max-w-4xl mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
        <a href="/forms" class="p-2 rounded-lg hover:bg-surface2 text-muted hover:text-text transition-all">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
        </a>
        <div class="flex-1">
            <h2 class="text-lg font-bold text-text flex items-center gap-2">
                <span class="material-symbols-outlined text-lime text-lg">assignment</span>
                <?= e($form['title']) ?>
            </h2>
            <p class="text-xs text-muted mt-0.5">Preencha as respostas durante a call ou reunião com o lead.</p>
        </div>
        <span class="px-3 py-1 text-[10px] font-bold rounded-pill bg-blue-500/10 text-blue-400 border border-blue-500/20 uppercase tracking-wider">Modo Interno</span>
    </div>

    <!-- Lead Selector -->
    <div class="bg-surface border border-stroke rounded-2xl p-5 mb-5">
        <h3 class="text-sm font-bold text-text mb-3 flex items-center gap-2">
            <span class="material-symbols-outlined text-lime text-sm">person_search</span>
            Vincular a um Lead
        </h3>
        <select id="leadSelect" class="w-full h-11 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none appearance-none">
            <option value="">Sem lead vinculado (preenchimento avulso)</option>
            <?php foreach ($leads as $l): ?>
            <option value="<?= e($l['id']) ?>" <?= ($lead && $lead['id'] === $l['id']) ? 'selected' : '' ?>>
                <?= e($l['name']) ?> <?= !empty($l['company']) ? '— ' . e($l['company']) : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($lead): ?>
        <div class="flex items-center gap-3 mt-3 p-3 bg-lime/5 border border-lime/10 rounded-xl">
            <div class="w-8 h-8 rounded-full bg-lime/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-lime text-sm">person</span>
            </div>
            <div class="text-xs">
                <span class="font-bold text-text"><?= e($lead['name']) ?></span>
                <?php if (!empty($lead['company'])): ?>
                <span class="text-muted"> — <?= e($lead['company']) ?></span>
                <?php endif; ?>
                <?php if (!empty($lead['email'])): ?>
                <span class="text-subtle block"><?= e($lead['email']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Questions -->
    <div id="fillQuestions" class="space-y-4">
        <?php
        $currentSection = '';
        foreach ($questions as $i => $q):
            $options = json_decode($q['options'] ?? '[]', true) ?: [];
            $isNewSection = $q['section_title'] && $q['section_title'] !== $currentSection;
            if ($isNewSection) $currentSection = $q['section_title'];
        ?>
        <?php if ($isNewSection): ?>
        <div class="flex items-center gap-3 pt-4 pb-1">
            <span class="text-xs font-bold text-lime uppercase tracking-wider"><?= e($currentSection) ?></span>
            <div class="flex-1 h-px bg-stroke"></div>
        </div>
        <?php endif; ?>

        <div class="bg-surface border border-stroke rounded-xl p-5 transition-all hover:border-white/10">
            <div class="flex items-start gap-3 mb-3">
                <span class="w-6 h-6 rounded-lg bg-surface2 flex items-center justify-center text-[10px] font-bold text-muted flex-shrink-0 mt-0.5"><?= $i + 1 ?></span>
                <div class="flex-1">
                    <label class="text-sm font-semibold text-text">
                        <?= e($q['label']) ?>
                        <?php if ($q['is_required']): ?><span class="text-red-400 ml-0.5">*</span><?php endif; ?>
                    </label>
                    <?php if (!empty($q['help_text'])): ?>
                    <p class="text-xs text-subtle mt-0.5"><?= e($q['help_text']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php switch($q['type']):
                case 'short_text': ?>
                <input type="text" data-qid="<?= e($q['id']) ?>" placeholder="<?= e($q['placeholder'] ?: 'Resposta...') ?>"
                       class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                <?php break; case 'long_text': ?>
                <textarea data-qid="<?= e($q['id']) ?>" rows="3" placeholder="<?= e($q['placeholder'] ?: 'Resposta detalhada...') ?>"
                          class="answer-field w-full px-4 py-2.5 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all resize-y"></textarea>
                <?php break; case 'single_choice': ?>
                <div class="space-y-2">
                    <?php foreach ($options as $opt): ?>
                    <label class="flex items-center gap-3 p-3 bg-surface2 border border-stroke rounded-xl cursor-pointer hover:border-lime/30 transition-all has-[:checked]:border-lime/50 has-[:checked]:bg-lime/5">
                        <input type="radio" name="q_<?= e($q['id']) ?>" data-qid="<?= e($q['id']) ?>" value="<?= e($opt) ?>" class="answer-radio w-4 h-4 text-lime bg-surface2 border-stroke focus:ring-lime/20">
                        <span class="text-sm text-muted"><?= e($opt) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php break; case 'multiple_choice': ?>
                <div class="space-y-2">
                    <?php foreach ($options as $opt): ?>
                    <label class="flex items-center gap-3 p-3 bg-surface2 border border-stroke rounded-xl cursor-pointer hover:border-lime/30 transition-all has-[:checked]:border-lime/50 has-[:checked]:bg-lime/5">
                        <input type="checkbox" data-qid="<?= e($q['id']) ?>" value="<?= e($opt) ?>" class="answer-check w-4 h-4 text-lime bg-surface2 border-stroke rounded focus:ring-lime/20">
                        <span class="text-sm text-muted"><?= e($opt) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php break; case 'number': ?>
                <input type="number" data-qid="<?= e($q['id']) ?>" placeholder="<?= e($q['placeholder'] ?: '0') ?>"
                       class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                <?php break; case 'date': ?>
                <input type="date" data-qid="<?= e($q['id']) ?>"
                       class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                <?php break; case 'select': ?>
                <select data-qid="<?= e($q['id']) ?>" class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none appearance-none">
                    <option value="">Selecione...</option>
                    <?php foreach ($options as $opt): ?>
                    <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php break; case 'checkbox': ?>
                <label class="flex items-center gap-3 p-3 bg-surface2 border border-stroke rounded-xl cursor-pointer">
                    <input type="checkbox" data-qid="<?= e($q['id']) ?>" value="1" class="answer-field w-4 h-4 text-lime bg-surface2 border-stroke rounded focus:ring-lime/20">
                    <span class="text-sm text-muted">Sim</span>
                </label>
                <?php break; case 'rating': ?>
                <div class="flex gap-2" id="fill-rating-<?= e($q['id']) ?>">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                    <button type="button" onclick="setFillRating('<?= e($q['id']) ?>', <?= $s ?>)"
                            class="fill-rating-star w-9 h-9 rounded-xl bg-surface2 border border-stroke flex items-center justify-center text-subtle hover:text-lime hover:border-lime/30 transition-all"
                            data-value="<?= $s ?>">
                        <span class="material-symbols-outlined text-base">star</span>
                    </button>
                    <?php endfor; ?>
                    <input type="hidden" data-qid="<?= e($q['id']) ?>" class="answer-field" value="">
                </div>
                <?php break; case 'email': ?>
                <input type="email" data-qid="<?= e($q['id']) ?>" placeholder="<?= e($q['placeholder'] ?: 'email@exemplo.com') ?>"
                       class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                <?php break; case 'phone': ?>
                <input type="tel" data-qid="<?= e($q['id']) ?>" placeholder="<?= e($q['placeholder'] ?: '(00) 00000-0000') ?>"
                       class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                <?php break; case 'url': ?>
                <input type="url" data-qid="<?= e($q['id']) ?>" placeholder="<?= e($q['placeholder'] ?: 'https://...') ?>"
                       class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                <?php break; default: ?>
                <input type="text" data-qid="<?= e($q['id']) ?>" placeholder="Resposta..."
                       class="answer-field w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
            <?php endswitch; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Submit -->
    <div class="mt-6 flex items-center gap-3">
        <button onclick="submitInternalForm()" id="fillSubmitBtn"
                class="flex-1 h-12 rounded-xl bg-lime text-bg text-sm font-black hover:brightness-110 transition-all shadow-glow flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-lg">save</span>
            Salvar Respostas
        </button>
        <a href="/forms" class="h-12 px-6 rounded-xl bg-surface2 border border-stroke text-sm text-muted hover:text-text flex items-center transition-all">Cancelar</a>
    </div>
</div>

<script>
const FORM_ID = '<?= e($form['id']) ?>';
const CSRF = '<?= csrf_token() ?>';

function setFillRating(qId, value) {
    const container = document.getElementById('fill-rating-' + qId);
    container.querySelector('input[type="hidden"]').value = value;
    container.querySelectorAll('.fill-rating-star').forEach(star => {
        const v = parseInt(star.dataset.value);
        if (v <= value) {
            star.classList.add('text-lime', 'border-lime/50', 'bg-lime/10');
            star.classList.remove('text-subtle', 'border-stroke', 'bg-surface2');
        } else {
            star.classList.remove('text-lime', 'border-lime/50', 'bg-lime/10');
            star.classList.add('text-subtle', 'border-stroke', 'bg-surface2');
        }
    });
}

async function submitInternalForm() {
    const btn = document.getElementById('fillSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">progress_activity</span> Salvando...';

    // Collect answers
    const answers = {};

    // Text/select/date/number/hidden fields
    document.querySelectorAll('.answer-field').forEach(el => {
        const qid = el.dataset.qid;
        if (!qid) return;
        if (el.type === 'checkbox') {
            answers[qid] = el.checked ? '1' : '0';
        } else {
            answers[qid] = el.value;
        }
    });

    // Radio buttons
    document.querySelectorAll('.answer-radio:checked').forEach(el => {
        answers[el.dataset.qid] = el.value;
    });

    // Checkboxes (multiple choice)
    const multiAnswers = {};
    document.querySelectorAll('.answer-check:checked').forEach(el => {
        const qid = el.dataset.qid;
        if (!multiAnswers[qid]) multiAnswers[qid] = [];
        multiAnswers[qid].push(el.value);
    });
    Object.entries(multiAnswers).forEach(([qid, vals]) => {
        answers[qid] = vals;
    });

    const leadId = document.getElementById('leadSelect').value;

    try {
        const res = await fetch('/forms/' + FORM_ID + '/submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ answers, lead_id: leadId, _csrf: CSRF }),
        });
        const data = await res.json();
        if (data.ok) {
            // Show success
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-6 right-6 z-50 bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 px-5 py-3 rounded-xl text-sm font-medium shadow-lg backdrop-blur-sm';
            toast.innerHTML = '<span class="material-symbols-outlined text-sm align-middle mr-1">check_circle</span> Respostas salvas com sucesso!';
            document.body.appendChild(toast);
            setTimeout(() => { window.location.href = '/forms'; }, 1500);
        } else {
            alert(data.error || 'Erro ao salvar');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-lg">save</span> Salvar Respostas';
        }
    } catch (e) {
        alert('Erro de conexão');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-lg">save</span> Salvar Respostas';
    }
}
</script>
