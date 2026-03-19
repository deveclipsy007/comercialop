<?php
// Layout exclusive to the admin portal
// Fallback: If admin_tenant_id is not set (old session), set it now
if (!empty($_SESSION['admin_auth']) && empty($_SESSION['admin_tenant_id'])) {
    $_SESSION['admin_tenant_id'] = \App\Core\Session::tenantId();
}
$adminTenantId = $_SESSION['admin_tenant_id'] ?? null;
$adminTenant = $adminTenantId ? \App\Core\Database::selectFirst('SELECT name FROM tenants WHERE id = ?', [$adminTenantId]) : null;
?>
<!DOCTYPE html>
<html class="dark" lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($pageTitle ?? 'Admin Portal') ?> — Operon</title>

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
                        "lime":        "#E1FB15",
                        "mint":        "#32D583",
                        "primary":     "#E1FB15"
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

    <!-- ── Topbar (Admin) ──────────────────────────────────────── -->
    <header class="flex h-[72px] lg:h-[88px] items-center justify-between border-b border-stroke bg-bg px-4 md:px-8 flex-shrink-0 z-20">
        
        <!-- Logo Area -->
        <div class="flex items-center gap-3 flex-shrink-0 min-w-[120px]">
            <div class="size-10 bg-lime/10 rounded-full flex items-center justify-center text-lime border border-lime/20 flex-shrink-0">
                <span class="material-symbols-outlined text-xl">admin_panel_settings</span>
            </div>
            <div class="hidden sm:block">
                <h1 class="text-sm font-black tracking-wider text-lime leading-none">OPERON ADMIN</h1>
                <p class="text-[9px] font-bold tracking-[0.2em] text-muted mt-1 uppercase">MANAGEMENT</p>
            </div>
        </div>

        <!-- Admin Navigation (Pills) -->
        <nav class="hidden flex-1 lg:flex items-center justify-center gap-2 overflow-x-auto hide-scrollbar px-4">
            <?php
            $navItems = [
                ['label' => 'Configuração Base', 'path' => '/admin',              'key' => 'admin_config'],
                ['label' => 'Gestão de I.A',     'path' => '/admin/ai-config',    'key' => 'admin_ai'],
                ['label' => 'Chaves API',        'path' => '/admin/ai-keys',      'key' => 'admin_keys'],
                ['label' => 'Provedores',        'path' => '/admin/providers',    'key' => 'admin_providers'],
                ['label' => 'Consumo',           'path' => '/admin/consumption',  'key' => 'admin_consumption'],
                ['label' => 'Equipe',            'path' => '/admin/users',        'key' => 'admin_users'],
                ['label' => 'Logs Globais',      'path' => '/admin/logs',         'key' => 'admin_logs'],
            ];
            $active = $active ?? '';
            foreach ($navItems as $item): 
                $isActive = $active === $item['key'];
            ?>
            <a href="<?= $item['path'] ?>"
               class="flex items-center h-[40px] px-5 rounded-pill text-sm transition-all duration-200 whitespace-nowrap
                      <?= $isActive 
                          ? 'bg-lime text-bg font-medium shadow-glow' 
                          : 'bg-surface border border-white/5 text-muted hover:text-text hover:bg-surface2' ?>">
                <?= $item['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- Right End Actions -->
        <div class="flex items-center gap-4 justify-end min-w-[120px]">

            <!-- Admin Company Badge (Fixed Context) -->
            <?php if ($adminTenant): ?>
            <div class="hidden md:flex items-center gap-2 h-10 px-4 rounded-pill bg-surface2 border border-lime/20">
                <span class="material-symbols-outlined text-[16px] text-lime">verified</span>
                <span class="text-xs font-bold text-text truncate max-w-[140px]"><?= e($adminTenant['name']) ?></span>
            </div>
            <?php endif; ?>

            <!-- User Dropdown (Admin Profile Overview) -->
            <div class="relative" id="userDropdownContainer">
                <button id="userDropdownBtn" class="flex items-center outline-none group gap-2">
                    <span class="text-xs font-bold text-muted group-hover:text-text hidden md:block">Portal Admin</span>
                    <div class="size-10 rounded-full bg-surface border border-white/5 flex items-center justify-center text-lime group-hover:text-bg group-hover:bg-lime transition-all overflow-hidden relative shadow-glow">
                        <span class="material-symbols-outlined text-[20px]">shield_person</span>
                    </div>
                </button>

                 <!-- Dropdown Menu -->
                 <div id="userDropdownMenu" class="absolute right-0 mt-3 w-56 bg-surface2 border border-stroke rounded-[24px] shadow-soft py-2 hidden z-50 transform opacity-0 scale-95 transition-all duration-200 origin-top-right">
                    <div class="px-5 py-3 border-b border-stroke mb-2">
                        <p class="text-xs font-bold text-text truncate"><?= e(\App\Core\Session::get('name') ?? 'Admin') ?></p>
                        <p class="text-[10px] text-lime mt-0.5 font-bold uppercase tracking-widest truncate">Acesso Administrativo</p>
                        <?php if ($adminTenant): ?>
                        <p class="text-[9px] text-muted mt-1 truncate">Empresa-mãe: <?= e($adminTenant['name']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <a href="/" class="flex items-center gap-3 px-5 py-2.5 text-sm text-lime hover:text-bg hover:bg-lime transition-colors">
                        <span class="material-symbols-outlined text-[18px]">open_in_new</span> Ir para Sistema (Usuário)
                    </a>

                    <div class="border-t border-stroke mt-2 pt-2">
                        <a href="/logout" class="flex items-center gap-3 px-5 py-2.5 text-sm text-red-500 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">logout</span> Encerrar Sessão
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ── Main Content Area ───────────────────────────────── -->
    <main id="mainContent" class="flex-1 overflow-y-auto bg-bg relative z-10">
        <?= $content ?>
    </main>
</div>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<script src="/js/app.js"></script>
<script>
// User Dropdown Logic
const userDropdownBtn = document.getElementById('userDropdownBtn');
const userDropdownMenu = document.getElementById('userDropdownMenu');

if (userDropdownBtn && userDropdownMenu) {
    userDropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = userDropdownMenu.classList.contains('hidden');
        
        if (isHidden) {
            userDropdownMenu.classList.remove('hidden');
            requestAnimationFrame(() => {
                userDropdownMenu.classList.remove('opacity-0', 'scale-95');
                userDropdownMenu.classList.add('opacity-100', 'scale-100');
            });
        } else {
            userDropdownMenu.classList.remove('opacity-100', 'scale-100');
            userDropdownMenu.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                userDropdownMenu.classList.add('hidden');
            }, 200);
        }
    });

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
</script>
<?= $extraScripts ?? '' ?>
</body>
</html>
