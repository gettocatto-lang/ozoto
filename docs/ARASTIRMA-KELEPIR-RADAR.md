# Kelepir Radar — veri kaynakları araştırması

> 04.10.2026 · Kapsam: Türkiye'de kelepir araç bulunabilecek tüm kanallar, erişim yolu, hukuki durum ve öncelik.
> Karar: Radar **sadece ekip içi** (alım için) kullanılacak; sonuçlar **yönetim panelinde** listelenecek.

---

## 1. En önemli bulgular

1. **Gerçek kelepirlerin çoğu ilan sitelerinde değil, ihalelerde.** İcra satışları (UYAP e-Satış), gümrük tasfiye
   ve kamu araçları (e-ihale.gov.tr), sigorta pert ihaleleri ve filo/leasing ihaleleri piyasa değerinin altında
   başlar. İlan toplayan rakipler bu kanallara bakmıyor; **"kimsenin yapmadığı" burası.**
   - Ticaret Bakanlığı e-ihale: 2026'nın ilk 6 ayında **1.477 araç** satıldı. Her araç için resmî ekspertiz raporu
     ve fotoğraf yayımlanıyor.
2. **Piyasa değeri için resmî taban var: TSB kasko değer listesi.**
   - Her marka, model ve yıl için tek değer veriyor; 2012–2026 model yıllarını kapsıyor, 15 yaşa kadar araçlar.
   - En az ayda bir güncelleniyor (TSB bir dönem ayda 3 kez yayımlama kararı aldı).
   - TSB'nin veri uç noktası kimlik doğrulaması olmadan yanıt veriyor (04.10.2026'da denendi).
   - Açık kaynak (MIT) bir istemci de var (`hmtkyn/tsb-kasko-mcp`); kendi entegrasyonumuz aynı uç noktaları kullanır.
3. **Büyük ilan sitelerini sunucudan taramak hem teknik hem hukuki olarak çıkmaz:**
   - **sahibinden.com** sunucu isteklerini tamamen engelliyor (`robots.txt` bile 403 dönüyor).
   - **arabam.com** üyelik sözleşmesi madde 6.2: ilanlara toplu ulaşmak, kopyalamak, derlemek, işlemek ve
     başka veri tabanlarına aktarmak açıkça yasak.
   - **letgo** site haritasına bile bot doğrulaması koymuş.
4. **Facebook Marketplace'in genel API'si yok.** Meta Content Library'deki Marketplace araması yalnızca akademik
   ve STK araştırmacılarına açık.
5. **Instagram resmî API'si sınırlı ama kullanılabilir:**
   - İşletme hesabıyla 7 günde 30 farklı hashtag aranabiliyor.
   - Son 24 saatin paylaşımları dönüyor, sayfa başına 50 sonuç.
6. **X (Twitter) API'si** Şubat 2026'dan beri kullandıkça ödemeli:
   - Okuma başına 0,005 $, ayda en fazla 2 milyon okuma.
   - Sadece son 7 günün araması.
   - Türkiye'de araç satışında hacmi düşük.
7. **Hukuk:** Türkiye'de web kazımaya özel bir yasa yok. Ancak:
   - **FSEK ek madde 8**, emek harcanmış veri tabanlarını koruyor; mahkemeler ilan sitelerini veri tabanı sayıyor.
   - Haksız rekabet hükümleri de devreye giriyor.
   - Rekabet Kurulu sahibinden'e **40,1 milyon TL** ceza verdi ve kurumsal üyelerin ilanlarını rakip platformlara
     ücretsiz taşıyabileceği bir API zorunlu kıldı (uyum kararı 26.06.2025).

---

## 2. Kaynak haritası

| Kaynak | Ne var | Erişim | Hukuki durum | Öncelik |
|---|---|---|---|---|
| **UYAP e-Satış** (icra) | Haczedilmiş araçlar, muhammen bedel, ihale tarihi | İlanlar herkese açık, teklif için e-Devlet | Kamu ilanı, yayımlanması zaten amaç | **1** |
| **e-ihale.gov.tr** (gümrük tasfiye + kamu araçları) | Ekspertiz PDF'i, fotoğraf, başlangıç bedeli | e-Devlet ile ücretsiz üyelik | Kamu ilanı | **1** |
| **Milli Emlak** taşıt satış ilanları | Kamu kurum araçları | İl müdürlüğü ilan PDF'leri | Kamu ilanı | 2 |
| **Filo/leasing ihaleleri**: Borusan Next İhale ("her hafta binlerce araç"), Ayvens Carmarket, Garanti BBVA Kirala İkinci El Garaj, Otokoç Filo Satış, Filomotive | Filo çıkışı, bakımlı, ekspertizli araçlar | Galerici üyeliği | Üye sözleşmesine tabi; kendi alımımız için kullanım | 2 |
| **Sigorta pert ihaleleri**: Autogong, ENKA, Pert Dünyası, Aksamoto, Otopert, Arena Pert | Hasarlı / pert araçlar | Üyelik + cayma bedeli | Üye sözleşmesine tabi | 2 |
| **Galeri/bayi ortaklıkları** | XML/CSV ilan beslemesi | Anlaşma | Temiz | 2 |
| **Otobid** (sahibinden) | Bireysel satıcıların araçları, galericilere açık artırma | Galerici üyeliği | Üye sözleşmesine tabi | 3 |
| **Telegram açık kanallar** | Satılık araç kanalları | Telegram API, kendi hesabınla | Telegram API şartlarına uygun, açık kanallar | 3 |
| **Instagram Graph API** | `#satilikaraba`, `#acilsatilik` gibi hashtag paylaşımları | İşletme hesabı + Meta uygulaması | Resmî | 3 |
| **X API** | Satılık araç paylaşımları | Ücretli | Resmî | 4 |
| **Kendi başvuru formumuz** | Doğrudan satıcı, KVKK onaylı | Hazır (Faz 1) | Temiz | ✅ |
| **sahibinden / arabam / letgo / Facebook** | En büyük hacim | Sunucudan: engelli veya sözleşmeyle yasak | FSEK ek m.8 + sözleşme riski | Yalnızca §3'teki eklentiyle |

**Altyapı notu:** e-ihale.gov.tr yurt dışındaki sunucumuzdan gelen bağlantıyı kesti. Kamu siteleri yurt dışı ve bot
trafiğini kısıtlayabiliyor. Bazı siteler de (UYAP gibi) JavaScript ile çalışıyor ve gerçek bir tarayıcı gerektiriyor.
Windows Plesk'te gerçek tarayıcı çalıştırılamaz. Bu yüzden:

- **Toplayıcılar ayrı, küçük bir Linux sunucuda (Türkiye lokasyonlu VPS)** çalışır.
- Topladığı veriyi ozoto.online'a güvenli bir API ile gönderir.
- Plesk'teki site yalnızca veriyi saklar, puanlar ve gösterir.

---

## 3. Büyük ilan siteleri ve Facebook için tek seçenek: tarayıcı eklentisi

**Nasıl çalışır:**
- Ekip ilanları her zamanki gibi gezer.
- Eklenti, ekrandaki ilanın fiyat, km, yıl, şehir ve link bilgisini Radar'a gönderir.
- Sayfada anında "piyasanın %18 altında" rozeti gösterir.

**Sınırları:**
- Otomatik sayfa gezmez.
- Giriş duvarını aşmaz.
- Satıcı adı ve telefonunu almaz.

**Risk:** Teknik engellenme riski düşük. Ama arabam'ın 6.2 maddesi ve benzerleri **bunu da kapsıyor** (derleme,
başka veri tabanına aktarma). İç kullanım ve yeniden yayın olmaması riski düşürür ama sıfırlamaz.
**Karar senin; başlamadan bir avukata sor.**

**Yapmayacaklarımız:**
- Sahte hesaplarla Facebook/Instagram gruplarına veya Marketplace'e girmek
- Captcha ve bot korumalarını aşmak
- Proxy değiştirerek engellerden kaçmak
- Satıcı telefonlarını toplamak

Sebebi: hesaplar ve IP'ler kısa sürede kapanır, hukuki risk ve KVKK cezası işi batırır.

---

## 4. Beyin: piyasa değeri ve kelepir puanı

1. **Taban değer:** TSB kasko değeri (marka + model + yıl).
2. **Düzeltmeler:**
   - Kilometre (yaşa göre beklenen km'den sapma)
   - Hasar durumu (boyalı, değişenli, ağır hasar kayıtlı) ve tramer tutarı
   - Vites ve yakıt
   - Şehir
3. **Öğrenen katman:** Toplanan ilan ve ihale sonuçlarından benzer araçların medyan fiyatı çıkarılır. Her model için
   "piyasa / kasko" oranı zamanla kendini kalibre eder. Veri arttıkça tahmin keskinleşir.
4. **Kelepir puanı (0–100):**
   - Beklenen değerden ne kadar aşağıda olduğu
   - \+ acil satış sinyali ("acil, borçtan, nakit lazım")
   - \+ tahminin güvenilirliği (kaç benzer araç var)
   - − şüphe (aşırı düşük fiyat → "sahte ilan olabilir" uyarısı)
5. **İhaleler için:** Başlangıç bedeli + tahmini masraflar ile piyasa değeri karşılaştırılır →
   **"tahmini kâr potansiyeli"**.
6. **Mükerrer birleştirme:** Aynı araç birden fazla kanalda görünürse tek kayıt olur, fiyat geçmişi tutulur.

---

## 5. Yönetim paneli: Kelepir listesi

- **Sıralama:**
  - Kelepir puanı
  - Fiyat (artan / azalan)
  - Piyasa farkı %
  - Km, model yılı
  - Eklenme tarihi
  - İhale bitiş tarihi
- **Filtreler:**
  - Marka, model, şehir, kaynak
  - Yıl aralığı, km aralığı, fiyat aralığı
  - Hasar durumu, vites, yakıt
  - "Sadece ihaleler"
  - Minimum kelepir puanı
- **Arama:** Marka, model ve serbest metin.
- **Her satırda:**
  - Fotoğraf, başlık, fiyat
  - Tahmini piyasa değeri, fark % ve puan rozeti
  - Kaynak, şehir, ihale geri sayımı
  - Orijinal link
- **İşaretleme:** Favori, takipte, teklif verildi, alındı; ayrıca iç notlar.
- **Kayıtlı aramalar:** Ör. "İzmir, dizel, 2018+, 900 bin altı".

---

## 6. Yol haritası

| Adım | İçerik |
|---|---|
| **R1** | Radar çekirdeği: veritabanı, TSB entegrasyonu, puanlama motoru, paneldeki kelepir listesi (sıralama, filtre, arama), elle ekleme (link + form) |
| **R2** | Kamu ihaleleri: e-ihale (gümrük + kamu araçları), UYAP e-Satış, Milli Emlak toplayıcıları + Türkiye'de toplayıcı VPS |
| **R3** | Telegram açık kanalları + Instagram hashtag (resmî API) |
| **R4** | Üyelik gerektiren B2B ihaleler (Borusan Next, Ayvens, pert platformları) — üyelikler açıldıkça |
| **R5** | (Kararına bağlı) tarayıcı eklentisi |

## 7. Senden gerekenler

1. **Öncelikler:** Öncelikli şehirler, bütçe aralığı ve araç tipleri (binek, hafif ticari, hasarlı dahil mi?).
2. **Galerici üyelikleri:** B2B ihaleler için vergi levhası ve yetki belgesiyle Borusan Next, Ayvens ve pert
   platformlarına üyelik.
3. **Hesaplar:** Instagram işletme hesabı (Meta uygulaması için) ve Telegram hesabı (API kimliği için).
4. **Sunucu:** Türkiye lokasyonlu küçük bir Linux VPS (aylık birkaç yüz TL düzeyinde; R2'de gerekli).
5. **Hukuk:** Eklenti (R5) için bir avukat görüşü.

---

## Kaynaklar

- UYAP e-Satış: https://www.esatis.uyap.gov.tr/main/esatis/index.jsp · https://analizhukukburosu.org/rehber/uyap-e-satis-icra-ihalesi
- e-ihale (Ticaret Bakanlığı): https://ticaret.gov.tr/haberler/ticaret-bakanligi-tasfiyelik-hale-gelen-esya-ve-araclari-e-ihale-sistemiyle-ekonomiye-kazandirmaya-devam-ediyor · https://www.habermeydan.com/ekonomi/ticaret-bakanligi-e-ihale-rakamlarini-acikladi-6-bin-826-ihale-tamamlandi-hazineye-27-milyar-tl-gelir-h215502/ · https://www.turkgun.com/ekonomi/gumruk-arac-satis-listesi-e-ihale-basvuru-sartlari/385375
- Kamu araçları: https://www.dunya.com/gundem/ihtiyac-fazlasi-kamu-araclarinda-tasarruf-satisi-basladi-1200-tasitin-ihalesi-elektronik-ortamda-haberi-761031
- TSB kasko değer listesi: https://www.tsb.org.tr/ · https://www.bloomberght.com/tsb-arac-kasko-deger-listesi-ayda-uc-kez-yayimlanacak-2309212 · https://github.com/hmtkyn/tsb-kasko-mcp
- Pert ihaleleri: https://autogong.com/ · https://en-ka.com.tr/en/ · https://www.pertdunyasi.com/ · https://aksamoto.com.tr/ · https://www.otopert.com.tr/
- Filo ihaleleri: https://borusannextihale.com/ · https://carmarket.ayvens.com/tr-tr/sales/44246 · https://www.garantibbvafilo.com.tr/tr/urun-ve-hizmetlerimiz/urunlerimiz/ikinci-el-garaj · https://www.otokoc.com.tr/filo-satis-otokoc
- Otobid: https://webrazzi.com/2024/02/28/sahibinden-den-ikinci-el-arac-alim-satim-platformu-otobid/
- Rekabet Kurulu / sahibinden: https://www.aa.com.tr/tr/ekonomi/rekabet-kurulu-sahibindencoma-40-1-milyon-lira-ceza-verdi/2974144 · https://www.mfylegal.av.tr/haberler/sahibinden-api-veri-tasima-karari-26-06-2025/
- arabam üyelik sözleşmesi (madde 6.2): https://www.arabam.com/bireysel-uyelik-sozlesmesi
- Web kazıma ve FSEK: https://www.mondaq.com/turkey/copyright/1304748/web-kaz%C4%B1ma-web-scraping-ve-internet-sitelerine-y%C3%B6nelik-koruma · https://www.goksusafiisik.av.tr/tr/publications/2025-summer-issue/web-scraping-eyleminin-haksiz-rekabet-acisindan-degerlendirilmesi?id=510
- Facebook Marketplace API: https://scrapecreators.com/blog/facebook-marketplace-api-tools · https://developers.secure.facebook.com/docs/marketplace/partnerships/
- Instagram hashtag API: https://developers.facebook.com/docs/instagram-platform/instagram-api-with-facebook-login/hashtag-search
- X API fiyatları: https://www.postproxy.dev/blog/x-api-pricing-2026/ · https://zernio.com/blog/twitter-api-pricing
- İkinci el pazar verisi (Indicata): https://www.capital.com.tr/haberler/tum-haberler/ikinci-el-cevrim-ici-oto-pazarinda-fiyatlar-ne-kadar-geriledi
