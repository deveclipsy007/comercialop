<?php
$pageTitle    = 'Admin';
$pageSubtitle = 'Configurações do Sistema';
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">Configurações da Agência</h2>
            <p class="text-sm text-slate-400 mt-0.5">Defina o contexto que a IA usa para personalizar todas as análises</p>
        </div>
        <div class="flex items-center gap-2 text-xs text-muted font-bold tracking-widest uppercase bg-surface2 border border-stroke px-3 py-1.5 rounded-pill shadow-soft">
            <span class="material-symbols-outlined text-[14px]">shield</span>
            Provider: <span class="text-lime"><?= e(strtoupper($config['provider'] ?? 'gemini')) ?></span>
        </div>
    </div>

    <form method="POST" action="/admin/save">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Core Settings -->
            <div class="lg:col-span-2 flex flex-col gap-5">

                <!-- Agency Identity -->
                <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft">
                    <h3 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-base">business</span>
                        Identidade da Agência
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Nome da Agência</label>
                            <input type="text" name="agency_name" value="<?= e($settings['agency_name'] ?? '') ?>" placeholder="Agência Nexus Digital"
                                   class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Cidade / Região</label>
                            <input type="text" name="agency_city" value="<?= e($settings['agency_city'] ?? '') ?>" placeholder="São Paulo, SP"
                                   class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Nicho de Atuação</label>
                            <input type="text" name="agency_niche" value="<?= e($settings['agency_niche'] ?? '') ?>" placeholder="Marketing Digital"
                                   class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Resumo da Oferta</label>
                            <textarea name="offer_summary" rows="2" placeholder="Descreva brevemente..."
                                      class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all resize-none"><?= e($settings['offer_summary'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Links rápidos p/ as outras seções -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <a href="/admin/ai-config" class="bg-surface2 border border-stroke hover:border-lime/30 rounded-card p-5 group transition-all flex flex-col justify-between h-32">
                        <div class="flex items-center gap-3 text-text group-hover:text-lime transition-colors">
                            <span class="material-symbols-outlined rounded-full bg-surface p-2 border border-stroke">psychology</span>
                            <span class="font-bold text-sm">Motor Neural (I.A)</span>
                        </div>
                        <p class="text-xs text-subtle">Roteamento de prompts, LLMs e Limits.</p>
                    </a>

                    <a href="/admin/users" class="bg-surface2 border border-stroke hover:border-lime/30 rounded-card p-5 group transition-all flex flex-col justify-between h-32">
                        <div class="flex items-center gap-3 text-text group-hover:text-lime transition-colors">
                            <span class="material-symbols-outlined rounded-full bg-surface p-2 border border-stroke">group</span>
                            <span class="font-bold text-sm">Equipe</span>
                        </div>
                        <p class="text-xs text-subtle">Acessos, cargos e logins individuais.</p>
                    </a>
                    
                    <a href="/admin/logs" class="bg-surface2 border border-stroke hover:border-mint/30 rounded-card p-5 group transition-all flex flex-col justify-between h-32">
                        <div class="flex items-center gap-3 text-text group-hover:text-mint transition-colors">
                            <span class="material-symbols-outlined rounded-full bg-surface p-2 border border-stroke">history</span>
                            <span class="font-bold text-sm">Auditoria</span>
                        </div>
                        <p class="text-xs text-subtle">Trilhas de uso de base e tokens globais.</p>
                    </a>
                </div>

            </div>

            <!-- Right: Token + Status -->
            <div class="flex flex-col gap-5">

                <!-- AI Setup Callout (Substitui os tokens visuais pra simplificar) -->
                <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft text-center group">
                    <div class="size-16 mx-auto bg-lime/10 rounded-full flex items-center justify-center text-lime mb-3 border border-lime/20 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-2xl">neurology</span>
                    </div>
                    <h4 class="font-bold text-text mb-1 text-sm">Motor Neural Mapeado</h4>
                    <p class="text-xs text-subtle leading-relaxed mb-4">A inteligência artificial irá absorver as identidades e configurações de nicho acima para orquestrar as abordagens dos Leads.</p>
                    
                    <a href="/admin/ai-config" class="inline-block px-4 py-1.5 text-xs font-bold text-lime uppercase tracking-widest border border-lime/20 rounded-pill hover:bg-lime/10 transition-colors">
                        Gerenciar IA
                    </a>
                </div>

                <!-- Save Button -->
                <button type="submit"
                        class="w-full py-3.5 bg-lime rounded-pill text-bg text-sm font-black hover:brightness-110 transition-all shadow-glow flex justify-center items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span> Salvar Modificações
                </button>

            </div>
        </div>
    </form>

</div>

<script>
function addService() {
    const list = document.getElementById('servicesList');
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2';
    row.innerHTML = `
        <input type="text" name="services[][name]" placeholder="Nome do serviço"
               class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 transition-all">
        <input type="number" name="services[][price]" placeholder="Preço (R$)"
               class="w-32 bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 transition-all">
        <button type="button" onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-300">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>`;
    list.appendChild(row);
}
</script>
