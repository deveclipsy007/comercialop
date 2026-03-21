/**
 * Operon Capture — Storage Manager
 * Wrapper para chrome.storage.local
 */

const KEYS = {
  TOKEN: 'operon_token',
  SERVER: 'operon_server_url',
  USER: 'operon_user',
  TENANT: 'operon_tenant_id',
  QUEUE: 'operon_queue',
};

export async function getToken() {
  const result = await chrome.storage.local.get(KEYS.TOKEN);
  return result[KEYS.TOKEN] || null;
}

export async function setToken(token) {
  await chrome.storage.local.set({ [KEYS.TOKEN]: token });
}

export async function clearToken() {
  await chrome.storage.local.remove([KEYS.TOKEN, KEYS.USER, KEYS.TENANT]);
}

export async function getServerUrl() {
  const result = await chrome.storage.local.get(KEYS.SERVER);
  return result[KEYS.SERVER] || '';
}

export async function setServerUrl(url) {
  // Normaliza: remove trailing slash
  url = url.replace(/\/+$/, '');
  await chrome.storage.local.set({ [KEYS.SERVER]: url });
}

export async function getUser() {
  const result = await chrome.storage.local.get(KEYS.USER);
  return result[KEYS.USER] || null;
}

export async function setUser(user) {
  await chrome.storage.local.set({ [KEYS.USER]: user });
}

export async function getTenantId() {
  const result = await chrome.storage.local.get(KEYS.TENANT);
  return result[KEYS.TENANT] || null;
}

export async function setTenantId(tenantId) {
  await chrome.storage.local.set({ [KEYS.TENANT]: tenantId });
}

export async function isAuthenticated() {
  const token = await getToken();
  return !!token;
}

export async function saveAuth(data) {
  await chrome.storage.local.set({
    [KEYS.TOKEN]: data.token,
    [KEYS.USER]: data.user,
    [KEYS.TENANT]: data.tenant_id,
    [KEYS.SERVER]: data.server_url || await getServerUrl(),
  });
}

export async function clearAuth() {
  await chrome.storage.local.remove([KEYS.TOKEN, KEYS.USER, KEYS.TENANT]);
}

// ── Queue para captura offline ──
export async function getQueue() {
  const result = await chrome.storage.local.get(KEYS.QUEUE);
  return result[KEYS.QUEUE] || [];
}

export async function addToQueue(lead) {
  const queue = await getQueue();
  queue.push({ ...lead, queued_at: new Date().toISOString() });
  await chrome.storage.local.set({ [KEYS.QUEUE]: queue });
}

export async function clearQueue() {
  await chrome.storage.local.set({ [KEYS.QUEUE]: [] });
}
