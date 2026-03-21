/**
 * Operon Capture — Google Maps Extractor
 * Extrai dados de:
 *   1. Painel de detalhes (ficha do negócio aberta)
 *   2. Lista de resultados (primeiro resultado visível como fallback)
 */

(() => {
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg.type === 'EXTRACT') {
      // Tenta primeiro o painel de detalhes, depois lista de resultados
      let data = extractBusinessPanel();
      let source = 'google-maps';

      if (!data || !data.name) {
        data = extractFromResultsList();
        if (data) source = 'google-maps';
      }

      sendResponse({ data, source, url: window.location.href });
    }

    if (msg.type === 'EXTRACT_ALL') {
      // Extrai TODOS os resultados visíveis da lista de busca
      const results = extractAllVisibleResults();
      sendResponse({ data: results, source: 'google-maps', url: window.location.href, count: results.length });
    }

    return true;
  });

  // ═══════════════════════════════════════════════════════════════
  // 0. EXTRAÇÃO EM MASSA — todos os resultados visíveis
  // ═══════════════════════════════════════════════════════════════
  function extractAllVisibleResults() {
    try {
      const results = [];
      const seen = new Set(); // Evita duplicatas na mesma extração

      // Estratégia 1: Containers de resultado padrão do Google Maps
      let containers = document.querySelectorAll('.Nv2PK');

      // Estratégia 2: containers alternativos
      if (containers.length === 0) {
        containers = document.querySelectorAll('[data-result-index]');
      }

      // Estratégia 3: Divs de resultado na sidebar
      if (containers.length === 0) {
        containers = document.querySelectorAll('div.lI9IFe');
      }

      // Estratégia 4: Links diretos dos resultados (mais estável)
      if (containers.length === 0) {
        containers = document.querySelectorAll('a.hfpxzc');
      }

      // Estratégia 5: Qualquer div que contenha links para /maps/place/
      if (containers.length === 0) {
        const placeLinks = document.querySelectorAll('a[href*="/maps/place/"]');
        const parentSet = new Set();
        placeLinks.forEach(link => {
          // Sobe 2 níveis para pegar o container do resultado
          const parent = link.closest('[jsaction]') || link.parentElement?.parentElement;
          if (parent && !parentSet.has(parent)) {
            parentSet.add(parent);
          }
        });
        containers = Array.from(parentSet);
      }

      console.log(`[Operon Capture] Encontrados ${containers.length} containers de resultado`);

      for (const el of containers) {
        try {
          const data = extractSingleResult(el);
          if (data && data.name && !seen.has(data.name.toLowerCase())) {
            seen.add(data.name.toLowerCase());
            results.push(data);
          }
        } catch (err) {
          console.warn('[Operon Capture] Erro ao extrair resultado individual:', err);
        }
      }

      // Estratégia final: se nada funcionou, tenta pelo aria-label dos links
      if (results.length === 0) {
        const allLinks = document.querySelectorAll('a[aria-label][href*="/maps/place/"]');
        for (const link of allLinks) {
          const name = link.getAttribute('aria-label')?.trim();
          if (name && !seen.has(name.toLowerCase())) {
            seen.add(name.toLowerCase());
            results.push({
              name,
              google_maps_url: link.href || '',
              source: 'google_maps',
              source_url: window.location.href,
              extractor_type: 'google-maps',
            });
          }
        }
      }

      console.log(`[Operon Capture] Total extraído: ${results.length} leads`);
      return results;
    } catch (err) {
      console.warn('[Operon Capture] Erro ao extrair todos os resultados:', err);
      return [];
    }
  }

  function extractSingleResult(el) {
    const data = {};

    // Se el é um link <a>, sobe para o container pai para pegar mais dados
    let container = el;
    if (el.tagName === 'A') {
      container = el.closest('.Nv2PK') || el.parentElement || el;
    }

    // Nome — tenta múltiplos seletores
    data.name = container.querySelector('.qBF1Pd')?.textContent?.trim() ||
                container.querySelector('.fontHeadlineSmall')?.textContent?.trim() ||
                container.querySelector('[role="heading"]')?.textContent?.trim() ||
                container.querySelector('.OSrXXb')?.textContent?.trim() ||
                container.querySelector('.NrDZNb')?.textContent?.trim() ||
                el.getAttribute('aria-label')?.trim() ||
                container.getAttribute('aria-label')?.trim() ||
                '';

    if (!data.name) return null;

    // Rating
    const ratingEl = container.querySelector('.MW4etd') || container.querySelector('.ZkP5Je');
    if (ratingEl) {
      data.rating = ratingEl.textContent?.trim()?.replace(',', '.');
    }

    // Review count
    const reviewEl = container.querySelector('.UY7F9') || container.querySelector('.HHrUdb');
    if (reviewEl) {
      const reviewText = reviewEl.textContent || '';
      const reviewMatch = reviewText.match(/([\d.,]+)/);
      if (reviewMatch) {
        data.review_count = reviewMatch[1].replace(/\D/g, '');
      }
    }

    // Categoria e infos
    const infoSpans = container.querySelectorAll('.W4Efsd span, .rllt__details span, .lrzp0, .W4Efsd .W4Efsd span');
    const infoTexts = [];
    infoSpans.forEach(span => {
      const text = span.textContent?.trim();
      if (text && text.length > 1 && !text.startsWith('·') && text !== '·' && text !== '⋅') {
        if (!infoTexts.includes(text)) {
          infoTexts.push(text);
        }
      }
    });

    if (infoTexts.length > 0) {
      data.category = infoTexts[0].replace(/^·\s*/, '').replace(/^⋅\s*/, '');
      data.segment = data.category;
    }

    // Endereço
    for (const text of infoTexts) {
      if (/\d+.*(?:rua|av|alameda|travessa|rod|estr|pça|praça|r\.|al\.)/i.test(text) ||
          /(?:rua|av|alameda|travessa|rod|estr|pça|praça|r\.|al\.).*\d+/i.test(text) ||
          /,\s*\d{5}[-]?\d{3}/.test(text)) {
        data.address = text.replace(/^[·⋅]\s*/, '');
        break;
      }
    }

    // Telefone
    const resultText = container.textContent || '';
    const phoneMatch = resultText.match(/\(?\d{2}\)?\s*\d{4,5}[-.\s]?\d{4}/);
    if (phoneMatch) {
      data.phone = phoneMatch[0].trim();
    }

    // Horário
    const hoursMatch = resultText.match(/(Aberto|Fechado|Open|Closed)\s*[·⋅]?\s*(.*?)(?:\s*[·⋅]|$)/i);
    if (hoursMatch) {
      data.opening_hours = hoursMatch[0].trim().substring(0, 100);
    }

    // Website — link dentro do resultado
    const websiteLink = container.querySelector('a[data-value="Website"]') ||
                        container.querySelector('a[aria-label*="site" i]') ||
                        container.querySelector('a[href*="http"]:not([href*="google"])');
    if (websiteLink) {
      data.website = cleanGoogleRedirect(websiteLink.href);
    }

    // Google Maps URL
    const placeLink = (el.tagName === 'A' ? el : null) ||
                      container.querySelector('a.hfpxzc') ||
                      container.querySelector('a[href*="/maps/place/"]');
    data.google_maps_url = placeLink?.href || '';

    data.source = 'google_maps';
    data.source_url = window.location.href;
    data.extractor_type = 'google-maps';

    return data;
  }

  // ═══════════════════════════════════════════════════════════════
  // 1. EXTRAÇÃO DO PAINEL DE DETALHES (ficha aberta de um negócio)
  // ═══════════════════════════════════════════════════════════════
  function extractBusinessPanel() {
    try {
      const data = {};

      // Nome — seletores do painel lateral
      data.name = getText('h1.DUwDvf') ||
                  getText('[data-header-feature-id] h1') ||
                  getText('h1.fontHeadlineLarge') ||
                  '';

      // Se não encontrou h1 de negócio, não tem painel aberto
      if (!data.name) return null;

      // Categoria
      data.category = getText('button.DkEaL') ||
                      getText('[jsaction*="category"] span') ||
                      getText('.DkEaL') ||
                      '';

      // Endereço — múltiplos seletores
      data.address = getDataItemText('address') ||
                     getButtonTextByIcon('place', 'location_on') ||
                     getAriaLabel('endereço') ||
                     getAriaLabel('address') ||
                     '';

      // Telefone
      data.phone = getDataItemText('phone:tel:') ||
                   getDataItemText('phone') ||
                   getButtonTextByIcon('phone', 'call') ||
                   getAriaLabel('telefone') ||
                   getAriaLabel('phone number') ||
                   findPhoneInPage() ||
                   '';

      // Website
      data.website = getWebsiteUrl() || '';

      // Rating e Reviews
      const ratingData = extractRating();
      data.rating = ratingData.rating;
      data.review_count = ratingData.reviewCount;

      // Horário
      data.opening_hours = extractHours();

      // Google Maps URL
      data.google_maps_url = window.location.href;

      // Segment = category
      data.segment = data.category;

      // Source info
      data.source = 'google_maps';
      data.source_url = window.location.href;
      data.extractor_type = 'google-maps';

      return data;
    } catch (err) {
      console.warn('[Operon Capture] Erro ao extrair painel:', err);
      return null;
    }
  }

  // ═══════════════════════════════════════════════════════════════
  // 2. EXTRAÇÃO DA LISTA DE RESULTADOS (fallback)
  //    Pega o primeiro resultado da lista quando nenhum painel está aberto
  // ═══════════════════════════════════════════════════════════════
  function extractFromResultsList() {
    try {
      // Seletores para itens da lista de resultados do Google Maps
      const resultSelectors = [
        '.Nv2PK',                    // Container principal de cada resultado
        '[data-result-index]',       // Alternativo com índice
        '.THODhf',                   // Outro container de resultado
        'div.lI9IFe',               // Resultado na sidebar
        'a.hfpxzc',                 // Link do resultado
      ];

      let firstResult = null;
      for (const sel of resultSelectors) {
        firstResult = document.querySelector(sel);
        if (firstResult) break;
      }

      if (!firstResult) return null;

      const data = {};

      // Nome do negócio
      data.name = firstResult.querySelector('.qBF1Pd')?.textContent?.trim() ||
                  firstResult.querySelector('.fontHeadlineSmall')?.textContent?.trim() ||
                  firstResult.querySelector('[role="heading"]')?.textContent?.trim() ||
                  firstResult.querySelector('.OSrXXb')?.textContent?.trim() ||
                  firstResult.getAttribute('aria-label')?.trim() ||
                  '';

      if (!data.name) return null;

      // Rating
      const ratingEl = firstResult.querySelector('.MW4etd') ||
                       firstResult.querySelector('.ZkP5Je');
      if (ratingEl) {
        data.rating = ratingEl.textContent?.trim()?.replace(',', '.');
      }

      // Review count
      const reviewEl = firstResult.querySelector('.UY7F9') ||
                       firstResult.querySelector('.HHrUdb');
      if (reviewEl) {
        const reviewText = reviewEl.textContent || '';
        const reviewMatch = reviewText.match(/([\d.,]+)/);
        if (reviewMatch) {
          data.review_count = reviewMatch[1].replace(/\D/g, '');
        }
      }

      // Categoria e info - texto nos spans abaixo do nome
      const infoSpans = firstResult.querySelectorAll('.W4Efsd span, .rllt__details span, .lrzp0');
      const infoTexts = [];
      infoSpans.forEach(span => {
        const text = span.textContent?.trim();
        if (text && text.length > 1 && !text.startsWith('·') && text !== '·') {
          infoTexts.push(text);
        }
      });

      // O primeiro texto geralmente é a categoria
      if (infoTexts.length > 0) {
        data.category = infoTexts[0].replace(/^·\s*/, '');
        data.segment = data.category;
      }

      // Procurar endereço nos textos (geralmente contém número + rua)
      for (const text of infoTexts) {
        if (/\d+.*(?:rua|av|alameda|travessa|rod|estr|pça|praça)/i.test(text) ||
            /(?:rua|av|alameda|travessa|rod|estr|pça|praça).*\d+/i.test(text)) {
          data.address = text.replace(/^·\s*/, '');
          break;
        }
      }

      // Telefone — procurar no texto visível
      const resultText = firstResult.textContent || '';
      const phoneMatch = resultText.match(/\(?\d{2}\)?\s*\d{4,5}[-.\s]?\d{4}/);
      if (phoneMatch) {
        data.phone = phoneMatch[0].trim();
      }

      // Horário
      const hoursMatch = resultText.match(/(Aberto|Fechado)\s*[·⋅]?\s*(.*?)(?:\s*[·⋅]|$)/i);
      if (hoursMatch) {
        data.opening_hours = hoursMatch[0].trim().substring(0, 100);
      }

      // Website — link dentro do resultado
      const websiteLink = firstResult.querySelector('a[data-value="Website"]') ||
                          firstResult.querySelector('a[aria-label*="site" i]') ||
                          firstResult.querySelector('a[href*="http"]:not([href*="google"])');
      if (websiteLink) {
        data.website = cleanGoogleRedirect(websiteLink.href);
      }

      // Google Maps URL — tentar pegar a URL do lugar
      const placeLink = firstResult.querySelector('a.hfpxzc') ||
                        firstResult.querySelector('a[href*="/maps/place/"]');
      data.google_maps_url = placeLink?.href || window.location.href;

      data.source = 'google_maps';
      data.source_url = window.location.href;
      data.extractor_type = 'google-maps';

      return data;
    } catch (err) {
      console.warn('[Operon Capture] Erro ao extrair lista:', err);
      return null;
    }
  }

  // ═══════════════════════════════════════════════════════════════
  // HELPERS
  // ═══════════════════════════════════════════════════════════════

  function getText(selector) {
    const el = document.querySelector(selector);
    return el?.textContent?.trim() || '';
  }

  function getAriaLabel(keyword) {
    // Busca por aria-label que contenha a keyword
    const el = document.querySelector(`[aria-label*="${keyword}" i]`);
    if (!el) return '';
    // Prefere o textContent do filho .Io6YTe ou .fontBodyMedium
    const textChild = el.querySelector('.Io6YTe, .fontBodyMedium, .rogA2c');
    return textChild?.textContent?.trim() || el.textContent?.trim() || '';
  }

  function getDataItemText(prefix) {
    const el = document.querySelector(`[data-item-id*="${prefix}"]`);
    if (!el) return '';
    // Texto geralmente em .Io6YTe ou .fontBodyMedium
    const textEl = el.querySelector('.Io6YTe, .fontBodyMedium, .rogA2c') || el;
    const text = textEl.textContent?.trim() || '';
    // Limpa prefixos como "Endereço: "
    return text.replace(/^(endereço|address|telefone|phone|site|website):\s*/i, '');
  }

  function getButtonTextByIcon(iconClass, iconName) {
    // Busca botões que contêm ícones Material e pega o texto próximo
    const buttons = document.querySelectorAll('button[data-item-id], [role="button"][data-item-id]');
    for (const btn of buttons) {
      const itemId = btn.getAttribute('data-item-id') || '';
      if (itemId.includes(iconClass) || itemId.includes(iconName)) {
        const textEl = btn.querySelector('.Io6YTe, .fontBodyMedium, .rogA2c');
        if (textEl) return textEl.textContent.trim();
      }
    }
    return '';
  }

  function getWebsiteUrl() {
    // Múltiplas estratégias para encontrar o website
    const selectors = [
      '[data-item-id="authority"] a',
      'a[data-item-id="authority"]',
      '[data-item-id*="authority"]',
      'a[aria-label*="site" i]',
      'a[aria-label*="website" i]',
      'a[data-tooltip*="site" i]',
    ];

    for (const sel of selectors) {
      const el = document.querySelector(sel);
      if (el) {
        const href = el.href || el.getAttribute('href') || '';
        if (href) return cleanGoogleRedirect(href);
      }
    }

    // Fallback: procura o link no texto do data-item "authority"
    const authEl = document.querySelector('[data-item-id*="authority"]');
    if (authEl) {
      const textEl = authEl.querySelector('.Io6YTe, .rogA2c');
      if (textEl) {
        const text = textEl.textContent.trim();
        if (text && !text.includes(' ')) return 'https://' + text;
      }
    }

    return '';
  }

  function cleanGoogleRedirect(url) {
    if (!url) return '';
    if (url.includes('google.com/url')) {
      try {
        const u = new URL(url);
        return u.searchParams.get('q') || u.searchParams.get('url') || url;
      } catch {}
    }
    return url;
  }

  function extractRating() {
    const result = { rating: '', reviewCount: '' };

    // Seletor principal de rating
    const ratingEl = document.querySelector('.F7nice span[aria-hidden="true"]') ||
                     document.querySelector('.F7nice span:first-child') ||
                     document.querySelector('.fontDisplayLarge') ||
                     document.querySelector('[role="img"][aria-label*="star" i]');

    if (ratingEl) {
      const ratingText = ratingEl.textContent || ratingEl.getAttribute('aria-label') || '';
      const ratingMatch = ratingText.match(/([\d,\.]+)/);
      if (ratingMatch) {
        result.rating = ratingMatch[1].replace(',', '.');
      }
    }

    // Review count
    const reviewEl = document.querySelector('.F7nice span:nth-child(2)') ||
                     document.querySelector('.F7nice span[aria-label*="review" i]') ||
                     document.querySelector('.F7nice span[aria-label*="comentário" i]');
    if (reviewEl) {
      const reviewText = reviewEl.textContent || reviewEl.getAttribute('aria-label') || '';
      const reviewMatch = reviewText.match(/([\d.,]+)/);
      if (reviewMatch) {
        result.reviewCount = reviewMatch[1].replace(/\D/g, '');
      }
    }

    return result;
  }

  function extractHours() {
    const hoursEl = document.querySelector('[aria-label*="horário" i]') ||
                    document.querySelector('[aria-label*="hours" i]') ||
                    document.querySelector('[data-item-id*="hour"]') ||
                    document.querySelector('[data-item-id*="oh"]') ||
                    document.querySelector('.t39EBf');
    if (!hoursEl) return '';
    return (hoursEl.getAttribute('aria-label') || hoursEl.textContent || '').substring(0, 200);
  }

  function findPhoneInPage() {
    // Último recurso: busca telefone via regex no painel lateral
    const sidePanel = document.querySelector('[role="main"]') || document.body;
    const buttons = sidePanel.querySelectorAll('button[data-item-id]');
    for (const btn of buttons) {
      const itemId = btn.getAttribute('data-item-id') || '';
      if (itemId.includes('phone') || itemId.includes('tel')) {
        const text = btn.textContent?.trim();
        if (text && /[\d\s\-\(\)+]{8,}/.test(text)) {
          // Extrai apenas a parte numérica
          const match = text.match(/[\d\s\-\(\)+]{8,}/);
          return match ? match[0].trim() : '';
        }
      }
    }
    return '';
  }
})();
