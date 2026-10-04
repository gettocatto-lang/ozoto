'use strict';
const $ = (id) => document.getElementById(id);
Promise.all([
  chrome.storage.sync.get({ server: 'https://ozoto.online', enabled: true, token: '' }),
  chrome.storage.local.get({ day: '', sent: 0, lastError: '', lastAt: '' }),
]).then(([s, stats]) => {
  $('enabled').checked = s.enabled;
  $('sent').textContent = stats.day === new Date().toISOString().slice(0, 10) ? String(stats.sent) : '0';
  $('last').textContent = stats.lastAt ? 'Son gönderim: ' + stats.lastAt : 'Henüz gönderim yok. sahibinden, arabam, letgo veya Facebook Marketplace\'te bir liste sayfası açın.';
  $('error').textContent = !s.token ? 'Eklenti anahtarı girilmemiş (Seçenekler).' : stats.lastError;
  $('panel').href = s.server.replace(/\/+$/, '') + '/yonetim/radar';
});
$('enabled').addEventListener('change', (e) => chrome.storage.sync.set({ enabled: e.target.checked }));
