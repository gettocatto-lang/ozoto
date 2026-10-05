<?php
/**
 * @var string $content
 * @var string|null $title
 * @var array{id: int, email: string}|null $user
 */
$user ??= null;
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Ozoto Yönetim') ?></title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="admin">
<header class="admin-header">
  <div class="container header-inner">
    <a class="logo" href="/yonetim"><?= logo_mark() ?><span>ozoto <small>yönetim</small></span></a>
    <?php if ($user): ?>
      <?php $current = request_path(); ?>
      <nav class="admin-nav" aria-label="Yönetim menüsü">
        <a href="/yonetim"<?= $current === '/yonetim' || str_starts_with($current, '/yonetim/basvuru') ? ' aria-current="page"' : '' ?>>Başvurular</a>
        <a href="/yonetim/radar"<?= str_starts_with($current, '/yonetim/radar') && $current !== '/yonetim/radar/ayarlar' ? ' aria-current="page"' : '' ?>>Kelepir Radar</a>
        <a href="/yonetim/radar/ayarlar"<?= $current === '/yonetim/radar/ayarlar' ? ' aria-current="page"' : '' ?>>Radar ayarları</a>
        <a href="/yonetim/hesap"<?= $current === '/yonetim/hesap' ? ' aria-current="page"' : '' ?>>Hesap ve site</a>
      </nav>
      <div class="admin-user">
        <span class="admin-email"><?= e($user['email']) ?></span>
        <form method="post" action="/yonetim/cikis">
          <?= csrf_field() ?>
          <button class="btn btn-ghost-dark btn-sm" type="submit">Çıkış</button>
        </form>
      </div>
    <?php endif ?>
  </div>
</header>
<main class="admin-main">
<?= $content ?>
</main>
</body>
</html>
