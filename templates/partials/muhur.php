<?php
/**
 * Yeşil "ödendi" mührü. Tarih her gün kendini yeniler: aynı gün ödeme sözünün imzası.
 *
 * @var string $id benzersiz kimlik (aynı sayfada birden fazla mühür olabilir)
 * @var string|null $word ortadaki söz
 */
$word ??= 'ÖDENDİ';
$date = date('d·m·Y');
// Yazı tabanı r=81 olan çemberin çevresi; harf aralığı yazı tam bir tur dönecek şekilde ayarlanır.
$circumference = number_format(2 * M_PI * 81, 2, '.', '');
?>
<svg class="muhur" viewBox="0 0 240 240" role="img" aria-label="Noterde devir, aynı gün nakit">
  <g transform="rotate(-11 120 120)">
    <circle cx="120" cy="120" r="114" class="m-kalin"/>
    <circle cx="120" cy="120" r="106"/>
    <circle cx="120" cy="120" r="68"/>
    <path id="<?= e($id) ?>" d="M 39 120 a 81 81 0 1 1 162 0 a 81 81 0 1 1 -162 0" fill="none" stroke="none"/>
    <text class="m-halka"><textPath href="#<?= e($id) ?>" textLength="<?= $circumference ?>" lengthAdjust="spacing">NOTERDE DEVİR · AYNI GÜN NAKİT · </textPath></text>
    <text class="m-orta" x="120" y="125" text-anchor="middle"><?= e($word) ?></text>
    <path d="M 92 139 H 148"/>
    <text class="m-tarih" x="120" y="160" text-anchor="middle"><?= e($date) ?></text>
  </g>
</svg>
