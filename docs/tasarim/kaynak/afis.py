import re
from arac import *  # noqa: F401,F403
inner = re.search(r'<svg[^>]*>(.*)</svg>', svg, re.S).group(1).replace('<style>.faint{opacity:.45}</style>', '')

W, H = 2400, 1500
FR = 120                         # çerçeve
ML = 170                         # metin kenar boşluğu
S = 0.34                         # mm → px
GY = 1060                        # zemin çizgisi
CX, CY = 330, GY - GROUND*S
gx = lambda x: round(CX + x*S, 1)
gy = lambda y: round(CY + y*S, 1)
far, sis, serit, gece, nakit = '#ecefee', '#8b949b', '#1c2329', '#07090b', '#7fd1a9'

def dim_h(x1, x2, y, label, sub):
    t = 9
    w = 22 * 0.62 * len(label) + 40
    return f'''<g class="dim"><line x1="{x1}" y1="{y}" x2="{x2}" y2="{y}"/>
<line x1="{x1-6}" y1="{y+6}" x2="{x1+6}" y2="{y-6}"/><line x1="{x2-6}" y1="{y+6}" x2="{x2+6}" y2="{y-6}"/></g>
<rect x="{(x1+x2)/2-w/2}" y="{y-14}" width="{w}" height="28" fill="{gece}"/>
<text class="num" x="{(x1+x2)/2}" y="{y+8}" text-anchor="middle">{label}</text>
<text class="lab" x="{(x1+x2)/2}" y="{y+38}" text-anchor="middle">{sub}</text>'''

def dim_v(x, y1, y2, label, sub):
    mid = (y1+y2)/2
    w = 22 * 0.62 * len(label) + 40
    return f'''<g class="dim"><line x1="{x}" y1="{y1}" x2="{x}" y2="{y2}"/>
<line x1="{x-6}" y1="{y1+6}" x2="{x+6}" y2="{y1-6}"/><line x1="{x-6}" y1="{y2+6}" x2="{x+6}" y2="{y2-6}"/></g>
<rect x="{x-14}" y="{mid-w/2}" width="28" height="{w}" fill="{gece}"/>
<text class="num" transform="translate({x+8} {mid}) rotate(-90)" text-anchor="middle">{label}</text>
<text class="lab" transform="translate({x+44} {mid}) rotate(-90)" text-anchor="middle">{sub}</text>'''

ground_y = GY
roof_y = gy(Y(1472))
front_x, rear_x = gx(152), gx(4676)
D1, D2 = GY + 70, GY + 150       # toplam uzunluk, dingil mesafesi
VX = rear_x + 118                 # yükseklik ölçüsü

# Ekspertiz noktaları: soldan sağa eşit aralıklı, okuma sırasıyla numaralı
MY = 360
lx = [gx(FW), None, None, gx(4250)]
step = (lx[3] - lx[0]) / 3
lx = [round(lx[0] + i*step, 1) for i in range(4)]
pts = [
    ('01', 'KİLOMETRE', FW, Y(R_TIRE*2)),                  # lastik sırtı
    ('02', 'BOYA', (lx[1]-CX)/S, Y(600)),                  # ön kapı
    ('03', 'DEĞİŞEN', (lx[2]-CX)/S, Y(600)),               # arka kapı
    ('04', 'TRAMER', 4250, Y(780)),                        # arka çamurluk
]
markers = []
for (no, name, px, py), x in zip(pts, lx):
    y = gy(py)
    markers.append(f'<line class="lead" x1="{x}" y1="{MY+21}" x2="{x}" y2="{y}"/><circle class="pt" cx="{x}" cy="{y}" r="4.5"/>'
                   f'<circle class="mk" cx="{x}" cy="{MY}" r="21"/><text class="mkn" x="{x}" y="{MY+5.5}" text-anchor="middle">{no}</text>'
                   f'<text class="lab" x="{x}" y="{MY-42}" text-anchor="middle">{name}</text>')

# Mühür: sağ kenar boşluğuna hizalı, künye bloğunun altında
sr = 112
sx, sy = W - ML - sr, 398
rt = 81                            # yazı taban çizgisi yarıçapı
seal = f'''<g transform="rotate(-11 {sx} {sy})" class="seal">
<circle cx="{sx}" cy="{sy}" r="{sr}"/><circle cx="{sx}" cy="{sy}" r="{sr-8}" class="thin"/><circle cx="{sx}" cy="{sy}" r="70" class="thin"/>
<path id="ring" d="M {sx-rt} {sy} a {rt} {rt} 0 1 1 {2*rt} 0 a {rt} {rt} 0 1 1 {-2*rt} 0" fill="none" stroke="none"/>
<text class="ringt" data-r="{rt}"><textPath href="#ring">NOTERDE DEVİR · AYNI GÜN NAKİT · </textPath></text>
<text class="sealc" x="{sx}" y="{sy+3}" text-anchor="middle">ÖDENDİ</text>
<line class="thin" x1="{sx-30}" y1="{sy+16}" x2="{sx+30}" y2="{sy+16}"/>
<text class="sealt" x="{sx}" y="{sy+36}" text-anchor="middle">05·10·2026</text>
</g>'''

ext = (f'<line class="ext" x1="{front_x}" y1="{gy(Y(150))+12}" x2="{front_x}" y2="{D1+14}"/>'
       f'<line class="ext" x1="{rear_x}" y1="{gy(Y(150))+12}" x2="{rear_x}" y2="{D1+14}"/>'
       f'<line class="ext" x1="{gx(3170)}" y1="{roof_y}" x2="{VX+14}" y2="{roof_y}"/>'
       f'<line class="ground" x1="{gx(0)}" y1="{GY}" x2="{VX+14}" y2="{GY}"/>')
axes = ''.join(f'<line class="axis" x1="{gx(c)}" y1="{gy(WY)-R_TIRE*S-40}" x2="{gx(c)}" y2="{D2+14}"/>' for c in (FW, RW))
dims = dim_h(front_x, rear_x, D1, '4 532', 'TOPLAM UZUNLUK · MM') + dim_h(gx(FW), gx(RW), D2, '2 640', 'DİNGİL MESAFESİ · MM') + dim_v(VX, roof_y, GY, '1 472', 'YÜKSEKLİK · MM')

TOP, BOT = 188, H - 182          # künye ve iddia taban çizgileri (çerçeveye eşit uzaklık)
sc = ''.join(f'<line x1="{i*34}" y1="{-9 if i%5==0 else -4}" x2="{i*34}" y2="{9 if i%5==0 else 4}"/>' for i in range(11))
scale = f'''<g class="scale" transform="translate({W-ML-340} {BOT-30})"><line x1="0" y1="0" x2="340" y2="0"/>{sc}
<text class="lab" x="0" y="30">0</text><text class="lab" x="170" y="30" text-anchor="middle">0,5</text><text class="lab" x="340" y="30" text-anchor="end">1 M</text></g>'''

doc = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">
<defs>
<style>
@font-face {{ font-family: Archivo; src: url(archivo.woff2) format('woff2'); font-weight: 300 700; font-stretch: 100% 125%; }}
text {{ font-family: Archivo; fill: {far}; font-variant-numeric: tabular-nums; font-kerning: normal; }}
.word {{ font-size: 25px; font-weight: 600; font-stretch: 125%; letter-spacing: .34em; }}
.meta {{ font-size: 14px; font-weight: 500; font-stretch: 125%; letter-spacing: .3em; fill: {sis}; }}
.lab {{ font-size: 12.5px; font-weight: 500; font-stretch: 125%; letter-spacing: .3em; fill: {sis}; }}
.num {{ font-size: 22px; font-weight: 400; font-stretch: 110%; letter-spacing: .06em; }}
.mkn {{ font-size: 14.5px; font-weight: 500; font-stretch: 110%; letter-spacing: .04em; }}
.claim {{ font-size: 50px; font-weight: 300; font-stretch: 110%; letter-spacing: -.004em; }}
.claim.two {{ fill: {sis}; }}
.car path, .car circle, .car line {{ vector-effect: non-scaling-stroke; stroke-width: 1.6px; }}
.car .faint {{ opacity: .42; }}
.dim line {{ stroke: {sis}; stroke-width: 1.15; }}
.ext {{ stroke: {sis}; stroke-width: 1; opacity: .5; }}
.ground {{ stroke: {sis}; stroke-width: 1; opacity: .6; }}
.axis {{ stroke: {sis}; stroke-width: 1; stroke-dasharray: 2 7; opacity: .55; }}
.lead {{ stroke: {sis}; stroke-width: 1; opacity: .75; }}
.pt {{ fill: {far}; }}
.mk {{ fill: {gece}; stroke: {far}; stroke-width: 1.15; }}
.seal circle, .seal line {{ fill: none; stroke: {nakit}; stroke-width: 2.4; }}
.seal .thin {{ stroke-width: 1.1; }}
.seal text {{ fill: {nakit}; }}
.ringt {{ font-size: 13.5px; font-weight: 600; font-stretch: 125%; }}
.sealc {{ font-size: 21px; font-weight: 600; font-stretch: 125%; letter-spacing: .16em; }}
.sealt {{ font-size: 11.5px; font-weight: 500; font-stretch: 110%; letter-spacing: .16em; }}
.scale line {{ stroke: {sis}; stroke-width: 1.15; }}
</style>
<pattern id="minor" width="25" height="25" patternUnits="userSpaceOnUse" patternTransform="translate({FR} {FR})"><path d="M 25 0 L 0 0 0 25" fill="none" stroke="{far}" stroke-opacity=".026" stroke-width="1"/></pattern>
<pattern id="major" width="125" height="125" patternUnits="userSpaceOnUse" patternTransform="translate({FR} {FR})"><path d="M 125 0 L 0 0 0 125" fill="none" stroke="{far}" stroke-opacity=".05" stroke-width="1"/></pattern>
</defs>
<rect width="{W}" height="{H}" fill="{gece}"/>
<rect x="{FR}" y="{FR}" width="{W-2*FR}" height="{H-2*FR}" fill="url(#minor)"/>
<rect x="{FR}" y="{FR}" width="{W-2*FR}" height="{H-2*FR}" fill="url(#major)"/>
<rect x="{FR}" y="{FR}" width="{W-2*FR}" height="{H-2*FR}" fill="none" stroke="{serit}" stroke-width="1.2"/>

<text class="word" x="{ML}" y="{TOP}">ÖZ OTO</text>
<text class="meta" x="{ML}" y="{TOP+36}">DEĞERLEME ÇİZİMİ</text>
<text class="meta" x="{W-ML}" y="{TOP}" text-anchor="end">PAFTA 01 / 04</text>
<text class="meta" x="{W-ML}" y="{TOP+36}" text-anchor="end">C SEGMENT · SEDAN</text>

{ext}
{axes}
<g class="car" transform="translate({CX} {CY}) scale({S})" fill="none" stroke="{far}" stroke-linecap="round" stroke-linejoin="round">{inner}</g>
{''.join(markers)}
{dims}
{seal}

<text class="claim" x="{ML-3}" y="{BOT-64}">Kesin değer.</text>
<text class="claim two" x="{ML-3}" y="{BOT}">Aynı gün nakit.</text>
{scale}
</svg>'''
# Araç çizimindeki kendi zemin çizgisi posterde ayrı çizildi
doc = doc.replace(f'<line x1="0" y1="{GROUND}" x2="4840" y2="{GROUND}" class="faint"/>', '')
open('poster.svg','w').write(doc)
fit = '''<script>
document.fonts.ready.then(() => {
  for (const t of document.querySelectorAll('text[data-r]')) {
    const c = 2 * Math.PI * +t.dataset.r, s = t.querySelector('textPath').textContent, n = [...s].length;
    t.style.letterSpacing = '0px';
    const l0 = t.getComputedTextLength();
    t.style.letterSpacing = ((c - l0) / n).toFixed(3) + 'px';
    document.body.dataset.ls = ((c - l0) / n).toFixed(3);
  }
  document.body.dataset.ready = '1';
});
</script>'''
open('poster.html','w').write(f'<!doctype html><html><head><meta charset="utf-8"><style>html,body{{margin:0;background:{gece}}}svg{{display:block}}</style></head><body>{doc}{fit}</body></html>')
print('ok', sx, sy, lx)
