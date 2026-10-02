# Off-road trail-spur survey checklist (9 DB spurs)

These DBs show **no drivable road** in OSRM — the design carries a straight-line x1.6 trail estimate (+10% slack).
Walk the spur, decide pole-vs-asl path, close the loop to the listed parent node, and update `dist_routes.json` before ordering cable.

| POP | Spur (from -> DB) | DB locality | Straight km | Design km (trail-est) | Impractical road detour | Homes | FAT/DB | Priority |
|--|--|--|--:|--:|--:|--:|--:|---|
| HE | HE-C03 -> HE-C12 |  | 2.78 | 4.9 | 12.94 | 88 | 8 | MED |
| POP1 | POP1 -> POP1-C08 | Kotgau Thatidada | 1.62 | 2.84 | 8.34 | 121 | 11 | HIGH |
| POP2 | POP2-C15 -> POP2-C09 |  | 2.2 | 3.87 | 9.46 | 118 | 10 | MED |
| POP4 | POP4 -> POP4-C02 | Chauki Banjyang | 2.75 | 4.84 | 8.72 | 92 | 8 | MED |
| POP6 | POP6-C04 -> POP6-C02 | Mulkharka (W) | 5.78 | 10.18 | 37.77 | 198 | 17 | HIGH |
| POP6 | POP6-C03 -> POP6-C05 | Chipling | 2.77 | 4.88 | 11.54 | 57 | 5 | LOW |
| POP7 | POP7-C11 -> POP7-C05 | Dongdhing | 2.43 | 4.27 | 14.86 | 47 | 4 | LOW |
| POP7 | POP7-C10 -> POP7-C09 | Tarke Ghyang | 2.2 | 3.86 | 10.26 | 86 | 8 | MED |
| POP7 | POP7-C03 -> POP7-C11 | Helambu | 4.28 | 7.53 | 13.51 | 132 | 11 | HIGH |

## Survey guide per spur
1. Mark day-pot poles every 40-45 m; note river/bluff crossings and which side the trunk pole line should follow.
2. If a motorable track exists within 1.5x straight-line km, switch edge back to `road` mode with measured km.
3. Check the DB end: safe pole stand at FAT cluster (flood/landslide clear), 3-m guy clearance.
4. Photograph every 200 m; geotag; record kandos for crossing owner permission (Rural Municipality).
5. Output: measured_km + pole count + splice-point notes -> engineer will re-run `make_dist_routes.py`.

> Rule of thumb: 1 trail-km ≈ 22-25 poles; budget NPR ~1.1-1.3 lakh/km for labour+material on trail terrain (solar carry).