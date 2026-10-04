<?php
/**
 * @var list<array{q: string, a: string}> $faqs
 * @var list<string> $brands
 * @var list<int> $years
 */
?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <p class="eyebrow"><?= icon('bolt') ?>Acil satışlarda hızlı çözüm</p>
      <h1>Acil satılık aracınız <span class="hl">anında nakite</span> dönüşsün</h1>
      <p class="lead">İlan vermekle, pazarlıkla, gelip giden alıcılarla uğraşmayın. Formu 2 dakikada doldurun; uzmanımız sizi arasın ve aracınıza net bir teklif versin.</p>
      <ul class="hero-points">
        <li><?= icon('check') ?>Ücretsiz ve bağlayıcı olmayan teklif</li>
        <li><?= icon('check') ?>Hasarlı, boyalı, yüksek kilometreli araçlar dahil</li>
        <li><?= icon('check') ?>Noterde resmi satış, ödemeniz hemen hesabınızda</li>
      </ul>
    </div>

    <form class="quick-card" action="/aracimi-hemen-sat" method="get">
      <h2 class="quick-title">Aracınıza teklif alın</h2>
      <p class="quick-sub">Markayı ve yılı seçin, kalan bilgileri bir sonraki adımda alalım.</p>
      <label class="field">
        <span>Marka</span>
        <select name="marka" required>
          <option value="">Seçin</option>
          <?php foreach ($brands as $brand): ?>
            <option value="<?= e($brand) ?>"><?= e($brand) ?></option>
          <?php endforeach ?>
        </select>
      </label>
      <label class="field">
        <span>Model yılı</span>
        <select name="yil" required>
          <option value="">Seçin</option>
          <?php foreach ($years as $year): ?>
            <option value="<?= $year ?>"><?= $year ?></option>
          <?php endforeach ?>
        </select>
      </label>
      <button class="btn btn-accent btn-block btn-lg" type="submit">Hemen Teklif Al<?= icon('arrow') ?></button>
      <p class="quick-note"><?= icon('shield') ?>Bilgileriniz yalnızca teklif için kullanılır.</p>
    </form>
  </div>
</section>

<section class="trust-strip" aria-label="Öne çıkanlar">
  <div class="container trust-grid">
    <div class="trust-item"><?= icon('clock') ?><div><strong>Hızlı dönüş</strong><span>Acil ihtiyaçlara öncelik</span></div></div>
    <div class="trust-item"><?= icon('tag') ?><div><strong>Net teklif</strong><span>Piyasa verisine dayalı fiyat</span></div></div>
    <div class="trust-item"><?= icon('shield') ?><div><strong>Güvenli satış</strong><span>Noterde resmi devir</span></div></div>
    <div class="trust-item"><?= icon('car') ?><div><strong>81 il</strong><span>Türkiye'nin her yerinden</span></div></div>
  </div>
</section>

<section class="section" id="nasil-calisir">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow eyebrow-dark">3 adımda satış</p>
      <h2>Aracınızı nakite çevirmek bu kadar kolay</h2>
    </div>
    <ol class="steps">
      <li class="step">
        <span class="step-no">1</span>
        <h3>Formu doldurun</h3>
        <p>Aracınızın marka, model, kilometre ve durum bilgilerini girin, varsa fotoğraf ekleyin. Sadece 2 dakika sürer.</p>
      </li>
      <li class="step">
        <span class="step-no">2</span>
        <h3>Teklifinizi alın</h3>
        <p>Uzmanımız sizi arar, aracınızı piyasa verileriyle değerlendirir ve size net bir fiyat söyler.</p>
      </li>
      <li class="step">
        <span class="step-no">3</span>
        <h3>Satış ve ödeme</h3>
        <p>Teklifi kabul ederseniz satış noterde yapılır, ödemeniz satış anında hesabınıza geçer.</p>
      </li>
    </ol>
    <div class="center">
      <a class="btn btn-primary btn-lg" href="/aracimi-hemen-sat">Ücretsiz teklif al<?= icon('arrow') ?></a>
    </div>
  </div>
</section>

<section class="section section-alt" id="neden-ozoto">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow eyebrow-dark">Neden Ozoto?</p>
      <h2>Aracınızı satarken zaman ve para kaybetmeyin</h2>
    </div>
    <div class="features">
      <article class="feature">
        <span class="feature-icon"><?= icon('bolt') ?></span>
        <h3>Acil durumlar için tasarlandı</h3>
        <p>Borç, taşınma, yeni araç alımı… Nakde hızlı ihtiyacınız olduğunda ilan bekleme süresini ortadan kaldırıyoruz.</p>
      </article>
      <article class="feature">
        <span class="feature-icon"><?= icon('tag') ?></span>
        <h3>Şeffaf fiyatlandırma</h3>
        <p>Teklifimizi marka, model, yıl, kilometre ve hasar durumuna göre güncel piyasa verileriyle hesaplıyoruz.</p>
      </article>
      <article class="feature">
        <span class="feature-icon"><?= icon('car') ?></span>
        <h3>Her durumdaki araç</h3>
        <p>Hatasız, boyalı, değişenli, ağır hasar kayıtlı veya yüksek kilometreli; tüm marka ve modeller için teklif veriyoruz.</p>
      </article>
      <article class="feature">
        <span class="feature-icon"><?= icon('shield') ?></span>
        <h3>Güvenli ve resmi süreç</h3>
        <p>Satış noterde resmi devirle yapılır. Kapora, aracı ya da sürpriz kesinti yok.</p>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container radar">
    <div class="radar-icon"><?= icon('radar') ?></div>
    <div>
      <p class="eyebrow eyebrow-dark">Çok yakında</p>
      <h2>Kelepir Radar</h2>
      <p>Türkiye genelindeki araç ilanlarını tarayıp piyasa değerinin altındaki fırsat araçları sizin için bulan akıllı sistemimiz geliyor. Her ilana kelepir puanı, fiyat geçmişi ve şüpheli ilan uyarısı.</p>
    </div>
  </div>
</section>

<section class="section section-alt" id="sss">
  <div class="container narrow">
    <div class="section-head">
      <p class="eyebrow eyebrow-dark">Sık sorulan sorular</p>
      <h2>Merak edilenler</h2>
    </div>
    <div class="faq">
      <?php foreach ($faqs as $i => $faq): ?>
        <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($faq['q']) ?></summary>
          <p><?= e($faq['a']) ?></p>
        </details>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="container cta-inner">
    <div>
      <h2>Aracınızı bugün nakite çevirin</h2>
      <p>Teklif almak ücretsiz, 2 dakikanızı alır.</p>
    </div>
    <a class="btn btn-accent btn-lg" href="/aracimi-hemen-sat">Hemen Teklif Al<?= icon('arrow') ?></a>
  </div>
</section>
