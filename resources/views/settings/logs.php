<?php
$allLogs = [];
foreach ($logs ?? [] as $log) {
    $allLogs[] = [
        'date' => $log['created_at'],
        'event' => $log['type'],
        'user' => $log['user_name'] ?? 'Sistema',
        'details' => $log['title'] . (!empty($log['content']) ? ' - ' . substr($log['content'], 0, 50) . '...' : ''),
        'ip' => '--'
    ];
}
foreach ($tokenLogs ?? [] as $tlog) {
    $allLogs[] = [
        'date' => $tlog['created_at'],
        'event' => 'token.consume',
        'user' => 'Sistema IA',
        'details' => $tlog['operation'] . ' (' . ($tlog['tokens_used'] ?? $tlog['tokens_consumed'] ?? 0) . ' tk)',
        'ip' => '--'
    ];
}
usort($allLogs, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
?>
<div class="max-w-6xl mx-auto p-6 md:p-8 space-y-8">
    <div class="mb-2">
        <h1 class="text-2xl font-bold text-text">Logs de Auditoria</h1>
        <p class="text-sm text-muted mt-1">Histórico completo de eventos e operações críticas.</p>
    </div>

    <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 mb-8">
            <div class="flex items-center gap-4">
                <div class="size-12 bg-surface2 rounded-full flex items-center justify-center border border-stroke flex-shrink-0">
                    <span class="material-symbols-outlined text-muted text-[24px]">history</span>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text leading-tight">Registros do Sistema</h3>
                    <p class="text-sm text-subtle mt-1">Navegue pelas rotinas de sistema e ações de usuários</p>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <select class="h-10 bg-surface2 border border-stroke rounded-pill px-4 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer pr-8 font-medium">
                    <option value="all">Tipos: Todos</option>
                    <option value="auth">Autenticação</option>
                    <option value="lead">Leads</option>
                    <option value="ai">IA & Tokens</option>
                </select>
                <button class="h-10 px-5 bg-surface hover:bg-surface3 border border-stroke text-text text-sm font-bold rounded-pill transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">download</span> Exportar
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-card border border-stroke">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-surface2">
                    <tr class="border-b border-stroke">
                        <th class="px-5 py-3.5 font-bold text-[11px] uppercase tracking-[0.1em] text-muted">Data/Hora</th>
                        <th class="px-5 py-3.5 font-bold text-[11px] uppercase tracking-[0.1em] text-muted">Evento</th>
                        <th class="px-5 py-3.5 font-bold text-[11px] uppercase tracking-[0.1em] text-muted">Usuário</th>
                        <th class="px-5 py-3.5 font-bold text-[11px] uppercase tracking-[0.1em] text-muted w-full">Detalhes</th>
                        <th class="px-5 py-3.5 font-bold text-[11px] uppercase tracking-[0.1em] text-muted text-right">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stroke text-subtle font-medium">
                    <?php if (empty($allLogs)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-muted">
                            <span class="material-symbols-outlined text-4xl mb-2 block opacity-50">search_off</span>
                            Nenhum registro de auditoria encontrado.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($allLogs as $l): ?>
                    <tr class="hover:bg-surface2 transition-colors group">
                        <td class="px-5 py-4 text-xs font-mono text-muted group-hover:text-text transition-colors"><?= date('d/m/Y H:i', strtotime($l['date'])) ?></td>
                        <td class="px-5 py-4 font-bold <?= str_contains($l['event'], 'delete') ? 'text-red-500' : (str_contains($l['event'], 'token') ? 'text-lime' : 'text-text') ?>"><?= e($l['event']) ?></td>
                        <td class="px-5 py-4 text-text"><?= e($l['user']) ?></td>
                        <td class="px-5 py-4 truncate max-w-xs xl:max-w-xl group-hover:text-text transition-colors" title="<?= e($l['details']) ?>"><?= e($l['details']) ?></td>
                        <td class="px-5 py-4 text-right text-xs font-mono text-stroke group-hover:text-muted transition-colors"><?= e($l['ip']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($allLogs)): ?>
        <div class="mt-8 flex justify-center">
            <button class="h-10 px-6 border border-stroke bg-surface hover:bg-surface3 text-text text-sm font-bold rounded-pill transition-colors">
                Carregar registros antigos
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
