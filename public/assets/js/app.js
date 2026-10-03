/* Ozoto — küçük, bağımlılıksız istemci betiği. JS kapalıyken de site ve form çalışır. */
(() => {
  'use strict';

  // Mobil menü
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('main-nav');
  if (toggle && nav) {
    const setOpen = (open) => {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(!nav.classList.contains('is-open')));
    nav.addEventListener('click', (e) => {
      if (e.target.closest('a')) setOpen(false);
    });
  }

  // Kilometre / fiyat alanlarında binlik ayraç (125000 → 125.000)
  document.querySelectorAll('[data-thousands]').forEach((input) => {
    const format = () => {
      const digits = input.value.replace(/\D+/g, '');
      input.value = digits ? Number(digits).toLocaleString('tr-TR') : '';
    };
    input.addEventListener('input', format);
    format();
  });

  // Fotoğraflar: gönderimden önce tarayıcıda küçült (yükleme süresi ve sunucu limiti için)
  const photoInput = document.querySelector('[data-photo-input]');
  const previews = document.querySelector('[data-photo-previews]');
  let pending = null;

  if (photoInput && previews && 'DataTransfer' in window) {
    const max = Number(photoInput.dataset.max || 10);
    const MAX_SIDE = 1600;
    const QUALITY = 0.82;

    const resize = (file) => new Promise((resolve) => {
      if (!file.type.startsWith('image/')) {
        resolve(file);
        return;
      }
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        const scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        if (scale === 1 && file.type === 'image/jpeg' && file.size < 1.5 * 1024 * 1024) {
          resolve(file);
          return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
          if (!blob) {
            resolve(file);
            return;
          }
          const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
          resolve(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
        }, 'image/jpeg', QUALITY);
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        resolve(file);
      };
      img.src = url;
    });

    photoInput.addEventListener('change', () => {
      const selected = Array.from(photoInput.files || []);
      const files = selected.slice(0, max);
      previews.replaceChildren();
      if (files.length === 0) return;

      pending = Promise.all(files.map(resize)).then((resized) => {
        const dt = new DataTransfer();
        resized.forEach((f) => dt.items.add(f));
        photoInput.files = dt.files;

        resized.forEach((f) => {
          const figure = document.createElement('figure');
          const img = document.createElement('img');
          img.alt = '';
          img.src = URL.createObjectURL(f);
          figure.appendChild(img);
          previews.appendChild(figure);
        });
        const status = document.createElement('p');
        status.className = 'photo-status';
        status.textContent = selected.length > max
          ? `En fazla ${max} fotoğraf eklenebilir; ilk ${max} fotoğraf alındı.`
          : `${resized.length} fotoğraf eklendi.`;
        previews.appendChild(status);
        pending = null;
      });
    });

    const zone = photoInput.closest('.dropzone');
    if (zone) {
      ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, () => zone.classList.add('is-dragover')));
      ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, () => zone.classList.remove('is-dragover')));
    }
  }

  // Çift gönderimi engelle; fotoğraflar hâlâ küçültülüyorsa bitmesini bekle
  const form = document.querySelector('[data-apply-form]');
  if (form) {
    const button = form.querySelector('[data-submit]');
    const label = button ? button.innerHTML : '';
    form.addEventListener('submit', (e) => {
      if (button) {
        button.disabled = true;
        button.textContent = pending ? 'Fotoğraflar hazırlanıyor…' : 'Gönderiliyor…';
      }
      if (pending) {
        e.preventDefault();
        pending.then(() => {
          if (button) button.textContent = 'Gönderiliyor…';
          form.submit();
        });
      }
    });
    // Geri tuşuyla dönüldüğünde buton kilitli kalmasın
    window.addEventListener('pageshow', () => {
      if (button) {
        button.disabled = false;
        button.innerHTML = label;
      }
    });
  }
})();
