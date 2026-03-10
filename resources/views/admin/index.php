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
        <div class="flex items-center gap-2 text-xs text-slate-500 bg-white/3 border border-white/8 px-3 py-1.5 rounded-xl">
            <span class="material-symbols-outlined text-sm">shield</span>
            Provider: <span class="text-operon-energy font-bold ml-1"><?= e(strtoupper($config['provider'] ?? 'gemini')) ?></span>
        </div>
    </div>

    <form method="POST" action="/admin/save">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Core Settings -->
            <div class="lg:col-span-2 flex flex-col gap-5">

                <!-- Agency Identity -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-operon-energy text-base">business</span>
                        Identidade da Agência
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nome da Agência</label>
                            <input type="text" name="agency_name" value="<?= e($settings['agency_name'] ?? '') ?>" placeholder="Agência Nexus Digital"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Cidade / Região</label>
                            <input type="text" name="agency_city" value="<?= e($settings['agency_city'] ?? '') ?>" placeholder="São Paulo, SP"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nicho de Atuação</label>
                            <input type="text" name="agency_niche" value="<?= e($settings['agency_niche'] ?? '') ?>" placeholder="Marketing Digital para Negócios Locais"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Resumo da Oferta (para IA)</label>
                            <textarea name="offer_summary" rows="3" placeholder="Descreva o que sua agência oferece em 2-3 frases diretas..."
                                      class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all resize-none"><?= e($settings['offer_summary'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Differentials -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-operon-energy text-base">star</span>
                        Diferenciais (um por linha)
                    </h3>
                    <textarea name="differentials" rows="5"
                              placeholder="IA integrada em todos os processos&#10;Cases comprovados com +300% de crescimento&#10;Gestor dedicado com acesso direto&#10;Relatórios semanais com dados reais"
                              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all resize-none"><?= e(implode("\n", $settings['differentials'] ?? [])) ?></textarea>
                </div>

                <!-- ICP Profile -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-operon-energy text-base">person_search</span>
                        Perfil do Cliente Ideal (ICP)
                    </h3>
                    <textarea name="icp_profile" rows="3"
                              placeholder="Empresas locais com faturamento entre R$50k-500k/mês, presença digital fraca, que perdem clientes para a concorrência online..."
                              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all resize-none"><?= e($settings['icp_profile'] ?? '') ?></textarea>
                </div>

                <!-- Services -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-operon-energy text-base">inventory</span>
                        Serviços e Preços
                    </h3>
                    <div id="servicesList" class="flex flex-col gap-2 mb-3">
                        <?php foreach (($settings['services'] ?? []) as $svc): ?>
                        <div class="flex items-center gap-2">
                            <input type="text" name="services[][name]" value="<?= e($svc['name']) ?>" placeholder="Nome do serviço"
                                   class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 transition-all">
                            <input type="number" name="services[][price]" value="<?= $svc['price'] ?>" placeholder="Preço (R$)"
                                   class="w-32 bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 transition-all">
                            <button type="button" onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-300">
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addService()"
                            class="flex items-center gap-1.5 text-xs text-operon-energy hover:underline font-medium">
                        <span class="material-symbols-outlined text-base">add_circle</span>
                        Adicionar serviço
                    </button>
                </div>

                <!-- Custom Context -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-operon-energy text-base">psychology</span>
                        Contexto Personalizado para IA
                    </h3>
                    <p class="text-xs text-slate-500 mb-3">Informações adicionais para personalizar ainda mais as análises de IA (cases, objeções comuns, etc.)</p>
                    <textarea name="custom_context" rows="4"
                              placeholder="Case: Clínica Dental Smile — aumentamos o agendamento em 340% em 4 meses com tráfego pago + automação WhatsApp. Objeção mais comum: 'já tenho um site'. Nossa resposta: site sem tráfego não converte..."
                              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all resize-none"><?= e($settings['custom_context'] ?? '') ?></textarea>
                </div>

            </div>

            <!-- Right: Token + Status -->
            <div class="flex flex-col gap-5">

                <!-- Token Economy -->
                <?php if (!empty($tokenBalance)): ?>
                <div class="bg-brand-surface border border-operon-energy/20 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-operon-energy text-base">bolt</span>
                        Token Economy
                    </h3>
                    <div class="text-center mb-4">
                        <div class="text-3xl font-black text-operon-energy"><?= $tokenBalance['remaining'] ?? 0 ?></div>
                        <p class="text-xs text-slate-400 mt-1">tokens disponíveis</p>
                    </div>
                    <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden mb-3">
                        <?php $pct = min(100, round((($tokenBalance['used'] ?? 0) / max($tokenBalance['limit'] ?? 1, 1)) * 100)); ?>
                        <div class="h-full rounded-full token-bar-fill" style="width: <?= $pct ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500 mb-4">
                        <span>Usados: <b class="text-white"><?= $tokenBalance['used'] ?? 0 ?></b></span>
                        <span>Limite: <b class="text-white"><?= $tokenBalance['limit'] ?? 0 ?></b></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Plano:</span>
                        <span class="font-bold text-operon-energy uppercase"><?= $tokenBalance['tier'] ?? 'starter' ?></span>
                    </div>
                    <div class="flex items-center justify-between text-xs mt-1">
                        <span class="text-slate-500">Reset:</span>
                        <span class="text-slate-300"><?= $tokenBalance['reset_at'] ?? '—' ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- AI Config -->
                <div class="bg-brand-surface border border-white/8 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-operon-energy text-base">smart_toy</span>
                        Configuração IA
                    </h3>
                    <div class="flex flex-col gap-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Modelo ativo</span>
                            <span class="font-bold text-white">Operon Intelligence</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Provider</span>
                            <span class="font-bold text-operon-energy uppercase"><?= e($config['provider'] ?? 'gemini') ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Google Search</span>
                            <span class="size-2 bg-operon-energy rounded-full inline-block animate-pulse"></span>
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-600 mt-4">Configure as API keys no arquivo .env</p>
                </div>

                <!-- Save Button -->
                <button type="submit"
                        class="w-full py-3.5 bg-operon-energy rounded-xl text-black text-sm font-black hover:brightness-110 transition-all shadow-lg glow-energy-sm">
                    Salvar Configurações
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
