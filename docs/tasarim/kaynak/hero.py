# Ana sayfa hero çizimi: arac.py'deki sedan + ekspertiz işaretleri + ölçü çizgileri.
# Çıktı: templates/partials/hero-cizim.php (çalıştırma: python3 hero.py)
import math
import os

from arac import (FW, RW, R_TIRE, R_RIM, WY, GROUND, Y, body, dlo, bpillar, cpillar, belt, shoulder, sill,
                  door1, door2, door3, head, tail, mirror, handle1, handle2, grille, lower, rearlow)

X0, X1 = 0, 5000                 # viewBox (mm)
Y0, Y1 = -440, 2030
W, H = X1 - X0, Y1 - Y0
MY = -230                        # işaret dairelerinin merkezi
D1, D2 = GROUND + 170, GROUND + 330
FRONT, REAR = 152, 4676


def px(x):
    return f'{(x - X0) / W * 100:.2f}%'


def py(y):
    return f'{(y - Y0) / H * 100:.2f}%'


def circle(cx, cy, r):
    return f'M {cx - r:.1f} {cy:.1f} a {r} {r} 0 1 0 {2 * r} 0 a {r} {r} 0 1 0 {-2 * r} 0'


def line(x1, y1, x2, y2):
    return f'M {x1:.1f} {y1:.1f} L {x2:.1f} {y2:.1f}'


def clean(d):
    return ' '.join(d.split())


paths = []                       # (sınıf, d, gecikme sn)


def add(cls, d, delay):
    paths.append((cls, clean(d), delay))


# Gövde önce, ayrıntılar arkasından çizilir
add('a', body, 0)
add('a', dlo, .35)
for d in (bpillar, door1, door2, door3):
    add('a', d, .6)
add('a f', cpillar, .7)
for d in (belt, shoulder, sill):
    add('a f', d, .8)
for d in (head, tail, mirror, handle1, handle2):
    add('a', d, .95)
for d in (grille, lower, rearlow):
    add('a f', d, 1.0)
for c in (FW, RW):
    add('a f', f'M {c - 359} {Y(150)} A 396 396 0 1 1 {c + 359} {Y(150)}', .9)


def wheel(cx, t):
    add('a', circle(cx, WY, R_TIRE), t)
    add('a', circle(cx, WY, R_RIM), t + .1)
    add('a f', circle(cx, WY, R_TIRE - 28), t + .1)
    add('a f', circle(cx, WY, R_RIM - 22), t + .15)
    add('a', circle(cx, WY, 44), t + .2)
    spokes = []
    for k in range(5):
        for dd in (-7, 7):
            a = math.radians(-90 + k * 72 + dd)
            b = math.radians(-90 + k * 72 + dd * .35)
            spokes.append(line(cx + 64 * math.cos(b), WY + 64 * math.sin(b),
                               cx + (R_RIM - 26) * math.cos(a), WY + (R_RIM - 26) * math.sin(a)))
        a = math.radians(-90 + 36 + k * 72)
        spokes.append(circle(round(cx + 30 * math.cos(a), 1), round(WY + 30 * math.sin(a), 1), 6))
    add('a f', ' '.join(spokes), t + .25)


wheel(FW, .5)
wheel(RW, .6)

# Zemin, ölçü ve yardımcı çizgiler
add('z', line(40, GROUND, 4960, GROUND), .2)
dims = []
for x in (FRONT, REAR):
    dims.append(('o', line(x, Y(150) + 40, x, D1 + 50)))
for x in (FW, RW):
    dims.append(('o eksen d2', line(x, WY - R_TIRE - 100, x, D2 + 50)))


def dim(x1, x2, y, extra=''):
    t = 26
    return [(f'o{extra}', line(x1, y, x2, y)),
            (f'o{extra}', line(x1 - t, y + t, x1 + t, y - t) + ' ' + line(x2 - t, y + t, x2 + t, y - t))]


dims += dim(FRONT, REAR, D1) + dim(FW, RW, D2, ' d2')

# Ekspertiz noktaları: eşit aralıklı, soldan sağa okuma sırasıyla
xs = [FW + i * (4150 - FW) / 3 for i in range(4)]
points = [
    ('01', 'Kilometre', xs[0], Y(R_TIRE * 2)),   # lastik sırtı
    ('02', 'Boya', xs[1], Y(600)),               # ön kapı
    ('03', 'Değişen', xs[2], Y(600)),            # arka kapı
    ('04', 'Tramer', xs[3], Y(780)),             # arka çamurluk
]
leaders = ' '.join(line(x, MY, x, y) for _, _, x, y in points)
dots = ' '.join(circle(round(x, 1), y, 18) for _, _, x, y in points)

svg = [f'<svg class="cizim-svg" viewBox="{X0} {Y0} {W} {H}" focusable="false">']
for cls, d in dims:
    # Kesikli eksen çizgilerinde pathLength kesik aralığını bozar; onlar çizilmek yerine belirir.
    svg.append(f'<path class="{cls}" d="{d}"/>' if 'eksen' in cls else f'<path class="{cls}" d="{d}" pathLength="1"/>')
svg.append(f'<path class="o kilavuz" d="{leaders}"/>')
# Site CSP'si satır içi stile izin vermez: konumlar ve gecikmeler sınıflarla, CSS ise site.css'teki üretilmiş bölgeyle verilir.
delays = sorted({t for _, _, t in paths})
for cls, d, t in paths:
    svg.append(f'<path class="{cls} g{delays.index(t)}" d="{d}" pathLength="1"/>')
svg.append(f'<path class="nokta" d="{dots}"/>')
svg.append('</svg>')

html = ['<?php /* Üretildi: docs/tasarim/kaynak/hero.py — elle düzenlemeyin. */ ?>',
        '<div class="cizim" aria-hidden="true">', '\n'.join(svg)]
css = [f'.cizim-svg .g{i} {{ --d: {t:.2f}s; }}' for i, t in enumerate(delays)]
for i, (no, name, x, _) in enumerate(points, 1):
    html.append(f'<span class="isaret i{i}"><b>{no}</b><i>{name}</i></span>')
    css.append(f'.isaret.i{i} {{ left: {px(x)}; top: {py(MY)}; }}')
html.append('<span class="olcu-etiket u1"><i>Uzunluk</i> 4 532 <i>mm</i></span>')
html.append('<span class="olcu-etiket d2 u2"><i>Dingil</i> 2 640 <i>mm</i></span>')
css.append(f'.olcu-etiket.u1 {{ left: {px((FRONT + REAR) / 2)}; top: {py(D1)}; }}')
css.append(f'.olcu-etiket.u2 {{ left: {px((FW + RW) / 2)}; top: {py(D2)}; }}')
html.append("<div class=\"cizim-muhur\"><?= partial('muhur', ['id' => 'muhur-hero']) ?></div>")
css.append(f'.cizim-muhur {{ top: {py(-20)}; }}')
html.append('</div>')

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), '../../..')
out = os.path.normpath(os.path.join(root, 'templates/partials/hero-cizim.php'))
open(out, 'w').write('\n'.join(html) + '\n')

sheet = os.path.normpath(os.path.join(root, 'public/assets/css/site.css'))
start, end = '/* cizim:başla — hero.py üretir */', '/* cizim:bitir */'
text = open(sheet).read()
a, b = text.index(start), text.index(end)
open(sheet, 'w').write(text[:a] + start + '\n' + '\n'.join(css) + '\n' + text[b:])
print('yazıldı', out, sheet)
