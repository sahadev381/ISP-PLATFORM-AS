# Fiber Core & Splice Plan (48F rings / 24F spur)

## Backbone core allocation (single 48F cable per ring; HE-POP2 shared segment runs TWO cables)
| Cores | Assignment |
|-------|-----------|
| 1-4 | Ring A transit + POP2 drop (2F/arm duplex uplinks) |
| 5-8 | Ring C transit + POP3/POP6 drops |
| 9-12 | Ring B transit + POP5 drop |
| 13-16 | Ring B return + POP4 drop |
| 17-20 | Ring A + POP1 drop |
| 21-24 | Reserved: future POP offshoot/town extension |
| 25-48 | Dark spare (expansion, enterprise/wholesale lease) |

## Splice closure matrix
| Location | Cable / arm | Cores dropped | Notes |
|----------|-------------|---------------|-------|
| HE ODF | Ring A east arm (to POP2) | 1-4 | POP2 drop/through — all 48 terminate on ODF |
| HE ODF | Ring C west arm (to POP3) | 5-8 | POP3 drop/through — separate cable recommended on shared HE-POP2 |
| HE ODF | Ring B south arm (to POP5) | 9-12 | POP5 drop/through —  |
| HE ODF | POP7 spur (24F cable) | 1-4 | POP7 — wireless = path protection |
| HE ODF | Spare dark | 13-48 | - — future expansion / enterprise lease |
| POP2 closure | Ring A: from HE / to POP1 | 1-4 | POP2 x4 drop (2 each arm) — 44F express, ~16 splices |
| POP2 closure | Ring C: from HE(2nd) / to POP6 | 5-8 | POP2 x4 drop — 44F express |
| POP1 closure | Ring A: from POP2 / back to HE | 1-8 | POP1 x4 drop (cores 3+7... see plan) — attenuation check C+ OK |
| POP5 closure | Ring B: from HE / to POP4 | 9-12 | POP5 x4 drop — 44F express |
| POP4 closure | Ring B: from POP5 / back to HE | 13-16 | POP4 x4 drop — 44F express |
| POP3 closure | Ring C: from POP2 / to HE side | 5-8 | POP3 x4 drop — 44F express |
| POP6 closure | Ring C: from POP3 / to POP2 | 5-8 alt | POP6 x4 drop — 44F express |
| POP7 closure | Spur from HE (24F) | 1-4 | POP7 x2 active + 2 spare — 20F dark spare |

## Field rules
- Splice loss target ≤0.05 dB per fusion joint; closure attenuation test OTDR both directions @1310/1550.
- Slack: 30 m at every closure, 60 m at POP closures; loop on pole above joint.
- HE-POP2 (5.85 km) gets TWO independent 48F cables (Ring A + Ring C both originate here) — core plan above is per-cable.
- POP7 spur: 24F; fibers 1-2 active duplex, 3-4 spare, 5-24 dark.
