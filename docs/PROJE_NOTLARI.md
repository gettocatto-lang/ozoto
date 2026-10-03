# ozoto.online — Proje Notları

> Toplantı 1 · 03.10.2026 · Başlangıç / ihtiyaç toplama

---

## 1. Müşterinin istekleri (söylendiği gibi)

| # | İstek | Not |
|---|-------|-----|
| 1 | Domain: **ozoto.online** | Plesk üzerinde barınıyor |
| 2 | **PHP 8.4.26**, Plesk'te **"Run PHP as FastCGI application"** ile uyumlu web projesi | Ek PHP eklentisi / Node gerektirmemeli |
| 3 | Türkiye genelinde **Facebook, Instagram, Twitter (X)** ve **birçok ilan sitesinden** **kelepir araç** bilgisi toplayan sistem | Projenin çekirdeği |
| 4 | "Aşırı mükemmel", rakiplerin yapamadığı seviyede olmalı | Fark yaratan özellikler: bkz. §4 |
| 5 | Google'da **en üstte görünmeli** (SEO) | bkz. §5 |
| 6 | **Başvuru formu**: *"Acil satılık araçlarınız anında nakite çevrilir"* | Araç sahiplerinden alım talebi toplanacak, bkz. §6 |

---

## 2. Sunucu / deploy durumu

- GitHub repo: `git@github.com:gettocatto-lang/ozoto.git`
- GitHub'a deploy key eklendi (adı: `ozoto`, **Read/write**). Plesk sadece çekme (pull) yapacağı için **read-only yeterli** — güvenlik için write yetkisi kaldırılabilir.
- **Plesk hatası:** `gitmng failed: error: pathspec 'main' did not match any file(s) known to git`
  - **Sebep:** Repo tamamen boştu, `main` dalı (branch) yoktu. Plesk bulunmayan bir dalı çekmeye çalıştı.
  - **Çözüm:** `main` dalı oluşturuldu (03.10.2026).
- **Plesk hatası 2:** `fatal: destination path 'C:\Inetpub\vhosts\ozoto.online\git\ozoto.git' already exists and is not an empty directory`
  - **Sebep:** İlk başarısız deneme sunucuda yarım bir `ozoto.git` klasörü bıraktı; aynı isimle tekrar klonlanamıyor.
  - **Çözüm:** Ya o klasör silinip tekrar denenecek, ya da formdaki *Repository name* farklı bir isim yapılacak (ör. `ozoto-web.git`).
- **Plesk Git bağlantısı kuruldu (04.10.2026):** repo `ozoto.git`, dal `main`, otomatik deploy yolu `\site`
  (`C:\Inetpub\vhosts\ozoto.online\site`).
- **Document root** kod yazılınca `site\public` yapılacak (Plesk → Hosting & DNS → Hosting Settings → Document root).
  Böylece kaynak kod, ayarlar, `.env`, bu notlar gibi dosyalar web'den erişilemez.
  `public/` klasörü repoya girmeden değiştirilmemeli, yoksa site hata verir.
- **Otomatik deploy için GitHub webhook:** Plesk'te repo ayarlarındaki *Webhook URL* kopyalanıp
  GitHub → Settings → Webhooks'a eklenecek (olay: *push*). Plesk'in SSL sertifikası geçerli değilse
  GitHub'da *SSL verification* kapatılması gerekebilir. Webhook yoksa güncellemeler Plesk'te **Pull now** ile çekilir.
- **Plesk deneme lisansı 3 gün sonra bitiyor** (04.10.2026 itibarıyla). Lisans alınmazsa panel kilitlenir;
  Git deploy ve ayarlar kullanılamaz.

### Sunucu Windows + IIS (önemli tespit)
Hata mesajındaki `C:\Inetpub\vhosts\...` yolu sunucunun **Windows Plesk / IIS** olduğunu gösteriyor. Bunun projeye etkileri:
- `.htaccess` **çalışmaz** → URL yönlendirme ve erişim engelleri **`web.config`** (IIS URL Rewrite) ile yapılacak.
- Dosya yolları her yerde `DIRECTORY_SEPARATOR` / `__DIR__` ile kurulacak; Linux'a özel komut (`exec`, `chmod` vb.) kullanılmayacak.
- Zamanlanmış görevler Plesk'te Windows görev zamanlayıcısı üzerinden `php.exe` ile çalışacak.
- Yükleme klasörü (`storage/`) için IIS uygulama havuzu kullanıcısına yazma izni gerekecek.

### FastCGI'nin projeye etkisi
- Web istekleri kısa sürmeli (FastCGI zaman aşımı). **Veri toplama (tarama) işleri web isteğinde çalışmayacak**;
  Plesk **Zamanlanmış Görevler** ile PHP CLI üzerinden arka planda çalışacak.
- Kalıcı süreç (daemon / websocket) yok; her şey zamanlanmış görev + veritabanı kuyruğu ile çözülecek.

---

## 3. Veri kaynakları — gerçekçi değerlendirme (ÖNEMLİ)

Bu konu projenin en kritik riski, toplantıda açıkça konuşuldu:

| Kaynak | Durum | Risk |
|--------|-------|------|
| **Facebook** (Marketplace, gruplar) | Resmî API yok. Otomatik veri çekme Meta kullanım şartlarına aykırı; hesap/IP engeli ve dava riski var. | Yüksek |
| **Instagram** | Resmî Graph API ile sınırlı **hashtag araması** mümkün (işletme hesabı gerekir, kotalı). Bunun dışı kullanım şartlarına aykırı. | Orta (API ile düşük) |
| **X / Twitter** | Resmî API **ücretli**; arama ücretli paketlerle mümkün. | Düşük (API ile), maliyetli |
| **İlan siteleri** (sahibinden, arabam.com vb.) | Kullanım şartları otomatik veri toplamayı yasaklıyor; Türkiye'de bu konuda açılmış davalar var. | Yüksek |

**Yasal çerçeve:** İlanlardaki ad-soyad / telefon **kişisel veri** (KVKK). İlan sitelerinin veri tabanları FSEK kapsamında korunabiliyor.
Kesin karar öncesi bir avukat görüşü alınması önerildi.

**Önerilen güvenli yaklaşım:**
1. Resmî API'si olan kaynaklar API ile (Instagram hashtag, X).
2. Diğerlerinde **kopyalama değil, yönlendirme**: başlık, fiyat, şehir, analiz + **orijinal ilana link**. Telefon ve fotoğraf kopyalanmaz.
3. **Galeri / bayi ortaklıkları** ile XML/CSV veri akışı (yasal ve sürdürülebilir).
4. **Kendi envanterimiz**: başvuru formundan gelen araçlar (§6) + kullanıcıların ekleyeceği ilanlar.
5. Her kaynak ayrı bir **"adaptör"** olacak; bir kaynak kapanırsa/değişirse sistem çökmez, sadece o adaptör kapatılır.

---

## 4. Fark yaratacak özellikler ("kimsenin yapamadığı")

- **Kelepir puanı (0–100):** Her ilan; marka, model, yıl, km, yakıt, vites, şehir benzerleriyle karşılaştırılıp
  piyasa ortalamasının ne kadar altında olduğu hesaplanır. "Piyasanın %18 altında" gibi net gösterim.
- **"Acil" sinyali:** İlan metninde *acil, ihtiyaçtan, borçtan, nakit lazım, takas olmaz, bugün satılık* gibi ifadeler puanı artırır.
- **Dolandırıcılık uyarısı:** Fiyat *aşırı* düşükse (ör. piyasanın %40+ altı) "şüpheli ilan" etiketi — güven kazandırır.
- **Mükerrer tespiti:** Aynı araç birden fazla sitede/sosyal medyada ise tek kayıtta birleştirilir.
- **Fiyat geçmişi:** İlanın fiyatı düştüyse grafik + "fiyatı düştü" etiketi.
- **Anlık bildirim:** Kullanıcı kriter kaydeder (ör. "İzmir, Corolla, 2018+, 900 bin altı") → yeni kelepir düşünce e-posta / Telegram / WhatsApp.
- **Hızlı, mobil öncelikli arayüz.**

---

## 5. SEO — "en üstte görünme" planı

Google'da 1. sıra **garanti edilemez**, ama bunu en güçlü şekilde hedefleyen altyapı kurulacak:

- PHP ile sunucu tarafında üretilen hızlı HTML (Core Web Vitals hedefi: yeşil).
- **schema.org** yapılandırılmış veri: `Car` / `Vehicle`, `Offer`, `Organization`, `BreadcrumbList`, `FAQPage`.
- Otomatik **şehir / marka / model sayfaları**: "İstanbul kelepir araba", "Ankara acil satılık Clio" gibi binlerce uzun kuyruklu sayfa.
- Otomatik `sitemap.xml`, `robots.txt`, temiz URL'ler (`/kelepir/istanbul/renault/clio`).
- Kopya içerik riskine karşı: her sayfada **kendi analizimiz** (kelepir puanı, piyasa ortalaması) olacak → özgün içerik.
- Google Search Console + Google İşletme Profili kurulumu, blog/rehber içerikleri ("Aracım nasıl hızlı nakite çevrilir?").
- Başvuru (nakit alım) sayfası ayrıca "aracımı hemen sat", "acil araç alan" gibi aramalara optimize edilecek.

---

## 6. Başvuru formu — "Acil satılık araçlarınız anında nakite çevrilir"

**Alanlar:** ad soyad, telefon, şehir/ilçe, marka, model, yıl, km, yakıt, vites, hasar/tramer durumu,
beklenen fiyat, fotoğraflar (birden fazla), açıklama.

**Zorunlu yasal kutucuklar:** KVKK aydınlatma metni onayı; isteğe bağlı ticari ileti izni (İYS).

**Güvenlik:** CSRF koruması, honeypot + hız sınırı (spam), fotoğraf tip/boyut kontrolü, isteğe bağlı Cloudflare Turnstile.

**Yönetim paneli:** başvuru listesi, durum takibi (yeni → arandı → teklif verildi → alındı / reddedildi),
yeni başvuruda e-posta / WhatsApp bildirimi, sistemin o araç için **otomatik piyasa değeri tahmini**.

---

## 7. Teknik mimari (taslak)

- **PHP 8.4**, framework'süz ve Composer'sız hafif yapı (kendi PSR-4 autoloader'ı) → Plesk'te ek adım gerektirmeden deploy.
- **MySQL / MariaDB** (önerilen) veya **SQLite**, PDO + hazırlanmış sorgular. Seçim kurulum sihirbazında yapılır.
- Klasörler: `public/` (web kökü), `src/` (kod), `templates/`, `bin/` (cron komutları), `storage/` (log, yüklemeler), `config/`.
- Cron işleri: kaynak tarama → normalize → mükerrer birleştirme → kelepir puanı → bildirim.
- Faz 1 güvenlik önlemleri: CSRF, gizli bot alanı + süre tuzağı, IP başına başvuru/giriş sınırı, fotoğraf içerik doğrulaması,
  fotoğraflar web kökü dışında, CSP ve güvenlik başlıkları, ham IP yerine özet (KVKK).
- Ana sayfa ve SSS metinleri pazarlama vaatleri içerir ("çoğu zaman aynı gün", "sürpriz kesinti yok" vb.);
  firmanın gerçek işleyişine göre gözden geçirilmeli. KVKK metni taslaktır, hukukçu kontrolünden geçmeli.

---

## 8. Yol haritası (öneri)

| Faz | İçerik |
|-----|--------|
| 0 | Repo + `main` dalı, Plesk Git deploy ✅ · `public/` document root, SSL → [KURULUM.md](KURULUM.md) |
| 1 | Ana sayfa + **başvuru formu** + yönetim paneli + kurulum sihirbazı ✅ kodlandı (04.10.2026) |
| 2 | Veri toplama altyapısı (adaptörler, cron, kuyruk) + ilk yasal kaynaklar |
| 3 | Kelepir puanı motoru + fiyat geçmişi + dolandırıcılık uyarısı |
| 4 | SEO sayfaları (şehir/marka/model), sitemap, yapılandırılmış veri |
| 5 | Kullanıcı kayıtları + kişisel alarm/bildirim |

---

## 9. Açık sorular (sonraki toplantıda netleşecek)

1. **Site herkese açık mı, yoksa kelepirleri sadece siz/ekibiniz mi göreceksiniz?** (Mimarinin ve yasal riskin en büyük belirleyicisi.)
2. Kelepir tanımı: piyasanın yüzde kaç altı? (öneri: %10+ "iyi fiyat", %20+ "kelepir")
3. Öncelikli ilan siteleri ve sosyal medya kaynakları hangileri?
4. Araçları satın alacak firma kim? (Ticari unvan, adres — KVKK metni ve iletişim için gerekli)
5. Bildirimler hangi kanaldan: e-posta, WhatsApp, Telegram?
6. Ücretli servis bütçesi var mı? (X API, SMS, WhatsApp Business API)
7. Plesk'te MySQL, Zamanlanmış Görevler (cron) ve SSH erişimi açık mı?
8. Logo, renkler, tasarım örneği beğendiğiniz siteler?
