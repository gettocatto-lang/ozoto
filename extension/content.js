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
  let settings = { enabled: true, panel: true, panelMin: 50, analysis: true };
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
        // İlan kartları kısadır; ilan detay sayfasındaki "benzer ilanlar" linki sayfanın tamamını kart sanmasın.
        if ((node.textContent || '').length > 1200 || node.querySelector('h1')) break;
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
    // Detay sayfası eksper analiziyle ayrıca gönderilir; açıkken kaba liste okumasıyla gönderilmez.
    if (self && !items.has(self.key) && !settings.analysis) {
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
    // Detay sayfasında eksper kartı gösterilir; liste paneli yalnızca liste sayfalarında.
    if (!settings.panel || known.size < 2 || listingKey(location.href)) {
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

  // ---- İlan detay sayfası: eksper analizi ----------------------------------------------------------
  // Kullanıcının açtığı ilan sayfasındaki araç bilgilerini, boya-değişen bölümünü ve açıklamayı okuyup Radar'a gönderir;
  // sunucunun ürettiği AL / PAZARLIK / GEÇ analizini sağda bir kartta gösterir. Satıcı kutuları okunmaz.
  const DAMAGE_HINT = /boya|değişen|degisen|hasar|ekspertiz|expertiz|tramer/i;
  const PRIVATE = /seller|owner|contact|phone|telefon|magaza|mağaza|store-?info|profile|user-?info|userbox|classifieduser|member-?info|uye-?bilgi/i;
  const BLOCK = new Set(['ADDRESS', 'ARTICLE', 'ASIDE', 'BLOCKQUOTE', 'BR', 'DD', 'DIV', 'DL', 'DT', 'FIELDSET', 'FIGCAPTION', 'FIGURE',
    'FORM', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HR', 'LI', 'MAIN', 'OL', 'P', 'PRE', 'SECTION', 'TABLE', 'TBODY', 'TD', 'TH', 'THEAD', 'TR', 'UL']);
  const cleanLines = (s) => String(s || '').split('\n').map((l) => l.replace(/\s+/g, ' ').replace(PHONE, '[tel]').trim()).filter(Boolean).join('\n');

  /** Blok öğeleri arasında satır sonu koruyan metin (ayrık kopyada da çalışır). */
  function blockText(root, max = 15000) {
    const out = [];
    let length = 0;
    const walk = (n) => {
      if (length > max) return;
      if (n.nodeType === 3) {
        const t = n.nodeValue;
        if (t && t.trim()) {
          out.push(t);
          length += t.length;
        }
        return;
      }
      if (n.nodeType !== 1) return;
      const tag = n.tagName.toUpperCase();
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT' || tag === 'TEMPLATE' || tag === 'SVG') return;
      const block = BLOCK.has(tag);
      if (block) out.push('\n');
      for (const c of n.childNodes) walk(c);
      out.push(block ? '\n' : tag === 'TD' || tag === 'TH' || tag === 'SPAN' ? ' ' : '');
    };
    walk(root);
    return cleanLines(out.join('')).slice(0, max);
  }

  function detailPayload() {
    const source = document.querySelector('main') || document.body;
    const root = source.cloneNode(true);
    root.querySelectorAll('script, style, noscript, template, iframe, canvas, video, header, footer, nav, [data-oz-radar]').forEach((n) => n.remove());
    const total = (root.textContent || '').length || 1;
    for (const n of [...root.querySelectorAll('[class], [id]')]) {
      const name = (n.getAttribute('class') || '') + ' ' + (n.id || '');
      // Satıcı/iletişim kutuları (kişisel veri) alınmaz; ama sayfanın büyük kısmını kapsayan bir sarmalayıcı silinmez.
      if (PRIVATE.test(name) && (n.textContent || '').length < total * 0.4) n.remove();
    }

    const pairs = [];
    const seen = new Set();
    const add = (label, value) => {
      const l = clean(label).replace(/\s*:$/, '');
      const v = clean(value);
      if (!l || !v || l.length > 40 || v.length > 160 || l === v || /^[\d.,\s₺]+$/.test(l)) return;
      const k = l.toLocaleLowerCase('tr');
      if (seen.has(k)) return;
      seen.add(k);
      pairs.push([l, v]);
    };
    for (const el of root.querySelectorAll('li, tr, dl, div, p, span')) {
      if (pairs.length >= 250) break;
      if (el.tagName === 'DL') {
        for (const dt of el.querySelectorAll(':scope > dt')) {
          const dd = dt.nextElementSibling;
          if (dd && dd.tagName === 'DD') add(dt.textContent, dd.textContent);
        }
        continue;
      }
      const kids = [...el.children].filter((c) => (c.textContent || '').trim());
      if (kids.length === 2 && (el.textContent || '').length <= 220) {
        add(kids[0].textContent, kids[1].textContent);
      } else if ((el.tagName === 'LI' || el.tagName === 'P') && kids.length <= 1) {
        const m = (el.textContent || '').trim().match(/^([^:\n]{2,40}):\s*([^\n]{1,140})$/);
        if (m) add(m[1], m[2]);
      }
    }

    const sections = [];
    const used = new Set();
    for (const h of root.querySelectorAll('h1, h2, h3, h4, h5, h6, strong, b, legend, dt, th, [class*="title" i], [class*="header" i]')) {
      const t = clean(h.textContent);
      if (!t || t.length > 60 || !DAMAGE_HINT.test(t)) continue;
      let box = h.parentElement;
      while (box && box !== root && (box.textContent || '').length < t.length + 30) box = box.parentElement;
      if (!box || used.has(box) || (box.textContent || '').length > 6000) continue;
      used.add(box);
      // Araç şemasında durum sınıf adıyla verilebilir: sınıftan durum, başlık/etiketten parça adı.
      const extra = [];
      for (const el of box.querySelectorAll('[class]')) {
        const cls = el.getAttribute('class') || '';
        const status = /lokal|local/i.test(cls) ? 'Lokal boyalı' : /degis|değiş|changed|replac/i.test(cls) ? 'Değişen' : /boya|paint/i.test(cls) ? 'Boyalı' : '';
        if (!status) continue;
        const name = el.getAttribute('title') || el.getAttribute('aria-label') || el.getAttribute('data-name') || el.getAttribute('data-part') || el.getAttribute('alt') || clean(el.textContent);
        if (name && name.length <= 40) extra.push(status + ': ' + clean(name));
      }
      sections.push({ title: t, text: (blockText(box, 4000) + (extra.length ? '\n' + extra.join('\n') : '')).slice(0, 4000) });
      if (sections.length >= 8) break;
    }

    let description = '';
    for (const el of root.querySelectorAll('#classifiedDescription, [id*="description" i], [class*="description" i], [id*="aciklama" i], [class*="aciklama" i], [class*="explanation" i]')) {
      const t = blockText(el, 6000);
      if (t.length > description.length) description = t;
    }
    const h1 = document.querySelector('h1');
    const priceMatch = ((document.querySelector('main') || document.body).innerText.match(/\d{1,3}(?:\.\d{3})+\s*(?:TL|₺)/) || [])[0] || '';
    return {
      url: (listingKey(location.href) || {}).url || location.href,
      title: clean(h1 ? h1.innerText : document.title),
      price_text: priceMatch,
      pairs,
      sections,
      description,
      text: blockText(root, 15000),
    };
  }

  const CARD_CSS = `
    :host { all: initial; position: fixed; top: 12px; right: 12px; z-index: 2147483647; }
    .box { width: 380px; max-height: 86vh; display: flex; flex-direction: column; background: #0b0f12; color: #eceff0;
      border: 1px solid #1c2329; border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,.4);
      font: 13px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; overflow: hidden; }
    header { padding: 11px 12px; cursor: pointer; user-select: none; border-bottom: 1px solid #1c2329; }
    .top { display: flex; align-items: center; gap: 8px; }
    .v { flex: none; padding: 3px 9px; border-radius: 999px; font-weight: 800; font-size: 12px; letter-spacing: .02em; }
    .v.al { background: #22c55e; color: #052e16; } .v.pazarlik { background: #fcd34d; color: #3b2a00; }
    .v.gec { background: #ef4444; color: #fff; } .v.eksik { background: #3a434a; color: #e5e7eb; }
    .top strong { flex: 1; font-size: 13px; } .top i { font-style: normal; color: #9aa5ab; }
    .head { margin: 7px 0 0; color: #d6dde0; }
    .body { overflow: auto; padding: 4px 12px 12px; }
    .collapsed .body { display: none; } .collapsed .head { display: none; }
    h4 { margin: 12px 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #9aa5ab; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 3px 0; vertical-align: top; } td:last-child { text-align: right; font-weight: 600; padding-left: 10px; }
    ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 4px; }
    li { padding-left: 14px; position: relative; } li::before { content: "•"; position: absolute; left: 2px; color: #64748b; }
    li.red::before { content: "!"; color: #f87171; font-weight: 800; } li.amber::before { content: "▲"; color: #fbbf24; font-size: 9px; top: 3px; }
    li.green::before { content: "✓"; color: #4ade80; } li.red { color: #fecaca; }
    p { margin: 0 0 8px; color: #d6dde0; }
    a { color: #86efac; } .foot { display: flex; justify-content: space-between; gap: 8px; margin-top: 10px; font-size: 12px; }
    .muted { color: #9aa5ab; }
  `;
  let card = null;
  let cardCollapsed = false;
  chrome.storage.local.get({ cardCollapsed: false }).then((v) => {
    cardCollapsed = !!v.cardCollapsed;
    if (card) card.box.classList.toggle('collapsed', cardCollapsed);
  });

  function ensureCard() {
    if (card && card.host.isConnected) return card;
    const host = document.createElement('div');
    host.setAttribute('data-oz-radar', 'analysis');
    const shadow = host.attachShadow({ mode: 'open' });
    const style = new CSSStyleSheet();
    style.replaceSync(CARD_CSS);
    shadow.adoptedStyleSheets = [style];
    const box = document.createElement('div');
    box.className = 'box' + (cardCollapsed ? ' collapsed' : '');
    const header = document.createElement('header');
    const body = document.createElement('div');
    body.className = 'body';
    box.append(header, body);
    shadow.append(box);
    header.addEventListener('click', () => {
      cardCollapsed = !cardCollapsed;
      box.classList.toggle('collapsed', cardCollapsed);
      chrome.storage.local.set({ cardCollapsed });
    });
    document.documentElement.appendChild(host);
    card = { host, box, header, body };
    return card;
  }

  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  };

  function renderCard(state, res) {
    const c = ensureCard();
    const top = el('div', 'top');
    const a = res && res.analysis;
    if (state === 'loading') {
      top.append(el('span', 'v eksik', '…'), el('strong', '', 'Öz Oto Radar inceliyor…'));
      c.header.replaceChildren(top);
      c.body.replaceChildren(el('p', 'muted', 'Araç bilgileri, boya-değişen, tramer ve açıklama okunuyor.'));
      return;
    }
    if (state === 'error' || !a) {
      top.append(el('span', 'v eksik', '!'), el('strong', '', 'Öz Oto Radar'));
      c.header.replaceChildren(top);
      c.body.replaceChildren(el('p', 'muted', 'Analiz yapılamadı: ' + ((res && res.error) || 'sunucudan yanıt yok')));
      return;
    }
    top.append(el('span', 'v ' + a.verdict, a.verdict_label), el('strong', '', a.score !== null && a.score !== undefined ? 'Puan ' + a.score : 'Öz Oto Radar'), el('i', '', cardCollapsed ? '▸' : '▾'));
    c.header.replaceChildren(top, el('p', 'head', a.headline || ''));
    const nodes = [];
    for (const b of a.blocks || []) {
      nodes.push(el('h4', '', b.title || ''));
      if (b.type === 'kv') {
        const table = el('table');
        for (const [k, v] of b.rows || []) {
          const tr = el('tr');
          tr.append(el('td', '', k), el('td', '', v));
          table.append(tr);
        }
        nodes.push(table);
      } else if (b.type === 'list') {
        const ul = el('ul');
        for (const item of b.items || []) ul.append(el('li', item.level || '', item.text));
        nodes.push(ul);
      } else if (b.type === 'text') {
        for (const p of b.paragraphs || []) nodes.push(el('p', '', p));
      }
    }
    const foot = el('div', 'foot');
    const link = el('a', '', "Radar'da aç ↗");
    link.href = res.link;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    foot.append(el('span', 'muted', 'Güven: ' + (a.confidence || '—')), link);
    nodes.push(foot);
    c.body.replaceChildren(...nodes);
  }

  let analyzedHref = '';
  async function maybeAnalyze() {
    const self = listingKey(location.href);
    if (!self || !settings.analysis) {
      if (card) card.host.remove();
      return;
    }
    if (analyzedHref === location.href) return;
    // Sayfanın ana içeriği yüklenmeden okuma (tek sayfa uygulamaları).
    if (!document.querySelector('h1') && (document.body.innerText || '').length < 1500) return;
    analyzedHref = location.href;
    renderCard('loading');
    let res;
    try {
      res = await chrome.runtime.sendMessage({ type: 'detail', payload: detailPayload() });
    } catch (e) {
      res = { ok: false, error: String(e && e.message ? e.message : e) };
    }
    if (analyzedHref !== location.href) return;
    if (!res || !res.ok) {
      analyzedHref = '';
      renderCard('error', res);
      return;
    }
    renderCard('done', res);
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
      settings = await chrome.storage.sync.get({ enabled: true, panel: true, panelMin: 50, analysis: true });
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
      maybeAnalyze();
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
