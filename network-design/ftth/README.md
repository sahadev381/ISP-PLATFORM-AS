# FTTH Network Design — Melamchi / Helambu region (Sindhupalchok, Nepal)

Complete FTTH planning bundle for the 8-site GPON network:
Headend/DC (32-PON) at Melamchi Bazaar + 7 POPs (16-PON each).

## Quick view
Open `deliverables/index.html` (or `deliverables/FTTH_master_map.html`). The map needs internet
(Leaflet/OSM tiles + live OSRM road geometry); without internet it falls back to
straight-line legs with measured road distances.

## Files
| File | Contents |
|------|----------|
| `deliverables/FTTH_master_map.html` | Interactive map: POPs, 3 fiber rings + POP7 spur (road-following), DB points sized by est. homes, distribution tree, coverage rings |
| `deliverables/FTTH_DESIGN_REPORT.md` | Full design report: topology, density analysis, PON allocation, link budget, power, indicative BOM (NPR), phases |
| `deliverables/ftth_seed.sql` | **Import into ISP platform** (phpMyAdmin / `mysql`): 842 ftth_nodes (10 OLT, 124 FDC=MASTER_BOX, 708 FAT=DB_BOX) + 6,832 port_assignments linked OLT→FDC→FAT + 88 fiber_routes |
| `deliverables/FTTH_network.kml` | Google Earth / OsmAnd / MAPS.ME field file |
| `deliverables/FTTH_topology.svg` | Printable backbone schematic (A4) |
| `deliverables/FIBER_SPLICE_PLAN.md` `splice_plan.csv` | 48F core allocation, closure/splice matrix |
| `deliverables/IP_VLAN_PLAN.md` | OSPF+BFD ring routing, mgmt + customer VLAN plan, MikroTik sample |
| `deliverables/WIRELESS_BACKUP_POP7.md` `path_profile.svg` | POP7 backup SRTM analysis: direct LOS FAILS (-624 m, 1751 m ridge at km 6.3); 4-hop relay chain solved; POP2-POP7 fiber ring closure recommended |
| `deliverables/PHASED_CAPEX.md` `phased_capex.csv` | Phase-wise investment (P1 3.13cr / P2 3.41cr / P3 1.36cr+redundancy NPR) |
| `deliverables/PAYBACK_MODEL.md` `payback_arpu*.csv` | 5-yr cashflow: ARPU 900 → payback ~58 mo; 1100 → ~45 mo; 700 stalls |
| `deliverables/huawei/` | **OLT**: MA5800-X2 per-site configs (8x `OLT-*.txt`) + guide. **L3 ring**: S6730/S5735 per-site configs (8x `L3-*.txt`, OSPF+BFD) + `HUAWEI_L3_GUIDE.md` (per-POP MikroTik CCR2004 BNG, central RADIUS) |
| `deliverables/db_points.csv` | 77 DB/FAT sites: coordinates, locality, buildings, est. homes, FAT count |
| `deliverables/pops.csv` | Per-POP stats: homes, FATs, PON used/spare, distribution km |
| `deliverables/pon_allocation.csv` | FDC/PON-port level plan (124 FDCs) |
| `deliverables/fiber_segments.csv` | Backbone + distribution segments with km and core counts |
| `deliverables/dist_routes.json` | Per-POP OSRM road-distance MST: edges (road/trail mode), km, path km per DB |
| `deliverables/TRAIL_SURVEY.md` `trail_survey_checklist.csv` | Off-road spur field-survey checklist (9 trail DBs, km, poles, guide) |
| `deliverables/HOTSPOT_OPTIMIZATION.md` `hotspot_priority.csv` | Homes/feeder-km ranking, lean phasing (16-DB tail -> P4), ARPU-900 payback levers |
| `deliverables/trail_survey_map.html` | Print-friendly survey map for the 9 trail spurs (parent + spur + fill-in table) |
| `deliverables/PRICING_BANDS.md` `pricing_bands.csv` | 3-band tariff model (Basic 30M/Std 60M/Prem 100M), blended margin + payback per mix |
| `deliverables/clusters.json` `deliverables/building_counts.json` `deliverables/legs.json` | Raw data |

## Methodology
1. POP coordinates (user-provided) → distance matrix + optimal tour.
2. OSRM (`router.project-osrm.org`) → real ROAD km per backbone leg; chose
   Ring A (HE-POP2-POP1-HE 40.2 km), Ring B (HE-POP5-POP4-HE 45.6 km),
   Ring C (HE-POP2-POP6-POP3-HE 56.9 km, shares HE-POP2 with A),
   spur HE-POP7 34.3 km + wireless PTP backup (~14 km).
3. Settlements from OSM (Overpass `z.overpass-api.de`): 156 place nodes,
   94 within 6.5 km of a POP → clustered at 500 m → 77 DB candidate sites.
4. Building density: OSM `way[building]` counts within 450 m of each site
   → est. homes = buildings × 0.75.
5. FAT/DB = 8-port, 12 homes per FAT; FDC 1:8 → PON ports implied per POP.
6. Distribution fiber = MST on OSRM **road-distance matrix** per POP (measured, not estimated) + 10% slack; DBs with no drivable road (9 spurs) flagged as trail-est (straight × 1.6 + slack). Result: 239.7 km road + 47.2 km trail-est = **310.9 km** design.

## Regenerate
```bash
python3 cluster.py        # settlements -> DB clusters (needs data_settlements.py)
python3 make_dist_routes.py  # OSRM road-distance MST per POP -> dist_routes.json
python3 build_design.py   # full design + map + report + CSVs
python3 export_kml.py     # KML
python3 make_diagram.py   # SVG schematic
python3 make_extras.py    # SQL seed + splice/IP/vlan/wireless/phased docs
python3 wireless_pop7.py  # SRTM LOS chain solver -> WIRELESS_BACKUP_POP7.md + path_profile.svg
python3 make_payback.py   # cashflow model -> PAYBACK_MODEL.md + payback_arpu*.csv
python3 make_huawei.py    # Huawei OLT per-site provisioning scripts -> huawei/
python3 make_huawei_l3.py # Huawei L3 ring switch scripts per POP -> huawei/L3-*.txt
```
Tunable parameters at top of `build_design.py` (HOME_FACTOR, HOMES_PER_FAT,
FATS_PER_PON, ROUTE_FACTOR_DIST, take rates).

## Assumptions / caveats
- OSM building data = planning proxy; do field verification per DB site.
- Road distances from OSRM driving profile (as of 2026-10-02).
- Costs are indicative Kathmandu-market rates; get quotes.
- POP3 area: OSM place data sparse — survey Thakani/Dhuseni along HE-POP3 route.
- Melamchi khola flood (2021) and landslide zones: re-survey fiber paths.
