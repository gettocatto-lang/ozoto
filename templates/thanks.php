<?php
/** @var string|null $ref */
$whatsapp = (string) config('site.whatsapp');
$sitePhone = (string) config('site.phone');
?>
<section class="section">
  <div class="container narrow center">
    <div class="success-mark"><?= icon('check', 'icon icon-xl') ?></div>
    <h1>Başvurunuz alındı</h1>
    <p class="lead">Teşekkür ederiz. Uzmanımız bilgilerinizi inceleyip en kısa sürede sizi arayacak.</p>
    <?php if ($ref): ?>
      <p class="ref-box">Başvuru numaranız <strong><?= e($ref) ?></strong></p>
    <?php endif ?>
    <div class="btn-row">
      <?php if ($whatsapp !== ''): ?>
        <a class="btn btn-whatsapp btn-lg" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('Merhaba, ' . ($ref ? $ref . ' numaralı ' : '') . 'başvurum hakkında yazıyorum.') ?>" rel="noopener" target="_blank"><?= icon('whatsapp') ?>WhatsApp'tan yazın</a>
      <?php endif ?>
      <?php if ($sitePhone !== ''): ?>
        <a class="btn btn-primary btn-lg" href="tel:<?= e(\Ozoto\Support\Phone::normalize($sitePhone) ?? $sitePhone) ?>"><?= icon('phone') ?>Hemen arayın</a>
      <?php endif ?>
      <a class="btn btn-ghost btn-lg" href="/">Ana sayfaya dön</a>
    </div>
  </div>
</section>
