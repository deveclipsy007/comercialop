<?php
$timezone = $agencySettings['timezone'] ?? 'America/Sao_Paulo';
$aiModel = $agencySettings['ai_model'] ?? 'gemini-2.5-flash';
?>
<div class="max-w-6xl mx-auto p-6 md:p-8 space-y-8">
    <div class="mb-2">
        <h1 class="text-2xl font-bold text-text">Configurações Gerais</h1>
        <p class="text-sm text-muted mt-1">Gerencie as preferências da agência e comportamento da IA no sistema.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        
        <div class="col-span-1 border-b md:border-b-0 md:border-r border-stroke pb-6 md:pb-0 md:pr-6">
            <ul class="space-y-1.5">
                <li>
                    <a href="#" class="block px-4 py-2.5 text-sm font-bold text-lime bg-lime/10 rounded-lg transition-colors">Geral</a>
                </li>
                <li>
                    <a href="#" class="block px-4 py-2.5 text-sm font-medium text-muted hover:text-text hover:bg-surface2 rounded-lg transition-colors">Notificações</a>
                </li>
                <li>
                    <a href="#" class="block px-4 py-2.5 text-sm font-medium text-muted hover:text-text hover:bg-surface2 rounded-lg transition-colors">Equipe</a>
                </li>
                <li>
                    <a href="#" class="block px-4 py-2.5 text-sm font-medium text-muted hover:text-text hover:bg-surface2 rounded-lg transition-colors">Campos Personalizados</a>
                </li>
            </ul>
        </div>

        <div class="col-span-1 md:col-span-3 space-y-6">
            <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">
                <h3 class="text-lg font-bold text-text mb-8 flex items-center gap-2 pb-4 border-b border-stroke">
                    <span class="material-symbols-outlined text-muted text-[20px]">tune</span> Preferências da Plataforma
                </h3>
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Fuso Horário</label>
                        <select name="timezone" class="w-full h-12 bg-surface2 border border-stroke rounded-pill px-5 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer">
                            <option value="America/Sao_Paulo" <?= $timezone === 'America/Sao_Paulo' ? 'selected' : '' ?>>America/Sao_Paulo (GMT-3)</option>
                            <option value="UTC" <?= $timezone === 'UTC' ? 'selected' : '' ?>>UTC</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Modelo de Linguagem (Operon AI) *</label>
                        <select name="ai_model" class="w-full h-12 bg-surface2 border border-stroke rounded-pill px-5 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer">
                            <option value="gemini-2.5-flash" <?= $aiModel === 'gemini-2.5-flash' ? 'selected' : '' ?>>Gemini 2.5 Flash (Rápido & Econômico)</option>
                            <option value="gemini-2.5-pro" <?= $aiModel === 'gemini-2.5-pro' ? 'selected' : '' ?>>Gemini 2.5 Pro (Avançado - Maior Consumo)</option>
                            <option value="gpt-4o" <?= $aiModel === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o (OpenAI Premium)</option>
                        </select>
                        <p class="text-xs text-subtle mt-2 flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">info</span> Define qual LLM será utilizado por padrão no Copilot e análises breves.</p>
                    </div>

                    <div class="pt-6 mt-6 border-t border-stroke flex justify-end">
                        <button class="h-11 px-6 bg-lime text-bg text-sm font-bold rounded-pill shadow-glow hover:brightness-110 transition-all">
                            Salvar Configurações
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
