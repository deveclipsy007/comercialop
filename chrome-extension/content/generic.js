/**
 * Operon Capture — Generic Site Extractor
 * Extrai dados de qualquer website usando meta tags, Schema.org, e regex
 */

(() => {
  if (window.__operonGenericExtractorLoaded) {
    return;
  }

  window.__operonGenericExtractorLoaded = true;

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg.type === 'EXTRACT') {
      const data = extractGeneric();
      sendResponse({ data, source: 'generic', url: window.location.href });
    }
    return true;
  });

  function extractGeneric() {
    try {
      const data = {};

      // 1. Schema.org JSON-LD (mais estruturado)
      const jsonLdScripts = document.querySelectorAll('script[type="application/ld+json"]');
      for (const script of jsonLdScripts) {
        try {
          const json = JSON.parse(script.textContent);
          const schemas = Array.isArray(json) ? json : [json];
          for (const schema of schemas) {
            if (['Organization','LocalBusiness','Restaurant','Store','MedicalBusiness',
                 'LegalService','FinancialService','RealEstateAgent','ProfessionalService',
                 'AutoDealer','HealthAndBeautyBusiness','SportsActivityLocation']
                .some(t => (schema['@type'] || '').includes(t))) {
              data.name = data.name || schema.name || '';
              data.phone = data.phone || schema.telephone || '';
              data.email = data.email || schema.email || '';
              data.address = data.address || formatAddress(schema.address) || '';
              data.website = data.website || schema.url || '';
              data.bio = data.bio || schema.description || '';
              if (schema.aggregateRating) {
                data.rating = schema.aggregateRating.ratingValue;
                data.review_count = schema.aggregateRating.reviewCount;
              }
            }
          }
        } catch {}
      }

      // 2. OpenGraph meta tags
      data.name = data.name || getMeta('og:site_name') || getMeta('og:title') || '';
      data.bio = data.bio || getMeta('og:description') || getMeta('description') || '';
      data.website = data.website || getMeta('og:url') || window.location.origin;

      // 3. Title fallback
      if (!data.name) {
        data.name = document.title.split(/[|\-–—]/)
          .map(s => s.trim())
          .reduce((a, b) => a.length <= b.length ? b : a, '')
          .substring(0, 100);
      }

      // 4. Regex — telefones no corpo da página
      if (!data.phone) {
        const pageText = document.body.innerText.substring(0, 20000);
        const phonePatterns = [
          /(?:\+55\s?)?(?:\(?\d{2}\)?\s?)\d{4,5}[-.\s]?\d{4}/g,
          /(?:tel|fone|telefone|whatsapp|celular)[:\s]*([(\d)\s\-+.]{8,20})/gi,
        ];
        for (const pattern of phonePatterns) {
          const matches = pageText.match(pattern);
          if (matches && matches.length > 0) {
            // Pega o primeiro número que parece válido
            const clean = matches[0].replace(/[^\d]/g, '');
            if (clean.length >= 10) {
              data.phone = matches[0].trim();
              break;
            }
          }
        }
      }

      // 5. Regex — emails no corpo da página
      if (!data.email) {
        const pageText = document.body.innerText.substring(0, 20000);
        const emailMatch = pageText.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
        if (emailMatch) {
          const email = emailMatch[0].toLowerCase();
          // Filtra emails genéricos de libs/frameworks
          if (!/(example|test|wixpress|wordpress|jquery|googleapis|sentry)/.test(email)) {
            data.email = email;
          }
        }
      }

      // 6. Links de redes sociais
      const socialLinks = [...document.querySelectorAll('a[href]')];
      for (const link of socialLinks) {
        const href = link.href;
        if (href.includes('linkedin.com/') && !data.linkedin_url) {
          data.linkedin_url = href.split('?')[0];
        }
        if (href.includes('instagram.com/') && !data.instagram_url) {
          data.instagram_url = href.split('?')[0];
        }
      }

      // 7. Endereço — buscar no footer ou seção de contato
      if (!data.address) {
        const addressEl = document.querySelector('[itemtype*="PostalAddress"]') ||
                          document.querySelector('address') ||
                          document.querySelector('[class*="address" i]') ||
                          document.querySelector('[class*="endereco" i]');
        if (addressEl) {
          data.address = addressEl.textContent.trim().replace(/\s+/g, ' ').substring(0, 200);
        }
      }

      // Source info
      data.source = 'website';
      data.source_url = window.location.href;
      data.extractor_type = 'generic';

      // Segment placeholder
      data.segment = '';

      return data;
    } catch (err) {
      console.warn('[Operon Capture] Erro ao extrair dados genéricos:', err);
      return null;
    }
  }

  function getMeta(name) {
    const el = document.querySelector(`meta[property="${name}"]`) ||
               document.querySelector(`meta[name="${name}"]`);
    return el?.content?.trim() || '';
  }

  function formatAddress(addr) {
    if (!addr) return '';
    if (typeof addr === 'string') return addr;
    const parts = [addr.streetAddress, addr.addressLocality, addr.addressRegion, addr.postalCode, addr.addressCountry];
    return parts.filter(Boolean).join(', ');
  }
})();
