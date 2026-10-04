/*
 * Öz Oto Radar — içerik betiği.
 * PASİF çalışır: yalnızca ekranda açık olan sayfadaki ilan kartlarını okur ve Radar'a gönderir.
 * Kendi kendine sayfa gezmez, kaydırmaz, tıklamaz. Satıcı adı/telefonu gönderilmez (telefon desenleri silinir).
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
  /** @type {Map<string, Element>} */
  const cards = new Map();

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
      return;
    }
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
      const { enabled } = await chrome.storage.sync.get({ enabled: true });
      if (!enabled) return;
      const items = collect();
      const fresh = [];
      for (const it of items) {
        cards.set(it.key, it.card);
        const state = sent.get(it.key);
        if (state) {
          if (state.result) badge(it, state.result);
        } else {
          sent.set(it.key, { url: it.url, result: null, retries: 0 });
          fresh.push(it);
        }
      }
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
