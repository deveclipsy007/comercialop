<?php
$hubspot = $integrations['hubspot']['connected'] ?? false;
$whatsapp = $integrations['whatsapp']['connected'] ?? false;
$google = $integrations['google']['connected'] ?? false;
?>
<div class="max-w-6xl mx-auto p-6 md:p-8 space-y-8">
    <div class="mb-2">
        <h1 class="text-2xl font-bold text-text">Integrações</h1>
        <p class="text-sm text-muted mt-1">Conecte o Operon com seu ecossistema de ferramentas.</p>
    </div>

    <div class="bg-surface border border-stroke rounded-cardLg p-8 shadow-soft">
        <div class="mb-8">
            <h3 class="text-xl font-bold text-text leading-tight">Aplicativos Disponíveis</h3>
            <p class="text-sm text-subtle mt-1">Sincronize contatos, rotinas e comunicações automaticamente</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- HubSpot -->
            <div class="bg-surface2 border border-stroke rounded-card p-6 hover:bg-surface3 transition-colors flex flex-col h-full relative overflow-hidden group">
                <div class="flex items-center justify-between mb-6">
                    <div class="size-14 rounded-2xl bg-[#FF7A59] flex items-center justify-center text-white font-black text-2xl shadow-sm">
                        H
                    </div>
                    <?php if ($hubspot): ?>
                    <div class="px-2.5 py-1 bg-mint/10 text-mint text-[10px] font-bold uppercase tracking-[0.1em] rounded-md border border-mint/20">
                        Conectado
                    </div>
                    <?php else: ?>
                    <div class="px-2.5 py-1 bg-surface border border-stroke text-muted text-[10px] font-bold uppercase tracking-[0.1em] rounded-md">
                        Inativo
                    </div>
                    <?php endif; ?>
                </div>
                <h4 class="text-base font-bold text-text mb-2">HubSpot CRM</h4>
                <p class="text-sm text-subtle mb-6 flex-1">Sincronização bidirecional de leads, notas e atividades operacionais.</p>
                <button class="w-full h-11 flex items-center justify-center <?= $hubspot ? 'bg-surface hover:bg-surface3 text-text border border-stroke' : 'bg-lime text-bg shadow-glow hover:brightness-110 border border-lime' ?> text-sm font-bold rounded-pill transition-all">
                    <?= $hubspot ? 'Configurar Sincronização' : 'Conectar API' ?>
                </button>
            </div>

            <!-- WhatsApp -->
            <div class="bg-surface2 border border-stroke rounded-card p-6 hover:bg-surface3 transition-colors flex flex-col h-full relative overflow-hidden group">
                <div class="flex items-center justify-between mb-6">
                    <div class="size-14 rounded-2xl bg-[#25D366] flex items-center justify-center text-white text-[28px] shadow-sm">
                        <span class="material-symbols-outlined text-[28px]">chat</span>
                    </div>
                    <?php if ($whatsapp): ?>
                    <div class="px-2.5 py-1 bg-mint/10 text-mint text-[10px] font-bold uppercase tracking-[0.1em] rounded-md border border-mint/20">
                        Conectado
                    </div>
                    <?php else: ?>
                    <div class="px-2.5 py-1 bg-surface border border-stroke text-muted text-[10px] font-bold uppercase tracking-[0.1em] rounded-md">
                        Inativo
                    </div>
                    <?php endif; ?>
                </div>
                <h4 class="text-base font-bold text-text mb-2">WhatsApp Cloud API</h4>
                <p class="text-sm text-subtle mb-6 flex-1">Dispare scripts de abordagem em massa e automatize réplicas.</p>
                <button class="w-full h-11 flex items-center justify-center <?= $whatsapp ? 'bg-surface hover:bg-surface3 text-text border border-stroke' : 'bg-lime text-bg shadow-glow hover:brightness-110 border border-lime' ?> text-sm font-bold rounded-pill transition-all">
                    <?= $whatsapp ? 'Gerenciar Webhooks' : 'Conectar API' ?>
                </button>
            </div>

            <!-- Google Calendar -->
            <div class="bg-surface2 border border-stroke rounded-card p-6 hover:bg-surface3 transition-colors flex flex-col h-full relative overflow-hidden group">
                <div class="flex items-center justify-between mb-6">
                    <div class="size-14 rounded-2xl bg-white flex items-center justify-center text-[#4285F4] shadow-sm">
                        <span class="material-symbols-outlined text-[28px]">calendar_today</span>
                    </div>
                    <?php if ($google): ?>
                    <div class="px-2.5 py-1 bg-mint/10 text-mint text-[10px] font-bold uppercase tracking-[0.1em] rounded-md border border-mint/20">
                        Conectado
                    </div>
                    <?php else: ?>
                    <div class="px-2.5 py-1 bg-surface border border-stroke text-muted text-[10px] font-bold uppercase tracking-[0.1em] rounded-md">
                        Inativo
                    </div>
                    <?php endif; ?>
                </div>
                <h4 class="text-base font-bold text-text mb-2">Google Calendar</h4>
                <p class="text-sm text-subtle mb-6 flex-1">Agendamento de reuniões e sincronização bidirecional da agenda.</p>
                <button class="w-full h-11 flex items-center justify-center <?= $google ? 'bg-surface hover:bg-surface3 text-text border border-stroke' : 'bg-lime text-bg shadow-glow hover:brightness-110 border border-lime' ?> text-sm font-bold rounded-pill transition-all">
                    <?= $google ? 'Preferências de Agenda' : 'Login com Google' ?>
                </button>
            </div>

        </div>
    </div>
</div>
