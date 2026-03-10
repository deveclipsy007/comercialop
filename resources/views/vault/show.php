<?php
use App\Models\Lead;
/** @var array $lead */
/** @var array $tokenBalance */
$analysis  = $lead['analysis'] ?? [];
$social    = $lead['social_presence'] ?? [];
$ctx       = $lead['human_context'] ?? [];
$cnpj      = $lead['cnpj_data'] ?? [];
$ps        = $lead['pagespeed_data'] ?? [];
$tags      = $lead['tags'] ?? [];
$score     = $lead['priority_score'] ?? 0;
?>

<div class="flex items-center gap-3 mb-6">
    <a href="/vault" class="text-zinc-400 hover:text-white transition-colors">
        <span class="material-symbols-outlined text-xl">arrow_back</span>
    </a>
    <div class="flex-1 min-w-0">
        <h1 class="text-2xl font-bold text-white truncate"><?= e($lead['name']) ?></h1>
        <p class="text-zinc-400 text-sm"><?= e($lead['segment']) ?></p>
    </div>
    <div class="flex items-center gap-2">
        <span class="px-3 py-1 rounded-full text-xs font-semibold border <?= stageBadgeClass($lead['pipeline_status']) ?>">
            <?= stageLabel($lead['pipeline_status']) ?>
        </span>
        <span class="px-3 py-1 rounded-full text-xs font-bold border <?= scoreBg($score) ?>">
            Score <?= $score ?>
        </span>
    </div>
</div>

<!-- Action bar -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php if ($lead['phone']): ?>
    <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['phone']) ?>?text=<?= urlencode('Olá, ' . $lead['name'] . '!') ?>"
       target="_blank"
       class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-base">chat</span> WhatsApp
    </a>
    <?php endif; ?>
    <?php if ($lead['website']): ?>
    <a href="<?= e($lead['website']) ?>" target="_blank"
       class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded-lg text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-base">open_in_new</span> Site
    </a>
    <?php endif; ?>
    <button onclick="openModal('edit-lead-modal')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded-lg text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-base">edit</span> Editar
    </button>
    <button id="btn-analyze" data-lead-id="<?= e($lead['id']) ?>"
            class="ai-trigger inline-flex items-center gap-2 px-4 py-2 bg-[#18C29C]/10 hover:bg-[#18C29C]/20 border border-[#18C29C]/30 text-[#18C29C] rounded-lg text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-base">psychology</span>
        <?= $analysis ? 'Re-analisar' : 'Analisar com IA' ?>
    </button>
    <button id="btn-4d" data-lead-id="<?= e($lead['id']) ?>"
            class="ai-trigger inline-flex items-center gap-2 px-4 py-2 bg-violet-500/10 hover:bg-violet-500/20 border border-violet-500/30 text-violet-400 rounded-lg text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-base">auto_awesome</span> Operon 4D
    </button>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Left column -->
    <div class="xl:col-span-2 space-y-6">

        <!-- AI Analysis -->
        <?php if ($analysis): ?>
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#18C29C]">psychology</span>
                    Diagnóstico IA
                </h2>
                <span class="text-xs text-zinc-500">
                    Maturidade: <span class="text-amber-400 font-medium"><?= e($analysis['digitalMaturity'] ?? '—') ?></span>
                </span>
            </div>

            <?php if (!empty($analysis['scoreExplanation'])): ?>
            <p class="text-zinc-300 text-sm mb-4 italic">"<?= e($analysis['scoreExplanation']) ?>"</p>
            <?php endif; ?>

            <?php if (!empty($analysis['summary'])): ?>
            <p class="text-zinc-400 text-sm mb-4"><?= e($analysis['summary']) ?></p>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if (!empty($analysis['diagnosis'])): ?>
                <div>
                    <p class="text-xs font-semibold text-red-400 uppercase tracking-wider mb-2">Problemas críticos</p>
                    <ul class="space-y-1">
                        <?php foreach ($analysis['diagnosis'] as $item): ?>
                        <li class="flex items-start gap-2 text-sm text-zinc-300">
                            <span class="material-symbols-outlined text-red-400 text-base mt-0.5 shrink-0">warning</span>
                            <?= e($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($analysis['opportunities'])): ?>
                <div>
                    <p class="text-xs font-semibold text-emerald-400 uppercase tracking-wider mb-2">Oportunidades</p>
                    <ul class="space-y-1">
                        <?php foreach ($analysis['opportunities'] as $item): ?>
                        <li class="flex items-start gap-2 text-sm text-zinc-300">
                            <span class="material-symbols-outlined text-emerald-400 text-base mt-0.5 shrink-0">check_circle</span>
                            <?= e($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($analysis['urgencyLevel'])): ?>
            <div class="mt-4 pt-4 border-t border-zinc-700/50 flex items-center gap-2">
                <span class="text-xs text-zinc-500">Urgência:</span>
                <?php $urg = $analysis['urgencyLevel'];
                $urgClass = $urg === 'Alta' ? 'bg-red-500/20 text-red-400 border-red-500/30'
                          : ($urg === 'Média' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30'
                          : 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'); ?>
                <span class="px-2 py-0.5 rounded text-xs font-semibold border <?= $urgClass ?>"><?= e($urg) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bg-zinc-900/60 border border-dashed border-zinc-700 rounded-2xl p-8 text-center">
            <span class="material-symbols-outlined text-4xl text-zinc-600 mb-3 block">psychology</span>
            <p class="text-zinc-400 text-sm mb-4">Nenhuma análise IA ainda. Clique em "Analisar com IA" para gerar o diagnóstico.</p>
            <button id="btn-analyze-empty" data-lead-id="<?= e($lead['id']) ?>"
                    class="ai-trigger inline-flex items-center gap-2 px-4 py-2 bg-[#18C29C]/10 hover:bg-[#18C29C]/20 border border-[#18C29C]/30 text-[#18C29C] rounded-lg text-sm font-medium transition-colors">
                <span class="material-symbols-outlined text-base">auto_fix_high</span>
                Analisar agora
            </button>
        </div>
        <?php endif; ?>

        <!-- Operon 4D Results (injected via JS) -->
        <div id="operon4d-results" class="hidden space-y-4"></div>

        <!-- Deep Analysis (Competitors, Target Audience, Value Proposition) -->
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-violet-400">query_stats</span>
                    Inteligência Profunda
                </h2>
                <button id="btn-deep-analyze" data-lead-id="<?= e($lead['id']) ?>"
                        class="text-xs px-3 py-1.5 bg-violet-500/10 hover:bg-violet-500/20 text-violet-400 rounded border border-violet-500/30 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">magic_button</span> Gerar Insights
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="deep-analysis-cards">
                <!-- Value Proposition -->
                <div class="bg-zinc-800/50 border border-zinc-700/30 rounded-xl p-4">
                    <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-emerald-400 text-sm">storefront</span> O que vende
                    </h3>
                    <div id="va-content" class="text-sm text-zinc-300 leading-relaxed">
                        <?= !empty($analysis['valueProposition']) ? nl2br(e($analysis['valueProposition'])) : '<span class="text-zinc-500 italic">Insight não gerado.</span>' ?>
                    </div>
                </div>

                <!-- Target Audience -->
                <div class="bg-zinc-800/50 border border-zinc-700/30 rounded-xl p-4">
                    <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-blue-400 text-sm">groups</span> Público-Alvo
                    </h3>
                    <div id="ta-content" class="text-sm text-zinc-300 leading-relaxed">
                        <?= !empty($analysis['targetAudience']) ? nl2br(e($analysis['targetAudience'])) : '<span class="text-zinc-500 italic">Insight não gerado.</span>' ?>
                    </div>
                </div>

                <!-- Competitors -->
                <div class="bg-zinc-800/50 border border-zinc-700/30 rounded-xl p-4">
                    <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-amber-400 text-sm">swords</span> Concorrentes
                    </h3>
                    <div id="ca-content" class="text-sm text-zinc-300 leading-relaxed">
                        <?php if (!empty($analysis['competitors']) && is_array($analysis['competitors'])): ?>
                            <ul class="list-disc list-inside space-y-1">
                                <?php foreach ($analysis['competitors'] as $comp): ?>
                                    <li><?= e($comp) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-zinc-500 italic">Insight não gerado.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PageSpeed -->
        <?php if ($ps): ?>
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <h2 class="font-semibold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-amber-400">speed</span>
                Performance do Site
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php
                $metrics = [
                    ['label' => 'Performance', 'value' => $ps['performanceScore'] ?? '?', 'unit' => '/100'],
                    ['label' => 'Carregamento', 'value' => $ps['loadTime'] ?? '?', 'unit' => 's'],
                    ['label' => 'SEO', 'value' => $ps['seoScore'] ?? '?', 'unit' => '/100'],
                    ['label' => 'Acessibil.', 'value' => $ps['accessibilityScore'] ?? '?', 'unit' => '/100'],
                ];
                foreach ($metrics as $m): ?>
                <div class="bg-zinc-800/50 rounded-xl p-3 text-center">
                    <p class="text-xl font-bold text-white"><?= e($m['value']) ?><span class="text-xs text-zinc-500"><?= e($m['unit']) ?></span></p>
                    <p class="text-xs text-zinc-500 mt-1"><?= e($m['label']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SPIN & Scripts shortcut -->
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <h2 class="font-semibold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-violet-400">forum</span>
                Inteligência de Abordagem
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <a href="/spin?lead_id=<?= e($lead['id']) ?>"
                   class="flex items-center gap-3 p-3 bg-zinc-800/50 hover:bg-zinc-700/50 border border-zinc-700/50 rounded-xl transition-colors group">
                    <span class="material-symbols-outlined text-violet-400 text-2xl">psychology_alt</span>
                    <div>
                        <p class="text-sm font-medium text-white group-hover:text-violet-400 transition-colors">Perguntas SPIN</p>
                        <p class="text-xs text-zinc-500">Framework de qualificação</p>
                    </div>
                    <span class="material-symbols-outlined text-zinc-600 ml-auto">chevron_right</span>
                </a>
                <a href="/spin?lead_id=<?= e($lead['id']) ?>&tab=scripts"
                   class="flex items-center gap-3 p-3 bg-zinc-800/50 hover:bg-zinc-700/50 border border-zinc-700/50 rounded-xl transition-colors group">
                    <span class="material-symbols-outlined text-[#18C29C] text-2xl">edit_note</span>
                    <div>
                        <p class="text-sm font-medium text-white group-hover:text-[#18C29C] transition-colors">Scripts de Abordagem</p>
                        <p class="text-xs text-zinc-500">WhatsApp, LinkedIn, Email</p>
                    </div>
                    <span class="material-symbols-outlined text-zinc-600 ml-auto">chevron_right</span>
                </a>
            </div>
        </div>

    </div>

    <!-- Right column -->
    <div class="space-y-6">

        <!-- Contact info -->
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <h2 class="font-semibold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-zinc-400">contact_page</span>
                Contato
            </h2>
            <div class="space-y-3">
                <?php if ($lead['phone']): ?>
                <div class="flex items-center gap-2 text-sm">
                    <span class="material-symbols-outlined text-zinc-500 text-base">call</span>
                    <span class="text-zinc-300"><?= e($lead['phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['email']): ?>
                <div class="flex items-center gap-2 text-sm">
                    <span class="material-symbols-outlined text-zinc-500 text-base">mail</span>
                    <span class="text-zinc-300"><?= e($lead['email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['website']): ?>
                <div class="flex items-center gap-2 text-sm">
                    <span class="material-symbols-outlined text-zinc-500 text-base">language</span>
                    <a href="<?= e($lead['website']) ?>" target="_blank" class="text-[#18C29C] hover:underline truncate"><?= e($lead['website']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($lead['address']): ?>
                <div class="flex items-start gap-2 text-sm">
                    <span class="material-symbols-outlined text-zinc-500 text-base mt-0.5">location_on</span>
                    <span class="text-zinc-300"><?= e($lead['address']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Social presence -->
            <?php if (!empty(array_filter($social))): ?>
            <div class="mt-4 pt-4 border-t border-zinc-700/50">
                <p class="text-xs text-zinc-500 mb-2">Redes Sociais</p>
                <div class="flex flex-wrap gap-2">
                    <?php if (!empty($social['instagram'])): ?>
                    <span class="flex items-center gap-1 text-xs bg-pink-500/10 text-pink-400 border border-pink-500/20 rounded px-2 py-1">
                        <span class="material-symbols-outlined text-xs">photo_camera</span> <?= e($social['instagram']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($social['linkedin'])): ?>
                    <span class="flex items-center gap-1 text-xs bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded px-2 py-1">
                        <span class="material-symbols-outlined text-xs">work</span> <?= e($social['linkedin']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($social['facebook'])): ?>
                    <span class="flex items-center gap-1 text-xs bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded px-2 py-1">
                        <span class="material-symbols-outlined text-xs">thumb_up</span> <?= e($social['facebook']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pipeline stage -->
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <h2 class="font-semibold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-zinc-400">view_kanban</span>
                Pipeline
            </h2>
            <div class="space-y-1" id="stage-selector">
                <?php foreach (Lead::STAGES as $stageKey => $stageLabel): ?>
                <button onclick="changeStage('<?= e($lead['id']) ?>', '<?= e($stageKey) ?>')"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-colors
                               <?= $lead['pipeline_status'] === $stageKey
                                   ? 'bg-[#18C29C]/10 text-[#18C29C] font-medium'
                                   : 'text-zinc-400 hover:text-white hover:bg-zinc-700/50' ?>">
                    <span class="w-2 h-2 rounded-full <?= $lead['pipeline_status'] === $stageKey ? 'bg-[#18C29C]' : 'bg-zinc-600' ?>"></span>
                    <?= e($stageLabel) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Context (human_context) -->
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <h2 class="font-semibold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-zinc-400">thermostat</span>
                Contexto Comercial
            </h2>
            <div class="space-y-3">
                <!-- Temperature -->
                <div>
                    <p class="text-xs text-zinc-500 mb-1">Temperatura</p>
                    <div class="flex gap-2">
                        <?php foreach (['HOT' => ['text-red-400', '🔥', 'Quente'], 'WARM' => ['text-amber-400', '☀️', 'Morno'], 'COLD' => ['text-blue-400', '❄️', 'Frio']] as $val => [$cls, $icon, $lbl]): ?>
                        <button onclick="saveContext('<?= e($lead['id']) ?>', 'temperature', '<?= $val ?>')"
                                class="flex-1 py-1.5 rounded-lg text-xs border transition-colors
                                       <?= ($ctx['temperature'] ?? '') === $val
                                           ? "border-current {$cls} bg-current/10"
                                           : 'border-zinc-700 text-zinc-500 hover:border-zinc-500' ?>">
                            <?= $icon ?> <?= $lbl ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Timing -->
                <div>
                    <p class="text-xs text-zinc-500 mb-1">Timing</p>
                    <div class="flex gap-2">
                        <?php foreach (['IMMEDIATE' => 'Imediato', 'SHORT_TERM' => 'Curto', 'LONG_TERM' => 'Longo'] as $val => $lbl): ?>
                        <button onclick="saveContext('<?= e($lead['id']) ?>', 'timingStatus', '<?= $val ?>')"
                                class="flex-1 py-1.5 rounded-lg text-xs border transition-colors
                                       <?= ($ctx['timingStatus'] ?? '') === $val
                                           ? 'border-[#18C29C] text-[#18C29C] bg-[#18C29C]/10'
                                           : 'border-zinc-700 text-zinc-500 hover:border-zinc-500' ?>">
                            <?= $lbl ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Objection -->
                <div>
                    <p class="text-xs text-zinc-500 mb-1">Objeção Principal</p>
                    <select onchange="saveContext('<?= e($lead['id']) ?>', 'objectionCategory', this.value)"
                            class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-300 focus:border-[#18C29C] outline-none">
                        <option value="">Nenhuma</option>
                        <option value="PRICE" <?= ($ctx['objectionCategory'] ?? '') === 'PRICE' ? 'selected' : '' ?>>Preço</option>
                        <option value="COMPETITOR" <?= ($ctx['objectionCategory'] ?? '') === 'COMPETITOR' ? 'selected' : '' ?>>Concorrente</option>
                        <option value="TIMING" <?= ($ctx['objectionCategory'] ?? '') === 'TIMING' ? 'selected' : '' ?>>Timing</option>
                        <option value="TRUST" <?= ($ctx['objectionCategory'] ?? '') === 'TRUST' ? 'selected' : '' ?>>Confiança</option>
                    </select>
                </div>
                <!-- Notes -->
                <div>
                    <p class="text-xs text-zinc-500 mb-1">Notas</p>
                    <textarea id="context-notes" rows="3"
                              onblur="saveContextNotes('<?= e($lead['id']) ?>')"
                              class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-300 focus:border-[#18C29C] outline-none resize-none"
                              placeholder="Anotações sobre o lead..."><?= e($ctx['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Tags -->
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <h2 class="font-semibold text-white flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-zinc-400">label</span>
                Tags
            </h2>
            <div class="flex flex-wrap gap-2 mb-3" id="tags-container">
                <?php foreach ($tags as $tag): ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-zinc-700 text-zinc-300 rounded text-xs">
                    <?= e($tag) ?>
                    <button onclick="removeTag('<?= e($lead['id']) ?>', '<?= e($tag) ?>')"
                            class="text-zinc-500 hover:text-red-400">×</button>
                </span>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
                <input type="text" id="new-tag" placeholder="Nova tag..."
                       class="flex-1 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-1.5 text-sm text-zinc-300 focus:border-[#18C29C] outline-none"
                       onkeydown="if(event.key==='Enter'){addTag('<?= e($lead['id']) ?>');}">
                <button onclick="addTag('<?= e($lead['id']) ?>')"
                        class="px-3 py-1.5 bg-[#18C29C]/10 hover:bg-[#18C29C]/20 border border-[#18C29C]/30 text-[#18C29C] rounded-lg text-sm">
                    +
                </button>
            </div>
        </div>

        <!-- Meta -->
        <div class="bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5">
            <h2 class="font-semibold text-white flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-zinc-400">info</span>
                Metadados
            </h2>
            <div class="space-y-2 text-xs text-zinc-500">
                <div class="flex justify-between">
                    <span>ID</span>
                    <span class="text-zinc-400 font-mono"><?= e(substr($lead['id'], 0, 8)) ?>...</span>
                </div>
                <div class="flex justify-between">
                    <span>Criado</span>
                    <span class="text-zinc-400"><?= e(timeAgo($lead['created_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Atualizado</span>
                    <span class="text-zinc-400"><?= e(timeAgo($lead['updated_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Fit Score</span>
                    <span class="text-zinc-400"><?= e($lead['fit_score'] ?? 0) ?></span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Edit Lead Modal -->
<div id="edit-lead-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal('edit-lead-modal')"></div>
    <div class="relative bg-[#1A1A1E] border border-zinc-700 rounded-2xl p-6 w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-white">Editar Lead</h3>
            <button onclick="closeModal('edit-lead-modal')" class="text-zinc-400 hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="/vault/<?= e($lead['id']) ?>/update" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs text-zinc-400 mb-1">Nome da empresa</label>
                    <input type="text" name="name" value="<?= e($lead['name']) ?>" required
                           class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-white text-sm focus:border-[#18C29C] outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-400 mb-1">Segmento</label>
                    <input type="text" name="segment" value="<?= e($lead['segment']) ?>"
                           class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-white text-sm focus:border-[#18C29C] outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-400 mb-1">Telefone</label>
                    <input type="text" name="phone" value="<?= e($lead['phone'] ?? '') ?>"
                           class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-white text-sm focus:border-[#18C29C] outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-400 mb-1">Email</label>
                    <input type="email" name="email" value="<?= e($lead['email'] ?? '') ?>"
                           class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-white text-sm focus:border-[#18C29C] outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-400 mb-1">Website</label>
                    <input type="url" name="website" value="<?= e($lead['website'] ?? '') ?>" placeholder="https://"
                           class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-white text-sm focus:border-[#18C29C] outline-none">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-zinc-400 mb-1">Endereço</label>
                    <input type="text" name="address" value="<?= e($lead['address'] ?? '') ?>"
                           class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-white text-sm focus:border-[#18C29C] outline-none">
                </div>
            </div>
            <button type="submit"
                    class="w-full py-3 bg-[#18C29C] hover:bg-[#15A882] text-white rounded-xl font-semibold text-sm transition-colors">
                Salvar Alterações
            </button>
        </form>
    </div>
</div>

<?php 
$safeId = e($lead['id']);
$extraScripts = <<<JS
<script>
const LEAD_ID = '{$safeId}';
console.log('Lead view initialized for:', LEAD_ID);

// ── Stage change ─────────────────────────────────────────
async function changeStage(id, stage) {
    console.log('Changing stage to:', stage);
    try {
        await operonFetch('/vault/' + id + '/stage', {
            method: 'POST',
            body: JSON.stringify({ stage })
        });
        
        // Update UI without reload
        document.querySelectorAll('#stage-selector button').forEach(btn => {
            btn.className = btn.className.replace(/bg-\[#18C29C\].*?font-medium/, 'text-zinc-400 hover:text-white hover:bg-zinc-700/50');
            btn.classList.remove('bg-[#18C29C]/10', 'text-[#18C29C]', 'font-medium');
            const dot = btn.querySelector('span.rounded-full');
            if (dot) dot.className = 'w-2 h-2 rounded-full bg-zinc-600';
        });
        
        const active = document.querySelector('#stage-selector button[onclick*="' + stage + '"]');
        if (active) {
            active.className = active.className.replace(/text-zinc-400 hover:text-white hover:bg-zinc-700\/50/, '');
            active.classList.add('bg-[#18C29C]/10', 'text-[#18C29C]', 'font-medium');
            const dot = active.querySelector('span.rounded-full');
            if (dot) { dot.classList.remove('bg-zinc-600'); dot.classList.add('bg-[#18C29C]'); }
        }
    } catch (err) {
        console.error('Error changing stage:', err);
    }
}

// ── Context save ─────────────────────────────────────────
async function saveContext(id, field, value) {
    console.log('Saving context:', field, value);
    try {
        // Re-render buttons optimistically
        document.querySelectorAll('[onclick*="saveContext"][onclick*="' + field + '"]').forEach(btn => {
            btn.classList.remove('border-current', 'border-[#18C29C]', 'text-[#18C29C]', 'bg-current/10', 'bg-[#18C29C]/10', 'text-red-400', 'text-amber-400', 'text-blue-400');
            btn.classList.add('border-zinc-700', 'text-zinc-500');
        });
        const active = document.querySelector('[onclick*="saveContext"][onclick*="' + value + '"]');
        if (active) {
            active.classList.remove('border-zinc-700', 'text-zinc-500');
            active.classList.add('border-[#18C29C]', 'text-[#18C29C]', 'bg-[#18C29C]/10');
        }
        await operonFetch('/vault/' + id + '/context', {
            method: 'POST',
            body: JSON.stringify({ field, value })
        });
    } catch (err) {
        console.error('Error saving context:', err);
    }
}

async function saveContextNotes(id) {
    const el = document.getElementById('context-notes');
    if(!el) return;
    try {
        const value = el.value;
        await operonFetch('/vault/' + id + '/context', {
            method: 'POST',
            body: JSON.stringify({ field: 'notes', value })
        });
        // Visual feedback
        el.classList.add('border-[#18C29C]');
        setTimeout(() => el.classList.remove('border-[#18C29C]'), 1000);
    } catch (err) {
        console.error('Error saving notes:', err);
    }
}

// ── Tags ─────────────────────────────────────────────────
async function addTag(id) {
    const input = document.getElementById('new-tag');
    if(!input) return;
    const tag = input.value.trim();
    if (!tag) return;
    
    input.disabled = true;
    console.log('Adding tag:', tag);
    try {
        const res = await operonFetch('/vault/' + id + '/tags', {
            method: 'POST',
            body: JSON.stringify({ action: 'add', tag })
        });
        if (res && res.tags) renderTags(id, res.tags);
        input.value = '';
    } catch (err) {
        console.error('Error adding tag:', err);
    } finally {
        input.disabled = false;
        input.focus();
    }
}

async function removeTag(id, tag) {
    console.log('Removing tag:', tag);
    try {
        const res = await operonFetch('/vault/' + id + '/tags', {
            method: 'POST',
            body: JSON.stringify({ action: 'remove', tag })
        });
        if (res && res.tags) renderTags(id, res.tags);
    } catch (err) {
        console.error('Error removing tag:', err);
    }
}

function renderTags(id, tags) {
    const container = document.getElementById('tags-container');
    if(!container) return;
    container.innerHTML = tags.map(t =>
        '<span class="inline-flex items-center gap-1 px-2 py-1 bg-zinc-700 text-zinc-300 rounded text-xs">' +
            escHtml(t) +
            '<button onclick="removeTag(\'' + id + '\', \'' + t + '\')" class="text-zinc-500 hover:text-red-400">×</button>' +
        '</span>'
    ).join('');
}

// ── Helpers ───────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function hideAILoading() {
    try {
        const loaders = document.querySelectorAll('.ai-spinner');
        loaders.forEach(l => {
            const parent = l.closest('.p-8') || l.closest('.py-12');
            if (parent) parent.remove();
        });
    } catch (e) {
        console.error('Error hiding AI loader:', e);
    }
}

// ── Interactivity bindings for AI Buttons ─────────────────
setTimeout(() => {
    document.querySelectorAll('#btn-analyze, #btn-analyze-empty').forEach(btn => {
        btn.addEventListener('click', async () => {
            showAILoading(btn.parentElement, 'Analisando com Operon Intelligence...');
            try {
                await operonFetch('/vault/' + LEAD_ID + '/analyze', { method: 'POST' });
                location.reload();
            } catch (e) {
                hideAILoading();
                alert('Erro na análise. Verifique o token de IA.');
            }
        });
    });

    const btn4d = document.getElementById('btn-4d');
    if (btn4d) {
        btn4d.addEventListener('click', async () => {
            const container = document.getElementById('operon4d-results');
            if(container) {
                container.classList.remove('hidden');
                showAILoading(container, 'Executando Operon 4D Intelligence...');
            }
            try {
                const data = await operonFetch('/vault/' + LEAD_ID + '/operon', { method: 'POST' });
                hideAILoading();
                if (data) render4D(data);
            } catch (e) {
                hideAILoading();
                alert('Erro no Operon 4D.');
            }
        });
    }

    const btnDeep = document.getElementById('btn-deep-analyze');
    if (btnDeep) {
        btnDeep.addEventListener('click', async () => {
            const container = document.getElementById('deep-analysis-cards');
            if(!container) { console.error('Cards container not found!'); return; }
            
            // showAILoading can destroy innerHTML which breaks the cards structure.
            // Let's just change the button text instead for deep insights or put it above.
            const originalText = btnDeep.innerHTML;
            btnDeep.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Processando...';
            btnDeep.disabled = true;
            
            try {
                const data = await operonFetch('/vault/' + LEAD_ID + '/insights', { method: 'POST' });
                
                if (data && !data.error) {
                    const va = document.getElementById('va-content');
                    if(va) va.innerHTML = data.valueProposition ? escHtml(data.valueProposition).replace(/\\\\n/g, '<br>') : '<span class="text-zinc-500 italic">N/D</span>';
                    
                    const ta = document.getElementById('ta-content');
                    if(ta) ta.innerHTML = data.targetAudience ? escHtml(data.targetAudience).replace(/\\\\n/g, '<br>') : '<span class="text-zinc-500 italic">N/D</span>';
                    
                    const ca = document.getElementById('ca-content');
                    if(ca) {
                        if (data.competitors && Array.isArray(data.competitors) && data.competitors.length > 0) {
                            ca.innerHTML = '<ul class="list-disc list-inside space-y-1">' + data.competitors.map(c => '<li>' + escHtml(c) + '</li>').join('') + '</ul>';
                        } else {
                            ca.innerHTML = '<span class="text-zinc-500 italic">N/D</span>';
                        }
                    }
                } else {
                    alert(data?.error || 'Erro ao gerar insights.');
                }
            } catch (e) {
                console.error(e);
                alert('Erro de conexão ao gerar Insights.');
            } finally {
                btnDeep.innerHTML = originalText;
                btnDeep.disabled = false;
            }
        });
    }
}, 100);

// ── 4D Renderer ───────────────────────────────────────────
function render4D(data) {
    const container = document.getElementById('operon4d-results');
    if(!container) return;
    
    if(data.error) {
         container.innerHTML = `<div class="p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">\${escHtml(data.error)}</div>`;
         return;
    }

    container.innerHTML = '';

    const sections = [
        { key: 'diagnostico', label: 'Diagnóstico de Perda', icon: 'monitoring', color: 'text-red-400' },
        { key: 'potencial',   label: 'Potencial Comercial',  icon: 'trending_up', color: 'text-emerald-400' },
        { key: 'autoridade',  label: 'Autoridade Local',     icon: 'verified',   color: 'text-amber-400' },
        { key: 'script',      label: 'Script de Abordagem',  icon: 'chat',       color: 'text-violet-400' },
    ];

    sections.forEach(({ key, label, icon, color }) => {
        const d = data[key];
        if (!d) return;
        const isStr = typeof d === 'string';
        const card = document.createElement('div');
        card.className = 'bg-zinc-900/60 border border-zinc-700/50 rounded-2xl p-5';
        
        let contentHtml = isStr 
            ? '<p class="text-zinc-300 text-sm whitespace-pre-line leading-relaxed">' + escHtml(d) + '</p>'
            : '<pre class="text-xs text-zinc-400 overflow-auto whitespace-pre-wrap">' + escHtml(JSON.stringify(d, null, 2)) + '</pre>';

        card.innerHTML = 
            '<h3 class="font-semibold text-white flex items-center gap-2 mb-3">' +
                '<span class="material-symbols-outlined ' + color + '">' + icon + '</span>' +
                label +
            '</h3>' + 
            contentHtml;
            
        container.appendChild(card);
    });
}
</script>
JS;
?>
