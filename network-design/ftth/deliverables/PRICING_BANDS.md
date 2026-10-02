# 3-band pricing model (indicative - confirm against local competition)

## Band sheet
| Band | Up/Down | Price (NPR/mo) | Indicative COGS | Net margin (6% govt. up) | Equipment |
|--|--|--:|--:|--:|---|
| Basic 30M | 30/30 Mbps | 750 | 160 | 545 | 1GE |
| Standard 60M | 60/60 Mbps | 1,150 | 260 | 821 | 4GE WiFi 5G |
| Premium 100M | 100/100 Mbps | 1,600 | 400 | 1104 | 4GE WiFi ac + OTT (NetTV) |

## Band-mix scenarios (payback on lean phasing, 8-yr model, design take-rate)
| Mix (Basic/Std/Prem) | Blended ARPU | Blended net margin/sub | Payback |
|--|--:|--:|--|
| Basic-heavy (rural default) - 55/35/10% | 975 | 700 | **month 59** (~4y11m) |
| Balanced (target Y2) - 40/45/15% | 1,058 | 750 | **month 55** (~4y7m) |
| Upsell push (ott bundles) - 30/50/20% | 1,120 | 790 | **month 52** (~4y4m) |

## Operating guidance
1. Launch ALL bands day one (nobody sells the band you do not offer); advertise Standard as the headline - anchors Basic up.
2. Marginal Standard->Premium upgrade = +NPR ~283 margin/sub for zero extra pole-km: push speed-test + OTT bundles at billing month 6.
3. FiO (FIBER+IVR+OTT) bundles: voice via existing NTC interconnect SIP at POP2; OTT via NetTV/DISH Go resale margin NPR 80-120/sub.
4. Keep the 16 deferred tail DBs as monthly target review: once monthly-activated demand at a trailing DB passes ~40 paid commitments, trigger its P4 build - the trail survey returns what that costs.
5. Payback ceiling check vs competitor price floor (NTC FTTH ~NPR 750 baseline): if county price war pulls Basic below 650, run the make_payback.py stress case before shedding more early capex.

> All numbers indicative planning values; replace COGS with supplier quotes and book 10% marketing budget in Year-1 (noted in PAYBACK_MODEL.md not yet included).