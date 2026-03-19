<?php
use App\Models\Lead;
/** @var array $lead */
/** @var array $tokenBalance */
$analysis  = $lead['analysis'] ?? [];
$social    = $lead['social_presence'] ?? [];
$ctx       = $lead['human_context'] ?? [];
$cnpj      = $lead['cnpj_data'] ?? [];
$ps        = $lead['pagespeed_data'] ?? [];
$tags      = $lead['tags'] ?? [];
$score     = $lead['priority_score'] ?? 0;
?>

<div class="flex items-center gap-4 mb-8">
    <a href="/vault" class="size-10 flex items-center justify-center rounded-full bg-surface border border-stroke text-muted hover:text-text hover:bg-surface2 transition-all">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
    </a>
    <div class="flex-1 min-w-0">
        <h1 class="text-[28px] font-bold text-text truncate tracking-tight"><?= e($lead['name']) ?></h1>
        <p class="text-sm text-muted mt-0.5"><?= e($lead['segment']) ?></p>
    </div>
    <div class="flex flex-col md:flex-row items-end md:items-center gap-3">
        <span class="px-4 py-1.5 rounded-pill text-xs font-bold border border-stroke bg-surface flex items-center gap-2">
            <span class="size-2 rounded-full inline-block" style="background:<?= $stageDotColors[$lead['pipeline_status']] ?? '#202020' ?>"></span>
            <?= stageLabel($lead['pipeline_status']) ?>
        </span>
        <span class="px-4 py-1.5 rounded-pill text-xs font-bold bg-lime/10 text-lime border border-lime/20 shadow-glow">
            Score <?= $score ?>
        </span>
    </div>
</div>

<!-- Action bar -->
<div class="flex flex-wrap gap-3 mb-8">
    <?php if ($lead['phone']): ?>
    <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['phone']) ?>?text=<?= urlencode('Olá, ' . $lead['name'] . '!') ?>"
       target="_blank"
       class="inline-flex items-center gap-2 h-10 px-5 bg-mint/10 hover:bg-mint/20 border border-mint/20 text-mint rounded-pill text-sm font-bold transition-all">
        <span class="material-symbols-outlined text-[18px]">chat</span> WhatsApp
    </a>
    <?php endif; ?>
    <?php if ($lead['website']): ?>
    <a href="<?= e($lead['website']) ?>" target="_blank"
       class="inline-flex items-center gap-2 h-10 px-5 bg-surface border border-stroke hover:bg-surface2 text-text rounded-pill text-sm font-medium transition-all">
        <span class="material-symbols-outlined text-[18px]">open_in_new</span> Site
    </a>
    <?php endif; ?>
    <button onclick="openModal('edit-lead-modal')"
            class="inline-flex items-center gap-2 h-10 px-5 bg-surface border border-stroke hover:bg-surface2 text-text rounded-pill text-sm font-medium transition-all">
        <span class="material-symbols-outlined text-[18px]">edit</span> Editar
    </button>
    <button id="btn-analyze" data-lead-id="<?= e($lead['id']) ?>"
            class="ai-trigger inline-flex items-center gap-2 h-10 px-6 bg-lime text-bg rounded-pill text-sm font-bold shadow-glow hover:brightness-110 transition-all ml-auto">
        <span class="material-symbols-outlined text-[18px]">psychology</span>
        <?= $analysis ? 'Re-analisar' : 'Analisar com IA' ?>
    </button>
    <button id="btn-4d" data-lead-id="<?= e($lead['id']) ?>"
            class="ai-trigger inline-flex items-center gap-2 h-10 px-5 bg-white text-bg rounded-pill text-sm font-bold hover:bg-white/90 transition-all shadow-soft ml-2">
        <span class="material-symbols-outlined text-[18px]">auto_awesome</span> Operon 4D
    </button>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Left column -->
    <div class="xl:col-span-2 space-y-6">

        <!-- AI Analysis -->
        <?php if ($analysis): ?>
        <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-text flex items-center gap-3">
                    <div class="size-10 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-lime text-[20px]">psychology</span>
                    </div>
                    Diagnóstico IA
                </h2>
                <div class="flex flex-col items-end">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-1">Maturidade Digital</span>
                    <span class="text-sm font-bold text-lime"><?= e($analysis['digitalMaturity'] ?? '—') ?></span>
                </div>
            </div>

            <?php if (!empty($analysis['scoreExplanation'])): ?>
            <div class="mb-6 p-4 rounded-xl bg-surface2 border-l-2 border-lime/50 text-sm text-muted italic">
                "<?= e($analysis['scoreExplanation']) ?>"
            </div>
            <?php endif; ?>

            <?php if (!empty($analysis['summary'])): ?>
            <p class="text-text text-sm mb-8 leading-relaxed"><?= e($analysis['summary']) ?></p>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (!empty($analysis['diagnosis'])): ?>
                <div class="bg-surface2 rounded-xl p-5 border border-stroke">
                    <p class="text-[11px] font-bold text-red-500 uppercase tracking-[0.1em] mb-4 flex items-center gap-2">
                        <span class="size-1.5 rounded-full bg-red-500"></span> Problemas Críticos
                    </p>
                    <ul class="space-y-3">
                        <?php foreach ($analysis['diagnosis'] as $item): ?>
                        <li class="flex items-start gap-2.5 text-sm text-subtle">
                            <span class="material-symbols-outlined text-red-500 text-[16px] mt-0.5 shrink-0">warning</span>
                            <?= e($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($analysis['opportunities'])): ?>
                <div class="bg-surface2 rounded-xl p-5 border border-stroke">
                    <p class="text-[11px] font-bold text-mint uppercase tracking-[0.1em] mb-4 flex items-center gap-2">
                        <span class="size-1.5 rounded-full bg-mint"></span> Oportunidades
                    </p>
                    <ul class="space-y-3">
                        <?php foreach ($analysis['opportunities'] as $item): ?>
                        <li class="flex items-start gap-2.5 text-sm text-subtle">
                            <span class="material-symbols-outlined text-mint text-[16px] mt-0.5 shrink-0">check_circle</span>
                            <?= e($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($analysis['urgencyLevel'])): ?>
            <div class="mt-8 pt-6 border-t border-stroke flex items-center justify-between">
                <span class="text-[11px] font-bold text-muted uppercase tracking-[0.1em]">Nível de Urgência</span>
                <?php $urg = $analysis['urgencyLevel'];
                $urgClass = $urg === 'Alta' ? 'bg-red-500/10 text-red-500 border-red-500/20'
                          : ($urg === 'Média' ? 'bg-amber-500/10 text-amber-500 border-amber-500/20'
                          : 'bg-mint/10 text-mint border-mint/20'); ?>
                <span class="px-3 py-1 rounded-pill text-xs font-bold border <?= $urgClass ?>"><?= e($urg) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bg-surface border border-dashed border-stroke rounded-cardLg p-10 text-center flex flex-col items-center justify-center min-h-[300px]">
            <div class="size-16 rounded-full bg-surface2 border border-stroke flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-3xl text-muted">psychology</span>
            </div>
            <p class="text-text font-medium text-lg mb-2">Diagnóstico Pendente</p>
            <p class="text-subtle text-sm mb-6 max-w-sm">Este lead ainda não passou pela análise da Operon Intelligence. Execute agora para identificar oportunidades e gerar insights.</p>
            <button id="btn-analyze-empty" data-lead-id="<?= e($lead['id']) ?>"
                    class="ai-trigger inline-flex items-center gap-2 h-10 px-6 bg-lime text-bg rounded-pill text-sm font-bold shadow-glow hover:brightness-110 transition-all">
                <span class="material-symbols-outlined text-[18px]">auto_fix_high</span>
                Iniciar Análise Completa
            </button>
        </div>
        <?php endif; ?>

        <!-- Operon 4D Results (injected via JS) -->
        <div id="operon4d-results" class="hidden space-y-6"></div>

        <!-- Deep Analysis (Modular Intelligence) -->
        <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pt-2">
                <h2 class="text-xl font-bold text-text flex items-center gap-3">
                    <div class="size-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-[20px]">query_stats</span>
                    </div>
                    Inteligência Profunda
                </h2>
                <!-- No global generate button anymore -->
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="deep-analysis-cards">
                <?php foreach ($availableIntelligences as $intel): 
                    $key = $intel['key'];
                    $record = $intelligenceHistory[$key] ?? null;
                    $status = $record ? $record['status'] : 'pending';
                    
                    // Decoded JSON context inside the manager handles presentation
                    $resultContent = '';
                    if ($status === 'completed' && !empty($record['result_data_decoded'])) {
                        // Tratar formato em array vs texto livre
                        if(isset($record['result_data_decoded']['content'])){
                            $resultContent = nl2br(e($record['result_data_decoded']['content']));
                        } elseif (isset($record['result_data_decoded']['items']) && is_array($record['result_data_decoded']['items'])) {
                            $resultContent = '<ul class="list-disc list-inside space-y-1.5">';
                            foreach ($record['result_data_decoded']['items'] as $item) {
                                $resultContent .= '<li>' . e($item) . '</li>';
                            }
                            $resultContent .= '</ul>';
                        }
                    } elseif ($status === 'failed') {
                        $resultContent = '<span class="text-red-400 text-xs italic">Erro: ' . e($record['error_message'] ?? 'Falha na geração') . '</span>';
                    }
                ?>
                <div class="bg-surface2 border border-stroke rounded-card p-5 flex flex-col h-full relative group">
                    <!-- Head -->
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="text-[11px] font-bold text-[<?= e($intel['color'] ?? 'white') ?>] uppercase tracking-[0.1em] flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px]"><?= e($intel['icon']) ?></span> <?= e($intel['name']) ?>
                        </h3>
                        <?php if($status === 'completed'): ?>
                            <div class="size-5 rounded-full bg-lime/10 flex items-center justify-center border border-lime/20" title="Concluído">
                                <span class="material-symbols-outlined text-lime text-[12px]">check</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Content Area -->
                    <div id="intel-content-<?= e($key) ?>" class="text-sm text-subtle leading-relaxed overflow-y-auto max-h-48 pr-2 flex-grow mb-4">
                        <?php if (empty($resultContent)): ?>
                            <p class="text-xs text-muted/70 italic mb-2"><?= e($intel['description']) ?></p>
                            <span class="text-muted italic flex items-center gap-2">
                                <span class="material-symbols-outlined text-[14px]">hourglass_empty</span> Aguardando geração...
                            </span>
                        <?php else: ?>
                            <?= $resultContent ?>
                        <?php endif; ?>
                    </div>

                    <!-- Footer Action -->
                    <div class="mt-auto flex items-center justify-between border-t border-stroke pt-4 z-10 relative">
                        <div class="flex items-center gap-1.5 text-xs font-medium text-amber-500/80 bg-amber-500/5 px-2 py-1 rounded-md border border-amber-500/20">
                            <span class="material-symbols-outlined text-[14px]">generating_tokens</span> <?= e($intel['tokens']) ?>
                        </div>
                        
                        <button id="btn-run-intel-<?= e($key) ?>" 
                                onclick="runDeepIntelligence('<?= e($lead['id']) ?>', '<?= e($key) ?>')"
                                class="h-8 px-4 rounded-pill <?= $status === 'completed' ? 'bg-surface3 text-text hover:bg-surface border border-stroke' : 'bg-lime text-bg hover:brightness-110 shadow-glow' ?> text-[11px] font-bold transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                            <?php if($status === 'completed'): ?>
                                <span class="material-symbols-outlined text-[14px]">refresh</span> Atualizar
                            <?php else: ?>
                                <span class="material-symbols-outlined text-[14px]">magic_button</span> Gerar
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- WhatsApp Conversations Linked -->
        <?php $waConversations = $waConversations ?? []; ?>
        <?php if (!empty($waConversations)): ?>
        <div class="bg-surface border border-mint/20 rounded-cardLg p-7 shadow-soft">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-text flex items-center gap-3">
                    <div class="size-10 rounded-full bg-mint/10 border border-mint/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-mint text-[20px]">chat</span>
                    </div>
                    Conversas WhatsApp
                    <span class="text-xs font-bold bg-mint/10 text-mint px-2.5 py-1 rounded-pill border border-mint/20"><?= count($waConversations) ?></span>
                </h2>
            </div>

            <div class="space-y-4">
                <?php foreach ($waConversations as $wac):
                    $convId = $wac['conversation_id'] ?? '';
                    $displayName = $wac['display_name'] ?? $wac['phone'] ?? $wac['remote_jid'] ?? '—';
                    $phone = $wac['phone'] ?? $wac['remote_jid'] ?? '';
                    $lastMsg = $wac['last_message_at'] ?? null;
                    $unread = (int)($wac['unread_count'] ?? 0);

                    // Score data
                    $scoreData = ($wac['score'] ?? null) ? ($wac['score']['analysis_data'] ?? []) : [];
                    $interestScore = $scoreData['interest_score'] ?? null;

                    // Summary data
                    $summaryData = ($wac['summary'] ?? null) ? ($wac['summary']['analysis_data'] ?? []) : [];
                    $summaryText = $summaryData['summary'] ?? null;
                    $pains = $summaryData['pains'] ?? [];
                    $interestLevel = $summaryData['interest_level'] ?? null;
                ?>
                <div class="bg-surface2 border border-stroke rounded-card p-5 hover:border-mint/30 transition-colors" id="wa-card-<?= e($convId) ?>">
                    <!-- Header row -->
                    <div class="flex items-center gap-4 mb-3">
                        <div class="size-10 rounded-full bg-mint/10 border border-mint/20 flex items-center justify-center text-mint font-bold text-sm flex-shrink-0">
                            <?= mb_substr($displayName, 0, 1, 'UTF-8') ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-text text-sm truncate"><?= e($displayName) ?></span>
                                <?php if ($unread > 0): ?>
                                <span class="text-[10px] font-bold bg-mint text-bg px-1.5 py-0.5 rounded-full"><?= $unread ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-[11px] text-muted font-mono truncate"><?= e($phone) ?></p>
                        </div>
                        <?php if ($interestScore !== null): ?>
                        <div class="flex flex-col items-center gap-0.5" id="wa-score-badge-<?= e($convId) ?>">
                            <span class="text-[10px] font-bold text-muted uppercase tracking-wider">Score</span>
                            <span class="text-lg font-black <?= $interestScore >= 70 ? 'text-lime' : ($interestScore >= 40 ? 'text-amber-400' : 'text-red-400') ?>"><?= $interestScore ?></span>
                        </div>
                        <?php else: ?>
                        <div id="wa-score-badge-<?= e($convId) ?>"></div>
                        <?php endif; ?>
                        <a href="/whatsapp/conversation/<?= e($convId) ?>"
                           class="h-8 px-3 rounded-pill bg-mint/10 text-mint hover:bg-mint/20 border border-mint/20 text-[11px] font-bold flex items-center gap-1.5 transition-all flex-shrink-0">
                            <span class="material-symbols-outlined text-[14px]">open_in_new</span> Abrir
                        </a>
                    </div>

                    <!-- Summary snippet (if exists) -->
                    <?php if ($summaryText): ?>
                    <div class="mb-3 text-xs text-subtle leading-relaxed line-clamp-2 pl-14" id="wa-summary-text-<?= e($convId) ?>">
                        <?= e(mb_substr($summaryText, 0, 200, 'UTF-8')) ?><?= mb_strlen($summaryText, 'UTF-8') > 200 ? '...' : '' ?>
                    </div>
                    <?php else: ?>
                    <div class="mb-3 pl-14" id="wa-summary-text-<?= e($convId) ?>"></div>
                    <?php endif; ?>

                    <!-- Tags row -->
                    <?php if (!empty($pains) || $interestLevel): ?>
                    <div class="flex flex-wrap gap-1.5 mb-3 pl-14">
                        <?php if ($interestLevel): ?>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-pill bg-lime/10 text-lime border border-lime/20"><?= e($interestLevel) ?></span>
                        <?php endif; ?>
                        <?php foreach (array_slice($pains, 0, 3) as $pain): ?>
                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-pill bg-red-400/10 text-red-400 border border-red-400/20"><?= e($pain) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Action buttons -->
                    <div class="flex items-center gap-2 pl-14">
                        <button onclick="waIntel('<?= e($convId) ?>', 'summary', this)"
                                class="h-7 px-3 rounded-pill bg-surface border border-stroke hover:bg-surface2 text-[10px] font-bold text-muted hover:text-text transition-all flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]">summarize</span> Resumo
                        </button>
                        <button onclick="waIntel('<?= e($convId) ?>', 'strategic', this)"
                                class="h-7 px-3 rounded-pill bg-surface border border-stroke hover:bg-surface2 text-[10px] font-bold text-muted hover:text-text transition-all flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]">strategy</span> Estratégia
                        </button>
                        <button onclick="waIntel('<?= e($convId) ?>', 'interest-score', this)"
                                class="h-7 px-3 rounded-pill bg-surface border border-stroke hover:bg-surface2 text-[10px] font-bold text-muted hover:text-text transition-all flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]">speed</span> Score
                        </button>
                        <button onclick="waIntel('<?= e($convId) ?>', 'next-message', this)"
                                class="h-7 px-3 rounded-pill bg-surface border border-stroke hover:bg-surface2 text-[10px] font-bold text-muted hover:text-text transition-all flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]">auto_fix_high</span> Mensagem
                        </button>
                        <?php if ($lastMsg): ?>
                        <span class="ml-auto text-[10px] text-muted"><?= timeAgo($lastMsg) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Inline result container -->
                    <div class="hidden mt-3 pl-14" id="wa-result-<?= e($convId) ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Call Recordings & Transcriptions -->
        <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <h2 class="text-xl font-bold text-text flex items-center gap-3">
                    <div class="size-10 rounded-full bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-indigo-400 text-[20px]">mic</span>
                    </div>
                    Calls & Transcrições
                </h2>
                
                <div class="flex items-center gap-3">
                    <button id="btn-record-audio" onclick="toggleRecording('<?= $lead['id'] ?>')" class="h-9 px-4 rounded-pill bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 text-[12px] font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]" id="icon-record-audio">mic</span> <span id="text-record-audio">Gravar Áudio</span>
                        <span id="time-record-audio" class="hidden text-[10px] bg-rose-500/20 px-1.5 py-0.5 rounded ml-1 tracking-wider font-mono">00:00</span>
                    </button>

                    <label id="btn-upload-audio" class="h-9 px-4 rounded-pill bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20 border border-indigo-500/20 text-[12px] font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">upload_file</span> Enviar Áudio
                        <input type="file" id="call-audio-upload" accept="audio/*,video/mp4" class="hidden" onchange="uploadCallAudio(this, '<?= $lead['id'] ?>')">
                    </label>

                    <!-- DIV PARA CONFIRMAR GRAVAÇÃO -->
                    <div id="confirm-audio-div" class="hidden items-center gap-2">
                        <div class="flex items-center text-[12px] font-mono text-lime-400 bg-lime-400/10 px-3 h-9 rounded-pill border border-lime-400/20">
                            <span class="material-symbols-outlined text-[14px] mr-1">check_circle</span>
                            Áudio Pronto (<span id="confirm-audio-time">00:00</span>)
                        </div>
                        <button onclick="submitRecordedAudio()" id="btn-submit-audio" class="h-9 px-4 rounded-pill bg-lime text-bg hover:brightness-110 shadow-glow text-[12px] font-bold transition-all flex items-center justify-center gap-1.5">
                            Transcrever e Analisar
                        </button>
                        <button onclick="discardRecordedAudio()" class="h-9 w-9 rounded-full bg-red-400/10 text-red-400 hover:bg-red-400/20 border border-red-400/20 flex items-center justify-center transition-all" title="Descartar gravação">
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4" id="calls-list-container">
                <?php if(empty($calls)): ?>
                    <div class="py-10 text-center border border-dashed border-stroke rounded-xl bg-surface2/50">
                        <span class="material-symbols-outlined text-muted text-4xl mb-3">mic_off</span>
                        <p class="text-sm text-subtle">Nenhuma call registrada para este lead.</p>
                        <p class="text-xs text-muted mt-1">Faça upload de uma gravação para transcrever e analisar comercialmente.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($calls as $call): 
                        $rawAnalysis = $call['analysis_data'] ? json_decode($call['analysis_data'], true) : null;
                        $execSummary = $rawAnalysis['executive_summary'] ?? 'Resumo pendente ou não estruturado.';
                        
                        $statusColors = [
                            'uploading'    => 'text-blue-400 bg-blue-400/10 border-blue-400/20',
                            'stored'       => 'text-blue-400 bg-blue-400/10 border-blue-400/20',
                            'transcribing' => 'text-amber-400 bg-amber-400/10 border-amber-400/20',
                            'transcribed'  => 'text-lime-400 bg-lime-400/10 border-lime-400/20',
                            'analyzing'    => 'text-indigo-400 bg-indigo-400/10 border-indigo-400/20',
                            'completed'    => 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
                            'failed'       => 'text-red-400 bg-red-400/10 border-red-400/20',
                        ];
                        $stColor = $statusColors[$call['status']] ?? 'text-muted bg-surface3 border-stroke';
                        
                        $statusLabels = [
                            'uploading'    => 'Enviando...',
                            'stored'       => 'Na Fila',
                            'transcribing' => 'Transcrevendo (Whisper)',
                            'transcribed'  => 'Transcritão',
                            'analyzing'    => 'Analisando Negócio',
                            'completed'    => 'Concluído',
                            'failed'       => 'Falha',
                        ];
                        $stLabel = $statusLabels[$call['status']] ?? $call['status'];
                    ?>
                    <div class="border border-stroke rounded-xl overflow-hidden call-item" data-call-id="<?= $call['id'] ?>" data-status="<?= $call['status'] ?>">
                        <!-- Header -->
                        <div class="bg-surface2 p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 cursor-pointer" onclick="toggleCallDetails(<?= $call['id'] ?>)">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-muted">record_voice_over</span>
                                <div>
                                    <h4 class="font-bold text-sm text-text"><?= e($call['title']) ?></h4>
                                    <div class="flex items-center gap-2 text-xs text-muted mt-1">
                                        <span><?= date('d/m/Y H:i', strtotime($call['created_at'])) ?></span>
                                        <span>•</span>
                                        <span><?= $call['duration'] ? gmdate("i:s", (int)$call['duration']) : '--:--' ?></span>
                                        <?php if($call['language']): ?>
                                            <span>•</span><span class="uppercase"><?= e($call['language']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-1 text-[10px] uppercase tracking-wider font-bold rounded-md border <?= $stColor ?> status-badge flex items-center gap-1.5">
                                    <?php if(in_array($call['status'], ['uploading', 'transcribing', 'analyzing'])): ?>
                                        <span class="material-symbols-outlined text-[12px] animate-spin">refresh</span>
                                    <?php endif; ?>
                                    <span class="status-text"><?= $stLabel ?></span>
                                </span>
                                <span class="material-symbols-outlined text-muted text-sm transition-transform duration-200" id="call-icon-<?= $call['id'] ?>">expand_more</span>
                            </div>
                        </div>

                        <!-- Body (Hidden by default unless completed/failed) -->
                        <div id="call-body-<?= $call['id'] ?>" class="hidden border-t border-stroke bg-surface/50 p-5">
                            
                            <?php if($call['status'] === 'failed'): ?>
                                <div class="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-500 text-sm mb-4">
                                    Falha no processamento: <?= e($call['error_message']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if($call['status'] === 'completed' && $rawAnalysis): ?>
                                <div class="mb-5">
                                    <h5 class="text-xs font-bold text-mint uppercase tracking-wider mb-2">Resumo da Call</h5>
                                    <p class="text-sm text-text leading-relaxed"><?= nl2br(e($execSummary)) ?></p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                                    <div class="bg-surface2 p-4 rounded-lg border border-stroke">
                                        <h5 class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-2 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">warning</span> Dores / Objeções</h5>
                                        <ul class="list-disc list-inside text-sm text-subtle space-y-1">
                                            <?php foreach(($rawAnalysis['core_pain_points']??[]) as $d) echo "<li>".e($d)."</li>"; ?>
                                            <?php foreach(($rawAnalysis['identified_objections']??[]) as $d) echo "<li>".e($d)."</li>"; ?>
                                        </ul>
                                    </div>
                                    <div class="bg-surface2 p-4 rounded-lg border border-stroke">
                                        <h5 class="text-xs font-bold text-lime-400 uppercase tracking-wider mb-2 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">flag</span> Próximos Passos</h5>
                                        <ul class="list-disc list-inside text-sm text-subtle space-y-1">
                                            <?php foreach(($rawAnalysis['recommended_next_steps']??[]) as $d) echo "<li>".e($d)."</li>"; ?>
                                        </ul>
                                        <div class="mt-3 text-xs">
                                            <span class="text-muted">Fit (ICP):</span> <span class="font-bold text-text"><?= e($rawAnalysis['icp_fit_score']??'N/A') ?>/100</span>
                                            <span class="mx-2 text-stroke">|</span>
                                            <span class="text-muted">Temperatura:</span> <span class="font-bold text-text"><?= e($rawAnalysis['temperature']??'N/A') ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if($call['transcript_clean']): ?>
                                <div class="mt-4">
                                    <button class="text-xs font-bold text-indigo-400 hover:text-indigo-300 flex items-center gap-1" onclick="document.getElementById('transcript-<?= $call['id'] ?>').classList.toggle('hidden')">
                                        <span class="material-symbols-outlined text-[14px]">notes</span> Ver Transcrição Completa
                                    </button>
                                    <div id="transcript-<?= $call['id'] ?>" class="hidden mt-3 p-4 bg-bg border border-stroke rounded-lg max-h-64 overflow-y-auto text-xs text-subtle leading-loose">
                                        <?= nl2br(e($call['transcript_clean'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- PageSpeed -->
        <?php if ($ps): ?>
        <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
            <h2 class="text-xl font-bold text-text flex items-center gap-3 mb-6">
                <div class="size-10 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-500 text-[20px]">speed</span>
                </div>
                Performance Web
            </h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php
                $metrics = [
                    ['label' => 'Performance', 'value' => $ps['performanceScore'] ?? '?', 'unit' => '/100'],
                    ['label' => 'Carregamento', 'value' => $ps['loadTime'] ?? '?', 'unit' => 's'],
                    ['label' => 'SEO', 'value' => $ps['seoScore'] ?? '?', 'unit' => '/100'],
                    ['label' => 'Acessibil.', 'value' => $ps['accessibilityScore'] ?? '?', 'unit' => '/100'],
                ];
                foreach ($metrics as $m): ?>
                <div class="bg-surface2 flex flex-col justify-center border border-stroke rounded-card p-5 text-center">
                    <p class="text-[32px] font-bold text-text mb-1"><?= e($m['value']) ?><span class="text-xs text-muted ml-0.5"><?= e($m['unit']) ?></span></p>
                    <p class="text-xs text-muted font-medium uppercase tracking-[0.05em]"><?= e($m['label']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SPIN & Scripts shortcut -->
        <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft">
            <h2 class="text-xl font-bold text-text flex items-center gap-3 mb-6">
                <div class="size-10 rounded-full bg-surface2 border border-stroke flex items-center justify-center">
                    <span class="material-symbols-outlined text-text text-[20px]">forum</span>
                </div>
                Inteligência de Abordagem
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="/spin?lead_id=<?= e($lead['id']) ?>"
                   class="flex flex-col gap-4 p-5 bg-surface2 hover:bg-surface3 border border-stroke rounded-card transition-all group">
                    <div class="flex items-center justify-between">
                        <span class="material-symbols-outlined text-text text-2xl">psychology_alt</span>
                        <span class="size-8 rounded-full bg-surface border border-stroke flex items-center justify-center text-muted group-hover:text-text transition-colors"><span class="material-symbols-outlined text-[16px]">chevron_right</span></span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-text mb-1">Perguntas SPIN</p>
                        <p class="text-xs text-subtle leading-relaxed">Framework estruturado de qualificação para descobrir dores reais.</p>
                    </div>
                </a>
                <a href="/spin?lead_id=<?= e($lead['id']) ?>&tab=scripts"
                   class="flex flex-col gap-4 p-5 bg-surface2 hover:bg-surface3 border border-stroke rounded-card transition-all group">
                    <div class="flex items-center justify-between">
                        <span class="material-symbols-outlined text-lime text-2xl">edit_note</span>
                        <span class="size-8 rounded-full bg-surface border border-stroke flex items-center justify-center text-muted group-hover:text-lime transition-colors"><span class="material-symbols-outlined text-[16px]">chevron_right</span></span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-text mb-1">Scripts de Abordagem</p>
                        <p class="text-xs text-subtle leading-relaxed">Templates gerados por IA para WhatsApp, LinkedIn e E-mail frio.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Linha do Tempo & Anexos (MOVED HERE) -->
        <style>
            @keyframes timelineSlideUp {
                0% { opacity: 0; transform: translateY(20px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            .timeline-item-enter {
                opacity: 0;
                animation: timelineSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
        </style>
        <div class="bg-surface border border-stroke rounded-cardLg p-7 shadow-soft mt-6">
            <h2 class="text-xl font-bold text-text flex items-center gap-3 mb-6">
                <div class="size-10 rounded-full bg-surface2 border border-stroke flex items-center justify-center">
                    <span class="material-symbols-outlined text-muted text-[20px]">history</span>
                </div>
                Linha do Tempo
            </h2>

            <!-- Add new activity tabs/forms -->
            <div class="mb-8 bg-surface2 border border-stroke rounded-xl overflow-hidden transition-all duration-300">
                <div class="flex border-b border-stroke">
                    <button class="flex-1 py-3 text-sm font-bold text-lime bg-lime/10 border-b-2 border-lime transition-all" id="tab-btn-note" onclick="switchTimelineTab('note'); return false;">Nota Manual</button>
                    <button class="flex-1 py-3 text-sm font-bold text-muted hover:text-text hover:bg-surface3 border-b-2 border-transparent transition-all" id="tab-btn-file" onclick="switchTimelineTab('file'); return false;">Anexar Arquivo</button>
                </div>

                <!-- Note Form -->
                <form method="POST" action="/vault/<?= e($lead['id']) ?>/note" id="form-tab-note" class="p-5 block animate-fade-in">
                    <?= csrf_field() ?>
                    <textarea name="note_content" rows="3" required
                              class="w-full bg-surface border border-stroke rounded-xl p-4 text-sm text-text focus:border-lime/50 focus:shadow-glow outline-none resize-none transition-all placeholder:text-muted mb-4"
                              placeholder="Escreva detalhes de uma ligação, reunião ou observação..."></textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="h-10 px-6 bg-lime text-bg rounded-pill text-sm font-bold shadow-glow hover:brightness-110 hover:-translate-y-0.5 transition-all">
                            Salvar Nota
                        </button>
                    </div>
                </form>

                <!-- File Form -->
                <form method="POST" action="/vault/<?= e($lead['id']) ?>/attachment" enctype="multipart/form-data" id="form-tab-file" class="p-5 hidden animate-fade-in">
                    <?= csrf_field() ?>
                    <div class="flex items-center justify-center w-full mb-4">
                        <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-stroke border-dashed rounded-xl cursor-pointer bg-surface hover:bg-surface3 hover:border-lime/50 transition-all group">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <span class="material-symbols-outlined text-muted mb-2 group-hover:text-lime transition-colors text-2xl">cloud_upload</span>
                                <p class="text-xs text-subtle"><span class="font-bold text-text group-hover:text-lime transition-colors">Clique para enviar</span> ou arraste</p>
                                <p class="text-[10px] text-muted mt-1">PDF, JPG, PNG, CSV, DOCX (Max 5MB)</p>
                            </div>
                            <input type="file" name="attachment" class="hidden" required id="file-upload-input" onchange="updateFileName(this)"/>
                        </label>
                    </div>
                    <p id="file-upload-name" class="text-sm text-lime font-medium text-center mb-4 hidden"></p>
                    <div class="flex justify-end">
                        <button type="submit" class="h-10 px-6 bg-white text-bg rounded-pill text-sm font-bold shadow-soft hover:brightness-90 hover:-translate-y-0.5 transition-all">
                            Anexar Arquivo
                        </button>
                    </div>
                </form>
            </div>

            <!-- Feed -->
            <div class="space-y-6 relative before:absolute before:inset-0 before:ml-[1.4rem] before:-translate-x-px before:h-full before:w-[2px] before:bg-gradient-to-b before:from-lime/30 before:via-stroke before:to-transparent">
                <?php if (empty($activities)): ?>
                    <p class="text-sm text-muted text-center py-8 relative z-10 italic">Nenhuma atividade registrada.</p>
                <?php else: ?>
                    <?php foreach ($activities as $index => $act): 
                        $isNote = $act['type'] === 'note';
                        $isAttachment = $act['type'] === 'attachment';
                        $meta = is_string($act['metadata']) ? json_decode($act['metadata'], true) : [];
                        $delay = min($index * 100, 1000); // cap delay at 1s
                    ?>
                    <div class="relative z-10 flex items-start gap-5 group timeline-item-enter" style="animation-delay: <?= $delay ?>ms;">
                        <div class="size-11 rounded-full bg-surface2 border-2 <?= $isNote ? 'border-lime/30 text-lime' : ($isAttachment ? 'border-white/30 text-white' : 'border-stroke text-muted') ?> flex items-center justify-center shrink-0 z-10 shadow-soft group-hover:scale-110 group-hover:shadow-glow transition-all duration-300">
                            <?php if ($isNote): ?>
                                <span class="material-symbols-outlined text-[20px]">sticky_note_2</span>
                            <?php elseif ($isAttachment): ?>
                                <span class="material-symbols-outlined text-[20px]">attach_file</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-[20px]">update</span>
                            <?php endif; ?>
                        </div>
                        <div class="pt-1 flex-1 min-w-0">
                            <div class="bg-surface2 border border-stroke rounded-cardLg p-5 group-hover:-translate-y-1 group-hover:border-lime/30 group-hover:shadow-soft transition-all duration-300">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                    <p class="text-sm font-bold text-text truncate"><?= e($act['title']) ?></p>
                                    <span class="text-xs text-muted whitespace-nowrap flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">schedule</span>
                                        <?= e(timeAgo($act['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="text-sm text-subtle leading-relaxed mb-4">
                                    <?= nl2br(e($act['content'])) ?>
                                </div>
                                <?php if ($isAttachment && !empty($meta['url'])): ?>
                                    <div class="mt-4 flex items-center justify-between p-3 rounded-xl bg-surface border border-stroke group/link hover:border-lime/50 transition-colors">
                                        <div class="flex items-center gap-3 truncate">
                                            <div class="size-8 rounded-lg bg-surface2 border border-stroke flex items-center justify-center shrink-0">
                                                <span class="material-symbols-outlined text-[18px] text-muted group-hover/link:text-lime transition-colors">draft</span>
                                            </div>
                                            <span class="text-xs text-text font-bold truncate group-hover/link:text-lime transition-colors"><?= e($meta['filename'] ?? 'Anexo') ?></span>
                                        </div>
                                        <a href="<?= e($meta['url']) ?>" target="_blank" class="h-8 px-4 bg-white hover:bg-white/90 rounded-pill text-xs font-bold text-bg flex items-center transition-transform hover:scale-105 shadow-soft shrink-0">
                                            Abrir
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-4 pt-4 border-t border-stroke/50 flex items-center gap-2">
                                    <div class="size-5 rounded-full bg-surface border border-stroke flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[12px] text-muted">person</span>
                                    </div>
                                    <span class="text-[11px] text-muted font-bold uppercase tracking-wider">
                                        <?= e($act['user_name'] ?? 'Sistema') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right column -->
    <div class="space-y-6">

        <!-- Contact info -->
        <div class="bg-surface border border-stroke rounded-card p-6 shadow-soft">
            <h2 class="text-base font-bold text-text flex items-center gap-2 mb-5">
                <span class="material-symbols-outlined text-muted text-[18px]">contact_page</span>
                Contato
            </h2>
            <div class="space-y-4">
                <?php if ($lead['phone']): ?>
                <div class="flex items-center gap-3 text-sm">
                    <span class="size-8 rounded-full bg-surface2 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-muted text-[14px]">call</span>
                    </span>
                    <span class="text-text font-medium"><?= e($lead['phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['email']): ?>
                <div class="flex items-center gap-3 text-sm">
                    <span class="size-8 rounded-full bg-surface2 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-muted text-[14px]">mail</span>
                    </span>
                    <span class="text-text font-medium truncate"><?= e($lead['email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['website']): ?>
                <div class="flex items-center gap-3 text-sm">
                    <span class="size-8 rounded-full bg-surface2 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-muted text-[14px]">language</span>
                    </span>
                    <a href="<?= e($lead['website']) ?>" target="_blank" class="text-lime hover:underline truncate font-medium"><?= e($lead['website']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($lead['address']): ?>
                <div class="flex items-start gap-3 text-sm">
                    <span class="size-8 rounded-full bg-surface2 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-muted text-[14px]">location_on</span>
                    </span>
                    <span class="text-text font-medium leading-tight mt-1"><?= e($lead['address']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Social presence -->
            <?php if (!empty(array_filter($social))): ?>
            <div class="mt-6 pt-5 border-t border-stroke">
                <p class="text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-3">Presença Digital</p>
                <div class="flex flex-wrap gap-2">
                    <?php if (!empty($social['instagram'])): ?>
                    <span class="flex items-center gap-1.5 text-xs bg-surface2 border border-stroke text-text rounded-md px-2.5 py-1.5 font-medium">
                        <span class="material-symbols-outlined text-[14px] text-pink-500">photo_camera</span> <?= e($social['instagram']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($social['linkedin'])): ?>
                    <span class="flex items-center gap-1.5 text-xs bg-surface2 border border-stroke text-text rounded-md px-2.5 py-1.5 font-medium">
                        <span class="material-symbols-outlined text-[14px] text-blue-500">work</span> <?= e($social['linkedin']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($social['facebook'])): ?>
                    <span class="flex items-center gap-1.5 text-xs bg-surface2 border border-stroke text-text rounded-md px-2.5 py-1.5 font-medium">
                        <span class="material-symbols-outlined text-[14px] text-indigo-500">thumb_up</span> <?= e($social['facebook']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pipeline stage -->
        <div class="bg-surface border border-stroke rounded-card p-6 shadow-soft">
            <h2 class="text-base font-bold text-text flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-muted text-[18px]">view_kanban</span>
                Estágio do Pipeline
            </h2>
            <div class="space-y-1.5" id="stage-selector">
                <?php foreach (Lead::STAGES as $stageKey => $stageLabel): ?>
                <button onclick="changeStage('<?= e($lead['id']) ?>', '<?= e($stageKey) ?>')"
                        class="w-full flex items-center gap-2.5 px-4 py-3 rounded-xl text-sm transition-all font-medium border
                               <?= $lead['pipeline_status'] === $stageKey
                                   ? 'bg-lime/10 text-lime border-lime/20'
                                   : 'bg-transparent text-muted hover:text-text hover:bg-surface2 border-transparent' ?>">
                    <span class="size-2 rounded-full <?= $lead['pipeline_status'] === $stageKey ? 'bg-lime shadow-[0_0_8px_rgba(225,251,21,0.5)]' : 'bg-surface3' ?>"></span>
                    <?= e($stageLabel) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Context (human_context) -->
        <div class="bg-surface border border-stroke rounded-card p-6 shadow-soft">
            <h2 class="text-base font-bold text-text flex items-center gap-2 mb-5">
                <span class="material-symbols-outlined text-muted text-[18px]">thermostat</span>
                Termômetro
            </h2>
            <div class="space-y-5">
                <!-- Temperature -->
                <div>
                    <p class="text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Temperatura</p>
                    <div class="flex gap-2">
                        <?php foreach (['HOT' => ['text-red-500', 'Quente'], 'WARM' => ['text-amber-500', 'Morno'], 'COLD' => ['text-[#60A5FA]', 'Frio']] as $val => [$cls, $lbl]): ?>
                        <button onclick="saveContext('<?= e($lead['id']) ?>', 'temperature', '<?= $val ?>')"
                                class="flex-1 py-2 rounded-lg text-xs font-bold uppercase tracking-wide border transition-all
                                       <?= ($ctx['temperature'] ?? '') === $val
                                           ? "border-current {$cls} bg-current/10"
                                           : 'border-stroke text-muted hover:bg-surface2' ?>">
                             <?= $lbl ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Timing -->
                <div>
                    <p class="text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Timing</p>
                    <div class="flex gap-2">
                        <?php foreach (['IMMEDIATE' => 'Imediato', 'SHORT_TERM' => 'Curto', 'LONG_TERM' => 'Longo'] as $val => $lbl): ?>
                        <button onclick="saveContext('<?= e($lead['id']) ?>', 'timingStatus', '<?= $val ?>')"
                                class="flex-1 py-2 rounded-lg text-xs font-bold uppercase tracking-wide border transition-all
                                       <?= ($ctx['timingStatus'] ?? '') === $val
                                           ? 'border-lime text-lime bg-lime/10'
                                           : 'border-stroke text-muted hover:bg-surface2' ?>">
                            <?= $lbl ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Objection -->
                <div>
                    <p class="text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Objeção Principal</p>
                    <select onchange="saveContext('<?= e($lead['id']) ?>', 'objectionCategory', this.value)"
                            class="w-full h-10 bg-surface2 border border-stroke rounded-lg px-3 text-sm text-text font-medium focus:border-lime/50 outline-none transition-colors appearance-none cursor-pointer">
                        <option value="">Nenhuma mapeada</option>
                        <option value="PRICE" <?= ($ctx['objectionCategory'] ?? '') === 'PRICE' ? 'selected' : '' ?>>💰 Preço / Orçamento</option>
                        <option value="COMPETITOR" <?= ($ctx['objectionCategory'] ?? '') === 'COMPETITOR' ? 'selected' : '' ?>>⚔️ Concorrente</option>
                        <option value="TIMING" <?= ($ctx['objectionCategory'] ?? '') === 'TIMING' ? 'selected' : '' ?>>⏳ Timing / Agora não</option>
                        <option value="TRUST" <?= ($ctx['objectionCategory'] ?? '') === 'TRUST' ? 'selected' : '' ?>>🛡️ Confiança / Autoridade</option>
                    </select>
                </div>
                <!-- Notes -->
                <div>
                    <p class="text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Anotações Livres</p>
                    <textarea id="context-notes" rows="4"
                              onblur="saveContextNotes('<?= e($lead['id']) ?>')"
                              class="w-full bg-surface2 border border-stroke rounded-xl p-3 text-sm text-text focus:border-lime/50 outline-none resize-none transition-colors placeholder:text-muted"
                              placeholder="Detalhes adicionais da negociação..."><?= e($ctx['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Tags -->
        <div class="bg-surface border border-stroke rounded-card p-6 shadow-soft">
            <h2 class="text-base font-bold text-text flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-muted text-[18px]">label</span>
                Tags
            </h2>
            <div class="flex flex-wrap gap-2 mb-4" id="tags-container">
                <?php foreach ($tags as $tag): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-surface2 border border-stroke text-muted rounded-md text-xs font-medium">
                    <?= e($tag) ?>
                    <button onclick="removeTag('<?= e($lead['id']) ?>', '<?= e($tag) ?>')"
                            class="text-subtle hover:text-red-500 transition-colors ml-1 leading-none">&times;</button>
                </span>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
                <input type="text" id="new-tag" placeholder="Adicionar tag..."
                       class="flex-1 h-9 bg-surface2 border border-stroke rounded-pill px-4 text-xs text-text placeholder:text-muted focus:border-lime/50 outline-none transition-colors"
                       onkeydown="if(event.key==='Enter'){addTag('<?= e($lead['id']) ?>');}">
                <button onclick="addTag('<?= e($lead['id']) ?>')"
                        class="h-9 px-4 bg-surface2 hover:bg-surface3 border border-stroke text-text rounded-pill text-xs font-medium transition-colors">
                    Adicionar
                </button>
            </div>
        </div>

        <!-- Meta -->
        <div class="bg-surface border border-stroke rounded-card p-6 shadow-soft">
            <h2 class="text-base font-bold text-text flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-muted text-[18px]">info</span>
                Metadados
            </h2>
            <div class="space-y-3 text-xs text-subtle font-medium">
                <div class="flex justify-between items-center border-b border-stroke pb-3 last:border-0 last:pb-0">
                    <span class="text-muted">ID Sistema</span>
                    <span class="text-text font-mono bg-surface2 px-2 py-0.5 rounded"><?= e(substr($lead['id'], 0, 8)) ?>...</span>
                </div>
                <div class="flex justify-between items-center border-b border-stroke pb-3 last:border-0 last:pb-0">
                    <span class="text-muted">Data de Criação</span>
                    <span class="text-text"><?= e(timeAgo($lead['created_at'])) ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-stroke pb-3 last:border-0 last:pb-0">
                    <span class="text-muted">Última Atualização</span>
                    <span class="text-text"><?= e(timeAgo($lead['updated_at'])) ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-stroke pb-3 last:border-0 last:pb-0">
                    <span class="text-muted">Fit Score Interno</span>
                    <span class="text-text bg-surface2 px-2 py-0.5 rounded border border-stroke"><?= e($lead['fit_score'] ?? 0) ?></span>
                </div>
        </div>



    </div>
</div>

<!-- Edit Lead Modal -->
<div id="edit-lead-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-bg/80 backdrop-blur-md" onclick="closeModal('edit-lead-modal')"></div>
    <div class="relative bg-surface border border-stroke rounded-cardLg p-7 w-full max-w-lg shadow-2xl animate-popIn">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-text">Editar Informações</h3>
            <button onclick="closeModal('edit-lead-modal')" class="size-8 flex items-center justify-center rounded-full bg-surface2 border border-stroke text-muted hover:text-text transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <form method="POST" action="/vault/<?= e($lead['id']) ?>/update" class="space-y-5">
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-5">
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Nome da empresa *</label>
                    <input type="text" name="name" value="<?= e($lead['name']) ?>" required
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text focus:border-lime/50 outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Segmento</label>
                    <input type="text" name="segment" value="<?= e($lead['segment']) ?>"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text focus:border-lime/50 outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Telefone</label>
                    <input type="text" name="phone" value="<?= e($lead['phone'] ?? '') ?>"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text focus:border-lime/50 outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Email corporativo</label>
                    <input type="email" name="email" value="<?= e($lead['email'] ?? '') ?>"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text focus:border-lime/50 outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Website URL</label>
                    <input type="url" name="website" value="<?= e($lead['website'] ?? '') ?>" placeholder="https://"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text focus:border-lime/50 outline-none transition-colors">
                </div>
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Sede / Endereço</label>
                    <input type="text" name="address" value="<?= e($lead['address'] ?? '') ?>"
                           class="w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-text focus:border-lime/50 outline-none transition-colors">
                </div>
            </div>
            <div class="pt-2">
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 h-12 bg-lime text-bg rounded-pill font-bold shadow-glow hover:brightness-110 transition-all">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
$safeId = e($lead['id']);
$extraScripts = <<<JS
<script>
const LEAD_ID = '{$safeId}';
console.log('Lead view initialized for:', LEAD_ID);

// ── Stage change ─────────────────────────────────────────
async function changeStage(id, stage) {
    console.log('Changing stage to:', stage);
    try {
        await operonFetch('/vault/' + id + '/stage', {
            method: 'POST',
            body: JSON.stringify({ stage })
        });
        
        // Update UI without reload
        document.querySelectorAll('#stage-selector button').forEach(btn => {
            btn.className = btn.className.replace(/bg-lime\/10.*?border-lime\/20/, 'bg-transparent text-muted hover:text-text hover:bg-surface2 border-transparent');
            const dot = btn.querySelector('span.rounded-full');
            if (dot) {
               dot.classList.remove('bg-lime', 'shadow-[0_0_8px_rgba(225,251,21,0.5)]'); 
               dot.classList.add('bg-surface3');
            }
        });
        
        const active = document.querySelector('#stage-selector button[onclick*="' + stage + '"]');
        if (active) {
            active.className = active.className.replace(/bg-transparent text-muted hover:text-text hover:bg-surface2 border-transparent/, 'bg-lime/10 text-lime border-lime/20');
            const dot = active.querySelector('span.rounded-full');
            if (dot) { 
               dot.classList.remove('bg-surface3'); 
               dot.classList.add('bg-lime', 'shadow-[0_0_8px_rgba(225,251,21,0.5)]'); 
            }
        }
    } catch (err) {
        console.error('Error changing stage:', err);
    }
}

// ── Context save ─────────────────────────────────────────
async function saveContext(id, field, value) {
    console.log('Saving context:', field, value);
    try {
        // Re-render buttons optimistically
        const btns = document.querySelectorAll('[onclick*="saveContext"][onclick*="' + field + '"]');
        
        btns.forEach(btn => {
            if(field === 'temperature') {
               btn.className = btn.className.replace(/border-current.*?text-red-500|border-current.*?text-amber-500|border-current.*?text-\[\#60A5FA\]/, 'border-stroke text-muted hover:bg-surface2');
               btn.classList.add('border-stroke', 'text-muted', 'hover:bg-surface2');
               btn.classList.remove('bg-current/10');
            } else if (field === 'timingStatus') {
               btn.className = btn.className.replace(/border-lime text-lime bg-lime\/10/, 'border-stroke text-muted hover:bg-surface2');
            }
        });
        
        const active = document.querySelector('[onclick*="saveContext"][onclick*="' + value + '"]');
        if (active) {
            if(field === 'temperature') {
                active.classList.remove('border-stroke', 'text-muted', 'hover:bg-surface2');
                active.classList.add('border-current', 'bg-current/10');
            } else if (field === 'timingStatus') {
                active.classList.remove('border-stroke', 'text-muted', 'hover:bg-surface2');
                active.classList.add('border-lime', 'text-lime', 'bg-lime/10');
            }
        }

        await operonFetch('/vault/' + id + '/context', {
            method: 'POST',
            body: JSON.stringify({ field, value })
        });
    } catch (err) {
        console.error('Error saving context:', err);
    }
}

async function saveContextNotes(id) {
    const el = document.getElementById('context-notes');
    if(!el) return;
    try {
        const value = el.value;
        await operonFetch('/vault/' + id + '/context', {
            method: 'POST',
            body: JSON.stringify({ field: 'notes', value })
        });
        // Visual feedback
        el.classList.add('border-lime/50');
        setTimeout(() => el.classList.remove('border-lime/50'), 1000);
    } catch (err) {
        console.error('Error saving notes:', err);
    }
}

// ── Tags ─────────────────────────────────────────────────
async function addTag(id) {
    const input = document.getElementById('new-tag');
    if(!input) return;
    const tag = input.value.trim();
    if (!tag) return;
    
    input.disabled = true;
    console.log('Adding tag:', tag);
    try {
        const res = await operonFetch('/vault/' + id + '/tags', {
            method: 'POST',
            body: JSON.stringify({ action: 'add', tag })
        });
        if (res && res.tags) renderTags(id, res.tags);
        input.value = '';
    } catch (err) {
        console.error('Error adding tag:', err);
    } finally {
        input.disabled = false;
        input.focus();
    }
}

async function removeTag(id, tag) {
    console.log('Removing tag:', tag);
    try {
        const res = await operonFetch('/vault/' + id + '/tags', {
            method: 'POST',
            body: JSON.stringify({ action: 'remove', tag })
        });
        if (res && res.tags) renderTags(id, res.tags);
    } catch (err) {
        console.error('Error removing tag:', err);
    }
}

function renderTags(id, tags) {
    const container = document.getElementById('tags-container');
    if(!container) return;
    container.innerHTML = tags.map(t =>
        '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-surface2 border border-stroke text-muted rounded-md text-xs font-medium">' +
            escHtml(t) +
            '<button onclick="removeTag(\'' + id + '\', \'' + t + '\')" class="text-subtle hover:text-red-500 transition-colors ml-1 leading-none">&times;</button>' +
        '</span>'
    ).join('');
}

// ── Helpers ───────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function hideAILoading() {
    try {
        const loaders = document.querySelectorAll('.ai-spinner');
        loaders.forEach(l => {
            const parent = l.closest('.p-8') || l.closest('.py-12');
            if (parent) parent.remove();
        });
    } catch (e) {
        console.error('Error hiding AI loader:', e);
    }
}

// ── Interactivity bindings for AI Buttons ─────────────────
setTimeout(() => {
    document.querySelectorAll('#btn-analyze, #btn-analyze-empty').forEach(btn => {
        btn.addEventListener('click', async () => {
            showAILoading(btn.parentElement, 'Analisando perfil com Inteligência Geral...');
            try {
                await operonFetch('/vault/' + LEAD_ID + '/analyze', { method: 'POST' });
                location.reload();
            } catch (e) {
                hideAILoading();
                alert('Erro na análise. Verifique o saldo do Nexus Token.');
            }
        });
    });

    const btn4d = document.getElementById('btn-4d');
    if (btn4d) {
        btn4d.addEventListener('click', async () => {
            const container = document.getElementById('operon4d-results');
            if(container) {
                container.classList.remove('hidden');
                showAILoading(container, 'Consultando malha fractal do Operon 4D...');
            }
            try {
                const data = await operonFetch('/vault/' + LEAD_ID + '/operon', { method: 'POST' });
                hideAILoading();
                if (data) render4D(data);
            } catch (e) {
                hideAILoading();
                alert('Falha na orquestração espacial 4D.');
            }
        });
    }

    // New deep intelligence logic
    window.runDeepIntelligence = async function(leadId, key) {
        const btn = document.getElementById('btn-run-intel-' + key);
        const contentDiv = document.getElementById('intel-content-' + key);
        if(!btn || !contentDiv) return;

        const originalHtml = btn.innerHTML;
        const originalClasses = btn.className;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] animate-spin">refresh</span> Processando';
        btn.className = 'h-8 px-4 rounded-pill bg-surface3 border border-stroke text-text text-[11px] font-bold transition-all flex items-center justify-center gap-1.5 opacity-80 cursor-not-allowed';

        try {
            const res = await operonFetch('/intelligence/run', {
                method: 'POST',
                body: JSON.stringify({ lead_id: leadId, type: key })
            });

            if(res && res.success) {
                // Update balance if returned
                if(res.tokenBalance !== undefined) {
                    const elBalance = document.getElementById('nexus-token-balance');
                    if(elBalance) elBalance.innerText = res.tokenBalance.toLocaleString('pt-BR');
                }

                // Format the content
                let contentHtml = '';
                const data = res.result;

                if (data.content) {
                    contentHtml = escHtml(data.content).replace(/\\\\n/g, '<br>');
                } else if (data.items && Array.isArray(data.items)) {
                    contentHtml = '<ul class="list-disc list-inside space-y-1.5">';
                    data.items.forEach(item => {
                        contentHtml += '<li>' + escHtml(item) + '</li>';
                    });
                    contentHtml += '</ul>';
                }

                contentDiv.innerHTML = contentHtml;

                // Update Button to "Atualizar" state
                btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">refresh</span> Atualizar';
                btn.className = 'h-8 px-4 rounded-pill bg-surface3 text-text hover:bg-surface border border-stroke text-[11px] font-bold transition-all flex items-center justify-center gap-1.5';
                
                // Add the checkmark to header
                const header = btn.closest('.bg-surface2').querySelector('h3').parentElement;
                if(!header.querySelector('.bg-lime\\/10')) {
                    header.insertAdjacentHTML('beforeend', '<div class="size-5 rounded-full bg-lime/10 flex items-center justify-center border border-lime/20" title="Concluído"><span class="material-symbols-outlined text-lime text-[12px]">check</span></div>');
                }

            } else {
                throw new Error(res?.error || 'Erro na inteligência.');
            }

        } catch (e) {
            console.error(e);
            alert('Falha ao processar inteligência: ' + e.message);
            // Revert state
            btn.innerHTML = originalHtml;
            btn.className = originalClasses;
        } finally {
            btn.disabled = false;
        }
    };
}, 100);

// ── 4D Renderer ───────────────────────────────────────────
function render4D(data) {
    const container = document.getElementById('operon4d-results');
    if(!container) return;
    
    if(data.error) {
         container.innerHTML = `<div class="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-500 font-medium text-sm border-l-4">\${escHtml(data.error)}</div>`;
         return;
    }

    container.innerHTML = '';

    const sections = [
        { key: 'diagnostico', label: 'Diagnóstico de Perda', icon: 'monitoring', color: 'text-red-500', bg: 'bg-red-500/10', border: 'border-red-500/20' },
        { key: 'potencial',   label: 'Potencial',            icon: 'trending_up', color: 'text-mint', bg: 'bg-mint/10', border: 'border-mint/20' },
        { key: 'autoridade',  label: 'Autoridade Local',     icon: 'verified',   color: 'text-amber-500', bg: 'bg-amber-500/10', border: 'border-amber-500/20' },
        { key: 'script',      label: 'Script',               icon: 'chat',       color: 'text-lime', bg: 'bg-lime/10', border: 'border-lime/20' },
    ];

    sections.forEach(({ key, label, icon, color, bg, border }) => {
        const d = data[key];
        if (!d) return;
        const isStr = typeof d === 'string';
        const card = document.createElement('div');
        card.className = 'bg-surface border border-stroke rounded-card p-6 shadow-soft';
        
        let contentHtml = isStr 
            ? '<p class="text-subtle text-sm whitespace-pre-line leading-relaxed">' + escHtml(d) + '</p>'
            : '<pre class="text-xs text-muted overflow-auto whitespace-pre-wrap p-4 bg-bg rounded-md border border-stroke">' + escHtml(JSON.stringify(d, null, 2)) + '</pre>';

        card.innerHTML = 
            '<h3 class="font-bold text-text flex items-center gap-2 mb-4">' +
                '<span class="size-8 rounded-full ' + bg + ' ' + border + ' flex items-center justify-center flex-shrink-0">' +
                   '<span class="material-symbols-outlined text-[16px] ' + color + '">' + icon + '</span>' +
                '</span>' +
                label +
            '</h3>' + 
            contentHtml;
            
        container.appendChild(card);
    });
}

// ── Calls & Transcriptions Logic ──────────────────────────
let mediaRecorder = null;
let audioChunks = [];
let recordingInterval = null;
let recordingSeconds = 0;
let currentRecordedBlob = null;
let currentRecordLeadId = null;

async function toggleRecording(leadId) {
    const btn = document.getElementById('btn-record-audio');
    const icon = document.getElementById('icon-record-audio');
    const text = document.getElementById('text-record-audio');
    const timeDisplay = document.getElementById('time-record-audio');

    if (mediaRecorder && mediaRecorder.state === 'recording') {
        // Parar gravação
        mediaRecorder.stop();
        clearInterval(recordingInterval);
        
        btn.classList.remove('bg-rose-500/20', 'animate-pulse');
        icon.innerText = 'mic';
        text.innerText = 'Gravar Áudio';
        timeDisplay.classList.add('hidden');
        
        // Hide initial buttons
        btn.classList.add('hidden');
        document.getElementById('btn-upload-audio').classList.add('hidden');

        // Show confirm panel
        const confirmDiv = document.getElementById('confirm-audio-div');
        const confirmTime = document.getElementById('confirm-audio-time');
        confirmDiv.classList.remove('hidden');
        confirmDiv.classList.add('flex');
        
        const m = String(Math.floor(recordingSeconds / 60)).padStart(2, '0');
        const s = String(recordingSeconds % 60).padStart(2, '0');
        confirmTime.innerText = m + ':' + s; // Using concatenation to avoid PHP Heredoc syntax error

        return; 
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        currentRecordLeadId = leadId;

        mediaRecorder.ondataavailable = e => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };

        mediaRecorder.onstop = () => {
            currentRecordedBlob = new Blob(audioChunks, { type: 'audio/webm' });
            stream.getTracks().forEach(track => track.stop());
        };

        // Iniciar gravação de fato
        mediaRecorder.start();
        recordingSeconds = 0;
        timeDisplay.innerText = '00:00';
        timeDisplay.classList.remove('hidden');
        
        btn.classList.add('bg-rose-500/20', 'animate-pulse');
        icon.innerText = 'stop_circle';
        text.innerText = 'Parar';

        recordingInterval = setInterval(() => {
            recordingSeconds++;
            const m = String(Math.floor(recordingSeconds / 60)).padStart(2, '0');
            const s = String(recordingSeconds % 60).padStart(2, '0');
            timeDisplay.innerText = m + ':' + s; // Using concatenation
        }, 1000);

    } catch (err) {
        console.error('Microfone negado ou indisponível', err);
        alert('Não foi possível acessar seu microfone. Detalhes: ' + err.message);
    }
}

function discardRecordedAudio() {
    currentRecordedBlob = null;
    currentRecordLeadId = null;
    
    // Hide confirm div
    const confirmDiv = document.getElementById('confirm-audio-div');
    confirmDiv.classList.remove('flex');
    confirmDiv.classList.add('hidden');
    
    // Show original buttons
    document.getElementById('btn-record-audio').classList.remove('hidden');
    document.getElementById('btn-upload-audio').classList.remove('hidden');
}

async function submitRecordedAudio() {
    if (!currentRecordedBlob || !currentRecordLeadId) return;
    
    const btnSubmit = document.getElementById('btn-submit-audio');
    const originalContent = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">refresh</span> Enviando...';
    btnSubmit.disabled = true;
    
    const formData = new FormData();
    formData.append('audio', new File([currentRecordedBlob], 'recording_' + Date.now() + '.webm', { type: 'audio/webm' }));
    formData.append('lead_id', currentRecordLeadId);

    try {
        const res = await fetch('/calls/upload', {
            method: 'POST',
            body: formData
        }).then(r => r.json());

        if (res && res.success) {
            location.reload();
        } else {
            alert('Falha ao enviar gravação: ' + (res.error || 'Erro desconhecido.'));
            btnSubmit.innerHTML = originalContent;
            btnSubmit.disabled = false;
        }
    } catch(e) {
        console.error(e);
        alert('Erro na conexão ao subir áudio gravado.');
        btnSubmit.innerHTML = originalContent;
        btnSubmit.disabled = false;
    }
}

function resetRecordingBtn() {
    const btn = document.getElementById('btn-record-audio');
    const icon = document.getElementById('icon-record-audio');
    const text = document.getElementById('text-record-audio');
    const timeDisplay = document.getElementById('time-record-audio');
    
    btn.classList.remove('bg-rose-500/20', 'animate-pulse');
    icon.classList.remove('animate-spin');
    icon.innerText = 'mic';
    text.innerText = 'Gravar Áudio';
    timeDisplay.classList.add('hidden');
    btn.disabled = false;
}

async function uploadCallAudio(input, leadId) {
    if(!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('audio', file);
    formData.append('lead_id', leadId);

    // Show loading state on label
    const label = input.closest('label');
    const originalLabelHtml = label.innerHTML;
    label.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">refresh</span> Enviando...';
    label.classList.add('opacity-50', 'pointer-events-none');

    try {
        const res = await fetch('/calls/upload', {
            method: 'POST',
            body: formData
            // Note: Não passe Content-Type aqui para que o fetch monte o multipart border
        }).then(r => r.json());

        if (res && res.success) {
            // Recarrega a página para exibir o novo card em status 'uploading/stored'
            location.reload();
        } else {
            alert('Falha no upload: ' + (res.error || 'Erro desconhecido.'));
            label.innerHTML = originalLabelHtml;
            label.classList.remove('opacity-50', 'pointer-events-none');
            input.value = ''; // reset input
        }
    } catch(e) {
        console.error(e);
        alert('Erro ao enviar áudio.');
        label.innerHTML = originalLabelHtml;
        label.classList.remove('opacity-50', 'pointer-events-none');
        input.value = '';
    }
}

function toggleCallDetails(callId) {
    const body = document.getElementById('call-body-' + callId);
    const icon = document.getElementById('call-icon-' + callId);
    if(body && icon) {
        body.classList.toggle('hidden');
        if(body.classList.contains('hidden')) {
            icon.style.transform = 'rotate(0deg)';
        } else {
            icon.style.transform = 'rotate(180deg)';
        }
    }
}

// ── Background Polling for Ongoing Calls ──────────────────
document.addEventListener('DOMContentLoaded', () => {
    const ongoingCalls = [];
    document.querySelectorAll('.call-item').forEach(el => {
        const status = el.getAttribute('data-status');
        if (['uploading', 'stored', 'transcribing', 'analyzing'].includes(status)) {
            ongoingCalls.push(el.getAttribute('data-call-id'));
        }
    });

    if (ongoingCalls.length > 0) {
        const pollInterval = setInterval(async () => {
            try {
                const res = await fetch('/calls/status?ids=' + ongoingCalls.join(',')).then(r => r.json());
                if (res && res.success && res.calls) {
                    let allDone = true;
                    // Seletor básico para forçar reload caso um termine assim não precisamos codar update dom complexo agr
                    for (const id of ongoingCalls) {
                        const call = res.calls[id];
                        if(call && ['completed', 'failed'].includes(call.status)) {
                            // Encontrou um que terminou. Reload da página para exibir os cards completos.
                            location.reload();
                            return;
                        } else if (call) {
                            allDone = false;
                        }
                    }
                    if(allDone) clearInterval(pollInterval);
                }
            } catch(e) {
                console.warn('Falha no polling', e);
            }
        }, 5000); // Poll a cada 5 segundos
    }
});


// ── Timeline Tabs ─────────────────────────────────────────
function switchTimelineTab(tab) {
    const btnNote = document.getElementById('tab-btn-note');
    const btnFile = document.getElementById('tab-btn-file');
    const formNote = document.getElementById('form-tab-note');
    const formFile = document.getElementById('form-tab-file');

    if (tab === 'note') {
        btnNote.className = "flex-1 py-2 text-xs font-bold text-lime bg-lime/10 border-b-2 border-lime transition-colors";
        btnFile.className = "flex-1 py-2 text-xs font-bold text-muted hover:text-text hover:bg-surface3 border-b-2 border-transparent transition-colors";
        formNote.classList.remove('hidden');
        formNote.classList.add('block');
        formFile.classList.add('hidden');
        formFile.classList.remove('block');
    } else {
        btnFile.className = "flex-1 py-2 text-xs font-bold text-white bg-white/10 border-b-2 border-white transition-colors";
        btnNote.className = "flex-1 py-2 text-xs font-bold text-muted hover:text-text hover:bg-surface3 border-b-2 border-transparent transition-colors";
        formFile.classList.remove('hidden');
        formFile.classList.add('block');
        formNote.classList.add('hidden');
        formNote.classList.remove('block');
    }
}

function updateFileName(input) {
    const display = document.getElementById('file-upload-name');
    if (input.files && input.files.length > 0) {
        display.textContent = input.files[0].name;
        display.classList.remove('hidden');
    } else {
        display.classList.add('hidden');
        display.textContent = '';
    }
}

// ── WhatsApp Intelligence from Lead page ───────────────────
async function waIntel(convId, endpoint, btn) {
    const origHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[13px] animate-spin">progress_activity</span>';

    const resultDiv = document.getElementById('wa-result-' + convId);

    try {
        const res = await fetch('/whatsapp/conversation/' + convId + '/' + endpoint, {
            method: 'POST',
            body: new URLSearchParams({ '_csrf': getCsrfToken() })
        });
        const text = await res.text();
        const j = text.indexOf('{');
        const data = JSON.parse(j > 0 ? text.substring(j) : text);

        if (!data.success) {
            showWaResult(resultDiv, '<span class="text-red-400">' + (data.error || 'Erro na análise.') + '</span>');
        } else if (endpoint === 'summary') {
            const s = data.summary || data.analysis || {};
            let html = '<p class="text-xs text-text font-medium mb-2">' + (s.summary || 'Resumo gerado.') + '</p>';
            if (s.pains && s.pains.length) {
                html += '<div class="flex flex-wrap gap-1 mt-1">';
                s.pains.forEach(p => html += '<span class="text-[10px] px-2 py-0.5 rounded-pill bg-red-400/10 text-red-400 border border-red-400/20">' + p + '</span>');
                html += '</div>';
            }
            showWaResult(resultDiv, html);
            // Update summary text
            const summaryEl = document.getElementById('wa-summary-text-' + convId);
            if (summaryEl && s.summary) summaryEl.textContent = s.summary.substring(0, 200);
        } else if (endpoint === 'interest-score') {
            const s = data.interest_score || data.analysis || {};
            const score = s.interest_score ?? s.score ?? '?';
            const cls = score >= 70 ? 'text-lime' : (score >= 40 ? 'text-amber-400' : 'text-red-400');
            showWaResult(resultDiv, '<span class="text-sm font-bold ' + cls + '">Score: ' + score + '/100</span> <span class="text-xs text-muted ml-2">' + (s.score_explanation || '') + '</span>');
            // Update score badge
            const badge = document.getElementById('wa-score-badge-' + convId);
            if (badge) badge.innerHTML = '<span class="text-[10px] font-bold text-muted uppercase tracking-wider">Score</span><span class="text-lg font-black ' + cls + '">' + score + '</span>';
        } else if (endpoint === 'strategic') {
            const s = data.strategic || data.analysis || {};
            let html = '<p class="text-xs font-bold text-text mb-1">Análise Estratégica</p>';
            if (s.loss_risk) html += '<span class="text-[10px] px-2 py-0.5 rounded-pill bg-red-400/10 text-red-400 border border-red-400/20 mr-1">Risco: ' + (s.loss_risk.level || '?') + '</span>';
            if (s.interest_level) html += '<span class="text-[10px] px-2 py-0.5 rounded-pill bg-lime/10 text-lime border border-lime/20">Interesse: ' + s.interest_level + '</span>';
            if (s.recommended_actions && s.recommended_actions.length) {
                html += '<ul class="mt-2 space-y-1">';
                s.recommended_actions.slice(0, 3).forEach(a => html += '<li class="text-[11px] text-subtle flex items-start gap-1"><span class="material-symbols-outlined text-[12px] text-lime mt-0.5">arrow_right</span>' + a + '</li>');
                html += '</ul>';
            }
            showWaResult(resultDiv, html);
        } else if (endpoint === 'next-message') {
            const s = data.next_message || data.analysis || {};
            const msg = s.message || 'Mensagem gerada.';
            showWaResult(resultDiv, '<div class="bg-surface border border-stroke rounded-lg p-3"><p class="text-xs text-text whitespace-pre-wrap">' + msg.replace(/</g, '&lt;') + '</p><button onclick="navigator.clipboard.writeText(this.dataset.msg);this.textContent=\'Copiado!\';setTimeout(()=>this.textContent=\'Copiar\',1500)" data-msg="' + msg.replace(/"/g, '&quot;') + '" class="mt-2 text-[10px] font-bold text-mint hover:underline">Copiar</button></div>');
        }
    } catch (err) {
        showWaResult(resultDiv, '<span class="text-red-400 text-xs">Erro: ' + err.message + '</span>');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHTML;
    }
}

function showWaResult(el, html) {
    if (!el) return;
    el.innerHTML = html;
    el.classList.remove('hidden');
}
</script>
JS;
?>
