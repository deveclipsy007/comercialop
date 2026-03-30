/**
 * Operon Intelligence — Side Panel Cockpit Logic
 * Hub + Capture + Bulk + Analyze + Copilot + Qualify + Visual
 */

let currentSource = 'generic';
let extractedData = null;
let pageContext = null;
let forceCreate = false;
let platformUrl = '';
let chatHistory = [];
let includePageContext = true;
let leadMatch = null;
let latestPageScan = null;
let latestScreenshot = '';
let analysisResult = null;
let qualificationResult = null;
let visualResult = null;
let lastContextKey = '';
let contextRefreshTimer = null;
let contextPollTimer = null;
let contextListenersBound = false;
let availableTenants = [];
let activeTenantId = '';
let tenantMenuOpen = false;
let tenantSwitchBusy = '';

let bulkLeads = [];
let bulkDuplicates = {};
let bulkSelected = new Set();
let bulkScores = {};
let bulkTopIndexes = [];
let bulkRankingApplied = false;

const BULK_TOP_LIMIT = 30;
const BULK_SMART_CRITERIA = [
  { key: 'rating', inputId: 'bulkWeightRating', label: 'nota média' },
  { key: 'reviews', inputId: 'bulkWeightReviews', label: 'quantidade de avaliações' },
  { key: 'website', inputId: 'bulkWeightWebsite', label: 'site confirmado' },
  { key: 'phone', inputId: 'bulkWeightPhone', label: 'telefone confirmado' },
  { key: 'completeness', inputId: 'bulkWeightCompleteness', label: 'completude dos dados' },
  { key: 'category_match', inputId: 'bulkWeightCategory', label: 'aderência ao segmento' },
  { key: 'operational', inputId: 'bulkWeightOperational', label: 'sinais operacionais' },
];

document.addEventListener('DOMContentLoaded', async () => {
  bindStaticListeners();

  const authState = await sendMessage({ type: 'CHECK_AUTH' });
  if (authState?.authenticated) {
    await showMainScreen();
  } else {
    showLoginScreen();
  }
});

function bindStaticListeners() {
  document.getElementById('loginForm').addEventListener('submit', handleLogin);
  document.getElementById('btnLogout').addEventListener('click', handleLogout);
  document.getElementById('btnRefresh').addEventListener('click', () => refreshPageContext('manual'));
  document.getElementById('tenantSwitcherButton').addEventListener('click', (event) => {
    event.stopPropagation();
    toggleTenantMenu();
  });
  document.addEventListener('click', handleTenantMenuDocumentClick);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeTenantMenu();
  });

  document.querySelectorAll('.nav-item').forEach((btn) => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
  });

  document.querySelectorAll('.btn-back').forEach((btn) => {
    btn.addEventListener('click', () => switchTab(btn.dataset.back));
  });

  document.querySelectorAll('.action-card').forEach((card) => {
    card.addEventListener('click', () => handleAction(card.dataset.action));
  });

  document.getElementById('captureForm').addEventListener('submit', handleCapture);
  document.getElementById('btnForceCreate').addEventListener('click', () => {
    forceCreate = true;
    document.getElementById('duplicateWarning').style.display = 'none';
    document.getElementById('captureForm').dispatchEvent(new Event('submit'));
  });

  document.getElementById('btnBulkCapture').addEventListener('click', handleBulkCapture);
  document.getElementById('bulkSelectAll').addEventListener('change', handleBulkSelectAll);
  document.getElementById('btnSmartSelectTop').addEventListener('click', applySmartTopSelection);
  document.getElementById('btnSmartClearSelection').addEventListener('click', clearSmartBulkSelection);
  document.querySelectorAll('.smart-weight-input').forEach((input) => {
    input.addEventListener('input', syncSmartWeightDisplays);
  });

  document.getElementById('btnAnalyzePage').addEventListener('click', handleAnalyzePage);
  document.getElementById('btnSaveAnalysis').addEventListener('click', () => handleSaveAnalysis('analysis'));
  document.getElementById('btnCaptureFromAnalysis').addEventListener('click', () => {
    prefillCaptureFromContext();
    switchTab('capture');
  });
  document.getElementById('btnCopilotFromAnalysis').addEventListener('click', () => {
    switchTab('copilot');
    sendCopilotMessage('Com base na análise que acabou de ser feita desta página, me dê recomendações estratégicas adicionais.');
  });

  document.getElementById('btnSendChat').addEventListener('click', handleSendChat);
  document.getElementById('chatInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendChat();
    }
  });
  document.getElementById('btnClearChat').addEventListener('click', clearChat);
  document.getElementById('btnRemoveContext').addEventListener('click', () => {
    includePageContext = false;
    document.getElementById('chatContextTag').style.display = 'none';
  });
  document.querySelectorAll('.quick-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
      document.getElementById('chatInput').value = chip.dataset.prompt || '';
      handleSendChat();
    });
  });
  document.getElementById('chatInput').addEventListener('input', function resizeChat() {
    this.style.height = 'auto';
    this.style.height = `${Math.min(this.scrollHeight, 80)}px`;
  });

  document.getElementById('btnQualify').addEventListener('click', handleQualify);
  document.getElementById('btnCaptureFromQualify').addEventListener('click', () => {
    prefillCaptureFromContext();
    switchTab('capture');
  });
  document.getElementById('btnCopilotFromQualify').addEventListener('click', () => {
    switchTab('copilot');
    sendCopilotMessage('Com base na qualificação que acabou de ser feita, como devo abordar este lead?');
  });

  document.getElementById('btnVisualAnalyze').addEventListener('click', handleVisualAnalyze);
  document.getElementById('btnSaveVisual').addEventListener('click', () => handleSaveAnalysis('visual'));
  syncSmartWeightDisplays();
}

function showLoginScreen() {
  document.getElementById('loginScreen').style.display = 'flex';
  document.getElementById('mainScreen').style.display = 'none';
  stopContextSync();
  closeTenantMenu();
  availableTenants = [];
  activeTenantId = '';
  tenantSwitchBusy = '';

  chrome.storage.local.get('operon_server_url', (result) => {
    if (result.operon_server_url) {
      document.getElementById('serverUrl').value = result.operon_server_url;
    }
  });
}

async function showMainScreen() {
  document.getElementById('loginScreen').style.display = 'none';
  document.getElementById('mainScreen').style.display = 'flex';

  const me = await sendMessage({ type: 'GET_ME' });
  if (me?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (me?.success) {
    applyAccountContext(me);
  }

  const segmentsLoaded = await loadSegments();
  if (segmentsLoaded === false) {
    return;
  }

  setupContextSync();
  await refreshPageContext('boot');
  switchTab('hub');
}

function applyAccountContext(me) {
  document.getElementById('userName').textContent = me.user_name || 'Usuário';
  document.getElementById('tenantName').textContent = me.tenant_name || 'Empresa';
  platformUrl = me.platform_url || '';
  activeTenantId = me.tenant_id || '';
  availableTenants = Array.isArray(me.tenants) ? me.tenants : [];
  renderTenantMenu();
}

async function loadSegments() {
  const segs = await sendMessage({ type: 'GET_SEGMENTS' });
  if (segs?.auth_expired) {
    showLoginScreen();
    return false;
  }

  const datalist = document.getElementById('segmentList');
  datalist.innerHTML = '';

  if (segs?.segments) {
    segs.segments.forEach((segment) => {
      const opt = document.createElement('option');
      opt.value = segment;
      datalist.appendChild(opt);
    });
  }

  return true;
}

function setupContextSync() {
  if (!contextListenersBound) {
    chrome.tabs.onActivated.addListener(() => scheduleContextRefresh('tab-activated'));
    chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
      if (!tab?.active) return;
      if (changeInfo.url || changeInfo.status === 'complete') {
        scheduleContextRefresh('tab-updated');
      }
    });
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        scheduleContextRefresh('visible');
      }
    });
    contextListenersBound = true;
  }

  if (!contextPollTimer) {
    contextPollTimer = setInterval(() => {
      if (!document.hidden) {
        refreshPageContext('poll');
      }
    }, 15000);
  }
}

function stopContextSync() {
  if (contextPollTimer) {
    clearInterval(contextPollTimer);
    contextPollTimer = null;
  }
}

function toggleTenantMenu() {
  if (tenantMenuOpen) {
    closeTenantMenu();
  } else {
    openTenantMenu();
  }
}

function openTenantMenu() {
  tenantMenuOpen = true;
  renderTenantMenu();
  document.getElementById('tenantMenu').style.display = 'block';
  document.getElementById('tenantSwitcherButton').classList.add('active');
  document.getElementById('tenantSwitcherIcon').textContent = 'expand_less';
}

function closeTenantMenu() {
  tenantMenuOpen = false;
  const menu = document.getElementById('tenantMenu');
  if (menu) menu.style.display = 'none';
  const button = document.getElementById('tenantSwitcherButton');
  if (button) button.classList.remove('active');
  const icon = document.getElementById('tenantSwitcherIcon');
  if (icon) icon.textContent = 'expand_more';
}

function handleTenantMenuDocumentClick(event) {
  if (!tenantMenuOpen) return;

  const menu = document.getElementById('tenantMenu');
  const button = document.getElementById('tenantSwitcherButton');
  if (!menu || !button) return;

  if (menu.contains(event.target) || button.contains(event.target)) {
    return;
  }

  closeTenantMenu();
}

function renderTenantMenu() {
  const badgeEl = document.getElementById('tenantCountBadge');
  const hintEl = document.getElementById('tenantMenuHint');
  const listEl = document.getElementById('tenantMenuList');
  const button = document.getElementById('tenantSwitcherButton');

  if (!badgeEl || !hintEl || !listEl || !button) return;

  badgeEl.textContent = String(availableTenants.length || 0);
  button.disabled = availableTenants.length === 0;
  listEl.innerHTML = '';

  if (availableTenants.length === 0) {
    hintEl.textContent = 'Nenhuma empresa disponível para este usuário na extensão.';
    return;
  }

  hintEl.textContent = tenantSwitchBusy
    ? 'Trocando o contexto operacional da extensão...'
    : `${availableTenants.length} empresa${availableTenants.length > 1 ? 's' : ''} liberada${availableTenants.length > 1 ? 's' : ''} pelo admin para este usuário.`;

  availableTenants.forEach((tenant) => {
    const isCurrent = (tenant.id || '') === activeTenantId;
    const isSwitching = tenantSwitchBusy === tenant.id;
    const item = document.createElement('button');
    item.type = 'button';
    item.className = `tenant-menu-item${isCurrent ? ' active' : ''}`;
    item.disabled = Boolean(tenantSwitchBusy);
    item.innerHTML = `
      <span class="material-symbols-outlined tenant-item-icon">apartment</span>
      <span class="tenant-item-body">
        <span class="tenant-item-name">${escapeHtml(tenant.name || 'Empresa')}</span>
        <span class="tenant-item-meta">Permissão: ${escapeHtml(formatRoleLabel(tenant.role || 'agent'))}</span>
      </span>
      <span class="tenant-item-status">
        <span class="material-symbols-outlined ${isSwitching ? 'spin' : ''}">
          ${isSwitching ? 'progress_activity' : isCurrent ? 'check_circle' : 'chevron_right'}
        </span>
      </span>
    `;

    item.addEventListener('click', () => handleTenantSwitch(tenant.id || ''));
    listEl.appendChild(item);
  });
}

function showTenantMenuError(message = '') {
  const errorEl = document.getElementById('tenantMenuError');
  if (!errorEl) return;

  if (!message) {
    errorEl.textContent = '';
    errorEl.style.display = 'none';
    return;
  }

  errorEl.textContent = message;
  errorEl.style.display = 'block';
}

async function handleTenantSwitch(tenantId) {
  if (!tenantId || tenantSwitchBusy) return;
  if (tenantId === activeTenantId) {
    closeTenantMenu();
    return;
  }

  tenantSwitchBusy = tenantId;
  showTenantMenuError('');
  renderTenantMenu();

  const result = await sendMessage({ type: 'SWITCH_TENANT', tenantId });
  tenantSwitchBusy = '';

  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (!result?.success) {
    showTenantMenuError(result?.message || 'Não foi possível alternar a empresa.');
    renderTenantMenu();
    openTenantMenu();
    return;
  }

  applyAccountContext(result);
  resetTenantWorkspace();

  const segmentsLoaded = await loadSegments();
  if (segmentsLoaded === false) {
    return;
  }

  closeTenantMenu();
  switchTab('hub');
  await refreshPageContext('tenant-switched');
}

function scheduleContextRefresh(reason) {
  if (contextRefreshTimer) clearTimeout(contextRefreshTimer);
  contextRefreshTimer = setTimeout(() => refreshPageContext(reason), 350);
}

function switchTab(tabName) {
  closeTenantMenu();
  document.querySelectorAll('.tab-pane').forEach((pane) => pane.classList.remove('active'));
  const pane = document.getElementById('tab' + capitalize(tabName));
  if (pane) pane.classList.add('active');

  document.querySelectorAll('.nav-item').forEach((item) => item.classList.remove('active'));
  const navItem = document.querySelector(`.nav-item[data-tab="${tabName}"]`);
  if (navItem) navItem.classList.add('active');

  if (tabName === 'capture') initCaptureTab();
  if (tabName === 'copilot') initCopilotTab();
  if (tabName === 'analyze') initAnalyzeTab();
  if (tabName === 'qualify') initQualifyTab();
  if (tabName === 'visual') initVisualTab();
}

function handleAction(action) {
  switch (action) {
    case 'capture':
      switchTab('capture');
      break;
    case 'bulk':
      switchTab('bulk');
      startBulkExtraction();
      break;
    case 'analyze':
      switchTab('analyze');
      break;
    case 'copilot':
      switchTab('copilot');
      break;
    case 'qualify':
      switchTab('qualify');
      break;
    case 'visual':
      switchTab('visual');
      break;
    default:
      break;
  }
}

function capitalize(value) {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

async function refreshPageContext(reason = 'manual') {
  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab) return;

    const url = tab.url || '';
    const title = tab.title || 'Página';
    const isGoogleMaps = /google\.(com|com\.br)\/maps/i.test(url) || /maps\.google\./i.test(url);
    const isLinkedIn = /linkedin\.com/i.test(url);
    currentSource = isGoogleMaps ? 'google-maps' : isLinkedIn ? 'linkedin' : 'generic';

    const icons = {
      'google-maps': { name: 'Google Maps', icon: 'map' },
      'linkedin': { name: 'LinkedIn', icon: 'work' },
      generic: { name: 'Web', icon: 'language' },
    };
    const info = icons[currentSource] || icons.generic;
    document.getElementById('pageContextIcon').textContent = info.icon;
    document.getElementById('pageContextLabel').textContent = info.name;

    document.getElementById('hubPageTitle').textContent = title.length > 50 ? `${title.substring(0, 47)}...` : title;
    document.getElementById('hubPageUrl').textContent = url.length > 60 ? `${url.substring(0, 57)}...` : url;

    const contextKey = `${tab.id}:${url}`;
    const contextChanged = contextKey !== lastContextKey;
    if (contextChanged) {
      lastContextKey = contextKey;
      resetPageIntelligence();
    }

    const extracted = await sendMessage({ type: 'GET_EXTRACTED_DATA' });
    extractedData = extracted?.data || null;

    pageContext = {
      url,
      title,
      source: currentSource,
      extractedData,
    };

    leadMatch = await resolveLeadMatch();
    renderHubSnapshot(reason);

    if (contextChanged) {
      refreshActivePane();
    }
  } catch (err) {
    console.warn('[Operon] Error refreshing context:', err);
  }
}

function resetPageIntelligence() {
  analysisResult = null;
  qualificationResult = null;
  visualResult = null;
  latestPageScan = null;
  latestScreenshot = '';
  includePageContext = true;

  document.getElementById('captureForm').reset();
  document.getElementById('extractHint').style.display = 'none';
  document.getElementById('duplicateWarning').style.display = 'none';
  document.getElementById('captureError').style.display = 'none';
  document.getElementById('captureSuccess').style.display = 'none';
  document.getElementById('chatContextTag').style.display = 'none';
  document.getElementById('analyzeLoading').style.display = 'none';
  document.getElementById('analyzeResults').style.display = 'none';
  document.getElementById('analyzePreview').style.display = 'block';
  document.getElementById('qualifyLoading').style.display = 'none';
  document.getElementById('qualifyResults').style.display = 'none';
  document.getElementById('qualifyPreview').style.display = 'block';
  document.getElementById('visualLoading').style.display = 'none';
  document.getElementById('visualResults').style.display = 'none';
  document.getElementById('visualPreview').style.display = 'block';
  document.getElementById('visualScreenshot').style.display = 'none';
  document.getElementById('screenshotImg').src = '';
}

function resetBulkUi() {
  bulkScores = {};
  bulkTopIndexes = [];
  bulkRankingApplied = false;
  document.getElementById('bulkTotalCount').textContent = '0';
  document.getElementById('bulkDuplicateCount').textContent = '0';
  document.getElementById('bulkNewCount').textContent = '0';
  document.getElementById('bulkSelectedCount').textContent = '0';
  document.getElementById('bulkSegment').value = '';
  document.getElementById('bulkLoading').style.display = 'none';
  document.getElementById('bulkTableWrapper').style.display = 'none';
  document.getElementById('bulkError').style.display = 'none';
  document.getElementById('bulkSuccess').style.display = 'none';
  document.getElementById('bulkTableBody').innerHTML = '';
  document.getElementById('bulkSelectAll').checked = false;
  document.getElementById('bulkSelectAll').indeterminate = false;
  document.getElementById('btnBulkCapture').disabled = true;
  document.getElementById('bulkCaptureLabel').textContent = 'Selecione leads';
  document.getElementById('btnSmartSelectTop').disabled = true;
  document.getElementById('bulkSmartSummary').style.display = 'none';
  document.getElementById('bulkSmartSummary').innerHTML = '';
  document.getElementById('btnSmartClearSelection').style.display = 'none';
}

function resetTenantWorkspace() {
  forceCreate = false;
  leadMatch = null;
  extractedData = null;
  chatHistory = [];
  bulkLeads = [];
  bulkDuplicates = {};
  bulkSelected = new Set();
  lastContextKey = '';
  resetPageIntelligence();
  resetBulkUi();
  clearChat();
}

function syncSmartWeightDisplays() {
  document.querySelectorAll('.smart-weight-input').forEach((input) => {
    const outputId = input.dataset.output;
    const output = outputId ? document.getElementById(outputId) : null;
    if (output) {
      output.textContent = input.value;
    }
  });
}

function getBulkCandidateIndexes() {
  return bulkLeads
    .map((_, index) => index)
    .filter((index) => !bulkDuplicates[index]);
}

function applyDefaultBulkSelection() {
  bulkSelected = new Set(getBulkCandidateIndexes());
}

function getBulkSmartWeights() {
  return BULK_SMART_CRITERIA.reduce((acc, criterion) => {
    const input = document.getElementById(criterion.inputId);
    acc[criterion.key] = Number(input?.value || 0);
    return acc;
  }, {});
}

function parseLeadRatingValue(lead) {
  const numeric = Number.parseFloat(String(lead?.rating ?? '').replace(',', '.'));
  return Number.isFinite(numeric) ? Math.max(0, Math.min(5, numeric)) : 0;
}

function parseLeadReviewCount(lead) {
  const numeric = Number.parseInt(String(lead?.review_count ?? '').replace(/\D/g, ''), 10);
  return Number.isFinite(numeric) ? Math.max(0, numeric) : 0;
}

function computeCategoryMatchScore(lead, segmentText) {
  const normalizedSegment = normalizeComparableText(segmentText);
  if (!normalizedSegment) return null;

  const segmentTokens = normalizedSegment.split(' ').filter((token) => token.length > 2);
  if (segmentTokens.length === 0) return null;

  const haystack = normalizeComparableText(`${lead?.category || ''} ${lead?.name || ''}`);
  if (!haystack) return 0;
  if (haystack.includes(normalizedSegment)) return 1;

  const hits = segmentTokens.filter((token) => haystack.includes(token)).length;
  return Math.min(1, hits / segmentTokens.length);
}

function computeBulkMetrics(lead, segmentText, maxReviewCount) {
  const rating = parseLeadRatingValue(lead);
  const reviewCount = parseLeadReviewCount(lead);
  const hasWebsite = Boolean((lead?.website || '').trim());
  const hasPhone = Boolean((lead?.phone || '').trim());
  const hasAddress = Boolean((lead?.address || '').trim());
  const hasCategory = Boolean((lead?.category || '').trim());
  const hasOpeningHours = Boolean((lead?.opening_hours || '').trim());
  const hasMapsProfile = Boolean((lead?.google_maps_url || '').trim());

  const completenessScore = [
    rating > 0,
    reviewCount > 0,
    hasWebsite,
    hasPhone,
    hasAddress,
    hasCategory,
    hasOpeningHours,
  ].filter(Boolean).length / 7;

  const operationalScore = [
    hasAddress,
    hasCategory,
    hasOpeningHours,
    hasMapsProfile,
  ].filter(Boolean).length / 4;

  return {
    rating,
    reviewCount,
    ratingScore: rating > 0 ? rating / 5 : 0,
    reviewsScore: reviewCount > 0 && maxReviewCount > 0
      ? Math.log1p(reviewCount) / Math.log1p(maxReviewCount)
      : 0,
    websiteScore: hasWebsite ? 1 : 0,
    phoneScore: hasPhone ? 1 : 0,
    completenessScore,
    operationalScore,
    categoryMatchScore: computeCategoryMatchScore(lead, segmentText),
    flags: {
      hasWebsite,
      hasPhone,
      hasAddress,
      hasCategory,
      hasOpeningHours,
      hasMapsProfile,
    },
  };
}

function renderSmartSelectionSummary(summary) {
  const summaryEl = document.getElementById('bulkSmartSummary');
  const clearBtn = document.getElementById('btnSmartClearSelection');
  if (!summaryEl || !clearBtn) return;

  if (!summary) {
    summaryEl.style.display = 'none';
    summaryEl.innerHTML = '';
    clearBtn.style.display = 'none';
    return;
  }

  const ignoredItems = Array.isArray(summary.ignored) ? summary.ignored : [];
  const ignoredLine = ignoredItems.length > 0
    ? `<div>Ignorados por falta de dado útil: ${escapeHtml(ignoredItems.join(', '))}.</div>`
    : '';

  summaryEl.innerHTML = `
    <strong>${escapeHtml(summary.title)}</strong>
    <div>${escapeHtml(summary.body)}</div>
    ${ignoredLine}
  `;
  summaryEl.style.display = 'block';
  clearBtn.style.display = 'inline-flex';
}

function clearSmartBulkSelection() {
  bulkScores = {};
  bulkTopIndexes = [];
  bulkRankingApplied = false;
  applyDefaultBulkSelection();
  renderSmartSelectionSummary(null);
  renderBulkTable();
  updateBulkCheckboxes();
  updateBulkCaptureButton();
}

function refreshActivePane() {
  const activePane = document.querySelector('.tab-pane.active');
  if (!activePane) return;

  if (activePane.id === 'tabCapture') initCaptureTab();
  if (activePane.id === 'tabAnalyze') initAnalyzeTab();
  if (activePane.id === 'tabCopilot') initCopilotTab();
  if (activePane.id === 'tabQualify') initQualifyTab();
  if (activePane.id === 'tabVisual') initVisualTab();
}

async function resolveLeadMatch() {
  if (!pageContext) return null;

  const duplicatePayload = {
    name: extractedData?.name || pageContext.title || '',
    segment: extractedData?.segment || extractedData?.category || '',
    category: extractedData?.category || '',
    phone: extractedData?.phone || '',
    email: extractedData?.email || '',
    website: extractedData?.website || (currentSource === 'generic' ? pageContext.url : ''),
    google_maps_url: extractedData?.google_maps_url || '',
    source_url: pageContext.url,
    extractor_type: extractedData?.extractor_type || currentSource,
  };

  const result = await sendMessage({ type: 'CHECK_DUPLICATE', data: duplicatePayload });
  if (result?.auth_expired) {
    showLoginScreen();
    return null;
  }

  return result?.lead || null;
}

function renderHubSnapshot(reason = 'manual') {
  const entityName = extractedData?.name || pageContext?.title || 'Página atual';
  const phone = extractedData?.phone || '';
  const email = extractedData?.email || '';
  const address = extractedData?.address || '';
  const socialCount = [
    extractedData?.linkedin_url,
    extractedData?.instagram_url,
    extractedData?.facebook_url,
  ].filter(Boolean).length;

  const contactParts = [];
  if (phone) contactParts.push('telefone');
  if (email) contactParts.push('email');
  if (address) contactParts.push('endereço');
  if (socialCount > 0) contactParts.push(`${socialCount} rede${socialCount > 1 ? 's' : ''}`);

  const leadState = leadMatch
    ? `Já existe no Vault: ${leadMatch.name || 'lead encontrado'}`
    : 'Novo contexto pronto para análise/captura';

  let actionState = 'Próximo passo: analisar';
  if (qualificationResult?.recommendedAction === 'capturar_agora') {
    actionState = 'Próximo passo: salvar no Vault';
  } else if (qualificationResult?.recommendedAction === 'qualificar_mais') {
    actionState = 'Próximo passo: aprofundar qualificação';
  } else if (analysisResult?.nextActions?.[0]) {
    actionState = `Próximo passo: ${analysisResult.nextActions[0]}`;
  }

  const insight = qualificationResult?.verdict || analysisResult?.summary ||
    (leadMatch
      ? 'A página atual já conversa com um lead existente. Use o cockpit para enriquecer o contexto e decidir o próximo movimento.'
      : 'A aba atual ainda não foi analisada. Você pode captar, qualificar, conversar com o copiloto ou rodar leitura visual sem sair da navegação.');

  document.getElementById('hubEntityName').textContent = truncate(entityName, 56);
  document.getElementById('hubLeadState').textContent = leadState;
  document.getElementById('hubContactState').textContent = contactParts.length > 0
    ? `Sinais: ${contactParts.join(', ')}`
    : 'Sinais: contato ainda não identificado';
  document.getElementById('hubSourceState').textContent = `Fonte: ${pageContext?.source || currentSource}`;
  document.getElementById('hubActionState').textContent = truncate(actionState, 48);
  document.getElementById('hubInsight').textContent = insight;
  document.getElementById('hubSyncState').textContent = `Leitura automática da aba ativa • ${new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })} • ${reason}`;
}

async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('btnLogin');
  const errorEl = document.getElementById('loginError');
  errorEl.style.display = 'none';

  const serverUrl = document.getElementById('serverUrl').value.trim().replace(/\/+$/, '');
  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;

  if (!serverUrl || !email || !password) {
    showMsg(errorEl, 'Preencha todos os campos.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined spin">progress_activity</span> Conectando...';

  const result = await sendMessage({ type: 'LOGIN', serverUrl, email, password });
  if (result?.success) {
    await showMainScreen();
  } else {
    showMsg(errorEl, result?.message || 'Falha na autenticação.');
  }

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined">login</span> Conectar';
}

async function handleLogout() {
  await sendMessage({ type: 'LOGOUT' });
  availableTenants = [];
  activeTenantId = '';
  resetTenantWorkspace();
  showLoginScreen();
}

function initCaptureTab() {
  const sourceNames = {
    'google-maps': { name: 'Google Maps', icon: 'map' },
    linkedin: { name: 'LinkedIn', icon: 'work' },
    generic: { name: 'Website', icon: 'language' },
  };
  const info = sourceNames[currentSource] || sourceNames.generic;
  document.getElementById('captureSourceName').textContent = info.name;
  document.querySelector('#captureSourceBadge .material-symbols-outlined').textContent = info.icon;

  if (analysisResult?.leadCandidate) {
    fillCaptureForm(analysisResult.leadCandidate);
    document.getElementById('extractHint').style.display = 'flex';
  } else if (extractedData) {
    fillCaptureForm(extractedData);
    document.getElementById('extractHint').style.display = 'flex';
  } else {
    document.getElementById('extractHint').style.display = 'none';
  }
}

function fillCaptureForm(data) {
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

  Object.entries(fields).forEach(([id, value]) => {
    if (!value) return;
    const el = document.getElementById(id);
    if (el) el.value = String(value).trim();
  });
}

function prefillCaptureFromContext() {
  if (analysisResult?.leadCandidate) {
    fillCaptureForm(analysisResult.leadCandidate);
    return;
  }
  if (qualificationResult?.leadCandidate) {
    fillCaptureForm(qualificationResult.leadCandidate);
    return;
  }
  if (extractedData) {
    fillCaptureForm(extractedData);
    return;
  }
  if (pageContext) {
    document.getElementById('capName').value = pageContext.title || '';
    document.getElementById('capWebsite').value = pageContext.url || '';
  }
}

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
    showMsg(errorEl, 'Nome é obrigatório.');
    return;
  }
  if (!segment) {
    showMsg(errorEl, 'Segmento é obrigatório.');
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
    review_count: extractedData?.review_count || analysisResult?.leadCandidate?.review_count || null,
    google_maps_url: extractedData?.google_maps_url || analysisResult?.leadCandidate?.google_maps_url || null,
    opening_hours: extractedData?.opening_hours || analysisResult?.leadCandidate?.opening_hours || null,
    linkedin_url: extractedData?.linkedin_url || analysisResult?.leadCandidate?.linkedin_url || null,
    instagram_url: extractedData?.instagram_url || analysisResult?.leadCandidate?.instagram_url || null,
    source: extractedData?.source || analysisResult?.leadCandidate?.source || currentSource,
    source_url: extractedData?.source_url || pageContext?.url || '',
    extractor_type: extractedData?.extractor_type || analysisResult?.leadCandidate?.extractor_type || currentSource,
    bio: extractedData?.bio || '',
    force_create: forceCreate,
  };

  const result = await sendMessage({ type: 'CAPTURE_LEAD', data });
  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (result?.duplicate) {
    dupEl.style.display = 'flex';
    const lead = result.existing_lead;
    document.getElementById('duplicateInfo').textContent = `"${lead.name}" (${lead.pipeline_status || 'novo'})`;
    document.getElementById('duplicateLink').href = buildPlatformLink(`/vault/${lead.id}`);
  } else if (result?.success) {
    successEl.style.display = 'flex';
    forceCreate = false;
    dupEl.style.display = 'none';
    document.getElementById('successLink').href = buildPlatformLink(result.url || '/vault');
    leadMatch = { id: result.lead_id, name, pipeline_status: 'new' };
    renderHubSnapshot('captured');
    setTimeout(() => {
      document.getElementById('captureForm').reset();
      successEl.style.display = 'none';
    }, 3000);
  } else {
    showMsg(errorEl, result?.message || 'Erro ao capturar lead.');
  }

  btn.disabled = false;
  document.getElementById('captureIcon').textContent = 'person_add';
  document.getElementById('captureIcon').classList.remove('spin');
  document.getElementById('captureLabel').textContent = 'Capturar Lead';
}

async function startBulkExtraction() {
  bulkLeads = [];
  bulkDuplicates = {};
  bulkSelected = new Set();
  bulkScores = {};
  bulkTopIndexes = [];
  bulkRankingApplied = false;

  const loadingEl = document.getElementById('bulkLoading');
  const tableWrapper = document.getElementById('bulkTableWrapper');
  const errorEl = document.getElementById('bulkError');
  const successEl = document.getElementById('bulkSuccess');
  loadingEl.style.display = 'flex';
  tableWrapper.style.display = 'none';
  errorEl.style.display = 'none';
  successEl.style.display = 'none';
  document.getElementById('btnBulkCapture').disabled = true;
  document.getElementById('btnSmartSelectTop').disabled = true;
  document.getElementById('bulkLoadingText').textContent = 'Extraindo resultados da página...';

  const extractResult = await sendMessage({ type: 'EXTRACT_ALL_DATA' });
  if (!extractResult?.data || extractResult.data.length === 0) {
    loadingEl.style.display = 'none';
    showMsg(errorEl, extractResult?.message || 'Nenhum resultado encontrado. Faça uma busca no Google Maps primeiro.');
    return;
  }

  bulkLeads = extractResult.data;
  document.getElementById('bulkTotalCount').textContent = bulkLeads.length;
  document.getElementById('bulkLoadingText').textContent = `Verificando ${bulkLeads.length} leads...`;

  const dupResult = await sendMessage({ type: 'CHECK_BULK_DUPLICATES', leads: bulkLeads });
  if (dupResult?.results) {
    let dupCount = 0;
    dupResult.results.forEach((row) => {
      if (row.exists) {
        bulkDuplicates[row.index] = row.existing;
        dupCount++;
      }
    });
    document.getElementById('bulkDuplicateCount').textContent = dupCount;
    document.getElementById('bulkNewCount').textContent = bulkLeads.length - dupCount;
  }

  loadingEl.style.display = 'none';
  tableWrapper.style.display = 'block';
  document.getElementById('btnSmartSelectTop').disabled = false;
  renderSmartSelectionSummary(null);
  applyDefaultBulkSelection();
  renderBulkTable();
  updateBulkCheckboxes();
  updateBulkCaptureButton();

  const categories = bulkLeads.map((lead) => lead.category).filter(Boolean);
  if (categories.length > 0) {
    const freq = {};
    categories.forEach((cat) => { freq[cat] = (freq[cat] || 0) + 1; });
    const mostCommon = Object.entries(freq).sort((a, b) => b[1] - a[1])[0]?.[0];
    if (mostCommon) document.getElementById('bulkSegment').value = mostCommon;
  }
}

function renderBulkTable() {
  const tbody = document.getElementById('bulkTableBody');
  tbody.innerHTML = '';

  const indexes = bulkLeads.map((_, index) => index);
  if (bulkRankingApplied) {
    indexes.sort((a, b) => {
      const scoreDiff = (bulkScores[b]?.score ?? 0) - (bulkScores[a]?.score ?? 0);
      if (scoreDiff !== 0) return scoreDiff;

      const reviewDiff = parseLeadReviewCount(bulkLeads[b]) - parseLeadReviewCount(bulkLeads[a]);
      if (reviewDiff !== 0) return reviewDiff;

      return parseLeadRatingValue(bulkLeads[b]) - parseLeadRatingValue(bulkLeads[a]);
    });
  }

  indexes.forEach((index) => {
    const lead = bulkLeads[index];
    const isDuplicate = !!bulkDuplicates[index];
    const isTopLead = bulkRankingApplied && bulkTopIndexes.includes(index);
    const score = bulkScores[index]?.score ?? null;
    const meta = buildBulkLeadMeta(lead, index);
    const tr = document.createElement('tr');
    tr.className = [
      isDuplicate ? 'is-duplicate' : '',
      isTopLead ? 'is-smart-top' : '',
    ].filter(Boolean).join(' ');
    tr.dataset.index = index;
    tr.innerHTML = `
      <td class="col-check">
        <input type="checkbox" class="bulk-row-check" data-index="${index}" ${!isDuplicate ? 'checked' : ''}>
      </td>
      <td>
        <span class="lead-name" title="${escapeHtml(lead.name)}">${escapeHtml(lead.name)}</span>
        <span class="lead-category">${escapeHtml(lead.category || lead.address || '')}</span>
        ${meta ? `<span class="lead-meta">${escapeHtml(meta)}</span>` : ''}
      </td>
      <td class="col-rating">
        ${lead.rating ? `<span class="lead-rating">${escapeHtml(String(lead.rating))}</span>` : '—'}
      </td>
      <td class="col-score">
        ${score !== null ? `<span class="lead-score-badge">${escapeHtml(String(score))}</span>` : '—'}
      </td>
      <td class="col-status">
        ${isDuplicate ? '<span class="status-duplicate">Existe</span>' : '<span class="status-new">Novo</span>'}
      </td>
    `;
    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('.bulk-row-check').forEach((cb) => {
    cb.addEventListener('change', (e) => {
      const idx = parseInt(e.target.dataset.index, 10);
      if (e.target.checked) bulkSelected.add(idx);
      else bulkSelected.delete(idx);
      updateBulkSelectAllState();
      updateBulkCaptureButton();
    });
  });
}

function buildBulkLeadMeta(lead, index) {
  const metrics = bulkScores[index]?.metrics;
  const hasWebsite = metrics ? metrics.flags.hasWebsite : Boolean((lead?.website || '').trim());
  const hasPhone = metrics ? metrics.flags.hasPhone : Boolean((lead?.phone || '').trim());
  const hasOpeningHours = metrics ? metrics.flags.hasOpeningHours : Boolean((lead?.opening_hours || '').trim());
  const reviewCount = metrics ? metrics.reviewCount : parseLeadReviewCount(lead);
  const tags = [];

  if (hasWebsite) tags.push('site');
  if (hasPhone) tags.push('telefone');
  if (reviewCount > 0) tags.push(`${reviewCount} aval.`);
  if (hasOpeningHours) tags.push('horário');
  if (bulkRankingApplied && (metrics?.categoryMatchScore ?? 0) >= 0.7) tags.push('aderente');

  return tags.slice(0, 4).join(' • ');
}

async function applySmartTopSelection() {
  const errorEl = document.getElementById('bulkError');
  errorEl.style.display = 'none';

  const candidateIndexes = getBulkCandidateIndexes();
  if (candidateIndexes.length === 0) {
    showMsg(errorEl, 'Não há leads novos suficientes para aplicar a curadoria inteligente.');
    return;
  }

  const weights = getBulkSmartWeights();
  const totalWeight = Object.values(weights).reduce((sum, value) => sum + value, 0);
  if (totalWeight <= 0) {
    showMsg(errorEl, 'Defina pelo menos um critério com peso maior que zero.');
    return;
  }

  const segmentText = document.getElementById('bulkSegment').value.trim();
  const maxReviewCount = Math.max(
    1,
    ...candidateIndexes.map((index) => parseLeadReviewCount(bulkLeads[index]))
  );

  const metricsByIndex = {};
  candidateIndexes.forEach((index) => {
    metricsByIndex[index] = computeBulkMetrics(bulkLeads[index], segmentText, maxReviewCount);
  });

  const criterionState = {
    rating: candidateIndexes.some((index) => metricsByIndex[index].rating > 0),
    reviews: candidateIndexes.some((index) => metricsByIndex[index].reviewCount > 0),
    website: true,
    phone: true,
    completeness: true,
    category_match: Boolean(segmentText),
    operational: candidateIndexes.some((index) => metricsByIndex[index].operationalScore > 0),
  };

  const ignoredCriteria = BULK_SMART_CRITERIA
    .filter((criterion) => weights[criterion.key] > 0 && !criterionState[criterion.key])
    .map((criterion) => criterion.label);

  const activeCriteria = BULK_SMART_CRITERIA
    .filter((criterion) => weights[criterion.key] > 0 && criterionState[criterion.key])
    .sort((a, b) => (weights[b.key] || 0) - (weights[a.key] || 0))
    .map((criterion) => criterion.label);

  if (activeCriteria.length === 0) {
    showMsg(errorEl, 'Os critérios escolhidos não têm dados suficientes nesta captura. Ajuste os pesos ou preencha melhor o segmento.');
    return;
  }

  bulkScores = {};
  candidateIndexes.forEach((index) => {
    const metrics = metricsByIndex[index];
    let weightedScore = 0;
    let activeWeight = 0;

    BULK_SMART_CRITERIA.forEach((criterion) => {
      const weight = weights[criterion.key] || 0;
      if (weight <= 0 || !criterionState[criterion.key]) return;

      const criterionScoreMap = {
        rating: metrics.ratingScore,
        reviews: metrics.reviewsScore,
        website: metrics.websiteScore,
        phone: metrics.phoneScore,
        completeness: metrics.completenessScore,
        category_match: metrics.categoryMatchScore ?? 0,
        operational: metrics.operationalScore,
      };

      weightedScore += (criterionScoreMap[criterion.key] || 0) * weight;
      activeWeight += weight;
    });

    bulkScores[index] = {
      score: activeWeight > 0 ? Math.round((weightedScore / activeWeight) * 100) : 0,
      metrics,
    };
  });

  const sortedIndexes = [...candidateIndexes].sort((a, b) => {
    const scoreDiff = (bulkScores[b]?.score ?? 0) - (bulkScores[a]?.score ?? 0);
    if (scoreDiff !== 0) return scoreDiff;

    const reviewDiff = (bulkScores[b]?.metrics?.reviewCount ?? 0) - (bulkScores[a]?.metrics?.reviewCount ?? 0);
    if (reviewDiff !== 0) return reviewDiff;

    return (bulkScores[b]?.metrics?.rating ?? 0) - (bulkScores[a]?.metrics?.rating ?? 0);
  });

  const selectedIndexes = sortedIndexes.slice(0, Math.min(BULK_TOP_LIMIT, sortedIndexes.length));
  bulkTopIndexes = selectedIndexes;
  bulkRankingApplied = true;
  bulkSelected = new Set(selectedIndexes);

  const leadingCriteria = activeCriteria.slice(0, 3);
  renderSmartSelectionSummary({
    title: `${selectedIndexes.length} leads priorizados para envio`,
    body: `A tabela foi reordenada e os melhores leads foram selecionados usando principalmente ${leadingCriteria.join(', ')}.`,
    ignored: ignoredCriteria,
  });

  renderBulkTable();
  updateBulkCheckboxes();
  updateBulkCaptureButton();
}

function handleBulkSelectAll(e) {
  bulkSelected = new Set();
  if (e.target.checked) {
    getBulkCandidateIndexes().forEach((index) => bulkSelected.add(index));
  }
  updateBulkCheckboxes();
  updateBulkCaptureButton();
}

function updateBulkCheckboxes() {
  document.querySelectorAll('.bulk-row-check').forEach((cb) => {
    cb.checked = bulkSelected.has(parseInt(cb.dataset.index, 10));
  });
  updateBulkSelectAllState();
}

function updateBulkSelectAllState() {
  const selectAll = document.getElementById('bulkSelectAll');
  const candidateIndexes = getBulkCandidateIndexes();
  const selectedCandidates = candidateIndexes.filter((index) => bulkSelected.has(index)).length;

  if (candidateIndexes.length > 0 && selectedCandidates === candidateIndexes.length) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else if (selectedCandidates === 0) {
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
}

async function handleBulkCapture() {
  const segment = document.getElementById('bulkSegment').value.trim();
  const errorEl = document.getElementById('bulkError');
  const successEl = document.getElementById('bulkSuccess');
  errorEl.style.display = 'none';
  successEl.style.display = 'none';

  if (!segment) {
    showMsg(errorEl, 'Defina um segmento.');
    return;
  }
  if (bulkSelected.size === 0) {
    showMsg(errorEl, 'Selecione pelo menos um lead.');
    return;
  }

  const selectedArray = Array.from(bulkSelected);
  const selectedLeads = selectedArray.map((idx) => ({ ...bulkLeads[idx], segment }));

  const btn = document.getElementById('btnBulkCapture');
  btn.disabled = true;
  document.getElementById('bulkCaptureIcon').textContent = 'progress_activity';
  document.getElementById('bulkCaptureIcon').classList.add('spin');
  document.getElementById('bulkCaptureLabel').textContent = `Enviando ${selectedLeads.length} leads...`;

  const result = await sendMessage({ type: 'CAPTURE_BULK', leads: selectedLeads });
  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (result?.success && result.summary) {
    result.created?.forEach((created) => markRowStatus(selectedArray[created.index], 'sent'));
    result.duplicates?.forEach((dup) => markRowStatus(selectedArray[dup.index], 'duplicate'));
    result.errors?.forEach((err) => markRowStatus(selectedArray[err.index], 'error'));

    successEl.style.display = 'flex';
    document.getElementById('bulkSuccessTitle').textContent = `${result.summary.created} lead${result.summary.created > 1 ? 's' : ''} capturado${result.summary.created > 1 ? 's' : ''}!`;
    const details = [];
    if (result.summary.duplicates > 0) details.push(`${result.summary.duplicates} duplicado${result.summary.duplicates > 1 ? 's' : ''}`);
    if (result.summary.errors > 0) details.push(`${result.summary.errors} erro${result.summary.errors > 1 ? 's' : ''}`);
    document.getElementById('bulkSuccessDetail').textContent = details.length > 0 ? `(${details.join(', ')})` : '';
  } else {
    showMsg(errorEl, result?.message || 'Erro ao capturar leads.');
  }

  btn.disabled = false;
  document.getElementById('bulkCaptureIcon').textContent = 'rocket_launch';
  document.getElementById('bulkCaptureIcon').classList.remove('spin');
  updateBulkCaptureButton();
}

function markRowStatus(bulkIndex, status) {
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

function initAnalyzeTab() {
  document.getElementById('analyzePageTitle').textContent = pageContext?.title || 'Página atual';
  document.getElementById('analyzePageUrl').textContent = truncate(pageContext?.url || '', 50);
  document.getElementById('analyzePageDesc').textContent = extractedData?.bio
    ? truncate(extractedData.bio, 150)
    : leadMatch
      ? 'Este contexto já existe no Vault. Você pode enriquecer o histórico sem sair da navegação.'
      : 'Faça uma leitura contextual desta página para entender posicionamento, maturidade digital e encaixe comercial.';

  if (analysisResult) {
    document.getElementById('analyzePreview').style.display = 'none';
    document.getElementById('analyzeResults').style.display = 'block';
    renderAnalysis(analysisResult);
  } else {
    document.getElementById('analyzePreview').style.display = 'block';
    document.getElementById('analyzeResults').style.display = 'none';
  }
}

async function handleAnalyzePage() {
  const loadingEl = document.getElementById('analyzeLoading');
  const previewEl = document.getElementById('analyzePreview');
  const resultsEl = document.getElementById('analyzeResults');
  const stepsEl = document.getElementById('analyzeSteps');

  previewEl.style.display = 'none';
  resultsEl.style.display = 'none';
  loadingEl.style.display = 'flex';

  const stopSteps = startProgressSteps(stepsEl, [
    'Lendo conteúdo da página...',
    'Extraindo contexto comercial...',
    'Analisando posicionamento...',
    'Avaliando maturidade digital...',
    'Cruzando com o contexto estratégico...',
    'Montando diagnóstico operacional...',
  ], 1200, 1800);

  const pageScan = await getPageScan(true);
  const deepAnalysis = document.getElementById('analyzeDeep').checked;
  const result = await sendMessage({
    type: 'ANALYZE_PAGE',
    data: {
      ...buildPagePayload(pageScan),
      deep: deepAnalysis,
    },
  });

  stopSteps();
  loadingEl.style.display = 'none';

  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (result?.success && result.analysis) {
    analysisResult = result.analysis;
    resultsEl.style.display = 'block';
    renderAnalysis(analysisResult);
    renderHubSnapshot('analysis-ready');
  } else {
    previewEl.style.display = 'block';
    renderErrorCard('analysisContent', result?.message || 'Erro ao analisar a página.');
    resultsEl.style.display = 'block';
  }
}

function renderAnalysis(analysis) {
  const container = document.getElementById('analysisContent');
  if (typeof analysis === 'string') {
    container.innerHTML = formatAIResponse(analysis);
    return;
  }

  const lead = analysis.leadCandidate || {};
  let html = '';

  html += `<h3><span class="material-symbols-outlined">business</span> Identificação</h3>`;
  html += `<p><strong>${escapeHtml(analysis.company || pageContext?.title || 'Página atual')}</strong></p>`;
  html += `<p>Tipo: ${escapeHtml(analysis.pageType || 'unknown')}</p>`;
  if (lead.segment) html += `<p>Segmento: ${escapeHtml(lead.segment)}</p>`;
  if (lead.category) html += `<p>Categoria: ${escapeHtml(lead.category)}</p>`;
  if (analysis.existingLead?.id) {
    html += `<p><strong>Vault:</strong> já existe um lead relacionado (${escapeHtml(analysis.existingLead.name || '')}).</p>`;
  }

  if (analysis.summary) {
    html += `<h3><span class="material-symbols-outlined">description</span> Resumo</h3>`;
    html += `<p>${escapeHtml(analysis.summary)}</p>`;
  }

  html += `<h3><span class="material-symbols-outlined">monitoring</span> Leituras-Chave</h3>`;
  html += `<p><span class="score-badge ${scoreClass(analysis.digitalMaturity)}">Maturidade ${escapeHtml(String(analysis.digitalMaturity ?? 0))}/10</span></p>`;
  html += `<p><span class="score-badge ${scoreClass(analysis.fitScore)}">Encaixe ${escapeHtml(String(analysis.fitScore ?? 0))}/10</span></p>`;
  if (analysis.offerClarity) html += `<p>Clareza da oferta: ${escapeHtml(analysis.offerClarity)}</p>`;
  if (analysis.positioning) html += `<p>${escapeHtml(analysis.positioning)}</p>`;

  if (analysis.evidence?.length) {
    html += `<h3><span class="material-symbols-outlined">fact_check</span> Evidências Observadas</h3>`;
    html += `<ul>${analysis.evidence.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  if (analysis.opportunities?.length) {
    html += `<h3><span class="material-symbols-outlined">lightbulb</span> Oportunidades</h3>`;
    html += `<ul>${analysis.opportunities.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  if (analysis.painPoints?.length) {
    html += `<h3><span class="material-symbols-outlined">warning</span> Lacunas ou Riscos</h3>`;
    html += `<ul>${analysis.painPoints.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  if (analysis.recommendation) {
    html += `<h3><span class="material-symbols-outlined">recommend</span> Recomendação</h3>`;
    html += `<p>${escapeHtml(analysis.recommendation)}</p>`;
  }

  if (analysis.nextActions?.length) {
    html += `<h3><span class="material-symbols-outlined">route</span> Próximas Ações</h3>`;
    html += `<ul>${analysis.nextActions.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  if (lead.website || lead.phone || lead.email || lead.address) {
    html += `<h3><span class="material-symbols-outlined">contact_page</span> Dados Estruturados para Captura</h3>`;
    html += `<p>Website: ${escapeHtml(lead.website || 'Não identificado')}</p>`;
    html += `<p>Telefone: ${escapeHtml(lead.phone || 'Não identificado')}</p>`;
    html += `<p>Email: ${escapeHtml(lead.email || 'Não identificado')}</p>`;
    html += `<p>Endereço: ${escapeHtml(lead.address || 'Não identificado')}</p>`;
  }

  if (analysis.warnings?.length) {
    html += `<h3><span class="material-symbols-outlined">error</span> Limites da Leitura</h3>`;
    html += `<ul>${analysis.warnings.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  container.innerHTML = html || formatAIResponse(JSON.stringify(analysis));
}

function initCopilotTab() {
  if (pageContext?.title && includePageContext) {
    document.getElementById('chatContextTag').style.display = 'flex';
    document.getElementById('chatContextName').textContent = truncate(pageContext.title, 30);
  }

  const messages = document.getElementById('chatMessages');
  messages.scrollTop = messages.scrollHeight;
  document.getElementById('chatInput').focus();
}

function handleSendChat() {
  const input = document.getElementById('chatInput');
  const message = input.value.trim();
  if (!message) return;

  input.value = '';
  input.style.height = 'auto';
  document.getElementById('copilotQuick').style.display = 'none';

  const welcome = document.querySelector('.chat-welcome');
  if (welcome) welcome.style.display = 'none';

  sendCopilotMessage(message);
}

async function sendCopilotMessage(message) {
  const messagesEl = document.getElementById('chatMessages');
  addChatBubble('user', message);
  chatHistory.push({ role: 'user', content: message });

  const typingEl = document.createElement('div');
  typingEl.className = 'chat-typing';
  typingEl.id = 'chatTyping';
  typingEl.innerHTML = '<span></span><span></span><span></span>';
  messagesEl.appendChild(typingEl);
  messagesEl.scrollTop = messagesEl.scrollHeight;
  document.getElementById('btnSendChat').disabled = true;

  let pageData = null;
  if (includePageContext && pageContext) {
    const pageScan = await getPageScan(false);
    pageData = buildPagePayload(pageScan);
  }

  const result = await sendMessage({
    type: 'COPILOT_CHAT',
    data: {
      message,
      history: chatHistory.slice(-10),
      page_context: pageData,
    },
  });

  document.getElementById('chatTyping')?.remove();
  document.getElementById('btnSendChat').disabled = false;

  if (result?.success && result.reply) {
    addChatBubble('assistant', result.reply);
    chatHistory.push({ role: 'assistant', content: result.reply });
  } else if (result?.auth_expired) {
    showLoginScreen();
  } else {
    addChatBubble('assistant', 'Nao consegui processar a mensagem agora. Tente novamente em alguns segundos.');
  }

  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function addChatBubble(role, content) {
  const messagesEl = document.getElementById('chatMessages');
  const bubble = document.createElement('div');
  bubble.className = `chat-bubble ${role}`;
  const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

  bubble.innerHTML = `
    <div class="bubble-content">${role === 'assistant' ? formatAIResponse(content) : escapeHtml(content)}</div>
    <div class="bubble-meta">${time}</div>
  `;

  messagesEl.appendChild(bubble);
}

function clearChat() {
  chatHistory = [];
  includePageContext = true;
  const messagesEl = document.getElementById('chatMessages');
  messagesEl.innerHTML = `
    <div class="chat-welcome">
      <span class="material-symbols-outlined" style="font-size:32px;color:var(--lime);">smart_toy</span>
      <p>Conversa limpa. Posso te ajudar com a página atual ou qualquer questão estratégica.</p>
    </div>
  `;
  document.getElementById('copilotQuick').style.display = 'flex';
  if (pageContext?.title) {
    document.getElementById('chatContextTag').style.display = 'flex';
    document.getElementById('chatContextName').textContent = truncate(pageContext.title, 30);
  }
}

function initQualifyTab() {
  document.getElementById('qualifyPageTitle').textContent = pageContext?.title || 'Página atual';
  document.getElementById('qualifyPageUrl').textContent = truncate(pageContext?.url || '', 50);

  if (qualificationResult) {
    document.getElementById('qualifyPreview').style.display = 'none';
    document.getElementById('qualifyResults').style.display = 'block';
    renderQualification(qualificationResult);
  } else {
    document.getElementById('qualifyPreview').style.display = 'block';
    document.getElementById('qualifyResults').style.display = 'none';
  }
}

async function handleQualify() {
  const previewEl = document.getElementById('qualifyPreview');
  const loadingEl = document.getElementById('qualifyLoading');
  const resultsEl = document.getElementById('qualifyResults');

  previewEl.style.display = 'none';
  loadingEl.style.display = 'flex';
  resultsEl.style.display = 'none';

  const pageScan = await getPageScan(false);
  const result = await sendMessage({
    type: 'QUALIFY_PAGE',
    data: buildPagePayload(pageScan),
  });

  loadingEl.style.display = 'none';

  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (result?.success && result.qualification) {
    qualificationResult = result.qualification;
    resultsEl.style.display = 'block';
    renderQualification(qualificationResult);
    renderHubSnapshot('qualification-ready');
  } else {
    previewEl.style.display = 'block';
    renderErrorCard('qualifyContent', result?.message || 'Erro ao qualificar a página.');
    resultsEl.style.display = 'block';
  }
}

function renderQualification(qual) {
  const scoresEl = document.getElementById('qualifyScores');
  const contentEl = document.getElementById('qualifyContent');

  if (typeof qual === 'string') {
    scoresEl.innerHTML = '';
    contentEl.innerHTML = formatAIResponse(qual);
    return;
  }

  const fitScore = qual.fitScore ?? 0;
  const potential = qual.potentialScore ?? 0;
  const urgency = qual.urgencyScore ?? 0;
  const scoreColor = (value) => value >= 7 ? 'var(--emerald)' : value >= 4 ? 'var(--amber)' : 'var(--red)';

  scoresEl.innerHTML = `
    <div class="qualify-score-card">
      <span class="qualify-score-value" style="color:${scoreColor(fitScore)}">${fitScore}</span>
      <span class="qualify-score-label">Encaixe</span>
    </div>
    <div class="qualify-score-card">
      <span class="qualify-score-value" style="color:${scoreColor(potential)}">${potential}</span>
      <span class="qualify-score-label">Potencial</span>
    </div>
    <div class="qualify-score-card">
      <span class="qualify-score-value" style="color:${scoreColor(urgency)}">${urgency}</span>
      <span class="qualify-score-label">Urgência</span>
    </div>
  `;

  let html = '';
  if (qual.verdict) {
    html += `<h3><span class="material-symbols-outlined">gavel</span> Veredito</h3><p>${escapeHtml(qual.verdict)}</p>`;
  }
  if (qual.confidence) {
    html += `<p><strong>Confiança:</strong> ${escapeHtml(qual.confidence)}</p>`;
  }
  if (qual.recommendedAction) {
    html += `<p><strong>Ação sugerida:</strong> ${escapeHtml(qual.recommendedAction.replaceAll('_', ' '))}</p>`;
  }
  if (qual.positiveSignals?.length) {
    html += `<h3><span class="material-symbols-outlined">thumb_up</span> Sinais Positivos</h3><ul>${qual.positiveSignals.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }
  if (qual.objectionSignals?.length) {
    html += `<h3><span class="material-symbols-outlined">thumb_down</span> Sinais de Objeção</h3><ul>${qual.objectionSignals.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }
  if (qual.nextSteps) {
    html += `<h3><span class="material-symbols-outlined">route</span> Próximos Passos</h3><p>${escapeHtml(qual.nextSteps)}</p>`;
  }
  if (qual.approachSuggestion) {
    html += `<h3><span class="material-symbols-outlined">handshake</span> Sugestão de Abordagem</h3><p>${escapeHtml(qual.approachSuggestion)}</p>`;
  }

  contentEl.innerHTML = html || formatAIResponse(JSON.stringify(qual));
}

function initVisualTab() {
  document.getElementById('visualPageTitle').textContent = pageContext?.title || 'Página atual';
  document.getElementById('visualPageUrl').textContent = truncate(pageContext?.url || '', 50);

  if (latestScreenshot) {
    document.getElementById('screenshotImg').src = latestScreenshot;
    document.getElementById('visualScreenshot').style.display = 'block';
  }

  if (visualResult) {
    document.getElementById('visualPreview').style.display = 'none';
    document.getElementById('visualResults').style.display = 'block';
    renderVisualAnalysis(visualResult);
  } else {
    document.getElementById('visualPreview').style.display = 'block';
    document.getElementById('visualResults').style.display = 'none';
  }
}

async function handleVisualAnalyze() {
  const previewEl = document.getElementById('visualPreview');
  const loadingEl = document.getElementById('visualLoading');
  const screenshotEl = document.getElementById('visualScreenshot');
  const resultsEl = document.getElementById('visualResults');
  const stepsEl = document.getElementById('visualSteps');

  previewEl.style.display = 'none';
  loadingEl.style.display = 'flex';
  screenshotEl.style.display = 'none';
  resultsEl.style.display = 'none';

  const stopSteps = startProgressSteps(stepsEl, [
    'Capturando screenshot da aba...',
    'Otimizando imagem para análise...',
    'Lendo estrutura visual...',
    'Avaliando hierarquia e percepção...',
    'Gerando diagnóstico visual...',
  ], 1000, 1400);

  const screenshotResult = await sendMessage({ type: 'CAPTURE_SCREENSHOT' });
  latestScreenshot = screenshotResult?.screenshot ? await compressDataUrl(screenshotResult.screenshot) : '';

  if (latestScreenshot) {
    document.getElementById('screenshotImg').src = latestScreenshot;
    screenshotEl.style.display = 'block';
  }

  const pageScan = await getPageScan(false);
  const result = await sendMessage({
    type: 'ANALYZE_VISUAL',
    data: {
      ...buildPagePayload(pageScan),
      screenshot: latestScreenshot,
    },
  });

  stopSteps();
  loadingEl.style.display = 'none';

  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  if (result?.success && result.analysis) {
    visualResult = result.analysis;
    resultsEl.style.display = 'block';
    renderVisualAnalysis(visualResult);
    renderHubSnapshot('visual-ready');
  } else {
    previewEl.style.display = 'block';
    renderErrorCard('visualContent', result?.message || 'Erro ao analisar visualmente a página.');
    resultsEl.style.display = 'block';
  }
}

function renderVisualAnalysis(analysis) {
  const container = document.getElementById('visualContent');
  if (typeof analysis === 'string') {
    container.innerHTML = formatAIResponse(analysis);
    return;
  }

  let html = '';
  if (analysis.firstImpression) {
    html += `<h3><span class="material-symbols-outlined">visibility</span> Primeira Impressão</h3><p>${escapeHtml(analysis.firstImpression)}</p>`;
  }

  html += `<h3><span class="material-symbols-outlined">dashboard</span> Leitura Visual</h3>`;
  html += `<p><span class="score-badge ${scoreClass(analysis.brandClarity)}">Marca ${escapeHtml(String(analysis.brandClarity ?? 0))}/10</span></p>`;
  html += `<p><span class="score-badge ${scoreClass(analysis.visualHierarchy)}">Hierarquia ${escapeHtml(String(analysis.visualHierarchy ?? 0))}/10</span></p>`;
  html += `<p><span class="score-badge ${scoreClass(analysis.ctaClarity)}">CTA ${escapeHtml(String(analysis.ctaClarity ?? 0))}/10</span></p>`;
  html += `<p><span class="score-badge ${scoreClass(analysis.perceivedTrust)}">Confianca ${escapeHtml(String(analysis.perceivedTrust ?? 0))}/10</span></p>`;
  html += `<p><span class="score-badge ${scoreClass(analysis.digitalMaturity)}">Maturidade ${escapeHtml(String(analysis.digitalMaturity ?? 0))}/10</span></p>`;

  if (analysis.strengths?.length) {
    html += `<h3><span class="material-symbols-outlined">thumb_up</span> Forças</h3><ul>${analysis.strengths.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }
  if (analysis.weaknesses?.length) {
    html += `<h3><span class="material-symbols-outlined">warning</span> Fragilidades</h3><ul>${analysis.weaknesses.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }
  if (analysis.commercialSignals?.length) {
    html += `<h3><span class="material-symbols-outlined">campaign</span> Sinais Comerciais</h3><ul>${analysis.commercialSignals.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }
  if (analysis.recommendation) {
    html += `<h3><span class="material-symbols-outlined">recommend</span> Recomendação</h3><p>${escapeHtml(analysis.recommendation)}</p>`;
  }
  if (analysis.warnings?.length) {
    html += `<h3><span class="material-symbols-outlined">error</span> Limites da Leitura</h3><ul>${analysis.warnings.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  container.innerHTML = html || formatAIResponse(JSON.stringify(analysis));
}

async function handleSaveAnalysis(kind) {
  const payload = {
    page_context: buildPagePayload(latestPageScan),
    analysis: analysisResult,
    qualification: qualificationResult,
    visual: visualResult,
  };

  if (!payload.analysis && !payload.qualification && !payload.visual) {
    return;
  }

  const result = await sendMessage({
    type: 'SAVE_ANALYSIS',
    data: payload,
  });

  if (result?.auth_expired) {
    showLoginScreen();
    return;
  }

  const button = kind === 'visual'
    ? document.getElementById('btnSaveVisual')
    : document.getElementById('btnSaveAnalysis');

  if (result?.success) {
    showActionFeedback(button, result.created ? 'Salvo no Vault' : 'Contexto atualizado', 'var(--emerald)');
    if (result.lead_id) {
      leadMatch = {
        id: result.lead_id,
        name: analysisResult?.company || extractedData?.name || pageContext?.title || 'Lead',
        pipeline_status: leadMatch?.pipeline_status || 'new',
      };
      renderHubSnapshot('saved');
    }
  } else {
    showActionFeedback(button, 'Falha ao salvar', 'var(--red)');
  }
}

async function getPageScan(force = false) {
  if (!force && latestPageScan) return latestPageScan;
  latestPageScan = await sendMessage({ type: 'EXTRACT_PAGE_CONTENT' });
  return latestPageScan || {};
}

function buildPagePayload(scan = latestPageScan) {
  return {
    url: pageContext?.url || '',
    title: pageContext?.title || '',
    source: currentSource,
    content: scan?.content || '',
    extracted: extractedData || {},
    page_scan: scan || {},
  };
}

function startProgressSteps(container, steps, minDelay, maxDelay) {
  let index = 0;
  let active = true;
  let timerId = null;

  const tick = () => {
    if (!active || index >= steps.length) return;
    container.innerHTML =
      steps.slice(0, index).map((step) => `<span class="step-done">${step}</span>`).join('') +
      `<span class="step-active">${steps[index]}</span>`;
    index += 1;
    timerId = setTimeout(tick, minDelay + Math.random() * (maxDelay - minDelay));
  };

  tick();
  return () => {
    active = false;
    if (timerId) clearTimeout(timerId);
  };
}

async function compressDataUrl(dataUrl, maxWidth = 1280, quality = 0.72) {
  return new Promise((resolve) => {
    if (!dataUrl) {
      resolve('');
      return;
    }

    const img = new Image();
    img.onload = () => {
      const ratio = Math.min(1, maxWidth / img.width);
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(img.width * ratio));
      canvas.height = Math.max(1, Math.round(img.height * ratio));
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        resolve(dataUrl);
        return;
      }
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      resolve(canvas.toDataURL('image/jpeg', quality));
    };
    img.onerror = () => resolve(dataUrl);
    img.src = dataUrl;
  });
}

function renderErrorCard(targetId, message) {
  const el = document.getElementById(targetId);
  if (!el) return;
  el.innerHTML = `<p>${escapeHtml(message)}</p>`;
}

function showActionFeedback(button, text, color) {
  if (!button) return;
  const original = button.innerHTML;
  button.innerHTML = `<span class="material-symbols-outlined">check</span> ${escapeHtml(text)}`;
  button.style.color = color;
  button.style.borderColor = color;
  setTimeout(() => {
    button.innerHTML = original;
    button.style.color = '';
    button.style.borderColor = '';
  }, 2200);
}

function scoreClass(value) {
  const num = Number(value) || 0;
  if (num >= 7) return 'score-high';
  if (num >= 4) return 'score-medium';
  return 'score-low';
}

function buildPlatformLink(path) {
  if (platformUrl) return `${platformUrl}${path}`;
  return path;
}

function formatRoleLabel(role) {
  const labels = {
    admin: 'Admin',
    agent: 'Agente',
    manager: 'Gestor',
    owner: 'Owner',
  };

  if (labels[role]) {
    return labels[role];
  }

  const normalized = String(role || 'agent').replace(/[_-]+/g, ' ').trim();
  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function showMsg(el, msg) {
  el.textContent = msg;
  el.style.display = 'block';
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function truncate(str, len) {
  if (!str) return '';
  return str.length > len ? `${str.substring(0, len - 3)}...` : str;
}

function normalizeComparableText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function formatAIResponse(text) {
  if (!text) return '';

  let html = escapeHtml(text);
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/^### (.+)$/gm, '<h4>$1</h4>');
  html = html.replace(/^## (.+)$/gm, '<h3>$1</h3>');
  html = html.replace(/^[-•] (.+)$/gm, '<li>$1</li>');
  html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');
  html = html.replace(/^\d+\.\s(.+)$/gm, '<li>$1</li>');
  html = html.replace(/\n\n/g, '</p><p>');
  html = html.replace(/\n/g, '<br>');

  if (!html.startsWith('<')) html = `<p>${html}</p>`;
  return html;
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
