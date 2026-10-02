#!/usr/bin/env python3
"""
FTTH network design builder
- reads deliverables/clusters.json, deliverables/building_counts.json, deliverables/legs.json
- computes: DB (FAT) counts, FDC/PON allocation, distribution fiber (MST x route factor),
  backbone totals, indicative BOM/cost
- writes: FTTH_master_map.html (+ index.html), FTTH_DESIGN_REPORT.md, CSVs in deliverables/
"""
import json, math, csv, os

HERE = os.path.dirname(os.path.abspath(__file__))
OUT  = os.path.join(HERE, "deliverables")
D = json.load(open(f"{OUT}/clusters.json"))
COUNTS = json.load(open(f"{OUT}/building_counts.json"))
LEGS = json.load(open(f"{OUT}/legs.json"))

# ---------------- design parameters (tune after field survey) ----------------
HOME_FACTOR        = 0.75   # share of OSM buildings assumed to be residences
HOMES_PER_FAT      = 12     # 8-port FAT/DB at ~65% fill  -> 12 homes passed
FATS_PER_PON       = 8      # FDC 1:8 -> 8  FAT 1:8       -> 64 ONT max / PON
ROUTE_FACTOR_DIST  = 1.35   # trail/road winding factor for distribution
TAKE_Y1, TAKE_Y3   = 0.40, 0.60
CIRC = math.pi * 2

R = 6371.0
def hav(a_lat, a_lon, b_lat, b_lon):
    la1, lo1, la2, lo2 = map(math.radians, (a_lat, a_lon, b_lat, b_lon))
    h = math.sin((la2-la1)/2)**2 + math.cos(la1)*math.cos(la2)*math.sin((lo2-lo1)/2)**2
    return 2*R*math.asin(math.sqrt(h))

def mst_edges(points):
    """points: list of dicts with id/lat/lon. Returns MST edge list (Prim)."""
    n = len(points)
    if n < 2: return []
    intree = {0}; edges = []
    while len(intree) < n:
        best = None
        for i in intree:
            for j in range(n):
                if j in intree: continue
                d = hav(points[i]["lat"], points[i]["lon"], points[j]["lat"], points[j]["lon"])
                if best is None or d < best[0]: best = (d, i, j)
        d, i, j = best
        intree.add(j)
        edges.append({"from": points[i]["id"], "to": points[j]["id"], "km": round(d, 3)})
    return edges

# ---------------- attach densities to DB clusters ----------------
counts = COUNTS["batch0"] + COUNTS["batch1"] + COUNTS["batch2"] + COUNTS["batch3"]
clusters = D["clusters"]
assert len(counts) == len(clusters)

dbs = []
for c, b in zip(clusters, counts):
    homes = max(1, round(b * HOME_FACTOR))
    fats  = max(1, math.ceil(homes / HOMES_PER_FAT))
    dbs.append({
        "id": f"DB-{c['cluster_id']}", "pop": c["pop"],
        "lat": c["lat"], "lon": c["lon"], "label": c["label"] or "",
        "members": c["members"], "buildings": b,
        "homes": homes, "fats": fats,
    })

POP = {p["pop"]: p for p in dbs}  # not used; silence lint
pop_stats = {}
for pop in LEGS["pops"]:
    members = [d for d in dbs if d["pop"] == pop]
    homes = sum(d["homes"] for d in members)
    fats  = sum(d["fats"]  for d in members)
    # FDCs are located per DB site (not shared across sites) -> PON ports = sum of ceil(site_fats/8)
    pon_used = sum(math.ceil(d["fats"] / FATS_PER_PON) for d in members)
    pop_stats[pop] = {"dbs": members, "homes": homes, "fats": fats, "pon_used": pon_used}

# ---------------- distribution fiber: OSRM road-distance MST per POP (make_dist_routes.py) ----------------
DIST_ROUTES = json.load(open(f"{OUT}/dist_routes.json"))
dist_edges_all = []
for pop, st in pop_stats.items():
    dr = DIST_ROUTES.get(pop)
    if dr:
        for e in dr["edges"]:
            dist_edges_all.append({"from": e["from"], "to": e["to"], "pop": pop,
                                   "km_route": e["km"], "km_raw": e["km_raw"],
                                   "km_straight": e["km_straight"], "mode": e["mode"]})
        st["route_km"] = dr["design_km"]
        st["road_km"] = dr["road_km"]; st["trail_km"] = dr["trail_km"]; st["n_trail"] = dr["n_trail"]
    else:
        st["route_km"] = 0.0; st["road_km"] = 0.0; st["trail_km"] = 0.0; st["n_trail"] = 0
dist_km = round(sum(st["route_km"] for st in pop_stats.values()), 1)
dist_road = round(sum(st["road_km"] for st in pop_stats.values()), 1)
dist_trail = round(sum(st["trail_km"] for st in pop_stats.values()), 1)
n_trail_all = sum(st["n_trail"] for st in pop_stats.values())

backbone_km = round(sum(l["km"] for l in LEGS["legs"]), 1)
backbone_48 = round(sum(l["km"] for l in LEGS["legs"] if l["core"] == 48), 1)
backbone_24 = round(sum(l["km"] for l in LEGS["legs"] if l["core"] == 24), 1)
dist_km = round(sum(st["route_km"] for st in pop_stats.values()), 1)

tot_homes = sum(st["homes"] for st in pop_stats.values())
tot_fats  = sum(st["fats"] for st in pop_stats.values())
tot_fdcs  = sum(st["pon_used"] for st in pop_stats.values())
cust_y1 = round(tot_homes * TAKE_Y1)
cust_y3 = round(tot_homes * TAKE_Y3)

# ---------------- indicative BOM / cost (NPR, 2026 indicative, confirm with market) ----------------
def poles(km):  # own wooden poles @40m span, only on backbone + half of distribution
    return math.ceil(km * 25)
BACKBONE_POLES = poles(backbone_km)
DIST_POLES     = math.ceil(poles(dist_km) * 0.5)  # reuse backbone poles on shared paths
bom = [
 ("48F ADSS backbone fiber",        f"{backbone_48*1.06:.0f} km", backbone_48*1.06*1000*95,  "cable @95/m +6% slack"),
 ("24F fiber (POP7 spur)",          f"{backbone_24*1.06:.0f} km", backbone_24*1.06*1000*55,  "cable @55/m"),
 ("12F distribution fiber",         f"{dist_km*1.08:.0f} km",     dist_km*1.08*1000*32,      "cable @32/m +8% service loop"),
 ("8m wooden poles",                f"{BACKBONE_POLES+DIST_POLES:,} pcs", (BACKBONE_POLES+DIST_POLES)*5500, "@5,500 incl. transport"),
 ("Aerial stringing/lashing labour",f"{backbone_km+dist_km:.0f} km", (backbone_km+dist_km)*1000*32, "@32/m"),
 ("FDC closures (1:8 cassette + tray)", f"{tot_fdcs} pcs", tot_fdcs*9500, "per PON coverage area"),
 ("FAT/DB box + 1:8 PLC splitter",  f"{tot_fats} pcs", tot_fats*2400, ""),
 ("Splice closures + pigtails",     f"{tot_fats+tot_fdcs+12} pcs", (tot_fats+tot_fdcs+12)*3200, "estimate"),
 ("GPON OLT 16-port (POP1-7)",      "7 pcs", 7*350000, "BDCOM/VSOL/ZTE class"),
 ("GPON OLT 16-port EXTRA (POP1+POP2 2nd unit)", "2 pcs", 2*350000, "both need ~19 PON > 16-port capacity"),
 ("GPON OLT 32-port (Headend)",     "1 pc",  1*650000, ""),
 ("GPON SFP C+/C++ modules",        f"{tot_fdcs+30} pcs", (tot_fdcs+30)*2200, "used ports + spares"),
 ("Uplink 10G LR optics pairs",     "18 pairs", 18*24000, "ring+spur POP uplinks"),
 ("POP power (UPS/rectifier/batt)", "8 sets", 8*220000, "UPS + 100Ah batt x2"),
 ("Aggregation switches/router",    "8 sites", 8*150000, "L3 at HE + L2 at POPs"),
 ("Drop cable 100m + install /customer (Y1)", f"{cust_y1} subs", cust_y1*(100*13+1500), "year-1 connections"),
 ("ONT/ONU (Y1)",                   f"{cust_y1} pcs", cust_y1*2300, "usually billed to customer"),
]
capex = sum(x[2] for x in bom)

# ---------------- CSV exports ----------------
os.makedirs(OUT, exist_ok=True)
with open(f"{OUT}/pops.csv", "w", newline="") as f:
    w = csv.writer(f); w.writerow(["pop","lat","lon","olt_ports","label","db_sites","est_homes","fats","pon_used","pon_spare","dist_route_km"])
    for pop, p in LEGS["pops"].items():
        st = pop_stats[pop]
        w.writerow([pop, p["lat"], p["lon"], p["pon"], p["label"], len(st["dbs"]), st["homes"], st["fats"], st["pon_used"], p["pon"]-st["pon_used"], st["route_km"]])
with open(f"{OUT}/db_points.csv", "w", newline="") as f:
    w = csv.writer(f); w.writerow(["db_id","pop","lat","lon","locality","osm_buildings_450m","est_homes","fat_boxes","villages"])
    for d in dbs:
        w.writerow([d["id"], d["pop"], d["lat"], d["lon"], d["label"], d["buildings"], d["homes"], d["fats"], "; ".join(d["members"][:4])])
with open(f"{OUT}/fiber_segments.csv", "w", newline="") as f:
    w = csv.writer(f); w.writerow(["segment","type","from","to","km","core"])
    for l in LEGS["legs"]:
        w.writerow([l["group"], "backbone", l["a"], l["b"], l["km"], l["core"]])
    for e in dist_edges_all:
        w.writerow([f"distribution {e['pop']}", "distribution(12F)" + (" trail-est" if e.get("mode")=="trail" else " road"), e["from"], e["to"], e["km_route"], 12])
with open(f"{OUT}/pon_allocation.csv", "w", newline="") as f:
    w = csv.writer(f); w.writerow(["pop","pon_port_at_olt","fdc_id","db_site","locality","fats_in_fdc","customer_capacity"])
    for pop, st in pop_stats.items():
        fdc_no = 0
        for d in sorted(st["dbs"], key=lambda x: -x["homes"]):
            left = d["fats"]
            while left > 0:
                fdc_no += 1
                take = min(FATS_PER_PON, left)
                w.writerow([pop, f"PON{fdc_no}", f"FDC-{pop}-{fdc_no:02d}", d["id"], d["label"], take, take*8])
                left -= take
        olt_cap = LEGS["pops"][pop]["pon"]
        if fdc_no > olt_cap:
            print(f"!!! {pop} oversubscribed: {fdc_no} PON needed > {olt_cap}-port OLT")

# ---------------- interactive map ----------------
fib = {e["to"]: {"from": e["from"], "km": e["km_route"], "mode": e["mode"], "raw": e["km_raw"]} for e in dist_edges_all}
data = {
 "pops": LEGS["pops"], "legs": LEGS["legs"], "backup": LEGS["backup_wireless"],
 "dbs": dbs, "edges": dist_edges_all, "fib": fib,
 "stats": {p: {"homes": s["homes"], "fats": s["fats"], "pon_used": s["pon_used"], "dbs": len(s["dbs"]), "route_km": s["route_km"]} for p, s in pop_stats.items()},
 "totals": {"homes": tot_homes, "fats": tot_fats, "fdcs": tot_fdcs, "backbone_km": backbone_km, "dist_km": dist_km, "cust_y1": cust_y1, "cust_y3": cust_y3, "capex_lakh": round(capex/100000)},
}
DATA_JSON = json.dumps(data, ensure_ascii=False)

html_tmpl = r"""<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FTTH Master Map - Melamchi/Helambu Network</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  html,body{margin:0;height:100%;font-family:system-ui,Segoe UI,Arial}
  #map{height:100%}
  .panel{position:absolute;z-index:1000;top:10px;right:10px;background:rgba(255,255,255,.94);
         padding:12px 14px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.3);max-width:330px;font-size:13px}
  .panel h3{margin:0 0 6px;font-size:15px}
  .panel table{border-collapse:collapse;width:100%}
  .panel td{padding:1px 4px;border-bottom:1px solid #eee}
  .sw{display:inline-block;width:16px;height:4px;margin-right:6px;vertical-align:middle}
  .legend{font-size:12px;margin-top:6px}
  @media print {#map{height:95vh}}
</style>
</head>
<body>
<div id="map"></div>
<div class="panel">
 <h3>FTTH Design - Melamchi / Helambu / Thangpal / Haibung</h3>
 <table>
   <tr><td>Backbone ring+spur</td><td><b>@BACKBONE@ km</b> road-km</td></tr>
   <tr><td>Distribution (to DB)</td><td><b>@DIST@ km</b> route-km (road-measured OSRM + trail-est spurs)</td></tr>
   <tr><td>DB/FAT boxes</td><td><b>@FATS@</b> pcs (sites: @DBSITES@)</td></tr>
   <tr><td>FDC / PON ports used</td><td><b>@FDCS@</b> of 144 ports</td></tr>
   <tr><td>Homes passed (est)</td><td><b>@HOMES@</b> (OSM buildings x 0.75)</td></tr>
   <tr><td>Customers Y1/Y3 @40/60%</td><td><b>@CY1@ / @CY3@</b></td></tr>
   <tr><td>Indicative capex</td><td><b>NPR ~@CAPEX@ L</b> (see report)</td></tr>
 </table>
 <div class="legend">
  <span class="sw" style="background:#e53935"></span>Ring A (HE-POP2-POP1-HE)<br>
  <span class="sw" style="background:#1e88e5"></span>Ring B (HE-POP5-POP4-HE)<br>
  <span class="sw" style="background:#43a047"></span>Ring C (HE-POP2-POP6-POP3-HE)<br>
  <span class="sw" style="background:#fb8c00"></span>Spur HE-POP7 (Timbu) + wireless backup<br>
  <span class="sw" style="background:#8e24aa;height:0;border-top:2px dashed #8e24aa"></span>Wireless PTP backup 14 km<br>
  <span class="sw" style="background:#43a047"></span>Distribution fiber - road-following (OSRM)<br>
  <span class="sw" style="background:#fb8c00;height:0;border-top:2px dashed #fb8c00"></span>Off-road DB spur (trail-est, survey)
 </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const DATA = @DATAJSON@;
const map = L.map('map').setView([27.855, 85.545], 11);
const voyager = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',{attribution:'(c) OpenStreetMap contributors (c) CARTO',maxZoom:19}).addTo(map);
const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'(c) OpenStreetMap contributors'});
const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{attribution:'Esri World Imagery'});
L.control.layers({"Voyager (default)":voyager,"OSM":osm,"Satellite":sat},null).addTo(map);

const POPCOL = {HE:'#c62828',POP1:'#6a3fb5',POP2:'#00838f',POP3:'#ad1457',POP4:'#2e7d32',POP5:'#ef6c00',POP6:'#4527a0',POP7:'#0277bd'};
const grpcol = g => g.startsWith('Ring A')?'#e53935': g.startsWith('Ring B')?'#1e88e5': g.startsWith('Ring C')?'#43a047':'#fb8c00';

const lyrBB = L.layerGroup().addTo(map), lyrDB = L.layerGroup().addTo(map),
      lyrDist = L.layerGroup().addTo(map), lyrCov = L.layerGroup();

// POP markers
const popLatLon = {};
for (const [k,p] of Object.entries(DATA.pops)) {
  popLatLon[k] = [p.lat, p.lon];
  const st = DATA.stats[k];
  const m = L.marker([p.lat,p.lon], {icon: L.divIcon({
      className:'', html:`<div style="background:${POPCOL[k]};color:#fff;border:2px solid #fff;border-radius:6px;
      padding:2px 6px;font-weight:700;font-size:12px;box-shadow:0 1px 5px rgba(0,0,0,.5);white-space:nowrap">${k}</div>`,
      iconSize:[40,20], iconAnchor:[20,10]})})
   .bindPopup(`<b>${p.label}</b><br>OLT: ${p.pon} PON<br>DB sites: ${st.dbs}<br>Homes passed: ~${st.homes}
               <br>FAT/DB boxes: ${st.fats}<br>PON used: ${st.pon_used}/${p.pon}<br>Distribution fiber: ${st.route_km} km road-measured`);
  lyrBB.addLayer(m);
  lyrCov.addLayer(L.circle([p.lat,p.lon],{radius:6500,color:POPCOL[k],weight:1,dashArray:'4 6',fillOpacity:.02}));
}

// Backbone legs: straight dashed immediately, then replaced by OSRM road route
async function drawRoad(leg){
  const a=popLatLon[leg.a], b=popLatLon[leg.b];
  const label = `${leg.a}-${leg.b}: ${leg.km} km (${leg.core}F)`;
  const fallback = L.polyline([a,b],{color:grpcol(leg.group),weight:3,dashArray:'6 5',opacity:.75}).bindTooltip(label);
  lyrBB.addLayer(fallback);
  try{
    const url=`https://router.project-osrm.org/route/v1/driving/${a[1]},${a[0]};${b[1]},${b[0]}?overview=full&geometries=geojson`;
    const r=await fetch(url); const j=await r.json();
    if(j.code==='Ok'){
      const coords=j.routes[0].geometry.coordinates.map(c=>[c[1],c[0]]);
      lyrBB.removeLayer(fallback);
      lyrBB.addLayer(L.polyline(coords,{color:grpcol(leg.group),weight:4,opacity:.9}).bindTooltip(label));
    }
  }catch(e){/* keep fallback */}
}
DATA.legs.forEach(drawRoad);

// wireless backup POP7
lyrBB.addLayer(L.polyline([popLatLon.HE, popLatLon.POP7],
  {color:'#8e24aa',weight:2,dashArray:'3 6'}).bindTooltip('Wireless PTP backup HE-POP7, ~14 km LOS (survey)'));

// distribution fiber tree: OSRM road-distance MST (km measured), trail spurs flagged
const idx={}; DATA.dbs.forEach(d=>idx[d.id]=[d.lat,d.lon]); Object.assign(idx,popLatLon);
const fiberQ=[];
DATA.edges.forEach(e=>{
  if(!(idx[e.from]&&idx[e.to])) return;
  const tr=e.mode==='trail';
  const pl=L.polyline([idx[e.from],idx[e.to]],{color:tr?'#fb8c00':'#43a047',weight:tr?1.5:2,dashArray:tr?'5 5':null,opacity:.85});
  pl.bindTooltip(`${e.from} -> ${e.to}: ${e.km_route} km `+(tr?`TRAIL SPUR (no drivable road; road detour ${e.km_raw} km impractical - field survey)`:'(road-following, loading exact path...)'));
  lyrDist.addLayer(pl);
  if(!tr) fiberQ.push([e,pl]);
});
// progressively upgrade road edges to exact OSRM geometry
(function pump(){
  const it=fiberQ.shift(); if(!it) return;
  const [e,pl]=it, a=idx[e.from], b=idx[e.to];
  fetch(`https://router.project-osrm.org/route/v1/driving/${a[1]},${a[0]};${b[1]},${b[0]}?overview=full&geometries=geojson`)
   .then(r=>r.json()).then(j=>{
     const g=j.routes&&j.routes[0]&&j.routes[0].geometry;
     if(g) pl.setLatLngs(g.coordinates.map(c=>[c[1],c[0]]))
           .setTooltipContent(`${e.from} -> ${e.to}: ${e.km_route} km (road-following)`);
   }).catch(()=>{})
  .finally(()=>setTimeout(pump,120));
})();

// DB points
DATA.dbs.forEach(d=>{
  const rad = 4+Math.min(10,Math.sqrt(d.homes)/6);
  lyrDB.addLayer(L.circleMarker([d.lat,d.lon],{radius:rad,color:POPCOL[d.pop]||'#555',weight:1.5,fillColor:POPCOL[d.pop]||'#555',fillOpacity:.55})
   .bindPopup(`<b>${d.id}</b> ${d.label?(' - '+d.label):''}<br>POP: ${d.pop}<br>Villages: ${d.members.join(', ')}
     <br>OSM buildings (450 m): ${d.buildings}<br>Est. homes: ${d.homes}<br>FAT/DB boxes here: ${d.fats}`
     +(DATA.fib[d.id]?`<br><b>Feeder fiber:</b> ${DATA.fib[d.id].km} km from ${DATA.fib[d.id].from}`
       +(DATA.fib[d.id].mode==='trail'?` (TRAIL spur est - road detour ${DATA.fib[d.id].raw} km, survey)`:' (road-following)'):'')));
});
L.control.layers(null,{"Backbone/POP":lyrBB,"DB points":lyrDB,"Distribution tree":lyrDist,"Coverage 6.5km":lyrCov},{collapsed:false}).addTo(map);
</script>
</body>
</html>
"""

html = (html_tmpl
        .replace("@DATAJSON@", DATA_JSON)
        .replace("@BACKBONE@", str(backbone_km))
        .replace("@DIST@", str(dist_km))
        .replace("@FATS@", str(tot_fats))
        .replace("@DBSITES@", str(len(dbs)))
        .replace("@FDCS@", str(tot_fdcs))
        .replace("@HOMES@", str(tot_homes))
        .replace("@CY1@", str(cust_y1))
        .replace("@CY3@", str(cust_y3))
        .replace("@CAPEX@", str(round(capex/100000))))
open(f"{OUT}/FTTH_master_map.html", "w").write(html)
open(f"{OUT}/index.html", "w").write(html)

# ---------------- report ----------------
# PON optical link budget per POP (worst-case farthest DB)
SPLIT_LOSS = 21.0      # 1:8 + 1:8 cascade
FIBER_DB_KM = 0.35     # @1490nm
CONN_DB = 1.8          # ~12 connectors/splices
MARGIN_DB = 2.0
lb_rows = ""
for pop, st in pop_stats.items():
    if not st["dbs"]:
        lb_rows += f"| {pop} | — | — | — | — | n/a |\n"; continue
    drp = DIST_ROUTES.get(pop)
    if drp and drp.get("path_km"):
        far_id = max(drp["path_km"], key=drp["path_km"].get)
        far = next((d for d in st["dbs"] if d["id"] == far_id), st["dbs"][0])
        fiber_km = drp["path_km"][far_id] + 1.0  # cumulative POP->DB road/trail km + splices slack
    else:
        p = LEGS["pops"][pop]
        far = max(st["dbs"], key=lambda d: hav(p["lat"], p["lon"], d["lat"], d["lon"]))
        fkm_straight = hav(p["lat"], p["lon"], far["lat"], far["lon"])
        fiber_km = fkm_straight * ROUTE_FACTOR_DIST + 1.0
    loss = SPLIT_LOSS + FIBER_DB_KM*fiber_km + CONN_DB + MARGIN_DB
    verdict = "PASS (C+ 32dB)" if loss < 32 else "CHECK design"
    lb_rows += f"| {pop} | {far['id']} {far['label']} | {fiber_km:.1f} km | {loss:.1f} dB | 32 dB | {verdict} |\n"
def lakh(x): return f"{x/100000:,.1f} L"
rows = "\n".join(f"| {i} | {q} | {lakh(c)} | {n} |" for i, q, c, n in bom)
pop_rows = ""
for pop, p in LEGS["pops"].items():
    st = pop_stats[pop]
    pop_rows += f"| {pop} | {p['pon']} | {len(st['dbs'])} | {st['homes']} | {st['fats']} | {st['pon_used']} | {p['pon']-st['pon_used']} | {st['route_km']} |\n"
leg_rows = "\n".join(f"| {l['a']} - {l['b']} | {l['group']} | {l['km']} | {l['core']}F |" for l in LEGS["legs"])

report = f"""# FTTH Network Design Report — Melamchi / Helambu / Thangpal / Haibung (Sindhupalchok)
Generated: 2026-10-02 | Data: OpenStreetMap (Overpass z mirror) + OSRM road routing
**Caveat:** OSM building/settlement data + assumed factors ko planning-stage estimate ho. Final deployment agadi route walk/pole survey confirm garnu.

## 1) Backbone topology (road-based, OSRM measured)
| Leg | Group | Road km | Fiber |
|-----|-------|--------:|------:|
{leg_rows}

- **Backbone total: {backbone_km} road-km** (48F {backbone_48} km + 24F spur {backbone_24} km), +6-8% slack/loop.
- Ring A+C le HE-POP2 (5.85 km) share garchha — tei segment ma 2 separate cable (48F+48F) run garnu.
- Western closure POP6-POP2 (22.2 km) Phase-2 ma defer garna sakinchha (pahila HE-POP3-POP6 tree); Ring B jaile pani day-1 banaune (POP4/POP5 customer dense chhan).
- POP7 (Timbu): single 24F spur 34.3 km + **wireless PTP backup (~14 km LOS)** — Fresnel survey. POP2-POP7 road-ring 37.9 km uneconomic (rejected).

## 2) Settlement density analysis (basti ko density)
- Study bbox ({len(D['settlements'])} settlements in coverage): {sum(x for x in counts):,} buildings TOTAL in DB circles; bbox-wide 93,191 buildings.
- Per-POP coverage potential (buildings within ~5.5-6 km radius of POP, rings overlap): HE 19,742 · POP2 15,838 · POP5 13,852 · POP4 11,859 · POP3 10,856 · POP1 9,887 · POP6 8,554 · POP7 6,099.
- Densest DB points: Melamchi bazaar 545 bldg/450m; Gunsakot 545; Dubachaur-area ~294-331; Mandandeupur 212; Haibung 264; Tarkeghyang-side 114.

## 3) Access network design (DB points, PON allocation)
Distribution routing: per-POP MST on **OSRM road-distance matrix** (measured), +10% slack; {n_trail_all} DB spurs have **no drivable road** (orange dashed on map) - trail-pole estimate = straight-line x 1.6 (+10% slack), field survey required. Distribution totals: {dist_km} km design = {dist_road} km road-measured x1.10 + {dist_trail} km trail-est.
Assumptions: homes = buildings x 0.75 · FAT/DB 8-port, 12 homes per FAT · FDC 1:8 + FAT 1:8 = 64 ONT/PON · distribution = OSRM road MST + trail spurs.

| POP | OLT PON | DB sites | Est. homes | FAT/DB boxes | PON used | PON spare | Dist. fiber km |
|-----|--------:|---------:|-----------:|-------------:|---------:|----------:|---------------:|
{pop_rows}
| **Total** | **144(+32)** | **{len(dbs)}** | **{tot_homes}** | **{tot_fats}** | **{tot_fdcs}** | — | **{dist_km}** |

- Customer projection: Y1 ~{cust_y1:,} (40%) · Y3 ~{cust_y3:,} (60%).
- ⚠ **POP1 ≈ 25 ra POP2 ≈ 28 PON required** (16-port OLT bhandaa dherai; FDC haru site-wise install hune bhayera spare splitter-port pani count vayeko) — tehaa **2nd 16-port OLT** (waa direct 32-port) rakhaa; BOM ma include gareko chha. Baaki POP haru 16-port le pugchha.
- HE (32-port) ma ≈ {pop_stats['HE']['pon_used']} PON used — Melamchi bazaar 5-6 FDC bata chalaanchha.
- POP oversubscription na huna laagi PON utilisation cap ~80% design gareko (FAT 8-fill x 65%).
- **POP3 note:** OSM ma Thakani ridge place-nodes thin chhan; POP3 ko DBs mostly POP6 sanga nearest paren. He-POP3 road (17.8 km) bhitra ko villages (Thakani, Dhuseni, Siwalaya) survey garera 6-10 DB thapne; ahileko POP3 spare capacity dherai chha.

## 6) Indicative BOM / cost (NPR, indicative 2026 market rates — quote lina parne)
| Item | Qty | Est. cost | Note |
|------|-----|----------:|------|
{rows}
| **TOTAL (approx)** | | **{lakh(capex)}** | ±30% |

## 7) Implementation phases
1. **Phase 1 (month 0-2):** Ring A+C shared HE-POP2, HE-POP5-POP4-HE (Ring B), Melamchi town DBs (550+ homes), POP4/POP5 feeders. ~60% of revenue potential.
2. **Phase 2 (month 2-4):** HE-POP3-POP6 + Haibung DBs, POP1 feeder Thangpal/Gunsakot, Ring C closure POP6-POP2.
3. **Phase 3 (month 4-6):** POP7 spur (34 km, river-valley route), Timbu/Dongdhing/Chhimi/Sermathang DBs, wireless backup live.

## 4) PON optical link budget (worst-case per POP; 1:8+1:8 = 64-way, 1310/1490 nm)
| POP | Farthest DB | Fiber (est) | Total loss | Budget (Class C+) | Verdict |
|-----|-------------|------------:|-----------:|------------------:|---------|
{lb_rows}
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
"""
open(f"{OUT}/FTTH_DESIGN_REPORT.md", "w").write(report)

print("="*70)
print(f"DB sites: {len(dbs)} | FAT boxes: {tot_fats} | FDC/PONs used: {tot_fdcs}")
print(f"Est homes passed: {tot_homes} | Y1: {cust_y1} | Y3: {cust_y3}")
print(f"Backbone: {backbone_km} road-km | Distribution: {dist_km} km design ({dist_road} km road-measured + {dist_trail} km trail-est; {n_trail_all} off-road DB spurs)")
for pop in LEGS["pops"]:
    st = pop_stats[pop]
    print(f"  {pop:5} homes={st['homes']:5} fats={st['fats']:3} pon={st['pon_used']:2}/{LEGS['pops'][pop]['pon']:2} dist={st['route_km']:6.2f} km")
print(f"Indicative capex: NPR {capex:,} (~{capex/100000:.0f} lakh)")
print("Files written to", OUT)
