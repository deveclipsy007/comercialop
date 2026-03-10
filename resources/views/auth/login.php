<?php $pageTitle = 'Login'; ?>

<div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Background blobs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-operon-energy/5 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-primary/5 rounded-full blur-3xl animate-blob animation-delay-4000"></div>
    </div>

    <div class="w-full max-w-md relative z-10">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center size-16 bg-operon-energy/15 rounded-full border border-operon-energy/30 mb-4 glow-energy-sm">
                <span class="material-symbols-outlined text-operon-energy text-3xl fill-1">security</span>
            </div>
            <h1 class="text-2xl font-black tracking-wider text-operon-energy">OPERON AGENTS</h1>
            <p class="text-xs font-bold tracking-[0.3em] text-slate-500 mt-1 uppercase">Intelligence Platform</p>
        </div>

        <!-- Card -->
        <div class="bg-[#1A1720] border border-white/10 rounded-2xl p-8 shadow-2xl">

            <div class="mb-6">
                <h2 class="text-xl font-bold text-white">Bem-vindo de volta</h2>
                <p class="text-sm text-slate-400 mt-1">Faça login para acessar o sistema.</p>
            </div>

            <?php $flashError = \App\Core\Session::getFlash('error'); if ($flashError): ?>
            <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl border flash-error text-sm">
                <span class="material-symbols-outlined text-base">error</span>
                <?= e($flashError) ?>
            </div>
            <?php endif; ?>

            <?php $flashSuccess = \App\Core\Session::getFlash('success'); if ($flashSuccess): ?>
            <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl border flash-success text-sm">
                <span class="material-symbols-outlined text-base">check_circle</span>
                <?= e($flashSuccess) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/login" class="flex flex-col gap-4">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">E-mail</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xl">mail</span>
                        <input type="email" id="email" name="email" required
                               value="<?= e($_POST['email'] ?? '') ?>"
                               placeholder="seu@email.com"
                               class="w-full bg-white/5 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Senha</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xl">lock</span>
                        <input type="password" id="password" name="password" required
                               placeholder="••••••••"
                               class="w-full bg-white/5 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-operon-energy/50 focus:ring-1 focus:ring-operon-energy/30 transition-all">
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-primary rounded-xl text-sm font-bold text-white hover:brightness-110 active:scale-95 transition-all mt-2 shadow-lg shadow-primary/20">
                    Acessar o Sistema
                </button>
            </form>

            <!-- Demo credentials hint -->
            <p class="text-center text-xs text-slate-600 mt-6">
                Demo: <span class="text-slate-500">admin@operon.ai</span> / <span class="text-slate-500">operon123</span>
            </p>
        </div>

        <!-- Footer -->
        <p class="text-center text-[10px] text-slate-600 mt-6">
            © 2026 Operon Intelligence Platform · Todos os direitos reservados
        </p>
    </div>
</div>
