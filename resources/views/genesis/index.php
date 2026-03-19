<?php $pageTitle = 'Genesis'; $pageSubtitle = 'Importação de Leads'; ?>

<div class="p-6 flex flex-col gap-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">Genesis Protocol</h2>
            <p class="text-sm text-slate-400 mt-0.5">Importe leads em massa via CSV para o Vault</p>
        </div>
    </div>

    <!-- ═══════ STEP 1: Upload ═══════ -->
    <div id="stepUpload">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <!-- Upload Card -->
            <div class="lg:col-span-3 bg-brand-surface border border-white/8 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-lime text-lg">upload_file</span>
                    Upload CSV
                </h3>
                <div class="flex flex-col gap-4">
                    <label id="dropZone" class="flex flex-col items-center justify-center border-2 border-dashed border-white/15 rounded-2xl p-10 cursor-pointer hover:border-operon-energy/40 hover:bg-operon-energy/3 transition-all group relative">
                        <div id="dropDefault">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-600 group-hover:text-operon-energy transition-colors mb-3">upload_file</span>
                                <span class="text-sm font-bold text-slate-400 group-hover:text-white transition-colors">Clique ou arraste o CSV aqui</span>
                                <span class="text-xs text-slate-600 mt-1">Maximo 5MB · CSV, TXT ou TSV · Qualquer formatacao</span>
                            </div>
                        </div>
                        <div id="dropFileInfo" class="hidden w-full">
                            <div class="flex items-center gap-3 p-3 bg-surface3 rounded-xl border border-white/5">
                                <span class="material-symbols-outlined text-lime text-2xl">description</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-white truncate" id="fileName"></p>
                                    <p class="text-xs text-muted" id="fileInfo"></p>
                                </div>
                                <button type="button" onclick="clearFile(event)" class="size-8 rounded-full bg-red-500/10 text-red-400 hover:bg-red-500/20 flex items-center justify-center transition-all">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                        </div>
                        <input type="file" accept=".csv,.txt,.tsv" class="hidden" id="csvInput">
                    </label>

                    <button type="button" id="analyzeBtn" onclick="analyzeCSV()" disabled
                            class="w-full py-3.5 bg-primary rounded-xl text-bg text-sm font-bold hover:brightness-110 transition-all shadow-md shadow-primary/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-lg" id="analyzeBtnIcon">search</span>
                        <span id="analyzeBtnText">Analisar Planilha</span>
                    </button>
                </div>
            </div>

            <!-- Guide -->
            <div class="lg:col-span-2 bg-brand-surface border border-white/8 rounded-2xl p-6 space-y-5">
                <div>
                    <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-lg">info</span>
                        Como funciona
                    </h3>
                    <div class="space-y-3 text-xs text-slate-400">
                        <div class="flex gap-3 items-start">
                            <span class="flex-shrink-0 size-6 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center text-lime text-[10px] font-black">1</span>
                            <p>Envie qualquer CSV ou planilha exportada. <b class="text-white">Nao precisa ter cabecalhos perfeitos</b> — o sistema analisa o conteudo de cada coluna.</p>
                        </div>
                        <div class="flex gap-3 items-start">
                            <span class="flex-shrink-0 size-6 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center text-lime text-[10px] font-black">2</span>
                            <p>O motor de deteccao identifica automaticamente: emails, telefones, nomes, sites, cidades, estados, cargos e mais — com base nos <b class="text-white">dados reais</b>.</p>
                        </div>
                        <div class="flex gap-3 items-start">
                            <span class="flex-shrink-0 size-6 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center text-lime text-[10px] font-black">3</span>
                            <p>Revise o mapeamento, ajuste se precisar, e confirme a importacao para o Vault.</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/5 pt-4">
                    <h4 class="text-xs font-bold text-white mb-3">Campos detectados automaticamente</h4>
                    <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                        <?php
                        $fields = [
                            ['icon' => 'person', 'label' => 'Nome / Empresa'],
                            ['icon' => 'mail', 'label' => 'Email'],
                            ['icon' => 'phone', 'label' => 'Telefone'],
                            ['icon' => 'language', 'label' => 'Website'],
                            ['icon' => 'category', 'label' => 'Segmento'],
                            ['icon' => 'location_on', 'label' => 'Endereco'],
                            ['icon' => 'location_city', 'label' => 'Cidade'],
                            ['icon' => 'map', 'label' => 'Estado/UF'],
                            ['icon' => 'badge', 'label' => 'Cargo'],
                            ['icon' => 'notes', 'label' => 'Observacoes'],
                        ];
                        foreach ($fields as $f): ?>
                        <span class="flex items-center gap-1.5 text-muted">
                            <span class="material-symbols-outlined text-[13px] text-lime"><?= $f['icon'] ?></span>
                            <?= $f['label'] ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-lime/5 border border-lime/10 rounded-xl p-3 text-xs text-slate-400">
                    <p class="font-bold text-lime mb-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">psychology</span> Deteccao Inteligente
                    </p>
                    <p>O sistema analisa o <b class="text-white">conteudo real</b> de cada coluna, nao apenas o titulo. Mesmo planilhas sem cabecalho ou com nomes estranhos sao reconhecidas.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════ STEP 2: Preview & Mapping ═══════ -->
    <div id="stepPreview" class="hidden">
        <div class="space-y-6">

            <!-- Stats Banner -->
            <div class="bg-brand-surface border border-white/8 rounded-2xl p-5">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <div class="size-12 rounded-xl bg-lime/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lime text-2xl">table_chart</span>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Analise Concluida</h3>
                            <p class="text-xs text-muted mt-0.5">
                                <span id="statRows" class="text-lime font-bold">0</span> linhas ·
                                <span id="statCols" class="text-lime font-bold">0</span> colunas ·
                                <span id="statMapped" class="text-lime font-bold">0</span> campos detectados
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="backToUpload()" class="h-10 px-4 bg-surface2 border border-white/10 text-muted text-sm font-medium rounded-xl hover:text-white hover:border-white/20 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">arrow_back</span> Voltar
                        </button>
                    </div>
                </div>
                <div id="headerDataWarning" class="hidden mt-3 px-3 py-2 bg-amber-500/10 border border-amber-500/20 rounded-lg text-xs text-amber-300 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">warning</span>
                    <span>Cabecalhos nao detectados. A primeira linha foi tratada como dado. Mapeamento feito 100% por analise de conteudo.</span>
                </div>
            </div>

            <!-- Column Mapping -->
            <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-lg">swap_horiz</span>
                        Mapeamento de Colunas
                    </h3>
                    <p class="text-xs text-muted">Ajuste o campo de cada coluna se necessario</p>
                </div>
                <div id="mappingContainer" class="space-y-2"></div>
            </div>

            <!-- Preview Table -->
            <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-lime text-lg">preview</span>
                    Preview dos Leads (primeiras linhas)
                </h3>
                <div class="overflow-x-auto rounded-xl border border-white/5">
                    <table class="w-full text-xs" id="previewTable">
                        <thead><tr id="previewTableHead"></tr></thead>
                        <tbody id="previewTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Import Button -->
            <form method="POST" action="/genesis" id="importForm">
                <?= csrf_field() ?>
                <input type="hidden" name="file_token" id="importFileToken" value="">
                <input type="hidden" name="mapping" id="importMapping" value="">
                <button type="submit" id="importBtn"
                        class="w-full py-4 bg-primary rounded-xl text-bg text-sm font-black hover:brightness-110 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2 text-base">
                    <span class="material-symbols-outlined text-xl">login</span>
                    Importar <span id="importCount">0</span> Leads para o Vault
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const Genesis = {
    fileToken: null,
    mapping: {},
    headers: [],
    sampleRows: [],
    confidence: {},
    stats: {},
    availableFields: [],
    fieldLabels: {},

    fieldIcons: {
        name:'person', email:'mail', phone:'phone', website:'language',
        segment:'category', address:'location_on', city:'location_city',
        state:'map', position:'badge', notes:'notes',
    },

    init() {
        const dropZone = document.getElementById('dropZone');
        const csvInput = document.getElementById('csvInput');

        ['dragenter','dragover'].forEach(evt => {
            dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('border-operon-energy','bg-operon-energy/5'); });
        });
        ['dragleave','drop'].forEach(evt => {
            dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('border-operon-energy','bg-operon-energy/5'); });
        });
        dropZone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length > 0) {
                csvInput.files = e.dataTransfer.files;
                this.handleFileSelect(e.dataTransfer.files[0]);
            }
        });
        csvInput.addEventListener('change', () => {
            if (csvInput.files.length > 0) this.handleFileSelect(csvInput.files[0]);
        });
    },

    handleFileSelect(file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileInfo').textContent = `${(file.size/1024).toFixed(1)} KB`;
        document.getElementById('dropDefault').classList.add('hidden');
        document.getElementById('dropFileInfo').classList.remove('hidden');
        document.getElementById('analyzeBtn').disabled = false;
    },

    async analyze() {
        const csvInput = document.getElementById('csvInput');
        if (!csvInput.files.length) return;

        const btn = document.getElementById('analyzeBtn');
        const btnText = document.getElementById('analyzeBtnText');
        const btnIcon = document.getElementById('analyzeBtnIcon');
        btn.disabled = true;
        btnText.textContent = 'Analisando...';
        btnIcon.textContent = 'hourglass_empty';

        const formData = new FormData();
        formData.append('csv', csvInput.files[0]);
        formData.append('_csrf', '<?= csrf_token() ?>');

        try {
            const res = await fetch('/genesis/analyze', { method: 'POST', body: formData });
            const data = await res.json();

            if (!data.success) {
                alert(data.error || 'Erro ao analisar o arquivo.');
                btn.disabled = false;
                btnText.textContent = 'Analisar Planilha';
                btnIcon.textContent = 'search';
                return;
            }

            this.fileToken = data.file_token;
            this.mapping = data.mapping;
            this.confidence = data.confidence;
            this.headers = data.headers;
            this.sampleRows = data.sample_rows;
            this.stats = data.stats;
            this.fieldLabels = data.field_labels;
            this.availableFields = data.available_fields;

            this.showPreview();
        } catch (err) {
            alert('Erro de conexao. Tente novamente.');
            console.error(err);
        }

        btn.disabled = false;
        btnText.textContent = 'Analisar Planilha';
        btnIcon.textContent = 'search';
    },

    showPreview() {
        document.getElementById('stepUpload').classList.add('hidden');
        document.getElementById('stepPreview').classList.remove('hidden');

        // Stats
        document.getElementById('statRows').textContent = this.stats.total_rows || 0;
        document.getElementById('statCols').textContent = this.stats.total_columns || 0;
        document.getElementById('statMapped').textContent = this.stats.mapped_columns || 0;

        if (this.stats.headers_are_data) {
            document.getElementById('headerDataWarning').classList.remove('hidden');
        }

        // Mapping UI
        this.renderMapping();
        this.renderPreviewTable();

        document.getElementById('importFileToken').value = this.fileToken;
        this.updateImportMapping();
    },

    renderMapping() {
        const container = document.getElementById('mappingContainer');
        container.innerHTML = '';

        this.headers.forEach((header, idx) => {
            const assignedField = this.mapping[idx] || '';
            const conf = this.confidence[idx] || 0;

            const confColor = conf >= 70 ? 'text-lime' : (conf >= 40 ? 'text-amber-400' : 'text-red-400');
            const confBg = conf >= 70 ? 'bg-lime/10 border-lime/20' : (conf >= 40 ? 'bg-amber-500/10 border-amber-500/20' : 'bg-red-500/10 border-red-500/20');
            const icon = this.fieldIcons[assignedField] || 'help';

            const row = document.createElement('div');
            row.className = 'flex items-center gap-3 p-3 bg-surface2/50 border border-white/5 rounded-xl';
            row.innerHTML = `
                <div class="flex items-center gap-2 min-w-[180px]">
                    <span class="material-symbols-outlined text-[16px] text-muted">table_chart</span>
                    <span class="text-xs font-mono text-slate-400 truncate max-w-[140px]" title="${this.escHtml(header)}">${this.escHtml(header)}</span>
                </div>
                <span class="material-symbols-outlined text-muted text-[16px]">arrow_forward</span>
                <div class="flex-1">
                    <select data-col="${idx}" onchange="Genesis.onMappingChange(${idx}, this.value)"
                            class="w-full h-9 bg-surface2 border border-white/10 rounded-lg px-3 text-xs text-white focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer [color-scheme:dark]">
                        <option value="_skip" class="text-muted">-- Ignorar coluna --</option>
                        ${this.availableFields.map(f =>
                            `<option value="${f}" ${f === assignedField ? 'selected' : ''}>${this.fieldLabels[f] || f}</option>`
                        ).join('')}
                    </select>
                </div>
                ${conf > 0 ? `
                <div class="flex items-center gap-1.5 min-w-[80px] justify-end">
                    <div class="w-12 h-1.5 bg-surface2 rounded-full overflow-hidden">
                        <div class="h-full rounded-full ${conf >= 70 ? 'bg-lime' : (conf >= 40 ? 'bg-amber-400' : 'bg-red-400')}" style="width:${Math.min(conf,100)}%"></div>
                    </div>
                    <span class="text-[10px] font-bold ${confColor}">${conf}%</span>
                </div>` : `<div class="min-w-[80px]"></div>`}
                <div class="flex items-center gap-1 text-xs">
                    ${this.sampleRows.length > 0 ? `<span class="text-slate-600 truncate max-w-[120px]" title="${this.escHtml(this.sampleRows[0]?.[idx] || '')}">${this.escHtml((this.sampleRows[0]?.[idx] || '').substring(0,20))}${(this.sampleRows[0]?.[idx] || '').length > 20 ? '...' : ''}</span>` : ''}
                </div>
            `;
            container.appendChild(row);
        });
    },

    onMappingChange(colIdx, newField) {
        if (newField === '_skip') {
            delete this.mapping[colIdx];
        } else {
            // Remover o campo de outra coluna se já estava atribuído
            for (const [k, v] of Object.entries(this.mapping)) {
                if (v === newField && parseInt(k) !== colIdx) {
                    delete this.mapping[k];
                    // Resetar o select dessa coluna
                    const otherSelect = document.querySelector(`select[data-col="${k}"]`);
                    if (otherSelect) otherSelect.value = '_skip';
                }
            }
            this.mapping[colIdx] = newField;
        }
        this.updateImportMapping();
        this.renderPreviewTable();
    },

    renderPreviewTable() {
        const head = document.getElementById('previewTableHead');
        const body = document.getElementById('previewTableBody');

        // Colunas mapeadas (ordenadas por colIdx)
        const mappedCols = Object.entries(this.mapping)
            .filter(([k,v]) => v && v !== '_skip')
            .sort((a,b) => parseInt(a[0]) - parseInt(b[0]));

        head.innerHTML = mappedCols.map(([idx, field]) =>
            `<th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider bg-surface2 whitespace-nowrap">
                <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-lime text-[13px]">${this.fieldIcons[field] || 'help'}</span>
                    ${this.fieldLabels[field] || field}
                </span>
            </th>`
        ).join('');

        body.innerHTML = this.sampleRows.slice(0, 5).map(row =>
            '<tr class="border-t border-white/5">' +
            mappedCols.map(([idx]) =>
                `<td class="px-3 py-2 text-xs text-slate-400 whitespace-nowrap max-w-[200px] truncate">${this.escHtml(row[parseInt(idx)] || '') || '<span class="text-slate-600">—</span>'}</td>`
            ).join('') +
            '</tr>'
        ).join('');

        // Update import count
        const nameCol = mappedCols.find(([k,v]) => v === 'name');
        const rowCount = nameCol ? this.sampleRows.filter(r => (r[parseInt(nameCol[0])] || '').trim()).length : 0;
        document.getElementById('importCount').textContent = this.stats.total_rows || rowCount;

        // Disable import if no 'name' mapped
        const hasName = mappedCols.some(([k,v]) => v === 'name');
        document.getElementById('importBtn').disabled = !hasName;
        if (!hasName) {
            document.getElementById('importBtn').classList.add('opacity-50','cursor-not-allowed');
        } else {
            document.getElementById('importBtn').classList.remove('opacity-50','cursor-not-allowed');
        }
    },

    updateImportMapping() {
        document.getElementById('importMapping').value = JSON.stringify(this.mapping);
    },

    escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Global functions
function analyzeCSV() { Genesis.analyze(); }
function clearFile(e) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('csvInput').value = '';
    document.getElementById('dropDefault').classList.remove('hidden');
    document.getElementById('dropFileInfo').classList.add('hidden');
    document.getElementById('analyzeBtn').disabled = true;
}
function backToUpload() {
    document.getElementById('stepPreview').classList.add('hidden');
    document.getElementById('stepUpload').classList.remove('hidden');
}

Genesis.init();
</script>
