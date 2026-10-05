<?php
/** @var string|null $ref */
use Ozoto\Support\Phone;

$whatsapp = (string) config('site.whatsapp');
$sitePhone = (string) config('site.phone');
?>
<section class="bolum tesekkur">
  <div class="kap tesekkur-ic">
    <div>
      <p class="etiket">Başvuru alındı</p>
      <h1>Teşekkürler.<br>Sizi arayacağız.</h1>
      <p class="giris">Uzmanımız bilgilerinizi inceleyip aynı gün sizi arayacak. Telefonunuz açık olsun; bilinmeyen bir numaradan arayabiliriz.</p>
      <?php if ($ref): ?>
        <dl class="ref"><dt>Başvuru numarası</dt><dd><?= e($ref) ?></dd></dl>
      <?php endif ?>
      <div class="eylem">
        <?php if ($whatsapp !== ''): ?>
          <a class="btn btn-nakit" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('Merhaba, ' . ($ref ? $ref . ' numaralı ' : '') . 'başvurum hakkında yazıyorum.') ?>" rel="noopener" target="_blank">WhatsApp'tan yazın<?= icon('ok') ?></a>
        <?php endif ?>
        <?php if ($sitePhone !== ''): ?>
          <a class="btn btn-cizgi" href="tel:<?= e(Phone::normalize($sitePhone) ?? $sitePhone) ?>">Hemen arayın</a>
        <?php endif ?>
        <a class="btn-metin" href="/">Ana sayfaya dön</a>
      </div>
    </div>
    <div class="tesekkur-muhur"><?= partial('muhur', ['id' => 'muhur-tesekkur', 'word' => 'ALINDI']) ?></div>
  </div>
</section>
