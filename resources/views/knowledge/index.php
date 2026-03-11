<?php
/**
 * Knowledge Base — Painel de gestão do perfil RAG da empresa.
 * Inclui: formulário de perfil, status de indexação, browser de chunks e audit trail.
 *
 * @var array|null $profile
 * @var array      $documents
 * @var int        $chunks
 * @var array      $docsByType
 * @var array      $traces
 * @var int        $traceCount
 * @var bool       $hasIndex
 * @var array      $flash
 */
$pageTitle = 'Knowledge Base';

$statusMap = [
    'pending'    => ['label' => 'Aguardando', 'color' => 'text-muted',   'dot' => 'bg-muted'],
    'processing' => ['label' => 'Indexando',  'color' => 'text-yellow-400', 'dot' => 'bg-yellow-400 animate-pulse'],
    'indexed'    => ['label' => 'Indexado',   'color' => 'text-mint',    'dot' => 'bg-mint'],
    'error'      => ['label' => 'Erro',       'color' => 'text-red-400', 'dot' => 'bg-red-400'],
    'no_profile' => ['label' => 'Sem perfil', 'color' => 'text-muted',   'dot' => 'bg-muted'],
];

$currentStatus = $profile ? ($profile['indexing_status'] ?? 'pending') : 'no_profile';
$statusInfo    = $statusMap[$currentStatus] ?? $statusMap['pending'];
?>

<div class="space-y-8">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-text">Knowledge Base</h1>
            <p class="text-muted text-sm mt-0.5">Contexto da empresa injetado automaticamente em todas as análises de IA</p>
        </div>

        <!-- Status badge -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 bg-surface2 border border-stroke rounded-lg px-4 py-2">
                <span class="w-2 h-2 rounded-full <?= $statusInfo['dot'] ?>"></span>
                <span class="text-sm <?= $statusInfo['color'] ?> font-medium"><?= $statusInfo['label'] ?></span>
                <?php if ($profile): ?>
                    <span class="text-subtle text-xs">· <?= (int)($profile['chunks_count'] ?? 0) ?> chunks</span>
                <?php endif; ?>
            </div>

            <?php if ($profile): ?>
            <button id="btn-reindex"
                    class="flex items-center gap-2 bg-surface2 border border-stroke text-muted hover:text-text hover:border-lime/40 rounded-lg px-4 py-2 text-sm transition-all">
                <span class="material-symbols-outlined text-base">refresh</span>
                Re-indexar
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Flash messages ─────────────────────────────────────────────── -->
    <?php if (!empty($flash['success'])): ?>
    <div class="bg-mint/10 border border-mint/30 rounded-lg px-4 py-3 text-mint text-sm">
        <?= e($flash['success']) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
    <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 text-red-400 text-sm">
        <?= e($flash['error']) ?>
    </div>
    <?php endif; ?>

    <!-- ── Grid principal ─────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- Coluna: Formulário de perfil (ocupa 2/3) -->
        <div class="xl:col-span-2 space-y-6">
            <?php include __DIR__ . '/partials/profile_form.php'; ?>
        </div>

        <!-- Coluna: Status + Chunk Browser + Audit (ocupa 1/3) -->
        <div class="space-y-6">
            <?php include __DIR__ . '/partials/index_status.php'; ?>
            <?php include __DIR__ . '/partials/chunk_browser.php'; ?>
        </div>
    </div>

</div>

<!-- ── Scripts ──────────────────────────────────────────────────────── -->
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── Re-indexar ───────────────────────────────────────────────────────
    const btnReindex = document.getElementById('btn-reindex');
    if (btnReindex) {
        btnReindex.addEventListener('click', async () => {
            btnReindex.disabled = true;
            btnReindex.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">refresh</span> Indexando…';

            const fd = new FormData();
            fd.append('_csrf', csrf);

            try {
                const res  = await fetch('/knowledge/reindex', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast(`Indexação concluída: ${data.chunks_indexed} chunks`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.error ?? 'Erro na indexação', 'error');
                    btnReindex.disabled = false;
                    btnReindex.innerHTML = '<span class="material-symbols-outlined text-base">refresh</span> Re-indexar';
                }
            } catch (e) {
                showToast('Erro de rede', 'error');
                btnReindex.disabled = false;
                btnReindex.innerHTML = '<span class="material-symbols-outlined text-base">refresh</span> Re-indexar';
            }
        });
    }

    // ── Salvar perfil (AJAX) ─────────────────────────────────────────────
    const form   = document.getElementById('knowledge-profile-form');
    const btnSave = document.getElementById('btn-save-profile');

    if (form && btnSave) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            btnSave.disabled = true;
            btnSave.textContent = 'Salvando e indexando…';

            try {
                const fd  = new FormData(form);
                const res = await fetch('/knowledge/profile', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    showToast(`Salvo! ${data.chunks_indexed} chunks indexados.`, 'success');
                    // Recarrega para refletir novo status
                    setTimeout(() => location.reload(), 1800);
                } else {
                    showToast(data.error ?? 'Erro ao salvar', 'error');
                }
            } catch (err) {
                showToast('Erro de rede', 'error');
            } finally {
                btnSave.disabled = false;
                btnSave.textContent = 'Salvar e Indexar';
            }
        });
    }

    // ── Polling de status enquanto processing ────────────────────────────
    <?php if ($currentStatus === 'processing'): ?>
    const pollStatus = async () => {
        try {
            const res  = await fetch('/knowledge/status');
            const data = await res.json();
            if (data.success && data.status !== 'processing') {
                location.reload();
            } else {
                setTimeout(pollStatus, 3000);
            }
        } catch { setTimeout(pollStatus, 5000); }
    };
    setTimeout(pollStatus, 3000);
    <?php endif; ?>

    // ── Toast helper ─────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = `fixed top-6 right-6 z-[9999] px-5 py-3 rounded-lg text-sm font-medium shadow-lg transition-all
            ${type === 'success' ? 'bg-mint/20 border border-mint/40 text-mint' : 'bg-red-500/20 border border-red-500/40 text-red-400'}`;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    // ── Delete document ───────────────────────────────────────────────────
    document.querySelectorAll('[data-delete-doc]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const docId = btn.dataset.deleteDoc;
            if (!confirm('Remover este documento do índice?')) return;

            const fd = new FormData();
            fd.append('_csrf', csrf);

            try {
                const res  = await fetch(`/knowledge/document/${docId}/delete`, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    btn.closest('[data-doc-row]')?.remove();
                    showToast('Documento removido.', 'success');
                } else {
                    showToast(data.error ?? 'Erro ao remover', 'error');
                }
            } catch { showToast('Erro de rede', 'error'); }
        });
    });
}());
</script>
