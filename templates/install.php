<?php
/**
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var array<string, bool> $requirements
 * @var list<string> $drivers
 */
$v = static fn (string $k): string => e($old[$k] ?? '');
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$driver = $old['driver'] ?? 'mysql';
?>
<div class="container install-wrap">
  <h1>Ozoto kurulumu</h1>
  <p class="muted">Bu sayfa yalnızca bir kez kullanılır. Kurulum bitince kapanır.</p>

  <section class="form-card">
    <h2 class="form-card-title">Sunucu kontrolü</h2>
    <ul class="checklist">
      <?php foreach ($requirements as $label => $ok): ?>
        <li class="<?= $ok ? 'ok' : 'fail' ?>"><?= icon($ok ? 'check' : 'x') ?><?= e($label) ?></li>
      <?php endforeach ?>
    </ul>
  </section>

  <form class="install-form" method="post" action="/kurulum" autocomplete="off">
    <?= csrf_field() ?>
    <?php if (isset($errors['_form'])): ?><div class="alert alert-error" role="alert"><?= e($errors['_form']) ?></div><?php endif ?>

    <section class="form-card">
      <h2 class="form-card-title">1. Kurulum anahtarı</h2>
      <label class="field">
        <span>Size iletilen kurulum anahtarı</span>
        <input type="text" name="token" required placeholder="XXXXX-XXXXX-XXXXX-XXXXX" autocapitalize="characters" spellcheck="false">
        <?= $err('token') ?>
      </label>
    </section>

    <section class="form-card">
      <h2 class="form-card-title">2. Veritabanı</h2>
      <?php if (isset($errors['db'])): ?><div class="alert alert-error" role="alert"><?= e($errors['db']) ?></div><?php endif ?>
      <fieldset class="field">
        <legend>Veritabanı türü</legend>
        <div class="chips">
          <label class="chip"><input type="radio" name="driver" value="mysql"<?= $driver === 'mysql' ? ' checked' : '' ?><?= in_array('mysql', $drivers, true) ? '' : ' disabled' ?>><span>MySQL / MariaDB (önerilen)</span></label>
          <label class="chip"><input type="radio" name="driver" value="sqlite"<?= $driver === 'sqlite' ? ' checked' : '' ?><?= in_array('sqlite', $drivers, true) ? '' : ' disabled' ?>><span>SQLite (ayar gerektirmez)</span></label>
        </div>
        <?= $err('driver') ?>
      </fieldset>
      <p class="muted small">MySQL için Plesk → Veritabanları → "Veritabanı Ekle" ile bir veritabanı ve kullanıcı oluşturup bilgileri aşağıya yazın. SQLite seçerseniz bu alanları boş bırakabilirsiniz.</p>
      <div class="grid-2">
        <label class="field"><span>Sunucu</span><input type="text" name="db_host" value="<?= $v('db_host') ?>"></label>
        <label class="field"><span>Port</span><input type="text" name="db_port" value="<?= $v('db_port') ?>" inputmode="numeric"></label>
        <label class="field"><span>Veritabanı adı</span><input type="text" name="db_name" value="<?= $v('db_name') ?>"></label>
        <label class="field"><span>Kullanıcı adı</span><input type="text" name="db_user" value="<?= $v('db_user') ?>"></label>
        <label class="field"><span>Şifre</span><input type="password" name="db_pass" autocomplete="new-password"></label>
      </div>
    </section>

    <section class="form-card">
      <h2 class="form-card-title">3. Yönetici hesabı</h2>
      <div class="grid-2">
        <label class="field"><span>E-posta</span><input type="email" name="admin_email" value="<?= $v('admin_email') ?>" required><?= $err('admin_email') ?></label>
        <span></span>
        <label class="field"><span>Şifre (en az 10 karakter)</span><input type="password" name="admin_password" minlength="10" required autocomplete="new-password"><?= $err('admin_password') ?></label>
        <label class="field"><span>Şifre (tekrar)</span><input type="password" name="admin_password2" minlength="10" required autocomplete="new-password"></label>
      </div>
    </section>

    <section class="form-card">
      <h2 class="form-card-title">4. Site bilgileri <small>(sonradan da eklenebilir)</small></h2>
      <div class="grid-2">
        <label class="field"><span>Sitede görünecek telefon</span><input type="tel" name="site_phone" value="<?= $v('site_phone') ?>" placeholder="0532 123 45 67"><?= $err('site_phone') ?></label>
        <label class="field"><span>WhatsApp numarası</span><input type="tel" name="site_whatsapp" value="<?= $v('site_whatsapp') ?>" placeholder="0532 123 45 67"><?= $err('site_whatsapp') ?></label>
        <label class="field"><span>Yeni başvuru bildirimi e-postası</span><input type="email" name="notify_email" value="<?= $v('notify_email') ?>"><?= $err('notify_email') ?></label>
        <label class="field"><span>Sitede görünecek e-posta</span><input type="email" name="site_email" value="<?= $v('site_email') ?>"></label>
        <label class="field"><span>Firma ticari unvanı (KVKK metni için)</span><input type="text" name="site_company" value="<?= $v('site_company') ?>"></label>
        <label class="field"><span>Firma adresi</span><input type="text" name="site_address" value="<?= $v('site_address') ?>"></label>
      </div>
    </section>

    <button class="btn btn-accent btn-lg btn-block" type="submit">Kurulumu tamamla</button>
  </form>
</div>
