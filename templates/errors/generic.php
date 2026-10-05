<?php
/**
 * @var int $status
 * @var string $title
 */
?>
<section class="bolum hata-sayfa">
  <div class="kap dar">
    <span class="hata-kod" aria-hidden="true"><?= (int) $status ?></span>
    <h1><?= e($title) ?></h1>
    <p class="giris">Lütfen biraz sonra tekrar deneyin.</p>
    <div class="eylem">
      <a class="btn btn-cizgi" href="/">Ana sayfaya dön</a>
    </div>
  </div>
</section>
