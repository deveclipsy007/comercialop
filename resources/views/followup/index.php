<?php 
$pageTitle = 'Follow-up Engine'; 
$pageSubtitle = 'Máquina de Vendas';

// Helper de formatação de datas curtas
function fmtDateShort(string $date): string {
    $d = new DateTime($date);
    return $d->format('d/m \· H:i');
}

// Helper para traduzir pipeline stages
function tStage(string $s): string {
    $map = [
        'new' => 'Novo Lead',
        'contacted' => 'Contatado',
        'qualified' => 'Qualificado',
        'proposal' => 'Proposta',
        'closed_won' => 'Ganho',
        'closed_lost' => 'Perdido'
    ];
    return $map[$s] ?? $s;
}

// Componente Card de Follow-up (PHP para reuso inline)
$renderCard = function($f, $type) {
    if (!$f) return '';
    
    // Cores e ícones baseados no tipo/prioridade
    $border = $type === 'overdue' ? 'border-red-500/30 hover:border-red-500/50' : 
             ($type === 'today' ? 'border-lime/30 hover:border-lime/50' : 'border-white/5 hover:border-white/20');
    
    $bg = $type === 'overdue' ? 'bg-red-500/5' : 
         ($type === 'today' ? 'bg-lime/5' : 'bg-surface2');
         
    $icon = $type === 'overdue' ? '<span class="material-symbols-outlined text-red-500 text-[18px]">warning</span>' : 
           ($type === 'today' ? '<span class="material-symbols-outlined text-lime text-[18px]">bolt</span>' : '<span class="material-symbols-outlined text-muted text-[18px]">event</span>');

    // Human context JSON
    $ctx = is_string($f['lead_context']) ? json_decode($f['lead_context'], true) : [];
    $temp = $ctx['temperature'] ?? 'COLD';
    $tempColor = ['HOT' => 'bg-red-500', 'WARM' => 'bg-amber-500', 'COLD' => 'bg-blue-400'][$temp] ?? 'bg-muted';
    
    $safeJson = e(json_encode([
        'id' => $f['id'],
        'lead_name' => $f['lead_name'],
        'lead_segment' => $f['lead_segment'],
        'title' => $f['title'],
        'description' => $f['description'],
        'pipeline_status' => tStage($f['pipeline_status']),
    ]));

    ob_start();
    ?>
    <div class="relative flex flex-col p-4 rounded-xl <?= $bg ?> border <?= $border ?> transition-all duration-300 group shadow-soft hover:-translate-y-1">
        <div class="flex items-start justify-between mb-3 gap-2">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full <?= $tempColor ?> shadow-glow shrink-0"></span>
                <p class="text-[13px] font-bold text-text truncate group-hover:text-lime transition-colors"><?= e($f['lead_name']) ?></p>
            </div>
            <div class="flex items-center gap-1 shrink-0 text-muted">
                <?= $icon ?>
                <span class="text-[10px] uppercase font-bold tracking-widest"><?= fmtDateShort($f['scheduled_at']) ?></span>
            </div>
        </div>
        
        <p class="text-sm font-bold text-white mb-1 leading-tight"><?= e($f['title']) ?></p>
        <p class="text-xs text-subtle line-clamp-2 mb-4"><?= e($f['description']) ?></p>

        <div class="mt-auto pt-4 border-t border-stroke/50 flex items-center justify-between">
            <div class="bg-surface border border-white/5 px-2 py-1 rounded text-[10px] text-muted font-medium shrink-0">
                <?= e(tStage($f['pipeline_status'])) ?>
            </div>
            <button onclick="openFollowupModal(this.dataset.info)" data-info="<?= $safeJson ?>"
                    class="h-8 px-4 rounded-pill bg-white text-bg text-[11px] font-bold shadow-soft hover:bg-lime transition-colors flex items-center gap-1">
                Executar <span class="material-symbols-outlined text-[14px]">play_arrow</span>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
};
?>

<div class="flex flex-col h-full overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between p-6 md:p-8 flex-shrink-0 animate-fadeInUp">
        <div>
            <h2 class="text-2xl font-black text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-lime text-3xl">rocket_launch</span>
                Follow-up Engine
            </h2>
            <p class="text-sm text-subtle mt-1 font-medium">Acelere negociações com cadências diárias apoiadas por IA.</p>
        </div>
        
        <div class="hidden sm:flex items-center gap-3 h-10 px-4 rounded-pill border border-lime/20 bg-lime/5">
            <span class="size-2 rounded-full bg-lime shadow-glow animate-pulse"></span>
            <span class="text-xs font-bold text-lime tracking-wider uppercase">Operon AI Ativado</span>
        </div>
    </div>

    <!-- Kanban Columns -->
    <div class="flex-1 flex overflow-x-auto overflow-y-hidden px-6 md:px-8 pb-8 gap-6 hide-scrollbar">
        
        <!-- Atrasados (Overdue) -->
        <div class="w-80 min-w-[320px] flex flex-col animate-popIn" style="animation-delay: 50ms;">
            <div class="flex items-center justify-between mb-4 px-2">
                <h3 class="text-xs font-bold text-red-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">warning</span> Atrasados
                </h3>
                <span class="size-6 rounded-full bg-red-500/10 text-red-500 border border-red-500/20 flex items-center justify-center text-[11px] font-bold"><?= count($overdue) ?></span>
            </div>
            <div class="flex-1 overflow-y-auto pr-2 space-y-4 rounded-xl custom-scrollbar">
                <?php if (empty($overdue)): ?>
                    <div class="h-32 border border-dashed border-stroke rounded-xl flex items-center justify-center text-xs text-muted font-medium">
                        Nenhum atraso. Bom trabalho!
                    </div>
                <?php else: ?>
                    <?php foreach ($overdue as $f) echo $renderCard($f, 'overdue'); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hoje (Today) -->
        <div class="w-80 min-w-[320px] flex flex-col animate-popIn" style="animation-delay: 150ms;">
            <div class="flex items-center justify-between mb-4 px-2">
                <h3 class="text-xs font-bold text-lime uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">bolt</span> Hoje
                </h3>
                <span class="size-6 rounded-full bg-lime/10 text-lime border border-lime/20 flex items-center justify-center text-[11px] font-bold"><?= count($today) ?></span>
            </div>
            <div class="flex-1 overflow-y-auto pr-2 space-y-4 rounded-xl custom-scrollbar">
                <?php if (empty($today)): ?>
                    <div class="h-32 border border-dashed border-stroke rounded-xl flex items-center justify-center text-xs text-muted font-medium">
                        Agenda livre hoje.
                    </div>
                <?php else: ?>
                    <?php foreach ($today as $f) echo $renderCard($f, 'today'); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Próximos (Upcoming) -->
        <div class="w-80 min-w-[320px] flex flex-col animate-popIn" style="animation-delay: 250ms;">
            <div class="flex items-center justify-between mb-4 px-2">
                <h3 class="text-xs font-bold text-muted uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">event</span> Próximos
                </h3>
                <span class="size-6 rounded-full bg-surface2 border border-stroke text-muted flex items-center justify-center text-[11px] font-bold"><?= count($upcoming) ?></span>
            </div>
            <div class="flex-1 overflow-y-auto pr-2 space-y-4 rounded-xl custom-scrollbar">
                <?php if (empty($upcoming)): ?>
                    <div class="h-32 border border-dashed border-stroke rounded-xl flex items-center justify-center text-xs text-muted font-medium">
                        Nada no radar ainda.
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming as $f) echo $renderCard($f, 'upcoming'); ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Modal de Execução de Follow-up -->
<div id="execute-followup-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-bg/90 backdrop-blur-md" onclick="closeExecuteModal()"></div>
    <div class="relative bg-surface border border-stroke rounded-cardLg p-6 w-full max-w-2xl shadow-2xl animate-popIn flex flex-col max-h-[90vh]">
        
        <!-- Header Mode -->
        <div class="flex items-center justify-between mb-6 shrink-0">
            <div>
                <h3 class="text-xl font-bold text-text flex items-center gap-2">
                    Executar Follow-up
                </h3>
                <p id="modal-lead-name" class="text-sm text-lime font-medium mt-1">Lead Name</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="h-8 px-3 rounded-md bg-surface2 border border-stroke text-xs font-bold text-white flex items-center">
                    IA Assist <span class="size-2 rounded-full bg-lime ml-2 animate-pulse shadow-glow"></span>
                </div>
                <button onclick="closeExecuteModal()" class="size-8 flex items-center justify-center rounded-full bg-surface2 border border-stroke text-muted hover:text-text transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-6">
            
            <!-- Objective Box -->
            <div class="bg-surface2 border border-stroke rounded-xl p-5">
                <h4 class="text-[10px] font-bold text-muted tracking-widest uppercase mb-2">Objetivo da Tarefa</h4>
                <p id="modal-task-title" class="text-sm font-bold text-white mb-1">Título</p>
                <p id="modal-task-desc" class="text-xs text-subtle leading-relaxed">Desc...</p>
            </div>

            <!-- Geração IA -->
            <div>
                <h4 class="text-[10px] font-bold text-muted tracking-widest uppercase mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">smart_toy</span> Mensagem Sugerida
                </h4>
                
                <div id="ai-loading" class="h-32 bg-surface2 border border-stroke border-dashed rounded-xl flex flex-col items-center justify-center">
                    <div class="ai-spinner border-lime mb-3"></div>
                    <p class="text-xs text-lime font-medium">A IA está analisando o contexto e gerando a mensagem perfeita...</p>
                </div>

                <div id="ai-result" class="hidden">
                    <div class="relative group">
                        <textarea id="ai-message-box" readonly class="w-full h-48 bg-surface border border-white/5 rounded-xl p-5 text-[13px] text-text leading-relaxed font-medium focus:outline-none focus:border-lime/30 resize-none"></textarea>
                        <button onclick="copyAiMessage()" class="absolute top-3 right-3 h-8 px-3 rounded-md bg-surface2 border border-stroke text-[11px] font-bold text-white hover:bg-white hover:text-bg transition-all flex items-center gap-1.5 shadow-soft">
                            <span class="material-symbols-outlined text-[14px]">content_copy</span> Copiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="mt-6 pt-5 border-t border-stroke flex items-center justify-between shrink-0">
            <button onclick="closeExecuteModal()" class="px-5 py-2.5 rounded-pill border border-stroke text-muted hover:text-white text-sm font-bold transition-all">
                Cancelar
            </button>
            <form method="POST" action="" id="complete-followup-form">
                <?= csrf_field() ?>
                <button type="submit" class="px-6 py-2.5 rounded-pill bg-lime text-bg text-sm font-bold shadow-glow hover:brightness-110 flex items-center gap-2 transition-all group">
                    Marcar como Feito <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">check_circle</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let currentFollowupId = null;

function openFollowupModal(dataStr) {
    const data = JSON.parse(dataStr);
    currentFollowupId = data.id;
    
    // Set data
    document.getElementById('modal-lead-name').innerText = data.lead_name + ' — ' + data.lead_segment;
    document.getElementById('modal-task-title').innerText = data.title;
    document.getElementById('modal-task-desc').innerText = data.description || 'Sem detalhes adicionais.';
    
    // Set form action
    document.getElementById('complete-followup-form').action = `/follow-up/${data.id}/complete`;
    
    // Reset UI
    document.getElementById('ai-loading').classList.remove('hidden');
    document.getElementById('ai-result').classList.add('hidden');
    document.getElementById('ai-message-box').value = '';
    
    // Open
    document.getElementById('execute-followup-modal').classList.remove('hidden');
    document.getElementById('execute-followup-modal').classList.add('flex');
    
    // Trigger AI Generation
    generateMessage(data.id);
}

function closeExecuteModal() {
    document.getElementById('execute-followup-modal').classList.add('hidden');
    document.getElementById('execute-followup-modal').classList.remove('flex');
}

async function generateMessage(id) {
    try {
        const res = await operonFetch('/follow-up/format-message', {
            method: 'POST',
            body: JSON.stringify({ followup_id: id, _csrf: getCsrfToken() })
        });
        
        document.getElementById('ai-loading').classList.add('hidden');
        document.getElementById('ai-result').classList.remove('hidden');
        
        if (res.error) {
            document.getElementById('ai-message-box').value = 'Erro: ' + res.error;
            return;
        }
        
        document.getElementById('ai-message-box').value = res.message;
        
    } catch (e) {
        document.getElementById('ai-loading').classList.add('hidden');
        document.getElementById('ai-result').classList.remove('hidden');
        document.getElementById('ai-message-box').value = 'Falha ao contatar a IA central.';
    }
}

function copyAiMessage() {
    const box = document.getElementById('ai-message-box');
    box.select();
    box.setSelectionRange(0, 99999); 
    document.execCommand("copy");
    
    // Temp feedback UI
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = `<span class="material-symbols-outlined text-[14px]">check</span> Copiado!`;
    btn.classList.add('bg-lime', 'text-bg', 'border-lime');
    btn.classList.remove('bg-surface2', 'text-white', 'border-stroke');
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.classList.remove('bg-lime', 'text-bg', 'border-lime');
        btn.classList.add('bg-surface2', 'text-white', 'border-stroke');
    }, 2000);
}
</script>
