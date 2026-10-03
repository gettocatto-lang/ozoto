<?php
/**
 * @var bool $apiKeySet
 * @var bool $enabled
 * @var string $queries
 * @var string $freshness
 * @var int $interval
 * @var array<string, mixed> $mail
 * @var array<string, mixed>|null $preview
 * @var string $cronUrl
 * @var list<array<string, mixed>> $runs
 * @var string|null $notice
 */
$sourceNames = ['eposta' => 'E-posta', 'arama' => 'Arama motoru'];
?>
<div class="container narrow-admin">
  <h1>Radar ayarları</h1>
  <?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif ?>

  <form class="form-card" method="post" action="/yonetim/radar/eposta" id="eposta">
    <?= csrf_field() ?>
    <h2 class="form-card-title">1. E-posta bildirimleri <small>(ana kaynak)</small></h2>
    <div class="howto">
      <p><strong>Nasıl çalışır:</strong> Sitelerde aramayı kaydedip "yeni ilan bildirimi"ni açtığınızda, kritere uyan her yeni ilan e-postayla gelir.
        Radar bu e-postaları birkaç dakikada bir okur, ilanları çıkarır ve puanlar. Sitelere tarama isteği gitmez.</p>
      <ol>
        <li>Plesk → <strong>Mail</strong> → <strong>Create Email Address</strong>: <code>radar@ozoto.online</code> ve güçlü bir şifre.</li>
        <li>sahibinden, arabam, letgo hesaplarınızda bildirim e-postası olarak bu adresi kullanın (veya gelen bildirimleri bu adrese otomatik yönlendirin).</li>
        <li>Sitelerde aramaları kaydedin ve <strong>"Bildirim almak istiyorum"</strong> seçeneğini anında/sık olarak açın. Örnekler: "Otomobil · son eklenenler · 300–900 bin TL", "acil", marka/şehir bazlı aramalar. Ne kadar çok kayıtlı arama, o kadar çok ilan.</li>
        <li>Aşağıya posta kutusu bilgilerini girin, <strong>Bağlantıyı test et</strong>'e basın, sonra otomatik okumayı açın.</li>
      </ol>
    </div>

    <div class="grid-2">
      <label class="field"><span>Posta sunucusu (Plesk'te genelde <code>localhost</code>, port 143, güvenlik "Yok")</span><input type="text" name="mail_host" value="<?= e($mail['host']) ?>" placeholder="localhost"></label>
      <div class="grid-2 tight">
        <label class="field"><span>Port</span><input type="text" name="mail_port" value="<?= e($mail['port']) ?>" inputmode="numeric"></label>
        <label class="field"><span>Güvenlik</span>
          <select name="mail_security">
            <option value="none"<?= selected($mail['security'], 'none') ?>>Yok</option>
            <option value="ssl"<?= selected($mail['security'], 'ssl') ?>>SSL (993)</option>
            <option value="starttls"<?= selected($mail['security'], 'starttls') ?>>STARTTLS (143)</option>
          </select>
        </label>
      </div>
      <label class="field"><span>Kullanıcı (e-posta adresi)</span><input type="text" name="mail_user" value="<?= e($mail['user']) ?>" autocomplete="off"></label>
      <label class="field"><span>Şifre <?= $mail['pass_set'] ? '(kayıtlı — değiştirmek için yazın)' : '' ?></span><input type="password" name="mail_pass" autocomplete="new-password" placeholder="<?= $mail['pass_set'] ? '••••••••' : '' ?>"></label>
      <label class="field"><span>Klasör</span><input type="text" name="mail_folder" value="<?= e($mail['folder']) ?>"></label>
      <label class="field"><span>İşlenen e-postaları kaç gün sonra sil (0 = silme)</span><input type="text" name="mail_delete_days" value="<?= e($mail['delete_days']) ?>" inputmode="numeric"></label>
    </div>
    <label class="field">
      <span>İzinli gönderenler (alan adı veya adres, her satıra bir tane). Başka gönderenlerin e-postaları yok sayılır; elle yönlendirdiğiniz e-postalar için kendi adresinizi ekleyin.</span>
      <textarea name="mail_senders" rows="5"><?= e($mail['senders']) ?></textarea>
    </label>
    <label class="check"><input type="checkbox" name="mail_enabled" value="1"<?= $mail['enabled'] ? ' checked' : '' ?>><span>Otomatik okumayı aç</span></label>
    <div class="btn-row-left">
      <button class="btn btn-primary" type="submit">Kaydet</button>
      <button class="btn btn-ghost" type="submit" form="mail-test">Bağlantıyı test et</button>
    </div>
  </form>
  <form id="mail-test" method="post" action="/yonetim/radar/eposta-test"><?= csrf_field() ?></form>

  <form class="form-card" method="post" action="/yonetim/radar/eposta-onizle" enctype="multipart/form-data" id="onizleme">
    <?= csrf_field() ?>
    <h2 class="form-card-title">Bir bildirim e-postasını dene</h2>
    <p class="muted small">Gelen bir bildirim e-postasını bilgisayarınıza <strong>.eml</strong> olarak indirin (Gmail: ⋮ → "Mesajı indir", Outlook: "Farklı kaydet") ve yükleyin.
      Radar'ın o e-postadan hangi ilanları çıkardığını görürsünüz.</p>
    <label class="field"><span>.eml dosyası</span><input type="file" name="eml" accept=".eml,message/rfc822" required></label>
    <label class="check"><input type="checkbox" name="save" value="1"><span>Bulunan ilanları Radar'a da ekle</span></label>
    <button class="btn btn-ghost" type="submit">Dene</button>

    <?php if ($preview): ?>
      <div class="preview">
        <p><strong><?= e($preview['subject'] ?: '(konu yok)') ?></strong><br><span class="muted small">Gönderen: <?= e($preview['from']) ?></span></p>
        <?php if (!$preview['allowed']): ?>
          <div class="alert alert-error">Bu gönderen izinli listesinde değil, e-posta yok sayıldı. Gönderenin alan adını yukarıdaki listeye ekleyip tekrar deneyin.</div>
        <?php elseif ($preview['listings'] === []): ?>
          <div class="alert alert-error">Bu e-postada ilan linki bulunamadı. Dosyayı Claude'a gönderin, biçime göre ayarlayalım.</div>
        <?php else: ?>
          <p class="muted small"><?= count($preview['listings']) ?> ilan bulundu<?= $preview['saved'] ? ', ' . (int) $preview['added'] . ' tanesi Radar\'a yeni eklendi' : ' (eklenmedi, sadece önizleme)' ?>.</p>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>İlan</th><th>Yıl</th><th>Km</th><th>Fiyat</th><th>Şehir</th></tr></thead>
              <tbody>
                <?php foreach ($preview['listings'] as $p): ?>
                  <tr>
                    <td><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(trim(($p['brand'] ?? '') . ' ' . ($p['model'] ?? '')) ?: ($p['title'] ?: $p['url'])) ?></a></td>
                    <td><?= e((string) ($p['model_year'] ?? '—')) ?></td>
                    <td class="nowrap"><?= e($p['km'] !== null ? format_number((int) $p['km']) : '—') ?></td>
                    <td class="nowrap"><?= e(format_tl($p['price'] !== null ? (int) $p['price'] : null)) ?></td>
                    <td><?= e((string) ($p['city'] ?? '—')) ?></td>
                  </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </div>
    <?php endif ?>
  </form>

  <form class="form-card" method="post" action="/yonetim/radar/ayarlar">
    <?= csrf_field() ?>
    <h2 class="form-card-title">2. Arama motoru taraması <small>(yan kaynak)</small></h2>
    <p class="muted small">
      Brave Search'ün resmî API'si ile arama motorunun dizinindeki ilan linkleri alınır. Dizine giren ilanlar sınırlı ve gecikmelidir;
      e-posta bildirimlerinin yerini tutmaz. Anahtar: <a href="https://api-dashboard.search.brave.com/" target="_blank" rel="noopener noreferrer">api-dashboard.search.brave.com</a>
      → "Search" planı (ayda yaklaşık 1.000 sorgu ücretsiz).
    </p>
    <label class="field">
      <span>API anahtarı <?= $apiKeySet ? '(kayıtlı — değiştirmek için yenisini yazın)' : '' ?></span>
      <input type="password" name="search_api_key" autocomplete="off" placeholder="<?= $apiKeySet ? '••••••••••••' : 'BSA...' ?>">
    </label>
    <?php if ($apiKeySet): ?><label class="check"><input type="checkbox" name="search_api_key_clear" value="1"><span>Kayıtlı anahtarı sil</span></label><?php endif ?>
    <label class="check"><input type="checkbox" name="search_enabled" value="1"<?= $enabled ? ' checked' : '' ?>><span>Otomatik taramayı aç</span></label>
    <label class="field">
      <span>Arama sorguları (her satıra bir tane; her taramada sırayla en fazla 10'u çalışır)</span>
      <textarea name="search_queries" rows="8"><?= e($queries) ?></textarea>
    </label>
    <div class="grid-2">
      <label class="field"><span>Ne kadar yeni ilanlar?</span>
        <select name="search_freshness">
          <option value="pd"<?= selected($freshness, 'pd') ?>>Son 24 saat</option>
          <option value="pw"<?= selected($freshness, 'pw') ?>>Son 1 hafta</option>
          <option value="pm"<?= selected($freshness, 'pm') ?>>Son 1 ay</option>
        </select>
      </label>
      <label class="field"><span>En sık kaç dakikada bir? (kredi tasarrufu)</span><input type="text" name="search_interval" value="<?= $interval ?>" inputmode="numeric"></label>
    </div>
    <button class="btn btn-primary" type="submit">Kaydet</button>
  </form>

  <section class="form-card">
    <h2 class="form-card-title">Zamanlanmış görev (Plesk)</h2>
    <p class="muted small">Plesk → ozoto.online → <strong>Scheduled Tasks → Add Task → "Fetch a URL"</strong>, aşağıdaki adresi yapıştırın,
      sıklığı <strong>her 5 dakika</strong> yapın (cron: <code>*/5 * * * *</code>). E-postalar her çalışmada, arama motoru yukarıdaki aralıkta çalışır.
      Bu adres gizlidir, paylaşmayın.</p>
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
          <li><span><?= e(format_date((string) $run['started_at'])) ?> · <?= e($sourceNames[$run['source']] ?? $run['source']) ?></span><span><?= e((string) $run['message']) ?></span></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>
</div>
