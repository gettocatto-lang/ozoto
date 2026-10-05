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

$title ??= 'Öz Oto – Acil Satılık Aracınız Anında Nakite';
$description ??= 'Acil satılık aracınıza net teklif, noterde devir ve aynı gün ödeme. Türkiye\'nin 81 ilinden başvuru.';
$canonical ??= null;
$noindex ??= false;
$sitePhone = (string) config('site.phone');
$telHref = $sitePhone !== '' ? 'tel:' . (Phone::normalize($sitePhone) ?? $sitePhone) : '';
$whatsapp = (string) config('site.whatsapp');
$waLink = $whatsapp !== '' ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode('Merhaba, aracım için nakit teklif almak istiyorum.') : '';
$email = (string) config('site.email');
$address = (string) config('site.address');
$company = rtrim((string) config('site.company') ?: 'Öz Oto', '.');
$path = request_path();
$onForm = $path === '/aracimi-hemen-sat';
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
<meta property="og:site_name" content="Öz Oto">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="<?= e(site_url('/assets/img/og.png')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#07090b">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/archivo.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/instrument-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ($jsonLd ?? [] as $schema): ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach ?>
</head>
<body<?= $onForm ? '' : ' class="cubuklu"' ?>>
<a class="atla" href="#icerik">İçeriğe geç</a>

<header class="ust">
  <div class="kap ust-ic">
    <a class="marka" href="/" aria-label="Öz Oto ana sayfa">Öz Oto</a>
    <nav class="menu" id="main-nav" aria-label="Ana menü">
      <a href="/#degerleme">Değerleme</a>
      <a href="/#surec">Süreç</a>
      <a href="/#sss">Sorular</a>
      <?php if ($telHref !== ''): ?><a class="menu-ek" href="<?= e($telHref) ?>">Ara: <?= e($sitePhone) ?></a><?php endif ?>
      <?php if ($waLink !== ''): ?><a class="menu-ek" href="<?= e($waLink) ?>" rel="noopener" target="_blank">WhatsApp ile yazın</a><?php endif ?>
    </nav>
    <?php if ($telHref !== ''): ?><a class="ust-tel" href="<?= e($telHref) ?>"><?= e($sitePhone) ?></a><?php endif ?>
    <?php if (!$onForm): ?><a class="btn btn-nakit btn-kucuk ust-cta" href="/aracimi-hemen-sat">Teklif al</a><?php endif ?>
    <button class="nav-toggle" type="button" aria-controls="main-nav" aria-expanded="false" aria-label="Menü"><?= icon('menu', 'icon i-ac') ?><?= icon('x', 'icon i-kapa') ?></button>
  </div>
</header>

<main id="icerik">
<?= $content ?>
</main>

<footer class="alt">
  <div class="kap alt-ust">
    <div class="alt-marka">
      <a class="marka" href="/">Öz Oto</a>
      <p>Acil satılık araç alımı. Net teklif, noterde devir, aynı gün ödeme. Türkiye'nin 81 ilinden başvuru.</p>
    </div>
    <nav aria-label="Sayfalar">
      <h2 class="etiket">Sayfalar</h2>
      <ul>
        <li><a href="/aracimi-hemen-sat">Teklif formu</a></li>
        <li><a href="/#degerleme">Değerleme</a></li>
        <li><a href="/#surec">Süreç</a></li>
        <li><a href="/#sss">Sık sorulanlar</a></li>
        <li><a href="/kvkk">KVKK aydınlatma metni</a></li>
      </ul>
    </nav>
    <div>
      <h2 class="etiket">İletişim</h2>
      <ul>
        <?php if ($telHref !== ''): ?><li><a href="<?= e($telHref) ?>"><?= e($sitePhone) ?></a></li><?php endif ?>
        <?php if ($waLink !== ''): ?><li><a href="<?= e($waLink) ?>" rel="noopener" target="_blank">WhatsApp</a></li><?php endif ?>
        <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif ?>
        <?php if ($address !== ''): ?><li><?= e($address) ?></li><?php endif ?>
      </ul>
    </div>
  </div>
  <div class="kap alt-alt">
    <span>© <?= date('Y') ?> <?= e($company) ?></span>
    <span>ozoto.online</span>
  </div>
</footer>

<?php if (!$onForm): ?>
<div class="mobil-cubuk">
  <a class="btn btn-nakit" href="/aracimi-hemen-sat">Teklif al<?= icon('ok') ?></a>
  <?php if ($waLink !== ''): ?>
    <a class="btn btn-cizgi" href="<?= e($waLink) ?>" rel="noopener" target="_blank" aria-label="WhatsApp ile yazın"><?= icon('whatsapp') ?></a>
  <?php endif ?>
  <?php if ($telHref !== ''): ?>
    <a class="btn btn-cizgi" href="<?= e($telHref) ?>" aria-label="Arayın"><?= icon('phone') ?></a>
  <?php endif ?>
</div>
<?php endif ?>
</body>
</html>
