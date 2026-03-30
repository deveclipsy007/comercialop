/**
 * Operon Intelligence — Service Worker (Background)
 * Gerencia auth, mensagens, Side Panel, extração profunda, screenshot e fila offline
 */

import { isAuthenticated, saveAuth, clearAuth, addToQueue, getQueue, clearQueue, updateAuthContext } from '../shared/storage.js';
import { api } from '../shared/api-client.js';

// ── Abrir Side Panel ao clicar no ícone ──
chrome.action.onClicked.addListener(async (tab) => {
  try {
    await chrome.sidePanel.open({ tabId: tab.id });
  } catch (err) {
    console.warn('[Operon] Error opening side panel:', err);
  }
});

// ── Configurar Side Panel ──
chrome.sidePanel?.setOptions?.({
  enabled: true,
});

// ── Message Handler ──
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  handleMessage(msg, sender).then(sendResponse).catch(err => {
    sendResponse({ error: true, message: err.message });
  });
  return true;
});

const CONTENT_SCRIPT_SPECS = {
  googleMaps: {
    file: 'content/google-maps.js',
    marker: '__operonGoogleMapsExtractorLoaded',
  },
  linkedin: {
    file: 'content/linkedin.js',
    marker: '__operonLinkedinExtractorLoaded',
  },
  generic: {
    file: 'content/generic.js',
    marker: '__operonGenericExtractorLoaded',
  },
};

async function handleMessage(msg, sender) {
  switch (msg.type) {

    // ═══ AUTH ═══
    case 'CHECK_AUTH': {
      const authed = await isAuthenticated();
      return { authenticated: authed };
    }

    case 'LOGIN': {
      const result = await api.login(msg.serverUrl, msg.email, msg.password);
      if (result.success && result.token) {
        await saveAuth({
          token: result.token,
          user: result.user,
          tenant_id: result.tenant_id,
          server_url: msg.serverUrl,
        });
        updateBadge('✓', '#a3e635');
        return { success: true, user: result.user };
      }
      return { error: true, message: result.message || 'Falha na autenticação.' };
    }

    case 'LOGOUT': {
      await api.logout();
      await clearAuth();
      updateBadge('', '');
      return { success: true };
    }

    // ═══ DATA EXTRACTION ═══
    case 'GET_EXTRACTED_DATA': {
      return await sendMessageToActiveTab(
        { type: 'EXTRACT' },
        { data: null, source: 'unknown' }
      );
    }

    case 'EXTRACT_ALL_DATA': {
      return await sendMessageToActiveTab(
        { type: 'EXTRACT_ALL' },
        { data: [], source: 'unknown', count: 0 }
      );
    }

    // ═══ DEEP PAGE CONTENT EXTRACTION (for AI analysis) ═══
    case 'EXTRACT_PAGE_CONTENT': {
      try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        if (!tab?.id) return { content: '', url: '' };

        const results = await chrome.scripting.executeScript({
          target: { tabId: tab.id },
          func: extractFullPageContent,
        });

        return results?.[0]?.result || { content: '', url: '' };
      } catch (err) {
        console.warn('[Operon] Page content extraction failed:', err);
        return { content: '', url: '', error: err.message };
      }
    }

    // ═══ SCREENSHOT CAPTURE ═══
    case 'CAPTURE_SCREENSHOT': {
      try {
        const dataUrl = await chrome.tabs.captureVisibleTab(null, {
          format: 'jpeg',
          quality: 75,
        });
        return { screenshot: dataUrl };
      } catch (err) {
        console.warn('[Operon] Screenshot failed:', err);
        return { screenshot: null, error: err.message };
      }
    }

    // ═══ AI ANALYSIS ═══
    case 'ANALYZE_PAGE': {
      return await api.analyzePage(msg.data);
    }

    case 'QUALIFY_PAGE': {
      return await api.qualifyPage(msg.data);
    }

    case 'ANALYZE_VISUAL': {
      return await api.analyzeVisual(msg.data);
    }

    case 'COPILOT_CHAT': {
      return await api.copilotChat(msg.data);
    }

    case 'SAVE_ANALYSIS': {
      return await api.saveAnalysis(msg.data);
    }

    // ═══ LEAD CAPTURE ═══
    case 'CAPTURE_LEAD': {
      const result = await api.capture(msg.data);
      if (result.network_error) {
        await addToQueue(msg.data);
        const queue = await getQueue();
        updateBadge(String(queue.length), '#fbbf24');
        return { queued: true, message: 'Sem conexão. Lead enfileirado.' };
      }
      if (result.auth_expired) {
        return { error: true, auth_expired: true, message: 'Sessão expirada.' };
      }
      return result;
    }

    case 'CAPTURE_BULK': {
      const result = await api.captureBulk(msg.leads);
      if (result.network_error) {
        for (const lead of msg.leads) { await addToQueue(lead); }
        const queue = await getQueue();
        updateBadge(String(queue.length), '#fbbf24');
        return { queued: true, message: `${msg.leads.length} leads enfileirados (offline).` };
      }
      if (result.auth_expired) {
        return { error: true, auth_expired: true, message: 'Sessão expirada.' };
      }
      return result;
    }

    case 'CHECK_DUPLICATE': {
      return await api.checkDuplicate(msg.data);
    }

    case 'CHECK_BULK_DUPLICATES': {
      return await api.checkBulkDuplicates(msg.leads);
    }

    case 'GET_SEGMENTS': {
      return await api.getSegments();
    }

    case 'GET_ME': {
      const result = await api.getMe();
      await syncStoredAuthContext(result);
      return result;
    }

    case 'SWITCH_TENANT': {
      const result = await api.switchTenant(msg.tenantId);
      await syncStoredAuthContext(result);
      return result;
    }

    case 'RETRY_QUEUE': {
      return await retryQueue();
    }

    default:
      return { error: true, message: 'Tipo de mensagem desconhecido.' };
  }
}

async function syncStoredAuthContext(result) {
  if (!result?.success) return;

  await updateAuthContext({
    user: {
      id: result.user_id,
      name: result.user_name,
      email: result.email,
      role: result.tenant_role || result.role || 'agent',
    },
    tenant_id: result.tenant_id,
  });
}

async function getActiveTab() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  return tab || null;
}

function resolveContentScriptSpec(url) {
  if (!url) return null;

  try {
    const parsed = new URL(url);
    if (!/^https?:$/.test(parsed.protocol)) return null;

    const host = parsed.hostname.toLowerCase();
    const path = parsed.pathname || '';

    if ((host.startsWith('maps.google.') || host.includes('google.')) && path.startsWith('/maps')) {
      return CONTENT_SCRIPT_SPECS.googleMaps;
    }

    if (host === 'www.linkedin.com' || host.endsWith('.linkedin.com')) {
      return CONTENT_SCRIPT_SPECS.linkedin;
    }

    return CONTENT_SCRIPT_SPECS.generic;
  } catch {
    return null;
  }
}

async function hasInjectedMarker(tabId, marker) {
  try {
    const results = await chrome.scripting.executeScript({
      target: { tabId },
      func: (markerKey) => Boolean(window[markerKey]),
      args: [marker],
    });

    return Boolean(results?.[0]?.result);
  } catch {
    return false;
  }
}

async function ensureContentScript(tab) {
  if (!tab?.id) return null;

  const spec = resolveContentScriptSpec(tab.url);
  if (!spec) return null;

  const alreadyLoaded = await hasInjectedMarker(tab.id, spec.marker);
  if (!alreadyLoaded) {
    await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: [spec.file],
    });
  }

  return spec;
}

function shouldRetryEmptyExtraction(message, spec, response) {
  return message?.type === 'EXTRACT_ALL'
    && spec === CONTENT_SCRIPT_SPECS.googleMaps
    && (!response?.data || response.data.length === 0);
}

async function retryEmptyExtraction(tabId, message, initialResponse) {
  const retryDelays = [450, 900];
  let lastResponse = initialResponse;

  for (const delayMs of retryDelays) {
    await new Promise((resolve) => setTimeout(resolve, delayMs));

    try {
      const retryResponse = await chrome.tabs.sendMessage(tabId, message);
      if (retryResponse?.data?.length) {
        return retryResponse;
      }

      if (retryResponse) {
        lastResponse = retryResponse;
      }
    } catch {}
  }

  return lastResponse;
}

async function sendMessageToActiveTab(message, fallback) {
  const tab = await getActiveTab();
  if (!tab?.id) return fallback;

  const spec = resolveContentScriptSpec(tab.url);
  if (!spec) return fallback;

  try {
    await ensureContentScript(tab);
    const response = await chrome.tabs.sendMessage(tab.id, message);
    if (shouldRetryEmptyExtraction(message, spec, response)) {
      const retryResponse = await retryEmptyExtraction(tab.id, message, response);
      return retryResponse || fallback;
    }
    return response || fallback;
  } catch (err) {
    console.warn('[Operon] Tab message failed, retrying after reinjection:', err);

    try {
      await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        files: [spec.file],
      });

      const retryResponse = await chrome.tabs.sendMessage(tab.id, message);
      return retryResponse || fallback;
    } catch (retryErr) {
      console.warn('[Operon] Tab message retry failed:', retryErr);
      return {
        ...fallback,
        error: true,
        message: retryErr.message || err.message || 'Falha ao comunicar com a aba ativa.',
      };
    }
  }
}

// ══════════════════════════════════════════════════════════════
// DEEP PAGE CONTENT EXTRACTION FUNCTION
// Executada no contexto da página via chrome.scripting.executeScript
// ══════════════════════════════════════════════════════════════

function extractFullPageContent() {
  try {
    const result = {
      url: window.location.href,
      title: document.title,
      content: '',
      meta: {},
      headers: [],
      contacts: {},
      social: {},
    };

    // 1. Meta tags
    const metaTags = ['description', 'keywords', 'author', 'og:title', 'og:description',
                      'og:site_name', 'og:type', 'og:url', 'twitter:description'];
    metaTags.forEach(name => {
      const el = document.querySelector(`meta[name="${name}"], meta[property="${name}"]`);
      if (el?.content) result.meta[name] = el.content.trim();
    });

    // 2. Headers structure
    document.querySelectorAll('h1, h2, h3').forEach(h => {
      const text = h.textContent?.trim();
      if (text && text.length > 2 && text.length < 200) {
        result.headers.push({ tag: h.tagName.toLowerCase(), text });
      }
    });

    // 3. Schema.org JSON-LD
    const jsonLdScripts = document.querySelectorAll('script[type="application/ld+json"]');
    const schemas = [];
    jsonLdScripts.forEach(script => {
      try {
        const data = JSON.parse(script.textContent);
        schemas.push(data);
      } catch {}
    });
    if (schemas.length > 0) {
      result.meta.schema_org = JSON.stringify(schemas).substring(0, 3000);
    }

    // 4. Main visible text (limited to ~8000 chars for AI context)
    const mainSelectors = ['main', 'article', '[role="main"]', '#content', '.content',
                           '#main', '.main-content', '.page-content', '.site-content'];
    let mainText = '';

    for (const selector of mainSelectors) {
      const el = document.querySelector(selector);
      if (el) {
        mainText = el.innerText || '';
        break;
      }
    }

    if (!mainText) {
      mainText = document.body?.innerText || '';
    }

    // Clean and truncate
    mainText = mainText
      .replace(/\s+/g, ' ')
      .replace(/(.)\1{5,}/g, '$1$1$1') // Remove repeated chars
      .trim()
      .substring(0, 8000);

    result.content = mainText;

    // 5. Contact information
    const bodyText = document.body?.innerText?.substring(0, 20000) || '';

    // Phones
    const phoneMatches = bodyText.match(/(?:\+55\s?)?(?:\(?\d{2}\)?\s?)\d{4,5}[-.\s]?\d{4}/g);
    if (phoneMatches) {
      result.contacts.phones = [...new Set(phoneMatches.slice(0, 5).map(p => p.trim()))];
    }

    // Emails
    const emailMatches = bodyText.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g);
    if (emailMatches) {
      result.contacts.emails = [...new Set(
        emailMatches
          .filter(e => !/(example|test|wixpress|wordpress|jquery|sentry|googleapis)/.test(e))
          .slice(0, 5)
          .map(e => e.toLowerCase())
      )];
    }

    // 6. Social links
    const links = document.querySelectorAll('a[href]');
    links.forEach(link => {
      const href = link.href || '';
      if (href.includes('linkedin.com/') && !result.social.linkedin) result.social.linkedin = href.split('?')[0];
      if (href.includes('instagram.com/') && !result.social.instagram) result.social.instagram = href.split('?')[0];
      if (href.includes('facebook.com/') && !result.social.facebook) result.social.facebook = href.split('?')[0];
      if (href.includes('youtube.com/') && !result.social.youtube) result.social.youtube = href.split('?')[0];
      if (href.includes('twitter.com/') && !result.social.twitter) result.social.twitter = href.split('?')[0];
      if (href.includes('x.com/') && !result.social.twitter) result.social.twitter = href.split('?')[0];
      if (href.includes('wa.me/') && !result.social.whatsapp) result.social.whatsapp = href;
    });

    // 7. Page statistics
    result.meta.images_count = document.querySelectorAll('img').length;
    result.meta.links_count = links.length;
    result.meta.has_form = document.querySelectorAll('form').length > 0;
    result.meta.has_video = document.querySelectorAll('video, iframe[src*="youtube"], iframe[src*="vimeo"]').length > 0;

    return result;
  } catch (err) {
    return { content: '', url: window.location.href, error: err.message };
  }
}

// ══════════════════════════════════════════════════════════════
// QUEUE RETRY
// ══════════════════════════════════════════════════════════════

async function retryQueue() {
  const queue = await getQueue();
  if (queue.length === 0) return { processed: 0 };

  let processed = 0;
  const remaining = [];

  for (const lead of queue) {
    const result = await api.capture(lead);
    if (result.success || result.duplicate) { processed++; }
    else { remaining.push(lead); }
  }

  if (remaining.length > 0) {
    await chrome.storage.local.set({ operon_queue: remaining });
    updateBadge(String(remaining.length), '#fbbf24');
  } else {
    await clearQueue();
    updateBadge('', '');
  }

  return { processed, remaining: remaining.length };
}

// ── Badge ──
function updateBadge(text, color) {
  chrome.action.setBadgeText({ text });
  if (color) chrome.action.setBadgeBackgroundColor({ color });
}

// ── On Install ──
chrome.runtime.onInstalled.addListener(() => {
  updateBadge('', '');
});

// ── Periodic queue retry ──
chrome.alarms?.create('retry-queue', { periodInMinutes: 5 });
chrome.alarms?.onAlarm?.addListener(async (alarm) => {
  if (alarm.name === 'retry-queue') {
    const queue = await getQueue();
    if (queue.length > 0) await retryQueue();
  }
});
