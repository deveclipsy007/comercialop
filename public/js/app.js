/**
 * Operon Intelligence Platform — Main JavaScript
 * Design System: Intelligence Dark Tech
 */

// ── Sidebar Toggle ──────────────────────────────────────────
const sidebar     = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const mainContent = document.getElementById('mainContent');

if (sidebarToggle && sidebar) {
    const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (collapsed) applySidebarCollapse(true);

    sidebarToggle.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        applySidebarCollapse(!isCollapsed);
    });
}

function applySidebarCollapse(collapse) {
    if (!sidebar || !mainContent) return;
    if (collapse) {
        sidebar.classList.add('sidebar-collapsed');
        mainContent.classList.remove('ml-64');
        mainContent.classList.add('ml-16');
        localStorage.setItem('sidebarCollapsed', 'true');
    } else {
        sidebar.classList.remove('sidebar-collapsed');
        mainContent.classList.remove('ml-16');
        mainContent.classList.add('ml-64');
        localStorage.setItem('sidebarCollapsed', 'false');
    }
}

// ── Flash Messages Auto-dismiss ────────────────────────────
document.querySelectorAll('.flash-message').forEach(el => {
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(-10px)';
        el.style.transition = 'all 0.3s ease';
        setTimeout(() => el.remove(), 300);
    }, 4000);
});

// ── CSRF Token Helper ──────────────────────────────────────
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ── Fetch Helper with CSRF ─────────────────────────────────
async function operonFetch(url, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken(),
            'Accept': 'application/json',
        },
    };
    const response = await fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers || {}) } });
    return response.json();
}

// ── AI Loading Overlay ─────────────────────────────────────
function showAILoading(container, message = 'Operon Intelligence processando...') {
    if (!container) return;
    container.innerHTML = `
        <div class="flex flex-col items-center justify-center gap-4 py-12">
            <div class="relative">
                <div class="ai-spinner" style="width:40px;height:40px;border-width:3px;"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[#18C29C] text-sm">psychology</span>
                </div>
            </div>
            <p class="text-sm text-slate-400 animate-pulse">${message}</p>
        </div>
    `;
}

// ── Score Color Utility ────────────────────────────────────
function scoreColor(score) {
    if (score >= 70) return '#18C29C';
    if (score >= 40) return '#F59E0B';
    return '#E11D48';
}

function scoreBgClass(score) {
    if (score >= 70) return 'score-high';
    if (score >= 40) return 'score-medium';
    return 'score-low';
}

// ── Kanban Drag & Drop ─────────────────────────────────────
let draggedCard = null;
let draggedLeadId = null;
let draggedFromStage = null;

document.addEventListener('DOMContentLoaded', () => {
    initKanban();
    initTooltips();
    initCountUpAnimations();
});

function initKanban() {
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.setAttribute('draggable', 'true');
        card.addEventListener('dragstart', onDragStart);
        card.addEventListener('dragend', onDragEnd);
    });

    document.querySelectorAll('.kanban-column').forEach(col => {
        col.addEventListener('dragover', onDragOver);
        col.addEventListener('drop', onDrop);
        col.addEventListener('dragleave', onDragLeave);
    });
}

function onDragStart(e) {
    draggedCard   = e.currentTarget;
    draggedLeadId = e.currentTarget.dataset.leadId;
    draggedFromStage = e.currentTarget.dataset.stage;
    e.currentTarget.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function onDragEnd(e) {
    e.currentTarget.classList.remove('dragging');
    document.querySelectorAll('.kanban-column').forEach(col => {
        col.classList.remove('bg-white/5', 'border-[#18C29C]/30');
    });
}

function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('bg-white/5', 'border-[#18C29C]/30');
}

function onDragLeave(e) {
    e.currentTarget.classList.remove('bg-white/5', 'border-[#18C29C]/30');
}

async function onDrop(e) {
    e.preventDefault();
    const col = e.currentTarget;
    col.classList.remove('bg-white/5', 'border-[#18C29C]/30');
    const newStage = col.dataset.stage;

    if (!draggedLeadId || !newStage || newStage === draggedFromStage) return;

    try {
        const result = await operonFetch(`/vault/${draggedLeadId}/stage`, {
            method: 'POST',
            body: JSON.stringify({ stage: newStage, _csrf: getCsrfToken() }),
        });

        if (result.success) {
            // Move card visually
            const cardsContainer = col.querySelector('.kanban-cards');
            if (cardsContainer && draggedCard) {
                draggedCard.dataset.stage = newStage;
                cardsContainer.appendChild(draggedCard);
                updateColumnCount(draggedFromStage);
                updateColumnCount(newStage);
            }
        }
    } catch (err) {
        console.error('Stage update failed:', err);
    }
}

function updateColumnCount(stage) {
    const col = document.querySelector(`[data-stage="${stage}"]`);
    if (!col) return;
    const count = col.querySelectorAll('.kanban-card').length;
    const badge = col.querySelector('.column-count');
    if (badge) badge.textContent = count;
}

// ── Tooltips ───────────────────────────────────────────────
function initTooltips() {
    // Native [data-tooltip] handled by CSS
}

// ── Count Up Animations ────────────────────────────────────
function initCountUpAnimations() {
    document.querySelectorAll('[data-countup]').forEach(el => {
        const target = parseInt(el.dataset.countup, 10);
        if (isNaN(target)) return;
        let start = 0;
        const duration = 1200;
        const step = target / (duration / 16);
        const timer = setInterval(() => {
            start = Math.min(start + step, target);
            el.textContent = Math.floor(start).toLocaleString('pt-BR');
            if (start >= target) clearInterval(timer);
        }, 16);
    });
}

// ── Modal Helper ───────────────────────────────────────────
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

// Close modal on backdrop click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.closest('.modal-backdrop').classList.add('hidden');
        document.body.style.overflow = '';
    }
});

// Close modal on ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop:not(.hidden)').forEach(modal => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        });
    }
});

// ── AI Trigger Buttons ─────────────────────────────────────
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-ai-action]');
    if (!btn) return;

    const action = btn.dataset.aiAction;
    const leadId = btn.dataset.leadId;
    const resultContainer = document.getElementById(btn.dataset.resultTarget);

    if (!action || !leadId) return;

    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');

    if (resultContainer) showAILoading(resultContainer);

    try {
        const url = `/vault/${leadId}/${action}`;
        const result = await operonFetch(url, { method: 'POST', body: JSON.stringify({ _csrf: getCsrfToken() }) });

        if (resultContainer && result) {
            renderAIResult(resultContainer, result, action);
        }
    } catch (err) {
        if (resultContainer) {
            resultContainer.innerHTML = `<p class="text-red-400 text-sm p-4">Erro ao processar. Tente novamente.</p>`;
        }
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
});

function renderAIResult(container, data, action) {
    if (data.error) {
        container.innerHTML = `<div class="p-4 rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 text-sm">${data.error}</div>`;
        return;
    }
    // Generic JSON display for now — views override this per module
    container.innerHTML = `<pre class="text-xs text-slate-300 overflow-auto p-4">${JSON.stringify(data, null, 2)}</pre>`;
}

// ── Token Bar Update ───────────────────────────────────────
async function refreshTokenBar() {
    try {
        const data = await operonFetch('/api/tokens');
        const bar   = document.getElementById('tokenBar');
        const label = document.getElementById('tokenLabel');
        if (!bar || !data) return;
        const pct = Math.min(100, Math.round((data.used / data.limit) * 100));
        bar.style.width = pct + '%';
        if (label) label.textContent = `${data.used}/${data.limit}`;
        if (pct >= 90) bar.className = bar.className.replace('token-bar-fill', 'token-bar-fill critical');
        else if (pct >= 70) bar.className = bar.className.replace('token-bar-fill', 'token-bar-fill warning');
    } catch (e) {}
}

// ── Confirm Dialog Helper ──────────────────────────────────
function confirmAction(message, callback) {
    if (window.confirm(message)) callback();
}

// ── Copy to Clipboard ──────────────────────────────────────
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">check</span>';
            btn.classList.add('text-[#18C29C]');
            setTimeout(() => {
                btn.innerHTML = original;
                btn.classList.remove('text-[#18C29C]');
            }, 1500);
        }
    });
}
