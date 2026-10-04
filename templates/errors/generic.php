<?php
/**
 * @var int $status
 * @var string $title
 */
?>
<section class="section">
  <div class="container narrow center">
    <p class="error-code"><?= (int) $status ?></p>
    <h1><?= e($title) ?></h1>
    <p class="lead">Lütfen biraz sonra tekrar deneyin.</p>
    <div class="btn-row">
      <a class="btn btn-ghost btn-lg" href="/">Ana sayfa</a>
    </div>
  </div>
</section>
