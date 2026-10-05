<?php
$company = (string) config('site.company') ?: 'Öz Oto (ozoto.online)';
$address = (string) config('site.address');
$email = (string) config('site.email');
?>
<section class="sayfa-bas">
  <div class="kap dar">
    <nav class="yol" aria-label="Sayfa yolu"><a href="/">Ana sayfa</a><span aria-hidden="true">/</span><span>KVKK</span></nav>
    <h1>Kişisel verilerin korunması aydınlatma metni</h1>
  </div>
</section>

<section class="metin">
  <div class="kap dar">
    <p>6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca, veri sorumlusu sıfatıyla <strong><?= e($company) ?></strong> olarak, ozoto.online üzerinden paylaştığınız kişisel verileri aşağıda açıklanan kapsamda işliyoruz.</p>

    <h2>1. İşlenen kişisel veriler</h2>
    <ul>
      <li><strong>Kimlik ve iletişim:</strong> ad soyad, telefon numarası, il ve ilçe.</li>
      <li><strong>Araç bilgileri:</strong> marka, model, model yılı, kilometre, yakıt ve vites türü, hasar ve tramer bilgisi, beklenen fiyat, araç fotoğrafları ve açıklamalarınız.</li>
      <li><strong>İşlem güvenliği:</strong> geri döndürülemez şekilde özetlenmiş IP bilgisi, tarayıcı bilgisi ve başvuru zamanı.</li>
    </ul>

    <h2>2. İşleme amaçları</h2>
    <ul>
      <li>Aracınız için satın alma teklifi hazırlamak ve size iletmek,</li>
      <li>Başvurunuzla ilgili sizinle telefon, SMS veya WhatsApp üzerinden iletişime geçmek,</li>
      <li>Satış sürecini yürütmek ve yasal yükümlülükleri yerine getirmek,</li>
      <li>Kötüye kullanımı ve sahte başvuruları önlemek,</li>
      <li>Ayrıca onay vermeniz halinde kampanya ve fırsatlar hakkında ticari ileti göndermek.</li>
    </ul>

    <h2>3. Hukuki sebepler</h2>
    <p>Kişisel verileriniz KVKK'nın 5. maddesinde yer alan; bir sözleşmenin kurulması veya ifasıyla doğrudan ilgili olması, veri sorumlusunun hukuki yükümlülüğünü yerine getirmesi ve temel hak ve özgürlüklerinize zarar vermemek kaydıyla meşru menfaatimiz için zorunlu olması hukuki sebeplerine dayanılarak işlenir. Ticari elektronik iletiler yalnızca açık rızanız ile gönderilir.</p>

    <h2>4. Aktarım</h2>
    <p>Verileriniz satılmaz ve reklam amacıyla üçüncü kişilerle paylaşılmaz. Yalnızca hizmetin yürütülmesi için gerekli olduğu ölçüde barındırma ve iletişim hizmeti aldığımız tedarikçilerle ve kanunen yetkili kamu kurum ve kuruluşlarıyla paylaşılabilir.</p>

    <h2>5. Saklama süresi</h2>
    <p>Başvuru bilgileriniz, teklif süreci sonuçlandıktan sonra ilgili mevzuatta öngörülen süreler boyunca veya en fazla 2 yıl saklanır; süre sonunda silinir, yok edilir ya da anonim hale getirilir.</p>

    <h2>6. Çerezler</h2>
    <p>Sitemizde reklam veya takip çerezi kullanılmaz. Yalnızca formun güvenli çalışması için zorunlu oturum çerezi kullanılır.</p>

    <h2>7. Haklarınız</h2>
    <p>KVKK'nın 11. maddesi uyarınca; verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, işleme amacını öğrenme, aktarıldığı kişileri bilme, eksik veya yanlış işlenmişse düzeltilmesini, silinmesini veya yok edilmesini isteme, itiraz etme ve zarara uğramanız halinde zararın giderilmesini talep etme haklarına sahipsiniz.</p>
    <p>Taleplerinizi<?= $email !== '' ? ' <a href="mailto:' . e($email) . '">' . e($email) . '</a> adresine e-posta ile veya' : '' ?><?= $address !== '' ? ' ' . e($address) . ' adresine' : '' ?> yazılı olarak iletebilirsiniz. Talepleriniz en geç 30 gün içinde sonuçlandırılır.</p>
  </div>
</section>
