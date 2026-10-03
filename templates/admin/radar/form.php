<?php
/**
 * @var array<string, string> $old
 * @var list<string> $errors
 * @var array<string, list<string|int>> $options
 */
$v = static fn (string $k): string => e($old[$k] ?? '');
?>
<div class="container narrow-admin">
  <p><a href="/yonetim/radar">← Kelepir listesi</a></p>
  <h1>İlan ekle</h1>
  <p class="muted">Linki yapıştırmanız yeterli: marka, model ve yıl linkteki metinden çıkarılır. Fiyat ve km'yi ilana bakarak girerseniz puan hemen hesaplanır.</p>

  <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif ?>

  <form class="form-card" method="post" action="/yonetim/radar/ekle">
    <?= csrf_field() ?>
    <label class="field"><span>İlan linki</span><input type="url" name="url" value="<?= $v('url') ?>" placeholder="https://www.sahibinden.com/ilan/..." autofocus></label>
    <label class="field"><span>Başlık (isteğe bağlı)</span><input type="text" name="title" value="<?= $v('title') ?>" maxlength="300" placeholder="İlan başlığını kopyalarsanız acil satış sinyali de yakalanır"></label>
    <div class="grid-2">
      <label class="field"><span>Fiyat (TL)</span><input type="text" name="price" value="<?= $v('price') ?>" inputmode="numeric" data-thousands></label>
      <label class="field"><span>Kilometre</span><input type="text" name="km" value="<?= $v('km') ?>" inputmode="numeric" data-thousands></label>
      <label class="field"><span>Marka</span>
        <select name="brand"><option value="">Linkten bul</option><?php foreach ($options['brands'] as $b): ?><option value="<?= e($b) ?>"<?= selected($old['brand'] ?? '', $b) ?>><?= e($b) ?></option><?php endforeach ?></select>
      </label>
      <label class="field"><span>Model ve paket</span><input type="text" name="model" value="<?= $v('model') ?>" placeholder="Linkten bul"></label>
      <label class="field"><span>Model yılı</span>
        <select name="model_year"><option value="">Linkten bul</option><?php foreach ($options['years'] as $y): ?><option value="<?= $y ?>"<?= selected($old['model_year'] ?? '', $y) ?>><?= $y ?></option><?php endforeach ?></select>
      </label>
      <label class="field"><span>Şehir</span>
        <select name="city"><option value="">—</option><?php foreach ($options['cities'] as $c): ?><option value="<?= e($c) ?>"<?= selected($old['city'] ?? '', $c) ?>><?= e($c) ?></option><?php endforeach ?></select>
      </label>
      <label class="field"><span>Hasar</span>
        <select name="damage"><option value="">Bilinmiyor</option><?php foreach ($options['damages'] as $d): ?><option value="<?= e($d) ?>"<?= selected($old['damage'] ?? '', $d) ?>><?= e($d) ?></option><?php endforeach ?></select>
      </label>
      <label class="field"><span>Vites</span>
        <select name="gearbox"><option value="">—</option><?php foreach ($options['gearboxes'] as $g): ?><option value="<?= e($g) ?>"<?= selected($old['gearbox'] ?? '', $g) ?>><?= e($g) ?></option><?php endforeach ?></select>
      </label>
    </div>
    <label class="field"><span>Açıklama (isteğe bağlı)</span><textarea name="description" rows="3" maxlength="2000"><?= $v('description') ?></textarea></label>
    <label class="check"><input type="checkbox" name="is_auction" value="1"<?= !empty($old['is_auction']) ? ' checked' : '' ?>><span>Bu bir ihale (fiyat = başlangıç bedeli)</span></label>
    <label class="field"><span>İhale bitişi</span><input type="datetime-local" name="auction_ends_at" value="<?= $v('auction_ends_at') ?>"></label>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Ekle ve puanla</button>
  </form>
</div>
