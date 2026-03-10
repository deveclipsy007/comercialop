<!DOCTYPE html>
<html class="dark" lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($pageTitle ?? 'Operon Intelligence') ?> — Operon</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

    <!-- Operon CSS -->
    <link rel="stylesheet" href="/css/app.css">

    <!-- Tailwind Config -->
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary":           "#E11D48",
                        "operon-charcoal":   "#19171A",
                        "operon-teal":       "#0A1D2A",
                        "operon-energy":     "#18C29C",
                        "background-dark":   "#19171A",
                        "background-light":  "#F8F6F6",
                        "brand-surface":     "#232026",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"],
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg":      "1rem",
                        "xl":      "1.5rem",
                        "2xl":     "1.75rem",
                        "full":    "9999px",
                    },
                    boxShadow: {
                        "energy": "0 0 20px rgba(24,194,156,0.3)",
                        "energy-sm": "0 0 10px rgba(24,194,156,0.2)",
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-background-dark font-display text-slate-100 antialiased">

<!-- Flash Messages -->
<?php
$flashError   = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashWarning = \App\Core\Session::getFlash('warning');
$flashMsg     = $flashError ?: $flashSuccess ?: $flashWarning ?: null;
$flashType    = $flashError ? 'error' : ($flashSuccess ? 'success' : ($flashWarning ? 'warning' : ''));
?>
<?php if ($flashMsg): ?>
<div class="fixed top-4 right-4 z-[200] flash-message">
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-lg backdrop-blur-sm animate-fadeInUp
        <?= $flashType === 'success' ? 'flash-success' : ($flashType === 'error' ? 'flash-error' : 'flash-warning') ?>">
        <span class="material-symbols-outlined text-lg">
            <?= $flashType === 'success' ? 'check_circle' : ($flashType === 'error' ? 'error' : 'warning') ?>
        </span>
        <?= e($flashMsg) ?>
    </div>
</div>
<?php endif; ?>

<div class="relative flex h-screen w-full overflow-hidden">

    <!-- ── Sidebar ─────────────────────────────────────────── -->
    <aside id="sidebar" class="flex h-full w-64 flex-col border-r border-slate-800 bg-operon-teal p-4 transition-all duration-300 relative z-10 flex-shrink-0">

        <!-- Logo -->
        <div class="px-4 py-5 mb-2">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-operon-energy/20 rounded-full flex items-center justify-center text-operon-energy border border-operon-energy/30 flex-shrink-0">
                    <span class="material-symbols-outlined text-lg">security</span>
                </div>
                <div class="sidebar-label">
                    <h1 class="text-sm font-black tracking-wider text-operon-energy leading-none sidebar-logo-text">OPERON AGENTS</h1>
                    <p class="text-[10px] font-bold tracking-[0.2em] text-slate-400 mt-0.5 uppercase sidebar-logo-text">INTELLIGENCE</p>
                </div>
            </div>
        </div>

        <!-- Token Bar -->
        <?php
        $tb = $tokenBalance ?? null;
        $tbUsed  = $tb['used'] ?? 0;
        $tbLimit = $tb['limit'] ?? 100;
        $tbPct   = $tbLimit > 0 ? min(100, round(($tbUsed / $tbLimit) * 100)) : 0;
        $tbClass = $tbPct >= 90 ? 'critical' : ($tbPct >= 70 ? 'warning' : '');
        ?>
        <div class="mx-2 mb-4 px-3 py-2.5 rounded-xl bg-white/5 border border-white/8 sidebar-label">
            <div class="flex justify-between items-center mb-1.5">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tokens IA</span>
                <span id="tokenLabel" class="text-[10px] font-bold <?= $tbPct >= 90 ? 'text-red-400' : ($tbPct >= 70 ? 'text-yellow-400' : 'text-operon-energy') ?>"><?= $tbUsed ?>/<?= $tbLimit ?></span>
            </div>
            <div class="w-full h-1.5 bg-white/10 rounded-full overflow-hidden">
                <div id="tokenBar" class="h-full rounded-full token-bar-fill <?= $tbClass ?>" style="width: <?= $tbPct ?>%"></div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex flex-col gap-0.5 grow px-2 overflow-y-auto">
            <?php
            $navItems = [
                ['icon' => 'grid_view',     'label' => 'Nexus',    'path' => '/',         'key' => 'dashboard'],
                ['icon' => 'view_kanban',   'label' => 'Vault',    'path' => '/vault',    'key' => 'vault'],
                ['icon' => 'map',           'label' => 'Atlas',    'path' => '/atlas',    'key' => 'atlas'],
                ['icon' => 'radar',         'label' => 'Hunter',   'path' => '/hunter',   'key' => 'hunter'],
                ['icon' => 'upload_file',   'label' => 'Genesis',  'path' => '/genesis',  'key' => 'genesis'],
                ['icon' => 'calendar_today','label' => 'Agenda',   'path' => '/agenda',   'key' => 'agenda'],
                ['icon' => 'event_repeat',  'label' => 'Follow-up','path' => '/follow-up','key' => 'followup'],
                ['icon' => 'track_changes', 'label' => 'SPIN Hub', 'path' => '/spin',     'key' => 'spin'],
            ];
            $active = $active ?? '';
            ?>

            <div class="text-[10px] font-bold text-slate-500 px-3 uppercase tracking-[0.15em] mb-2 mt-2 sidebar-label">PROTOCOLOS</div>

            <?php foreach ($navItems as $item): ?>
            <a href="<?= $item['path'] ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-150 group sidebar-nav-item
                      <?= $active === $item['key']
                          ? 'bg-primary text-white shadow-lg shadow-primary/20'
                          : 'text-slate-400 hover:text-white hover:bg-white/8' ?>">
                <span class="material-symbols-outlined text-xl <?= $active === $item['key'] ? 'fill-1' : '' ?> flex-shrink-0"><?= $item['icon'] ?></span>
                <span class="text-sm font-<?= $active === $item['key'] ? 'bold' : 'medium' ?> sidebar-label"><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>

            <!-- Operon AI Copilot Button -->
            <div class="mt-6 px-1 sidebar-label">
                <button onclick="openModal('copilotModal')"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-operon-energy text-xl">smart_toy</span>
                        <span class="text-xs font-bold tracking-wider text-white uppercase">OPERON AI</span>
                    </div>
                    <span class="size-2 bg-operon-energy rounded-full animate-pulse"></span>
                </button>
            </div>

            <!-- Admin Link -->
            <?php if ((\App\Core\Session::get('role') ?? '') === 'admin'): ?>
            <div class="mt-2 px-1">
                <a href="/admin" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all
                    <?= $active === 'admin' ? 'bg-primary/20 text-primary' : 'text-slate-500 hover:text-white hover:bg-white/8' ?>">
                    <span class="material-symbols-outlined text-xl flex-shrink-0">shield</span>
                    <span class="text-sm font-medium sidebar-label">Admin</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Footer: Governance Card + User -->
        <div class="mt-auto flex flex-col gap-3 pt-4 border-t border-white/8">

            <!-- Governance Card -->
            <div class="rounded-2xl bg-white/5 border border-white/10 p-3 sidebar-label">
                <div class="flex justify-between items-start mb-1">
                    <p class="text-[11px] font-bold text-white">Cérebro de Governança</p>
                    <span class="material-symbols-outlined text-slate-500 text-base">psychology</span>
                </div>
                <p class="text-[9px] text-slate-400 leading-relaxed mb-3">Sistema ativo. Pronto para análise profunda de mercado.</p>
                <a href="/vault" class="block w-full py-2 bg-primary rounded-xl text-[10px] font-bold text-white text-center hover:brightness-110 transition-all">
                    Executar Análise
                </a>
            </div>

            <!-- User Profile -->
            <div class="flex items-center justify-between px-2 py-1">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-full bg-operon-energy/20 border border-operon-energy/30 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-operon-energy text-base">person</span>
                    </div>
                    <div class="sidebar-label">
                        <p class="text-xs font-bold text-white leading-none"><?= e(\App\Core\Session::get('name') ?? 'Usuário') ?></p>
                        <p class="text-[9px] font-medium text-slate-500 tracking-wider uppercase mt-0.5"><?= e(\App\Core\Session::get('role') ?? 'agent') ?></p>
                    </div>
                </div>
                <a href="/logout" class="text-slate-500 hover:text-white transition-colors sidebar-label" title="Sair">
                    <span class="material-symbols-outlined text-lg">logout</span>
                </a>
            </div>

        </div>
    </aside>

    <!-- ── Main Content ────────────────────────────────────── -->
    <div id="mainContent" class="flex flex-1 flex-col overflow-hidden min-w-0 transition-all duration-300">

        <!-- Topbar -->
        <header class="flex h-16 items-center justify-between border-b border-slate-800 bg-operon-charcoal px-6 flex-shrink-0 z-10">
            <div class="flex items-center gap-4 flex-1">
                <!-- Sidebar Toggle -->
                <button id="sidebarToggle" class="size-9 flex items-center justify-center rounded-xl bg-white/5 text-slate-400 hover:text-white hover:bg-white/10 transition-all" title="Toggle sidebar">
                    <span class="material-symbols-outlined text-xl">menu</span>
                </button>

                <!-- Page Title -->
                <div>
                    <h2 class="text-sm font-bold text-white leading-none"><?= e($pageTitle ?? 'Dashboard') ?></h2>
                    <?php if (!empty($pageSubtitle)): ?>
                    <p class="text-[10px] text-slate-500 mt-0.5"><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right side actions -->
            <div class="flex items-center gap-3">
                <!-- Token indicator (compact) -->
                <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/5 border border-white/8">
                    <span class="material-symbols-outlined text-operon-energy text-sm">bolt</span>
                    <span class="text-[11px] font-bold text-slate-300"><?= $tbUsed ?><span class="text-slate-500">/<?= $tbLimit ?></span></span>
                </div>

                <!-- AI Badge -->
                <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-operon-energy/10 border border-operon-energy/20">
                    <span class="size-1.5 bg-operon-energy rounded-full animate-pulse"></span>
                    <span class="text-[10px] font-bold tracking-wider text-operon-energy uppercase">Operon Intelligence</span>
                </div>

                <div class="h-8 w-px bg-slate-800 mx-1"></div>

                <!-- Notifications -->
                <button class="size-9 flex items-center justify-center rounded-xl bg-white/5 text-slate-400 hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-xl">notifications</span>
                </button>

                <!-- User Avatar -->
                <div class="flex items-center gap-2.5">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-bold text-white leading-none"><?= e(\App\Core\Session::get('name') ?? 'Usuário') ?></p>
                        <p class="text-[10px] text-slate-500 font-medium">High-Ticket Sales</p>
                    </div>
                    <div class="size-9 rounded-full bg-operon-energy/20 border-2 border-operon-energy/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-operon-energy text-base">person</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto bg-background-dark">
            <?= $content ?>
        </main>

    </div>
</div>

<!-- ── Copilot Modal ───────────────────────────────────────── -->
<div id="copilotModal" class="fixed inset-0 z-50 modal-backdrop hidden items-end justify-center sm:items-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="w-full max-w-lg bg-brand-surface border border-white/10 rounded-2xl shadow-2xl animate-popIn overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/8">
            <div class="flex items-center gap-3">
                <div class="size-8 bg-operon-energy/20 rounded-full flex items-center justify-center border border-operon-energy/30">
                    <span class="material-symbols-outlined text-operon-energy text-base">smart_toy</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-white leading-none">Operon Intelligence</p>
                    <p class="text-[10px] text-operon-energy mt-0.5 flex items-center gap-1">
                        <span class="size-1.5 bg-operon-energy rounded-full animate-pulse inline-block"></span>
                        Sistema ativo
                    </p>
                </div>
            </div>
            <button onclick="closeModal('copilotModal')" class="text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Messages -->
        <div id="copilotMessages" class="h-64 overflow-y-auto p-4 flex flex-col gap-3">
            <div class="flex gap-3">
                <div class="size-6 bg-operon-energy/20 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-operon-energy text-xs">smart_toy</span>
                </div>
                <div class="bg-white/5 rounded-xl rounded-tl-none px-3 py-2.5 text-sm text-slate-300 max-w-xs">
                    Olá! Sou o Copilot da Operon. Como posso ajudar sua prospecção hoje?
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="px-4 py-3 border-t border-white/8">
            <form id="copilotForm" class="flex gap-2">
                <?= csrf_field() ?>
                <input id="copilotInput" type="text" placeholder="Pergunte algo sobre seus leads..."
                       class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                <button type="submit" class="size-10 bg-operon-energy rounded-xl flex items-center justify-center hover:brightness-110 transition-all flex-shrink-0">
                    <span class="material-symbols-outlined text-black text-lg">send</span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<script src="/js/app.js"></script>
<script>
// Copilot Chat
const copilotForm = document.getElementById('copilotForm');
const copilotMessages = document.getElementById('copilotMessages');
const copilotInput = document.getElementById('copilotInput');

copilotForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = copilotInput.value.trim();
    if (!msg) return;

    // Append user message
    copilotMessages.innerHTML += `
        <div class="flex gap-3 justify-end">
            <div class="bg-operon-energy/20 border border-operon-energy/20 rounded-xl rounded-tr-none px-3 py-2.5 text-sm text-white max-w-xs">${msg}</div>
        </div>`;
    copilotInput.value = '';

    // Append loading
    const loaderId = 'copilot-loader-' + Date.now();
    copilotMessages.innerHTML += `
        <div id="${loaderId}" class="flex gap-3">
            <div class="size-6 bg-operon-energy/20 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                <span class="material-symbols-outlined text-operon-energy text-xs">smart_toy</span>
            </div>
            <div class="bg-white/5 rounded-xl rounded-tl-none px-3 py-2.5">
                <div class="ai-spinner"></div>
            </div>
        </div>`;
    copilotMessages.scrollTop = copilotMessages.scrollHeight;

    try {
        const res = await operonFetch('/api/copilot', {
            method: 'POST',
            body: JSON.stringify({ message: msg, _csrf: getCsrfToken() })
        });
        document.getElementById(loaderId)?.remove();
        copilotMessages.innerHTML += `
            <div class="flex gap-3">
                <div class="size-6 bg-operon-energy/20 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-operon-energy text-xs">smart_toy</span>
                </div>
                <div class="bg-white/5 rounded-xl rounded-tl-none px-3 py-2.5 text-sm text-slate-300 max-w-xs ai-prose">${res.reply || res.error || 'Erro ao processar.'}</div>
            </div>`;
    } catch {
        document.getElementById(loaderId)?.remove();
        copilotMessages.innerHTML += `<div class="text-xs text-red-400 px-4">Erro de conexão.</div>`;
    }
    copilotMessages.scrollTop = copilotMessages.scrollHeight;
});
</script>

<?= $extraScripts ?? '' ?>
</body>
</html>
