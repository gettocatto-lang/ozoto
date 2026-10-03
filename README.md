# ozoto.online

Türkiye genelinde kelepir araç ilanlarını bulan ve "acil satılık aracınız anında nakite" başvurularını toplayan PHP 8.4 web projesi.

- Hedef ortam: Windows Plesk (IIS), PHP 8.4, FastCGI
- Canlıya alma: [docs/KURULUM.md](docs/KURULUM.md)
- Proje notları ve yol haritası: [docs/PROJE_NOTLARI.md](docs/PROJE_NOTLARI.md)

## Yapı

```
public/      Web kökü (Plesk Document root: site\public) — index.php, web.config, assets
src/         Uygulama kodu (Ozoto\ isim alanı, Composer gerektirmez)
templates/   Sayfa şablonları
config/      app.php (varsayılanlar) + local.php (kurulumda oluşur, Git'e girmez)
database/    MySQL ve SQLite şemaları
storage/     Yüklenen fotoğraflar, oturumlar, loglar (web'den erişilemez)
bin/         Komut satırı görevleri (radar.php: Radar toplayıcısı)
```

## Sayfalar

| Adres | İçerik |
|---|---|
| `/` | Ana sayfa (SEO, SSS, hızlı teklif kutusu) |
| `/aracimi-hemen-sat` | Nakit teklif başvuru formu (fotoğraflı) |
| `/kvkk` | KVKK aydınlatma metni |
| `/yonetim` | Başvuru yönetim paneli |
| `/yonetim/radar` | Kelepir Radar: değerlenmiş ilan listesi, filtre, sıralama, kayıtlı aramalar |
| `/yonetim/radar/ayarlar` | Arama motoru taraması ve zamanlanmış görev adresi |
| `/cron/radar?anahtar=…` | Zamanlanmış görev (Plesk "URL getir") |
| `/kurulum` | Tek seferlik kurulum sihirbazı |
| `/sitemap.xml` | Site haritası |

## Yerelde çalıştırma

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Ardından `http://127.0.0.1:8080/kurulum` adresinden SQLite ile kurulum yapılabilir.
