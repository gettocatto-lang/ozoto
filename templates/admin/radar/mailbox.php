<?php
/**
 * @var list<array{date: string, from: string, subject: string, snippet: string, links: list<array{text: string, url: string}>}> $messages
 * @var string|null $error
 * @var string $mailUser
 */
?>
<div class="container narrow-admin">
  <p><a href="/yonetim/radar/ayarlar#eposta">← Radar ayarları</a></p>
  <h1>Radar posta kutusu</h1>
  <p class="muted small"><?= e($mailUser) ?> kutusundaki son 10 e-posta, en yenisi üstte. Sitelere üye olurken gelen doğrulama linklerini ve kodlarını buradan açabilirsiniz.
    Bu sayfa e-postaları okundu yapmaz ve silmez.</p>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
  <?php if (!$error && $messages === []): ?><p class="muted">Kutu boş.</p><?php endif ?>

  <?php foreach ($messages as $message): ?>
    <section class="form-card mail-item">
      <h2 class="form-card-title"><?= e($message['subject'] ?: '(konu yok)') ?></h2>
      <p class="muted small"><?= e($message['from']) ?> · <?= e($message['date']) ?></p>
      <?php if ($message['snippet'] !== ''): ?><p class="small"><?= e($message['snippet']) ?></p><?php endif ?>
      <?php if ($message['links'] !== []): ?>
        <details>
          <summary class="small">Linkler (<?= count($message['links']) ?>)</summary>
          <ul class="mail-links">
            <?php foreach ($message['links'] as $link): ?>
              <li><?php if ($link['text'] !== ''): ?><strong><?= e($link['text']) ?></strong><br><?php endif ?>
                <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['url']) ?></a></li>
            <?php endforeach ?>
          </ul>
        </details>
      <?php endif ?>
    </section>
  <?php endforeach ?>
</div>
