/* Öz Oto Radar — arka plan. İçerik betiğinden gelen ilanları Radar API'sine iletir. */
'use strict';

const DEFAULTS = { server: 'https://ozoto.online', token: '', enabled: true };

const apiBase = (server) => String(server || DEFAULTS.server).trim().replace(/\/+$/, '');

async function call(path, options = {}) {
  const { server, token } = await chrome.storage.sync.get(DEFAULTS);
  if (!token) return { ok: false, error: 'Eklenti anahtarı girilmemiş. Seçenekler sayfasından ekleyin.' };
  let res;
  try {
    res = await fetch(apiBase(server) + path, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer ' + token,
        'X-Radar-Token': token,
        ...(options.headers || {}),
      },
    });
  } catch (e) {
    return { ok: false, error: 'Sunucuya ulaşılamadı: ' + (e && e.message ? e.message : e) };
  }
  let data;
  try {
    data = await res.json();
  } catch {
    data = { ok: false, error: 'Sunucu yanıtı okunamadı (HTTP ' + res.status + ').' };
  }
  return data;
}

async function remember(result, count) {
  const today = new Date().toISOString().slice(0, 10);
  const stats = await chrome.storage.local.get({ day: today, sent: 0, lastError: '', lastAt: '' });
  if (stats.day !== today) {
    stats.day = today;
    stats.sent = 0;
  }
  if (result.ok) {
    stats.sent += count;
    stats.lastError = '';
  } else {
    stats.lastError = result.error || 'Bilinmeyen hata';
  }
  stats.lastAt = new Date().toLocaleTimeString('tr-TR');
  await chrome.storage.local.set(stats);
  chrome.action.setBadgeBackgroundColor({ color: result.ok ? '#16a34a' : '#dc2626' });
  chrome.action.setBadgeText({ text: result.ok ? (stats.sent > 999 ? '999+' : String(stats.sent || '')) : '!' });
}

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message && message.type === 'ingest' && Array.isArray(message.items)) {
    call('/api/radar/eklenti', { method: 'POST', body: JSON.stringify({ items: message.items.slice(0, 100) }) })
      .then(async (result) => {
        await remember(result, message.items.length);
        sendResponse(result);
      });
    return true;
  }
  if (message && message.type === 'detail' && message.payload) {
    call('/api/radar/eklenti/detay', { method: 'POST', body: JSON.stringify(message.payload) })
      .then(async (result) => {
        await remember(result, 1);
        sendResponse(result);
      });
    return true;
  }
  if (message && message.type === 'test') {
    call('/api/radar/eklenti/durum').then(sendResponse);
    return true;
  }
  return false;
});
