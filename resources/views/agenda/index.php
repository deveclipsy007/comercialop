<?php $pageTitle = 'Agenda'; $pageSubtitle = 'Follow-ups e Lembretes'; ?>

<div class="h-full flex flex-col md:flex-row bg-bg">
    <!-- Main Calendar Area -->
    <div class="flex-1 p-6 md:p-8 overflow-y-auto border-r border-stroke flex flex-col min-w-0">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-text flex items-center gap-3">
                <div class="size-10 rounded-full bg-lime/10 border border-lime/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lime text-[20px]">calendar_month</span>
                </div>
                <span id="currentMonthLabel" class="tracking-tight">Mês Calendário</span>
            </h2>
            <div class="flex items-center gap-2">
                <button id="prevMonthBtn" class="size-10 bg-surface border border-stroke hover:bg-surface2 rounded-full flex items-center justify-center text-muted hover:text-text transition-all">
                    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                </button>
                <button id="todayBtn" class="h-10 px-5 bg-surface border border-stroke hover:bg-surface2 rounded-pill text-sm font-bold text-text transition-all">
                    Hoje
                </button>
                <button id="nextMonthBtn" class="size-10 bg-surface border border-stroke hover:bg-surface2 rounded-full flex items-center justify-center text-muted hover:text-text transition-all">
                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                </button>
            </div>
        </div>

        <!-- Days of Week Header -->
        <div class="grid grid-cols-7 gap-px bg-stroke border border-stroke rounded-t-card overflow-hidden">
            <?php foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $day): ?>
                <div class="bg-surface2 py-3 text-center text-[10px] font-bold text-muted uppercase tracking-[0.1em]">
                    <?= $day ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Calendar Grid -->
        <div id="calendarGrid" class="grid grid-cols-7 gap-px bg-stroke border-x border-b border-stroke rounded-b-card overflow-hidden flex-1 min-h-[500px]">
            <!-- JS vai renderizar as células do mês aqui -->
        </div>
    </div>

    <!-- Sidebar: Event List -->
    <div class="w-full md:w-[360px] lg:w-[400px] flex flex-col p-6 md:p-8 overflow-y-auto bg-bg border-l border-stroke">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-xl font-bold text-text">Próximos</h3>
            <button onclick="openEventModal()" class="size-10 bg-lime hover:brightness-110 rounded-full flex items-center justify-center text-bg shadow-glow transition-all" title="Adicionar Lembrete">
                <span class="material-symbols-outlined text-[22px]">add</span>
            </button>
        </div>

        <div class="flex-1 flex flex-col gap-4">
            <?php if (empty($allEvents)): ?>
                <div class="flex flex-col items-center justify-center py-16 bg-surface border border-dashed border-stroke rounded-cardLg text-center">
                    <span class="material-symbols-outlined text-4xl text-stroke mb-3 block">event_busy</span>
                    <p class="text-sm font-medium text-subtle">A agenda está limpa.</p>
                </div>
            <?php else: ?>
                <?php 
                $todayDate = date('Y-m-d');
                foreach ($allEvents as $ev): 
                    $isPast = strtotime($ev['start_time']) < time();
                    $eventDate = date('Y-m-d', strtotime($ev['start_time']));
                    $displayDate = $eventDate === $todayDate ? 'Hoje' : date('d/m/Y', strtotime($ev['start_time']));
                ?>
                <div class="bg-surface2 border border-stroke rounded-card p-5 flex flex-col gap-3 hover:bg-surface3 transition-all <?= $isPast ? 'opacity-50 grayscale hover:grayscale-0' : '' ?> group">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3.5">
                            <?php 
                                $iconBg = strpos($ev['color'] ?? '', 'primary') !== false ? 'bg-mint/10 text-mint border border-mint/20' : 
                                         (strpos($ev['color'] ?? '', 'blue') !== false ? 'bg-[#60A5FA]/10 text-[#60A5FA] border border-[#60A5FA]/20' : 'bg-surface border border-stroke text-muted');
                            ?>
                            <div class="size-10 rounded-full flex items-center justify-center flex-shrink-0 <?= $iconBg ?>">
                                <span class="material-symbols-outlined text-[18px]"><?= $ev['icon'] ?></span>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-text leading-tight group-hover:text-lime transition-colors"><?= e($ev['title']) ?></h4>
                                <?php if ($ev['lead_name']): ?>
                                <p class="text-[10px] text-muted font-bold uppercase tracking-[0.05em] mt-1">Lead: <a href="/vault/<?= $ev['lead_id'] ?>" class="text-subtle hover:text-lime hover:underline transition-colors"><?= e($ev['lead_name']) ?></a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-text"><?= date('H:i', strtotime($ev['start_time'])) ?></p>
                            <p class="text-[10px] font-medium text-subtle mt-0.5"><?= $displayDate ?></p>
                        </div>
                    </div>
                    <?php if ($ev['description']): ?>
                        <p class="text-xs text-subtle mt-1.5 pl-[54px] leading-relaxed relative before:absolute before:left-6 before:top-0 before:bottom-0 before:w-px before:bg-stroke"><?= e($ev['description']) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($ev['type'] !== 'followup'): // Follow-up is strictly managed via Lead ?>
                    <form method="POST" action="/agenda/event/<?= $ev['id'] ?>/delete" class="hidden group-hover:flex justify-end mt-3 pt-3 border-t border-stroke">
                        <?= csrf_field() ?>
                        <button type="submit" onclick="return confirm('Tem certeza que deseja apagar?')" class="text-[10px] font-bold text-red-500 hover:text-red-400 transition-colors uppercase tracking-[0.1em] flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">delete</span> Excluir
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Criar Evento Genérico -->
<div id="eventModal" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4 bg-bg/80 backdrop-blur-md">
    <div class="w-full max-w-md bg-surface border border-stroke rounded-cardLg shadow-soft animate-popIn overflow-hidden p-7">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-stroke">
            <h3 class="text-lg font-bold text-text flex items-center gap-2">
                <span class="material-symbols-outlined text-lime text-[20px]">event_available</span> Novo Registro
            </h3>
            <button onclick="closeEventModal()" class="size-8 rounded-full bg-surface2 border border-stroke text-muted hover:text-text flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <form method="POST" action="/agenda/event" class="flex flex-col gap-5">
            <?= csrf_field() ?>
            
            <div>
                <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Título do Evento *</label>
                <input type="text" name="title" required placeholder="Ex: Ligar para fornecedor..." class="w-full h-11 bg-surface2 border border-stroke rounded-pill px-5 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors placeholder:text-muted">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Data *</label>
                    <input type="date" name="start_date" id="modalDate" required class="w-full h-11 bg-surface2 border border-stroke rounded-pill px-5 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors [color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Hora *</label>
                    <input type="time" name="start_time" required class="w-full h-11 bg-surface2 border border-stroke rounded-pill px-5 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors [color-scheme:dark]">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Tipo *</label>
                <select name="event_type" class="w-full h-11 bg-surface2 border border-stroke rounded-pill px-5 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors appearance-none cursor-pointer">
                    <option value="reminder">🔔 Lembrete Ad-hoc</option>
                    <option value="appointment">🤝 Compromisso / Call</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-muted uppercase tracking-[0.1em] mb-2">Descrição Curta</label>
                <textarea name="description" rows="3" placeholder="Pauta ou anotações rápidas..." class="w-full bg-surface2 border border-stroke rounded-2xl p-4 text-sm text-text focus:outline-none focus:border-lime/50 transition-colors resize-none placeholder:text-muted"></textarea>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full h-12 flex items-center justify-center gap-2 bg-lime hover:brightness-110 text-bg text-sm font-bold rounded-pill shadow-glow transition-all">
                    Salvar Evento na Rede
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Remove spinner arrows from date/time inputs for a cleaner look */
input[type="date"]::-webkit-calendar-picker-indicator,
input[type="time"]::-webkit-calendar-picker-indicator {
    filter: invert(0.6);
    cursor: pointer;
}
</style>

<script>
// Dados brutos vindos do PHP convertidos para Object JavaScript
const allEvents = <?= $eventsJson ?? '[]' ?>;

// Setup do Calendário Visual
const calendarGrid = document.getElementById('calendarGrid');
const currentMonthLabel = document.getElementById('currentMonthLabel');
let currentDate = new Date();

function renderCalendar() {
    calendarGrid.innerHTML = '';
    
    // Configura infos cruciais do mes
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth(); // 0 a 11
    
    // Update Label
    const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
    currentMonthLabel.innerText = `${monthNames[month]} ${year}`;
    
    // Primeiro dia do mes (0-domingo, 1-segunda...)
    const firstDayIndex = new Date(year, month, 1).getDay();
    // Último dia do mes
    const lastDayText = new Date(year, month + 1, 0).getDate();
    // Dias do mês anterior para preencher buracos
    const prevLastDayText = new Date(year, month, 0).getDate();
    
    // Preenchendo buracos do mes passado
    for (let x = firstDayIndex; x > 0; x--) {
        const dayNum = prevLastDayText - x + 1;
        createCell(year, month - 1, dayNum, true);
    }
    
    // Preenchendo o mês atual
    const today = new Date();
    for (let i = 1; i <= lastDayText; i++) {
        const isToday = i === today.getDate() && month === today.getMonth() && year === today.getFullYear();
        createCell(year, month, i, false, isToday);
    }
    
    // Preenchendo buracos final (ate fechar a linha das 35/42 celulas)
    const currentCells = firstDayIndex + lastDayText;
    const endDays = 42 - currentCells;
    for (let j = 1; j <= endDays; j++) {
        createCell(year, month + 1, j, true);
    }
}

function createCell(y, m, d, isMuted, isToday = false) {
    // Ajuste de virada de ano e mes para gerar ISO date string correto 'Y-m-d'
    const cleanDate = new Date(y, m, d);
    const cellDateStr = cleanDate.toLocaleDateString('en-CA'); // retorna formato yyyy-mm-dd
    
    // Filtrar eventos pro dia atual iterado
    const dayEvents = allEvents.filter(ev => ev.start_time.startsWith(cellDateStr));
    
    // UI Create
    const cell = document.createElement('div');
    cell.className = `min-h-[110px] p-2 bg-surface relative group cursor-pointer hover:bg-surface2 transition-colors 
        ${isMuted ? 'opacity-30' : ''}`;
    
    // Click action -> Abrir modal preenchendo o dia
    cell.onclick = () => {
        openEventModal();
        document.getElementById('modalDate').value = cellDateStr;
    };
    
    // Number indicator
    const numDiv = document.createElement('div');
    numDiv.className = `text-[11px] font-bold mb-1.5 w-6 h-6 flex items-center justify-center rounded-full transition-all
        ${isToday ? 'bg-lime text-bg shadow-[0_0_8px_rgba(225,251,21,0.5)] z-10 relative' : 'text-muted group-hover:text-text'}`;
    numDiv.innerText = d;
    cell.appendChild(numDiv);
    
    // Render Events (max 3, plus indicator)
    const eventsContainer = document.createElement('div');
    eventsContainer.className = 'flex flex-col gap-1.5 mt-1';
    
    for (let i = 0; i < Math.min(dayEvents.length, 3); i++) {
        const ev = dayEvents[i];
        let colorClass = ev.type === 'followup' ? 'bg-mint/10 text-mint border border-mint/20' : 
                         (ev.type === 'appointment' ? 'bg-[#60A5FA]/10 text-[#60A5FA] border border-[#60A5FA]/20' : 'bg-surface3 border border-stroke text-text');
        
        eventsContainer.innerHTML += `
            <div class="px-2 py-1 ${colorClass} rounded-md text-[9px] font-bold tracking-wide truncate leading-tight shadow-sm" title="${ev.title}">
                <span class="opacity-70 mr-0.5 font-mono">${ev.start_time.substring(11, 16)}</span> ${ev.title}
            </div>
        `;
    }
    
    if (dayEvents.length > 3) {
        eventsContainer.innerHTML += `<div class="text-[9px] text-muted font-bold px-1.5">+${dayEvents.length - 3} itens ocultos</div>`;
    }
    
    cell.appendChild(eventsContainer);
    calendarGrid.appendChild(cell);
}

// Botoes Navegacao
document.getElementById('prevMonthBtn').onclick = () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
};

document.getElementById('nextMonthBtn').onclick = () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
};

document.getElementById('todayBtn').onclick = () => {
    currentDate = new Date();
    renderCalendar();
};

// Modal Logic
const eventModal = document.getElementById('eventModal');
function openEventModal() {
    eventModal.classList.remove('hidden');
    eventModal.classList.add('flex');
    if(!document.getElementById('modalDate').value) {
       document.getElementById('modalDate').value = new Date().toLocaleDateString('en-CA');
    }
}
function closeEventModal() {
    eventModal.classList.remove('flex');
    eventModal.classList.add('hidden');
}

// Init
renderCalendar();
</script>
