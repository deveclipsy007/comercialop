<?php $pageTitle = 'Genesis'; $pageSubtitle = 'Importação de Leads'; ?>

<div class="p-6 flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-white">Genesis Protocol</h2>
            <p class="text-sm text-slate-400 mt-0.5">Importe leads em massa via CSV para o Vault</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Upload Card -->
        <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white mb-4">Upload CSV</h3>
            <form method="POST" action="/genesis" enctype="multipart/form-data" class="flex flex-col gap-4">
                <?= csrf_field() ?>
                <label class="flex flex-col items-center justify-center border-2 border-dashed border-white/15 rounded-2xl p-10 cursor-pointer hover:border-operon-energy/40 hover:bg-operon-energy/3 transition-all group">
                    <span class="material-symbols-outlined text-5xl text-slate-600 group-hover:text-operon-energy transition-colors mb-3">upload_file</span>
                    <span class="text-sm font-bold text-slate-400 group-hover:text-white transition-colors">Clique ou arraste o CSV aqui</span>
                    <span class="text-xs text-slate-600 mt-1">Máximo 5MB · Formato CSV UTF-8</span>
                    <input type="file" name="csv" accept=".csv" class="hidden" required>
                </label>
                <button type="submit" class="w-full py-3 bg-primary rounded-xl text-bg text-sm font-bold hover:brightness-110 transition-all shadow-md shadow-primary/20">
                    Importar para o Vault
                </button>
            </form>
        </div>

        <!-- Format Guide -->
        <div class="bg-brand-surface border border-white/8 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white mb-4">Formato Esperado</h3>
            <p class="text-xs text-slate-400 mb-3">O CSV deve ter cabeçalho na primeira linha. Colunas suportadas:</p>
            <div class="bg-black/30 rounded-xl p-4 font-mono text-xs text-operon-energy overflow-x-auto mb-4">
                name,segment,website,phone,email,address<br>
                Clínica Dr. Silva,Saúde,https://...,11999887766,dr@clinica.com,Rua Augusta 100 SP
            </div>
            <div class="flex flex-col gap-2 text-xs">
                <?php
                $cols = [
                    ['name' => 'name / Nome', 'req' => true, 'desc' => 'Nome da empresa'],
                    ['name' => 'segment / Segmento', 'req' => true, 'desc' => 'Nicho/setor'],
                    ['name' => 'website / Website', 'req' => false, 'desc' => 'URL do site'],
                    ['name' => 'phone / Telefone', 'req' => false, 'desc' => 'Número de contato'],
                    ['name' => 'email / Email', 'req' => false, 'desc' => 'E-mail de contato'],
                    ['name' => 'address / Endereço', 'req' => false, 'desc' => 'Para o mapa Atlas'],
                ];
                foreach ($cols as $col): ?>
                <div class="flex items-center gap-2">
                    <code class="text-operon-energy"><?= $col['name'] ?></code>
                    <?= $col['req'] ? '<span class="text-red-400 font-bold">*</span>' : '' ?>
                    <span class="text-slate-500">— <?= $col['desc'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
