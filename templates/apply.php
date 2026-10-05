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
use Ozoto\Support\Phone;

$v = static fn (string $k): string => e($old[$k] ?? '');
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="alan-hata" id="err-' . $k . '">' . e($errors[$k]) . '</p>' : '';
$aria = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
$checked = static fn (string $k, string $value): string => ($old[$k] ?? '') === $value ? ' checked' : '';
$fieldErrors = array_diff_key($errors, ['_form' => true]);
$sitePhone = (string) config('site.phone');
$req = '<em aria-hidden="true">*</em>';
?>
<section class="sayfa-bas">
  <div class="kap">
    <nav class="yol" aria-label="Sayfa yolu"><a href="/">Ana sayfa</a><span aria-hidden="true">/</span><span>Teklif formu</span></nav>
    <h1>Aracınıza nakit teklif alın.</h1>
    <p class="giris">Dört bölüm, yaklaşık iki dakika. Uzmanımız bilgilerinizi inceleyip aynı gün sizi arar. <span class="zorunlu">* zorunlu alan</span></p>
  </div>
</section>

<section class="form-bolum">
  <div class="kap form-duzen">
    <form class="basvuru" method="post" action="/aracimi-hemen-sat" enctype="multipart/form-data" data-apply-form>
      <?= csrf_field() ?>

      <?php if (isset($errors['_form'])): ?>
        <div class="uyari" role="alert"><?= e($errors['_form']) ?></div>
      <?php elseif ($fieldErrors !== []): ?>
        <div class="uyari" role="alert">Lütfen işaretli alanları kontrol edin.<?= isset($errors['photos']) ? '' : ' Fotoğraf seçtiyseniz tekrar eklemeniz gerekir.' ?></div>
      <?php endif ?>

      <fieldset class="parca">
        <legend><span class="no">01</span>Araç bilgileri</legend>
        <div class="izgara-2">
          <label class="alan">
            <span class="alan-ad">Marka <?= $req ?></span>
            <select name="brand" required<?= $aria('brand') ?>>
              <option value="">Seçin</option>
              <?php foreach ($brands as $brand): ?>
                <option value="<?= e($brand) ?>"<?= selected($old['brand'] ?? '', $brand) ?>><?= e($brand) ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('brand') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Model ve paket <?= $req ?></span>
            <input type="text" name="model" value="<?= $v('model') ?>" maxlength="80" required placeholder="ör. Corolla 1.6 Vision"<?= $aria('model') ?>>
            <?= $err('model') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Model yılı <?= $req ?></span>
            <select name="model_year" required<?= $aria('model_year') ?>>
              <option value="">Seçin</option>
              <?php foreach ($years as $year): ?>
                <option value="<?= $year ?>"<?= selected($old['model_year'] ?? '', $year) ?>><?= $year ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('model_year') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Kilometre <?= $req ?></span>
            <input type="text" name="km" value="<?= $v('km') ?>" inputmode="numeric" maxlength="9" required placeholder="ör. 125.000" data-thousands<?= $aria('km') ?>>
            <?= $err('km') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Yakıt <?= $req ?></span>
            <select name="fuel" required<?= $aria('fuel') ?>>
              <option value="">Seçin</option>
              <?php foreach ($fuels as $fuel): ?>
                <option value="<?= e($fuel) ?>"<?= selected($old['fuel'] ?? '', $fuel) ?>><?= e($fuel) ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('fuel') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Vites <?= $req ?></span>
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

      <fieldset class="parca">
        <legend><span class="no">02</span>Aracın durumu</legend>
        <fieldset class="alan">
          <legend>Hasar durumu <?= $req ?></legend>
          <div class="secimler">
            <?php foreach ($damages as $damage): ?>
              <label class="secim"><input type="radio" name="damage" value="<?= e($damage) ?>" required<?= $checked('damage', $damage) ?>><span><?= e($damage) ?></span></label>
            <?php endforeach ?>
          </div>
          <?= $err('damage') ?>
        </fieldset>
        <div class="izgara-2">
          <label class="alan">
            <span class="alan-ad">Tramer tutarı (varsa, TL)</span>
            <input type="text" name="tramer_amount" value="<?= $v('tramer_amount') ?>" inputmode="numeric" maxlength="12" placeholder="ör. 15.000" data-thousands<?= $aria('tramer_amount') ?>>
            <?= $err('tramer_amount') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Beklediğiniz fiyat (TL)</span>
            <input type="text" name="expected_price" value="<?= $v('expected_price') ?>" inputmode="numeric" maxlength="13" placeholder="ör. 850.000" data-thousands<?= $aria('expected_price') ?>>
            <?= $err('expected_price') ?>
          </label>
        </div>
        <fieldset class="alan alan-son">
          <legend>Ne kadar acil? <?= $req ?></legend>
          <div class="secimler">
            <?php foreach ($urgencies as $urgency): ?>
              <label class="secim"><input type="radio" name="urgency" value="<?= e($urgency) ?>" required<?= $checked('urgency', $urgency) ?>><span><?= e($urgency) ?></span></label>
            <?php endforeach ?>
          </div>
          <?= $err('urgency') ?>
        </fieldset>
      </fieldset>

      <fieldset class="parca">
        <legend><span class="no">03</span>Fotoğraflar<small>İsteğe bağlı</small></legend>
        <label class="dropzone">
          <?= icon('camera') ?>
          <strong>Fotoğraf ekleyin</strong>
          <span>Önden, arkadan, iki yandan ve iç mekândan en fazla <?= $maxPhotos ?> fotoğraf. Göndermeden önce otomatik küçültülür.</span>
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/*" multiple data-photo-input data-max="<?= $maxPhotos ?>"<?= $aria('photos') ?>>
        </label>
        <div class="foto-onizleme" data-photo-previews></div>
        <?= $err('photos') ?>
      </fieldset>

      <fieldset class="parca">
        <legend><span class="no">04</span>İletişim bilgileri</legend>
        <div class="izgara-2">
          <label class="alan">
            <span class="alan-ad">Ad soyad <?= $req ?></span>
            <input type="text" name="full_name" value="<?= $v('full_name') ?>" maxlength="100" autocomplete="name" required<?= $aria('full_name') ?>>
            <?= $err('full_name') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Telefon <?= $req ?></span>
            <input type="tel" name="phone" value="<?= $v('phone') ?>" maxlength="20" autocomplete="tel" inputmode="tel" required placeholder="05xx xxx xx xx"<?= $aria('phone') ?>>
            <?= $err('phone') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">Aracın bulunduğu il <?= $req ?></span>
            <select name="city" required<?= $aria('city') ?>>
              <option value="">Seçin</option>
              <?php foreach ($cities as $city): ?>
                <option value="<?= e($city) ?>"<?= selected($old['city'] ?? '', $city) ?>><?= e($city) ?></option>
              <?php endforeach ?>
            </select>
            <?= $err('city') ?>
          </label>
          <label class="alan">
            <span class="alan-ad">İlçe</span>
            <input type="text" name="district" value="<?= $v('district') ?>" maxlength="60">
          </label>
        </div>
        <label class="alan alan-not">
          <span class="alan-ad">Eklemek istedikleriniz</span>
          <textarea name="notes" rows="4" maxlength="2000" placeholder="ör. Bakımları yetkili serviste yapıldı, iki anahtar var, lastikler yeni…"<?= $aria('notes') ?>><?= $v('notes') ?></textarea>
          <?= $err('notes') ?>
        </label>
      </fieldset>

      <div class="hp" aria-hidden="true">
        <label>Web siteniz <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="onaylar">
        <label class="onay">
          <input type="checkbox" name="kvkk" value="1" required<?= $checked('kvkk', '1') ?><?= $aria('kvkk') ?>>
          <span><a href="/kvkk" target="_blank" rel="noopener">KVKK aydınlatma metnini</a> okudum; bilgilerimin teklif verilmesi ve benimle iletişime geçilmesi amacıyla işlenmesini kabul ediyorum. <?= $req ?></span>
        </label>
        <?= $err('kvkk') ?>
        <label class="onay">
          <input type="checkbox" name="marketing" value="1"<?= $checked('marketing', '1') ?>>
          <span>Kampanya ve fırsatlardan SMS / e-posta ile haberdar olmak istiyorum.</span>
        </label>
      </div>

      <div class="gonder">
        <button class="btn btn-nakit btn-buyuk btn-tam" type="submit" data-submit>Teklif iste<?= icon('ok') ?></button>
        <p class="gonder-not">Ücretsiz · Sizi bağlamaz</p>
      </div>
    </form>

    <aside class="form-yan">
      <div>
        <h2 class="etiket">Sonra ne olacak?</h2>
        <ol class="yan-adim">
          <li><b>Sizi arıyoruz.</b>Başvurunuz bize anında ulaşır; uzmanımız aynı gün arar.</li>
          <li><b>Teklif veriyoruz.</b>Kilometre, boya-değişen ve tramer kaydına göre net fiyat.</li>
          <li><b>Noterde devir.</b>Kabul ederseniz ödemeniz devir anında hesabınızda.</li>
        </ol>
      </div>
      <?php if ($sitePhone !== ''): ?>
        <div class="yan-tel">
          <h2 class="etiket">Acele mi ediyorsunuz?</h2>
          <p>Formla uğraşmadan hemen arayın.</p>
          <a class="tel" href="tel:<?= e(Phone::normalize($sitePhone) ?? $sitePhone) ?>"><?= e($sitePhone) ?></a>
        </div>
      <?php endif ?>
    </aside>
  </div>
</section>
