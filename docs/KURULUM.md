# Canlıya alma rehberi (Windows Plesk)

Bu adımlar bir kez yapılır. Sonraki her güncelleme GitHub'da `main`'e girdiği anda otomatik yayına çıkar.

## 1. Kodu `main`'e al
GitHub'da açık PR'ı **Ready for review → Merge** ile birleştir. Plesk webhook sayesinde dosyalar birkaç saniye içinde
`C:\Inetpub\vhosts\ozoto.online\site` klasörüne gelir.

## 2. Sitenin kök klasörünü ayarla
Plesk → **Websites & Domains → ozoto.online → Hosting & DNS → Hosting Settings**:
- **Document root:** `site\public`
- **PHP support:** açık, sürüm **8.4**, *Run PHP as FastCGI application*
- Kaydet.

> Kök klasör `site` değil **`site\public`** olmalı. Böylece ayarlar, veritabanı ve yüklenen fotoğraflar dışarıdan görünmez.

## 3. SSL (https) — Google sıralaması için şart
Plesk → ozoto.online → **SSL/TLS Certificates → Let's Encrypt** ile ücretsiz sertifika al (`ozoto.online` ve `www.ozoto.online`).
Ardından Hosting Settings'te **"Permanent SEO-safe 301 redirect from HTTP to HTTPS"** seçeneğini aç
ve tercih edilen alan adı olarak **ozoto.online** (www'suz) seç.

## 4. Veritabanı oluştur (önerilen: MySQL)
Plesk → ozoto.online → **Databases → Add Database**:
- Tür: MySQL / MariaDB
- Veritabanı adı: ör. `ozoto_db`
- Kullanıcı adı ve güçlü bir şifre belirle, bir yere not et.

> MySQL yoksa veya hızlıca denemek istersen kurulumda **SQLite** seçebilirsin; ek ayar gerektirmez.

## 5. Kurulum sihirbazı
Tarayıcıda **https://ozoto.online/kurulum** adresini aç.
1. Sayfanın üstündeki **Sunucu kontrolü** listesinin tamamı yeşil olmalı.
   Kırmızı "yazılabilir" satırı varsa: Plesk **File Manager** → `site\config` ve `site\storage` klasörleri →
   **Change Permissions** → IIS uygulama havuzu kullanıcısına (Plesk'te genelde `Plesk IIS WP User` / `IWPD_...`) **Write** izni ver.
2. **Kurulum anahtarı:** Claude'un sohbette verdiği anahtar (bu dosyada yazmaz).
3. Veritabanı bilgilerini, yönetici e-posta/şifresini ve sitede görünecek telefon/WhatsApp numarasını gir.
4. **Kurulumu tamamla** → giriş sayfasına yönlendirilirsin. `/kurulum` sayfası bundan sonra kapanır.

## 6. Yönetim paneli
**https://ozoto.online/yonetim** — gelen başvurular, fotoğraflar, durum takibi (Yeni → Arandı → Teklif verildi → Satın alındı / Olumsuz),
iç notlar, tek tıkla arama ve WhatsApp.

## 7. Google
- [Google Search Console](https://search.google.com/search-console)'a `ozoto.online` alan adını ekle ve doğrula.
- **Sitemaps** bölümüne `https://ozoto.online/sitemap.xml` adresini gönder.
- Google İşletme Profili açılırsa yerel aramalarda da görünürüz.

## Sorun giderme
| Belirti | Sebep / çözüm |
|---|---|
| **HTTP 500.19** hatası | Sunucu `web.config` içindeki bir bölümü kilitlemiş. Hata ekranının görüntüsünü Claude'a gönder. |
| Ana sayfa açılıyor, diğer sayfalar **404** | IIS **URL Rewrite** modülü kurulu değil. Plesk → Tools & Settings → Updates → IIS URL Rewrite bileşenini kur. |
| Fotoğraflı başvuru gönderilemiyor | Plesk → PHP Settings: `upload_max_filesize` ≥ 12M, `post_max_size` ≥ 64M. |
| Başvuru e-postası gelmiyor | Plesk'te posta servisi kapalı olabilir; başvurular yine de panelde görünür. |
| Bir hata sayfası çıkıyor | `site\storage\logs\php-error.log` dosyasındaki son satırları Claude'a gönder. |
