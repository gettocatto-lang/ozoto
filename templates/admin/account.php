<?php
/**
 * @var array{id: int, email: string} $user
 * @var array<string, string> $site
 * @var array<string, string> $errors
 * @var string|null $notice
 */
$v = static fn (string $key): string => e($site[$key] ?? '');
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key]) . '</span>' : '';
?>
<div class="container narrow-admin">
  <h1>Hesap ve site</h1>
  <?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif ?>

  <form class="form-card" method="post" action="/yonetim/hesap/site" id="site">
    <?= csrf_field() ?>
    <h2 class="form-card-title">Sitede görünen bilgiler</h2>
    <p class="muted small">Telefon ve WhatsApp sitenin üst menüsünde ve başvuru sayfasında, e-posta ve adres alt bilgide,
      ticari unvan ve adres KVKK metninde görünür. Boş bırakılan bilgi sitede gösterilmez.</p>
    <div class="grid-2">
      <label class="field"><span>Telefon</span><input type="tel" name="site_phone" value="<?= $v('site.phone') ?>" placeholder="0532 123 45 67"><?= $err('site.phone') ?></label>
      <label class="field"><span>WhatsApp numarası</span><input type="tel" name="site_whatsapp" value="<?= $v('site.whatsapp') ?>" placeholder="0532 123 45 67"><?= $err('site.whatsapp') ?></label>
      <label class="field"><span>Sitede görünecek e-posta</span><input type="email" name="site_email" value="<?= $v('site.email') ?>"><?= $err('site.email') ?></label>
      <label class="field"><span>Yeni başvuru bildirimi gidecek e-posta</span><input type="email" name="notify_email" value="<?= $v('notify_email') ?>"><?= $err('notify_email') ?></label>
      <label class="field"><span>Firma ticari unvanı</span><input type="text" name="site_company" value="<?= $v('site.company') ?>" maxlength="150"><?= $err('site.company') ?></label>
      <label class="field"><span>Firma adresi</span><input type="text" name="site_address" value="<?= $v('site.address') ?>" maxlength="250"><?= $err('site.address') ?></label>
    </div>
    <button class="btn btn-primary" type="submit">Kaydet</button>
  </form>

  <form class="form-card" method="post" action="/yonetim/hesap/sifre" id="sifre">
    <?= csrf_field() ?>
    <h2 class="form-card-title">Şifre değiştir</h2>
    <p class="muted small">Giriş e-postası: <strong><?= e($user['email']) ?></strong></p>
    <?php if (isset($errors['password'])): ?><div class="alert alert-error" role="alert"><?= e($errors['password']) ?></div><?php endif ?>
    <div class="grid-2">
      <label class="field"><span>Mevcut şifre</span><input type="password" name="current_password" required autocomplete="current-password"></label>
      <span></span>
      <label class="field"><span>Yeni şifre (en az 10 karakter)</span><input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label>
      <label class="field"><span>Yeni şifre (tekrar)</span><input type="password" name="new_password2" minlength="10" required autocomplete="new-password"></label>
    </div>
    <button class="btn btn-primary" type="submit">Şifreyi değiştir</button>
  </form>
</div>
