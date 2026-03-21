/**
 * Operon Intelligence — API Client
 * Comunicação com a plataforma via Bearer Token
 */

import { getToken, getServerUrl, clearAuth } from './storage.js';

class OperonAPI {
  /**
   * Faz uma request autenticada para a API da plataforma.
   */
  async request(method, endpoint, body = null) {
    const serverUrl = await getServerUrl();
    if (!serverUrl) throw new Error('Servidor não configurado');

    const token = await getToken();
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const options = { method, headers };
    if (body && method !== 'GET') {
      options.body = JSON.stringify(body);
    }

    try {
      const response = await fetch(`${serverUrl}${endpoint}`, options);

      if (response.status === 401) {
        await clearAuth();
        return { error: true, auth_expired: true, message: 'Sessão expirada. Faça login novamente.' };
      }

      if (response.status === 429) {
        return { error: true, message: 'Muitas tentativas. Aguarde alguns minutos.' };
      }

      return await response.json();
    } catch (err) {
      return { error: true, network_error: true, message: 'Erro de conexão com o servidor.' };
    }
  }

  // ═══ AUTH ═══
  async login(serverUrl, email, password) {
    try {
      const response = await fetch(`${serverUrl}/api/ext/auth`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      return await response.json();
    } catch {
      return { error: true, message: 'Não foi possível conectar ao servidor.' };
    }
  }

  async logout() {
    return this.request('POST', '/api/ext/logout');
  }

  // ═══ DATA ═══
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

  // ═══ AI ANALYSIS ═══
  async analyzePage(data) {
    return this.request('POST', '/api/ext/analyze-page', data);
  }

  async qualifyPage(data) {
    return this.request('POST', '/api/ext/qualify', data);
  }

  async analyzeVisual(data) {
    return this.request('POST', '/api/ext/analyze-visual', data);
  }

  async copilotChat(data) {
    return this.request('POST', '/api/ext/copilot', data);
  }

  async saveAnalysis(data) {
    return this.request('POST', '/api/ext/save-analysis', data);
  }
}

export const api = new OperonAPI();
