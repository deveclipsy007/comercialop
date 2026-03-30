<?php
/**
 * Email Module — Dashboard Principal
 * Hub central: contas, templates, campanhas, logs e envio.
 */
$pageTitle = 'E-mail';
$accounts  = $accounts ?? [];
$templates = $templates ?? [];
$campaigns = $campaigns ?? [];
$stats     = $stats ?? [];
$recentLogs = $recentLogs ?? [];
$categories = $categories ?? [];
$csrfToken = csrf_token();
$hasAccount = !empty($accounts);
$activeAccount = null;
foreach ($accounts as $acc) {
    if ($acc['is_active'] && $acc['is_verified']) { $activeAccount = $acc; break; }
}
?>

<div class="flex flex-col h-[calc(100vh-72px)] overflow-hidden">

    <!-- Sub-nav -->
    <div class="flex items-center justify-between border-b border-stroke px-6 py-3 flex-shrink-0 bg-bg">
        <div class="flex items-center gap-2">
            <a href="/emails" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-lime text-bg text-xs font-bold">
                <span class="material-symbols-outlined text-sm">dashboard</span> Visão Geral
            </a>
            <a href="/emails/templates" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface border border-stroke text-xs text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">draft</span> Templates
            </a>
            <a href="/emails/campaigns" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface border border-stroke text-xs text-muted hover:text-text hover:border-white/10 transition-all">
                <span class="material-symbols-outlined text-sm">campaign</span> Campanhas
            </a>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($activeAccount): ?>
            <div class="flex items-center gap-2 h-9 px-4 rounded-pill bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400 font-medium">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <?= e($activeAccount['email_address']) ?>
            </div>
            <?php endif; ?>
            <button onclick="openConnectModal()" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-lime hover:border-lime/30 transition-all">
                <span class="material-symbols-outlined text-sm"><?= $hasAccount ? 'settings' : 'add' ?></span>
                <?= $hasAccount ? 'Gerenciar Conta' : 'Conectar E-mail' ?>
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php
                $statCards = [
                    ['label' => 'Enviados', 'value' => $stats['sent'] ?? 0, 'icon' => 'send', 'color' => 'lime'],
                    ['label' => 'Abertos', 'value' => ($stats['open_rate'] ?? 0) . '%', 'icon' => 'visibility', 'color' => 'emerald-400'],
                    ['label' => 'Clicados', 'value' => ($stats['click_rate'] ?? 0) . '%', 'icon' => 'ads_click', 'color' => 'blue-400'],
                    ['label' => 'Na fila', 'value' => $stats['queued'] ?? 0, 'icon' => 'schedule', 'color' => 'yellow-400'],
                ];
                foreach ($statCards as $sc):
                ?>
                <div class="bg-surface border border-stroke rounded-2xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-<?= $sc['color'] ?>/10 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-<?= $sc['color'] ?> text-lg"><?= $sc['icon'] ?></span>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-text"><?= $sc['value'] ?></p>
                        <p class="text-xs text-muted"><?= $sc['label'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions + Account Status -->
            <?php if (!$hasAccount): ?>
            <div class="bg-surface border border-stroke rounded-2xl p-8 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-lime/10 flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl text-lime">mail_lock</span>
                </div>
                <h3 class="text-lg font-bold text-text mb-2">Conecte seu e-mail para começar</h3>
                <p class="text-sm text-muted mb-6 max-w-md mx-auto">Configure sua conta SMTP para enviar e-mails diretamente pela plataforma. Suporte a Gmail, Outlook, e qualquer servidor SMTP.</p>
                <button onclick="openConnectModal()" class="h-11 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all shadow-glow">
                    <span class="material-symbols-outlined text-sm align-middle mr-1">add</span> Conectar E-mail
                </button>
            </div>
            <?php else: ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Quick Send -->
                <div class="lg:col-span-2 bg-surface border border-stroke rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-lime text-base">edit</span>
                        Envio Rápido
                    </h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <input type="email" id="quickTo" placeholder="E-mail do destinatário" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                            <input type="text" id="quickToName" placeholder="Nome (opcional)" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                        </div>
                        <input type="text" id="quickSubject" placeholder="Assunto" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                        <textarea id="quickBody" rows="5" placeholder="Escreva seu e-mail aqui... (suporta HTML)" class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all resize-y"></textarea>
                        <div class="flex items-center justify-between">
                            <button onclick="aiGenerateQuick()" class="flex items-center gap-2 h-9 px-4 rounded-pill bg-surface2 border border-stroke text-xs text-muted hover:text-lime hover:border-lime/30 transition-all">
                                <span class="material-symbols-outlined text-sm">auto_awesome</span> Gerar com IA
                            </button>
                            <button onclick="sendQuickEmail()" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">
                                <span class="material-symbols-outlined text-sm">send</span> Enviar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Account Health -->
                <div class="bg-surface border border-stroke rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-text mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-400 text-base">health_and_safety</span>
                        Saúde da Conta
                    </h3>
                    <?php if ($activeAccount): ?>
                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-muted">Reputação</span>
                                <span class="text-xs font-bold text-<?= ($activeAccount['reputation_score'] ?? 100) >= 80 ? 'emerald-400' : (($activeAccount['reputation_score'] ?? 100) >= 50 ? 'yellow-400' : 'red-400') ?>"><?= (int)($activeAccount['reputation_score'] ?? 100) ?>%</span>
                            </div>
                            <div class="w-full h-2 bg-surface3 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-400 rounded-full" style="width: <?= (int)($activeAccount['reputation_score'] ?? 100) ?>%"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted">Enviados hoje</span>
                                <span class="text-text font-medium"><?= (int)($activeAccount['sent_today'] ?? 0) ?> / <?= (int)($activeAccount['daily_limit'] ?? 50) ?></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted">Warmup</span>
                                <span class="text-text font-medium"><?= $activeAccount['warmup_enabled'] ? 'Dia ' . ($activeAccount['warmup_day'] ?? 0) . '/30' : 'Desativado' ?></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted">Delay entre envios</span>
                                <span class="text-text font-medium"><?= (int)($activeAccount['delay_seconds'] ?? 30) ?>s</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-stroke">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="material-symbols-outlined text-sm text-emerald-400">verified</span>
                                <span class="text-muted"><?= e($activeAccount['email_address']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-surface border border-stroke rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-stroke">
                    <h3 class="text-sm font-bold text-text flex items-center gap-2">
                        <span class="material-symbols-outlined text-base text-muted">history</span>
                        Envios Recentes
                    </h3>
                    <span class="text-xs text-muted"><?= count($recentLogs) ?> registro(s)</span>
                </div>
                <?php if (empty($recentLogs)): ?>
                <div class="p-8 text-center text-muted text-sm">
                    <span class="material-symbols-outlined text-2xl mb-2 block">inbox</span>
                    Nenhum e-mail enviado ainda.
                </div>
                <?php else: ?>
                <div class="divide-y divide-stroke max-h-[400px] overflow-y-auto">
                    <?php foreach ($recentLogs as $log): ?>
                    <div class="flex items-center gap-4 px-5 py-3 hover:bg-surface2/50 transition-colors">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                            <?php
                            $statusColors = ['sent' => 'bg-emerald-500/10 text-emerald-400', 'queued' => 'bg-yellow-500/10 text-yellow-400', 'failed' => 'bg-red-500/10 text-red-400', 'sending' => 'bg-blue-500/10 text-blue-400', 'bounced' => 'bg-red-500/10 text-red-400'];
                            echo $statusColors[$log['status']] ?? 'bg-surface3 text-muted';
                            ?>">
                            <span class="material-symbols-outlined text-sm">
                                <?php
                                $statusIcons = ['sent' => 'check_circle', 'queued' => 'schedule', 'failed' => 'error', 'sending' => 'sync', 'bounced' => 'cancel'];
                                echo $statusIcons[$log['status']] ?? 'mail';
                                ?>
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-text truncate"><?= e($log['to_email']) ?></span>
                                <?php if ($log['lead_name'] ?? null): ?>
                                <span class="text-xs text-subtle">(<?= e($log['lead_name']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-muted truncate"><?= e($log['subject'] ?: '(sem assunto)') ?></p>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-subtle flex-shrink-0">
                            <?php if ($log['opened_at']): ?>
                            <span class="flex items-center gap-1 text-emerald-400" title="Aberto em <?= e($log['opened_at']) ?>">
                                <span class="material-symbols-outlined text-xs">visibility</span>
                            </span>
                            <?php endif; ?>
                            <?php if ($log['clicked_at']): ?>
                            <span class="flex items-center gap-1 text-blue-400" title="Clicado em <?= e($log['clicked_at']) ?>">
                                <span class="material-symbols-outlined text-xs">ads_click</span>
                            </span>
                            <?php endif; ?>
                            <span><?= e($log['created_at'] ? date('d/m H:i', strtotime($log['created_at'])) : '') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="/emails/templates" class="bg-surface2 border border-stroke hover:border-lime/30 rounded-2xl p-5 group transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="material-symbols-outlined text-lg text-muted group-hover:text-lime transition-colors">draft</span>
                        <span class="text-sm font-bold text-text"><?= count($templates) ?> Templates</span>
                    </div>
                    <p class="text-xs text-subtle">Modelos reutilizáveis de e-mail para prospecção, follow-up e propostas.</p>
                </a>
                <a href="/emails/campaigns" class="bg-surface2 border border-stroke hover:border-lime/30 rounded-2xl p-5 group transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="material-symbols-outlined text-lg text-muted group-hover:text-lime transition-colors">campaign</span>
                        <span class="text-sm font-bold text-text"><?= count($campaigns) ?> Campanhas</span>
                    </div>
                    <p class="text-xs text-subtle">Campanhas de e-mail com sequências, follow-ups e métricas de envio.</p>
                </a>
                <div class="bg-surface2 border border-stroke rounded-2xl p-5">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="material-symbols-outlined text-lg text-muted">shield</span>
                        <span class="text-sm font-bold text-text">Proteção Ativa</span>
                    </div>
                    <p class="text-xs text-subtle">Warmup progressivo, limites por hora/dia, delay entre envios e monitoramento de reputação.</p>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Conectar E-mail -->
<div id="connectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-xl mx-4 shadow-soft max-h-[90vh] overflow-y-auto" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke sticky top-0 bg-surface z-10">
            <h3 class="text-base font-bold text-text">Conectar Conta de E-mail</h3>
            <button onclick="closeModal('connectModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <!-- Presets -->
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-2">Configuração Rápida</label>
                <div class="grid grid-cols-3 gap-2">
                    <button onclick="fillPreset('gmail')" class="p-3 bg-surface2 border border-stroke rounded-xl text-center hover:border-lime/30 transition-all">
                        <span class="text-xs font-bold text-text">Gmail</span>
                        <p class="text-[10px] text-subtle mt-0.5">App Password</p>
                    </button>
                    <button onclick="fillPreset('outlook')" class="p-3 bg-surface2 border border-stroke rounded-xl text-center hover:border-lime/30 transition-all">
                        <span class="text-xs font-bold text-text">Outlook</span>
                        <p class="text-[10px] text-subtle mt-0.5">SMTP</p>
                    </button>
                    <button onclick="fillPreset('custom')" class="p-3 bg-surface2 border border-stroke rounded-xl text-center hover:border-lime/30 transition-all">
                        <span class="text-xs font-bold text-text">Outro</span>
                        <p class="text-[10px] text-subtle mt-0.5">SMTP Manual</p>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Endereço de E-mail</label>
                    <input type="email" id="accEmail" placeholder="seuemail@empresa.com" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                </div>
                <div class="col-span-2">
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Nome de Exibição</label>
                    <input type="text" id="accDisplayName" placeholder="Seu Nome" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Servidor SMTP</label>
                    <input type="text" id="accSmtpHost" placeholder="smtp.gmail.com" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Porta</label>
                        <input type="number" id="accSmtpPort" value="587" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Criptografia</label>
                        <select id="accSmtpEncryption" class="w-full h-10 px-3 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">Nenhuma</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Usuário SMTP</label>
                    <input type="text" id="accSmtpUser" placeholder="seuemail@empresa.com" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Senha / App Password</label>
                    <input type="password" id="accSmtpPass" placeholder="********" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none transition-all">
                </div>
            </div>

            <!-- Safety Limits -->
            <div class="pt-2 border-t border-stroke">
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-2">Limites de Segurança</label>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] text-subtle mb-1">Máx/dia</label>
                        <input type="number" id="accDailyLimit" value="50" class="w-full h-9 px-3 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] text-subtle mb-1">Máx/hora</label>
                        <input type="number" id="accHourlyLimit" value="15" class="w-full h-9 px-3 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] text-subtle mb-1">Delay (seg)</label>
                        <input type="number" id="accDelay" value="30" class="w-full h-9 px-3 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                    </div>
                </div>
            </div>

            <div class="bg-surface2 border border-stroke rounded-xl p-3 text-xs text-muted">
                <span class="material-symbols-outlined text-xs text-yellow-400 align-middle mr-1">info</span>
                <strong>Gmail:</strong> Use uma <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-lime underline">App Password</a>. Ative a verificação em 2 etapas primeiro.
            </div>

            <div class="flex items-center justify-between pt-2">
                <button onclick="testConnection()" id="testBtn" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text transition-all">
                    <span class="material-symbols-outlined text-sm">cable</span> Testar Conexão
                </button>
                <button onclick="saveAccount()" id="saveAccountBtn" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">
                    <span class="material-symbols-outlined text-sm">check</span> Conectar
                </button>
            </div>
            <div id="connectResult" class="hidden text-sm rounded-xl p-3 border"></div>
        </div>
    </div>
</div>

<!-- Modal: AI Generate -->
<div id="aiModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-surface border border-stroke rounded-2xl w-full max-w-md mx-4 shadow-soft" style="animation: fadeInUp 0.3s ease-out">
        <div class="flex items-center justify-between px-6 py-4 border-b border-stroke">
            <h3 class="text-base font-bold text-text">Gerar E-mail com IA</h3>
            <button onclick="closeModal('aiModal')" class="p-1.5 rounded-lg hover:bg-surface3 text-muted hover:text-text transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Propósito</label>
                <select id="aiPurpose" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                    <option value="prospecção">Prospecção</option>
                    <option value="follow-up">Follow-up</option>
                    <option value="proposta comercial">Proposta Comercial</option>
                    <option value="reativação">Reativação de Lead</option>
                    <option value="agradecimento">Agradecimento</option>
                    <option value="convite para reunião">Convite para Reunião</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Tom</label>
                <select id="aiTone" class="w-full h-10 px-4 bg-surface2 border border-stroke rounded-xl text-sm text-text focus:border-lime/40 outline-none">
                    <option value="profissional">Profissional</option>
                    <option value="consultivo">Consultivo</option>
                    <option value="direto">Direto e Objetivo</option>
                    <option value="amigável">Amigável</option>
                    <option value="sofisticado">Sofisticado</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-1.5">Contexto Adicional (opcional)</label>
                <textarea id="aiContext" rows="3" placeholder="Ex: Lead demonstrou interesse no serviço X..." class="w-full px-4 py-3 bg-surface2 border border-stroke rounded-xl text-sm text-text placeholder-subtle focus:border-lime/40 outline-none resize-none"></textarea>
            </div>
            <button onclick="generateEmailAI()" id="aiGenerateBtn" class="w-full h-10 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">auto_awesome</span> Gerar E-mail
            </button>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrfToken ?>';

function openModal(id) {
    const m = document.getElementById(id);
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeModal(id) {
    const m = document.getElementById(id);
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function openConnectModal() { openModal('connectModal'); }

function fillPreset(type) {
    const presets = {
        gmail: { host: 'smtp.gmail.com', port: 587, enc: 'tls' },
        outlook: { host: 'smtp.office365.com', port: 587, enc: 'tls' },
        custom: { host: '', port: 587, enc: 'tls' },
    };
    const p = presets[type] || presets.custom;
    document.getElementById('accSmtpHost').value = p.host;
    document.getElementById('accSmtpPort').value = p.port;
    document.getElementById('accSmtpEncryption').value = p.enc;
}

async function testConnection() {
    const btn = document.getElementById('testBtn');
    const result = document.getElementById('connectResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Testando...';

    const body = buildAccountBody();
    try {
        const res = await fetch('/emails/account/test', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        const data = await res.json();
        result.classList.remove('hidden');
        if (data.ok) {
            result.className = 'text-sm rounded-xl p-3 border bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
            result.textContent = 'Conexão SMTP verificada com sucesso!';
        } else {
            result.className = 'text-sm rounded-xl p-3 border bg-red-500/10 border-red-500/20 text-red-400';
            result.textContent = data.error || 'Falha na conexão.';
        }
    } catch (err) {
        result.classList.remove('hidden');
        result.className = 'text-sm rounded-xl p-3 border bg-red-500/10 border-red-500/20 text-red-400';
        result.textContent = 'Erro de rede: ' + err.message;
    }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm">cable</span> Testar Conexão';
}

async function saveAccount() {
    const btn = document.getElementById('saveAccountBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Conectando...';

    const body = buildAccountBody();
    try {
        const res = await fetch('/emails/account', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        const data = await res.json();
        if (data.ok) {
            showToast('Conta conectada com sucesso!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.error || 'Erro ao conectar.', 'error');
        }
    } catch (err) {
        showToast('Erro: ' + err.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm">check</span> Conectar';
}

function buildAccountBody() {
    return '_csrf=' + encodeURIComponent(CSRF) +
        '&email_address=' + encodeURIComponent(document.getElementById('accEmail').value) +
        '&display_name=' + encodeURIComponent(document.getElementById('accDisplayName').value) +
        '&smtp_host=' + encodeURIComponent(document.getElementById('accSmtpHost').value) +
        '&smtp_port=' + encodeURIComponent(document.getElementById('accSmtpPort').value) +
        '&smtp_encryption=' + encodeURIComponent(document.getElementById('accSmtpEncryption').value) +
        '&smtp_username=' + encodeURIComponent(document.getElementById('accSmtpUser').value) +
        '&smtp_password=' + encodeURIComponent(document.getElementById('accSmtpPass').value) +
        '&daily_limit=' + encodeURIComponent(document.getElementById('accDailyLimit').value) +
        '&hourly_limit=' + encodeURIComponent(document.getElementById('accHourlyLimit').value) +
        '&delay_seconds=' + encodeURIComponent(document.getElementById('accDelay').value);
}

function aiGenerateQuick() {
    openModal('aiModal');
}

async function generateEmailAI() {
    const btn = document.getElementById('aiGenerateBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Gerando...';

    try {
        const res = await fetch('/emails/ai/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                _csrf: CSRF,
                purpose: document.getElementById('aiPurpose').value,
                tone: document.getElementById('aiTone').value,
                context: document.getElementById('aiContext').value,
            }),
        });
        const data = await res.json();
        if (data.ok || data.subject) {
            document.getElementById('quickSubject').value = data.subject || '';
            document.getElementById('quickBody').value = data.body || '';
            closeModal('aiModal');
            showToast('E-mail gerado com sucesso!', 'success');
        } else {
            showToast(data.error || 'Erro ao gerar.', 'error');
        }
    } catch (err) {
        showToast('Erro: ' + err.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm">auto_awesome</span> Gerar E-mail';
}

async function sendQuickEmail() {
    const toEmail = document.getElementById('quickTo').value.trim();
    const subject = document.getElementById('quickSubject').value.trim();
    const body = document.getElementById('quickBody').value.trim();

    if (!toEmail) { showToast('Informe o e-mail do destinatário.', 'error'); return; }
    if (!subject) { showToast('Informe o assunto.', 'error'); return; }
    if (!body) { showToast('Escreva o corpo do e-mail.', 'error'); return; }

    try {
        const res = await fetch('/emails/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                _csrf: CSRF,
                to_email: toEmail,
                to_name: document.getElementById('quickToName').value.trim(),
                subject: subject,
                body: body,
            }),
        });
        const data = await res.json();
        if (data.ok) {
            showToast('E-mail enviado com sucesso!', 'success');
            document.getElementById('quickTo').value = '';
            document.getElementById('quickToName').value = '';
            document.getElementById('quickSubject').value = '';
            document.getElementById('quickBody').value = '';
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.error || 'Falha ao enviar.', 'error');
        }
    } catch (err) {
        showToast('Erro: ' + err.message, 'error');
    }
}

function showToast(msg, type = 'success') {
    const existing = document.querySelector('.toast-msg');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast-msg fixed top-4 right-4 z-[200] flex items-center gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-lg backdrop-blur-sm animate-fadeInUp ' +
        (type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400');
    toast.innerHTML = '<span class="material-symbols-outlined text-lg">' + (type === 'success' ? 'check_circle' : 'error') + '</span> ' + msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// Close modals on backdrop click
['connectModal', 'aiModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>
