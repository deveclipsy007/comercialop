<?php
        $userName = $user['name'] ?? \App\Core\Session::get('name') ?? 'Usuário';
        $userRole = $user['role'] ?? \App\Core\Session::get('role') ?? 'agent';
        $userEmail = $user['email'] ?? '';
        $roleLabels = ['admin' => 'Administrador', 'agent' => 'Agente Comercial', 'viewer' => 'Visualizador'];
        $roleLabel = $roleLabels[$userRole] ?? ucfirst($userRole);
?>
<div class="max-w-4xl mx-auto p-6 md:p-8 space-y-8">
    <div class="mb-2">
        <h1 class="text-2xl font-bold text-text">Meu Perfil</h1>
        <p class="text-sm text-muted mt-1">Gerencie suas informações pessoais e credenciais de acesso.</p>
    </div>

    <form method="POST" action="/profile" id="profileForm" class="bg-surface border border-stroke rounded-cardLg overflow-hidden shadow-soft">
        <?= csrf_field() ?>
        <div class="h-32 bg-lime/10 relative border-b border-stroke">
            <div class="absolute inset-0 bg-gradient-to-t from-bg/50 to-transparent"></div>
        </div>
        
        <div class="px-8 pb-8 relative">
            <div class="flex flex-col sm:flex-row gap-6 -mt-12 sm:items-end justify-between">
                <div class="flex flex-col sm:flex-row gap-6 sm:items-end">
                    <div class="size-24 rounded-full bg-bg border-4 border-surface flex items-center justify-center text-lime relative z-10 shrink-0 shadow-soft">
                        <span class="material-symbols-outlined text-[40px]">person</span>
                    </div>
                    <div class="pb-2">
                        <h2 class="text-[24px] font-bold text-text leading-tight"><?= e($userName) ?></h2>
                        <p class="text-[11px] font-bold text-lime uppercase tracking-[0.1em] mt-1.5"><?= e($roleLabel) ?></p>
                    </div>
                </div>
                <div class="pb-2">
                    <button type="button" id="editProfileBtn" class="h-10 px-6 bg-surface2 border border-stroke hover:bg-surface3 text-text text-sm font-medium rounded-pill transition-all min-w-[140px]">
                        Editar Perfil
                    </button>
                </div>
            </div>
        </div>

        <div class="border-t border-stroke px-8 py-8 border-b">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Nome Completo</label>
                    <input type="text" name="name" value="<?= e($userName) ?>" class="profile-input w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-subtle focus:outline-none transition-all opacity-70 cursor-not-allowed" readonly required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Email corporativo</label>
                    <input type="email" name="email" value="<?= e($userEmail) ?>" class="profile-input w-full bg-surface2 border border-stroke rounded-pill px-5 py-3 text-sm text-subtle focus:outline-none transition-all opacity-70 cursor-not-allowed" readonly required>
                </div>
            </div>
        </div>
        
        <div class="px-8 py-8 bg-surface2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h4 class="text-base font-bold text-text flex items-center gap-2">
                        <span class="material-symbols-outlined text-muted text-[18px]">security</span>
                        Autenticação em Duas Etapas (2FA)
                    </h4>
                    <p class="text-sm text-subtle mt-1.5">Proteja sua conta adicionando uma camada extra de segurança.</p>
                </div>
                <button type="button" class="h-10 px-5 border border-stroke bg-surface hover:bg-surface3 text-text text-sm font-medium rounded-pill transition-colors flex-shrink-0">
                    Configurar 2FA
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editBtn = document.getElementById('editProfileBtn');
    const inputs = document.querySelectorAll('.profile-input');
    
    if(editBtn) {
        let isEditing = false;
        
        editBtn.addEventListener('click', (e) => {
            if(!isEditing) {
                // Previne submit se clicou para entrar em modo de edição
                e.preventDefault();
                isEditing = true;
                
                inputs.forEach(input => {
                    input.removeAttribute('readonly');
                    input.classList.remove('opacity-70', 'cursor-not-allowed', 'text-subtle', 'bg-surface2');
                    input.classList.add('text-text', 'bg-surface', 'focus:border-lime/50', 'shadow-inner');
                });
                inputs[0].focus();
                
                editBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] mr-1.5 align-text-bottom">save</span> Salvar Alterações';
                editBtn.type = 'submit';
                editBtn.classList.remove('bg-surface2', 'border-stroke', 'text-text', 'hover:bg-surface3');
                editBtn.classList.add('bg-lime', 'text-bg', 'border-lime', 'hover:brightness-110', 'shadow-glow', 'font-bold');
            } else {
                // Se já estiver editando, o botão sendo type="submit" enviará o form nativamente.
            }
        });
    }
});
</script>
