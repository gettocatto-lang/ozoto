'use strict';
const DEFAULTS = { server: 'https://ozoto.online', token: '', enabled: true };
const $ = (id) => document.getElementById(id);
const say = (text, ok) => {
  $('status').textContent = text;
  $('status').className = ok ? 'ok' : 'err';
};

chrome.storage.sync.get(DEFAULTS).then((s) => {
  $('server').value = s.server;
  $('token').value = s.token;
  $('enabled').checked = s.enabled;
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
  await chrome.storage.sync.set({ server, token: $('token').value.trim(), enabled: $('enabled').checked });
  say('Kaydedildi.', true);
  return true;
}

$('save').addEventListener('click', save);
$('test').addEventListener('click', async () => {
  if (!(await save())) return;
  const res = await chrome.runtime.sendMessage({ type: 'test' });
  say(res && res.ok ? 'Bağlantı başarılı: ' + res.site : 'Bağlantı başarısız: ' + ((res && res.error) || 'bilinmeyen hata'), !!(res && res.ok));
});
