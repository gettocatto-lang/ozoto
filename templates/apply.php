<?php
/**
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var list<string> $cities
 * @var list<string> $brands
 * @var list<int> $years
 * @var list<string> $fuels
 * @var list<string> $gearboxes
 * @var list<string> $damages
 * @var list<string> $urgencies
 * @var int $maxPhotos
 */
$v = static fn (string $k): string => e($old[$k] ?? '');
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error" id="err-' . $k . '">' . e($errors[$k]) . '</p>' : '';
$aria = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
$checked = static fn (string $k, string $value): string => ($old[$k] ?? '') === $value ? ' checked' : '';
$fieldErrors = array_diff_key($errors, ['_form' => true]);
$sitePhone = (string) config('site.phone');
?>
<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Sayfa yolu"><a href="/">Ana sayfa</a><span aria-hidden="true">/</span><span>Aracımı hemen sat</span></nav>
    <h1>Aracınıza ücretsiz nakit teklif alın</h1>
    <p class="lead">Formu doldurun, uzmanımız en kısa sürede sizi arasın. <span class="req-note">* zorunlu alanlar</span></p>
  </div>
</section>

<section class="section section-tight">
  <div class="container form-layout">
    <form class="apply-form" method="post" action="/aracimi-hemen-sat" enctype="multipart/form-data" data-apply-form>
      <?= csrf_field() ?>

      <?php if (isset($errors['_form'])): ?>
        <div class="alert alert-error" role="alert"><?= e($errors['_form']) ?></div>
      <?php elseif ($fieldErrors !== []): ?>
        <div class="alert alert-error" role="alert">Lütfen işaretli alanları kontrol edin.<?= isset($errors['photos']) ? '' : ' Fotoğraf seçtiyseniz tekrar eklemeniz gerekir.' ?></div>
      <?php endif ?>

      <fieldset class="form-card">
        <legend class="form-card-title"><span class="badge-no">1</span>Araç bilgileri</legend>
        <div class="grid-2">
          <label class="field">
            <span>Marka *</span>
            <select name="brand" required<?= $aria('brand') ?>>
              <option value="">Seçin</option>
              <?php foreach ($brands as $brand): ?>
                <option value="<?= e($brand) ?>"<?= selected($old['brand'] ?? '', $brand) ?>><?= e($brand) ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('brand') ?>
          </label>
          <label class="field">
            <span>Model ve paket *</span>
            <input type="text" name="model" value="<?= $v('model') ?>" maxlength="80" required placeholder="ör. Corolla 1.6 Vision"<?= $aria('model') ?>>
            <?= $err('model') ?>
          </label>
          <label class="field">
            <span>Model yılı *</span>
            <select name="model_year" required<?= $aria('model_year') ?>>
              <option value="">Seçin</option>
              <?php foreach ($years as $year): ?>
                <option value="<?= $year ?>"<?= selected($old['model_year'] ?? '', $year) ?>><?= $year ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('model_year') ?>
          </label>
          <label class="field">
            <span>Kilometre *</span>
            <input type="text" name="km" value="<?= $v('km') ?>" inputmode="numeric" maxlength="9" required placeholder="ör. 125.000" data-thousands<?= $aria('km') ?>>
            <?= $err('km') ?>
          </label>
          <label class="field">
            <span>Yakıt *</span>
            <select name="fuel" required<?= $aria('fuel') ?>>
              <option value="">Seçin</option>
              <?php foreach ($fuels as $fuel): ?>
                <option value="<?= e($fuel) ?>"<?= selected($old['fuel'] ?? '', $fuel) ?>><?= e($fuel) ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('fuel') ?>
          </label>
          <label class="field">
            <span>Vites *</span>
            <select name="gearbox" required<?= $aria('gearbox') ?>>
              <option value="">Seçin</option>
              <?php foreach ($gearboxes as $gearbox): ?>
                <option value="<?= e($gearbox) ?>"<?= selected($old['gearbox'] ?? '', $gearbox) ?>><?= e($gearbox) ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('gearbox') ?>
          </label>
        </div>
      </fieldset>

      <fieldset class="form-card">
        <legend class="form-card-title"><span class="badge-no">2</span>Aracın durumu</legend>
        <fieldset class="field">
          <legend>Hasar durumu *</legend>
          <div class="chips">
            <?php foreach ($damages as $damage): ?>
              <label class="chip"><input type="radio" name="damage" value="<?= e($damage) ?>" required<?= $checked('damage', $damage) ?>><span><?= e($damage) ?></span></label>
            <?php endforeach ?>
          </div>
          <?= $err('damage') ?>
        </fieldset>
        <div class="grid-2">
          <label class="field">
            <span>Tramer tutarı (varsa, TL)</span>
            <input type="text" name="tramer_amount" value="<?= $v('tramer_amount') ?>" inputmode="numeric" maxlength="12" placeholder="ör. 15.000" data-thousands<?= $aria('tramer_amount') ?>>
            <?= $err('tramer_amount') ?>
          </label>
          <label class="field">
            <span>Beklediğiniz fiyat (TL)</span>
            <input type="text" name="expected_price" value="<?= $v('expected_price') ?>" inputmode="numeric" maxlength="13" placeholder="ör. 850.000" data-thousands<?= $aria('expected_price') ?>>
            <?= $err('expected_price') ?>
          </label>
        </div>
        <fieldset class="field">
          <legend>Ne kadar acil? *</legend>
          <div class="chips">
            <?php foreach ($urgencies as $urgency): ?>
              <label class="chip"><input type="radio" name="urgency" value="<?= e($urgency) ?>" required<?= $checked('urgency', $urgency) ?>><span><?= e($urgency) ?></span></label>
            <?php endforeach ?>
          </div>
          <?= $err('urgency') ?>
        </fieldset>
      </fieldset>

      <fieldset class="form-card">
        <legend class="form-card-title"><span class="badge-no">3</span>Fotoğraflar <small>(isteğe bağlı)</small></legend>
        <label class="dropzone">
          <?= icon('camera', 'icon icon-lg') ?>
          <strong>Fotoğraf ekleyin</strong>
          <span>Önden, arkadan, yanlardan ve iç mekândan en fazla <?= $maxPhotos ?> fotoğraf. Fotoğraflar gönderilmeden önce otomatik küçültülür.</span>
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/*" multiple data-photo-input data-max="<?= $maxPhotos ?>"<?= $aria('photos') ?>>
        </label>
        <div class="photo-previews" data-photo-previews></div>
        <?= $err('photos') ?>
      </fieldset>

      <fieldset class="form-card">
        <legend class="form-card-title"><span class="badge-no">4</span>İletişim bilgileri</legend>
        <div class="grid-2">
          <label class="field">
            <span>Ad soyad *</span>
            <input type="text" name="full_name" value="<?= $v('full_name') ?>" maxlength="100" autocomplete="name" required<?= $aria('full_name') ?>>
            <?= $err('full_name') ?>
          </label>
          <label class="field">
            <span>Telefon *</span>
            <input type="tel" name="phone" value="<?= $v('phone') ?>" maxlength="20" autocomplete="tel" inputmode="tel" required placeholder="05xx xxx xx xx"<?= $aria('phone') ?>>
            <?= $err('phone') ?>
          </label>
          <label class="field">
            <span>Aracın bulunduğu il *</span>
            <select name="city" required<?= $aria('city') ?>>
              <option value="">Seçin</option>
              <?php foreach ($cities as $city): ?>
                <option value="<?= e($city) ?>"<?= selected($old['city'] ?? '', $city) ?>><?= e($city) ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('city') ?>
          </label>
          <label class="field">
            <span>İlçe</span>
            <input type="text" name="district" value="<?= $v('district') ?>" maxlength="60">
          </label>
        </div>
        <label class="field">
          <span>Eklemek istedikleriniz</span>
          <textarea name="notes" rows="4" maxlength="2000" placeholder="ör. Bakımları yetkili serviste yapıldı, 2 anahtar var, lastikler yeni…"<?= $aria('notes') ?>><?= $v('notes') ?></textarea>
          <?= $err('notes') ?>
        </label>
      </fieldset>

      <div class="hp" aria-hidden="true">
        <label>Web siteniz <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="consents">
        <label class="check">
          <input type="checkbox" name="kvkk" value="1" required<?= $checked('kvkk', '1') ?><?= $aria('kvkk') ?>>
          <span><a href="/kvkk" target="_blank" rel="noopener">KVKK aydınlatma metnini</a> okudum; bilgilerimin teklif verilmesi ve benimle iletişime geçilmesi amacıyla işlenmesini kabul ediyorum. *</span>
        </label>
        <?= $err('kvkk') ?>
        <label class="check">
          <input type="checkbox" name="marketing" value="1"<?= $checked('marketing', '1') ?>>
          <span>Kampanya ve fırsatlardan SMS / e-posta ile haberdar olmak istiyorum.</span>
        </label>
      </div>

      <button class="btn btn-accent btn-lg btn-block" type="submit" data-submit>Teklif İste<?= icon('arrow') ?></button>
      <p class="form-foot"><?= icon('shield') ?>Teklif almak ücretsizdir ve sizi bağlamaz.</p>
    </form>

    <aside class="form-aside">
      <div class="aside-card">
        <h2>Sonra ne olacak?</h2>
        <ol class="mini-steps">
          <li><strong>Sizi arıyoruz.</strong> Başvurunuz bize anında ulaşır.</li>
          <li><strong>Teklif veriyoruz.</strong> Aracınızı piyasa verileriyle değerlendiririz.</li>
          <li><strong>Noterde satış.</strong> Kabul ederseniz ödemeniz satış anında yapılır.</li>
        </ol>
      </div>
      <?php if ($sitePhone !== ''): ?>
        <div class="aside-card aside-dark">
          <h2>Acele mi ediyorsunuz?</h2>
          <p>Formla uğraşmadan hemen arayın.</p>
          <a class="btn btn-accent btn-block" href="tel:<?= e(\Ozoto\Support\Phone::normalize($sitePhone) ?? $sitePhone) ?>"><?= icon('phone') ?><?= e($sitePhone) ?></a>
        </div>
      <?php endif ?>
    </aside>
  </div>
</section>
