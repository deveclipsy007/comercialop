/**
 * Operon Capture — Popup Logic
 * UI principal: login + formulário de captura + modo bulk
 */

// ── State ──
let currentSource = 'generic';
let extractedData = null;
let forceCreate = false;
let platformUrl = '';

// Bulk state
let bulkLeads = [];        // Array de leads extraídos
let bulkDuplicates = {};   // { index: existingLead }
let bulkSelected = new Set(); // Índices selecionados

// ── Init ──
document.addEventListener('DOMContentLoaded', async () => {
  // Check auth state
  const authState = await sendMessage({ type: 'CHECK_AUTH' });

  if (authState?.authenticated) {
    showCaptureScreen();
  } else {
    showLoginScreen();
  }

  // Event listeners — Single mode
  document.getElementById('loginForm').addEventListener('submit', handleLogin);
  document.getElementById('captureForm').addEventListener('submit', handleCapture);
  document.getElementById('btnLogout').addEventListener('click', handleLogout);
  document.getElementById('btnForceCreate').addEventListener('click', () => {
    forceCreate = true;
    document.getElementById('duplicateWarning').style.display = 'none';
    document.getElementById('captureForm').dispatchEvent(new Event('submit'));
  });

  // Event listeners — Bulk mode
  document.getElementById('btnBulkMode').addEventListener('click', handleBulkMode);
  document.getElementById('btnBackToSingle').addEventListener('click', showCaptureScreenFromBulk);
  document.getElementById('btnBulkCapture').addEventListener('click', handleBulkCapture);
  document.getElementById('bulkSelectAll').addEventListener('change', handleBulkSelectAll);
});

// ── Screens ──
function showLoginScreen() {
  document.getElementById('loginScreen').style.display = 'block';
  document.getElementById('captureScreen').style.display = 'none';
  document.getElementById('bulkScreen').style.display = 'none';

  // Restore saved server URL
  chrome.storage.local.get('operon_server_url', (result) => {
    if (result.operon_server_url) {
      document.getElementById('serverUrl').value = result.operon_server_url;
    }
  });
}

async function showCaptureScreen() {
  document.getElementById('loginScreen').style.display = 'none';
  document.getElementById('captureScreen').style.display = 'block';
  document.getElementById('bulkScreen').style.display = 'none';

  // Load user info
  const me = await sendMessage({ type: 'GET_ME' });
  if (me?.auth_expired) {
    showLoginScreen();
    return;
  }
  if (me?.success) {
    document.getElementById('userName').textContent = me.user_name || 'Usuário';
    document.getElementById('tenantName').textContent = me.tenant_name || '';
    platformUrl = me.platform_url || '';
  }

  // Load segments for autocomplete
  const segs = await sendMessage({ type: 'GET_SEGMENTS' });
  if (segs?.segments) {
    const datalist = document.getElementById('segmentList');
    datalist.innerHTML = '';
    segs.segments.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s;
      datalist.appendChild(opt);
    });
  }

  // Extract data from current page
  await extractPageData();
}

function showCaptureScreenFromBulk() {
  document.getElementById('bulkScreen').style.display = 'none';
  document.getElementById('captureScreen').style.display = 'block';
}

// ── Login Handler ──
async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('btnLogin');
  const errorEl = document.getElementById('loginError');
  errorEl.style.display = 'none';

  const serverUrl = document.getElementById('serverUrl').value.trim().replace(/\/+$/, '');
  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;

  if (!serverUrl || !email || !password) {
    showError(errorEl, 'Preencha todos os campos.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined spin">progress_activity</span> Conectando...';

  const result = await sendMessage({ type: 'LOGIN', serverUrl, email, password });

  if (result?.success) {
    showCaptureScreen();
  } else {
    showError(errorEl, result?.message || 'Falha na autenticação.');
  }

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined">login</span> Conectar';
}

// ── Logout ──
async function handleLogout() {
  await sendMessage({ type: 'LOGOUT' });
  showLoginScreen();
}

// ── Extract Page Data ──
async function extractPageData() {
  // 1. Detectar se estamos no Google Maps pela URL da tab ativa
  let isGoogleMaps = false;
  try {
    const [tab] = await new Promise(resolve => {
      chrome.tabs.query({ active: true, currentWindow: true }, resolve);
    });
    if (tab?.url) {
      isGoogleMaps = /google\.(com|com\.br)\/maps/i.test(tab.url) ||
                     /maps\.google\./i.test(tab.url);
    }
  } catch {}

  // 2. Tentar extrair dados da página (single lead)
  const result = await sendMessage({ type: 'GET_EXTRACTED_DATA' });

  if (result?.data) {
    extractedData = result.data;
    currentSource = result.source || (isGoogleMaps ? 'google-maps' : 'generic');
    fillForm(result.data);
    updateSourceBadge(currentSource, result.url);
  } else {
    currentSource = isGoogleMaps ? 'google-maps' : 'generic';
    updateSourceBadge(currentSource, '');
  }

  // 3. Mostrar botão de bulk mode no Google Maps (independente da extração single)
  const bulkArea = document.getElementById('bulkToggleArea');
  if (isGoogleMaps) {
    bulkArea.style.display = 'block';
  } else {
    bulkArea.style.display = 'none';
  }
}

function fillForm(data) {
  if (!data) return;

  const fields = {
    capName: data.name,
    capSegment: data.segment || data.category,
    capCategory: data.category,
    capPhone: data.phone,
    capEmail: data.email,
    capWebsite: data.website,
    capAddress: data.address,
    capCargo: data.cargo,
    capRating: data.rating,
  };

  for (const [id, value] of Object.entries(fields)) {
    if (value) {
      const el = document.getElementById(id);
      if (el) el.value = String(value).trim();
    }
  }
}

function updateSourceBadge(source, url) {
  const nameEl = document.getElementById('sourceName');
  const icons = {
    'google-maps': { name: 'Google Maps', icon: 'map' },
    'linkedin': { name: 'LinkedIn', icon: 'work' },
    'instagram': { name: 'Instagram', icon: 'photo_camera' },
    'generic': { name: 'Website', icon: 'language' },
  };

  const info = icons[source] || icons.generic;
  nameEl.textContent = info.name;

  const badge = document.getElementById('sourceLabel');
  badge.querySelector('.material-symbols-outlined').textContent = info.icon;
}

// ══════════════════════════════════════════════════════════════
// BULK MODE
// ══════════════════════════════════════════════════════════════

async function handleBulkMode() {
  // Trocar para tela bulk
  document.getElementById('captureScreen').style.display = 'none';
  document.getElementById('bulkScreen').style.display = 'block';

  // Resetar estado
  bulkLeads = [];
  bulkDuplicates = {};
  bulkSelected = new Set();

  // Mostrar loading
  const loadingEl = document.getElementById('bulkLoading');
  const tableWrapper = document.getElementById('bulkTableWrapper');
  const errorEl = document.getElementById('bulkError');
  const successEl = document.getElementById('bulkSuccess');
  loadingEl.style.display = 'flex';
  tableWrapper.style.display = 'none';
  errorEl.style.display = 'none';
  successEl.style.display = 'none';
  document.getElementById('btnBulkCapture').disabled = true;

  document.getElementById('bulkLoadingText').textContent = 'Extraindo resultados da página...';

  // 1. Extrair todos os resultados
  const extractResult = await sendMessage({ type: 'EXTRACT_ALL_DATA' });

  if (!extractResult?.data || extractResult.data.length === 0) {
    loadingEl.style.display = 'none';
    showError(errorEl, 'Nenhum resultado encontrado na página. Faça uma busca no Google Maps primeiro.');
    return;
  }

  bulkLeads = extractResult.data;
  document.getElementById('bulkTotalCount').textContent = bulkLeads.length;

  // 2. Verificar duplicatas no servidor
  document.getElementById('bulkLoadingText').textContent = `Verificando ${bulkLeads.length} leads no Vault...`;

  const dupResult = await sendMessage({ type: 'CHECK_BULK_DUPLICATES', leads: bulkLeads });

  if (dupResult?.results) {
    let dupCount = 0;
    for (const r of dupResult.results) {
      if (r.exists) {
        bulkDuplicates[r.index] = r.existing;
        dupCount++;
      }
    }
    document.getElementById('bulkDuplicateCount').textContent = dupCount;
    document.getElementById('bulkNewCount').textContent = bulkLeads.length - dupCount;
  } else {
    document.getElementById('bulkDuplicateCount').textContent = '?';
    document.getElementById('bulkNewCount').textContent = '?';
  }

  // 3. Preencher tabela
  loadingEl.style.display = 'none';
  tableWrapper.style.display = 'block';
  renderBulkTable();

  // Pré-selecionar apenas novos (não-duplicados)
  bulkSelected = new Set();
  bulkLeads.forEach((_, i) => {
    if (!bulkDuplicates[i]) {
      bulkSelected.add(i);
    }
  });
  updateBulkCheckboxes();
  updateBulkCaptureButton();

  // Preencher segmento com categoria mais comum
  const categories = bulkLeads.map(l => l.category).filter(Boolean);
  if (categories.length > 0) {
    const freq = {};
    categories.forEach(c => { freq[c] = (freq[c] || 0) + 1; });
    const mostCommon = Object.entries(freq).sort((a, b) => b[1] - a[1])[0][0];
    document.getElementById('bulkSegment').value = mostCommon;
  }
}

function renderBulkTable() {
  const tbody = document.getElementById('bulkTableBody');
  tbody.innerHTML = '';

  bulkLeads.forEach((lead, index) => {
    const isDuplicate = !!bulkDuplicates[index];
    const tr = document.createElement('tr');
    tr.className = isDuplicate ? 'is-duplicate' : '';
    tr.dataset.index = index;

    tr.innerHTML = `
      <td class="col-check">
        <input type="checkbox" class="bulk-row-check" data-index="${index}"
               ${!isDuplicate ? 'checked' : ''}>
      </td>
      <td class="col-name">
        <span class="lead-name" title="${escapeHtml(lead.name)}">${escapeHtml(lead.name)}</span>
        <span class="lead-category">${escapeHtml(lead.category || lead.address || '')}</span>
      </td>
      <td class="col-rating">
        ${lead.rating ? `<span class="lead-rating">${lead.rating}</span>` : '—'}
      </td>
      <td class="col-status">
        ${isDuplicate
          ? '<span class="status-duplicate">Existe</span>'
          : '<span class="status-new">Novo</span>'
        }
      </td>
    `;

    tbody.appendChild(tr);
  });

  // Event listeners para checkboxes individuais
  tbody.querySelectorAll('.bulk-row-check').forEach(cb => {
    cb.addEventListener('change', (e) => {
      const idx = parseInt(e.target.dataset.index);
      if (e.target.checked) {
        bulkSelected.add(idx);
      } else {
        bulkSelected.delete(idx);
      }
      updateBulkSelectAllState();
      updateBulkCaptureButton();
    });
  });
}

function handleBulkSelectAll(e) {
  const checked = e.target.checked;
  bulkSelected = new Set();

  if (checked) {
    bulkLeads.forEach((_, i) => bulkSelected.add(i));
  }

  updateBulkCheckboxes();
  updateBulkCaptureButton();
}

function updateBulkCheckboxes() {
  document.querySelectorAll('.bulk-row-check').forEach(cb => {
    const idx = parseInt(cb.dataset.index);
    cb.checked = bulkSelected.has(idx);
  });
  updateBulkSelectAllState();
}

function updateBulkSelectAllState() {
  const selectAll = document.getElementById('bulkSelectAll');
  if (bulkSelected.size === bulkLeads.length) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else if (bulkSelected.size === 0) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  } else {
    selectAll.checked = false;
    selectAll.indeterminate = true;
  }
  document.getElementById('bulkSelectedCount').textContent = bulkSelected.size;
}

function updateBulkCaptureButton() {
  const btn = document.getElementById('btnBulkCapture');
  const count = bulkSelected.size;
  btn.disabled = count === 0;
  document.getElementById('bulkCaptureLabel').textContent =
    count === 0 ? 'Selecione leads' : `Enviar ${count} lead${count > 1 ? 's' : ''} para o Vault`;
  document.getElementById('bulkSelectedCount').textContent = count;
}

// ── Bulk Capture Handler ──
async function handleBulkCapture() {
  const segment = document.getElementById('bulkSegment').value.trim();
  const errorEl = document.getElementById('bulkError');
  const successEl = document.getElementById('bulkSuccess');
  errorEl.style.display = 'none';
  successEl.style.display = 'none';

  if (!segment) {
    showError(errorEl, 'Defina um segmento para os leads.');
    return;
  }

  if (bulkSelected.size === 0) {
    showError(errorEl, 'Selecione pelo menos um lead.');
    return;
  }

  // Preparar leads selecionados
  const selectedLeads = [];
  for (const idx of bulkSelected) {
    const lead = { ...bulkLeads[idx] };
    lead.segment = segment;
    if (!lead.segment) lead.segment = 'Geral';
    selectedLeads.push(lead);
  }

  // UI de loading
  const btn = document.getElementById('btnBulkCapture');
  btn.disabled = true;
  document.getElementById('bulkCaptureIcon').textContent = 'progress_activity';
  document.getElementById('bulkCaptureIcon').classList.add('spin');
  document.getElementById('bulkCaptureLabel').textContent = `Enviando ${selectedLeads.length} leads...`;

  // Enviar para o backend
  const result = await sendMessage({ type: 'CAPTURE_BULK', leads: selectedLeads });

  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (result?.success && result.summary) {
    const s = result.summary;

    // Atualizar status das linhas na tabela
    if (result.created) {
      for (const c of result.created) {
        markRowStatus(c.index, 'sent');
      }
    }
    if (result.duplicates) {
      for (const d of result.duplicates) {
        markRowStatus(d.index, 'duplicate');
      }
    }
    if (result.errors) {
      for (const e of result.errors) {
        markRowStatus(e.index, 'error');
      }
    }

    // Mostrar sucesso
    successEl.style.display = 'flex';
    document.getElementById('bulkSuccessTitle').textContent =
      `${s.created} lead${s.created > 1 ? 's' : ''} capturado${s.created > 1 ? 's' : ''}!`;

    const details = [];
    if (s.duplicates > 0) details.push(`${s.duplicates} duplicado${s.duplicates > 1 ? 's' : ''}`);
    if (s.errors > 0) details.push(`${s.errors} erro${s.errors > 1 ? 's' : ''}`);
    document.getElementById('bulkSuccessDetail').textContent =
      details.length > 0 ? `(${details.join(', ')})` : '';

  } else if (result?.queued) {
    showError(errorEl, result.message || 'Leads enfileirados (offline).');
    errorEl.style.borderColor = 'rgba(251, 191, 36, 0.25)';
    errorEl.style.color = '#fbbf24';
    errorEl.style.background = 'rgba(251, 191, 36, 0.1)';
  } else {
    showError(errorEl, result?.message || 'Erro ao capturar leads.');
  }

  // Reset button
  btn.disabled = false;
  document.getElementById('bulkCaptureIcon').textContent = 'rocket_launch';
  document.getElementById('bulkCaptureIcon').classList.remove('spin');
  updateBulkCaptureButton();
}

function markRowStatus(originalIndex, status) {
  // Encontra a row pela posição original do lead no array bulkLeads
  // Os leads selecionados mantêm referência ao índice original via bulkSelected
  const selectedArray = Array.from(bulkSelected);
  const tableRows = document.querySelectorAll('#bulkTableBody tr');

  // Busca na tabela pela row que tem data-index correspondente
  // O originalIndex do backend corresponde à posição no array selectedLeads,
  // precisamos mapear de volta para o índice no bulkLeads
  const bulkIndex = selectedArray[originalIndex];
  if (bulkIndex === undefined) return;

  const row = document.querySelector(`#bulkTableBody tr[data-index="${bulkIndex}"]`);
  if (!row) return;

  const statusCell = row.querySelector('.col-status');
  if (!statusCell) return;

  const labels = {
    sent: '<span class="status-sent">Enviado</span>',
    duplicate: '<span class="status-duplicate">Existe</span>',
    error: '<span class="status-error">Erro</span>',
  };

  statusCell.innerHTML = labels[status] || '';

  // Desabilitar checkbox de rows já enviadas
  if (status === 'sent') {
    const cb = row.querySelector('.bulk-row-check');
    if (cb) {
      cb.checked = false;
      cb.disabled = true;
    }
    bulkSelected.delete(bulkIndex);
    row.classList.add('is-duplicate');
  }
}

// ── Capture Handler (single mode) ──
async function handleCapture(e) {
  e.preventDefault();

  const btn = document.getElementById('btnCapture');
  const errorEl = document.getElementById('captureError');
  const successEl = document.getElementById('captureSuccess');
  const dupEl = document.getElementById('duplicateWarning');
  errorEl.style.display = 'none';
  successEl.style.display = 'none';

  const name = document.getElementById('capName').value.trim();
  const segment = document.getElementById('capSegment').value.trim();

  if (!name) {
    showError(errorEl, 'Nome é obrigatório.');
    return;
  }
  if (!segment) {
    showError(errorEl, 'Segmento é obrigatório.');
    return;
  }

  btn.disabled = true;
  document.getElementById('captureIcon').textContent = 'progress_activity';
  document.getElementById('captureIcon').classList.add('spin');
  document.getElementById('captureLabel').textContent = 'Capturando...';

  const data = {
    name,
    segment,
    category: document.getElementById('capCategory').value.trim(),
    phone: document.getElementById('capPhone').value.trim(),
    email: document.getElementById('capEmail').value.trim(),
    website: document.getElementById('capWebsite').value.trim(),
    address: document.getElementById('capAddress').value.trim(),
    cargo: document.getElementById('capCargo').value.trim(),
    rating: document.getElementById('capRating').value.trim() || null,
    notes: document.getElementById('capNotes').value.trim(),
    review_count: extractedData?.review_count || null,
    google_maps_url: extractedData?.google_maps_url || null,
    opening_hours: extractedData?.opening_hours || null,
    linkedin_url: extractedData?.linkedin_url || null,
    instagram_url: extractedData?.instagram_url || null,
    source: extractedData?.source || currentSource,
    source_url: extractedData?.source_url || '',
    extractor_type: extractedData?.extractor_type || currentSource,
    bio: extractedData?.bio || '',
    force_create: forceCreate,
  };

  const result = await sendMessage({ type: 'CAPTURE_LEAD', data });

  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (result?.duplicate) {
    // Mostrar aviso de duplicidade
    dupEl.style.display = 'flex';
    const lead = result.existing_lead;
    document.getElementById('duplicateInfo').textContent = `"${lead.name}" (${lead.pipeline_status || 'novo'})`;

    // Link para abrir o lead na plataforma
    chrome.storage.local.get('operon_server_url', (res) => {
      const baseUrl = res.operon_server_url || '';
      document.getElementById('duplicateLink').href = `${baseUrl}/vault/${lead.id}`;
    });
  } else if (result?.success) {
    // Sucesso!
    successEl.style.display = 'flex';
    forceCreate = false;
    dupEl.style.display = 'none';

    chrome.storage.local.get('operon_server_url', (res) => {
      const baseUrl = res.operon_server_url || '';
      document.getElementById('successLink').href = `${baseUrl}${result.url}`;
    });

    // Reset form after delay
    setTimeout(() => {
      document.getElementById('captureForm').reset();
      successEl.style.display = 'none';
    }, 3000);
  } else if (result?.queued) {
    showError(errorEl, result.message || 'Lead enfileirado (offline).');
    errorEl.style.borderColor = 'rgba(251, 191, 36, 0.25)';
    errorEl.style.color = '#fbbf24';
    errorEl.style.background = 'rgba(251, 191, 36, 0.1)';
  } else {
    showError(errorEl, result?.message || 'Erro ao capturar lead.');
  }

  // Reset button
  btn.disabled = false;
  document.getElementById('captureIcon').textContent = 'add_circle';
  document.getElementById('captureIcon').classList.remove('spin');
  document.getElementById('captureLabel').textContent = 'Capturar Lead';
}

// ── Helpers ──
function showError(el, msg) {
  el.textContent = msg;
  el.style.display = 'block';
  // Reset styles to default error
  el.style.borderColor = '';
  el.style.color = '';
  el.style.background = '';
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function sendMessage(msg) {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage(msg, (response) => {
      if (chrome.runtime.lastError) {
        resolve({ error: true, message: chrome.runtime.lastError.message });
      } else {
        resolve(response);
      }
    });
  });
}
