<?php
/**
 * @var string $content
 * @var string|null $title
 * @var string|null $description
 * @var string|null $canonical
 * @var bool|null $noindex
 * @var list<array<string, mixed>>|null $jsonLd
 */
use Ozoto\Support\Phone;

$title ??= 'Ozoto – Acil Satılık Aracınız Anında Nakite';
$description ??= 'Acil satılık aracınıza hızlı nakit teklif. Türkiye\'nin 81 ilinden başvuru.';
$canonical ??= null;
$noindex ??= false;
$sitePhone = (string) config('site.phone');
$whatsapp = (string) config('site.whatsapp');
$waLink = $whatsapp !== '' ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode('Merhaba, aracım için nakit teklif almak istiyorum.') : '';
$path = request_path();
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif ?>
<?php if ($canonical): ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php endif ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="tr_TR">
<meta property="og:site_name" content="Ozoto">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="<?= e(site_url('/assets/img/og.png')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0b1220">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ($jsonLd ?? [] as $schema): ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach ?>
</head>
<body>
<a class="skip-link" href="#icerik">İçeriğe geç</a>

<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="/" aria-label="Ozoto ana sayfa"><?= logo_mark() ?><span>ozoto</span></a>
    <nav class="main-nav" id="main-nav" aria-label="Ana menü">
      <a href="/#nasil-calisir">Nasıl çalışır?</a>
      <a href="/#neden-ozoto">Neden Ozoto?</a>
      <a href="/#sss">Sık sorulanlar</a>
      <?php if ($sitePhone !== ''): ?>
        <a class="nav-phone" href="tel:<?= e(Phone::normalize($sitePhone) ?? $sitePhone) ?>"><?= icon('phone') ?><?= e($sitePhone) ?></a>
      <?php endif ?>
    </nav>
    <a class="btn btn-accent btn-sm header-cta" href="/aracimi-hemen-sat">Teklif Al</a>
    <button class="nav-toggle" type="button" aria-controls="main-nav" aria-expanded="false" aria-label="Menüyü aç"><?= icon('menu') ?></button>
  </div>
</header>

<main id="icerik">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a class="logo" href="/"><?= logo_mark() ?><span>ozoto</span></a>
      <p class="footer-text">Acil satılık aracınızı hızlı ve güvenli şekilde nakite çeviriyoruz. Türkiye'nin 81 ilinden başvuru kabul ediyoruz.</p>
    </div>
    <div>
      <h2 class="footer-title">Hızlı erişim</h2>
      <ul class="footer-links">
        <li><a href="/aracimi-hemen-sat">Aracımı hemen sat</a></li>
        <li><a href="/#nasil-calisir">Nasıl çalışır?</a></li>
        <li><a href="/#sss">Sık sorulan sorular</a></li>
        <li><a href="/kvkk">KVKK aydınlatma metni</a></li>
      </ul>
    </div>
    <div>
      <h2 class="footer-title">İletişim</h2>
      <ul class="footer-links">
        <?php if ($sitePhone !== ''): ?><li><a href="tel:<?= e(Phone::normalize($sitePhone) ?? $sitePhone) ?>"><?= e($sitePhone) ?></a></li><?php endif ?>
        <?php if ($waLink !== ''): ?><li><a href="<?= e($waLink) ?>" rel="noopener" target="_blank">WhatsApp ile yazın</a></li><?php endif ?>
        <?php if ((string) config('site.email') !== ''): ?><li><a href="mailto:<?= e(config('site.email')) ?>"><?= e(config('site.email')) ?></a></li><?php endif ?>
        <?php if ((string) config('site.address') !== ''): ?><li><?= e(config('site.address')) ?></li><?php endif ?>
        <li>ozoto.online</li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e(rtrim((string) config('site.company') ?: 'Ozoto', '.')) ?>. Tüm hakları saklıdır.</span>
  </div>
</footer>

<?php if ($path !== '/aracimi-hemen-sat'): ?>
<div class="mobile-bar">
  <a class="btn btn-accent" href="/aracimi-hemen-sat"><?= icon('bolt') ?>Hemen Teklif Al</a>
  <?php if ($waLink !== ''): ?>
    <a class="btn btn-whatsapp" href="<?= e($waLink) ?>" rel="noopener" target="_blank" aria-label="WhatsApp"><?= icon('whatsapp') ?></a>
  <?php elseif ($sitePhone !== ''): ?>
    <a class="btn btn-ghost-dark" href="tel:<?= e(Phone::normalize($sitePhone) ?? $sitePhone) ?>" aria-label="Ara"><?= icon('phone') ?></a>
  <?php endif ?>
</div>
<?php endif ?>
</body>
</html>
