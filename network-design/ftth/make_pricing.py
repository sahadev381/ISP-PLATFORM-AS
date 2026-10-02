#!/usr/bin/env python3
"""PRICING_BANDS.md + pricing_bands.csv: 3-band tariff model (Basic/Standard/Premium),
blended margin simulations, payback per mix. Also trail_survey_map.html print map."""
import csv, json, os

HERE = os.path.dirname(os.path.abspath(__file__)); OUT = os.path.join(HERE, "deliverables")
DR  = json.load(open(f"{OUT}/dist_routes.json"))
POPS= json.load(open(f"{OUT}/legs.json"))["pops"]
CL  = {c["cluster_id"]: c for c in json.load(open(f"{OUT}/clusters.json"))["clusters"]}
for r in csv.DictReader(open(f"{OUT}/db_points.csv")):
    cid = r["db_id"].replace("DB-","",1)
    if cid in CL: CL[cid]["homes"] = int(r["est_homes"])

# ------------- A) trail survey print map -------------
pop_latlon = {p: (POPS[p]["lat"], POPS[p]["lon"]) for p in DR}
pts, edges = [], []
for pop, v in DR.items():
    for e in v["edges"]:
        if e["mode"] != "trail": continue
        f = ("POP", pop_latlon[e["from"]]) if e["from"] in pop_latlon else \
            ("DB", (CL[e["from"]]["lat"], CL[e["from"]]["lon"]))
        t = ("DB", (CL[e["to"]]["lat"], CL[e["to"]]["lon"]))
        edges.append({"fr": e["from"], "to": e["to"], "a": f[1], "b": t[1], "km": e["km"],
                      "detour": e["km_raw"], "label": CL[e["to"]].get("label",""),
                      "homes": CL[e["to"]].get("homes", ""), "pop": pop})
html = """<!DOCTYPE html><html><head><meta charset="utf-8"><title>Trail-spur survey map (9 DBs)</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
body{font-family:system-ui;margin:14px}#map{height:560px;border:1px solid #999}
table{border-collapse:collapse;font-size:11px;margin-top:10px}
td,th{border:1px solid #aaa;padding:3px 6px}th{background:#f0f0f0}
h2{margin-bottom:2px}.small{color:#555;font-size:12px}
@media print{#map{height:440px}.noprint{display:none}}
</style></head><body>
<h2>FTTH off-road trail-spur survey map - 9 DBs</h2>
<div class="small">Orange dashed = trail spur (no drivable road in OSRM). Blue = parent node. Field walk then return measured_km to update dist_routes.json.</div>
<div id="map"></div><div id="tbl"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>
const EDGES = @EDGES@;
const map = L.map('map'); L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
const bounds=[];
EDGES.forEach(function(e,i){
  var pl=L.polyline([e.a,e.b],{color:'#e65100',weight:3,dashArray:'6 6'}); pl.addTo(map);
  pl.bindTooltip((i+1)+'. '+e.fr+' -> '+e.to+': '+e.km+' km trail-est (road detour '+e.detour+' km)');
  L.circleMarker(e.a,{radius:7,color:'#1565c0',weight:2,fillColor:'#ffffff',fillOpacity:1}).addTo(map).bindTooltip('PARENT: '+e.fr);
  L.circleMarker(e.b,{radius:8,color:'#e65100',weight:2,fillColor:'#ff9800',fillOpacity:.7}).addTo(map)
    .bindTooltip('DB '+e.to+' ('+e.label+') - '+e.homes+' homes; spur '+e.km+' km');
  L.marker(e.b,{icon:L.divIcon({className:'',html:'<b style="color:#e65100;text-shadow:0 0 3px #fff">'+(i+1)+'</b>',iconSize:[14,14]})}).addTo(map);
  bounds.push(e.a,e.b);
});
map.fitBounds(bounds,{padding:[30,30]});
document.getElementById('tbl').innerHTML = '<table><tr><th>#</th><th>POP</th><th>Spur from -> DB</th><th>DB locality</th><th>Straight-est km</th><th>Design km (trail)</th><th>Road detour km</th><th>Homes</th><th>Survey done (date / initials)</th><th>Measured km</th></tr>'
+ EDGES.map(function(e,i){return '<tr><td>'+(i+1)+'</td><td>'+e.pop+'</td><td>'+e.fr+' -> '+e.to+'</td><td>'+e.label+'</td><td>'+(e.km/1.1/1.6).toFixed(1)+'</td><td>'+e.km+'</td><td>'+e.detour+'</td><td>'+e.homes+'</td><td></td><td></td></tr>';}).join('')+'</table>';
</script></body></html>"""
open(f"{OUT}/trail_survey_map.html","w").write(html.replace("@EDGES@", json.dumps(edges)))

# ------------- B) pricing bands -------------
BANDS = [
 {"band": "Basic 30M",    "mbps": 30,  "arpu": 750,  "cogs": 160, "max_ratio": "1:8 fine", "ont": "1GE"},
 {"band": "Standard 60M", "mbps": 60,  "arpu": 1150, "cogs": 260, "max_ratio": "1:8 fine", "ont": "4GE WiFi 5G"},
 {"band": "Premium 100M","mbps": 100, "arpu": 1600, "cogs": 400, "max_ratio": "1:6 or 1:8", "ont": "4GE WiFi ac + OTT (NetTV)"},
]
for b in BANDS: b["margin"] = b["arpu"]*0.94 - b["cogs"]

MIXES = [
 ("Basic-heavy (rural default)", (.55,.35,.10)),
 ("Balanced (target Y2)",        (.40,.45,.15)),
 ("Upsell push (ott bundles)",   (.30,.50,.20)),
]
ph = {r["phase"]: float(r["est_cost_npr"]) for r in csv.DictReader(open(f"{OUT}/phased_capex.csv"))}
kop = 0.927
LEAN = [("P1-lean", ph["Phase1"]*kop-5_775_000, 0), ("P2-lean", ph["Phase2"]*kop-3_675_000, 12),
        ("P3-lean", ph["Phase3"]*kop-1_050_000, 24), ("P4 tail DBs", 10_500_000, 48)]
AVGL = [int(x*kop) for x in [1600, 3600, 4500, 5000, 5200]]

def run(margin_sub, avg):
    cash = 0.0; pay = None; OPEX = {0:570_000, 12:810_000, 24:920_000}
    for m in range(96):
        subs = avg[min(m//12, 4)]
        if m < 6: subs = int(subs*m/6)
        ebitda = subs*margin_sub - max((v for k, v in OPEX.items() if m >= k), default=570_000)
        for _, amt, mm in LEAN:
            if mm == m: cash -= amt
        prev = avg[min((m-1)//12, 4)] if m else 0
        cash -= max(0, subs-prev)*1500; cash += ebitda
        if pay is None and cash > 0: pay = m+1
    return pay

rows = []
for name, mix in MIXES:
    arpu  = sum(s*b["arpu"]  for s, b in zip(mix, BANDS))
    cogs  = sum(s*b["cogs"]  for s, b in zip(mix, BANDS))
    marg  = arpu*0.94 - cogs
    rows.append((name, mix, round(arpu), round(cogs), round(marg, -1), run(marg, AVGL)))

doc = ["# 3-band pricing model (indicative - confirm against local competition)\n",
"## Band sheet",
"| Band | Up/Down | Price (NPR/mo) | Indicative COGS | Net margin (6% govt. up) | Equipment |",
"|--|--|--:|--:|--:|---|"]
for b in BANDS:
    doc.append(f"| {b['band']} | {b['mbps']}/{b['mbps']} Mbps | {b['arpu']:,} | {b['cogs']} | {b['margin']:.0f} | {b['ont']} |")
doc += ["\n## Band-mix scenarios (payback on lean phasing, 8-yr model, design take-rate)",
"| Mix (Basic/Std/Prem) | Blended ARPU | Blended net margin/sub | Payback |","|--|--:|--:|--|"]
for name, mix, arpu, cogs, marg, pay in rows:
    p = f"**month {pay}** (~{pay//12}y{pay%12}m)" if pay else ">8y"
    doc.append(f"| {name} - {int(mix[0]*100)}/{int(mix[1]*100)}/{int(mix[2]*100)}% | {arpu:,} | {marg:,.0f} | {p} |")
doc += ["\n## Operating guidance",
"1. Launch ALL bands day one (nobody sells the band you do not offer); advertise Standard as the headline - anchors Basic up.",
"2. Marginal Standard->Premium upgrade = +NPR ~{:.0f} margin/sub for zero extra pole-km: push speed-test + OTT bundles at billing month 6.".format(BANDS[2]["margin"]-BANDS[1]["margin"]),
"3. FiO (FIBER+IVR+OTT) bundles: voice via existing NTC interconnect SIP at POP2; OTT via NetTV/DISH Go resale margin NPR 80-120/sub.",
"4. Keep the 16 deferred tail DBs as monthly target review: once monthly-activated demand at a trailing DB passes ~40 paid commitments, trigger its P4 build - the trail survey returns what that costs.",
"5. Payback ceiling check vs competitor price floor (NTC FTTH ~NPR 750 baseline): if county price war pulls Basic below 650, run the make_payback.py stress case before shedding more early capex.",
"\n> All numbers indicative planning values; replace COGS with supplier quotes and book 10% marketing budget in Year-1 (noted in PAYBACK_MODEL.md not yet included)."]
open(f"{OUT}/PRICING_BANDS.md","w").write("\n".join(doc))
with open(f"{OUT}/pricing_bands.csv","w",newline="") as f:
    w=csv.writer(f); w.writerow(["band","mbps","price_npr","cogs_npr","net_margin_npr","max_split_ratio","equipment"])
    for b in BANDS: w.writerow([b["band"],b["mbps"],b["arpu"],b["cogs"],round(b["margin"]),b["max_ratio"],b["ont"]])
    w.writerow([]); w.writerow(["mix","basic%","std%","prem%","blended_arpu","blended_margin","payback_months"])
    for name, mix, arpu, cogs, marg, pay in rows:
        w.writerow([name,*[int(s*100) for s in mix],arpu,round(marg,-1),pay])

print("trail_spy_map:", len(edges), "spurs")
for r in rows: print(f"{r[0]:34} ARPU {r[2]:>5} margin {r[4]:>6,.0f} -> payback {r[5]}")
