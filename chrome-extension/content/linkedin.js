/**
 * Operon Capture — LinkedIn Extractor
 * Extrai dados básicos de perfis e company pages do LinkedIn
 * Somente dados visíveis na página — sem requests extras
 */

(() => {
  if (window.__operonLinkedinExtractorLoaded) {
    return;
  }

  window.__operonLinkedinExtractorLoaded = true;

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg.type === 'EXTRACT') {
      const url = window.location.href;
      let data = null;

      if (url.includes('/company/') || url.includes('/school/')) {
        data = extractCompanyPage();
      } else if (url.includes('/in/')) {
        data = extractProfilePage();
      }

      sendResponse({ data, source: 'linkedin', url });
    }
    return true;
  });

  function extractProfilePage() {
    try {
      const data = {};

      // Nome
      data.name = getText('.text-heading-xlarge') ||
                  getText('h1.inline.t-24') ||
                  getText('h1') ||
                  '';

      // Headline (cargo + empresa)
      data.cargo = getText('.text-body-medium.break-words') ||
                   getText('.pv-top-card--list .text-body-medium') ||
                   '';

      // Localização
      data.address = getText('.text-body-small.inline.t-black--light.break-words') ||
                     getText('.pv-top-card--list:nth-child(2) .text-body-small') ||
                     '';

      // URL do perfil
      data.linkedin_url = window.location.href.split('?')[0];

      // Bio/About
      const aboutSection = document.querySelector('#about ~ .display-flex .inline-show-more-text') ||
                           document.querySelector('[id*="about"] .inline-show-more-text') ||
                           document.querySelector('.pv-about__summary-text');
      data.bio = aboutSection?.textContent?.trim()?.substring(0, 500) || '';

      // Experiência atual (primeira posição)
      const expSection = document.querySelector('#experience ~ .pvs-list__outer-container li:first-child') ||
                         document.querySelector('[id*="experience"] .pvs-list li:first-child');
      if (expSection) {
        const spans = expSection.querySelectorAll('.t-bold span, .t-14.t-normal span');
        if (spans.length >= 1) {
          // Se headline não capturou empresa, pega da experiência
          if (!data.cargo) {
            data.cargo = spans[0]?.textContent?.trim() || '';
          }
        }
      }

      // Tentar extrair empresa do headline
      if (data.cargo) {
        const parts = data.cargo.split(/\s+(?:at|@|em|na|no|—|-)\s+/i);
        if (parts.length >= 2) {
          data.segment = parts[parts.length - 1].trim();
        }
      }

      data.source = 'linkedin';
      data.source_url = window.location.href;
      data.extractor_type = 'linkedin';

      return data;
    } catch (err) {
      console.warn('[Operon Capture] Erro ao extrair LinkedIn profile:', err);
      return null;
    }
  }

  function extractCompanyPage() {
    try {
      const data = {};

      // Nome da empresa
      data.name = getText('.org-top-card-summary__title') ||
                  getText('h1.ember-view') ||
                  getText('h1') ||
                  '';

      // Indústria/Segment
      data.segment = getText('.org-top-card-summary-info-list__info-item') ||
                     getText('.org-top-card-summary__tagline') ||
                     '';

      // Tamanho da empresa
      const sizeEl = document.querySelector('.org-about-company-module__company-size-definition-text') ||
                     document.querySelector('[data-test-id="about-us__size"]');
      if (sizeEl) {
        data.bio = 'Porte: ' + sizeEl.textContent.trim();
      }

      // Website
      const linkEl = document.querySelector('.org-top-card-primary-actions a[href*="://"]') ||
                     document.querySelector('.link-without-visited-state');
      if (linkEl) {
        let href = linkEl.href;
        if (href.includes('linkedin.com/redir')) {
          try { href = new URL(href).searchParams.get('url') || href; } catch {}
        }
        data.website = href;
      }

      // Localização
      data.address = getText('.org-top-card-summary-info-list .org-top-card-summary-info-list__info-item:nth-child(2)') || '';

      data.linkedin_url = window.location.href.split('?')[0];
      data.source = 'linkedin';
      data.source_url = window.location.href;
      data.extractor_type = 'linkedin';

      return data;
    } catch (err) {
      console.warn('[Operon Capture] Erro ao extrair LinkedIn company:', err);
      return null;
    }
  }

  function getText(selector) {
    const el = document.querySelector(selector);
    return el?.textContent?.trim() || '';
  }
})();
