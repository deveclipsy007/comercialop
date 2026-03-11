<?php $pageTitle = 'Acesso Administrativo'; ?>

<div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden bg-bg">

    <!-- Background blobs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-lime/5 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-mint/5 rounded-full blur-3xl animate-blob animation-delay-4000"></div>
    </div>

    <div class="w-full max-w-md relative z-10">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center size-16 bg-surface2 rounded-full border border-stroke mb-4 shadow-soft hover:shadow-glow transition-all">
                <span class="material-symbols-outlined text-lime text-3xl">shield</span>
            </div>
            <h1 class="text-2xl font-black tracking-wider text-text">PORTAL ADMIN</h1>
            <p class="text-[10px] font-bold tracking-[0.3em] text-muted mt-1 uppercase">Acesso Restrito</p>
        </div>

        <!-- Card -->
        <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">

            <div class="mb-6">
                <h2 class="text-xl font-bold text-text">Autenticação</h2>
                <p class="text-sm text-subtle mt-1">Insira suas credenciais de gestor para acessar.</p>
            </div>

            <?php $flashError = \App\Core\Session::getFlash('error'); if ($flashError): ?>
            <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl border border-red-500/20 bg-red-500/10 text-red-500 text-sm font-medium animate-fadeInUp">
                <span class="material-symbols-outlined text-lg">error</span>
                <?= e($flashError) ?>
            </div>
            <?php endif; ?>

            <?php $flashSuccess = \App\Core\Session::getFlash('success'); if ($flashSuccess): ?>
            <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl border border-lime/20 bg-lime/10 text-lime text-sm font-medium animate-fadeInUp">
                <span class="material-symbols-outlined text-lg">check_circle</span>
                <?= e($flashSuccess) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login" class="flex flex-col gap-5">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-2">E-mail Corporativo</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-muted text-xl">mail</span>
                        <input type="email" id="email" name="email" required
                               value="<?= e($_POST['email'] ?? '') ?>"
                               placeholder="gestor@operon.com"
                               class="w-full bg-surface2 border border-stroke rounded-pill pl-12 pr-5 py-3.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-[11px] font-bold text-muted uppercase tracking-wider mb-2">Chave de Acesso</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-muted text-xl">key</span>
                        <input type="password" id="password" name="password" required
                               placeholder="••••••••"
                               class="w-full bg-surface2 border border-stroke rounded-pill pl-12 pr-5 py-3.5 text-sm text-text placeholder-subtle focus:outline-none focus:border-lime/50 transition-all">
                    </div>
                </div>

                <button type="submit"
                        class="w-full h-12 bg-lime rounded-pill text-sm font-black text-bg hover:brightness-110 active:scale-95 transition-all mt-4 shadow-glow flex justify-center items-center gap-2">
                    Validar Acesso <span class="material-symbols-outlined text-[18px]">login</span>
                </button>
            </form>

            <a href="/" class="block mt-6 text-center text-[11px] font-bold text-muted hover:text-text transition-colors flex justify-center items-center gap-1.5 uppercase tracking-wider">
                <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                Retornar ao Sistema
            </a>
            
        </div>

        <!-- Footer -->
        <p class="text-center text-[10px] font-bold text-subtle mt-8 tracking-wider">
            © 2026 OPERON INTELLIGENCE PLATFORM
        </p>
    </div>
</div>
