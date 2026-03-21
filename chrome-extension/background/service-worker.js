/**
 * Operon Capture — Service Worker (Background)
 * Gerencia auth, mensagens entre popup/content scripts, e fila offline
 */

import { getToken, isAuthenticated, saveAuth, clearAuth, getServerUrl, addToQueue, getQueue, clearQueue } from '../shared/storage.js';
import { api } from '../shared/api-client.js';

// ── Message Handler ──
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  handleMessage(msg, sender).then(sendResponse).catch(err => {
    sendResponse({ error: true, message: err.message });
  });
  return true; // Keep channel open for async response
});

async function handleMessage(msg, sender) {
  switch (msg.type) {

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

    case 'GET_EXTRACTED_DATA': {
      // Solicita dados do content script da tab ativa
      try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        if (!tab?.id) return { data: null, source: 'unknown' };

        const response = await chrome.tabs.sendMessage(tab.id, { type: 'EXTRACT' });
        return response || { data: null, source: 'unknown' };
      } catch {
        return { data: null, source: 'unknown' };
      }
    }

    case 'EXTRACT_ALL_DATA': {
      // Solicita TODOS os leads visíveis do content script (bulk mode)
      try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        if (!tab?.id) return { data: [], source: 'unknown', count: 0 };

        const response = await chrome.tabs.sendMessage(tab.id, { type: 'EXTRACT_ALL' });
        return response || { data: [], source: 'unknown', count: 0 };
      } catch {
        return { data: [], source: 'unknown', count: 0 };
      }
    }

    case 'CAPTURE_BULK': {
      const result = await api.captureBulk(msg.leads);

      if (result.network_error) {
        // Enfileira cada lead individualmente
        for (const lead of msg.leads) {
          await addToQueue(lead);
        }
        const queue = await getQueue();
        updateBadge(String(queue.length), '#fbbf24');
        return { queued: true, message: `${msg.leads.length} leads enfileirados (offline).` };
      }

      if (result.auth_expired) {
        return { error: true, auth_expired: true, message: 'Sessão expirada.' };
      }

      return result;
    }

    case 'CHECK_BULK_DUPLICATES': {
      return await api.checkBulkDuplicates(msg.leads);
    }

    case 'CAPTURE_LEAD': {
      const result = await api.capture(msg.data);

      // Se falha de rede, enfileira
      if (result.network_error) {
        await addToQueue(msg.data);
        const queue = await getQueue();
        updateBadge(String(queue.length), '#fbbf24');
        return { queued: true, message: 'Sem conexão. Lead enfileirado.' };
      }

      // Se token expirou
      if (result.auth_expired) {
        return { error: true, auth_expired: true, message: 'Sessão expirada.' };
      }

      return result;
    }

    case 'CHECK_DUPLICATE': {
      return await api.checkDuplicate(msg.data);
    }

    case 'GET_SEGMENTS': {
      return await api.getSegments();
    }

    case 'GET_ME': {
      return await api.getMe();
    }

    case 'RETRY_QUEUE': {
      return await retryQueue();
    }

    default:
      return { error: true, message: 'Tipo de mensagem desconhecido.' };
  }
}

// ── Queue Retry ──
async function retryQueue() {
  const queue = await getQueue();
  if (queue.length === 0) return { processed: 0 };

  let processed = 0;
  const remaining = [];

  for (const lead of queue) {
    const result = await api.capture(lead);
    if (result.success || result.duplicate) {
      processed++;
    } else {
      remaining.push(lead);
    }
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
  if (color) {
    chrome.action.setBadgeBackgroundColor({ color });
  }
}

// ── On Install ──
chrome.runtime.onInstalled.addListener(() => {
  updateBadge('', '');
});

// ── Periodic queue retry (when online) ──
chrome.alarms?.create('retry-queue', { periodInMinutes: 5 });
chrome.alarms?.onAlarm?.addListener(async (alarm) => {
  if (alarm.name === 'retry-queue') {
    const queue = await getQueue();
    if (queue.length > 0) {
      await retryQueue();
    }
  }
});
