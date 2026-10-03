<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var array<string, string> $statuses
 * @var array<string, int> $counts
 * @var string $status
 * @var string $q
 * @var int $page
 * @var int $pages
 * @var int $total
 */
use Ozoto\Support\Phone;

$query = static fn (array $params): string => '/yonetim?' . http_build_query(array_filter(
    $params + ['durum' => $status, 'q' => $q],
    static fn ($v) => $v !== '' && $v !== null && $v !== 1
));
?>
<div class="container">
  <div class="admin-toolbar">
    <h1>Başvurular <span class="muted">(<?= $total ?>)</span></h1>
    <form class="search" method="get" action="/yonetim">
      <?php if ($status !== ''): ?><input type="hidden" name="durum" value="<?= e($status) ?>"><?php endif ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="İsim, telefon, marka, il, başvuru no…" aria-label="Ara">
      <button class="btn btn-primary btn-sm" type="submit">Ara</button>
    </form>
  </div>

  <nav class="tabs" aria-label="Duruma göre filtre">
    <a class="tab<?= $status === '' ? ' is-active' : '' ?>" href="<?= e($query(['durum' => ''])) ?>">Tümü <span><?= array_sum($counts) ?></span></a>
    <?php foreach ($statuses as $key => $label): ?>
      <a class="tab<?= $status === $key ? ' is-active' : '' ?>" href="<?= e($query(['durum' => $key])) ?>"><?= e($label) ?> <span><?= $counts[$key] ?? 0 ?></span></a>
    <?php endforeach ?>
  </nav>

  <?php if ($rows === []): ?>
    <div class="empty">Henüz bu filtreye uyan başvuru yok.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Tarih</th>
            <th>Başvuru</th>
            <th>Araç</th>
            <th>Km</th>
            <th>Beklenen</th>
            <th>Aciliyet</th>
            <th>Durum</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="nowrap"><?= e(format_date((string) $row['created_at'])) ?></td>
              <td>
                <a class="row-link" href="/yonetim/basvuru/<?= (int) $row['id'] ?>"><?= e($row['full_name']) ?></a>
                <div class="muted small"><?= e($row['ref_code']) ?> · <a href="tel:<?= e($row['phone']) ?>"><?= e(Phone::pretty((string) $row['phone'])) ?></a> · <?= e($row['city']) ?></div>
              </td>
              <td>
                <?= e($row['brand'] . ' ' . $row['model']) ?>
                <div class="muted small"><?= (int) $row['model_year'] ?> · <?= e($row['damage']) ?><?= (int) $row['photo_count'] > 0 ? ' · ' . (int) $row['photo_count'] . ' foto' : '' ?></div>
              </td>
              <td class="nowrap"><?= e(format_number((int) $row['km'])) ?></td>
              <td class="nowrap"><?= e(format_tl($row['expected_price'] !== null ? (int) $row['expected_price'] : null)) ?></td>
              <td><span class="pill pill-urgency-<?= $row['urgency'] === 'Bugün' ? 'high' : 'normal' ?>"><?= e($row['urgency']) ?></span></td>
              <td><span class="pill pill-<?= e($row['status']) ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="pagination" aria-label="Sayfalar">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e($query(['sayfa' => $page - 1])) ?>">← Önceki</a><?php endif ?>
        <span class="muted">Sayfa <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e($query(['sayfa' => $page + 1])) ?>">Sonraki →</a><?php endif ?>
      </nav>
    <?php endif ?>
  <?php endif ?>
</div>
