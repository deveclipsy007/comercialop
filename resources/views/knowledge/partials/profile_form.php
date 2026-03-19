<?php
/**
 * Formulário de perfil da empresa — Knowledge Base
 *
 * @var array|null $profile
 */

// Helpers para preencher campos do formulário
$p = fn(string $key, $default = '') => $profile[$key] ?? $default;
$pJson = function(string $key, string $sep = "\n") use ($profile): string {
    $val = $profile[$key] ?? [];
    if (is_string($val)) $val = json_decode($val, true) ?? [];
    return is_array($val) ? implode($sep, $val) : '';
};
?>

<!-- ── UPLOAD DE DOCUMENTO ── -->
<div class="bg-surface border border-dashed border-lime/30 rounded-xl p-6 mb-6">
    <div class="flex items-start gap-4">
        <div class="size-12 rounded-xl bg-lime/10 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-lime text-2xl">upload_file</span>
        </div>
        <div class="flex-1">
            <div class="flex items-center justify-between gap-4 mb-1">
                <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Preenchimento Automático por Documento</h2>
                <button type="button" id="clear-all-fields" class="flex items-center gap-1.5 px-3 py-1.5 bg-red-400/10 hover:bg-red-400/20 text-red-400 text-[10px] uppercase tracking-wider font-bold rounded-lg transition-all border border-red-400/20">
                    <span class="material-symbols-outlined text-[14px]">delete_sweep</span>
                    Limpar Tudo
                </button>
            </div>
            <p class="text-xs text-muted mb-4">Envie um documento estratégico da empresa (proposta comercial, playbook, apresentação) e a IA irá extrair automaticamente as informações para preencher o formulário. Você poderá revisar antes de salvar.</p>

            <div id="doc-upload-zone" class="relative">
                <input type="file" id="doc-file-input" accept=".txt,.md,.csv,.pdf,.docx"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                <div id="doc-upload-label" class="flex items-center gap-3 bg-surface2 border border-stroke rounded-lg px-4 py-3 cursor-pointer hover:border-lime/40 transition-all">
                    <span class="material-symbols-outlined text-muted text-lg">description</span>
                    <span class="text-sm text-muted">Selecione um arquivo (.txt, .md, .csv, .pdf, .docx) — máx 5MB</span>
                </div>
            </div>

            <!-- Status -->
            <div id="doc-upload-status" class="hidden mt-3">
                <div id="doc-upload-processing" class="hidden flex items-center gap-2 text-xs text-lime">
                    <span class="material-symbols-outlined text-[14px] animate-spin">refresh</span>
                    <span>Analisando documento com IA...</span>
                </div>
                <div id="doc-upload-success" class="hidden flex items-center gap-2 text-xs text-mint">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                    <span id="doc-upload-success-msg"></span>
                </div>
                <div id="doc-upload-error" class="hidden flex items-center gap-2 text-xs text-red-400">
                    <span class="material-symbols-outlined text-[14px]">error</span>
                    <span id="doc-upload-error-msg"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const fileInput = document.getElementById('doc-file-input');
    const statusDiv = document.getElementById('doc-upload-status');
    const processingDiv = document.getElementById('doc-upload-processing');
    const successDiv = document.getElementById('doc-upload-success');
    const errorDiv = document.getElementById('doc-upload-error');
    const labelDiv = document.getElementById('doc-upload-label');

    if (!fileInput) return;

    fileInput.addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        // Show processing
        statusDiv.classList.remove('hidden');
        processingDiv.classList.remove('hidden');
        successDiv.classList.add('hidden');
        errorDiv.classList.add('hidden');
        labelDiv.querySelector('span:last-child').textContent = file.name;

        const formData = new FormData();
        formData.append('document', file);

        try {
            const res = await fetch('/knowledge/extract-document', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            processingDiv.classList.add('hidden');

            if (data.success && data.extracted) {
                const filled = fillFormFromExtraction(data.extracted);
                successDiv.classList.remove('hidden');
                document.getElementById('doc-upload-success-msg').textContent =
                    `${filled} campos preenchidos a partir de "${data.filename}". Revise e clique em Salvar.`;
            } else {
                errorDiv.classList.remove('hidden');
                document.getElementById('doc-upload-error-msg').textContent = data.error || 'Erro desconhecido.';
            }
        } catch (e) {
            processingDiv.classList.add('hidden');
            errorDiv.classList.remove('hidden');
            document.getElementById('doc-upload-error-msg').textContent = 'Erro de conexão: ' + e.message;
        }

        // Reset file input
        this.value = '';
    });

    const clearBtn = document.getElementById('clear-all-fields');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (!confirm('Deseja realmente limpar todos os campos do formulário?')) return;

            const form = document.getElementById('knowledge-profile-form');
            if (!form) return;

            // Simple text/url/textarea fields
            form.querySelectorAll('input[type="text"], input[type="url"], textarea').forEach(el => {
                el.value = '';
                el.classList.remove('border-lime/40');
                el.classList.remove('border-stroke');
                el.classList.add('border-stroke');
            });

            // Selects
            form.querySelectorAll('select').forEach(el => {
                el.selectedIndex = 0;
            });

            // Reset dynamic lists - Back to 1 empty row state
            const dynamicLists = [
                {
                    id: 'services-list',
                    html: `<div class="flex gap-2 items-start service-row"><div class="flex-1 grid grid-cols-3 gap-2"><input type="text" name="services[0][name]" placeholder="Nome do serviço" class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="services[0][description]" placeholder="Descrição breve" class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="services[0][price_range]" placeholder="Preço" class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"></div></div>`
                },
                {
                    id: 'cases-list',
                    html: `<div class="bg-surface2 border border-stroke rounded-xl p-4 space-y-4 case-row"><div class="grid grid-cols-1 sm:grid-cols-2 gap-4"><input type="text" name="cases[0][client]" placeholder="Nome do Cliente/Caso" class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="cases[0][result]" placeholder="Resultado alcançado" class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="cases[0][niche]" placeholder="Nicho do cliente" class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="cases[0][timeframe]" placeholder="Prazo do projeto" class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"></div></div>`
                },
                {
                    id: 'objections-list',
                    html: `<div class="flex gap-2 objection-row"><div class="flex-1 grid grid-cols-2 gap-2"><input type="text" name="objection_responses[0][objection]" placeholder="Ex: Está caro demais..." class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="objection_responses[0][response]" placeholder="Ex: Nossa qualidade justifica..." class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"></div></div>`
                },
                {
                    id: 'competitors-list',
                    html: `<div class="flex gap-2 competitor-row"><div class="flex-1 grid grid-cols-3 gap-2"><input type="text" name="competitors[0][name]" placeholder="Nome do concorrente" class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="competitors[0][weakness]" placeholder="Pontos fracos dele" class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"><input type="text" name="competitors[0][how_to_win]" placeholder="Como vencer" class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none"></div></div>`
                }
            ];

            dynamicLists.forEach(cfg => {
                const el = document.getElementById(cfg.id);
                if (el) el.innerHTML = cfg.html;
            });

            // Hide upload status
            if (statusDiv) statusDiv.classList.add('hidden');
            labelDiv.querySelector('span:last-child').textContent = 'Selecione um arquivo (.txt, .md, .csv, .pdf, .docx) — máx 5MB';
        });
    }

    function fillFormFromExtraction(data) {
        let filled = 0;
        const form = document.getElementById('knowledge-profile-form');
        if (!form) return 0;

        // Simple text fields
        const simpleFields = {
            'agency_name': data.agency_name,
            'agency_niche': data.agency_niche,
            'agency_city': data.agency_city,
            'agency_state': data.agency_state,
            'offer_summary': data.offer_summary,
            'offer_price_range': data.offer_price_range,
            'unique_value_prop': data.unique_value_prop,
            'guarantees': data.guarantees,
            'delivery_timeline': data.delivery_timeline,
            'icp_profile': data.icp_profile,
            'icp_company_size': data.icp_company_size,
            'icp_ticket_range': data.icp_ticket_range,
            'pricing_justification': data.pricing_justification,
            'custom_context': data.custom_context,
            'awards_recognition': data.awards_recognition,
        };

        for (const [name, value] of Object.entries(simpleFields)) {
            if (value) {
                const el = form.querySelector(`[name="${name}"]`);
                if (el && !el.value.trim()) {
                    el.value = value;
                    el.classList.add('border-lime/40');
                    setTimeout(() => el.classList.remove('border-lime/40'), 3000);
                    filled++;
                }
            }
        }

        // Textarea array fields (one per line)
        const textareaArrays = {
            'differentials': data.differentials,
            'icp_segment': data.icp_segment,
            'icp_pain_points': data.icp_pain_points,
        };

        for (const [name, arr] of Object.entries(textareaArrays)) {
            if (arr && Array.isArray(arr) && arr.length > 0) {
                const el = form.querySelector(`[name="${name}"]`);
                if (el && !el.value.trim()) {
                    el.value = arr.join('\n');
                    el.classList.add('border-lime/40');
                    setTimeout(() => el.classList.remove('border-lime/40'), 3000);
                    filled++;
                }
            }
        }

        // Dynamic services
        if (data.services && Array.isArray(data.services) && data.services.length > 0) {
            const list = document.getElementById('services-list');
            if (list) {
                // Check if current services are empty
                const existing = list.querySelectorAll('.service-row');
                const firstEmpty = existing.length === 1 &&
                    !list.querySelector('input[name*="[name]"]')?.value?.trim();
                if (firstEmpty) list.innerHTML = '';

                data.services.forEach((svc, i) => {
                    const idx = existing.length > 1 ? existing.length + i : i;
                    list.insertAdjacentHTML('beforeend', `
                        <div class="flex gap-2 items-start service-row" style="border-left: 2px solid var(--lime); padding-left: 6px;">
                            <div class="flex-1 grid grid-cols-3 gap-2">
                                <input type="text" name="services[${idx}][name]" value="${escAttr(svc.name || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                                <input type="text" name="services[${idx}][description]" value="${escAttr(svc.description || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                                <input type="text" name="services[${idx}][price_range]" value="${escAttr(svc.price_range || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                            </div>
                            <button type="button" onclick="this.closest('.service-row').remove()"
                                    class="text-muted hover:text-red-400 transition-colors mt-2">
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>`);
                    filled++;
                });
            }
        }

        // Dynamic cases
        if (data.cases && Array.isArray(data.cases) && data.cases.length > 0) {
            const list = document.getElementById('cases-list');
            if (list) {
                const existing = list.querySelectorAll('.case-row');
                const firstEmpty = existing.length === 1 &&
                    !list.querySelector('input[name*="[client]"]')?.value?.trim();
                if (firstEmpty) list.innerHTML = '';

                data.cases.forEach((c, i) => {
                    const idx = existing.length > 1 ? existing.length + i : i;
                    list.insertAdjacentHTML('beforeend', `
                        <div class="bg-surface2 border border-lime/40 rounded-lg p-3 space-y-2 case-row">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <input type="text" name="cases[${idx}][client]" value="${escAttr(c.client || '')}"
                                       class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text">
                                <input type="text" name="cases[${idx}][result]" value="${escAttr(c.result || '')}"
                                       class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text">
                                <input type="text" name="cases[${idx}][niche]" value="${escAttr(c.niche || '')}"
                                       class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text">
                                <div class="flex gap-2">
                                    <input type="text" name="cases[${idx}][timeframe]" value="${escAttr(c.timeframe || '')}"
                                           class="flex-1 bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text">
                                    <button type="button" onclick="this.closest('.case-row').remove()"
                                            class="text-muted hover:text-red-400 transition-colors">
                                        <span class="material-symbols-outlined text-lg">close</span>
                                    </button>
                                </div>
                            </div>
                        </div>`);
                    filled++;
                });
            }
        }

        // Dynamic objections
        if (data.objection_responses && Array.isArray(data.objection_responses) && data.objection_responses.length > 0) {
            const list = document.getElementById('objections-list');
            if (list) {
                const existing = list.querySelectorAll('.objection-row');
                const firstEmpty = existing.length === 1 &&
                    !list.querySelector('input[name*="[objection]"]')?.value?.trim();
                if (firstEmpty) list.innerHTML = '';

                data.objection_responses.forEach((o, i) => {
                    const idx = existing.length > 1 ? existing.length + i : i;
                    list.insertAdjacentHTML('beforeend', `
                        <div class="flex gap-2 objection-row">
                            <div class="flex-1 grid grid-cols-2 gap-2">
                                <input type="text" name="objection_responses[${idx}][objection]" value="${escAttr(o.objection || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                                <input type="text" name="objection_responses[${idx}][response]" value="${escAttr(o.response || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                            </div>
                            <button type="button" onclick="this.closest('.objection-row').remove()"
                                    class="text-muted hover:text-red-400 transition-colors mt-2">
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>`);
                    filled++;
                });
            }
        }

        // Dynamic competitors
        if (data.competitors && Array.isArray(data.competitors) && data.competitors.length > 0) {
            const list = document.getElementById('competitors-list');
            if (list) {
                const existing = list.querySelectorAll('.competitor-row');
                const firstEmpty = existing.length === 1 &&
                    !list.querySelector('input[name*="[name]"]')?.value?.trim();
                if (firstEmpty) list.innerHTML = '';

                data.competitors.forEach((comp, i) => {
                    const idx = existing.length > 1 ? existing.length + i : i;
                    list.insertAdjacentHTML('beforeend', `
                        <div class="flex gap-2 competitor-row">
                            <div class="flex-1 grid grid-cols-3 gap-2">
                                <input type="text" name="competitors[${idx}][name]" value="${escAttr(comp.name || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                                <input type="text" name="competitors[${idx}][weakness]" value="${escAttr(comp.weakness || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                                <input type="text" name="competitors[${idx}][how_to_win]" value="${escAttr(comp.how_to_win || '')}"
                                       class="bg-surface2 border border-lime/40 rounded-lg px-3 py-2 text-sm text-text">
                            </div>
                            <button type="button" onclick="this.closest('.competitor-row').remove()"
                                    class="text-muted hover:text-red-400 transition-colors mt-2">
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>`);
                    filled++;
                });
            }
        }

        return filled;
    }

    function escAttr(s) {
        return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
}());
</script>

<form id="knowledge-profile-form" class="space-y-6">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

    <!-- ── IDENTIDADE ─────────────────────────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-xl p-6 space-y-5">
        <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Identidade da Agência</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-muted mb-1">Nome da Agência</label>
                <input type="text" name="agency_name" value="<?= e($p('agency_name')) ?>"
                       placeholder="Ex: Nexus Digital"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-muted mb-1">Nicho Principal</label>
                <input type="text" name="agency_niche" value="<?= e($p('agency_niche')) ?>"
                       placeholder="Ex: Marketing Digital para Saúde"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-muted mb-1">Cidade</label>
                <input type="text" name="agency_city" value="<?= e($p('agency_city')) ?>"
                       placeholder="Ex: São Paulo"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-muted mb-1">Estado</label>
                <input type="text" name="agency_state" value="<?= e($p('agency_state')) ?>"
                       placeholder="Ex: SP"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-muted mb-1">Ano de Fundação</label>
                <input type="text" name="founding_year" value="<?= e($p('founding_year')) ?>"
                       placeholder="Ex: 2019"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-muted mb-1">Tamanho do Time</label>
                <select name="team_size"
                        class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <?php foreach (['1-5','6-20','21-50','50+'] as $size): ?>
                    <option value="<?= $size ?>" <?= $p('team_size') === $size ? 'selected' : '' ?>><?= $size ?> funcionários</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-muted mb-1">Website</label>
                <input type="url" name="website_url" value="<?= e($p('website_url')) ?>"
                       placeholder="https://suaagencia.com.br"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
        </div>
    </div>

    <!-- ── OFERTA ─────────────────────────────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-xl p-6 space-y-5">
        <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Oferta &amp; Serviços</h2>

        <div>
            <label class="block text-xs text-muted mb-1">Resumo da Oferta</label>
            <textarea name="offer_summary" rows="3"
                      placeholder="Descreva em 2-3 frases o que sua agência oferece e como resolve os problemas dos clientes."
                      class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($p('offer_summary')) ?></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-muted mb-1">Faixa de Preço</label>
                <input type="text" name="offer_price_range" value="<?= e($p('offer_price_range')) ?>"
                       placeholder="Ex: R$ 1.500 - R$ 8.000/mês"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-muted mb-1">Prazo de Entrega</label>
                <input type="text" name="delivery_timeline" value="<?= e($p('delivery_timeline')) ?>"
                       placeholder="Ex: Resultados em 30 dias"
                       class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
            </div>
        </div>

        <!-- Serviços dinâmicos -->
        <div>
            <label class="block text-xs text-muted mb-2">Serviços Oferecidos</label>
            <div id="services-list" class="space-y-2">
                <?php
                $servicesArr = $profile['services'] ?? [];
                if (is_string($servicesArr)) $servicesArr = json_decode($servicesArr, true) ?? [];
                if (empty($servicesArr)) $servicesArr = [['name'=>'','description'=>'','price_range'=>'']];
                foreach ($servicesArr as $i => $svc):
                ?>
                <div class="flex gap-2 items-start service-row">
                    <div class="flex-1 grid grid-cols-3 gap-2">
                        <input type="text" name="services[<?= $i ?>][name]" value="<?= e($svc['name'] ?? '') ?>"
                               placeholder="Nome do serviço"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                        <input type="text" name="services[<?= $i ?>][description]" value="<?= e($svc['description'] ?? '') ?>"
                               placeholder="Descrição breve"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                        <input type="text" name="services[<?= $i ?>][price_range]" value="<?= e($svc['price_range'] ?? '') ?>"
                               placeholder="R$ 0/mês"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    </div>
                    <button type="button" onclick="this.closest('.service-row').remove()"
                            class="text-muted hover:text-red-400 transition-colors mt-2">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="btn-add-service"
                    class="mt-2 text-xs text-lime hover:underline flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">add</span> Adicionar serviço
            </button>
        </div>

        <div>
            <label class="block text-xs text-muted mb-1">Garantias</label>
            <input type="text" name="guarantees" value="<?= e($p('guarantees')) ?>"
                   placeholder="Ex: Devolvemos o 1º mês se não houver resultado"
                   class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
        </div>
    </div>

    <!-- ── POSICIONAMENTO ─────────────────────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-xl p-6 space-y-5">
        <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Posicionamento</h2>

        <div>
            <label class="block text-xs text-muted mb-1">Proposta de Valor Única</label>
            <textarea name="unique_value_prop" rows="3"
                      placeholder="O que te torna diferente de todas as outras agências? Por que o cliente deve te escolher?"
                      class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($p('unique_value_prop')) ?></textarea>
        </div>

        <div>
            <label class="block text-xs text-muted mb-1">Diferenciais Competitivos <span class="text-subtle">(um por linha)</span></label>
            <textarea name="differentials" rows="4"
                      placeholder="Resultados mensuráveis em 30 dias&#10;Especialistas no mercado local&#10;Suporte 7 dias/semana"
                      class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($pJson('differentials')) ?></textarea>
        </div>

        <div>
            <label class="block text-xs text-muted mb-1">Prêmios e Reconhecimentos</label>
            <input type="text" name="awards_recognition" value="<?= e($p('awards_recognition')) ?>"
                   placeholder="Ex: Top 10 Agências Google Partners SP 2024"
                   class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
        </div>
    </div>

    <!-- ── ICP ────────────────────────────────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-xl p-6 space-y-5">
        <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Perfil de Cliente Ideal (ICP)</h2>

        <div>
            <label class="block text-xs text-muted mb-1">Descrição Narrativa do ICP</label>
            <textarea name="icp_profile" rows="3"
                      placeholder="Descreva quem é o cliente ideal: setor, porte, desafios, momento de compra..."
                      class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($p('icp_profile')) ?></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-muted mb-1">Segmentos-alvo <span class="text-subtle">(um por linha)</span></label>
                <textarea name="icp_segment" rows="4"
                          placeholder="saúde&#10;educação&#10;varejo&#10;serviços"
                          class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($pJson('icp_segment')) ?></textarea>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs text-muted mb-1">Porte da Empresa</label>
                    <input type="text" name="icp_company_size" value="<?= e($p('icp_company_size')) ?>"
                           placeholder="Ex: 2-50 funcionários"
                           class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-muted mb-1">Faixa de Ticket</label>
                    <input type="text" name="icp_ticket_range" value="<?= e($p('icp_ticket_range')) ?>"
                           placeholder="Ex: R$ 3.000 - R$ 15.000/mês"
                           class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs text-muted mb-1">Principais Dores do ICP <span class="text-subtle">(uma por linha)</span></label>
            <textarea name="icp_pain_points" rows="4"
                      placeholder="Não aparece no Google&#10;Concorrentes roubando clientes&#10;Site desatualizado&#10;Sem processo de captação"
                      class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($pJson('icp_pain_points')) ?></textarea>
        </div>
    </div>

    <!-- ── PROVA SOCIAL ───────────────────────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-xl p-6 space-y-5">
        <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Prova Social &amp; Cases</h2>

        <div id="cases-list" class="space-y-3">
            <?php
            $casesArr = $profile['cases'] ?? [];
            if (is_string($casesArr)) $casesArr = json_decode($casesArr, true) ?? [];
            if (empty($casesArr)) $casesArr = [['client'=>'','result'=>'','niche'=>'','timeframe'=>'']];
            foreach ($casesArr as $ci => $case):
            ?>
            <div class="bg-surface2 border border-stroke rounded-lg p-3 space-y-2 case-row">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <input type="text" name="cases[<?= $ci ?>][client]" value="<?= e($case['client'] ?? '') ?>"
                           placeholder="Cliente"
                           class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="cases[<?= $ci ?>][result]" value="<?= e($case['result'] ?? '') ?>"
                           placeholder="Resultado (+40% leads)"
                           class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="cases[<?= $ci ?>][niche]" value="<?= e($case['niche'] ?? '') ?>"
                           placeholder="Nicho"
                           class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <div class="flex gap-2">
                        <input type="text" name="cases[<?= $ci ?>][timeframe]" value="<?= e($case['timeframe'] ?? '') ?>"
                               placeholder="Em 90 dias"
                               class="flex-1 bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                        <button type="button" onclick="this.closest('.case-row').remove()"
                                class="text-muted hover:text-red-400 transition-colors">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="btn-add-case"
                class="text-xs text-lime hover:underline flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">add</span> Adicionar case
        </button>

        <div>
            <label class="block text-xs text-muted mb-1">URL do Portfólio</label>
            <input type="url" name="portfolio_url" value="<?= e($p('portfolio_url')) ?>"
                   placeholder="https://suaagencia.com/cases"
                   class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
        </div>
    </div>

    <!-- ── GESTÃO COMERCIAL ───────────────────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-xl p-6 space-y-5">
        <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Gestão Comercial</h2>

        <!-- Objeções -->
        <div>
            <label class="block text-xs text-muted mb-2">Objeções &amp; Respostas</label>
            <div id="objections-list" class="space-y-2">
                <?php
                $objArr = $profile['objection_responses'] ?? [];
                if (is_string($objArr)) $objArr = json_decode($objArr, true) ?? [];
                if (empty($objArr)) $objArr = [['objection'=>'','response'=>'']];
                foreach ($objArr as $oi => $obj):
                ?>
                <div class="flex gap-2 objection-row">
                    <div class="flex-1 grid grid-cols-2 gap-2">
                        <input type="text" name="objection_responses[<?= $oi ?>][objection]" value="<?= e($obj['objection'] ?? '') ?>"
                               placeholder="Objeção (ex: 'é muito caro')"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                        <input type="text" name="objection_responses[<?= $oi ?>][response]" value="<?= e($obj['response'] ?? '') ?>"
                               placeholder="Resposta ideal"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    </div>
                    <button type="button" onclick="this.closest('.objection-row').remove()"
                            class="text-muted hover:text-red-400 transition-colors mt-2">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="btn-add-objection"
                    class="mt-2 text-xs text-lime hover:underline flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">add</span> Adicionar objeção
            </button>
        </div>

        <!-- Concorrentes -->
        <div>
            <label class="block text-xs text-muted mb-2">Concorrentes</label>
            <div id="competitors-list" class="space-y-2">
                <?php
                $compArr = $profile['competitors'] ?? [];
                if (is_string($compArr)) $compArr = json_decode($compArr, true) ?? [];
                if (empty($compArr)) $compArr = [['name'=>'','weakness'=>'','how_to_win'=>'']];
                foreach ($compArr as $ki => $comp):
                ?>
                <div class="flex gap-2 competitor-row">
                    <div class="flex-1 grid grid-cols-3 gap-2">
                        <input type="text" name="competitors[<?= $ki ?>][name]" value="<?= e($comp['name'] ?? '') ?>"
                               placeholder="Concorrente"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                        <input type="text" name="competitors[<?= $ki ?>][weakness]" value="<?= e($comp['weakness'] ?? '') ?>"
                               placeholder="Ponto fraco deles"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                        <input type="text" name="competitors[<?= $ki ?>][how_to_win]" value="<?= e($comp['how_to_win'] ?? '') ?>"
                               placeholder="Como ganhar"
                               class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    </div>
                    <button type="button" onclick="this.closest('.competitor-row').remove()"
                            class="text-muted hover:text-red-400 transition-colors mt-2">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="btn-add-competitor"
                    class="mt-2 text-xs text-lime hover:underline flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">add</span> Adicionar concorrente
            </button>
        </div>

        <div>
            <label class="block text-xs text-muted mb-1">Justificativa de Preço</label>
            <textarea name="pricing_justification" rows="2"
                      placeholder="Por que nosso preço vale o investimento? Use dados e comparações."
                      class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($p('pricing_justification')) ?></textarea>
        </div>
    </div>

    <!-- ── CONTEXTO LIVRE ─────────────────────────────────────────────── -->
    <div class="bg-surface border border-stroke rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-semibold text-lime uppercase tracking-widest">Contexto Adicional</h2>
        <p class="text-xs text-muted">Campo livre para informações estratégicas não cobertas pelos campos acima: metodologias, playbooks, rituais de vendas, etc.</p>
        <textarea name="custom_context" rows="6"
                  placeholder="Ex: Nossa metodologia exclusiva de 3 encontros: diagnóstico → proposta → onboarding..."
                  class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none resize-none"><?= e($p('custom_context')) ?></textarea>
    </div>

    <!-- ── CTA ────────────────────────────────────────────────────────── -->
    <div class="flex justify-end">
        <button id="btn-save-profile" type="submit"
                class="flex items-center gap-2 bg-lime text-bg font-semibold px-6 py-3 rounded-xl hover:bg-lime/90 transition-all shadow-glow">
            <span class="material-symbols-outlined text-lg">save</span>
            Salvar e Indexar
        </button>
    </div>
</form>

<!-- ── Dynamic row JS ──────────────────────────────────────────────── -->
<script>
(function () {
    function makeAddRowHandler(listId, tplFn) {
        const list = document.getElementById(listId);
        if (!list) return;
        document.getElementById('btn-add-' + listId.replace('-list', '').replace('s-list',''))
            ?.addEventListener('click', () => {
                const idx = list.querySelectorAll('[data-idx], div').length;
                list.insertAdjacentHTML('beforeend', tplFn(idx));
            });
    }

    // Services
    document.getElementById('btn-add-service')?.addEventListener('click', () => {
        const list = document.getElementById('services-list');
        const idx  = list.querySelectorAll('.service-row').length;
        list.insertAdjacentHTML('beforeend', `
            <div class="flex gap-2 items-start service-row">
                <div class="flex-1 grid grid-cols-3 gap-2">
                    <input type="text" name="services[${idx}][name]" placeholder="Nome do serviço"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="services[${idx}][description]" placeholder="Descrição breve"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="services[${idx}][price_range]" placeholder="R$ 0/mês"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                </div>
                <button type="button" onclick="this.closest('.service-row').remove()"
                        class="text-muted hover:text-red-400 transition-colors mt-2">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>`);
    });

    // Cases
    document.getElementById('btn-add-case')?.addEventListener('click', () => {
        const list = document.getElementById('cases-list');
        const idx  = list.querySelectorAll('.case-row').length;
        list.insertAdjacentHTML('beforeend', `
            <div class="bg-surface2 border border-stroke rounded-lg p-3 space-y-2 case-row">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <input type="text" name="cases[${idx}][client]" placeholder="Cliente"
                           class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="cases[${idx}][result]" placeholder="Resultado"
                           class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="cases[${idx}][niche]" placeholder="Nicho"
                           class="bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <div class="flex gap-2">
                        <input type="text" name="cases[${idx}][timeframe]" placeholder="Em X dias"
                               class="flex-1 bg-surface border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                        <button type="button" onclick="this.closest('.case-row').remove()"
                                class="text-muted hover:text-red-400 transition-colors">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>
                </div>
            </div>`);
    });

    // Objections
    document.getElementById('btn-add-objection')?.addEventListener('click', () => {
        const list = document.getElementById('objections-list');
        const idx  = list.querySelectorAll('.objection-row').length;
        list.insertAdjacentHTML('beforeend', `
            <div class="flex gap-2 objection-row">
                <div class="flex-1 grid grid-cols-2 gap-2">
                    <input type="text" name="objection_responses[${idx}][objection]" placeholder="Objeção"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="objection_responses[${idx}][response]" placeholder="Resposta"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                </div>
                <button type="button" onclick="this.closest('.objection-row').remove()"
                        class="text-muted hover:text-red-400 transition-colors mt-2">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>`);
    });

    // Competitors
    document.getElementById('btn-add-competitor')?.addEventListener('click', () => {
        const list = document.getElementById('competitors-list');
        const idx  = list.querySelectorAll('.competitor-row').length;
        list.insertAdjacentHTML('beforeend', `
            <div class="flex gap-2 competitor-row">
                <div class="flex-1 grid grid-cols-3 gap-2">
                    <input type="text" name="competitors[${idx}][name]" placeholder="Concorrente"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="competitors[${idx}][weakness]" placeholder="Ponto fraco"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                    <input type="text" name="competitors[${idx}][how_to_win]" placeholder="Como ganhar"
                           class="bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text focus:border-lime/50 focus:outline-none">
                </div>
                <button type="button" onclick="this.closest('.competitor-row').remove()"
                        class="text-muted hover:text-red-400 transition-colors mt-2">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>`);
    });
}());
</script>
