<?php
/**
 * @var array<string, string> $f
 * @var array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} $result
 * @var array{total: int, scored: int, hot: int, pending: int} $stats
 * @var list<string> $brands
 * @var list<string> $cities
 * @var list<string> $sites
 * @var list<array<string, mixed>> $saved
 * @var string|null $notice
 * @var bool $searchConfigured
 * @var string|null $lastCollect
 */
use Ozoto\Radar\Listings;
use Ozoto\Support\Catalog;

require __DIR__ . '/_helpers.php';

$v = static fn (string $k): string => e($f[$k] ?? '');
$active = array_filter($f, static fn ($value, $key): bool => $value !== '' && $key !== 'sayfa', ARRAY_FILTER_USE_BOTH);
$queryString = http_build_query($active);
$link = static fn (array $params): string => '/yonetim/radar?' . http_build_query(array_filter($params + $f, static fn ($x): bool => $x !== '' && $x !== null));
$sort = $f['sirala'] ?? 'puan';
?>
<div class="container">
  <div class="admin-toolbar">
    <div>
      <h1>Kelepir Radar</h1>
      <p class="muted small">
        <?= $lastCollect ? 'Son tarama: ' . e(format_date($lastCollect)) : 'Henüz otomatik tarama yapılmadı.' ?>
        <?php if (!$searchConfigured): ?> · <a href="/yonetim/radar/ayarlar">Otomatik taramayı aç</a><?php endif ?>
      </p>
    </div>
    <div class="btn-row-left">
      <form method="post" action="/yonetim/radar/tara">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">Şimdi tara</button>
      </form>
      <a class="btn btn-primary btn-sm" href="/yonetim/radar/ekle">+ İlan ekle</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif ?>

  <div class="stat-row">
    <div class="stat"><span class="stat-value"><?= format_number($stats['total']) ?></span><span class="stat-label">Toplam ilan</span></div>
    <div class="stat"><span class="stat-value"><?= format_number($stats['scored']) ?></span><span class="stat-label">Puanlanan</span></div>
    <a class="stat stat-hot" href="<?= e($link(['puan_min' => '60', 'sirala' => 'puan', 'sayfa' => ''])) ?>"><span class="stat-value"><?= format_number($stats['hot']) ?></span><span class="stat-label">60+ puan kelepir</span></a>
    <div class="stat"><span class="stat-value"><?= format_number($stats['pending']) ?></span><span class="stat-label">Değerleme bekliyor</span></div>
  </div>

  <div class="radar-layout">
    <aside class="radar-filters">
      <form method="get" action="/yonetim/radar" class="form-card filter-form">
        <label class="field"><span>Ara</span><input type="search" name="q" value="<?= $v('q') ?>" placeholder="Marka, model, şehir, ilan no…"></label>

        <label class="field"><span>Sırala</span>
          <select name="sirala">
            <?php foreach (Listings::SORTS as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= selected($sort, $key) ?>><?= e($label) ?></option>
            <?php endforeach ?>
          </select>
        </label>

        <label class="field"><span>Marka</span>
          <select name="marka">
            <option value="">Tümü</option>
            <?php foreach ($brands as $brand): ?><option value="<?= e($brand) ?>"<?= selected($f['marka'] ?? '', $brand) ?>><?= e($brand) ?></option><?php endforeach ?>
          </select>
        </label>
        <label class="field"><span>Model</span><input type="text" name="model" value="<?= $v('model') ?>" placeholder="ör. Corolla"></label>

        <div class="grid-2 tight">
          <label class="field"><span>Yıl (en az)</span>
            <select name="yil_min"><option value="">—</option><?php foreach (Catalog::years() as $y): ?><option value="<?= $y ?>"<?= selected($f['yil_min'] ?? '', $y) ?>><?= $y ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Yıl (en çok)</span>
            <select name="yil_max"><option value="">—</option><?php foreach (Catalog::years() as $y): ?><option value="<?= $y ?>"<?= selected($f['yil_max'] ?? '', $y) ?>><?= $y ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Fiyat (en az)</span><input type="text" name="fiyat_min" value="<?= $v('fiyat_min') ?>" inputmode="numeric" data-thousands placeholder="TL"></label>
          <label class="field"><span>Fiyat (en çok)</span><input type="text" name="fiyat_max" value="<?= $v('fiyat_max') ?>" inputmode="numeric" data-thousands placeholder="TL"></label>
        </div>
        <label class="field"><span>Km (en çok)</span><input type="text" name="km_max" value="<?= $v('km_max') ?>" inputmode="numeric" data-thousands></label>

        <label class="field"><span>Şehir</span>
          <select name="sehir"><option value="">Tümü</option><?php foreach ($cities as $city): ?><option value="<?= e($city) ?>"<?= selected($f['sehir'] ?? '', $city) ?>><?= e($city) ?></option><?php endforeach ?></select>
        </label>
        <label class="field"><span>Kaynak</span>
          <select name="kaynak"><option value="">Tümü</option><?php foreach ($sites as $site): ?><option value="<?= e($site) ?>"<?= selected($f['kaynak'] ?? '', $site) ?>><?= e($site) ?></option><?php endforeach ?></select>
        </label>
        <label class="field"><span>Hasar</span>
          <select name="hasar"><option value="">Tümü</option><?php foreach (Catalog::damages() as $d): ?><option value="<?= e($d) ?>"<?= selected($f['hasar'] ?? '', $d) ?>><?= e($d) ?></option><?php endforeach ?></select>
        </label>
        <div class="grid-2 tight">
          <label class="field"><span>Vites</span>
            <select name="vites"><option value="">Tümü</option><?php foreach (Catalog::gearboxes() as $g): ?><option value="<?= e($g) ?>"<?= selected($f['vites'] ?? '', $g) ?>><?= e($g) ?></option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Yakıt</span>
            <select name="yakit"><option value="">Tümü</option><?php foreach (Catalog::fuels() as $fu): ?><option value="<?= e($fu) ?>"<?= selected($f['yakit'] ?? '', $fu) ?>><?= e($fu) ?></option><?php endforeach ?></select>
          </label>
        </div>
        <div class="grid-2 tight">
          <label class="field"><span>En az puan</span>
            <select name="puan_min"><option value="">—</option><?php foreach ([30, 50, 60, 70, 80] as $p): ?><option value="<?= $p ?>"<?= selected($f['puan_min'] ?? '', $p) ?>><?= $p ?>+</option><?php endforeach ?></select>
          </label>
          <label class="field"><span>Durum</span>
            <select name="durum">
              <option value="">Elenenler hariç</option>
              <?php foreach (Listings::STATUSES as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($f['durum'] ?? '', $key) ?>><?= e($label) ?></option><?php endforeach ?>
              <option value="hepsi"<?= selected($f['durum'] ?? '', 'hepsi') ?>>Hepsi</option>
            </select>
          </label>
        </div>
        <label class="check"><input type="checkbox" name="acil" value="1"<?= !empty($f['acil']) ? ' checked' : '' ?>><span>Sadece "acil" ilanlar</span></label>
        <label class="check"><input type="checkbox" name="ihale" value="1"<?= !empty($f['ihale']) ? ' checked' : '' ?>><span>Sadece ihaleler</span></label>
        <label class="check"><input type="checkbox" name="puanli" value="1"<?= !empty($f['puanli']) ? ' checked' : '' ?>><span>Fiyatı bilinmeyenleri gizle</span></label>

        <div class="btn-row-left">
          <button class="btn btn-primary btn-sm" type="submit">Filtrele</button>
          <a class="btn btn-ghost btn-sm" href="/yonetim/radar">Temizle</a>
        </div>
      </form>

      <div class="form-card saved-searches">
        <h2 class="form-card-title">Kayıtlı aramalar</h2>
        <?php if ($saved === []): ?><p class="muted small">Filtreleri ayarlayıp aşağıdan kaydedin.</p><?php endif ?>
        <ul>
          <?php foreach ($saved as $s): ?>
            <li>
              <a href="/yonetim/radar?<?= e($s['query_string']) ?>"><?= e($s['name']) ?></a>
              <form method="post" action="/yonetim/radar/arama-sil/<?= (int) $s['id'] ?>"><?= csrf_field() ?><button class="link-btn" type="submit" aria-label="Sil">×</button></form>
            </li>
          <?php endforeach ?>
        </ul>
        <?php if ($queryString !== ''): ?>
          <form method="post" action="/yonetim/radar/arama-kaydet" class="save-search">
            <?= csrf_field() ?>
            <input type="hidden" name="query" value="<?= e($queryString) ?>">
            <input type="text" name="name" placeholder="Bu aramaya ad verin" maxlength="80" required aria-label="Arama adı">
            <button class="btn btn-ghost btn-sm" type="submit">Kaydet</button>
          </form>
        <?php endif ?>
      </div>
    </aside>

    <section class="radar-results">
      <div class="results-head">
        <strong><?= format_number($result['total']) ?> ilan</strong>
        <nav class="sort-chips" aria-label="Hızlı sıralama">
          <?php foreach (['puan' => 'Puan', 'fark' => 'En ucuz', 'fiyat_artan' => 'Fiyat ↑', 'fiyat_azalan' => 'Fiyat ↓', 'yeni' => 'En yeni'] as $key => $label): ?>
            <a class="tab<?= $sort === $key ? ' is-active' : '' ?>" href="<?= e($link(['sirala' => $key, 'sayfa' => ''])) ?>"><?= e($label) ?></a>
          <?php endforeach ?>
        </nav>
      </div>

      <?php if ($result['rows'] === []): ?>
        <div class="empty">
          Bu filtreye uyan ilan yok.
          <?php if ($stats['total'] === 0): ?><br><a href="/yonetim/radar/ekle">İlk ilanı ekleyin</a> veya <a href="/yonetim/radar/ayarlar">otomatik taramayı açın</a>.<?php endif ?>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table radar-table">
            <thead>
              <tr><th>Puan</th><th>Araç</th><th>Fiyat</th><th>Piyasa</th><th>Fark</th><th>Durum</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($result['rows'] as $l): ?>
                <?php $score = $l['score'] !== null ? (int) $l['score'] : null; ?>
                <tr>
                  <td><span class="score <?= radar_score_class($score) ?>"><?= $score ?? '–' ?></span></td>
                  <td>
                    <a class="row-link" href="/yonetim/radar/<?= (int) $l['id'] ?>"><?= e(radar_title($l)) ?></a>
                    <?php if ((int) $l['urgent'] === 1): ?><span class="pill pill-urgency-high">acil</span><?php endif ?>
                    <?php if ((int) $l['suspicious'] === 1): ?><span class="pill pill-warn">şüpheli</span><?php endif ?>
                    <?php if ((int) $l['is_auction'] === 1): ?><span class="pill pill-offered">ihale<?= $l['auction_ends_at'] ? ' · ' . e(radar_countdown($l['auction_ends_at'])) : '' ?></span><?php endif ?>
                    <div class="muted small">
                      <?= e(implode(' · ', array_filter([
                          $l['model_year'] ?? null,
                          $l['km'] !== null ? format_number((int) $l['km']) . ' km' : null,
                          $l['damage'] ?? null,
                          $l['city'] ?? null,
                          ($l['site'] ?? '') . ($l['source_ref'] ? ' #' . $l['source_ref'] : ''),
                      ]))) ?>
                    </div>
                  </td>
                  <td class="nowrap"><?= e(format_tl($l['price'] !== null ? (int) $l['price'] : null)) ?></td>
                  <td class="nowrap muted"><?= e(format_tl($l['market_value'] !== null ? (int) $l['market_value'] : null)) ?></td>
                  <td class="nowrap <?= $l['discount_pct'] !== null && (float) $l['discount_pct'] > 0 ? 'good' : '' ?>"><?= e(radar_discount($l['discount_pct'])) ?></td>
                  <td><span class="pill pill-st-<?= e($l['status']) ?>"><?= e(Listings::STATUSES[$l['status']] ?? $l['status']) ?></span></td>
                  <td class="nowrap"><?php if ($l['url']): ?><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer">İlana git ↗</a><?php endif ?></td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>

        <?php if ($result['pages'] > 1): ?>
          <nav class="pagination" aria-label="Sayfalar">
            <?php if ($result['page'] > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e($link(['sayfa' => (string) ($result['page'] - 1)])) ?>">← Önceki</a><?php endif ?>
            <span class="muted">Sayfa <?= $result['page'] ?> / <?= $result['pages'] ?></span>
            <?php if ($result['page'] < $result['pages']): ?><a class="btn btn-ghost btn-sm" href="<?= e($link(['sayfa' => (string) ($result['page'] + 1)])) ?>">Sonraki →</a><?php endif ?>
          </nav>
        <?php endif ?>
      <?php endif ?>
    </section>
  </div>
</div>
