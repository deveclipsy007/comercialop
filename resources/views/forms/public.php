<?php
/**
 * Formulário Público — Preenchido pelo lead via link
 * Suporta modo corrido e modo paginado/etapas
 */
$pageTitle = $form['title'] ?? 'Formulário';
$questions = $questions ?? [];
$rawSettings = $form['settings'] ?? '{}';
$settings = json_decode($rawSettings, true);
if (!is_array($settings) || empty($settings)) $settings = [];
$displayMode = $settings['display_mode'] ?? 'continuous';

// Group questions by section for paginated mode
$sections = [];
$currentSection = '__intro__';
foreach ($questions as $q) {
    $sec = $q['section_title'] ?: $currentSection;
    if (!isset($sections[$sec])) $sections[$sec] = [];
    $sections[$sec][] = $q;
    if ($q['section_title']) $currentSection = $q['section_title'];
}
$sectionKeys = array_keys($sections);
$totalSections = count($sectionKeys) + 1; // +1 for respondent info
?>

<div class="min-h-screen bg-[#0a0a0a] flex items-start justify-center py-8 px-4">
    <div class="w-full max-w-2xl">

        <!-- Form Header -->
        <div class="bg-[#131313] border border-[#2A2A2A] rounded-2xl p-6 md:p-8 mb-4 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-[#E1FB15]/10 flex items-center justify-center mb-4 border border-[#E1FB15]/20">
                <span class="material-symbols-outlined text-[#E1FB15] text-2xl">dynamic_form</span>
            </div>
            <h1 class="text-xl font-black text-white mb-2"><?= e($form['title']) ?></h1>
            <?php if (!empty($form['description'])): ?>
            <p class="text-sm text-[#A1A1AA] leading-relaxed"><?= e($form['description']) ?></p>
            <?php endif; ?>
            <div class="flex items-center justify-center gap-3 mt-4 text-xs text-[#71717A]">
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-xs">quiz</span> <?= count($questions) ?> perguntas</span>
                <?php if ($displayMode === 'paginated'): ?>
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-xs">view_carousel</span> <?= $totalSections ?> etapas</span>
                <?php endif; ?>
            </div>

            <?php if ($displayMode === 'paginated'): ?>
            <!-- Progress Bar -->
            <div class="mt-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-[#71717A] uppercase tracking-wider" id="stepLabel">Etapa 1 de <?= $totalSections ?></span>
                    <span class="text-xs font-bold text-[#E1FB15]" id="stepPct">0%</span>
                </div>
                <div class="w-full h-1.5 bg-[#1A1A1A] rounded-full overflow-hidden">
                    <div id="stepProgressBar" class="h-full bg-[#E1FB15] rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Form Body -->
        <form id="publicForm" onsubmit="submitPublicForm(event)">

            <?php if ($displayMode === 'paginated'): ?>
            <!-- ══════ PAGINATED MODE ══════ -->

            <!-- Step 0: Respondent Info -->
            <div class="form-step" data-step="0">
                <div class="bg-[#131313] border border-[#2A2A2A] rounded-2xl p-6 mb-4">
                    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#E1FB15] text-base">person</span>
                        Suas Informações
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-[#A1A1AA] uppercase tracking-wider mb-1.5">Seu Nome</label>
                            <input type="text" name="respondent_name" required placeholder="Digite seu nome"
                                   class="pub-input w-full h-11 px-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl text-sm text-white placeholder-[#71717A] focus:border-[#E1FB15]/40 focus:ring-1 focus:ring-[#E1FB15]/20 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[#A1A1AA] uppercase tracking-wider mb-1.5">Seu E-mail</label>
                            <input type="email" name="respondent_email" required placeholder="seu@email.com"
                                   class="pub-input w-full h-11 px-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl text-sm text-white placeholder-[#71717A] focus:border-[#E1FB15]/40 focus:ring-1 focus:ring-[#E1FB15]/20 outline-none transition-all">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="goToStep(1)" class="h-11 px-6 rounded-xl bg-[#E1FB15] text-[#0a0a0a] text-sm font-bold hover:brightness-110 transition-all flex items-center gap-2">
                        Próximo <span class="material-symbols-outlined text-base">arrow_forward</span>
                    </button>
                </div>
            </div>

            <!-- Steps for each section -->
            <?php foreach ($sectionKeys as $si => $secKey):
                $stepNum = $si + 1;
                $secQuestions = $sections[$secKey];
                $isLast = ($stepNum === count($sectionKeys));
            ?>
            <div class="form-step hidden" data-step="<?= $stepNum ?>">
                <?php if ($secKey !== '__intro__'): ?>
                <div class="flex items-center gap-3 mb-4 px-1">
                    <span class="text-xs font-bold text-[#E1FB15] uppercase tracking-wider"><?= e($secKey) ?></span>
                    <div class="flex-1 h-px bg-[#2A2A2A]"></div>
                </div>
                <?php endif; ?>

                <?php foreach ($secQuestions as $q):
                    $options = json_decode($q['options'] ?? '[]', true) ?: [];
                ?>
                <div class="bg-[#131313] border border-[#2A2A2A] rounded-2xl p-6 mb-3">
                    <label class="block text-sm font-semibold text-white mb-1">
                        <?= e($q['label']) ?>
                        <?php if ($q['is_required']): ?><span class="text-red-400 ml-0.5">*</span><?php endif; ?>
                    </label>
                    <?php if (!empty($q['help_text'])): ?>
                    <p class="text-xs text-[#71717A] mb-3"><?= e($q['help_text']) ?></p>
                    <?php else: ?><div class="mb-3"></div><?php endif; ?>
                    <?php renderPublicField($q, $options); ?>
                </div>
                <?php endforeach; ?>

                <div class="flex justify-between mt-4">
                    <button type="button" onclick="goToStep(<?= $stepNum - 1 ?>)" class="h-11 px-5 rounded-xl bg-[#1A1A1A] border border-[#2A2A2A] text-sm text-[#A1A1AA] hover:text-white transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">arrow_back</span> Anterior
                    </button>
                    <?php if ($isLast): ?>
                    <button type="submit" id="submitBtn" class="h-11 px-6 rounded-xl bg-[#E1FB15] text-[#0a0a0a] text-sm font-bold hover:brightness-110 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">send</span> Enviar Respostas
                    </button>
                    <?php else: ?>
                    <button type="button" onclick="goToStep(<?= $stepNum + 1 ?>)" class="h-11 px-6 rounded-xl bg-[#E1FB15] text-[#0a0a0a] text-sm font-bold hover:brightness-110 transition-all flex items-center gap-2">
                        Próximo <span class="material-symbols-outlined text-base">arrow_forward</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php else: ?>
            <!-- ══════ CONTINUOUS MODE ══════ -->

            <!-- Respondent Info -->
            <div class="bg-[#131313] border border-[#2A2A2A] rounded-2xl p-6 mb-4">
                <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#E1FB15] text-base">person</span>
                    Suas Informações
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-[#A1A1AA] uppercase tracking-wider mb-1.5">Seu Nome</label>
                        <input type="text" name="respondent_name" required placeholder="Digite seu nome"
                               class="w-full h-11 px-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl text-sm text-white placeholder-[#71717A] focus:border-[#E1FB15]/40 focus:ring-1 focus:ring-[#E1FB15]/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[#A1A1AA] uppercase tracking-wider mb-1.5">Seu E-mail</label>
                        <input type="email" name="respondent_email" required placeholder="seu@email.com"
                               class="w-full h-11 px-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl text-sm text-white placeholder-[#71717A] focus:border-[#E1FB15]/40 focus:ring-1 focus:ring-[#E1FB15]/20 outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Questions -->
            <?php
            $currentSection = '';
            foreach ($questions as $q):
                $options = json_decode($q['options'] ?? '[]', true) ?: [];
                $isNewSection = $q['section_title'] && $q['section_title'] !== $currentSection;
                if ($isNewSection) $currentSection = $q['section_title'];
            ?>
            <?php if ($isNewSection): ?>
            <div class="flex items-center gap-3 my-5 px-2">
                <span class="text-xs font-bold text-[#E1FB15] uppercase tracking-wider"><?= e($currentSection) ?></span>
                <div class="flex-1 h-px bg-[#2A2A2A]"></div>
            </div>
            <?php endif; ?>

            <div class="bg-[#131313] border border-[#2A2A2A] rounded-2xl p-6 mb-3">
                <label class="block text-sm font-semibold text-white mb-1">
                    <?= e($q['label']) ?>
                    <?php if ($q['is_required']): ?><span class="text-red-400 ml-0.5">*</span><?php endif; ?>
                </label>
                <?php if (!empty($q['help_text'])): ?>
                <p class="text-xs text-[#71717A] mb-3"><?= e($q['help_text']) ?></p>
                <?php else: ?><div class="mb-3"></div><?php endif; ?>
                <?php renderPublicField($q, $options); ?>
            </div>
            <?php endforeach; ?>

            <!-- Submit -->
            <div class="mt-6">
                <button type="submit" id="submitBtn"
                        class="w-full h-12 rounded-2xl bg-[#E1FB15] text-[#0a0a0a] text-sm font-black hover:brightness-110 transition-all shadow-[0_0_0_1px_rgba(225,251,21,0.08),0_8px_24px_rgba(225,251,21,0.15)] flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">send</span>
                    Enviar Respostas
                </button>
            </div>
            <?php endif; ?>
        </form>

        <!-- Success State -->
        <div id="successState" class="hidden">
            <div class="bg-[#131313] border border-[#2A2A2A] rounded-2xl p-8 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/10 flex items-center justify-center mb-4 border border-emerald-500/20">
                    <span class="material-symbols-outlined text-emerald-400 text-3xl">check_circle</span>
                </div>
                <h2 class="text-lg font-bold text-white mb-2">Respostas enviadas!</h2>
                <p class="text-sm text-[#A1A1AA]">Obrigado por preencher o formulário. Suas respostas foram registradas com sucesso.</p>
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-[#71717A]">
            Powered by <span class="font-bold text-[#A1A1AA]">Operon Intelligence</span>
        </div>
    </div>
</div>

<?php
/** Helper: renders a public field by type */
function renderPublicField(array $q, array $options): void {
    $id = e($q['id']);
    $ph = e($q['placeholder'] ?? '');
    $req = $q['is_required'] ? 'required' : '';
    $inputClass = 'w-full h-11 px-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl text-sm text-white placeholder-[#71717A] focus:border-[#E1FB15]/40 outline-none transition-all';

    switch ($q['type']) {
        case 'short_text':
            echo "<input type='text' name='answers[{$id}]' placeholder='" . ($ph ?: 'Sua resposta') . "' {$req} class='{$inputClass}'>";
            break;
        case 'long_text':
            echo "<textarea name='answers[{$id}]' rows='4' placeholder='" . ($ph ?: 'Sua resposta detalhada...') . "' {$req} class='{$inputClass} !h-auto py-3 resize-y'></textarea>";
            break;
        case 'single_choice':
            echo '<div class="space-y-2">';
            foreach ($options as $opt) {
                $eo = e($opt);
                echo "<label class='flex items-center gap-3 p-3 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl cursor-pointer hover:border-[#E1FB15]/30 transition-all has-[:checked]:border-[#E1FB15]/50 has-[:checked]:bg-[#E1FB15]/5'>
                    <input type='radio' name='answers[{$id}]' value='{$eo}' {$req} class='w-4 h-4 text-[#E1FB15] bg-[#1A1A1A] border-[#2A2A2A] focus:ring-[#E1FB15]/20'>
                    <span class='text-sm text-[#A1A1AA]'>{$eo}</span></label>";
            }
            echo '</div>';
            break;
        case 'multiple_choice':
            echo '<div class="space-y-2">';
            foreach ($options as $opt) {
                $eo = e($opt);
                echo "<label class='flex items-center gap-3 p-3 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl cursor-pointer hover:border-[#E1FB15]/30 transition-all has-[:checked]:border-[#E1FB15]/50 has-[:checked]:bg-[#E1FB15]/5'>
                    <input type='checkbox' name='answers[{$id}][]' value='{$eo}' class='w-4 h-4 text-[#E1FB15] bg-[#1A1A1A] border-[#2A2A2A] rounded focus:ring-[#E1FB15]/20'>
                    <span class='text-sm text-[#A1A1AA]'>{$eo}</span></label>";
            }
            echo '</div>';
            break;
        case 'number':
            echo "<input type='number' name='answers[{$id}]' placeholder='" . ($ph ?: '0') . "' {$req} class='{$inputClass}'>";
            break;
        case 'date':
            echo "<input type='date' name='answers[{$id}]' {$req} class='{$inputClass}'>";
            break;
        case 'select':
            echo "<select name='answers[{$id}]' {$req} class='{$inputClass} appearance-none'><option value=''>Selecione...</option>";
            foreach ($options as $opt) { $eo = e($opt); echo "<option value='{$eo}'>{$eo}</option>"; }
            echo "</select>";
            break;
        case 'checkbox':
            echo "<label class='flex items-center gap-3 p-3 bg-[#1A1A1A] border border-[#2A2A2A] rounded-xl cursor-pointer hover:border-[#E1FB15]/30 transition-all'>
                <input type='checkbox' name='answers[{$id}]' value='1' class='w-4 h-4 text-[#E1FB15] bg-[#1A1A1A] border-[#2A2A2A] rounded focus:ring-[#E1FB15]/20'>
                <span class='text-sm text-[#A1A1AA]'>Sim</span></label>";
            break;
        case 'rating':
            echo "<div class='flex gap-2' id='rating-{$id}'>";
            for ($s = 1; $s <= 5; $s++) {
                echo "<button type='button' onclick=\"setRating('{$id}', {$s})\" class='rating-star w-10 h-10 rounded-xl bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#71717A] hover:text-[#E1FB15] hover:border-[#E1FB15]/30 transition-all' data-value='{$s}'>
                    <span class='material-symbols-outlined text-lg'>star</span></button>";
            }
            echo "<input type='hidden' name='answers[{$id}]' id='rating-val-{$id}' value=''></div>";
            break;
        case 'email':
            echo "<input type='email' name='answers[{$id}]' placeholder='" . ($ph ?: 'email@exemplo.com') . "' {$req} class='{$inputClass}'>";
            break;
        case 'phone':
            echo "<input type='tel' name='answers[{$id}]' placeholder='" . ($ph ?: '(00) 00000-0000') . "' {$req} class='{$inputClass}'>";
            break;
        case 'url':
            echo "<input type='url' name='answers[{$id}]' placeholder='" . ($ph ?: 'https://...') . "' {$req} class='{$inputClass}'>";
            break;
        default:
            echo "<input type='text' name='answers[{$id}]' placeholder='" . ($ph ?: 'Sua resposta') . "' class='{$inputClass}'>";
    }
}
?>

<script>
const DISPLAY_MODE = '<?= $displayMode ?>';
const TOTAL_STEPS = <?= $totalSections ?>;
let currentStep = 0;

function setRating(qId, value) {
    document.getElementById('rating-val-' + qId).value = value;
    const container = document.getElementById('rating-' + qId);
    container.querySelectorAll('.rating-star').forEach(star => {
        const v = parseInt(star.dataset.value);
        if (v <= value) {
            star.classList.add('text-[#E1FB15]', 'border-[#E1FB15]/50', 'bg-[#E1FB15]/10');
            star.classList.remove('text-[#71717A]', 'border-[#2A2A2A]', 'bg-[#1A1A1A]');
        } else {
            star.classList.remove('text-[#E1FB15]', 'border-[#E1FB15]/50', 'bg-[#E1FB15]/10');
            star.classList.add('text-[#71717A]', 'border-[#2A2A2A]', 'bg-[#1A1A1A]');
        }
    });
}

// ─── Paginated Navigation ────────────────────────────
function goToStep(step) {
    if (step < 0 || step >= TOTAL_STEPS) return;

    // Validate current step required fields
    const currentStepEl = document.querySelector('.form-step[data-step="' + currentStep + '"]');
    if (currentStepEl && step > currentStep) {
        const requiredFields = currentStepEl.querySelectorAll('[required]');
        for (const field of requiredFields) {
            if (!field.value.trim()) {
                field.focus();
                field.classList.add('border-red-500/50');
                setTimeout(() => field.classList.remove('border-red-500/50'), 2000);
                return;
            }
        }
    }

    // Hide all steps
    document.querySelectorAll('.form-step').forEach(el => el.classList.add('hidden'));

    // Show target step
    const target = document.querySelector('.form-step[data-step="' + step + '"]');
    if (target) {
        target.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    currentStep = step;
    updateProgress();
}

function updateProgress() {
    const pct = Math.round((currentStep / (TOTAL_STEPS - 1)) * 100);
    const bar = document.getElementById('stepProgressBar');
    const pctEl = document.getElementById('stepPct');
    const label = document.getElementById('stepLabel');
    if (bar) bar.style.width = pct + '%';
    if (pctEl) pctEl.textContent = pct + '%';
    if (label) label.textContent = 'Etapa ' + (currentStep + 1) + ' de ' + TOTAL_STEPS;
}

async function submitPublicForm(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">progress_activity</span> Enviando...';

    const formData = new FormData(document.getElementById('publicForm'));
    const urlData = new URLSearchParams(formData);

    try {
        const res = await fetch(window.location.pathname + '/submit', { method: 'POST', body: urlData });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('publicForm').classList.add('hidden');
            document.getElementById('successState').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            alert(data.error || 'Erro ao enviar. Tente novamente.');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-lg">send</span> Enviar Respostas';
        }
    } catch (err) {
        alert('Erro de conexão. Verifique sua internet e tente novamente.');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-lg">send</span> Enviar Respostas';
    }
}

// Init paginated mode
if (DISPLAY_MODE === 'paginated') {
    updateProgress();
}
</script>
