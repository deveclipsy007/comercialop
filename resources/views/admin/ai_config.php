<?php
$pageTitle    = 'IA & Tokens (Admin)';
$pageSubtitle = 'Configuração do Motor de Inteligência Artificial';

// Supondo que venha do controller:
// $config = ['provider' => 'gemini', 'tier' => 'starter']
// $tokenBalance = ['used' => 120, 'limit' => 500, 'percent' => 24]
// $aiDistribution = ['gemini' => 80, 'openai' => 20] // <-- novo recurso sugerido no Áudio
$distGemini = $aiDistribution['gemini'] ?? 80;
$distOpenAI = $aiDistribution['openai'] ?? 20;
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-text">Gestão de Inteligência Artificial</h2>
            <p class="text-sm text-subtle mt-0.5">Gerenciamento de recursos de IA, roteamento de prompts e limites de Tokens.</p>
        </div>
        <div class="flex items-center gap-2 text-xs text-muted bg-surface2 border border-stroke px-3 py-1.5 rounded-pill shadow-soft">
            <span class="material-symbols-outlined text-[14px]">psychology</span>
            Motor Neural
        </div>
    </div>

    <!-- Main Configs -->
    <form method="POST" action="/admin/ai/save">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left/Center: Provider configs -->
            <div class="lg:col-span-2 flex flex-col gap-6">
                
                <!-- Routing / Distribution -->
                <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft">
                    <h3 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-base">route</span>
                        Distribuição de Carga de Modelos (LLM Routing)
                    </h3>
                    
                    <p class="text-xs text-subtle mb-6 leading-relaxed">
                        Defina como os tokens consumidos pela agência serão distribuídos entre os provedores de I.A disponíveis. Mover esse slider altera automaticamente para qual modelo o Operon enviará os prompts.
                    </p>

                    <div class="mb-5">
                        <div class="flex justify-between text-xs font-bold mb-3">
                            <span class="text-lime flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">google</span> Google Gemini (<span id="valGemini"><?= $distGemini ?></span>%)</span>
                            <span class="text-operon-energy flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">neurology</span> OpenAI GPT (<span id="valOpenAi"><?= $distOpenAI ?></span>%)</span>
                        </div>
                        
                        <div class="relative w-full h-3 bg-surface2 rounded-full border border-stroke appearance-none overflow-hidden flex cursor-pointer" id="distributionBar">
                            <!-- A div do Gemini cresce, a da OpenAI ocupa o resto -->
                            <div id="barGemini" class="h-full bg-lime border-r border-bg transition-all" style="width: <?= $distGemini ?>%;"></div>
                            <div id="barOpenAi" class="h-full bg-operon-energy flex-1 transition-all"></div>
                        </div>
                        <input type="hidden" name="dist_gemini" id="inputGemini" value="<?= $distGemini ?>">
                        <input type="hidden" name="dist_openai" id="inputOpenAi" value="<?= $distOpenAI ?>">

                        <div class="flex justify-between mt-3 text-[10px] text-muted tracking-widest uppercase font-black">
                            <span>0%</span>
                            <span>Range Dinâmico</span>
                            <span>100%</span>
                        </div>
                    </div>
                </div>

                <!-- API Keys Setup -->
                <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft">
                    <h3 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-base">key</span>
                        Chaves de API (Overrides Locais)
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Google Gemini API Key</label>
                            <input type="password" name="api_key_gemini" value="" placeholder="••••••••••••••••"
                                   class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">OpenAI API Key</label>
                            <input type="password" name="api_key_openai" value="" placeholder="sk-••••••••••••••••"
                                   class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                        </div>
                        <div class="col-span-1 md:col-span-2 text-[10px] text-subtle flex items-start gap-1 mt-1">
                            <span class="material-symbols-outlined text-[14px] text-mint">info</span>
                            <p>Deixar em branco fará o sistema utilizar as variáveis ambiente globais configuradas no `.env` do servidor (Recomendado para segurança corporativa).</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Limits & Metrics -->
            <div class="flex flex-col gap-6">

                <!-- Token Usage Overall -->
                <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft relative overflow-hidden">
                    <div class="absolute -right-6 -top-6 size-24 bg-lime/5 rounded-full blur-xl pointer-events-none"></div>
                    
                    <h3 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-base">battery_charging_full</span>
                        Token Economy
                    </h3>

                    <div class="mb-5">
                        <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Tier Comercial (Hard Limit Mensal)</label>
                        <select name="token_tier" class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text focus:outline-none focus:border-lime/50 transition-all">
                            <option value="starter" <?= ($config['tier'] ?? 'starter') === 'starter' ? 'selected' : '' ?>>Starter (Standard Limits)</option>
                            <option value="pro" <?= ($config['tier'] ?? '') === 'pro' ? 'selected' : '' ?>>Pro (High Performance)</option>
                            <option value="elite" <?= ($config['tier'] ?? '') === 'elite' ? 'selected' : '' ?>>Elite (Unmetered / Custom)</option>
                        </select>
                    </div>

                    <?php if (!empty($tokenBalance)): ?>
                    <div class="bg-surface2 border border-stroke rounded-xl p-4 text-center">
                        <div class="text-2xl font-black text-text mb-1">
                            <?= number_format($tokenBalance['used'] ?? 0, 0, ',', '.') ?> 
                            <span class="text-sm text-subtle font-medium">/ <?= number_format($tokenBalance['limit'] ?? 0, 0, ',', '.') ?></span>
                        </div>
                        <p class="text-[10px] font-bold text-muted uppercase tracking-widest mb-3">Tokens Consumidos</p>

                        <div class="w-full h-1.5 bg-surface rounded-full overflow-hidden border border-stroke">
                            <?php $pct = min(100, round((($tokenBalance['used'] ?? 0) / max($tokenBalance['limit'] ?? 1, 1)) * 100)); ?>
                            <div class="h-full rounded-full bg-lime shadow-glow-sm" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Save Action -->
                <button type="submit" class="w-full py-3.5 bg-lime rounded-pill text-bg text-sm font-black hover:brightness-110 transition-all shadow-glow flex justify-center items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span> Salvar Política de IA
                </button>

            </div>

        </div>
    </form>

</div>

<script>
// Interactive Slider Logic for AI Distribution
document.addEventListener('DOMContentLoaded', () => {
    const barContainer = document.getElementById('distributionBar');
    const barGemini = document.getElementById('barGemini');
    
    const valGeminiTxt = document.getElementById('valGemini');
    const valOpenAiTxt = document.getElementById('valOpenAi');
    
    const inputGemini = document.getElementById('inputGemini');
    const inputOpenAi = document.getElementById('inputOpenAi');

    let isDragging = false;

    function updateDistribution(e) {
        const rect = barContainer.getBoundingClientRect();
        let x = e.clientX - rect.left;
        
        // Boundaries
        if (x < 0) x = 0;
        if (x > rect.width) x = rect.width;

        let pctGemini = Math.round((x / rect.width) * 100);
        let pctOpenAi = 100 - pctGemini;

        // Animação/Update UI
        barGemini.style.width = pctGemini + '%';
        valGeminiTxt.innerText = pctGemini;
        valOpenAiTxt.innerText = pctOpenAi;

        // Atualiza inputs pro POST
        inputGemini.value = pctGemini;
        inputOpenAi.value = pctOpenAi;
    }

    barContainer.addEventListener('mousedown', (e) => {
        isDragging = true;
        updateDistribution(e);
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        updateDistribution(e);
    });

    document.addEventListener('mouseup', () => {
        if (isDragging) {
            isDragging = false;
        }
    });
});
</script>
