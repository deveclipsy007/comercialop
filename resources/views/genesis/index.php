<?php $pageTitle = 'Genesis'; $pageSubtitle = 'Importação de Leads'; ?>

<div class="p-6 flex flex-col gap-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">Genesis Protocol</h2>
            <p class="text-sm text-slate-400 mt-0.5">Importe leads em massa via CSV para o Vault</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- Upload Card (wider) -->
        <div class="lg:col-span-3 bg-brand-surface border border-white/8 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-lime text-lg">upload_file</span>
                Upload CSV
            </h3>
            <form method="POST" action="/genesis" enctype="multipart/form-data" id="genesisForm" class="flex flex-col gap-4">
                <?= csrf_field() ?>
                <label id="dropZone" class="flex flex-col items-center justify-center border-2 border-dashed border-white/15 rounded-2xl p-10 cursor-pointer hover:border-operon-energy/40 hover:bg-operon-energy/3 transition-all group relative">
                    <div id="dropDefault">
                        <div class="flex flex-col items-center">
                            <span class="material-symbols-outlined text-5xl text-slate-600 group-hover:text-operon-energy transition-colors mb-3">upload_file</span>
                            <span class="text-sm font-bold text-slate-400 group-hover:text-white transition-colors">Clique ou arraste o CSV aqui</span>
                            <span class="text-xs text-slate-600 mt-1">Máximo 5MB · CSV, TXT ou TSV · UTF-8 ou Latin-1</span>
                        </div>
                    </div>
                    <div id="dropPreview" class="hidden w-full">
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
                        <div id="csvPreviewTable" class="mt-3 overflow-x-auto max-h-40 overflow-y-auto rounded-xl border border-white/5"></div>
                    </div>
                    <input type="file" name="csv" accept=".csv,.txt,.tsv" class="hidden" id="csvInput" required>
                </label>

                <div id="columnFeedback" class="hidden bg-surface3 border border-white/5 rounded-xl p-4">
                    <p class="text-xs font-bold text-muted uppercase mb-2">Colunas detectadas</p>
                    <div id="columnTags" class="flex flex-wrap gap-2"></div>
                </div>

                <button type="submit" id="importBtn" class="w-full py-3.5 bg-primary rounded-xl text-bg text-sm font-bold hover:brightness-110 transition-all shadow-md shadow-primary/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2" disabled>
                    <span class="material-symbols-outlined text-lg">login</span>
                    Importar para o Vault
                </button>
            </form>
        </div>

        <!-- Format Guide (slimmer) -->
        <div class="lg:col-span-2 bg-brand-surface border border-white/8 rounded-2xl p-6 space-y-5">
            <div>
                <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-lime text-lg">info</span>
                    Como funciona
                </h3>
                <div class="space-y-3 text-xs text-slate-400">
                    <div class="flex gap-3 items-start">
                        <span class="flex-shrink-0 size-6 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center text-lime text-[10px] font-black">1</span>
                        <p>Prepare sua planilha com os dados dos leads. Pode ser Excel exportado como CSV ou um arquivo de texto separado por vírgulas.</p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span class="flex-shrink-0 size-6 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center text-lime text-[10px] font-black">2</span>
                        <p>O sistema <b class="text-white">reconhece automaticamente</b> as colunas mesmo com nomes diferentes (ex: "Empresa", "Razão Social", "Company" → tudo vira "Nome").</p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span class="flex-shrink-0 size-6 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center text-lime text-[10px] font-black">3</span>
                        <p>Faça o upload e o sistema importa os leads direto para o Vault, prontos para prospecção.</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/5 pt-4">
                <h4 class="text-xs font-bold text-white mb-2">Exemplo de CSV</h4>
                <div class="bg-black/30 rounded-xl p-3 font-mono text-[11px] text-operon-energy overflow-x-auto leading-relaxed">
                    name,segment,phone,email<br>
                    Clínica Silva,Saúde,11999887766,dr@clinica.com<br>
                    Studio Fit,Fitness,21988776655,contato@fit.com
                </div>
            </div>

            <div class="border-t border-white/5 pt-4">
                <h4 class="text-xs font-bold text-white mb-3">Colunas reconhecidas</h4>
                <div class="space-y-2 text-xs">
                    <?php
                    $cols = [
                        ['field' => 'Nome', 'aliases' => 'name, Nome, Empresa, Razão Social, Company', 'req' => true],
                        ['field' => 'Segmento', 'aliases' => 'segment, Nicho, Setor, Ramo, Categoria', 'req' => false],
                        ['field' => 'Website', 'aliases' => 'website, Site, URL, Link', 'req' => false],
                        ['field' => 'Telefone', 'aliases' => 'phone, Tel, Celular, WhatsApp, Contato', 'req' => false],
                        ['field' => 'Email', 'aliases' => 'email, E-mail, Mail, Correio', 'req' => false],
                        ['field' => 'Endereço', 'aliases' => 'address, Endereco, Rua, Cidade', 'req' => false],
                    ];
                    foreach ($cols as $col): ?>
                    <div class="flex items-start gap-2">
                        <span class="font-bold text-white whitespace-nowrap"><?= $col['field'] ?><?= $col['req'] ? ' <span class="text-red-400">*</span>' : '' ?></span>
                        <span class="text-slate-600">—</span>
                        <span class="text-slate-500 text-[11px]"><?= $col['aliases'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-lime/5 border border-lime/10 rounded-xl p-3 text-xs text-slate-400">
                <p class="font-bold text-lime mb-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">auto_fix_high</span> Auto-correção
                </p>
                <p>O sistema corrige automaticamente: encoding (Latin-1 → UTF-8), delimitadores (vírgula, ponto-e-vírgula, tab) e variações de nomenclatura. Campo "segmento" é opcional — será preenchido como "Não classificado".</p>
            </div>
        </div>
    </div>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const csvInput = document.getElementById('csvInput');
const importBtn = document.getElementById('importBtn');
const dropDefault = document.getElementById('dropDefault');
const dropPreview = document.getElementById('dropPreview');

// Drag & drop visual feedback
['dragenter', 'dragover'].forEach(evt => {
    dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.add('border-operon-energy', 'bg-operon-energy/5');
    });
});
['dragleave', 'drop'].forEach(evt => {
    dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-operon-energy', 'bg-operon-energy/5');
    });
});
dropZone.addEventListener('drop', (e) => {
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        csvInput.files = files;
        handleFileSelect(files[0]);
    }
});

csvInput.addEventListener('change', () => {
    if (csvInput.files.length > 0) handleFileSelect(csvInput.files[0]);
});

function handleFileSelect(file) {
    document.getElementById('fileName').textContent = file.name;
    const sizeKB = (file.size / 1024).toFixed(1);
    document.getElementById('fileInfo').textContent = `${sizeKB} KB`;

    dropDefault.classList.add('hidden');
    dropPreview.classList.remove('hidden');
    importBtn.disabled = false;

    // Preview CSV
    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target.result;
        const lines = text.split(/\r?\n/).filter(l => l.trim());
        if (lines.length === 0) return;

        // Detect delimiter
        const delimiters = {';': 0, ',': 0, '\t': 0};
        for (const d in delimiters) delimiters[d] = (lines[0].match(new RegExp(d === '\t' ? '\\t' : (d === '.' ? '\\.' : d), 'g')) || []).length;
        const delimiter = Object.entries(delimiters).sort((a,b) => b[1]-a[1])[0][0];

        const headers = lines[0].split(delimiter).map(h => h.trim().replace(/^"|"$/g, ''));
        const previewRows = lines.slice(1, 4).map(l => l.split(delimiter).map(c => c.trim().replace(/^"|"$/g, '')));

        // Show column detection feedback
        showColumnFeedback(headers);

        // Build preview table
        let html = '<table class="w-full text-xs"><thead><tr>';
        headers.forEach(h => html += `<th class="px-3 py-2 text-left text-muted font-bold bg-surface2 whitespace-nowrap">${h}</th>`);
        html += '</tr></thead><tbody>';
        previewRows.forEach(row => {
            html += '<tr class="border-t border-white/5">';
            row.forEach(c => html += `<td class="px-3 py-2 text-slate-400 whitespace-nowrap max-w-[200px] truncate">${c || '<span class="text-slate-600">—</span>'}</td>`);
            html += '</tr>';
        });
        html += '</tbody></table>';
        document.getElementById('csvPreviewTable').innerHTML = html;
    };
    reader.readAsText(file);
}

function showColumnFeedback(headers) {
    const knownAliases = {
        'name': ['name','nome','empresa','razao social','company','company name','nome da empresa','nome empresa','fantasia','nome fantasia','razao'],
        'segment': ['segment','segmento','nicho','setor','ramo','area','industry','categoria','tipo','atividade'],
        'website': ['website','site','url','pagina','web','link'],
        'phone': ['phone','telefone','tel','celular','fone','whatsapp','contato','numero'],
        'email': ['email','e-mail','e mail','correio','mail'],
        'address': ['address','endereco','endereço','logradouro','rua','cidade','localizacao'],
    };
    const fieldLabels = {name:'Nome',segment:'Segmento',website:'Website',phone:'Telefone',email:'Email',address:'Endereço'};

    const container = document.getElementById('columnTags');
    container.innerHTML = '';
    let hasName = false;

    headers.forEach(h => {
        const normalized = h.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[_\-\.]/g,' ').trim();
        let matched = null;
        for (const [field, aliases] of Object.entries(knownAliases)) {
            if (aliases.includes(normalized)) { matched = field; break; }
        }

        if (matched) {
            if (matched === 'name') hasName = true;
            container.innerHTML += `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-lime/10 border border-lime/20 text-lime text-[11px] font-bold">
                <span class="material-symbols-outlined text-[12px]">check</span> ${h} → ${fieldLabels[matched]}</span>`;
        } else {
            container.innerHTML += `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-muted text-[11px]">
                <span class="material-symbols-outlined text-[12px]">help</span> ${h} (ignorada)</span>`;
        }
    });

    if (!hasName) {
        container.innerHTML += `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-[11px] font-bold">
            <span class="material-symbols-outlined text-[12px]">error</span> Coluna "Nome" não encontrada — importação falhará</span>`;
        importBtn.disabled = true;
    }

    document.getElementById('columnFeedback').classList.remove('hidden');
}

function clearFile(e) {
    e.preventDefault();
    e.stopPropagation();
    csvInput.value = '';
    dropDefault.classList.remove('hidden');
    dropPreview.classList.add('hidden');
    importBtn.disabled = true;
    document.getElementById('columnFeedback').classList.add('hidden');
    document.getElementById('csvPreviewTable').innerHTML = '';
}
</script>
