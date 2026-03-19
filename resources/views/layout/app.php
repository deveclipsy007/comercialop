<!DOCTYPE html>
<html class="dark" lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($pageTitle ?? 'Operon Intelligence') ?> — Operon</title>

    <?php
    $userSession = \App\Core\Session::get('auth_user') ?? [];
    $linkedTenants = [];
    $currentTenantName = 'Empresa';
    
    // Buscar configurações direto no banco para aplicar mudanças de White Label em Tempo Real
    if (!empty($userSession['id'])) {
        $realTimeWL = \App\Core\Database::selectFirst(
            'SELECT wl_color, wl_logo, wl_features, max_tenants, can_create_tenants FROM users WHERE id = ?', 
            [$userSession['id']]
        );
        if ($realTimeWL) {
            $userSession['wl_color'] = $realTimeWL['wl_color'];
            $userSession['wl_logo']  = $realTimeWL['wl_logo'];
            $userSession['wl_features'] = $realTimeWL['wl_features'];
            $userSession['max_tenants'] = $realTimeWL['max_tenants'];
            $userSession['can_create_tenants'] = $realTimeWL['can_create_tenants'];
        }

        // Buscar todas as empresas que o usuário tem acesso
        $linkedTenants = \App\Models\User::getLinkedTenants($userSession['id']);
        foreach ($linkedTenants as $t) {
            if ($t['id'] === \App\Core\Session::tenantId()) {
                $currentTenantName = $t['name'];
                break;
            }
        }
    }
    
    $wlColor = $userSession['wl_color'] ?? '#E1FB15';
    if ($wlColor === '') $wlColor = '#E1FB15';
    
    $wlLogo = $userSession['wl_logo'] ?? '';
    if ($wlLogo === '') {
        $wlLogo = 'https://imagedelivery.net/mYdfeAeRRdkIXG5w7XJhtQ/08d22f62-d69a-4d61-7a2f-f565e4546b00/public';
    }

    $wlFeaturesRaw = $userSession['wl_features'] ?? null;
    $wlFeatures = null;
    if ($wlFeaturesRaw !== null && $wlFeaturesRaw !== '') {
        $wlFeatures = json_decode($wlFeaturesRaw, true) ?: [];
    }
    ?>

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
                        "bg":          "#000000",
                        "surface":     "#131313",
                        "surface2":    "#1A1A1A",
                        "surface3":    "#202020",
                        "stroke":      "#2A2A2A",
                        "text":        "#F5F5F5",
                        "muted":       "#A1A1AA",
                        "subtle":      "#71717A",
                        "lime":        "<?= $wlColor ?>",
                        "mint":        "#32D583",
                        
                        // Legado Operon p/ não quebrar views não ajustadas ainda
                        "primary":           "<?= $wlColor ?>", 
                        "operon-charcoal":   "#000000",
                        "operon-teal":       "#131313",
                        "operon-energy":     "<?= $wlColor ?>",
                        "background-dark":   "#000000",
                        "background-light":  "#F8F6F6",
                        "brand-surface":     "#131313",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"],
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg":      "1rem",
                        "xl":      "1.5rem",
                        "2xl":     "1.75rem",
                        "card":    "28px",
                        "cardLg":  "32px",
                        "pill":    "9999px",
                        "full":    "9999px",
                    },
                    boxShadow: {
                        "soft": "0 8px 30px rgba(0,0,0,0.28)",
                        "glow": "0 0 0 1px rgba(225,251,21,0.08), 0 8px 24px rgba(225,251,21,0.15)",
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-bg font-display text-text antialiased">

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

<div class="relative flex flex-col h-screen w-full overflow-hidden bg-bg">

    <!-- ── Topbar Premium ──────────────────────────────────────── -->
    <header class="flex h-[72px] lg:h-[88px] items-center justify-between border-b border-stroke bg-bg px-4 md:px-8 flex-shrink-0 z-20">
        
        <!-- Logo Area -->
        <a href="/" class="flex items-center flex-shrink-0 min-w-[120px]">
            <img src="<?= e($wlLogo) ?>" alt="Operon Intelligence" class="h-12 lg:h-[52px] w-auto object-contain relative -left-2 transition-transform hover:scale-105">
        </a>

        <!-- Primary Navigation (Grouped Dropdowns) -->
        <nav class="hidden flex-1 lg:flex items-center justify-center gap-4 px-4 h-full">
            <?php
            $navGroups = [
                'Inteligência' => [
                    'icon' => 'psychology',
                    'items' => [
                        ['label' => 'Overview',  'path' => '/',          'key' => 'dashboard', 'icon' => 'grid_view'],
                        ['label' => 'Copilot',   'path' => '/copilot',   'key' => 'copilot',   'icon' => 'smart_toy'],
                        ['label' => 'SPIN Hub',  'path' => '/spin',      'key' => 'spin',      'icon' => 'psychology_alt'],
                        ['label' => 'Knowledge', 'path' => '/knowledge', 'key' => 'knowledge', 'icon' => 'neurology'],
                    ]
                ],
                'Exploração' => [
                    'icon' => 'explore',
                    'items' => [
                        ['label' => 'Hunter',    'path' => '/hunter',    'key' => 'hunter',    'icon' => 'travel_explore'],
                        ['label' => 'Atlas',     'path' => '/atlas',     'key' => 'atlas',     'icon' => 'map'],
                        ['label' => 'Genesis',   'path' => '/genesis',   'key' => 'genesis',   'icon' => 'upload_file'],
                    ]
                ],
                'Interação' => [
                    'icon' => 'forum',
                    'items' => [
                        ['label' => 'WhatsApp',  'path' => '/whatsapp',  'key' => 'whatsapp',  'icon' => 'chat'],
                        ['label' => 'Follow-up', 'path' => '/follow-up', 'key' => 'agenda',    'icon' => 'notifications_active'],
                    ]
                ],
                'Organização' => [
                    'icon' => 'inventory_2',
                    'items' => [
                        ['label' => 'Vault',     'path' => '/vault',     'key' => 'vault',     'icon' => 'contacts'],
                        ['label' => 'Agenda',    'path' => '/agenda',    'key' => 'agenda',    'icon' => 'calendar_today'],
                    ]
                ],
            ];

            $active = $active ?? '';
            foreach ($navGroups as $groupLabel => $group): 
                $groupActive = false;
                foreach ($group['items'] as $item) {
                    if ($active === $item['key']) {
                        $groupActive = true;
                        break;
                    }
                }
            ?>
            <div class="relative group/nav-dropdown h-full flex items-center">
                <button class="flex items-center gap-2 h-11 px-4 rounded-pill text-sm transition-all duration-200 whitespace-nowrap border
                               <?= $groupActive
                                   ? 'bg-lime/10 border-lime/40 text-lime shadow-glow-sm'
                                   : 'bg-surface border-white/5 text-muted hover:text-text hover:bg-surface2 hover:border-white/10' ?>">
                    <span class="material-symbols-outlined text-[18px]"><?= $group['icon'] ?></span>
                    <span class="font-bold"><?= $groupLabel ?></span>
                    <span class="material-symbols-outlined text-xs transition-transform duration-200 group-hover/nav-dropdown:rotate-180">expand_more</span>
                </button>

                <!-- Dropdown Content -->
                <div class="absolute top-[80%] left-1/2 -translate-x-1/2 pt-4 opacity-0 pointer-events-none group-hover/nav-dropdown:opacity-100 group-hover/nav-dropdown:pointer-events-auto transition-all duration-200 z-50">
                    <div class="bg-surface2 border border-stroke rounded-[24px] shadow-soft p-2 min-w-[220px] backdrop-blur-xl">
                        <?php foreach ($group['items'] as $item): 
                            $isActive = $active === $item['key'];
                        ?>
                        <a href="<?= $item['path'] ?>" 
                           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all
                                  <?= $isActive ? 'bg-lime text-bg font-bold' : 'text-muted hover:text-text hover:bg-surface3' ?>">
                            <span class="material-symbols-outlined text-[18px]"><?= $item['icon'] ?></span>
                            <span class="text-sm"><?= $item['label'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </nav>

        <!-- Right End Actions -->
        <div class="flex items-center gap-3 md:gap-4 justify-end min-w-[120px]">
            <!-- Token/Credit Indicator -->
            <?php
            // Auto-load token balance if not passed by controller
            if (!isset($tokenBalance) && !empty($userSession['id'])) {
                $tokenBalance = \App\Models\TokenQuota::getBalance(\App\Core\Session::tenantId());
            }
            $tb = $tokenBalance ?? null;
            $tbUsed  = $tb['used'] ?? 0;
            $tbLimit = $tb['limit'] ?? 100;
            $tbRemaining = $tb['remaining'] ?? ($tbLimit - $tbUsed);
            $tbPercent = $tbLimit > 0 ? round(($tbUsed / $tbLimit) * 100) : 0;
            $tbColor = $tbPercent >= 90 ? 'text-red-400' : ($tbPercent >= 70 ? 'text-yellow-400' : 'text-lime');
            $tbBarColor = $tbPercent >= 90 ? 'bg-red-400' : ($tbPercent >= 70 ? 'bg-yellow-400' : 'bg-lime');
            ?>
            <a href="/costs" class="hidden md:flex items-center h-10 px-4 rounded-pill bg-surface border border-white/5 gap-2 hover:border-white/10 transition-all group" title="<?= $tbRemaining ?> créditos restantes">
                <span class="material-symbols-outlined <?= $tbColor ?> text-sm">bolt</span>
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-bold <?= $tbColor ?>"><?= $tbRemaining ?><span class="text-subtle font-medium ml-1">/ <?= $tbLimit ?></span></span>
                    <div class="w-16 h-[3px] bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full <?= $tbBarColor ?> rounded-full transition-all" style="width: <?= min(100, $tbPercent) ?>%"></div>
                    </div>
                </div>
            </a>

            <div class="hidden md:block h-6 w-px bg-stroke"></div>

            <!-- Mobile Menu Toggle -->
            <button id="mobileMenuBtn" class="flex lg:hidden size-10 items-center justify-center rounded-full bg-surface border border-white/5 text-muted hover:text-text hover:bg-surface2 transition-all">
                <span class="material-symbols-outlined text-[20px]">menu</span>
            </button>

            <!-- Knowledge Base (Circular) -->
            <a href="/knowledge"
               title="Knowledge Base — IA da sua empresa"
               class="flex size-10 items-center justify-center rounded-full bg-surface border border-white/5 text-muted hover:text-lime hover:border-lime/30 transition-all relative <?= ($active ?? '') === 'knowledge' ? 'border-lime/40 text-lime' : '' ?>">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings:'FILL' 1">neurology</span>
                <?php if (($active ?? '') !== 'knowledge'): ?>
                <span class="absolute top-[2px] right-[2px] size-2 bg-lime/60 rounded-full border-2 border-bg"></span>
                <?php endif; ?>
            </a>

            <!-- Copilot Action (Circular) -->
            <a href="/copilot" class="flex size-10 items-center justify-center rounded-full bg-surface border border-white/5 text-muted hover:text-lime hover:border-lime/30 transition-all group relative <?= ($active ?? '') === 'copilot' ? 'border-lime/40 text-lime' : '' ?>" title="Operon AI Copilot">
                <span class="material-symbols-outlined text-[20px]">smart_toy</span>
                <span class="absolute top-[2px] right-[2px] size-2.5 bg-lime rounded-full animate-pulse border-2 border-bg"></span>
            </a>

            <!-- Context Switcher (Multi-Company) -->
            <?php if (!empty($linkedTenants)): ?>
            <div class="relative" id="companySwitcherContainer">
                <button id="companySwitcherBtn" class="hidden md:flex items-center gap-2 h-10 px-4 rounded-pill bg-surface2 border border-white/10 hover:border-lime/50 transition-all group">
                    <span class="material-symbols-outlined text-[18px] text-lime">domain</span>
                    <span class="text-sm font-medium text-text truncate max-w-[120px]"><?= e($currentTenantName) ?></span>
                    <span class="material-symbols-outlined text-[18px] text-muted group-hover:text-text transition-transform duration-200" id="companySwitcherIcon">expand_more</span>
                </button>

                <!-- Dropdown Menu -->
                <div id="companySwitcherMenu" class="absolute right-0 mt-3 w-64 bg-surface2 border border-stroke rounded-[20px] shadow-lg py-2 hidden z-50 transform opacity-0 scale-95 transition-all duration-200 origin-top-right">
                    <div class="px-4 py-2 border-b border-stroke mb-2 flex items-center justify-between">
                        <p class="text-xs font-bold text-subtle uppercase tracking-wider">Alternar Empresa</p>
                        <span class="text-[10px] bg-lime/10 text-lime px-2 py-0.5 rounded-full border border-lime/20"><?= count($linkedTenants) ?></span>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto px-2 custom-scrollbar">
                        <?php foreach($linkedTenants as $tenant): 
                            $isActive = $tenant['id'] === \App\Core\Session::tenantId();
                        ?>
                            <form action="/context/switch" method="POST" class="m-0">
                                <input type="hidden" name="_csrf" value="<?= \App\Core\Session::csrf() ?>">
                                <input type="hidden" name="tenant_id" value="<?= e($tenant['id']) ?>">
                                <button type="submit" class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= $isActive ? 'bg-lime/10 border border-lime/20' : 'hover:bg-surface3 border border-transparent' ?>">
                                    <div class="size-8 rounded-lg flex items-center justify-center flex-shrink-0 <?= $isActive ? 'bg-lime text-black' : 'bg-surface border border-white/5 text-muted' ?>">
                                        <span class="material-symbols-outlined text-[16px]">business</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium truncate <?= $isActive ? 'text-lime' : 'text-text' ?>"><?= e($tenant['name']) ?></p>
                                        <p class="text-[10px] text-muted truncate capitalize">Permissão: <?= e($tenant['pivot_role'] ?? 'Membro') ?></p>
                                    </div>
                                    <?php if($isActive): ?>
                                        <span class="material-symbols-outlined text-[18px] text-lime">check_circle</span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                        
                        <?php if ((int)($userSession['max_tenants'] ?? 1) > count($linkedTenants) && ($userSession['can_create_tenants'] ?? 0)): ?>
                            <div class="mt-2 p-2 border-t border-stroke">
                                <button onclick="openCreateCompanyModal()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-subtle hover:text-lime hover:bg-lime/5 transition-all text-xs font-bold">
                                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                                    Criar Nova Empresa
                                </button>
                                <p class="text-[9px] text-muted text-center mt-1.5 px-4 italic">Limite: <?= count($linkedTenants) ?> / <?= $userSession['max_tenants'] ?></p>
                            </div>
                        <?php else: ?>
                            <div class="mt-2 p-2 border-t border-stroke text-center">
                                <p class="text-[9px] text-muted px-4 italic">Você está no limite de empresas (<?= $userSession['max_tenants'] ?>)</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- User Dropdown -->
            <div class="relative" id="userDropdownContainer">
                <button id="userDropdownBtn" class="flex items-center outline-none group">
                    <div class="size-10 rounded-full bg-surface border border-white/5 flex items-center justify-center text-muted group-hover:text-text group-hover:bg-surface2 transition-all">
                        <span class="material-symbols-outlined text-[20px]">person</span>
                    </div>
                </button>

                 <!-- Dropdown Menu -->
                 <div id="userDropdownMenu" class="absolute right-0 mt-3 w-56 bg-surface2 border border-stroke rounded-[24px] shadow-soft py-2 hidden z-50 transform opacity-0 scale-95 transition-all duration-200 origin-top-right">
                    <div class="px-5 py-3 border-b border-stroke mb-2">
                        <p class="text-xs font-bold text-text truncate"><?= e(\App\Core\Session::get('name') ?? 'Usuário') ?></p>
                        <p class="text-[10px] text-muted mt-0.5 truncate"><?= e(\App\Core\Session::get('email') ?? 'usuario@operon.com') ?></p>
                    </div>
                    
                    <a href="/costs" class="flex items-center gap-3 px-5 py-2.5 text-sm text-muted hover:text-text hover:bg-surface3 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">payments</span> Controle de Custos
                    </a>
                    <a href="/profile" class="flex items-center gap-3 px-5 py-2.5 text-sm text-muted hover:text-text hover:bg-surface3 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">account_circle</span> Perfil
                    </a>
                    <a href="/logs" class="flex items-center gap-3 px-5 py-2.5 text-sm text-muted hover:text-text hover:bg-surface3 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">history</span> Logs
                    </a>
                    <a href="/settings" class="flex items-center gap-3 px-5 py-2.5 text-sm text-muted hover:text-text hover:bg-surface3 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">settings</span> Configurações
                    </a>
                    <a href="/integrations" class="flex items-center gap-3 px-5 py-2.5 text-sm text-muted hover:text-text hover:bg-surface3 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">extension</span> Integrações
                    </a>



                    <div class="border-t border-stroke mt-2 pt-2">
                        <a href="/logout" class="flex items-center gap-3 px-5 py-2.5 text-sm text-red-500 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">logout</span> Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation Overlay (Visible only on small screens when triggered) -->
    <div id="mobileNavOverlay" class="fixed inset-0 bg-bg z-40 flex-col hidden lg:hidden overflow-y-auto px-4 py-6 border-t border-stroke mt-[72px]">
        <div class="flex flex-col gap-3">
            <p class="text-[10px] font-bold text-subtle tracking-[0.2em] uppercase mb-2 px-2">Navegação Principal</p>
            <?php foreach ($filteredNav as $item): ?>
                <a href="<?= $item['path'] ?>" class="flex items-center gap-2.5 h-12 px-5 rounded-pill text-sm transition-all duration-200 <?= ($active ?? '') === $item['key'] ? 'bg-lime text-bg font-medium' : 'bg-surface text-muted border border-white/5' ?>">
                    <?php if (!empty($item['icon'])): ?>
                    <span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1,'wght' 400"><?= $item['icon'] ?></span>
                    <?php endif; ?>
                    <?= $item['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Main Content Area ───────────────────────────────── -->
    <main id="mainContent" class="flex-1 overflow-y-auto bg-bg relative z-10">
        <?= $content ?>
    </main>
</div>

<!-- ── Copilot Modal ───────────────────────────────────────── -->
<div id="copilotModal" class="fixed inset-0 z-50 modal-backdrop hidden items-end justify-center sm:items-center p-4 bg-bg/80 backdrop-blur-md">
    <div class="w-full max-w-lg bg-surface border border-stroke rounded-cardLg shadow-soft animate-popIn overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-stroke">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-lime/10 rounded-full flex items-center justify-center border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-lg">smart_toy</span>
                </div>
                <div>
                    <p class="text-base font-bold text-text leading-none">Operon Intelligence</p>
                    <p class="text-xs font-medium text-muted mt-1 flex items-center gap-1.5">
                        <span class="size-1.5 bg-lime rounded-full animate-pulse inline-block"></span>
                        Sistema conectado
                    </p>
                </div>
            </div>
            <button onclick="closeModal('copilotModal')" class="flex size-8 items-center justify-center rounded-full bg-surface2 text-muted hover:text-text transition-colors border border-white/5">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <!-- Messages -->
        <div id="copilotMessages" class="h-72 overflow-y-auto p-6 flex flex-col gap-4">
            <div class="flex gap-4">
                <div class="size-8 bg-lime/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-sm">smart_toy</span>
                </div>
                <div class="bg-surface2 border border-stroke rounded-[20px] rounded-tl-none px-4 py-3 text-sm text-text max-w-xs shadow-sm shadow-black/20">
                    Olá! Sou o Operon Intelligence. Como posso acelerar os seus resultados hoje?
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="px-6 py-4 border-t border-stroke bg-surface2">
            <form id="copilotForm" class="flex gap-3">
                <?= csrf_field() ?>
                <input id="copilotInput" type="text" placeholder="Formule seu comando..."
                       class="flex-1 bg-surface border border-stroke rounded-pill px-5 py-3 text-sm text-text placeholder-muted focus:outline-none focus:border-lime/50 transition-all">
                <button type="submit" class="size-12 bg-lime rounded-full flex items-center justify-center hover:brightness-110 shadow-glow transition-all flex-shrink-0">
                    <span class="material-symbols-outlined text-bg text-[22px]">arrow_upward</span>
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
            <div class="bg-surface3 border border-stroke rounded-[20px] rounded-tr-none px-4 py-3 text-sm text-text max-w-xs">${msg}</div>
        </div>`;
    copilotInput.value = '';

    // Append loading
    const loaderId = 'copilot-loader-' + Date.now();
    copilotMessages.innerHTML += `
        <div id="${loaderId}" class="flex gap-4 animate-pulse">
            <div class="size-8 bg-lime/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                <span class="material-symbols-outlined text-lime text-sm">smart_toy</span>
            </div>
            <div class="bg-surface2 border border-stroke rounded-[20px] rounded-tl-none px-4 py-3 flex items-center justify-center">
                <div class="ai-spinner border-lime !w-4 !h-4"></div>
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
            <div class="flex gap-4">
                <div class="size-8 bg-lime/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 border border-lime/20">
                    <span class="material-symbols-outlined text-lime text-sm">smart_toy</span>
                </div>
                <div class="bg-surface2 border border-stroke rounded-[20px] rounded-tl-none px-4 py-3 text-sm text-text max-w-xs ai-prose shadow-sm shadow-black/20">${res.reply || res.error || 'Erro processando.'}</div>
            </div>`;
    } catch {
        document.getElementById(loaderId)?.remove();
        copilotMessages.innerHTML += `<div class="text-[10px] font-bold tracking-wider uppercase text-red-500 px-12">Falha de Comlink.</div>`;
    }
    copilotMessages.scrollTop = copilotMessages.scrollHeight;
});

// User Dropdown Logic
const userDropdownBtn = document.getElementById('userDropdownBtn');
const userDropdownMenu = document.getElementById('userDropdownMenu');

if (userDropdownBtn && userDropdownMenu) {
    userDropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = userDropdownMenu.classList.contains('hidden');
        
        if (isHidden) {
            userDropdownMenu.classList.remove('hidden');
            // Allow display block to apply before animating opacity/scale
            requestAnimationFrame(() => {
                userDropdownMenu.classList.remove('opacity-0', 'scale-95');
                userDropdownMenu.classList.add('opacity-100', 'scale-100');
            });
        } else {
            userDropdownMenu.classList.remove('opacity-100', 'scale-100');
            userDropdownMenu.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                userDropdownMenu.classList.add('hidden');
            }, 200); // match transition duration
        }
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!userDropdownMenu.classList.contains('hidden') && !e.target.closest('#userDropdownContainer')) {
            userDropdownMenu.classList.remove('opacity-100', 'scale-100');
            userDropdownMenu.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                userDropdownMenu.classList.add('hidden');
            }, 200);
        }
    });
} 

// Company Switcher Dropdown Logic
const companySwitcherBtn = document.getElementById('companySwitcherBtn');
const companySwitcherMenu = document.getElementById('companySwitcherMenu');
const companySwitcherIcon = document.getElementById('companySwitcherIcon');

if (companySwitcherBtn && companySwitcherMenu) {
    companySwitcherBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = companySwitcherMenu.classList.contains('hidden');
        
        if (isHidden) {
            companySwitcherMenu.classList.remove('hidden');
            if (companySwitcherIcon) companySwitcherIcon.style.transform = 'rotate(180deg)';
            requestAnimationFrame(() => {
                companySwitcherMenu.classList.remove('opacity-0', 'scale-95');
                companySwitcherMenu.classList.add('opacity-100', 'scale-100');
            });
        } else {
            companySwitcherMenu.classList.remove('opacity-100', 'scale-100');
            companySwitcherMenu.classList.add('opacity-0', 'scale-95');
            if (companySwitcherIcon) companySwitcherIcon.style.transform = 'rotate(0deg)';
            setTimeout(() => {
                companySwitcherMenu.classList.add('hidden');
            }, 200);
        }
    });

    document.addEventListener('click', (e) => {
        if (!companySwitcherMenu.classList.contains('hidden') && !e.target.closest('#companySwitcherContainer')) {
            companySwitcherMenu.classList.remove('opacity-100', 'scale-100');
            companySwitcherMenu.classList.add('opacity-0', 'scale-95');
            if (companySwitcherIcon) companySwitcherIcon.style.transform = 'rotate(0deg)';
            setTimeout(() => {
                companySwitcherMenu.classList.add('hidden');
            }, 200);
        }
    });
}

// Mobile Nav Toggle
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileNavOverlay = document.getElementById('mobileNavOverlay');
if(mobileMenuBtn && mobileNavOverlay) {
    mobileMenuBtn.addEventListener('click', () => {
        mobileNavOverlay.classList.toggle('hidden');
        mobileNavOverlay.classList.toggle('flex');
    });
}
</script>

<!-- ─── Create Company Modal ────────────────────────── -->
<div id="createCompanyModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-md" onclick="closeCreateCompanyModal()"></div>
    <div class="relative w-full max-w-md bg-surface2 border border-stroke rounded-[32px] shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="createCompanyModalContent">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <div class="size-12 rounded-2xl bg-lime/10 border border-lime/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-lime text-2xl">add_business</span>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-text">Nova Empresa</h3>
                        <p class="text-xs text-subtle">Crie um novo ambiente de operação.</p>
                    </div>
                </div>
                <button onclick="closeCreateCompanyModal()" class="size-10 rounded-full border border-stroke flex items-center justify-center text-muted hover:text-text hover:bg-surface3 transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form action="/context/create" method="POST" class="space-y-6">
                <?= csrf_field() ?>
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] ml-1">Nome da Empresa</label>
                    <input type="text" name="company_name" required placeholder="Ex: Nexus Filial Norte"
                        class="w-full h-14 bg-surface3 border border-stroke rounded-2xl px-5 text-sm text-text placeholder:text-muted focus:outline-none focus:border-lime/50 focus:ring-1 focus:ring-lime/20 transition-all shadow-inner">
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeCreateCompanyModal()" class="flex-1 h-14 bg-surface3 text-text text-sm font-bold rounded-2xl border border-stroke hover:bg-surface hover:text-muted transition-all">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-[1.5] h-14 bg-lime text-bg text-sm font-black rounded-2xl shadow-glow hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">rocket_launch</span>
                        Criar e Ativar
                    </button>
                </div>
                
                <p class="text-[10px] text-center text-muted px-4">
                    Ao criar, você se tornará o <b>Administrador</b> central deste novo ambiente.
                </p>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateCompanyModal() {
    const modal = document.getElementById('createCompanyModal');
    const content = document.getElementById('createCompanyModalContent');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    });
}

function closeCreateCompanyModal() {
    const modal = document.getElementById('createCompanyModal');
    const content = document.getElementById('createCompanyModalContent');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
}
</script>

<?= $extraScripts ?? '' ?>
</body>
</html>
