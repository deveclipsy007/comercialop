/**
 * Operon Capture — API Client
 * Comunicação com a plataforma via Bearer Token
 */

import { getToken, getServerUrl, clearAuth } from './storage.js';

class OperonAPI {
  /**
   * Faz uma request autenticada para a API da plataforma.
   */
  async request(method, endpoint, body = null) {
    const serverUrl = await getServerUrl();
    if (!serverUrl) {
      throw new Error('Servidor não configurado');
    }

    const token = await getToken();
    const headers = { 'Content-Type': 'application/json' };
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const options = { method, headers };
    if (body && method !== 'GET') {
      options.body = JSON.stringify(body);
    }

    const url = `${serverUrl}${endpoint}`;

    try {
      const response = await fetch(url, options);

      // Token expirado/inválido
      if (response.status === 401) {
        await clearAuth();
        return { error: true, auth_expired: true, message: 'Sessão expirada. Faça login novamente.' };
      }

      if (response.status === 429) {
        return { error: true, message: 'Muitas tentativas. Aguarde alguns minutos.' };
      }

      const data = await response.json();
      return data;
    } catch (err) {
      return { error: true, network_error: true, message: 'Erro de conexão com o servidor.' };
    }
  }

  // ── Auth ──
  async login(serverUrl, email, password) {
    const headers = { 'Content-Type': 'application/json' };
    const url = `${serverUrl}/api/ext/auth`;

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify({ email, password }),
      });
      return await response.json();
    } catch (err) {
      return { error: true, message: 'Não foi possível conectar ao servidor.' };
    }
  }

  async logout() {
    return this.request('POST', '/api/ext/logout');
  }

  // ── Data ──
  async getMe() {
    return this.request('GET', '/api/ext/me');
  }

  async getSegments() {
    return this.request('GET', '/api/ext/segments');
  }

  async checkDuplicate(data) {
    return this.request('POST', '/api/ext/check', data);
  }

  async capture(data) {
    return this.request('POST', '/api/ext/capture', data);
  }

  async captureBulk(leads) {
    return this.request('POST', '/api/ext/capture-bulk', { leads });
  }

  async checkBulkDuplicates(leads) {
    return this.request('POST', '/api/ext/check-bulk', { leads });
  }
}

export const api = new OperonAPI();
