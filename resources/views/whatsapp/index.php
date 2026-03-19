<?php
/**
 * View: WhatsApp — Chat Interface (WhatsApp-style)
 * Layout: Sidebar com contatos + Área de chat principal
 */
?>

<?php if (!$integration): ?>
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- SETUP: Primeira vez — Criar instância + QR Code                          -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div class="space-y-6">
    <div class="flex justify-between items-center bg-zinc-900/50 p-6 rounded-2xl border border-zinc-800 backdrop-blur-sm">
        <div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-green-400 to-emerald-500 bg-clip-text text-transparent">Nexus WhatsApp</h1>
            <p class="text-zinc-400 mt-1">Integração inteligente com Evolution API</p>
        </div>
        <div class="flex items-center gap-3 bg-zinc-800 text-zinc-400 px-4 py-2 rounded-full border border-zinc-700">
            <div class="h-3 w-3 rounded-full bg-zinc-600"></div>
            Desconectado
        </div>
    </div>

    <div class="max-w-2xl mx-auto" id="wa-setup-container">
        <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-8 shadow-2xl">
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-500/10 mb-6 border border-green-500/20">
                    <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Conectar WhatsApp</h2>
                <p class="text-zinc-400">Digite um nome para sua instância e escaneie o QR Code.</p>
            </div>

            <form id="wa-setup-form" class="space-y-6">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <div>
                    <label class="block text-sm font-medium text-zinc-400 mb-2">Nome da Instância</label>
                    <input type="text" name="instance_name" placeholder="ex: operon_vendas_01" required
                           class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-green-500 outline-none transition-all">
                    <p class="text-xs text-zinc-600 mt-1.5">Apenas letras, números, _ e -. Será o identificador da sua conexão.</p>
                </div>
                <button type="submit" id="btn-setup" class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-green-900/20 active:scale-[0.98] flex items-center justify-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Criar Instância e Conectar
                </button>
            </form>

            <div id="qr-inline-area" class="hidden mt-8">
                <div class="border-t border-zinc-800 pt-8 text-center">
                    <h3 class="text-xl font-bold text-white mb-2">Escaneie o QR Code</h3>
                    <p class="text-zinc-400 text-sm mb-6">Abra o WhatsApp no celular → Configurações → Dispositivos Conectados → Conectar Dispositivo</p>
                    <div id="qr-inline-image" class="bg-white p-4 rounded-2xl inline-block shadow-2xl shadow-green-500/10 mb-6"></div>
                    <div id="qr-inline-status" class="flex items-center justify-center gap-3 text-green-400 text-sm font-medium animate-pulse">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        Aguardando leitura do QR Code...
                    </div>
                </div>
            </div>

            <div id="setup-error" class="hidden mt-6 bg-red-900/20 border border-red-500/30 text-red-400 rounded-xl p-4 text-sm"></div>
        </div>
    </div>
</div>

<script>
const WA = {
    csrf: '<?= $csrf ?>',
    statusPollInterval: null,

    init() {
        const form = document.getElementById('wa-setup-form');
        if (form) {
            form.onsubmit = (e) => { e.preventDefault(); this.setup(new FormData(form)); };
        }
    },

    async setup(formData) {
        const btn = document.getElementById('btn-setup');
        const errorDiv = document.getElementById('setup-error');
        errorDiv.classList.add('hidden');
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Criando instância...';
        try {
            const res = await fetch('/whatsapp/setup', { method: 'POST', body: formData });
            const text = await res.text();
            const jsonStart = text.indexOf('{');
            const data = JSON.parse(jsonStart > 0 ? text.substring(jsonStart) : text);
            if (data.success) {
                document.getElementById('wa-setup-form').classList.add('hidden');
                if (data.qr_code) {
                    this.showInlineQr(data.qr_code);
                } else {
                    window.location.reload();
                }
            } else {
                errorDiv.textContent = data.error || 'Erro desconhecido.';
                errorDiv.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = 'Criar Instância e Conectar';
            }
        } catch (err) {
            errorDiv.textContent = 'Erro de rede ou conexão recusada.';
            errorDiv.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = 'Criar Instância e Conectar';
        }
    },

    showInlineQr(qrData) {
        const area = document.getElementById('qr-inline-area');
        const imgContainer = document.getElementById('qr-inline-image');
        let imgSrc = qrData.startsWith('data:') ? qrData : 'data:image/png;base64,' + qrData;
        imgContainer.innerHTML = `<img src="${imgSrc}" class="w-64 h-64" alt="QR Code WhatsApp">`;
        area.classList.remove('hidden');
        this.startStatusPolling();
    },

    startStatusPolling() {
        if (this.statusPollInterval) clearInterval(this.statusPollInterval);
        this.statusPollInterval = setInterval(async () => {
            try {
                const res = await fetch('/whatsapp/status');
                const text = await res.text();
                const j = text.indexOf('{');
                const data = JSON.parse(j > 0 ? text.substring(j) : text);
                if (data.status === 'connected') {
                    clearInterval(this.statusPollInterval);
                    const s = document.getElementById('qr-inline-status');
                    if (s) { s.innerHTML = '<span class="h-3 w-3 rounded-full bg-green-500"></span> Conectado! Recarregando...'; s.classList.remove('animate-pulse'); }
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (e) {}
        }, 4000);
    }
};
document.addEventListener('DOMContentLoaded', () => WA.init());
</script>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- CHAT INTERFACE: Sidebar + Main Chat (WhatsApp-style)                      -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->

<style>
    /* Chat container takes full available height */
    #wa-chat-root { height: calc(100vh - 96px); min-height: 500px; }
    #wa-chat-root .wa-sidebar { width: 380px; min-width: 320px; }
    @media (max-width: 1023px) { #wa-chat-root .wa-sidebar { width: 100%; } }

    /* Scrollbar styling */
    .wa-scroll::-webkit-scrollbar { width: 4px; }
    .wa-scroll::-webkit-scrollbar-track { background: transparent; }
    .wa-scroll::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 99px; }
    .wa-scroll::-webkit-scrollbar-thumb:hover { background: #52525b; }

    /* Contact hover */
    .wa-contact { transition: all 0.15s; }
    .wa-contact:hover { background: rgba(39, 39, 42, 0.8); }
    .wa-contact.active { background: rgba(34, 197, 94, 0.08); border-left: 3px solid #22c55e; }

    /* Message bubbles */
    .wa-msg-in { background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.15); border-radius: 0 16px 16px 16px; }
    .wa-msg-out { background: #27272a; border-radius: 16px 0 16px 16px; }

    /* Typing indicator */
    @keyframes wa-bounce { 0%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-4px); } }
    .wa-typing-dot { animation: wa-bounce 1.4s infinite; }
    .wa-typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .wa-typing-dot:nth-child(3) { animation-delay: 0.4s; }
</style>

<div id="wa-chat-root" class="flex rounded-2xl border border-zinc-800 overflow-hidden bg-zinc-950 shadow-2xl">

    <!-- ═══ SIDEBAR ═══ -->
    <div class="wa-sidebar flex flex-col border-r border-zinc-800 bg-zinc-900/50" id="wa-sidebar">
        <!-- Sidebar Header -->
        <div class="p-4 border-b border-zinc-800 space-y-3 flex-shrink-0">
            <div class="flex justify-between items-center">
                <h1 class="text-lg font-bold bg-gradient-to-r from-green-400 to-emerald-500 bg-clip-text text-transparent">WhatsApp</h1>
                <div class="flex items-center gap-2">
                    <div id="wa-conn-dot" class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                    </div>
                    <button onclick="Chat.sync()" id="btn-sync" title="Sincronizar conversas" class="p-2 rounded-lg hover:bg-zinc-800 text-zinc-500 hover:text-green-400 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                    <button onclick="Chat.toggleSettings(event)" id="wa-menu-trigger" title="Configurações" class="p-2 rounded-lg hover:bg-zinc-800 text-zinc-500 hover:text-zinc-300 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="wa-search" placeholder="Buscar conversa..." class="w-full bg-zinc-950 border border-zinc-800 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white focus:ring-1 focus:ring-green-500/50 outline-none transition-all placeholder-zinc-600">
            </div>
        </div>

        <!-- Contact List -->
        <div class="flex-1 overflow-y-auto wa-scroll" id="wa-contact-list">
            <div class="p-8 text-center text-zinc-600 text-sm">
                <svg class="w-8 h-8 mx-auto mb-3 text-zinc-700 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Carregando...
            </div>
        </div>

        <!-- Sidebar Footer (Stats) -->
        <div class="p-3 border-t border-zinc-800 flex-shrink-0 flex items-center justify-between text-[10px] text-zinc-600 uppercase tracking-wider">
            <span id="wa-total-count"><?= $totalConversations ?> conversas</span>
            <span>Sync: <?= $integration['last_sync_at'] ? date('d/m H:i', strtotime($integration['last_sync_at'])) : 'Nunca' ?></span>
        </div>
    </div>

    <!-- ═══ MAIN CHAT AREA ═══ -->
    <div class="flex-1 flex flex-col min-w-0" id="wa-main">
        <!-- Empty state -->
        <div id="wa-empty-state" class="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="w-24 h-24 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center mb-6">
                <svg class="w-12 h-12 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-zinc-400 mb-2">Nexus WhatsApp</h2>
            <p class="text-zinc-600 text-sm max-w-sm">Selecione uma conversa na lista ao lado para visualizar as mensagens.</p>
            <p class="text-zinc-700 text-xs mt-4">Instância: <span class="font-mono text-zinc-500"><?= htmlspecialchars($integration['instance_name']) ?></span></p>
        </div>

        <!-- Chat View (hidden initially) -->
        <div id="wa-chat-view" class="hidden flex-1 flex flex-col min-h-0">
            <!-- Chat Header -->
            <div class="px-6 py-4 border-b border-zinc-800 flex items-center justify-between flex-shrink-0 bg-zinc-900/30 backdrop-blur-sm">
                <div class="flex items-center gap-4 min-w-0">
                    <button onclick="Chat.closeChat()" class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-zinc-800 text-zinc-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div id="wa-chat-avatar" class="w-10 h-10 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center text-zinc-400 font-bold text-sm flex-shrink-0"></div>
                    <div class="min-w-0">
                        <h2 id="wa-chat-name" class="font-bold text-white truncate"></h2>
                        <p id="wa-chat-phone" class="text-xs text-zinc-500 font-mono truncate"></p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <a id="wa-chat-detail-link" href="#" class="p-2 rounded-lg hover:bg-zinc-800 text-zinc-500 hover:text-green-400 transition-all" title="Abrir detalhes completos">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </a>
                    <span id="wa-chat-lead-badge" class="hidden text-[10px] px-2 py-1 bg-green-500/10 text-green-400 rounded-full border border-green-500/20 truncate max-w-[120px]"></span>
                </div>
            </div>

            <!-- Messages Container -->
            <div id="wa-messages" class="flex-1 overflow-y-auto wa-scroll px-6 py-4 space-y-3">
                <!-- Messages loaded via JS -->
            </div>

            <!-- Sync hint bar -->
            <div id="wa-chat-syncbar" class="hidden px-6 py-2 border-t border-zinc-800 bg-zinc-900/50 flex-shrink-0">
                <div class="flex items-center justify-center gap-2 text-xs text-zinc-500">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Mostrando mensagens sincronizadas. <button onclick="Chat.sync()" class="text-green-500 hover:underline">Sincronizar mais</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Settings dropdown (hidden) -->
<div id="wa-settings-dropdown" class="hidden fixed z-50 bg-zinc-900 border border-zinc-800 rounded-xl shadow-2xl p-2 w-64" style="top:0;left:0;">
    <button onclick="Chat.showConfigModal()" class="w-full text-left px-3 py-2.5 rounded-lg hover:bg-zinc-800 text-sm text-zinc-300 flex items-center gap-3">
        <svg class="w-4 h-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Configurações da Instância
    </button>
    <button onclick="Chat.showQr()" class="w-full text-left px-3 py-2.5 rounded-lg hover:bg-zinc-800 text-sm text-zinc-300 flex items-center gap-3">
        <svg class="w-4 h-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
        Reconectar (QR Code)
    </button>
    <div class="h-px bg-zinc-800 my-1"></div>
    <button onclick="Chat.disconnect()" class="w-full text-left px-3 py-2.5 rounded-lg hover:bg-red-900/20 text-sm text-red-500 flex items-center gap-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Desconectar & Limpar
    </button>
</div>

<!-- Modal Configurações da Instância -->
<div id="modal-config" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[100] flex items-center justify-center p-4">
    <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-8 max-w-md w-full relative">
        <button onclick="document.getElementById('modal-config').classList.add('hidden')" class="absolute top-6 right-6 text-zinc-500 hover:text-white transition-all">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        
        <h3 class="text-2xl font-bold text-white mb-6">Configurações Nexus</h3>
        
        <div class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1.5 ml-1">Nome da Instância</label>
                <div class="bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-3 text-zinc-300 font-mono text-sm">
                    <?= htmlspecialchars($integration['instance_name']) ?>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1.5 ml-1">API Base URL</label>
                <div class="bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-3 text-zinc-300 font-mono text-xs break-all">
                    <?= htmlspecialchars($integration['base_url']) ?>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1.5 ml-1">Status de Conexão</label>
                <div id="wa-config-status" class="flex items-center gap-2 bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-3">
                    <span class="h-2 w-2 rounded-full bg-zinc-600"></span>
                    <span class="text-sm text-zinc-400 capitalize">Verificando...</span>
                </div>
            </div>

            <div class="pt-4 border-t border-zinc-800 flex flex-col gap-3">
                <button onclick="Chat.sync(); document.getElementById('modal-config').classList.add('hidden');" class="w-full bg-zinc-800 hover:bg-zinc-700 text-white font-bold py-3 rounded-xl transition-all border border-zinc-700 text-sm">
                    Forçar Sincronização
                </button>
                <p class="text-[10px] text-zinc-600 text-center">Essas configurações são automáticas. Para alterar o servidor, você deve desconectar e reconectar.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal QR Code -->
<div id="modal-qr" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[100] flex items-center justify-center p-4">
    <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-8 max-w-sm w-full text-center">
        <h3 class="text-xl font-bold text-white mb-2">Escaneie o QR Code</h3>
        <p class="text-zinc-400 text-sm mb-6">WhatsApp → Configurações → Dispositivos Conectados</p>
        <div id="qr-image-container" class="bg-white p-4 rounded-2xl inline-block shadow-2xl shadow-green-500/10 mb-6"></div>
        <div class="flex items-center justify-center gap-3 text-green-400 text-sm font-medium animate-pulse">
            <span class="h-2 w-2 rounded-full bg-green-500"></span>
            Aguardando leitura...
        </div>
        <button onclick="document.getElementById('modal-qr').classList.add('hidden')" class="mt-8 text-zinc-500 hover:text-white transition-all">Cancelar</button>
    </div>
</div>

<script>
const Chat = {
    csrf: '<?= $csrf ?>',
    conversations: [],
    activeConvId: null,
    searchTimeout: null,
    pendingConvId: <?= json_encode($_GET['conversation'] ?? null) ?>,

    // ─── Init ───
    init() {
        this.checkStatus();
        this.loadConversations();

        // Search debounce
        document.getElementById('wa-search').addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => this.loadConversations(e.target.value), 300);
        });

        // Close settings dropdown on outside click
        document.addEventListener('click', (e) => {
            const dd = document.getElementById('wa-settings-dropdown');
            if (!dd.classList.contains('hidden') && !dd.contains(e.target)) {
                dd.classList.add('hidden');
            }
        });
    },

    // ─── Parse JSON with PHP warning tolerance ───
    parseJson(text) {
        const i = text.indexOf('{');
        return JSON.parse(i > 0 ? text.substring(i) : text);
    },

    // ─── Status ───
    async checkStatus() {
        try {
            const res = await fetch('/whatsapp/status');
            const data = this.parseJson(await res.text());
            const dot = document.getElementById('wa-conn-dot');
            if (data.status === 'connected') {
                dot.innerHTML = '<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>';
            } else if (data.status === 'connecting') {
                dot.innerHTML = '<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-yellow-500"></span>';
            } else {
                dot.innerHTML = '<span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-zinc-600"></span>';
            }
        } catch (e) { console.error(e); }
    },

    // ─── Load Conversations ───
    async loadConversations(search = '') {
        const container = document.getElementById('wa-contact-list');
        try {
            const url = '/whatsapp/conversations' + (search ? '?search=' + encodeURIComponent(search) : '');
            const res = await fetch(url);
            const data = this.parseJson(await res.text());

            this.conversations = data.conversations || [];
            document.getElementById('wa-total-count').textContent = (data.total || 0) + ' conversas';

            if (this.conversations.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center">
                            <svg class="w-8 h-8 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <p class="text-zinc-500 text-sm mb-1">${search ? 'Nenhum resultado para "' + search + '"' : 'Nenhuma conversa sincronizada.'}</p>
                        ${!search ? '<button onclick="Chat.sync()" class="text-green-500 text-xs hover:underline mt-2">Sincronizar Agora</button>' : ''}
                    </div>`;
                return;
            }

            let html = '';
            this.conversations.forEach(c => {
                const initial = (c.display_name || '?').charAt(0).toUpperCase();
                const isActive = this.activeConvId === c.id;
                const timeStr = c.last_message_at ? this.formatTime(c.last_message_at) : '';
                const preview = c.last_message_preview || '';
                const truncPreview = preview.length > 45 ? preview.substring(0, 45) + '...' : preview;
                const unread = Number(c.unread_count || 0);

                html += `
                <div class="wa-contact ${isActive ? 'active' : ''} px-4 py-3 cursor-pointer border-b border-zinc-800/30" onclick="Chat.openConversation('${c.id}')">
                    <div class="flex gap-3 items-center">
                        <div class="w-11 h-11 rounded-full bg-zinc-800 flex items-center justify-center text-zinc-400 font-bold text-sm flex-shrink-0 border border-zinc-700 ${isActive ? 'border-green-500/40 text-green-400' : ''}">
                            ${initial}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-baseline mb-0.5">
                                <span class="font-semibold text-sm ${isActive ? 'text-green-400' : 'text-zinc-200'} truncate">${this.esc(c.display_name || 'Sem nome')}</span>
                                <span class="text-[10px] text-zinc-600 flex-shrink-0 ml-2">${timeStr}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-zinc-500 truncate">${this.esc(truncPreview) || '<span class="italic">Sem mensagens</span>'}</span>
                                <div class="flex items-center gap-1.5 flex-shrink-0 ml-2">
                                    ${c.lead_name ? `<span class="w-2 h-2 rounded-full bg-green-500" title="Vinculado: ${this.esc(c.lead_name)}"></span>` : ''}
                                    ${unread > 0 ? `<span class="min-w-[20px] h-5 px-1.5 rounded-full bg-green-500 text-white text-[10px] font-black flex items-center justify-center">${unread}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            });

            container.innerHTML = html;

            if (this.pendingConvId) {
                const targetId = this.pendingConvId;
                this.pendingConvId = null;
                if (this.conversations.some(c => c.id === targetId)) {
                    await this.openConversation(targetId);
                }
            }
        } catch (err) {
            console.error('loadConversations error:', err);
            container.innerHTML = '<div class="p-6 text-center text-red-500 text-sm">Erro ao carregar conversas</div>';
        }
    },

    // ─── Open Conversation ───
    async openConversation(convId) {
        this.activeConvId = convId;
        const conv = this.conversations.find(c => c.id === convId);
        if (!conv) return;

        // Update sidebar active states
        document.querySelectorAll('.wa-contact').forEach(el => el.classList.remove('active'));
        // Re-render to show active state
        const contacts = document.querySelectorAll('.wa-contact');
        const idx = this.conversations.findIndex(c => c.id === convId);
        if (contacts[idx]) contacts[idx].classList.add('active');

        // Mobile: hide sidebar, show chat
        document.getElementById('wa-sidebar').classList.add('lg:flex');
        document.getElementById('wa-empty-state').classList.add('hidden');
        const chatView = document.getElementById('wa-chat-view');
        chatView.classList.remove('hidden');

        // Set header info
        const initial = (conv.display_name || '?').charAt(0).toUpperCase();
        document.getElementById('wa-chat-avatar').textContent = initial;
        document.getElementById('wa-chat-name').textContent = conv.display_name || 'Sem nome';
        document.getElementById('wa-chat-phone').textContent = conv.phone || conv.remote_jid || '';
        document.getElementById('wa-chat-detail-link').href = '/whatsapp/conversation/' + convId;

        const leadBadge = document.getElementById('wa-chat-lead-badge');
        if (conv.lead_name) {
            leadBadge.textContent = conv.lead_name;
            leadBadge.classList.remove('hidden');
        } else {
            leadBadge.classList.add('hidden');
        }

        // Load messages
        const msgContainer = document.getElementById('wa-messages');
        msgContainer.innerHTML = `
            <div class="flex items-center justify-center py-16">
                <svg class="w-6 h-6 text-zinc-700 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>`;

        try {
            const res = await fetch(`/whatsapp/conversation/${convId}/messages`);
            const data = this.parseJson(await res.text());

            if (!data.messages || data.messages.length === 0) {
                msgContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-full text-zinc-600">
                        <svg class="w-10 h-10 mb-3 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-sm">Nenhuma mensagem sincronizada.</p>
                        <button onclick="Chat.sync()" class="text-green-500 text-xs hover:underline mt-2">Sincronizar</button>
                    </div>`;
                document.getElementById('wa-chat-syncbar').classList.add('hidden');
                return;
            }

            // Messages come DESC from API, reverse to ASC for display
            const messages = [...data.messages].reverse();
            let html = '';
            let currentDate = '';

            messages.forEach(msg => {
                const date = new Date(msg.timestamp * 1000);
                const dateStr = date.toLocaleDateString('pt-BR');
                const timeStr = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

                if (dateStr !== currentDate) {
                    currentDate = dateStr;
                    html += `
                        <div class="flex justify-center my-4">
                            <span class="bg-zinc-800/80 text-zinc-500 text-[10px] px-3 py-1 rounded-full uppercase tracking-widest font-bold">${dateStr}</span>
                        </div>`;
                }

                const isOut = msg.direction === 'outgoing';
                html += `
                    <div class="flex ${isOut ? 'justify-end' : 'justify-start'}">
                        <div class="${isOut ? 'wa-msg-out' : 'wa-msg-in'} max-w-[75%] px-4 py-2.5 relative">
                            <p class="text-sm leading-relaxed text-zinc-200 whitespace-pre-wrap break-words">${this.esc(msg.body || '')}</p>
                            <div class="flex items-center justify-end gap-1 mt-1 opacity-40">
                                <span class="text-[9px] font-mono">${timeStr}</span>
                                ${isOut ? '<svg class="w-3 h-3 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>' : ''}
                            </div>
                        </div>
                    </div>`;
            });

            msgContainer.innerHTML = html;
            // Scroll to bottom
            msgContainer.scrollTop = msgContainer.scrollHeight;

            const currentConv = this.conversations.find(c => c.id === convId);
            if (currentConv && Number(currentConv.unread_count || 0) > 0) {
                currentConv.unread_count = 0;
                await this.loadConversations(document.getElementById('wa-search').value);
            }

            // Show sync bar
            document.getElementById('wa-chat-syncbar').classList.remove('hidden');
        } catch (err) {
            console.error('openConversation error:', err);
            msgContainer.innerHTML = '<div class="p-6 text-center text-red-500 text-sm">Erro ao carregar mensagens</div>';
        }
    },

    // ─── Close Chat (mobile) ───
    closeChat() {
        this.activeConvId = null;
        document.getElementById('wa-chat-view').classList.add('hidden');
        document.getElementById('wa-empty-state').classList.remove('hidden');
        document.querySelectorAll('.wa-contact').forEach(el => el.classList.remove('active'));
    },

    // ─── Sync ───
    async sync() {
        const btn = document.getElementById('btn-sync');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>';
        }

        try {
            const res = await fetch('/whatsapp/sync', {
                method: 'POST',
                body: new URLSearchParams({ '_csrf': this.csrf })
            });
            const data = this.parseJson(await res.text());

            if (data.success) {
                // Reload conversations list
                await this.loadConversations(document.getElementById('wa-search').value);
                // If a conversation is open, reload its messages
                if (this.activeConvId) {
                    await this.openConversation(this.activeConvId);
                }
            } else {
                alert('Erro na sincronização: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (err) {
            console.error('sync error:', err);
            alert('Erro de rede ao sincronizar.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
            }
        }
    },

    // ─── Settings Dropdown ───
    toggleSettings(event) {
        if (event) event.stopPropagation();
        const dd = document.getElementById('wa-settings-dropdown');
        if (dd.classList.contains('hidden')) {
            const trigger = event.currentTarget;
            const rect = trigger.getBoundingClientRect();
            dd.style.top = (rect.bottom + 8) + 'px';
            dd.style.left = (rect.right - 256) + 'px';
            dd.classList.remove('hidden');
        } else {
            dd.classList.add('hidden');
        }
    },

    showConfigModal() {
        document.getElementById('wa-settings-dropdown').classList.add('hidden');
        document.getElementById('modal-config').classList.remove('hidden');
        this.updateConfigStatus();
    },

    async updateConfigStatus() {
        const statusArea = document.getElementById('wa-config-status');
        try {
            const res = await fetch('/whatsapp/status');
            const data = this.parseJson(await res.text());
            
            if (data.status === 'connected') {
                statusArea.innerHTML = '<span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span> <span class="text-sm text-green-400 font-bold">Online / Conectado</span>';
            } else {
                statusArea.innerHTML = '<span class="h-2 w-2 rounded-full bg-red-500"></span> <span class="text-sm text-red-500 font-bold">Desconectado</span>';
            }
        } catch (e) {
            statusArea.innerHTML = '<span class="text-xs text-zinc-600 italic">Erro ao verificar status</span>';
        }
    },

    // ─── QR Code (Reconnect) ───
    async showQr() {
        document.getElementById('wa-settings-dropdown').classList.add('hidden');
        try {
            const res = await fetch('/whatsapp/connect', {
                method: 'POST',
                body: new URLSearchParams({ '_csrf': this.csrf })
            });
            const data = this.parseJson(await res.text());
            if (data.success && data.qr_code) {
                document.getElementById('modal-qr').classList.remove('hidden');
                let imgSrc = data.qr_code.startsWith('data:') ? data.qr_code : 'data:image/png;base64,' + data.qr_code;
                document.getElementById('qr-image-container').innerHTML = `<img src="${imgSrc}" class="w-64 h-64">`;
                const interval = setInterval(async () => {
                    try {
                        const sr = await fetch('/whatsapp/status');
                        const sd = this.parseJson(await sr.text());
                        if (sd.status === 'connected') { clearInterval(interval); window.location.reload(); }
                    } catch(e) {}
                    if (document.getElementById('modal-qr').classList.contains('hidden')) clearInterval(interval);
                }, 5000);
            } else if (data.status === 'already_connected') {
                this.checkStatus();
            }
        } catch (err) { console.error(err); }
    },

    // ─── Disconnect ───
    async disconnect() {
        document.getElementById('wa-settings-dropdown').classList.add('hidden');
        if (!confirm('Deseja realmente desconectar e REMOVER esta instância?')) return;
        try {
            await fetch('/whatsapp/disconnect', {
                method: 'POST',
                body: new URLSearchParams({ '_csrf': this.csrf })
            });
            window.location.reload();
        } catch (err) { window.location.reload(); }
    },

    // ─── Helpers ───
    esc(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    formatTime(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        const now = new Date();
        const diff = now - d;

        if (diff < 86400000 && d.getDate() === now.getDate()) {
            return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        if (diff < 172800000) return 'Ontem';
        if (diff < 604800000) {
            return d.toLocaleDateString('pt-BR', { weekday: 'short' });
        }
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    }
};

document.addEventListener('DOMContentLoaded', () => Chat.init());
</script>

<?php endif; ?>
