<?php
/**
 * @var array<string, mixed> $a
 * @var list<array{id: int|string, mime: string, size: int|string}> $photos
 * @var array<string, string> $statuses
 * @var bool $saved
 */
use Ozoto\Support\Phone;

$wa = 'https://wa.me/' . Phone::international((string) $a['phone']) . '?text=' . rawurlencode(
    'Merhaba ' . $a['full_name'] . ', Ozoto\'ya yaptığınız ' . $a['brand'] . ' ' . $a['model'] . ' başvurusu (' . $a['ref_code'] . ') hakkında yazıyorum.'
);
$rows = [
    'Başvuru no' => $a['ref_code'],
    'Tarih' => format_date((string) $a['created_at']),
    'Araç' => $a['brand'] . ' ' . $a['model'],
    'Model yılı' => $a['model_year'],
    'Kilometre' => format_number((int) $a['km']) . ' km',
    'Yakıt / vites' => $a['fuel'] . ' / ' . $a['gearbox'],
    'Hasar durumu' => $a['damage'],
    'Tramer' => format_tl($a['tramer_amount'] !== null ? (int) $a['tramer_amount'] : null),
    'Beklenen fiyat' => format_tl($a['expected_price'] !== null ? (int) $a['expected_price'] : null),
    'Aciliyet' => $a['urgency'],
    'Konum' => $a['city'] . ($a['district'] ? ' / ' . $a['district'] : ''),
    'Ticari ileti izni' => (int) $a['marketing_consent'] === 1 ? 'Var' : 'Yok',
];
?>
<div class="container">
  <p><a href="/yonetim">← Tüm başvurular</a></p>

  <div class="detail-head">
    <div>
      <h1><?= e($a['full_name']) ?></h1>
      <p class="muted"><?= e($a['brand'] . ' ' . $a['model'] . ' · ' . $a['model_year']) ?></p>
    </div>
    <div class="btn-row">
      <a class="btn btn-primary" href="tel:<?= e($a['phone']) ?>"><?= icon('phone') ?><?= e(Phone::pretty((string) $a['phone'])) ?></a>
      <a class="btn btn-whatsapp" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>WhatsApp</a>
    </div>
  </div>

  <?php if ($saved): ?><div class="alert alert-success" role="status">Kaydedildi.</div><?php endif ?>

  <div class="detail-grid">
    <section class="form-card">
      <h2 class="form-card-title">Araç ve başvuru bilgileri</h2>
      <dl class="dl">
        <?php foreach ($rows as $label => $value): ?>
          <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
        <?php endforeach ?>
      </dl>
      <?php if ($a['notes']): ?>
        <h3 class="sub-title">Müşterinin notu</h3>
        <p class="note-box"><?= nl2br(e($a['notes'])) ?></p>
      <?php endif ?>

      <h3 class="sub-title">Fotoğraflar (<?= count($photos) ?>)</h3>
      <?php if ($photos === []): ?>
        <p class="muted">Fotoğraf eklenmemiş.</p>
      <?php else: ?>
        <div class="gallery">
          <?php foreach ($photos as $photo): ?>
            <a href="/yonetim/foto/<?= (int) $photo['id'] ?>" target="_blank" rel="noopener"><img src="/yonetim/foto/<?= (int) $photo['id'] ?>" alt="Araç fotoğrafı" loading="lazy"></a>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>

    <form class="form-card" method="post" action="/yonetim/basvuru/<?= (int) $a['id'] ?>">
      <?= csrf_field() ?>
      <h2 class="form-card-title">Takip</h2>
      <label class="field">
        <span>Durum</span>
        <select name="status">
          <?php foreach ($statuses as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= selected($a['status'], $key) ?>><?= e($label) ?></option>
          <?php endforeach ?>
        </select>
      </label>
      <label class="field">
        <span>İç notlar (müşteri görmez)</span>
        <textarea name="admin_notes" rows="8" maxlength="5000" placeholder="ör. 14:30'da arandı, 820.000 ₺ teklif verildi, yarın dönüş yapacak."><?= e($a['admin_notes'] ?? '') ?></textarea>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Kaydet</button>
      <p class="muted small">Son güncelleme: <?= e(format_date((string) $a['updated_at'])) ?></p>
    </form>
  </div>
</div>
