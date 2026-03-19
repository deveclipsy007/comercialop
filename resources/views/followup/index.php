<?php
$pageTitle = 'Follow-up Engine';
$pageSubtitle = 'Cadencias de vendas';

$leads = $leads ?? [];
$leadsWithFollowups = $leadsWithFollowups ?? [];
$stats = $stats ?? ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'completed_30d' => 0];
$currentFilter = $currentFilter ?? 'pending';
$currentLeadFilter = $currentLeadFilter ?? '';
$overdue = $overdue ?? [];
$today = $today ?? [];
$upcoming = $upcoming ?? [];
$completed = $completed ?? [];

function fmtDateFollowup(string $date): string {
    $d = new DateTime($date);
    return $d->format('d/m · H:i');
}

function fmtDateRelative(string $date): string {
    $now = new DateTime();
    $d = new DateTime($date);
    $diff = $now->diff($d);

    if ($d->format('Y-m-d') === $now->format('Y-m-d')) return 'Hoje';
    if ($d->format('Y-m-d') === $now->modify('+1 day')->format('Y-m-d')) return 'Amanhã';

    $now = new DateTime();
    if ($d < $now) {
        return 'Há ' . $diff->days . ' dia' . ($diff->days > 1 ? 's' : '');
    }
    return 'Em ' . $diff->days . ' dia' . ($diff->days > 1 ? 's' : '');
}

function translateStage(string $s): string {
    return ['new'=>'Novo','contacted'=>'Contatado','qualified'=>'Qualificado','proposal'=>'Proposta','closed_won'=>'Ganho','closed_lost'=>'Perdido'][$s] ?? $s;
}
?>

<div class="flex h-[calc(100vh-64px)] overflow-hidden">

    <!-- ═══ SIDEBAR ═══ -->
    <aside class="w-72 min-w-[288px] border-r border-stroke bg-brand-surface flex flex-col overflow-hidden">

        <!-- Stats -->
        <div class="p-4 border-b border-stroke space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <div class="bg-red-500/5 border border-red-500/15 rounded-lg p-2.5 text-center">
                    <p class="text-lg font-black text-red-400"><?= $stats['overdue'] ?></p>
                    <p class="text-[9px] font-bold text-red-400/70 uppercase tracking-wider">Atrasados</p>
                </div>
                <div class="bg-lime/5 border border-lime/15 rounded-lg p-2.5 text-center">
                    <p class="text-lg font-black text-lime"><?= $stats['today'] ?></p>
                    <p class="text-[9px] font-bold text-lime/70 uppercase tracking-wider">Hoje</p>
                </div>
                <div class="bg-surface2 border border-stroke rounded-lg p-2.5 text-center">
                    <p class="text-lg font-black text-muted"><?= $stats['upcoming'] ?></p>
                    <p class="text-[9px] font-bold text-muted/70 uppercase tracking-wider">Proximos</p>
                </div>
                <div class="bg-surface2 border border-stroke rounded-lg p-2.5 text-center">
                    <p class="text-lg font-black text-muted"><?= $stats['completed_30d'] ?></p>
                    <p class="text-[9px] font-bold text-muted/70 uppercase tracking-wider">Feitos 30d</p>
                </div>
            </div>
        </div>

        <!-- Create Button -->
        <div class="p-4 border-b border-stroke">
            <button onclick="openCreateModal()" class="w-full py-2.5 bg-lime text-bg text-xs font-bold rounded-xl hover:brightness-110 transition-all flex items-center justify-center gap-2 shadow-glow">
                <span class="material-symbols-outlined text-[16px]">add</span> Novo Follow-up
            </button>
        </div>

        <!-- Filters -->
        <div class="p-4 border-b border-stroke space-y-1.5">
            <p class="text-[9px] font-bold text-muted uppercase tracking-widest mb-2">Filtros</p>
            <a href="/follow-up?filter=pending" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-colors <?= $currentFilter === 'pending' ? 'bg-lime/10 text-lime font-bold' : 'text-muted hover:text-text hover:bg-surface2' ?>">
                <span class="material-symbols-outlined text-[15px]">schedule</span> Pendentes
            </a>
            <a href="/follow-up?filter=completed" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-colors <?= $currentFilter === 'completed' ? 'bg-lime/10 text-lime font-bold' : 'text-muted hover:text-text hover:bg-surface2' ?>">
                <span class="material-symbols-outlined text-[15px]">check_circle</span> Concluidos
            </a>
            <a href="/follow-up?filter=all" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-colors <?= $currentFilter === 'all' ? 'bg-lime/10 text-lime font-bold' : 'text-muted hover:text-text hover:bg-surface2' ?>">
                <span class="material-symbols-outlined text-[15px]">list</span> Todos
            </a>
        </div>

        <!-- Leads with Followups -->
        <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
            <p class="text-[9px] font-bold text-muted uppercase tracking-widest mb-2">Por Lead</p>
            <?php if (empty($leadsWithFollowups)): ?>
                <p class="text-xs text-subtle px-3 py-2">Nenhum lead com follow-up ativo.</p>
            <?php else: ?>
                <div class="space-y-1">
                    <a href="/follow-up?filter=<?= $currentFilter ?>" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition-colors <?= !$currentLeadFilter ? 'bg-surface2 text-text font-bold' : 'text-muted hover:text-text hover:bg-surface2' ?>">
                        <span>Todos os leads</span>
                    </a>
                    <?php foreach ($leadsWithFollowups as $lf): ?>
                    <a href="/follow-up?filter=<?= $currentFilter ?>&lead_id=<?= $lf['id'] ?>"
                       class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition-colors <?= $currentLeadFilter === $lf['id'] ? 'bg-surface2 text-text font-bold' : 'text-muted hover:text-text hover:bg-surface2' ?>">
                        <span class="truncate"><?= htmlspecialchars($lf['name']) ?></span>
                        <span class="flex-shrink-0 size-5 rounded-full bg-lime/10 text-lime text-[10px] font-bold flex items-center justify-center"><?= $lf['followup_count'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ═══ MAIN CONTENT ═══ -->
    <main class="flex-1 flex flex-col overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between p-6 flex-shrink-0 border-b border-stroke">
            <div>
                <h2 class="text-xl font-black text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-lime text-2xl">rocket_launch</span>
                    Follow-up Engine
                </h2>
                <p class="text-xs text-subtle mt-0.5">Cadencias de vendas semi-automaticas com IA.</p>
            </div>
        </div>

        <?php $flash = \App\Core\Session::getFlash('success'); if ($flash): ?>
            <div class="mx-6 mt-4 bg-lime/10 border border-lime/30 text-lime text-sm px-4 py-2.5 rounded-lg"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>
        <?php $flashErr = \App\Core\Session::getFlash('error'); if ($flashErr): ?>
            <div class="mx-6 mt-4 bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-2.5 rounded-lg"><?= htmlspecialchars($flashErr) ?></div>
        <?php endif; ?>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">

            <?php if ($currentFilter === 'completed'): ?>
                <!-- Completed List -->
                <div class="space-y-2">
                    <?php if (empty($completed)): ?>
                        <div class="text-center py-16 text-muted">
                            <span class="material-symbols-outlined text-4xl mb-2 block">check_circle</span>
                            <p class="text-sm">Nenhum follow-up concluido ainda.</p>
                        </div>
                    <?php else: foreach ($completed as $f): ?>
                        <div class="flex items-center gap-4 p-4 bg-surface2/50 border border-stroke rounded-xl opacity-70">
                            <span class="material-symbols-outlined text-lime text-[20px]">check_circle</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-text truncate"><?= htmlspecialchars($f['title']) ?></p>
                                <p class="text-xs text-muted"><?= htmlspecialchars($f['lead_name']) ?> · Concluido em <?= $f['completed_at'] ? (new DateTime($f['completed_at']))->format('d/m/Y') : '—' ?></p>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

            <?php else: ?>
                <!-- Kanban Columns -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Atrasados -->
                    <div>
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h3 class="text-xs font-bold text-red-400 uppercase tracking-widest flex items-center gap-2">
                                <span class="material-symbols-outlined text-[15px]">warning</span> Atrasados
                            </h3>
                            <span class="size-5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20 flex items-center justify-center text-[10px] font-bold"><?= count($overdue) ?></span>
                        </div>
                        <div class="space-y-3">
                            <?php if (empty($overdue)): ?>
                                <div class="h-24 border border-dashed border-stroke rounded-xl flex items-center justify-center text-xs text-muted">Nenhum atraso.</div>
                            <?php else: foreach ($overdue as $f): ?>
                                <?php renderFollowupCard($f, 'overdue'); ?>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- Hoje -->
                    <div>
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h3 class="text-xs font-bold text-lime uppercase tracking-widest flex items-center gap-2">
                                <span class="material-symbols-outlined text-[15px]">bolt</span> Hoje
                            </h3>
                            <span class="size-5 rounded-full bg-lime/10 text-lime border border-lime/20 flex items-center justify-center text-[10px] font-bold"><?= count($today) ?></span>
                        </div>
                        <div class="space-y-3">
                            <?php if (empty($today)): ?>
                                <div class="h-24 border border-dashed border-stroke rounded-xl flex items-center justify-center text-xs text-muted">Agenda livre hoje.</div>
                            <?php else: foreach ($today as $f): ?>
                                <?php renderFollowupCard($f, 'today'); ?>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- Proximos -->
                    <div>
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h3 class="text-xs font-bold text-muted uppercase tracking-widest flex items-center gap-2">
                                <span class="material-symbols-outlined text-[15px]">event</span> Proximos
                            </h3>
                            <span class="size-5 rounded-full bg-surface2 border border-stroke text-muted flex items-center justify-center text-[10px] font-bold"><?= count($upcoming) ?></span>
                        </div>
                        <div class="space-y-3">
                            <?php if (empty($upcoming)): ?>
                                <div class="h-24 border border-dashed border-stroke rounded-xl flex items-center justify-center text-xs text-muted">Nada agendado.</div>
                            <?php else: foreach ($upcoming as $f): ?>
                                <?php renderFollowupCard($f, 'upcoming'); ?>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php
function renderFollowupCard(array $f, string $type): void {
    $border = match($type) {
        'overdue' => 'border-red-500/25 hover:border-red-500/50',
        'today' => 'border-lime/25 hover:border-lime/50',
        default => 'border-white/5 hover:border-white/20',
    };
    $bg = match($type) { 'overdue' => 'bg-red-500/5', 'today' => 'bg-lime/5', default => 'bg-surface2/50' };
    $icon = match($type) {
        'overdue' => '<span class="material-symbols-outlined text-red-400 text-[14px]">warning</span>',
        'today' => '<span class="material-symbols-outlined text-lime text-[14px]">bolt</span>',
        default => '<span class="material-symbols-outlined text-muted text-[14px]">event</span>',
    };

    $ctx = is_string($f['lead_context'] ?? '') ? json_decode($f['lead_context'] ?? '{}', true) : [];
    $temp = $ctx['temperature'] ?? 'COLD';
    $tempColor = ['HOT'=>'bg-red-500','WARM'=>'bg-amber-500','COLD'=>'bg-blue-400'][$temp] ?? 'bg-muted';

    $safeData = htmlspecialchars(json_encode([
        'id' => $f['id'],
        'lead_name' => $f['lead_name'],
        'lead_segment' => $f['lead_segment'] ?? '',
        'lead_phone' => $f['lead_phone'] ?? '',
        'title' => $f['title'],
        'description' => $f['description'] ?? '',
        'pipeline_status' => translateStage($f['pipeline_status'] ?? 'new'),
        'scheduled_at' => $f['scheduled_at'],
    ]), ENT_QUOTES);
    ?>
    <div class="relative flex flex-col p-4 rounded-xl <?= $bg ?> border <?= $border ?> transition-all group">
        <div class="flex items-start justify-between mb-2 gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="size-2 rounded-full <?= $tempColor ?> shrink-0"></span>
                <p class="text-[12px] font-bold text-text truncate"><?= htmlspecialchars($f['lead_name']) ?></p>
            </div>
            <div class="flex items-center gap-1 shrink-0 text-muted">
                <?= $icon ?>
                <span class="text-[10px] font-bold"><?= fmtDateFollowup($f['scheduled_at']) ?></span>
            </div>
        </div>

        <p class="text-sm font-bold text-white mb-0.5"><?= htmlspecialchars($f['title']) ?></p>
        <?php if (!empty($f['description'])): ?>
            <p class="text-[11px] text-subtle line-clamp-2 mb-3"><?= htmlspecialchars($f['description']) ?></p>
        <?php else: ?>
            <div class="mb-3"></div>
        <?php endif; ?>

        <div class="mt-auto pt-3 border-t border-stroke/50 flex items-center justify-between">
            <span class="text-[10px] text-muted bg-surface border border-white/5 px-2 py-0.5 rounded"><?= htmlspecialchars(translateStage($f['pipeline_status'] ?? 'new')) ?></span>
            <div class="flex items-center gap-1.5">
                <form method="POST" action="/follow-up/<?= $f['id'] ?>/delete" class="inline" onsubmit="return confirm('Remover este follow-up?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="size-7 rounded-lg bg-surface2 border border-stroke text-muted hover:text-red-400 hover:border-red-500/30 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100" title="Remover">
                        <span class="material-symbols-outlined text-[14px]">delete</span>
                    </button>
                </form>
                <button onclick='openExecuteModal(<?= $safeData ?>)'
                        class="h-7 px-3 rounded-lg bg-white text-bg text-[11px] font-bold hover:bg-lime transition-colors flex items-center gap-1">
                    Executar <span class="material-symbols-outlined text-[13px]">play_arrow</span>
                </button>
            </div>
        </div>
    </div>
    <?php
}
?>

<!-- ═══ MODAL: Criar Follow-up ═══ -->
<div id="create-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-bg/90 backdrop-blur-md" onclick="closeCreateModal()"></div>
    <div class="relative bg-surface border border-stroke rounded-2xl p-6 w-full max-w-xl shadow-2xl flex flex-col max-h-[90vh]">

        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-text flex items-center gap-2">
                <span class="material-symbols-outlined text-lime">add_circle</span> Novo Follow-up
            </h3>
            <button onclick="closeCreateModal()" class="size-8 flex items-center justify-center rounded-full bg-surface2 border border-stroke text-muted hover:text-text transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <form method="POST" action="/follow-up/create" id="createForm" class="flex-1 overflow-y-auto custom-scrollbar space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" id="createMode" value="single">
            <input type="hidden" name="steps" id="createSteps" value="[]">

            <!-- Lead Selector -->
            <div>
                <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1.5">Lead *</label>
                <select name="lead_id" id="leadSelect" required class="w-full h-10 bg-surface2 border border-stroke rounded-lg px-3 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer [color-scheme:dark]">
                    <option value="">Selecione um lead...</option>
                    <?php foreach ($leads as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?> — <?= htmlspecialchars($l['segment']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Mode Tabs -->
            <div class="flex border border-stroke rounded-lg overflow-hidden">
                <button type="button" onclick="setCreateMode('single')" id="tabSingle"
                        class="flex-1 py-2 text-xs font-bold transition-colors bg-lime/10 text-lime">
                    Follow-up Unico
                </button>
                <button type="button" onclick="setCreateMode('cadence')" id="tabCadence"
                        class="flex-1 py-2 text-xs font-bold transition-colors text-muted hover:text-text">
                    Cadencia (multi-etapas)
                </button>
            </div>

            <!-- Single Mode -->
            <div id="singlePanel">
                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1.5">Titulo *</label>
                        <input type="text" name="title" placeholder="Ex: Apresentar proposta" class="w-full h-10 bg-surface2 border border-stroke rounded-lg px-3 text-sm text-text placeholder:text-subtle focus:outline-none focus:border-lime/50">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1.5">Descricao / Objetivo</label>
                        <textarea name="description" rows="2" placeholder="O que fazer neste contato..." class="w-full bg-surface2 border border-stroke rounded-lg px-3 py-2 text-sm text-text placeholder:text-subtle focus:outline-none focus:border-lime/50 resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1.5">Data *</label>
                        <input type="date" name="scheduled_at" class="w-full h-10 bg-surface2 border border-stroke rounded-lg px-3 text-sm text-text focus:outline-none focus:border-lime/50 [color-scheme:dark]">
                    </div>
                </div>
            </div>

            <!-- Cadence Mode -->
            <div id="cadencePanel" class="hidden">
                <!-- Preset Cadences -->
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Cadencia Pronta</label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="applyCadence([1,3,7])" class="h-8 px-3 bg-surface2 border border-stroke rounded-lg text-[11px] font-bold text-muted hover:text-lime hover:border-lime/30 transition-colors">D+1, D+3, D+7</button>
                        <button type="button" onclick="applyCadence([1,3,5,10])" class="h-8 px-3 bg-surface2 border border-stroke rounded-lg text-[11px] font-bold text-muted hover:text-lime hover:border-lime/30 transition-colors">D+1, D+3, D+5, D+10</button>
                        <button type="button" onclick="applyCadence([2,5,10,15])" class="h-8 px-3 bg-surface2 border border-stroke rounded-lg text-[11px] font-bold text-muted hover:text-lime hover:border-lime/30 transition-colors">D+2, D+5, D+10, D+15</button>
                        <button type="button" onclick="applyCadence([1,4,7,14,21])" class="h-8 px-3 bg-surface2 border border-stroke rounded-lg text-[11px] font-bold text-muted hover:text-lime hover:border-lime/30 transition-colors">Completa (5 etapas)</button>
                    </div>
                </div>

                <!-- Steps List -->
                <div id="cadenceSteps" class="space-y-2 mb-3"></div>

                <button type="button" onclick="addCadenceStep()" class="w-full py-2 border border-dashed border-stroke rounded-lg text-xs text-muted hover:text-lime hover:border-lime/30 transition-colors flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">add</span> Adicionar Etapa
                </button>
            </div>

            <!-- Submit -->
            <div class="pt-4 border-t border-stroke flex justify-end gap-2">
                <button type="button" onclick="closeCreateModal()" class="h-10 px-4 border border-stroke text-muted text-sm rounded-lg hover:text-text transition-colors">Cancelar</button>
                <button type="submit" class="h-10 px-6 bg-lime text-bg text-sm font-bold rounded-lg shadow-glow hover:brightness-110 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">save</span> Criar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL: Executar Follow-up ═══ -->
<div id="execute-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-bg/90 backdrop-blur-md" onclick="closeExecuteModal()"></div>
    <div class="relative bg-surface border border-stroke rounded-2xl p-6 w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">

        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-lg font-bold text-text">Executar Follow-up</h3>
                <p id="exec-lead-name" class="text-sm text-lime font-medium mt-0.5"></p>
            </div>
            <button onclick="closeExecuteModal()" class="size-8 flex items-center justify-center rounded-full bg-surface2 border border-stroke text-muted hover:text-text transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar space-y-5">

            <!-- Objective -->
            <div class="bg-surface2 border border-stroke rounded-xl p-4">
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5">Objetivo</p>
                <p id="exec-title" class="text-sm font-bold text-white"></p>
                <p id="exec-desc" class="text-xs text-subtle mt-1"></p>
                <div class="flex items-center gap-3 mt-3 text-[10px] text-muted">
                    <span id="exec-stage" class="bg-surface border border-white/5 px-2 py-0.5 rounded"></span>
                    <span id="exec-date"></span>
                </div>
            </div>

            <!-- AI Message -->
            <div>
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[14px]">smart_toy</span> Mensagem Sugerida (IA)
                </p>

                <div id="exec-ai-loading" class="h-28 bg-surface2 border border-dashed border-stroke rounded-xl flex flex-col items-center justify-center">
                    <div class="ai-spinner border-lime mb-2"></div>
                    <p class="text-xs text-lime">Gerando mensagem...</p>
                </div>

                <div id="exec-ai-result" class="hidden space-y-3">
                    <textarea id="exec-message-box" class="w-full h-36 bg-surface2 border border-stroke rounded-xl p-4 text-[13px] text-text leading-relaxed focus:outline-none focus:border-lime/30 resize-none"></textarea>

                    <!-- WhatsApp Action -->
                    <div id="exec-whatsapp-panel" class="hidden">
                        <a id="exec-whatsapp-link" href="#" target="_blank" rel="noopener"
                           class="w-full py-3 bg-[#25D366] text-white text-sm font-bold rounded-xl hover:brightness-110 transition-all flex items-center justify-center gap-2 shadow-lg">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.625-1.452A11.93 11.93 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-2.24 0-4.31-.733-5.988-1.97l-.43-.32-2.746.863.837-2.678-.35-.459A9.718 9.718 0 012.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75z"/></svg>
                            Abrir no WhatsApp
                        </a>
                        <p class="text-[10px] text-muted text-center mt-1.5">A mensagem sera preenchida automaticamente. Voce revisa e envia.</p>
                    </div>

                    <div id="exec-no-phone" class="hidden bg-amber-500/10 border border-amber-500/20 rounded-lg p-3 text-xs text-amber-300 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[14px]">warning</span>
                        Lead sem telefone cadastrado. Copie a mensagem e envie por outro canal.
                    </div>

                    <button onclick="copyExecMessage()" class="h-9 px-4 bg-surface2 border border-stroke rounded-lg text-xs font-bold text-white hover:bg-white hover:text-bg transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px]">content_copy</span> Copiar Mensagem
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-5 pt-4 border-t border-stroke flex items-center justify-between">
            <button onclick="closeExecuteModal()" class="h-10 px-4 border border-stroke text-muted text-sm rounded-lg hover:text-text transition-colors">Fechar</button>
            <form method="POST" id="complete-form" action="">
                <?= csrf_field() ?>
                <button type="submit" class="h-10 px-5 bg-lime text-bg text-sm font-bold rounded-lg shadow-glow hover:brightness-110 flex items-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span> Marcar como Feito
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ═══ Create Modal ═══
function openCreateModal() {
    document.getElementById('create-modal').classList.remove('hidden');
    document.getElementById('create-modal').classList.add('flex');
}
function closeCreateModal() {
    document.getElementById('create-modal').classList.add('hidden');
    document.getElementById('create-modal').classList.remove('flex');
}

let currentMode = 'single';
let cadenceSteps = [];

function setCreateMode(mode) {
    currentMode = mode;
    document.getElementById('createMode').value = mode;
    document.getElementById('singlePanel').classList.toggle('hidden', mode !== 'single');
    document.getElementById('cadencePanel').classList.toggle('hidden', mode !== 'cadence');
    document.getElementById('tabSingle').className = mode === 'single'
        ? 'flex-1 py-2 text-xs font-bold transition-colors bg-lime/10 text-lime'
        : 'flex-1 py-2 text-xs font-bold transition-colors text-muted hover:text-text';
    document.getElementById('tabCadence').className = mode === 'cadence'
        ? 'flex-1 py-2 text-xs font-bold transition-colors bg-lime/10 text-lime'
        : 'flex-1 py-2 text-xs font-bold transition-colors text-muted hover:text-text';
}

function applyCadence(days) {
    cadenceSteps = days.map((d, i) => ({
        days: d,
        title: 'Follow-up D+' + d,
        description: ''
    }));
    renderCadenceSteps();
}

function addCadenceStep() {
    const lastDay = cadenceSteps.length > 0 ? cadenceSteps[cadenceSteps.length - 1].days + 3 : 1;
    cadenceSteps.push({ days: lastDay, title: 'Follow-up D+' + lastDay, description: '' });
    renderCadenceSteps();
}

function removeCadenceStep(idx) {
    cadenceSteps.splice(idx, 1);
    renderCadenceSteps();
}

function renderCadenceSteps() {
    const container = document.getElementById('cadenceSteps');
    container.innerHTML = '';

    cadenceSteps.forEach((step, idx) => {
        const today = new Date();
        const target = new Date(today);
        target.setDate(target.getDate() + step.days);
        const dateStr = target.toLocaleDateString('pt-BR', { day:'2-digit', month:'2-digit' });

        const el = document.createElement('div');
        el.className = 'flex items-start gap-3 p-3 bg-surface2/50 border border-stroke rounded-lg';
        el.innerHTML = `
            <div class="flex flex-col items-center pt-1">
                <span class="text-[10px] font-bold text-lime">D+${step.days}</span>
                <span class="text-[9px] text-muted">${dateStr}</span>
            </div>
            <div class="flex-1 space-y-1.5 min-w-0">
                <input type="text" value="${escHtml(step.title)}" placeholder="Titulo da etapa"
                       onchange="cadenceSteps[${idx}].title=this.value; syncSteps()"
                       class="w-full h-8 bg-surface2 border border-stroke rounded-lg px-2.5 text-xs text-text focus:outline-none focus:border-lime/50">
                <div class="flex gap-2">
                    <input type="number" value="${step.days}" min="1" max="90"
                           onchange="cadenceSteps[${idx}].days=parseInt(this.value)||1; renderCadenceSteps()"
                           class="w-16 h-8 bg-surface2 border border-stroke rounded-lg px-2 text-xs text-text text-center focus:outline-none focus:border-lime/50" title="Dias">
                    <input type="text" value="${escHtml(step.description)}" placeholder="Objetivo (opcional)"
                           onchange="cadenceSteps[${idx}].description=this.value; syncSteps()"
                           class="flex-1 h-8 bg-surface2 border border-stroke rounded-lg px-2.5 text-xs text-text placeholder:text-subtle focus:outline-none focus:border-lime/50">
                </div>
            </div>
            <button type="button" onclick="removeCadenceStep(${idx})" class="size-7 rounded-lg bg-surface2 border border-stroke text-muted hover:text-red-400 flex items-center justify-center mt-1">
                <span class="material-symbols-outlined text-[14px]">close</span>
            </button>
        `;
        container.appendChild(el);
    });

    syncSteps();
}

function syncSteps() {
    document.getElementById('createSteps').value = JSON.stringify(cadenceSteps);
}

// Form submit: validate and set steps
document.getElementById('createForm').addEventListener('submit', function(e) {
    if (currentMode === 'cadence') {
        if (cadenceSteps.length === 0) {
            e.preventDefault();
            alert('Adicione pelo menos uma etapa na cadencia.');
            return;
        }
        syncSteps();
    }
});

// ═══ Execute Modal ═══
let currentExecId = null;

function openExecuteModal(data) {
    currentExecId = data.id;

    document.getElementById('exec-lead-name').textContent = data.lead_name + (data.lead_segment ? ' — ' + data.lead_segment : '');
    document.getElementById('exec-title').textContent = data.title;
    document.getElementById('exec-desc').textContent = data.description || 'Sem detalhes adicionais.';
    document.getElementById('exec-stage').textContent = data.pipeline_status;
    document.getElementById('exec-date').textContent = data.scheduled_at ? new Date(data.scheduled_at).toLocaleDateString('pt-BR') : '';

    document.getElementById('complete-form').action = '/follow-up/' + data.id + '/complete';

    // Reset
    document.getElementById('exec-ai-loading').classList.remove('hidden');
    document.getElementById('exec-ai-result').classList.add('hidden');
    document.getElementById('exec-whatsapp-panel').classList.add('hidden');
    document.getElementById('exec-no-phone').classList.add('hidden');

    document.getElementById('execute-modal').classList.remove('hidden');
    document.getElementById('execute-modal').classList.add('flex');

    generateExecMessage(data.id);
}

function closeExecuteModal() {
    document.getElementById('execute-modal').classList.add('hidden');
    document.getElementById('execute-modal').classList.remove('flex');
}

async function generateExecMessage(id) {
    try {
        const res = await operonFetch('/follow-up/format-message', {
            method: 'POST',
            body: JSON.stringify({ followup_id: id, _csrf: getCsrfToken() })
        });

        document.getElementById('exec-ai-loading').classList.add('hidden');
        document.getElementById('exec-ai-result').classList.remove('hidden');

        if (res.error) {
            document.getElementById('exec-message-box').value = 'Erro: ' + res.error;
            return;
        }

        document.getElementById('exec-message-box').value = res.message || '';

        if (res.whatsapp_url) {
            document.getElementById('exec-whatsapp-link').href = res.whatsapp_url;
            document.getElementById('exec-whatsapp-panel').classList.remove('hidden');
        } else {
            document.getElementById('exec-no-phone').classList.remove('hidden');
        }

        // Update WhatsApp link when message is edited
        const msgBox = document.getElementById('exec-message-box');
        msgBox.addEventListener('input', function() {
            if (res.phone) {
                const cleanPhone = res.phone.replace(/\D/g, '');
                const fullPhone = cleanPhone.startsWith('55') ? cleanPhone : '55' + cleanPhone;
                document.getElementById('exec-whatsapp-link').href = 'https://wa.me/' + fullPhone + '?text=' + encodeURIComponent(this.value);
            }
        });

    } catch (e) {
        document.getElementById('exec-ai-loading').classList.add('hidden');
        document.getElementById('exec-ai-result').classList.remove('hidden');
        document.getElementById('exec-message-box').value = 'Falha ao contatar a IA. Tente novamente.';
    }
}

function copyExecMessage() {
    const box = document.getElementById('exec-message-box');
    navigator.clipboard.writeText(box.value).then(() => {
        const btn = event.currentTarget;
        const orig = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">check</span> Copiado!';
        btn.classList.add('bg-lime', 'text-bg');
        setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('bg-lime', 'text-bg'); }, 2000);
    });
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>
