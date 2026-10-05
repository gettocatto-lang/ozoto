'use strict';
const DEFAULTS = { server: 'https://ozoto.online', token: '', enabled: true, panel: true, panelMin: 50 };
const $ = (id) => document.getElementById(id);
const say = (text, ok) => {
  $('status').textContent = text;
  $('status').className = ok ? 'ok' : 'err';
};

chrome.storage.sync.get(DEFAULTS).then((s) => {
  $('server').value = s.server;
  $('token').value = s.token;
  $('enabled').checked = s.enabled;
  $('panel').checked = s.panel;
  $('panelMin').value = s.panelMin;
});

async function save() {
  const server = ($('server').value || DEFAULTS.server).trim().replace(/\/+$/, '');
  let origin;
  try {
    origin = new URL(server).origin;
  } catch {
    say('Sunucu adresi geçersiz.', false);
    return false;
  }
  // ozoto.online dışında bir sunucu seçildiyse Chrome'dan o adrese erişim izni iste.
  if (origin !== 'https://ozoto.online') {
    const granted = await chrome.permissions.request({ origins: [origin + '/*'] });
    if (!granted) {
      say('Bu sunucuya erişim izni verilmedi.', false);
      return false;
    }
  }
  const panelMin = Math.max(0, Math.min(100, parseInt($('panelMin').value, 10) || 0));
  await chrome.storage.sync.set({ server, token: $('token').value.trim(), enabled: $('enabled').checked, panel: $('panel').checked, panelMin });
  say('Kaydedildi.', true);
  return true;
}

$('save').addEventListener('click', save);
$('test').addEventListener('click', async () => {
  if (!(await save())) return;
  const res = await chrome.runtime.sendMessage({ type: 'test' });
  say(res && res.ok ? 'Bağlantı başarılı: ' + res.site : 'Bağlantı başarısız: ' + ((res && res.error) || 'bilinmeyen hata'), !!(res && res.ok));
});
