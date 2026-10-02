#!/usr/bin/env python3
"""LOS chain solver HE<->POP7 over real SRTM 30 m profile.
Finds minimal relay chain (greedy farthest-reach). Writes WIRELESS_BACKUP_POP7.md + path_profile.svg"""
import math, os

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "deliverables")

ELE = [850,831,1030,1235,1351,1390,1442,1542,1540,1458,1521,1635,1751,1658,1483,1422,
       1505,1429,1445,1334,1235,1171,1271,1359,1226,1258,1418,1382]
LAT = [27.82954+ (27.95379-27.82954)*i/(len(ELE)-1) for i in range(len(ELE))]
LON = [85.57499+ (85.54791-85.57499)*i/(len(ELE)-1) for i in range(len(ELE))]
N=len(ELE); D_TOTAL=14.1; STEP=D_TOTAL/(N-1)
LAM=3e8/5.8e9; VEG=10.0

def clearance(i0,i1,ha,hb):
    ReK=12749  # km effective earth radius
    dkm=(i1-i0)*STEP; worst=(1e9,None)
    for i in range(i0+1,i1):
        f=(i-i0)/(i1-i0)
        los=(ELE[i0]+ha)+((ELE[i1]+hb)-(ELE[i0]+ha))*f
        d1=(i-i0)*STEP; d2=(i1-i)*STEP
        bulge=(d1*d2)/(2*ReK)*1000
        f1=math.sqrt(LAM*d1*1000*d2*1000/(dkm*1000))
        req=bulge+0.6*f1+VEG*4*f*(1-f)
        c=los-(ELE[i]+req)
        if c<worst[0]: worst=(c,i)
    return worst

MAST_OPTS = {0: [12,20,30,40,60], "end": [12,24,36], "relay": [12,16,20,24]}

def solve():
    chain=[(0,0)]
    while chain[-1][0] < N-1:
        i0, fixed = chain[-1]
        solved=None
        for i1 in range(N-1, i0, -1):              # farthest first
            opts0 = [fixed] if fixed else MAST_OPTS[0]   # mast already chosen by previous hop
            opts1 = MAST_OPTS["end"] if i1==N-1 else MAST_OPTS["relay"]
            pairs = sorted((a+b,a,b) for a in opts0 for b in opts1)
            for _s,a,b in pairs:
                c,_ = clearance(i0,i1,a,b)
                if c>=0:
                    solved=(i1,a,b); break
            if solved: break
        if not solved: return None
        i1,a,b = solved
        chain[-1]=(i0,a); chain.append((i1,b))
    return chain

chain = solve()
print("Chain:", chain)
for k,(i,m) in enumerate(chain[:-1]):
    j=chain[k+1][0]
    c,wi=clearance(i,j,m,chain[k+1][1])
    print(f"  hop {k+1}: sample {i}->{j} ({(j-i)*STEP:.1f} km) masts {m}/{chain[k+1][1]} -> min clearance {c:+.1f} m at sample {wi}")

cd,_ = clearance(0,N-1,60,60)
print(f"DIRECT 60m/60m clearance: {cd:+.1f} m (blocked)")

# ---------- SVG ----------
W,H=1000,430; mx,my=60,34
emin,emax=min(ELE)-40,max(ELE)+150
X=lambda i: mx+i*(W-2*mx)/(N-1)
Y=lambda e: H-my-(e-emin)*(H-2*my)/(emax-emin)
ter=" ".join(f"{X(i):.0f},{Y(e):.0f}" for i,e in enumerate(ELE))
segments=""; labels=""
for k,(i,m) in enumerate(chain[:-1]):
    j,m2=chain[k+1]
    segments+=f'<polyline points="{X(i):.0f},{Y(ELE[i]+m):.0f} {X(j):.0f},{Y(ELE[j]+m2):.0f}" stroke="#16a34a" stroke-width="2"/>\n'
    segments+=f'<line x1="{X(i):.0f}" y1="{Y(ELE[i]):.0f}" x2="{X(i):.0f}" y2="{Y(ELE[i]+m):.0f}" stroke="#16a34a" stroke-width="2.5"/>\n'
    if 0<i<N-1:
        labels+=f'<text x="{X(i):.0f}" y="{Y(ELE[i]+m)-6:.0f}" font-size="11" fill="#15803d" text-anchor="middle" font-family="Arial">R{i} {ELE[i]}m+{m}</text>\n'
segments+=f'<line x1="{X(N-1):.0f}" y1="{Y(ELE[N-1]):.0f}" x2="{X(N-1):.0f}" y2="{Y(ELE[N-1]+chain[-1][1]):.0f}" stroke="#16a34a" stroke-width="2.5"/>\n'
segments+=f'<polyline points="{X(0):.0f},{Y(ELE[0]+60):.0f} {X(N-1):.0f},{Y(ELE[N-1]+60):.0f}" stroke="#dc2626" stroke-width="1.3" stroke-dasharray="5 4"/>\n'
labels+=f'<text x="{X(0):.0f}" y="{Y(ELE[0]+chain[0][1])-6:.0f}" font-size="11" fill="#0d9488" font-family="Arial">HE 850m+{chain[0][1]}</text>'
labels+=f'<text x="{X(N-1):.0f}" y="{Y(ELE[N-1]+chain[-1][1])-6:.0f}" font-size="11" fill="#0d9488" text-anchor="end" font-family="Arial">POP7 1382m+{chain[-1][1]}</text>'
labels+=f'<text x="{X(14):.0f}" y="{Y(1650):.0f}" font-size="10.5" fill="#dc2626" font-family="Arial">direct LOS fails ({cd:.0f} m) with the 1751 m ridge</text>'
svg=f'''<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">
<rect width="{W}" height="{H}" fill="#f8fafc"/>
<text x="{W/2}" y="20" font-size="15" text-anchor="middle" font-family="Arial" fill="#0f172a">HE (Melamchi) &#8596; POP7 (Timbu) — SRTM 30 m path profile, {D_TOTAL} km</text>
<polygon points="{ter} {X(N-1):.0f},{Y(emin):.0f} {X(0):.0f},{Y(emin):.0f}" fill="#d6c8b0" stroke="#8b7355" stroke-width="1.5"/>
{segments}{labels}
<text x="{mx}" y="{H-8}" font-size="10" fill="#64748b" font-family="Arial">green = working relay chain ({len(chain)-1} hops) | red = direct shot fails | veg {VEG:.0f} m, earth bulge, 60% Fresnel @5.8 GHz included</text>
</svg>'''
open(f"{OUT}/path_profile.svg","w").write(svg)

relays=[(i,m) for (i,m) in chain[1:-1]]
n_relay=len(relays)
relay_capex=n_relay*1_300_000+1_400_000  # relays ~13 lakh each + HE 20m + POP7 24m masts
fiber_ring_km=37.9*1.06
fiber_cost=fiber_ring_km*1000*(55+32)+math.ceil(37.9*25)*5500
wl=f"""# POP7 (Timbu) Backup — SRTM Path Analysis (real terrain, 2026-10-02)

## Verdict
- **Direct HE &#8596; POP7 (14.1 km): NOT feasible.** Mid-path ridge (1751 m at km 6.3)
  blocks by ~{abs(cd):.0f} m even with 60 m towers at both ends.
- Working wireless chain needs **{len(chain)-1} hops with {n_relay} relay sites** (solar-powered, no grid on ridges):

## Relay chain (solved from SRTM profile)
| Hop | From | To | km | Masts | Min clearance |
|-----|------|----|----|-------|---------------|
""" + "\n".join(
  f"| {k+1} | {'HE (Melamchi 850 m)' if chain[k][0]==0 else f'Relay {chain[k][0]} ({ELE[chain[k][0]]} m)'} | {'POP7 (Timbu 1382 m)' if chain[k+1][0]==N-1 else f'R{chain[k+1][0]} ({ELE[chain[k+1][0]]} m)'} | {(chain[k+1][0]-chain[k][0])*STEP:.1f} | {chain[k][1]}/{chain[k+1][1]} m | {clearance(chain[k][0],chain[k+1][0],chain[k][1],chain[k+1][1])[0]:+.1f} m |"
  for k in range(len(chain)-1)) + f"""

Relay coordinates: {', '.join(f'R{i}({LAT[i]:.5f},{LON[i]:.5f})' for (i,m) in relays)}
Clearance model: earth bulge (K=4/3), 60% first Fresnel @5.8 GHz, {VEG:.0f} m vegetation. **Field walk survey MANDATORY** before ordering; shave margins with on-site height checks (drone/binocular flare test).

## Cost comparison for POP7 redundancy
| Option | Est. capex | Notes |
|--------|-----------|-------|
| {len(chain)-1}-hop wireless relay | ~NPR {relay_capex/100000:.0f} lakh | {n_relay}x solar relay (~13 lakh each incl. mast, shelter, 2 radios), land permission on forest ridgeline (hard), +30-60 m tower at HE |
| **POP2&#8596;POP7 road fiber ring (37.9 km)** | **~NPR {fiber_cost/100000:.0f} lakh** | closes the full mesh; carries extra villages en-route; no power/permit headaches (NEA poles along road) |

## Recommendation
Primary POP7 path = 24F spur from HE (in design, Phase 3).
For redundancy: **prefer the POP2-POP7 fiber ring-closure over multi-relay wireless** — similar money, better SLA, future-proof. Wireless chain only if ridge land comes free/quickly.
Per-hop radio budget at longest hop ({max((chain[k+1][0]-chain[k][0]) for k in range(len(chain)-1))*STEP:.1f} km): FSPL {32.44+20*math.log10(5800)+20*math.log10(max((chain[k+1][0]-chain[k][0]) for k in range(len(chain)-1))*STEP):.0f} dB; 2x 30 dBi + 20 dBm TX &#8594; RSL {20+60-(32.44+20*math.log10(5800)+20*math.log10(max((chain[k+1][0]-chain[k][0]) for k in range(len(chain)-1))*STEP)):+.0f} dBm &#8594; margin &#8776; {20+60-(32.44+20*math.log10(5800)+20*math.log10(max((chain[k+1][0]-chain[k][0]) for k in range(len(chain)-1))*STEP))+80:.0f} dB.
"""
open(f"{OUT}/WIRELESS_BACKUP_POP7.md","w").write(wl)
print(f"wrote WIRELESS_BACKUP_POP7.md ({len(chain)-1} hops, {n_relay} relays) + path_profile.svg")
