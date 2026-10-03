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

## 8. Kelepir Radar
Panelde **Kelepir Radar** sekmesi: ilanlar TSB kasko değeri + km/hasar düzeltmesi + benzer ilanlarla değerlenir, 0–100 kelepir puanı alır.
Liste puana, fiyata, piyasa farkına, km'ye, yıla, tarihe göre sıralanır; marka, model, şehir, kaynak, yıl/fiyat/km aralığı,
hasar, vites, yakıt, "acil", "ihale" filtreleri ve kayıtlı aramalar vardır.

- **E-posta bildirimleri (ana kaynak):**
  1. Plesk → **Mail → Create Email Address**: `radar@ozoto.online`.
  2. sahibinden / arabam / letgo hesaplarında bildirim adresi olarak bunu kullan (veya bildirimleri bu adrese otomatik yönlendir).
  3. Sitelerde aramaları kaydet ve **"Bildirim almak istiyorum"**u anında/sık aç. Ne kadar çok kayıtlı arama, o kadar çok ilan.
  4. Panel → **Ayarlar → 1. E-posta bildirimleri**: sunucu `localhost`, port `143`, güvenlik "Yok", kullanıcı ve şifre →
     **Bağlantıyı test et** → "Otomatik okumayı aç" → Kaydet.
  5. Bir bildirim e-postasını `.eml` olarak indirip "Bir bildirim e-postasını dene" bölümünden yükleyerek hangi ilanların
     çıkarıldığını görebilirsin. Çıkmayan biçim olursa dosyayı Claude'a gönder.
- **Tarayıcı eklentisi (Chrome):** ekip ilanları gezerken sayfadaki ilanları Radar'a gönderir, her ilanın yanında puan rozeti gösterir.
  1. Panel → **Ayarlar → 2. Tarayıcı eklentisi** → **Anahtar oluştur** → anahtarı kopyala (bir kez gösterilir).
  2. **Eklentiyi indir (zip)** → bir klasöre çıkar.
  3. Chrome → `chrome://extensions` → **Geliştirici modu** → **Paketlenmemiş öğe yükle** → klasörü seç.
  4. Eklenti **Seçenekler**: sunucu `https://ozoto.online`, anahtar → **Bağlantıyı test et**.
  5. sahibinden/arabam/letgo/Facebook Marketplace'te "en yeni" sıralı liste sayfalarını gez; rozetler çıkar, ilanlar Radar'a düşer.
  Eklenti kendi kendine sayfa gezmez; satıcı adı ve telefonu gönderilmez.
- **Elle ekleme:** Radar → **+ İlan ekle** → ilan linkini yapıştır, fiyat ve km'yi gir. Marka/model/yıl linkten bulunur.
- **Otomatik tarama (arama motoru):**
  1. [api-dashboard.search.brave.com](https://api-dashboard.search.brave.com/) adresinde hesap aç, **Search** planını seç
     (ayda yaklaşık 1.000 sorgu ücretsiz kredi, sonrası 1.000 sorgu başına 5 $), API anahtarını kopyala.
  2. Panel → **Ayarlar** → anahtarı yapıştır, "Otomatik taramayı aç"ı işaretle, kaydet.
  3. Ayarlar sayfasındaki **zamanlanmış görev adresini** kopyala. Plesk → ozoto.online → **Scheduled Tasks → Add Task →
     Fetch a URL** → adresi yapıştır → sıklık **her 5 dakika** (`*/5 * * * *`) → kaydet. E-postalar her çalışmada,
     arama motoru ayarlardaki aralıkta (varsayılan 180 dk) çalışır.
  4. (Alternatif) "Run a PHP script" seçip `site\bin\radar.php` yolunu da verebilirsin.
- Tarama ilan sitelerine istek atmaz; arama motorunun dizinindeki ilan linklerini, başlıklarını ve özetlerini alır.
  Satıcı adı/telefonu alınmaz. Fiyatı özette olmayan kayıtlar "fiyat bilinmiyor" olarak gelir; ilana bakıp detay sayfasından tamamla.

## Sorun giderme
| Belirti | Sebep / çözüm |
|---|---|
| **HTTP 500.19** hatası | Sunucu `web.config` içindeki bir bölümü kilitlemiş. Hata ekranının görüntüsünü Claude'a gönder. |
| Ana sayfa açılıyor, diğer sayfalar **404** | IIS **URL Rewrite** modülü kurulu değil. Plesk → Tools & Settings → Updates → IIS URL Rewrite bileşenini kur. |
| Fotoğraflı başvuru gönderilemiyor | Plesk → PHP Settings: `upload_max_filesize` ≥ 12M, `post_max_size` ≥ 64M. |
| Başvuru e-postası gelmiyor | Plesk'te posta servisi kapalı olabilir; başvurular yine de panelde görünür. |
| Radar'da TSB değeri gelmiyor | TSB 15 yaşa kadar araçları kapsar; model adı eşleşmezse detay sayfasından model/paket bilgisini düzelt ve "Yeniden değerle"ye bas. |
| Bir hata sayfası çıkıyor | `site\storage\logs\php-error.log` dosyasındaki son satırları Claude'a gönder. |
