/**
 * Operon Capture — Client-side Normalizer
 * Limpeza básica de dados antes de enviar ao servidor
 */

export function normalizePhone(raw) {
  if (!raw) return '';
  let clean = raw.replace(/[^\d+]/g, '');
  // Remove +55 duplicado
  if (clean.startsWith('+55')) clean = clean.slice(3);
  else if (clean.startsWith('55') && clean.length >= 12) clean = clean.slice(2);
  else if (clean.startsWith('+')) clean = clean.slice(1);
  return clean.length >= 8 ? clean : '';
}

export function normalizeEmail(raw) {
  if (!raw) return '';
  const email = raw.trim().toLowerCase();
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? email : '';
}

export function normalizeUrl(raw) {
  if (!raw) return '';
  let url = raw.trim();
  if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
  // Remove tracking params
  try {
    const u = new URL(url);
    ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','fbclid','gclid','ref'].forEach(p => u.searchParams.delete(p));
    return u.toString();
  } catch {
    return '';
  }
}

export function cleanText(raw) {
  if (!raw) return '';
  return raw.trim().replace(/\s+/g, ' ').substring(0, 500);
}

export function normalizeLeadData(data) {
  return {
    ...data,
    name: cleanText(data.name),
    segment: cleanText(data.segment),
    phone: data.phone ? normalizePhone(data.phone) : '',
    email: data.email ? normalizeEmail(data.email) : '',
    website: data.website ? normalizeUrl(data.website) : '',
    address: cleanText(data.address),
    category: cleanText(data.category),
    cargo: cleanText(data.cargo),
    bio: cleanText(data.bio),
  };
}
