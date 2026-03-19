<?php
$pageTitle    = 'Provedores de IA (Admin)';
$pageSubtitle = 'Configuração de provedores por operação';

// Dados do controller: $configs (array), $operations (array de nomes)
$configsByOp = [];
foreach (($configs ?? []) as $cfg) {
    $configsByOp[$cfg['operation']] = $cfg;
}

$availableProviders = ['gemini', 'openai', 'grok'];
$availableModels = [
    'gemini' => ['gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-pro', 'gemini-1.5-flash'],
    'openai' => ['gpt-4o', 'gpt-4o-mini'],
    'grok'   => ['grok-2', 'grok-3-mini'],
];

$operationLabels = [
    'lead_analysis' => 'Análise de Lead',
    'deep_analysis' => 'Deep Insights',
    'deal_insights' => 'Deal Insights',
    'script_variations' => 'Variações de Script',
    'operon_diagnostico' => 'Operon: Diagnóstico',
    'operon_potencial' => 'Operon: Potencial',
    'operon_autoridade' => 'Operon: Autoridade',
    'operon_script' => 'Operon: Script',
    'audio_strategy' => 'Análise de Áudio',
    'spin_questions' => 'SPIN Questions',
    'copilot_message' => 'Copilot',
    'social_analysis' => 'Análise Social',
    'hunter' => 'Hunter (Busca)',
    'lead_offerings_analysis' => 'Deep: Proposta de Valor',
    'lead_clients_analysis' => 'Deep: Público-Alvo',
    'lead_competitors_analysis' => 'Deep: Concorrentes',
    'lead_sales_potential_analysis' => 'Deep: Potencial Vendas',
    'wa_summary' => 'WhatsApp: Resumo',
    'wa_next_message' => 'WhatsApp: Próx. Mensagem',
    'wa_strategic' => 'WhatsApp: Estratégica',
    'wa_interest_score' => 'WhatsApp: Score',
    'knowledge_index' => 'RAG: Indexação',
    'embedding_query' => 'RAG: Query',
    'default' => 'Padrão (Fallback)',
];
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-text">Provedores por Operação</h2>
            <p class="text-sm text-subtle mt-0.5">Configure qual provedor e modelo de IA é utilizado em cada operação do sistema.</p>
        </div>
        <div class="flex items-center gap-2 text-xs text-muted bg-surface2 border border-stroke px-3 py-1.5 rounded-pill shadow-soft">
            <span class="material-symbols-outlined text-[14px]">route</span>
            <?= count($configs ?? []) ?> configurações ativas
        </div>
    </div>

    <!-- Info -->
    <div class="bg-surface2 border border-lime/20 rounded-cardLg p-4 flex items-start gap-3">
        <span class="material-symbols-outlined text-lime text-lg mt-0.5">info</span>
        <div class="text-xs text-subtle leading-relaxed">
            <span class="text-text font-bold">Roteamento Inteligente:</span>
            Operações sem configuração personalizada usam o provedor padrão definido na distribuição de carga (Gestão de I.A).
            Configure aqui para direcionar operações específicas para provedores diferentes.
        </div>
    </div>

    <!-- Operations Table -->
    <div class="bg-surface border border-stroke rounded-cardLg shadow-soft overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-stroke bg-surface2">
                        <th class="text-left px-5 py-3 text-[11px] font-bold text-muted uppercase tracking-wider">Operação</th>
                        <th class="text-left px-5 py-3 text-[11px] font-bold text-muted uppercase tracking-wider">Provedor</th>
                        <th class="text-left px-5 py-3 text-[11px] font-bold text-muted uppercase tracking-wider">Modelo</th>
                        <th class="text-center px-5 py-3 text-[11px] font-bold text-muted uppercase tracking-wider">Status</th>
                        <th class="text-center px-5 py-3 text-[11px] font-bold text-muted uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($operations ?? []) as $op):
                        $cfg = $configsByOp[$op] ?? null;
                        $label = $operationLabels[$op] ?? $op;
                        $hasCustom = !empty($cfg);
                    ?>
                    <tr class="border-b border-stroke/50 hover:bg-surface2/50 transition-colors" id="row_<?= $op ?>">
                        <td class="px-5 py-3">
                            <div class="font-bold text-text"><?= e($label) ?></div>
                            <div class="text-[10px] text-subtle font-mono"><?= e($op) ?></div>
                        </td>
                        <td class="px-5 py-3">
                            <?php if ($hasCustom): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-pill text-xs font-bold bg-lime/10 text-lime border border-lime/20">
                                    <?= e($cfg['provider']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-subtle text-xs">Auto (distribuição)</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3">
                            <?php if ($hasCustom): ?>
                                <span class="text-text text-xs font-mono"><?= e($cfg['model']) ?></span>
                            <?php else: ?>
                                <span class="text-subtle text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <?php if ($hasCustom): ?>
                                <span class="size-2 rounded-full inline-block <?= ($cfg['is_active'] ?? 1) ? 'bg-lime' : 'bg-red-400' ?>"></span>
                            <?php else: ?>
                                <span class="size-2 rounded-full inline-block bg-subtle/30"></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="openConfig('<?= $op ?>', '<?= e($label) ?>')" class="text-xs text-lime hover:text-lime/80 font-bold transition-colors">
                                    <?= $hasCustom ? 'Editar' : 'Configurar' ?>
                                </button>
                                <?php if ($hasCustom): ?>
                                <form method="POST" action="/admin/providers/delete" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="config_id" value="<?= e($cfg['id']) ?>">
                                    <button type="submit" onclick="return confirm('Remover config personalizada? Voltará ao roteamento automático.')"
                                            class="text-xs text-red-400 hover:text-red-300 font-bold transition-colors">
                                        Resetar
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Config Modal -->
<div id="configModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-cardLg shadow-soft w-full max-w-md p-6 mx-4">
        <h3 class="text-lg font-bold text-text mb-1" id="modalTitle">Configurar Operação</h3>
        <p class="text-xs text-subtle mb-6" id="modalSubtitle">Defina o provedor e modelo para esta operação.</p>

        <form method="POST" action="/admin/providers/save" id="configForm">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" id="modalOperation">
            <input type="hidden" name="is_active" value="1">

            <div class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Provedor</label>
                    <select name="provider" id="modalProvider" onchange="updateModels()"
                            class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text focus:outline-none focus:border-lime/50">
                        <option value="gemini">Google Gemini</option>
                        <option value="openai">OpenAI GPT</option>
                        <option value="grok">xAI Grok</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Modelo</label>
                    <select name="model" id="modalModel"
                            class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text focus:outline-none focus:border-lime/50">
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Prioridade (0 = principal)</label>
                    <input type="number" name="priority" value="0" min="0" max="10"
                           class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2.5 text-sm text-text focus:outline-none focus:border-lime/50">
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 py-2.5 bg-lime rounded-pill text-bg text-sm font-black hover:brightness-110 transition-all shadow-glow">
                    Salvar
                </button>
                <button type="button" onclick="closeConfig()" class="px-5 py-2.5 bg-surface2 border border-stroke rounded-pill text-sm font-bold text-text hover:bg-surface3 transition-all">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const models = <?= json_encode($availableModels) ?>;

function updateModels() {
    const provider = document.getElementById('modalProvider').value;
    const select = document.getElementById('modalModel');
    select.innerHTML = '';
    (models[provider] || []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m;
        select.appendChild(opt);
    });
}

function openConfig(operation, label) {
    document.getElementById('modalOperation').value = operation;
    document.getElementById('modalTitle').textContent = 'Configurar: ' + label;
    document.getElementById('modalSubtitle').textContent = 'Operação: ' + operation;
    updateModels();
    const modal = document.getElementById('configModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeConfig() {
    const modal = document.getElementById('configModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('configModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfig();
});
</script>
