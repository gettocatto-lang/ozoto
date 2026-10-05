<?php
/**
 * @var list<array{model: string, links: list<array{label: string, url: string, site: string}>}> $rows
 * @var string $city
 * @var list<string> $cities
 */
?>
<div class="container">
  <p><a href="/yonetim/radar">← Kelepir listesi</a></p>
  <h1>Kelepir linkleri</h1>
  <div class="howto">
    <p><strong>Nasıl kullanılır:</strong></p>
    <ol>
      <li><strong>Bakmak için:</strong> linke tıklayın. Sayfa en ucuzdan sıralı ve 50 ilanlı açılır; eklenti hepsini puanlar, sağ üstteki panel kelepirleri gösterir.
        Kelepir yoksa "sonraki sayfa"ya geçin.</li>
      <li><strong>Kayıtlı arama kurmak için:</strong> aynı linki açın → sitede <strong>"Aramayı kaydet"</strong> → e-posta bildirimini açın.
        Yeni ilanlar <code>radar@ozoto.online</code>'a gelir, Radar 5 dakikada bir okur ve kelepiri Telegram'a atar.</li>
    </ol>
    <p class="small muted">Linkler sitelerin bugünkü adres yapısına göre üretilir. Sıralama veya "son 24 saat" uygulanmazsa sayfadan elle seçin ve bana bildirin.</p>
  </div>

  <form method="get" action="/yonetim/radar/linkler" class="btn-row-left">
    <label class="field"><span>Şehir (sahibinden "son 24 saat" linklerinde)</span>
      <select name="sehir" onchange="this.form.submit()">
        <option value="">Tüm Türkiye</option>
        <?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"<?= selected($city, $c) ?>><?= e($c) ?></option><?php endforeach ?>
      </select>
    </label>
    <noscript><button class="btn btn-ghost" type="submit">Uygula</button></noscript>
  </form>

  <div class="table-wrap">
    <table class="table quick-links">
      <thead><tr><th>Model</th><th>sahibinden · son 24 saat</th><th>sahibinden · sahibinden ilanları</th><th>arabam · sahibinden ilanları</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><strong><?= e($row['model']) ?></strong></td>
            <?php foreach ($row['links'] as $link): ?>
              <td><a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['label']) ?> ↗</a></td>
            <?php endforeach ?>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
