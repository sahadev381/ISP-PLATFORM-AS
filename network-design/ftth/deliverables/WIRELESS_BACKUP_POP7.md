# POP7 (Timbu) Backup — SRTM Path Analysis (real terrain, 2026-10-02)

## Verdict
- **Direct HE &#8596; POP7 (14.1 km): NOT feasible.** Mid-path ridge (1751 m at km 6.3)
  blocks by ~624 m even with 60 m towers at both ends.
- Working wireless chain needs **4 hops with 3 relay sites** (solar-powered, no grid on ridges):

## Relay chain (solved from SRTM profile)
| Hop | From | To | km | Masts | Min clearance |
|-----|------|----|----|-------|---------------|
| 1 | HE (Melamchi 850 m) | R4 (1351 m) | 2.1 | 12/24 m | +1.5 m |
| 2 | Relay 4 (1351 m) | R7 (1542 m) | 1.6 | 24/12 m | +33.2 m |
| 3 | Relay 7 (1542 m) | R12 (1751 m) | 2.6 | 12/12 m | +46.6 m |
| 4 | Relay 12 (1751 m) | POP7 (Timbu 1382 m) | 7.8 | 12/24 m | +6.1 m |

Relay coordinates: R4(27.84795,85.57098), R7(27.86175,85.56797), R12(27.88476,85.56295)
Clearance model: earth bulge (K=4/3), 60% first Fresnel @5.8 GHz, 10 m vegetation. **Field walk survey MANDATORY** before ordering; shave margins with on-site height checks (drone/binocular flare test).

## Cost comparison for POP7 redundancy
| Option | Est. capex | Notes |
|--------|-----------|-------|
| 4-hop wireless relay | ~NPR 53 lakh | 3x solar relay (~13 lakh each incl. mast, shelter, 2 radios), land permission on forest ridgeline (hard), +30-60 m tower at HE |
| **POP2&#8596;POP7 road fiber ring (37.9 km)** | **~NPR 87 lakh** | closes the full mesh; carries extra villages en-route; no power/permit headaches (NEA poles along road) |

## Recommendation
Primary POP7 path = 24F spur from HE (in design, Phase 3).
For redundancy: **prefer the POP2-POP7 fiber ring-closure over multi-relay wireless** — similar money, better SLA, future-proof. Wireless chain only if ridge land comes free/quickly.
Per-hop radio budget at longest hop (7.8 km): FSPL 126 dB; 2x 30 dBi + 20 dBm TX &#8594; RSL -46 dBm &#8594; margin &#8776; 34 dB.
