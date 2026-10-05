/*
 * Öz Oto Radar — içerik betiği.
 * PASİF çalışır: yalnızca ekranda açık olan sayfadaki ilan kartlarını okur, Radar'a gönderir, puanları rozet ve
 * sağ üstteki "kelepir paneli"nde gösterir. Kendi kendine sayfa gezmez, kaydırmaz, tıklamaz.
 * Satıcı adı/telefonu gönderilmez (telefon desenleri silinir).
 */
(() => {
  'use strict';

  const PATTERNS = [
    { host: /(^|\.)sahibinden\.com$/, path: /\/ilan\/.+-(\d{8,12})(?:\/|$)/ },
    { host: /(^|\.)arabam\.com$/, path: /\/ilan\/.+\/(\d{6,12})\/?$/ },
    { host: /(^|\.)letgo\.com$/, path: /-iid-(\d+)/ },
    { host: /(^|\.)facebook\.com$/, path: /\/marketplace\/item\/(\d+)/ },
  ];
  const PHONE = /(?:\+?90[\s.-]?)?\(?0?[2-5]\d{2}\)?[\s.-]?\d{3}[\s.-]?\d{2}[\s.-]?\d{2}(?![\d.,])/g;
  const BATCH = 50;

  /** @type {Map<string, {url: string, result: object|null, retries: number}>} */
  const sent = new Map();
  /** Sayfada görülen ilanlar (panel başlığı ve karta kaydırma için). @type {Map<string, object>} */
  const known = new Map();
  let settings = { enabled: true, panel: true, panelMin: 50 };
  let lastError = '';

  function listingKey(href) {
    let u;
    try {
      u = new URL(href, location.href);
    } catch {
      return null;
    }
    if (!/^https?:$/.test(u.protocol)) return null;
    for (const p of PATTERNS) {
      if (!p.host.test(u.hostname)) continue;
      const m = u.pathname.match(p.path);
      if (m) {
        const host = u.hostname.replace(/^(www|m|tr)\./, '');
        return { key: host + ':' + m[1], url: u.origin + u.pathname };
      }
    }
    return null;
  }

  const clean = (s) => String(s || '').replace(/\s+/g, ' ').replace(PHONE, '[tel]').trim();

  /** Tablo satırıysa sütun başlıklarıyla etiketler: "Yıl: 2018 · KM: 125.000 km · Fiyat: 850.000 TL". */
  function cardText(card) {
    if (card.tagName === 'TR') {
      const table = card.closest('table');
      const headers = table ? [...table.querySelectorAll('thead th, thead td')].map((h) => clean(h.innerText)) : [];
      const cells = [...card.children];
      if (headers.length && headers.length === cells.length) {
        return clean(cells.map((cell, i) => {
          let value = clean(cell.innerText);
          const header = headers[i];
          if (/^(km|kilometre)$/i.test(header) && /^[\d.\s]+$/.test(value)) value += ' km';
          if (/fiyat/i.test(header) && value && !/tl|₺/i.test(value)) value += ' TL';
          return value ? (header ? header + ': ' : '') + value : '';
        }).filter(Boolean).join(' · ')).slice(0, 1500);
      }
    }
    return clean(card.innerText).slice(0, 1500);
  }

  /** İlan detay sayfası: fiyatın bulunduğu bilgi kutusu (satıcı bölümü ayrı bloktadır, alınmaz). */
  function detailText() {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let box = null;
    while (walker.nextNode()) {
      if (/\d{1,3}(?:\.\d{3})+\s*(?:TL|₺)/i.test(walker.currentNode.nodeValue || '')) {
        box = walker.currentNode.parentElement;
        break;
      }
    }
    let node = box && box.parentElement;
    while (node && node !== document.body && (node.innerText || '').length < 1200) {
      box = node;
      node = node.parentElement;
    }
    const h1 = document.querySelector('h1');
    return clean((h1 ? h1.innerText : '') + ' ' + (box ? box.innerText : '')).slice(0, 2500);
  }

  function collect() {
    const keyOf = new WeakMap();
    const anchors = [...document.querySelectorAll('a[href]')];
    for (const a of anchors) {
      const k = listingKey(a.getAttribute('href'));
      if (k) keyOf.set(a, k);
    }

    const items = new Map();
    for (const a of anchors) {
      const k = keyOf.get(a);
      if (!k) continue;
      // Kart: yukarı çıkarken içinde başka ilan linki olmayan en geniş blok.
      let card = a;
      let node = a.parentElement;
      for (let depth = 0; depth < 10 && node && node !== document.body; depth++, node = node.parentElement) {
        const other = [...node.querySelectorAll('a[href]')].some((x) => {
          const kk = keyOf.get(x);
          return kk && kk.key !== k.key;
        });
        if (other) break;
        card = node;
      }
      const title = clean(a.innerText || a.title || (a.querySelector('img') && a.querySelector('img').alt));
      const text = cardText(card);
      const prev = items.get(k.key);
      if (!prev) {
        items.set(k.key, { key: k.key, url: k.url, title, text, card, anchor: a });
      } else {
        if (title.length > prev.title.length) {
          prev.title = title;
          prev.anchor = a;
        }
        if (text.length > prev.text.length) {
          prev.text = text;
          prev.card = card;
        }
      }
    }

    const self = listingKey(location.href);
    if (self && !items.has(self.key)) {
      const h1 = document.querySelector('h1');
      items.set(self.key, { key: self.key, url: self.url, title: clean(h1 ? h1.innerText : document.title), text: detailText(), card: h1, anchor: h1 });
    }
    return [...items.values()];
  }

  // ---- Rozet (Shadow DOM: sitenin stilleri etkilemez, sitenin CSP'sine takılmaz) -------------------
  const CSS = `
    :host { all: initial; display: inline-block; vertical-align: middle; margin: 2px 6px; }
    a { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px 3px 4px; border-radius: 999px;
        font: 600 12px/1.2 system-ui, -apple-system, "Segoe UI", sans-serif; text-decoration: none; cursor: pointer;
        background: #0b0f12; color: #eceff0; border: 1px solid #1c2329; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,.25); }
    b { display: inline-grid; place-items: center; min-width: 22px; height: 20px; padding: 0 5px; border-radius: 999px; font-size: 12px; }
    .hot b { background: #22c55e; color: #052e16; }
    .good b { background: #86efac; color: #052e16; }
    .ok b { background: #fcd34d; color: #3b2a00; }
    .low b, .none b { background: #3a434a; color: #cfd6da; }
    .warn { border-color: #ef4444; }
    .warn i { color: #fca5a5; font-style: normal; }
  `;
  let sheet = null;

  function badge(item, r) {
    const target = item.anchor || item.card;
    if (!target || !target.isConnected) return;
    const holder = target.closest('td, th') || target.parentElement || target;
    let host = holder.querySelector(':scope > [data-oz-radar]');
    if (!host) {
      host = document.createElement('span');
      host.setAttribute('data-oz-radar', '');
      const shadow = host.attachShadow({ mode: 'open' });
      if (!sheet) {
        sheet = new CSSStyleSheet();
        sheet.replaceSync(CSS);
      }
      shadow.adoptedStyleSheets = [sheet];
      shadow.appendChild(document.createElement('a'));
      if (target === holder) holder.appendChild(host);
      else target.insertAdjacentElement('afterend', host);
    }
    const link = host.shadowRoot.querySelector('a');
    const score = r && typeof r.score === 'number' ? r.score : null;
    const level = score === null ? 'none' : score >= 70 ? 'hot' : score >= 50 ? 'good' : score >= 30 ? 'ok' : 'low';
    let label = 'Radar';
    if (r && r.pending) label = 'değerleniyor…';
    else if (r && typeof r.discount === 'number') label = r.discount >= 0 ? '%' + Math.round(r.discount) + ' altında' : '%' + Math.round(-r.discount) + ' üstünde';
    else if (r) label = 'fiyat/yıl eksik';
    const text = (score === null ? '–' : String(score)) + '|' + label + '|' + (r && r.suspicious ? 1 : 0);
    if (link.dataset.text === text) return;
    link.dataset.text = text;
    link.className = level + (r && r.suspicious ? ' warn' : '');
    link.title = 'Öz Oto Radar' + (r && r.market ? ' · tahmini piyasa ' + r.market.toLocaleString('tr-TR') + ' TL' : '') + ' · ayrıntı için tıklayın';
    if (r && r.link) {
      link.href = r.link;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    }
    link.replaceChildren();
    const b = document.createElement('b');
    b.textContent = score === null ? '–' : String(score);
    link.append(b, document.createTextNode(label));
    if (r && r.suspicious) {
      const i = document.createElement('i');
      i.textContent = ' · şüpheli';
      link.append(i);
    }
  }

  // ---- Kelepir paneli: bu sayfadaki en iyi ilanlar tek bakışta (sağ üst, Shadow DOM) --------------
  const PANEL_CSS = `
    :host { all: initial; position: fixed; top: 12px; right: 12px; z-index: 2147483647; }
    .box { width: 310px; max-height: 70vh; display: flex; flex-direction: column; background: #0b0f12; color: #eceff0;
      border: 1px solid #1c2329; border-radius: 10px; box-shadow: 0 10px 28px rgba(0,0,0,.35);
      font: 13px/1.35 system-ui, -apple-system, "Segoe UI", sans-serif; overflow: hidden; }
    header { display: flex; align-items: center; gap: 8px; padding: 9px 11px; cursor: pointer; user-select: none; }
    header span { flex: 1; min-width: 0; }
    header strong { display: block; font-size: 13px; }
    header small { display: block; color: #9aa5ab; font-size: 11.5px; }
    header i { font-style: normal; color: #9aa5ab; }
    .dot { flex: none; width: 9px; height: 9px; border-radius: 50%; background: #3a434a; }
    .dot.on { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.25); }
    ol { list-style: none; margin: 0; padding: 0 6px 6px; overflow: auto; }
    li { display: flex; align-items: center; gap: 8px; padding: 7px 6px; border-radius: 7px; cursor: pointer; }
    li:hover { background: #151b20; }
    b { flex: none; display: inline-grid; place-items: center; min-width: 26px; height: 22px; padding: 0 4px; border-radius: 999px; font-size: 12px; }
    .hot b { background: #22c55e; color: #052e16; }
    .good b { background: #86efac; color: #052e16; }
    .ok b { background: #fcd34d; color: #3b2a00; }
    .low b { background: #3a434a; color: #cfd6da; }
    .t { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .d { flex: none; color: #86efac; font-weight: 600; font-size: 12px; }
    .w .d { color: #fca5a5; }
    a { flex: none; color: #9aa5ab; text-decoration: none; padding: 0 2px; }
    a:hover { color: #eceff0; }
    .empty { margin: 0; padding: 0 12px 11px; color: #9aa5ab; font-size: 12px; }
    .collapsed ol, .collapsed .empty { display: none; }
  `;
  let panel = null;
  let collapsed = false;
  chrome.storage.local.get({ panelCollapsed: false }).then((v) => {
    collapsed = !!v.panelCollapsed;
    if (panel) panel.box.classList.toggle('collapsed', collapsed);
  });

  function ensurePanel() {
    if (panel && panel.host.isConnected) return panel;
    const host = document.createElement('div');
    host.setAttribute('data-oz-radar', 'panel');
    const shadow = host.attachShadow({ mode: 'open' });
    const style = new CSSStyleSheet();
    style.replaceSync(PANEL_CSS);
    shadow.adoptedStyleSheets = [style];
    const box = document.createElement('div');
    box.className = 'box' + (collapsed ? ' collapsed' : '');
    const header = document.createElement('header');
    header.title = 'Aç / kapat';
    const dot = document.createElement('span');
    dot.className = 'dot';
    const label = document.createElement('span');
    const toggle = document.createElement('i');
    header.append(dot, label, toggle);
    const list = document.createElement('ol');
    const empty = document.createElement('p');
    empty.className = 'empty';
    box.append(header, list, empty);
    shadow.append(box);
    header.addEventListener('click', () => {
      collapsed = !collapsed;
      box.classList.toggle('collapsed', collapsed);
      toggle.textContent = collapsed ? '▸' : '▾';
      chrome.storage.local.set({ panelCollapsed: collapsed });
    });
    toggle.textContent = collapsed ? '▸' : '▾';
    document.documentElement.appendChild(host);
    panel = { host, box, dot, label, list, empty };
    return panel;
  }

  function flash(el) {
    if (!el || !el.isConnected) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const outline = el.style.outline;
    const offset = el.style.outlineOffset;
    el.style.outline = '3px solid #22c55e';
    el.style.outlineOffset = '2px';
    setTimeout(() => {
      el.style.outline = outline;
      el.style.outlineOffset = offset;
    }, 2200);
  }

  function updatePanel() {
    // Tek ilanlık detay sayfasında panel gerekmez; rozet yeter.
    if (!settings.panel || known.size < 2) {
      if (panel) panel.host.remove();
      return;
    }
    let scored = 0;
    let waiting = 0;
    const rows = [];
    for (const [key, it] of known) {
      const state = sent.get(key);
      if (!state) continue; // gönderilemedi; bir sonraki sayfa değişikliğinde yeniden denenir
      const r = state.result;
      if (!r || r.pending) {
        waiting++;
        continue;
      }
      scored++;
      if (typeof r.score === 'number' && r.score >= settings.panelMin) rows.push({ it, r });
    }
    rows.sort((a, b) => b.r.score - a.r.score);

    const p = ensurePanel();
    p.dot.className = 'dot' + (rows.length ? ' on' : '');
    const title = document.createElement('strong');
    title.textContent = 'Öz Oto Radar · ' + (rows.length ? rows.length + ' kelepir' : 'kelepir yok');
    const sub = document.createElement('small');
    sub.textContent = scored + ' ilan puanlandı' + (waiting ? ' · ' + waiting + ' değerleniyor' : '') + ' · eşik ' + settings.panelMin;
    p.label.replaceChildren(title, sub);

    p.list.replaceChildren(...rows.slice(0, 15).map(({ it, r }) => {
      const li = document.createElement('li');
      const level = r.score >= 70 ? 'hot' : r.score >= 50 ? 'good' : r.score >= 30 ? 'ok' : 'low';
      li.className = level + (r.suspicious ? ' w' : '');
      const b = document.createElement('b');
      b.textContent = String(r.score);
      const t = document.createElement('span');
      t.className = 't';
      t.textContent = it.title || it.url;
      t.title = it.title || it.url;
      const d = document.createElement('span');
      d.className = 'd';
      d.textContent = r.suspicious ? 'şüpheli' : typeof r.discount === 'number' && r.discount > 0 ? '%' + Math.round(r.discount) + '↓' : '';
      li.append(b, t, d);
      if (r.link) {
        const a = document.createElement('a');
        a.href = r.link;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = '↗';
        a.title = "Radar'da aç";
        a.addEventListener('click', (e) => e.stopPropagation());
        li.append(a);
      }
      li.addEventListener('click', () => flash(it.card && it.card.isConnected ? it.card : it.anchor));
      return li;
    }));
    p.empty.textContent = rows.length
      ? ''
      : lastError && !scored
        ? "Radar'a ulaşılamadı: " + lastError
        : waiting
          ? 'Puanlar geliyor…'
          : 'Bu sayfada eşiğin üstünde ilan yok. Sonraki sayfaya geçebilirsin.';
  }

  // ---- Gönderim ----------------------------------------------------------------------------------
  async function send(batch) {
    let response;
    try {
      response = await chrome.runtime.sendMessage({
        type: 'ingest',
        items: batch.map(({ url, title, text }) => ({ url, title, text })),
      });
    } catch (e) {
      response = { ok: false, error: String(e && e.message ? e.message : e) };
    }
    if (!response || !response.ok) {
      for (const it of batch) sent.delete(it.key);
      lastError = (response && response.error) || 'bilinmeyen hata';
      updatePanel();
      return;
    }
    lastError = '';
    const byUrl = new Map(batch.map((it) => [it.url, it]));
    const pending = [];
    for (const r of response.results || []) {
      const it = byUrl.get(r.url);
      if (!it) continue;
      const state = sent.get(it.key);
      if (state) state.result = r;
      badge(it, r);
      if (r.pending && state && state.retries < 2) {
        state.retries++;
        pending.push(it);
      }
    }
    updatePanel();
    if (pending.length) setTimeout(() => send(pending), 8000);
  }

  let running = false;
  let again = false;
  async function run() {
    if (running) {
      // Gönderim sürerken yeni kart geldi: bitince bir tur daha.
      again = true;
      return;
    }
    running = true;
    again = false;
    try {
      settings = await chrome.storage.sync.get({ enabled: true, panel: true, panelMin: 50 });
      if (!settings.enabled) return;
      const items = collect();
      const fresh = [];
      for (const it of items) {
        known.set(it.key, it);
        const state = sent.get(it.key);
        if (state) {
          if (state.result) badge(it, state.result);
        } else {
          sent.set(it.key, { url: it.url, result: null, retries: 0 });
          fresh.push(it);
        }
      }
      updatePanel();
      for (let i = 0; i < fresh.length; i += BATCH) {
        await send(fresh.slice(i, i + BATCH));
      }
    } finally {
      running = false;
      if (again) schedule();
    }
  }

  let timer = null;
  const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(run, 1200);
  };

  // Sonsuz kaydırma / tek sayfa uygulamalarında (Facebook) yeni kartlar geldikçe tekrar bak.
  new MutationObserver((mutations) => {
    for (const m of mutations) {
      for (const n of m.addedNodes) {
        if (n.nodeType === 1 && !n.hasAttribute('data-oz-radar')) {
          schedule();
          return;
        }
      }
    }
  }).observe(document.body, { childList: true, subtree: true });

  schedule();
})();
