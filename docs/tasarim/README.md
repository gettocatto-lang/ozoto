# Tasarım: Mühürlü Çizgi

Sitenin görsel dili. Felsefe metni: [felsefe.md](felsefe.md).

- **Zemin ve renk:** gece `#07090b`, şerit `#1c2329`, sis `#8b949b`, far `#ecefee`; tek vurgu rengi nakit yeşili `#7fd1a9`.
- **Yazı:** başlıklar ve rakamlar Archivo (değişken genişlik), metin Instrument Sans. Dosyalar `public/assets/fonts` altında, harici CDN yok.
- **Çizgi:** 1 px kılcal çizgiler, 2 px köşe yarıçapı, gölge ve degrade yok.
- **Etiket:** küçük, büyük harf, geniş aralıklı (paftanın köşe bilgisi gibi).

## Dosyalar

| Dosya | Ne |
| --- | --- |
| `ozoto-degerleme-cizimi.png` / `.pdf` | Marka afişi (2400×1500). Sosyal medya görseli `public/assets/img/og.png` bundan üretildi. |
| `kaynak/arac.py` | Sedan yan profilinin geometrisi (mm). |
| `kaynak/afis.py` | Afişi üretir (`poster.svg` / `poster.html`). |
| `kaynak/hero.py` | Ana sayfadaki teknik çizimi üretir: `templates/partials/hero-cizim.php` ve `site.css` içindeki `cizim:başla … cizim:bitir` bölgesi. Çizimi değiştirmek için bu betiği düzenleyip `python3 hero.py` çalıştırın. |

Ziyaretçi sayfalarının stili `public/assets/css/site.css`, yönetim panelininki `public/assets/css/app.css`.
