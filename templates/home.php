<?php
/**
 * @var list<array{q: string, a: string}> $faqs
 */
use Ozoto\Support\Phone;

$sitePhone = (string) config('site.phone');
$telHref = $sitePhone !== '' ? 'tel:' . (Phone::normalize($sitePhone) ?? $sitePhone) : '';
?>
<section class="hero">
  <div class="kap">
    <div class="hero-ust">
      <div>
        <p class="etiket">Acil satılık araç alımı · 81 il</p>
        <h1>Net teklif.<br><span>Aynı gün nakit.</span></h1>
      </div>
      <div class="hero-yan">
        <p class="giris">İlan, pazarlık, gelip giden alıcı yok. Kilometreye, boya-değişen parçalara ve tramer kaydına tek tek bakıp net bir fiyat söylüyoruz. Kabul ederseniz devir noterde, ödeme aynı gün.</p>
        <div class="eylem">
          <a class="btn btn-nakit btn-buyuk" href="/aracimi-hemen-sat">Teklif al<?= icon('ok') ?></a>
          <?php if ($telHref !== ''): ?>
            <a class="btn-metin" href="<?= e($telHref) ?>">ya da arayın <b><?= e($sitePhone) ?></b></a>
          <?php endif ?>
        </div>
        <p class="hero-not">Ücretsiz · Bağlayıcı değil · 2 dakika</p>
      </div>
    </div>
    <?= partial('hero-cizim') ?>
  </div>
</section>

<section class="bolum" id="degerleme">
  <div class="kap">
    <header class="bolum-bas">
      <p class="etiket">Değerleme</p>
      <h2>Fiyatı dört şey belirler.</h2>
      <p>Teklifimizi tahminle değil, aracınızın kaydıyla hesaplıyoruz. Formda ne kadar doğru bilgi verirseniz fiyat o kadar kesinleşir.</p>
    </header>
    <ol class="olcutler">
      <li>
        <span class="no">01</span>
        <h3>Kilometre</h3>
        <p>Aynı yaş ve modeldeki araçların ortalamasıyla karşılaştırılır. Servis kaydı varsa teklif ona göre netleşir.</p>
      </li>
      <li>
        <span class="no">02</span>
        <h3>Boya</h3>
        <p>Hangi parçanın boyalı ya da lokal boyalı olduğu tek tek ele alınır. Tavandaki işlem ayrıca değerlendirilir.</p>
      </li>
      <li>
        <span class="no">03</span>
        <h3>Değişen</h3>
        <p>Değişen parçanın kaput mu, kapı mı, yoksa taşıyıcı aksam mı olduğu fiyatı belirleyen ayrıntıdır.</p>
      </li>
      <li>
        <span class="no">04</span>
        <h3>Tramer</h3>
        <p>Hasar kaydının tutarı aracın piyasa değeriyle oranlanır. Kayıt yüksek olsa da teklif veririz.</p>
      </li>
    </ol>
    <p class="dipnot"><strong>Her durumdaki araca teklif veriyoruz:</strong> hatasız, boyalı, değişenli, ağır hasar kayıtlı ya da yüksek kilometreli.</p>
  </div>
</section>

<section class="bolum" id="surec">
  <div class="kap">
    <header class="bolum-bas">
      <p class="etiket">Süreç</p>
      <h2>Üç adım, tek gün.</h2>
      <p>Acil satışta en pahalı şey beklemek. Süreci ilan vermekten kısa tutuyoruz.</p>
    </header>
    <ol class="zaman">
      <li>
        <span class="zaman-sure">2 dakika</span>
        <h3><span class="no">01</span>Başvuru</h3>
        <p>Formu doldurun. Aracın bilgileri ve iletişim numaranız yeterli; fotoğraf eklemek isteğe bağlı.</p>
      </li>
      <li>
        <span class="zaman-sure">Aynı gün</span>
        <h3><span class="no">02</span>Teklif</h3>
        <p>Uzmanımız sizi arar, aracınızı sizinle konuşur ve net bir fiyat söyler. Teklif sizi bağlamaz.</p>
      </li>
      <li>
        <span class="zaman-sure">Devir anında</span>
        <h3><span class="no">03</span>Noter ve ödeme</h3>
        <p>Kabul ederseniz devir noterde yapılır, ödemeniz devir anında hesabınıza geçer.</p>
        <span class="zaman-uc" aria-hidden="true"></span>
      </li>
    </ol>
    <ul class="guvence">
      <li>
        <h3>Noterde resmi devir</h3>
        <p>Satış iki tarafın imzasıyla, noter huzurunda yapılır.</p>
      </li>
      <li>
        <h3>Kapora yok</h3>
        <p>Hiçbir aşamada sizden ön ödeme ya da kapora istemeyiz.</p>
      </li>
      <li>
        <h3>Ücretsiz teklif</h3>
        <p>Teklif almak ücretsizdir; beğenmezseniz satmak zorunda değilsiniz.</p>
      </li>
      <li>
        <h3>Türkiye geneli</h3>
        <p>81 ilden başvuru alıyoruz; aracın bulunduğu ili seçmeniz yeterli.</p>
      </li>
    </ul>
  </div>
</section>

<section class="bolum" id="sss">
  <div class="kap sss">
    <header class="sss-bas">
      <p class="etiket">Sorular</p>
      <h2>Merak edilenler</h2>
      <?php if ($telHref !== ''): ?>
        <p>Cevabını burada bulamadığınız bir soru için arayın: <a href="<?= e($telHref) ?>"><?= e($sitePhone) ?></a></p>
      <?php else: ?>
        <p>Cevabını burada bulamadığınız sorular için formdaki not alanına yazabilirsiniz.</p>
      <?php endif ?>
    </header>
    <div>
      <?php foreach ($faqs as $faq): ?>
        <details class="soru">
          <summary><?= e($faq['q']) ?></summary>
          <p><?= e($faq['a']) ?></p>
        </details>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="kapanis">
  <div class="kap kapanis-ic">
    <div>
      <p class="etiket">Başvuru</p>
      <h2>Aracınızı bugün<br>nakde çevirin.</h2>
      <p class="giris">Formu doldurun; uzmanımız aynı gün sizi arasın ve aracınıza net bir fiyat söylesin.</p>
      <div class="eylem">
        <a class="btn btn-nakit btn-buyuk" href="/aracimi-hemen-sat">Teklif al<?= icon('ok') ?></a>
        <?php if ($telHref !== ''): ?>
          <a class="btn-metin" href="<?= e($telHref) ?>">ya da arayın <b><?= e($sitePhone) ?></b></a>
        <?php endif ?>
      </div>
    </div>
    <div class="kapanis-muhur"><?= partial('muhur', ['id' => 'muhur-kapanis']) ?></div>
  </div>
</section>
