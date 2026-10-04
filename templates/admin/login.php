<?php
/**
 * @var string $email
 * @var string|null $error
 * @var string|null $notice
 */
?>
<div class="container auth-wrap">
  <form class="form-card auth-card" method="post" action="/yonetim/giris">
    <h1>Yönetim girişi</h1>
    <?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
    <?= csrf_field() ?>
    <label class="field">
      <span>E-posta</span>
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </label>
    <label class="field">
      <span>Şifre</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-block btn-lg" type="submit">Giriş yap</button>
  </form>
</div>
