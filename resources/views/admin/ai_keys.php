<?php
$pageTitle    = 'Chaves de API (Admin)';
$pageSubtitle = 'Gestão de chaves de autenticação dos provedores de IA';

// Dados do controller: $keys (array mascarado), $envStatus (array provider => bool)
$providers = [
    'gemini' => ['name' => 'Google Gemini', 'icon' => 'google', 'color' => 'lime', 'placeholder' => 'AIza...'],
    'openai' => ['name' => 'OpenAI GPT',   'icon' => 'neurology', 'color' => 'emerald-400', 'placeholder' => 'sk-...'],
    'grok'   => ['name' => 'xAI Grok',     'icon' => 'bolt',      'color' => 'orange-400',  'placeholder' => 'xai-...'],
];

// Indexar chaves por provider para lookup rápido
$keysByProvider = [];
foreach (($keys ?? []) as $k) {
    $keysByProvider[$k['provider']] = $k;
}
?>

<div class="p-6 flex flex-col gap-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black text-text">Chaves de API</h2>
            <p class="text-sm text-subtle mt-0.5">Cadastre e gerencie as credenciais dos provedores de IA. Chaves armazenadas com criptografia AES-256.</p>
        </div>
        <a href="/admin/ai-config" class="flex items-center gap-2 text-xs text-muted bg-surface2 border border-stroke px-3 py-1.5 rounded-pill shadow-soft hover:text-text transition-colors">
            <span class="material-symbols-outlined text-[14px]">arrow_back</span>
            Voltar para Gestão de I.A
        </a>
    </div>

    <!-- Info Banner -->
    <div class="bg-surface2 border border-lime/20 rounded-cardLg p-4 flex items-start gap-3">
        <span class="material-symbols-outlined text-lime text-lg mt-0.5">shield_lock</span>
        <div class="text-xs text-subtle leading-relaxed">
            <span class="text-text font-bold">Armazenamento Seguro:</span>
            Chaves são encriptadas com AES-256-CBC antes de serem salvas no banco de dados.
            Se nenhuma chave for cadastrada aqui, o sistema utiliza as variáveis de ambiente do <code class="text-lime/80">.env</code> como fallback.
        </div>
    </div>

    <!-- Provider Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <?php foreach ($providers as $providerId => $prov):
            $existing = $keysByProvider[$providerId] ?? null;
            $hasEnv = $envStatus[$providerId] ?? false;
            $isActive = $existing && ($existing['is_active'] ?? false);
            $source = $existing ? 'DB (Encriptada)' : ($hasEnv ? '.env (Fallback)' : 'Não configurada');
            $sourceColor = $existing ? 'text-lime' : ($hasEnv ? 'text-orange-400' : 'text-red-400');
        ?>
        <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft flex flex-col gap-5">

            <!-- Provider Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 bg-surface2 rounded-full flex items-center justify-center border border-stroke">
                        <span class="material-symbols-outlined text-<?= $prov['color'] ?> text-lg"><?= $prov['icon'] ?></span>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-text"><?= $prov['name'] ?></h3>
                        <p class="text-[10px] font-bold <?= $sourceColor ?> uppercase tracking-widest"><?= $source ?></p>
                    </div>
                </div>

                <?php if ($existing): ?>
                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-pill text-[10px] font-bold uppercase tracking-wider
                    <?= $isActive ? 'bg-lime/10 text-lime border border-lime/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
                    <span class="size-1.5 rounded-full <?= $isActive ? 'bg-lime' : 'bg-red-400' ?>"></span>
                    <?= $isActive ? 'Ativa' : 'Inativa' ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Save Form -->
            <form method="POST" action="/admin/ai-keys/save" class="flex flex-col gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="provider" value="<?= $providerId ?>">

                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Label (opcional)</label>
                    <input type="text" name="label" value="<?= e($existing['label'] ?? '') ?>" placeholder="Ex: Produção, Testes..."
                           class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">API Key</label>
                    <input type="password" name="api_key" value=""
                           placeholder="<?= $existing ? $existing['masked_key'] : $prov['placeholder'] ?>"
                           class="w-full bg-surface2 border border-stroke rounded-xl px-4 py-2 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all font-mono">
                </div>

                <?php if ($existing && !empty($existing['last_used_at'])): ?>
                <p class="text-[10px] text-subtle">
                    <span class="material-symbols-outlined text-[12px] align-middle">schedule</span>
                    Último uso: <?= (new DateTime($existing['last_used_at']))->format('d/m/Y H:i') ?>
                </p>
                <?php endif; ?>

                <div class="flex gap-2 mt-1">
                    <button type="submit" class="flex-1 py-2.5 bg-lime rounded-pill text-bg text-xs font-black hover:brightness-110 transition-all shadow-glow flex justify-center items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        <?= $existing ? 'Atualizar Chave' : 'Salvar Chave' ?>
                    </button>

                    <button type="button" onclick="testKey('<?= $providerId ?>')"
                            class="px-4 py-2.5 bg-surface2 border border-stroke rounded-pill text-xs font-bold text-text hover:bg-surface3 transition-all flex items-center gap-1.5"
                            id="testBtn_<?= $providerId ?>">
                        <span class="material-symbols-outlined text-[16px]">network_check</span>
                        Testar
                    </button>
                </div>
            </form>

            <!-- Test Result -->
            <div id="testResult_<?= $providerId ?>" class="hidden"></div>

            <!-- Delete -->
            <?php if ($existing): ?>
            <form method="POST" action="/admin/ai-keys/delete" class="border-t border-stroke pt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="key_id" value="<?= e($existing['id']) ?>">
                <button type="submit" onclick="return confirm('Remover chave de <?= $prov['name'] ?>? O sistema usará o .env como fallback.')"
                        class="w-full py-2 text-xs font-bold text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-xl transition-colors flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                    Remover Chave do DB
                </button>
            </form>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>

    <!-- Encryption Info -->
    <div class="bg-surface border border-stroke rounded-cardLg p-6 shadow-soft">
        <h3 class="text-sm font-bold text-text mb-3 flex items-center gap-2">
            <span class="material-symbols-outlined text-lime text-base">security</span>
            Hierarquia de Resolução de Chaves
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-subtle">
            <div class="bg-surface2 border border-stroke rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="size-6 bg-lime/10 rounded-full flex items-center justify-center text-lime text-[11px] font-black">1</span>
                    <span class="font-bold text-text">Chave no DB (Tenant)</span>
                </div>
                <p>Chave específica deste tenant, encriptada com AES-256-CBC.</p>
            </div>
            <div class="bg-surface2 border border-stroke rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="size-6 bg-orange-400/10 rounded-full flex items-center justify-center text-orange-400 text-[11px] font-black">2</span>
                    <span class="font-bold text-text">Chave Global no DB</span>
                </div>
                <p>Chave compartilhada entre tenants (sem tenant_id).</p>
            </div>
            <div class="bg-surface2 border border-stroke rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="size-6 bg-red-400/10 rounded-full flex items-center justify-center text-red-400 text-[11px] font-black">3</span>
                    <span class="font-bold text-text">Variável .env</span>
                </div>
                <p>Fallback final: GEMINI_API_KEY, OPENAI_API_KEY, GROK_API_KEY.</p>
            </div>
        </div>
    </div>

</div>

<script>
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function testKey(provider) {
    const btn = document.getElementById('testBtn_' + provider);
    const resultDiv = document.getElementById('testResult_' + provider);

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Testando...';
    resultDiv.classList.remove('hidden');
    resultDiv.innerHTML = '<p class="text-xs text-muted animate-pulse">Conectando ao provedor...</p>';

    try {
        const resp = await fetch('/admin/ai-keys/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _csrf: getCsrfToken(), provider: provider })
        });
        const data = await resp.json();

        if (data.success) {
            resultDiv.innerHTML = `
                <div class="flex items-center gap-2 p-3 bg-lime/10 border border-lime/20 rounded-xl">
                    <span class="material-symbols-outlined text-lime text-lg">check_circle</span>
                    <div class="text-xs">
                        <p class="font-bold text-lime">Conexão OK</p>
                        <p class="text-subtle">${data.message}</p>
                    </div>
                </div>`;
        } else {
            resultDiv.innerHTML = `
                <div class="flex items-center gap-2 p-3 bg-red-500/10 border border-red-500/20 rounded-xl">
                    <span class="material-symbols-outlined text-red-400 text-lg">error</span>
                    <div class="text-xs">
                        <p class="font-bold text-red-400">Falha na Conexão</p>
                        <p class="text-subtle">${data.message}</p>
                    </div>
                </div>`;
        }
    } catch (err) {
        resultDiv.innerHTML = `
            <div class="flex items-center gap-2 p-3 bg-red-500/10 border border-red-500/20 rounded-xl">
                <span class="material-symbols-outlined text-red-400 text-lg">error</span>
                <p class="text-xs text-red-400">Erro de rede: ${err.message}</p>
            </div>`;
    }

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">network_check</span> Testar';
}
</script>
