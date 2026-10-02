# FTTH Network Design Report — Melamchi / Helambu / Thangpal / Haibung (Sindhupalchok)
Generated: 2026-10-02 | Data: OpenStreetMap (Overpass z mirror) + OSRM road routing
**Caveat:** OSM building/settlement data + assumed factors ko planning-stage estimate ho. Final deployment agadi route walk/pole survey confirm garnu.

## 1) Backbone topology (road-based, OSRM measured)
| Leg | Group | Road km | Fiber |
|-----|-------|--------:|------:|
| HE - POP2 | Ring A+C (shared) | 5.85 | 48F |
| POP2 - POP1 | Ring A | 19.84 | 48F |
| POP1 - HE | Ring A | 14.55 | 48F |
| HE - POP5 | Ring B | 12.51 | 48F |
| POP5 - POP4 | Ring B | 13.19 | 48F |
| POP4 - HE | Ring B | 19.9 | 48F |
| HE - POP3 | Ring C | 17.84 | 48F |
| POP3 - POP6 | Ring C | 11.01 | 48F |
| POP6 - POP2 | Ring C | 22.21 | 48F |
| HE - POP7 | Spur (fiber 24F) + wireless PTP backup | 34.32 | 24F |

- **Backbone total: 171.2 road-km** (48F 136.9 km + 24F spur 34.3 km), +6-8% slack/loop.
- Ring A+C le HE-POP2 (5.85 km) share garchha — tei segment ma 2 separate cable (48F+48F) run garnu.
- Western closure POP6-POP2 (22.2 km) Phase-2 ma defer garna sakinchha (pahila HE-POP3-POP6 tree); Ring B jaile pani day-1 banaune (POP4/POP5 customer dense chhan).
- POP7 (Timbu): single 24F spur 34.3 km + **wireless PTP backup (~14 km LOS)** — Fresnel survey. POP2-POP7 road-ring 37.9 km uneconomic (rejected).

## 2) Settlement density analysis (basti ko density)
- Study bbox (94 settlements in coverage): 10,747 buildings TOTAL in DB circles; bbox-wide 93,191 buildings.
- Per-POP coverage potential (buildings within ~5.5-6 km radius of POP, rings overlap): HE 19,742 · POP2 15,838 · POP5 13,852 · POP4 11,859 · POP3 10,856 · POP1 9,887 · POP6 8,554 · POP7 6,099.
- Densest DB points: Melamchi bazaar 545 bldg/450m; Gunsakot 545; Dubachaur-area ~294-331; Mandandeupur 212; Haibung 264; Tarkeghyang-side 114.

## 3) Access network design (DB points, PON allocation)
Distribution routing: per-POP MST on **OSRM road-distance matrix** (measured), +10% slack; 9 DB spurs have **no drivable road** (orange dashed on map) - trail-pole estimate = straight-line x 1.6 (+10% slack), field survey required. Distribution totals: 310.6 km design = 239.7 km road-measured x1.10 + 47.2 km trail-est.
Assumptions: homes = buildings x 0.75 · FAT/DB 8-port, 12 homes per FAT · FDC 1:8 + FAT 1:8 = 64 ONT/PON · distribution = OSRM road MST + trail spurs.

| POP | OLT PON | DB sites | Est. homes | FAT/DB boxes | PON used | PON spare | Dist. fiber km |
|-----|--------:|---------:|-----------:|-------------:|---------:|----------:|---------------:|
| HE | 32 | 17 | 2279 | 198 | 31 | 1 | 55.0 |
| POP1 | 16 | 14 | 1729 | 151 | 25 | -9 | 62.9 |
| POP2 | 16 | 17 | 1741 | 152 | 28 | -12 | 55.3 |
| POP3 | 16 | 0 | 0 | 0 | 0 | 16 | 0.0 |
| POP4 | 16 | 11 | 858 | 77 | 14 | 2 | 44.6 |
| POP5 | 16 | 2 | 295 | 26 | 4 | 12 | 5.3 |
| POP6 | 16 | 5 | 432 | 39 | 8 | 8 | 25.8 |
| POP7 | 16 | 11 | 727 | 65 | 14 | 2 | 61.7 |

| **Total** | **144(+32)** | **77** | **8061** | **708** | **124** | — | **310.6** |

- Customer projection: Y1 ~3,224 (40%) · Y3 ~4,837 (60%).
- ⚠ **POP1 ≈ 25 ra POP2 ≈ 28 PON required** (16-port OLT bhandaa dherai; FDC haru site-wise install hune bhayera spare splitter-port pani count vayeko) — tehaa **2nd 16-port OLT** (waa direct 32-port) rakhaa; BOM ma include gareko chha. Baaki POP haru 16-port le pugchha.
- HE (32-port) ma ≈ 31 PON used — Melamchi bazaar 5-6 FDC bata chalaanchha.
- POP oversubscription na huna laagi PON utilisation cap ~80% design gareko (FAT 8-fill x 65%).
- **POP3 note:** OSM ma Thakani ridge place-nodes thin chhan; POP3 ko DBs mostly POP6 sanga nearest paren. He-POP3 road (17.8 km) bhitra ko villages (Thakani, Dhuseni, Siwalaya) survey garera 6-10 DB thapne; ahileko POP3 spare capacity dherai chha.

## 6) Indicative BOM / cost (NPR, indicative 2026 market rates — quote lina parne)
| Item | Qty | Est. cost | Note |
|------|-----|----------:|------|
| 48F ADSS backbone fiber | 145 km | 137.9 L | cable @95/m +6% slack |
| 24F fiber (POP7 spur) | 36 km | 20.0 L | cable @55/m |
| 12F distribution fiber | 335 km | 107.3 L | cable @32/m +8% service loop |
| 8m wooden poles | 8,163 pcs | 449.0 L | @5,500 incl. transport |
| Aerial stringing/lashing labour | 482 km | 154.2 L | @32/m |
| FDC closures (1:8 cassette + tray) | 124 pcs | 11.8 L | per PON coverage area |
| FAT/DB box + 1:8 PLC splitter | 708 pcs | 17.0 L |  |
| Splice closures + pigtails | 844 pcs | 27.0 L | estimate |
| GPON OLT 16-port (POP1-7) | 7 pcs | 24.5 L | BDCOM/VSOL/ZTE class |
| GPON OLT 16-port EXTRA (POP1+POP2 2nd unit) | 2 pcs | 7.0 L | both need ~19 PON > 16-port capacity |
| GPON OLT 32-port (Headend) | 1 pc | 6.5 L |  |
| GPON SFP C+/C++ modules | 154 pcs | 3.4 L | used ports + spares |
| Uplink 10G LR optics pairs | 18 pairs | 4.3 L | ring+spur POP uplinks |
| POP power (UPS/rectifier/batt) | 8 sets | 17.6 L | UPS + 100Ah batt x2 |
| Aggregation switches/router | 8 sites | 12.0 L | L3 at HE + L2 at POPs |
| Drop cable 100m + install /customer (Y1) | 3224 subs | 90.3 L | year-1 connections |
| ONT/ONU (Y1) | 3224 pcs | 74.2 L | usually billed to customer |
| **TOTAL (approx)** | | **1,163.9 L** | ±30% |

## 7) Implementation phases
1. **Phase 1 (month 0-2):** Ring A+C shared HE-POP2, HE-POP5-POP4-HE (Ring B), Melamchi town DBs (550+ homes), POP4/POP5 feeders. ~60% of revenue potential.
2. **Phase 2 (month 2-4):** HE-POP3-POP6 + Haibung DBs, POP1 feeder Thangpal/Gunsakot, Ring C closure POP6-POP2.
3. **Phase 3 (month 4-6):** POP7 spur (34 km, river-valley route), Timbu/Dongdhing/Chhimi/Sermathang DBs, wireless backup live.

## 4) PON optical link budget (worst-case per POP; 1:8+1:8 = 64-way, 1310/1490 nm)
| POP | Farthest DB | Fiber (est) | Total loss | Budget (Class C+) | Verdict |
|-----|-------------|------------:|-----------:|------------------:|---------|
| HE | DB-HE-C01 Melamchi | 13.7 km | 29.6 dB | 32 dB | PASS (C+ 32dB) |
| POP1 | DB-POP1-C01 Dhap | 13.2 km | 29.4 dB | 32 dB | PASS (C+ 32dB) |
| POP2 | DB-POP2-C01  | 11.8 km | 28.9 dB | 32 dB | PASS (C+ 32dB) |
| POP3 | — | — | — | — | n/a |
| POP4 | DB-POP4-C01 Nagarkot | 12.8 km | 29.3 dB | 32 dB | PASS (C+ 32dB) |
| POP5 | DB-POP5-C01 Mandandeupur | 6.3 km | 27.0 dB | 32 dB | PASS (C+ 32dB) |
| POP6 | DB-POP6-C01 Chisapani | 11.5 km | 28.8 dB | 32 dB | PASS (C+ 32dB) |
| POP7 | DB-POP7-C01 Sanugopte | 19.4 km | 31.6 dB | 32 dB | PASS (C+ 32dB) |

Formula: 21 dB splitters + 0.35 dB/km fiber + 1.8 dB connectors/splices + 2 dB margin. **Class C+ (32 dB) SFP sabai PON port ma use gara** (BOM ma chha); C++ (35 dB) optional for POP7 longest runs.

## 5) Power & backup sizing
| Site | Load (est) | Backup kit | Autonomy |
|------|-----------:|------------|----------|
| Headend (Melamchi NOC) | ~600 W (32p OLT + L3 + servers) | 2 kVA online UPS + 48V/200Ah + genset hookup | 6-8 h |
| POP1-POP6 (each) | ~200 W (16p OLT + L2 switch) | 1 kVA UPS/rectifier + 48V/100-200Ah | 6-10 h |
| POP7 (Timbu - far) | ~200 W | rectifier + 48V/200Ah + **solar hybrid 600-800 W** | 24h+ target |
Load-shedding: hilly stretch ma supply unreliable — POP7 solar mandatory-ish. BOM ma 8 power sets included.

## 8) Files
- `FTTH_master_map.html` / `index.html` — interactive map (ring roads via OSRM live overlay, straight fallback)
- `FTTH_topology.svg` — printable backbone schematic (A4 print friendly)
- `FTTH_network.kml` — Google Earth / OsmAnd / MAPS.ME field-survey file
- **`ftth_seed.sql` — one-shot import into ISP platform** (842 ftth_nodes, 6,832 port_assignments, 88 fiber_routes: OLT→MASTER_BOX(FDC)→DB_BOX(FAT) hierarchy, linked)
- `FIBER_SPLICE_PLAN.md` + `splice_plan.csv` — 48F core allocation + closure matrix
- `IP_VLAN_PLAN.md` — loopbacks, /31 arms, OSPF+BFD, management + per-PON customer VLANs, MikroTik sample
- `WIRELESS_BACKUP_POP7.md` + `path_profile.svg` — POP7 backup SRTM analysis: direct LOS blocked (-624 m, 1751 m ridge); 4-hop relay chain solved; POP2-POP7 fiber ring recommended
- `PHASED_CAPEX.md` + `phased_capex.csv` — phase-wise investment split
- `PAYBACK_MODEL.md` + `payback_arpu700/900/1100.csv` — 5-year cashflow model, 3 ARPU scenarios
- `db_points.csv`, `pops.csv`, `pon_allocation.csv`, `fiber_segments.csv` — GIS-importable
- `clusters.json`, `building_counts.json`, `legs.json` — raw data

## 9) Next steps / field survey checklist
- [ ] Ring leg ma road-permission (DoLIDAR/local gov) + pole span walk route measure (map ko 1.35 factor validate)
- [ ] POP building rent + power availability (POP7 Timbu: solar hybrid recommended)
- [ ] Melamchi-HE fiber entry duct/ODF plan; NOC room ≥4 kW cooling, dual uplink (NT + private) 
- [ ] Hazards: Melamchi khola flood zone (2021 flood route recheck), landslide zones Helambu road
- [ ] DB-top 20 sites ko household ground-truth (FAT count validate) — GPS: db_points.csv / KML use gara
