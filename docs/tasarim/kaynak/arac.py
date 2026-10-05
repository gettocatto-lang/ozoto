# C segment sedan yan profili (mm; zemin y=1600, araç sola bakıyor). Doğrudan çalıştırılırsa arac.svg yazar.
GROUND = 1600
def Y(h): return GROUND - h          # yükseklikten SVG y'ye
FW, RW = 1050, 3690                   # ön/arka teker merkezi (dingil 2640)
R_TIRE, R_RIM = 316, 214
WY = Y(316)

body = f"""
M 205 {Y(150)}
C 168 {Y(170)} 150 {Y(250)} 152 {Y(330)}
L 158 {Y(560)}
C 160 {Y(640)} 175 {Y(700)} 215 {Y(725)}
C 420 {Y(760)} 900 {Y(800)} 1360 {Y(860)}
C 1560 {Y(890)} 1700 {Y(920)} 1785 {Y(950)}
C 1980 {Y(1110)} 2180 {Y(1330)} 2390 {Y(1432)}
C 2520 {Y(1468)} 2780 {Y(1472)} 3060 {Y(1462)}
C 3260 {Y(1450)} 3420 {Y(1400)} 3560 {Y(1310)}
C 3720 {Y(1190)} 3870 {Y(1060)} 3990 {Y(1012)}
C 4180 {Y(1000)} 4420 {Y(998)} 4560 {Y(985)}
C 4620 {Y(978)} 4655 {Y(955)} 4664 {Y(910)}
L 4676 {Y(640)}
C 4680 {Y(520)} 4672 {Y(330)} 4650 {Y(250)}
C 4635 {Y(195)} 4605 {Y(160)} 4560 {Y(150)}
L {RW+324} {Y(150)}
A 364 364 0 1 0 {RW-324} {Y(150)}
L {FW+324} {Y(150)}
A 364 364 0 1 0 {FW-324} {Y(150)}
Z"""

# Cam alanı (DLO): kemer hattı ve tavanın içinden
dlo = f"""
M 1905 {Y(965)}
C 2060 {Y(1110)} 2230 {Y(1290)} 2410 {Y(1378)}
C 2560 {Y(1418)} 2800 {Y(1424)} 3050 {Y(1414)}
C 3230 {Y(1402)} 3370 {Y(1360)} 3470 {Y(1290)}
C 3560 {Y(1225)} 3650 {Y(1130)} 3700 {Y(1070)}
C 3500 {Y(1045)} 3100 {Y(1020)} 2700 {Y(1003)}
C 2400 {Y(992)} 2100 {Y(978)} 1905 {Y(965)}
Z"""
bpillar = f"M 2905 {Y(1416)} L 2862 {Y(1005)}"
cpillar = f"M 3330 {Y(1372)} C 3380 {Y(1260)} 3420 {Y(1150)} 3438 {Y(1040)}"
belt = f"M 1795 {Y(955)} C 2300 {Y(985)} 3200 {Y(1030)} 3990 {Y(1013)}"
shoulder = f"M 330 {Y(770)} C 1200 {Y(830)} 2600 {Y(880)} 4560 {Y(905)}"
sill = f"M {FW+352} {Y(262)} C 2000 {Y(250)} 3000 {Y(250)} {RW-352} {Y(262)}"
door1 = f"M 1880 {Y(948)} C 1860 {Y(800)} 1745 {Y(690)} 1722 {Y(560)} L 1714 {Y(262)}"
door2 = f"M 2862 {Y(1002)} L 2875 {Y(258)}"
door3 = f"M 3655 {Y(1062)} C 3664 {Y(930)} 3640 {Y(790)} 3566 {Y(712)}"
head = f"M 222 {Y(718)} C 420 {Y(745)} 560 {Y(762)} 640 {Y(772)} C 600 {Y(700)} 470 {Y(660)} 300 {Y(648)} C 250 {Y(650)} 225 {Y(680)} 222 {Y(718)} Z"
tail = f"M 4665 {Y(905)} C 4560 {Y(915)} 4430 {Y(920)} 4360 {Y(918)} C 4400 {Y(880)} 4520 {Y(845)} 4672 {Y(835)}"
mirror = f"M 1960 {Y(1000)} C 1930 {Y(1060)} 1990 {Y(1100)} 2060 {Y(1090)} C 2080 {Y(1050)} 2050 {Y(1005)} 1960 {Y(1000)} Z"
handle1 = f"M 2560 {Y(860)} L 2690 {Y(864)}"
handle2 = f"M 3270 {Y(878)} L 3390 {Y(880)}"
grille = f"M 168 {Y(470)} C 260 {Y(480)} 420 {Y(490)} 560 {Y(500)}"
lower = f"M 175 {Y(300)} C 300 {Y(290)} 500 {Y(285)} 640 {Y(282)}"
rearlow = f"M 4655 {Y(320)} C 4560 {Y(305)} 4440 {Y(300)} 4300 {Y(300)}"

def wheel(cx):
    import math
    parts = [f'<circle cx="{cx}" cy="{WY}" r="{R_TIRE}"/>', f'<circle cx="{cx}" cy="{WY}" r="{R_RIM}"/>',
             f'<circle cx="{cx}" cy="{WY}" r="{R_RIM-22}" class="faint"/>', f'<circle cx="{cx}" cy="{WY}" r="44"/>', f'<circle class="faint" cx="{cx}" cy="{WY}" r="{R_TIRE-28}"/>']
    for k in range(5):
        for d in (-7, 7):
            a = math.radians(-90 + k*72 + d)
            b = math.radians(-90 + k*72 + d*0.35)
            x1, y1 = cx + 64*math.cos(b), WY + 64*math.sin(b)
            x2, y2 = cx + (R_RIM-26)*math.cos(a), WY + (R_RIM-26)*math.sin(a)
            parts.append(f'<line class="faint" x1="{x1:.1f}" y1="{y1:.1f}" x2="{x2:.1f}" y2="{y2:.1f}"/>')
        a = math.radians(-90 + 36 + k*72)
        parts.append(f'<circle class="faint" cx="{cx + 30*math.cos(a):.1f}" cy="{WY + 30*math.sin(a):.1f}" r="6"/>')
    return '\n'.join(parts)

arch = lambda cx: f'<path class="faint" d="M {cx-359} {Y(150)} A 396 396 0 1 1 {cx+359} {Y(150)}"/>'

svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4840 1700" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
<style>.faint{{opacity:.45}}</style>
<path d="{body}"/>
<path d="{dlo}"/>
<path d="{bpillar}"/><path d="{cpillar}" class="faint"/>
<path d="{belt}" class="faint"/><path d="{shoulder}" class="faint"/><path d="{sill}" class="faint"/>
<path d="{door1}"/><path d="{door2}"/><path d="{door3}"/>
<path d="{head}"/><path d="{tail}"/><path d="{mirror}"/>
<path d="{handle1}"/><path d="{handle2}"/>
<path d="{grille}" class="faint"/><path d="{lower}" class="faint"/><path d="{rearlow}" class="faint"/>
{arch(FW)}{arch(RW)}
{wheel(FW)}
{wheel(RW)}
<line x1="0" y1="{GROUND}" x2="4840" y2="{GROUND}" class="faint"/>
</svg>'''
if __name__ == '__main__':
    open('arac.svg', 'w').write(svg)
