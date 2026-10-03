<?php
/**
 * @var bool $apiKeySet
 * @var bool $enabled
 * @var string $queries
 * @var string $freshness
 * @var string $cronUrl
 * @var list<array<string, mixed>> $runs
 * @var string|null $notice
 */
?>
<div class="container narrow-admin">
  <h1>Radar ayarları</h1>
  <?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif ?>

  <form class="form-card" method="post" action="/yonetim/radar/ayarlar">
    <?= csrf_field() ?>
    <h2 class="form-card-title">Arama motoru taraması</h2>
    <p class="muted small">
      İlan sitelerine doğrudan istek atılmaz. Brave Search'ün resmî API'si ile arama motorunun dizinindeki yeni ilan linkleri,
      başlıkları ve özetleri alınır (satıcı adı ve telefon alınmaz). Anahtar almak için
      <a href="https://api-dashboard.search.brave.com/" target="_blank" rel="noopener noreferrer">api-dashboard.search.brave.com</a>
      adresinde hesap açıp "Search" planını seçin (ayda yaklaşık 1.000 sorgu ücretsiz kredi).
    </p>
    <label class="field">
      <span>API anahtarı <?= $apiKeySet ? '(kayıtlı — değiştirmek için yenisini yazın)' : '' ?></span>
      <input type="password" name="search_api_key" autocomplete="off" placeholder="<?= $apiKeySet ? '••••••••••••' : 'BSA...' ?>">
    </label>
    <?php if ($apiKeySet): ?><label class="check"><input type="checkbox" name="search_api_key_clear" value="1"><span>Kayıtlı anahtarı sil</span></label><?php endif ?>
    <label class="check"><input type="checkbox" name="search_enabled" value="1"<?= $enabled ? ' checked' : '' ?>><span>Otomatik taramayı aç</span></label>
    <label class="field">
      <span>Arama sorguları (her satıra bir tane; her taramada sırayla en fazla 10'u çalışır)</span>
      <textarea name="search_queries" rows="9"><?= e($queries) ?></textarea>
    </label>
    <label class="field"><span>Ne kadar yeni ilanlar?</span>
      <select name="search_freshness">
        <option value="pd"<?= selected($freshness, 'pd') ?>>Son 24 saat</option>
        <option value="pw"<?= selected($freshness, 'pw') ?>>Son 1 hafta</option>
        <option value="pm"<?= selected($freshness, 'pm') ?>>Son 1 ay</option>
      </select>
    </label>
    <button class="btn btn-primary" type="submit">Kaydet</button>
  </form>

  <section class="form-card">
    <h2 class="form-card-title">Zamanlanmış görev (Plesk)</h2>
    <p class="muted small">Taramanın kendiliğinden çalışması için Plesk → ozoto.online → <strong>Zamanlanmış Görevler → Görev Ekle → "URL getir"</strong>
      seçin, aşağıdaki adresi yapıştırın ve sıklığı <strong>her 30 dakika</strong> yapın. Bu adres gizlidir, paylaşmayın.</p>
    <input class="code-input" type="text" readonly value="<?= e($cronUrl) ?>" aria-label="Zamanlanmış görev adresi">
    <form method="post" action="/yonetim/radar/tara">
      <?= csrf_field() ?>
      <input type="hidden" name="back" value="ayarlar">
      <button class="btn btn-ghost" type="submit">Şimdi bir kez çalıştır</button>
    </form>
  </section>

  <section class="form-card">
    <h2 class="form-card-title">Son taramalar</h2>
    <?php if ($runs === []): ?>
      <p class="muted">Henüz tarama yok.</p>
    <?php else: ?>
      <ul class="history">
        <?php foreach ($runs as $run): ?>
          <li><span><?= e(format_date((string) $run['started_at'])) ?></span><span><?= e((string) $run['message']) ?></span></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>
</div>
