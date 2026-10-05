<?php
/**
 * @var array<string, mixed> $l
 * @var list<array{price: int|string, seen_at: string}> $history
 * @var list<string> $notes
 * @var array<string, list<string|int>> $options
 * @var string|null $notice
 * @var array{data: array<string, mixed>, analysis: ?array<string, mixed>, raw: ?array<string, mixed>, updated_at: string}|null $details
 */
use Ozoto\Radar\Listings;

require __DIR__ . '/_helpers.php';

$score = $l['score'] !== null ? (int) $l['score'] : null;
$val = static fn (string $k): string => e((string) ($l[$k] ?? ''));
?>
<div class="container">
  <p><a href="/yonetim/radar">← Kelepir listesi</a></p>

  <?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif ?>

  <div class="detail-head">
    <div class="detail-title">
      <span class="score score-lg <?= radar_score_class($score) ?>"><?= $score ?? '–' ?></span>
      <div>
        <h1><?= e(radar_title($l)) ?></h1>
        <p class="muted"><?= e(implode(' · ', array_filter([$l['model_year'] ?? null, $l['city'] ?? null, $l['site'] ?? null, $l['source_ref'] ? 'İlan no ' . $l['source_ref'] : null]))) ?></p>
      </div>
    </div>
    <div class="btn-row">
      <?php if ($l['url']): ?><a class="btn btn-primary" href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer">İlana git ↗</a><?php endif ?>
      <form method="post" action="/yonetim/radar/<?= (int) $l['id'] ?>/degerle"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Yeniden değerle</button></form>
    </div>
  </div>

  <div class="stat-row">
    <div class="stat"><span class="stat-value"><?= e(format_tl($l['price'] !== null ? (int) $l['price'] : null)) ?></span><span class="stat-label"><?= (int) $l['is_auction'] === 1 ? 'İhale başlangıç bedeli' : 'İlan fiyatı' ?></span></div>
    <div class="stat"><span class="stat-value"><?= e(format_tl($l['market_value'] !== null ? (int) $l['market_value'] : null)) ?></span><span class="stat-label">Tahmini piyasa değeri</span></div>
    <div class="stat <?= $l['discount_pct'] !== null && (float) $l['discount_pct'] > 0 ? 'stat-hot' : '' ?>"><span class="stat-value"><?= e(radar_discount($l['discount_pct'])) ?></span><span class="stat-label">Piyasaya göre</span></div>
    <div class="stat"><span class="stat-value"><?= e(format_tl($l['tsb_value'] !== null ? (int) $l['tsb_value'] : null)) ?></span><span class="stat-label">TSB kasko değeri</span></div>
  </div>

  <?php $a = $details['analysis'] ?? null; ?>
  <?php if ($a): ?>
    <section class="form-card analysis">
      <div class="analysis-head">
        <span class="verdict verdict-<?= e((string) $a['verdict']) ?><?= !empty($a['risky']) ? ' verdict-risky' : '' ?>"><?= e((string) $a['verdict_label']) ?></span>
        <p><strong><?= e((string) $a['headline']) ?></strong></p>
      </div>
      <div class="analysis-grid">
        <?php foreach ($a['blocks'] as $block): ?>
          <div class="analysis-block<?= $block['type'] === 'text' ? ' analysis-wide' : '' ?>">
            <h3><?= e((string) $block['title']) ?></h3>
            <?php if ($block['type'] === 'kv'): ?>
              <table class="kv"><?php foreach ($block['rows'] as [$k, $v]): ?><tr><td><?= e((string) $k) ?></td><td><?= e((string) $v) ?></td></tr><?php endforeach ?></table>
            <?php elseif ($block['type'] === 'list'): ?>
              <ul class="levels"><?php foreach ($block['items'] as $item): ?><li class="lvl-<?= e((string) $item['level']) ?>"><?= e((string) $item['text']) ?></li><?php endforeach ?></ul>
            <?php else: ?>
              <?php foreach ($block['paragraphs'] as $paragraph): ?><p><?= e((string) $paragraph) ?></p><?php endforeach ?>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
      <details>
        <summary class="small muted">İlan sayfasından okunan veriler (<?= e(format_date((string) $details['updated_at'])) ?>)</summary>
        <textarea class="code-input" rows="10" readonly><?= e((string) json_encode($details['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
      </details>
    </section>
  <?php else: ?>
    <p class="muted small">Eksper analizi için ilanı tarayıcı eklentisi açıkken bir kez açın; araç bilgileri, boya-değişen ve tramer okunup burada gösterilir.</p>
  <?php endif ?>

  <div class="detail-grid">
    <div class="stack">
      <section class="form-card">
        <h2 class="form-card-title">Puan neden böyle?</h2>
        <?php if ((int) $l['suspicious'] === 1): ?><div class="alert alert-error">Fiyat piyasanın çok altında: sahte ilan olabilir. Aracı görmeden kapora göndermeyin.</div><?php endif ?>
        <ul class="reason-list">
          <?php foreach ($notes as $note): ?><li><?= e($note) ?></li><?php endforeach ?>
          <?php if ($notes === []): ?><li class="muted">Henüz değerlenmedi.</li><?php endif ?>
        </ul>
        <?php if ($l['valued_at']): ?><p class="muted small">Değerleme: <?= e(format_date((string) $l['valued_at'])) ?></p><?php endif ?>
      </section>

      <?php if ($l['title'] || $l['description']): ?>
        <section class="form-card">
          <h2 class="form-card-title">İlan metni</h2>
          <?php if ($l['title']): ?><p><strong><?= e($l['title']) ?></strong></p><?php endif ?>
          <?php if ($l['description']): ?><p class="muted"><?= nl2br(e($l['description'])) ?></p><?php endif ?>
        </section>
      <?php endif ?>

      <section class="form-card">
        <h2 class="form-card-title">Fiyat geçmişi</h2>
        <?php if ($history === []): ?>
          <p class="muted">Fiyat kaydı yok.</p>
        <?php else: ?>
          <ul class="history">
            <?php foreach ($history as $h): ?><li><span><?= e(format_date((string) $h['seen_at'])) ?></span><strong><?= e(format_tl((int) $h['price'])) ?></strong></li><?php endforeach ?>
          </ul>
        <?php endif ?>
        <p class="muted small">İlk görülme <?= e(format_date((string) $l['first_seen_at'])) ?> · son görülme <?= e(format_date((string) $l['last_seen_at'])) ?></p>
      </section>

      <form class="form-card" method="post" action="/yonetim/radar/<?= (int) $l['id'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="edit" value="1">
        <h2 class="form-card-title">Bilgileri düzelt</h2>
        <p class="muted small">Arama motorundan gelen kayıtlarda fiyat veya km eksik olabilir; ilana bakıp buradan tamamlayın, puan hemen yeniden hesaplanır.</p>
        <div class="grid-2">
          <label class="field"><span>Marka</span>
            <select name="brand"><option value="">—</option><?php foreach ($options['brands'] as $b): ?><option value="<?= e($b) ?>"<?= selected($l['brand'] ?? '', $b) ?>><?= e($b) ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Model ve paket</span><input type="text" name="model" value="<?= $val('model') ?>" placeholder="ör. Corolla 1.6 Vision"></label>
          <label class="field"><span>Model yılı</span>
            <select name="model_year"><option value="">—</option><?php foreach ($options['years'] as $y): ?><option value="<?= $y ?>"<?= selected($l['model_year'] ?? '', $y) ?>><?= $y ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Kilometre</span><input type="text" name="km" value="<?= e(format_number($l['km'] !== null ? (int) $l['km'] : null)) ?>" inputmode="numeric" data-thousands></label>
          <label class="field"><span>Fiyat (TL)</span><input type="text" name="price" value="<?= e(format_number($l['price'] !== null ? (int) $l['price'] : null)) ?>" inputmode="numeric" data-thousands></label>
          <label class="field"><span>Şehir</span>
            <select name="city"><option value="">—</option><?php foreach ($options['cities'] as $c): ?><option value="<?= e($c) ?>"<?= selected($l['city'] ?? '', $c) ?>><?= e($c) ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Hasar</span>
            <select name="damage"><option value="">Bilinmiyor</option><?php foreach ($options['damages'] as $d): ?><option value="<?= e($d) ?>"<?= selected($l['damage'] ?? '', $d) ?>><?= e($d) ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Vites</span>
            <select name="gearbox"><option value="">—</option><?php foreach ($options['gearboxes'] as $g): ?><option value="<?= e($g) ?>"<?= selected($l['gearbox'] ?? '', $g) ?>><?= e($g) ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Yakıt</span>
            <select name="fuel"><option value="">—</option><?php foreach ($options['fuels'] as $fu): ?><option value="<?= e($fu) ?>"<?= selected($l['fuel'] ?? '', $fu) ?>><?= e($fu) ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>İhale bitişi</span><input type="datetime-local" name="auction_ends_at" value="<?= $l['auction_ends_at'] ? e(date('Y-m-d\TH:i', (int) strtotime((string) $l['auction_ends_at']))) : '' ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="is_auction" value="1"<?= (int) $l['is_auction'] === 1 ? ' checked' : '' ?>><span>Bu bir ihale (fiyat = başlangıç bedeli)</span></label>
        <input type="hidden" name="title" value="<?= $val('title') ?>">
        <input type="hidden" name="description" value="<?= $val('description') ?>">
        <button class="btn btn-primary" type="submit">Kaydet ve yeniden puanla</button>
      </form>
    </div>

    <div class="stack">
      <form class="form-card" method="post" action="/yonetim/radar/<?= (int) $l['id'] ?>">
        <?= csrf_field() ?>
        <h2 class="form-card-title">Takip</h2>
        <label class="field"><span>Durum</span>
          <select name="status">
            <?php foreach (Listings::STATUSES as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($l['status'], $key) ?>><?= e($label) ?></option><?php endforeach ?>
          </select>
        </label>
        <label class="field"><span>Notlar</span><textarea name="notes" rows="6" maxlength="5000" placeholder="ör. Satıcıyla görüşüldü, 780 bine iner."><?= $val('notes') ?></textarea></label>
        <button class="btn btn-primary btn-block" type="submit">Kaydet</button>
      </form>

      <form class="form-card" method="post" action="/yonetim/radar/<?= (int) $l['id'] ?>/sil">
        <?= csrf_field() ?>
        <h2 class="form-card-title">Kaydı sil</h2>
        <p class="muted small">Listeden tamamen kaldırır. Sadece gizlemek için durumu "Elendi" yapın.</p>
        <button class="btn btn-ghost btn-block" type="submit">Sil</button>
      </form>
    </div>
  </div>
</div>
