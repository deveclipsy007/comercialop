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
