# Hotspot-first phasing: make ARPU-900 payback work

Homes total: 8061. Ranking = homes per design feeder-km (edge cost, see hotspot_priority.csv).
Cheap tier (top 61 DBs) covers 7475 homes (93%).

## Deferred tail (Phase-4, demand-triggered)
- DBs: 16 (POP1-C05, POP1-C06, POP2-C01, POP2-C15, POP2-C17, POP4-C05, POP4-C09, POP4-C10, POP4-C11, POP6-C03, POP7-C01, POP7-C02, POP7-C05, POP7-C06, POP7-C07, POP7-C10)
- Homes deferred: 586 (7.3%), feeder km deferred: 93.2
- Capex deferred: NPR 10.5M -> booked as Phase-4 at month 48 (or when take-rate proves demand)
- Capex in P1-P3 reduced proportionally to kept-home share (92.7%).

## Payback (NPR, indicative)
| Scenario | ARPU 900 | ARPU 1100 |
|--|--|--|
| Base phasing | month 67 | month 52 |
| Lean + Phase-4 tail | month 66 | month 47 |

## Top-10 hotspot DBs (build-first)
| Rank | DB | POP | Locality | Homes | Edge km | Homes/km |
|--|--|--|--|--:|--:|--:|
| 1 | HE-C01 | HE | Melamchi | 409 | 0.37 | 1105 |
| 2 | POP7-C04 | POP7 | Timbu | 90 | 0.01 | 300 |
| 3 | POP2-C06 | POP2 |  | 146 | 0.62 | 235 |
| 4 | POP1-C09 | POP1 | Gunsakot | 409 | 3.34 | 122 |
| 5 | POP5-C02 | POP5 | Dhaitar | 136 | 1.2 | 113 |
| 6 | HE-C02 | HE |  | 147 | 1.57 | 94 |
| 7 | HE-C07 | HE |  | 154 | 1.69 | 91 |
| 8 | POP2-C10 | POP2 |  | 206 | 2.31 | 89 |
| 9 | POP2-C07 | POP2 |  | 177 | 2.2 | 80 |
| 10 | POP7-C03 | POP7 | Sermathang | 142 | 1.8 | 79 |

## Recommendation
1. **Order phase**: POP4/POP5-side + HE + POP1/POP2 top-efficiency DBs first (P1 allocates homes/km, not geography).
2. **Trail spurs**: build only after `TRAIL_SURVEY.md` returns measured routes; hold their FDC+FAT hardware in Phase-4.
3. If survey finds a motorable alternate for a spur with <2x straight km, promote it back to its original phase.
4. Re-run `make_payback.py` after real quotes; every NPR 1M shaved ≈ 1-2 months payback at ARPU 900.
## What actually fixes ARPU-900 payback (sensitivity, 8-yr model)

| Scenario | Payback | Note |
|--|--|--|
| Base phasing, design take-rate | **month 67** (~5y7m) | stalls: EBITDA too thin for full early capex |
| Lean: 16-DB tail deferred to P4 | **month 66** (~5y6m) | cost trim alone ≈ 1 month gain |
| Lean + take-rate +20% | **month 55** (~4y7m) | avg subs ≈ 1.8k/4.2k/5.4k/6.0k/6.2k |
| Lean + ARPU 950 | **month 61** (~5y1m) | split-band pricing (basic NTC-fighting plan) |
| Lean + take +20% + ARPU 950 | **month 48** (~4y0m) | combined lever |
| Lean + ARPU 1000 | **month 57** (~4y9m) | upsell / OTT-bundle blended |

**Conclusion**: EBITDA levers (take-rate, ARPU per band) ≫ cost trimming. Defer the 16-DB tail to protect early cash (NPR ~10.5M out of P1-P3), but plan marketing + banded pricing from day one — only then does ARPU-900 break even inside ~5 years.
