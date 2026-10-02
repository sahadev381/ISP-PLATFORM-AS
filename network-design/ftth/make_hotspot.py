#!/usr/bin/env python3
"""TRAIL_SURVEY.md + trail_survey_checklist.csv + HOTSPOT_OPTIMIZATION.md
- survey checklist for the 9 off-road (trail) DB spurs
- hotspot priority ranking: homes per design path-km of feeder fiber
- lean phasing: defer trail spurs + worst road detours -> ARPU-900 payback recompute"""
import json, csv, math, os, collections

HERE = os.path.dirname(os.path.abspath(__file__)); OUT = os.path.join(HERE, "deliverables")
D  = json.load(open(f"{OUT}/clusters.json"))
DR = json.load(open(f"{OUT}/dist_routes.json"))
POPS = json.load(open(f"{OUT}/legs.json"))["pops"]

HAS_FDC_PT = 9000      # FDC + PON card amortized per DB site
FAT_HW     = 2400      # 8-port FAT + splitters
FIBER_ROAD = 120_000   # NPR/km: 12F ADSS aerial + labour + pole share
FIBER_TRAIL= 110_000   # NPR/km market labour, pole-heavy trail
OPEX_KEEP  = 0.10      # keep-alive for deferred trunk (none, hardware later)

cl = {c["cluster_id"]: c for c in D["clusters"]}
dp = {}
for r in csv.DictReader(open(f"{OUT}/db_points.csv")):
    dp[r["db_id"].replace("DB-","",1)] = {"homes": int(r["est_homes"]), "fats": int(r["fat_boxes"]), "label": r["locality"]}
for cid, c in cl.items():
    if cid in dp: c.update(dp[cid])

# ---------- 1) trail survey checklist ----------
rows, doc = [], ["# Off-road trail-spur survey checklist (9 DB spurs)\n",
"These DBs show **no drivable road** in OSRM — the design carries a straight-line x1.6 trail estimate (+10% slack).",
"Walk the spur, decide pole-vs-asl path, close the loop to the listed parent node, and update `dist_routes.json` before ordering cable.\n",
"| POP | Spur (from -> DB) | DB locality | Straight km | Design km (trail-est) | Impractical road detour | Homes | FAT/DB | Priority |",
"|--|--|--|--:|--:|--:|--:|--:|---|"]
for pop, v in DR.items():
    for e in v["edges"]:
        if e["mode"] != "trail": continue
        c = cl.get(e["to"], {})
        rows.append({"pop": pop, "parent": e["from"], "db": e["to"], "locality": c.get("label",""),
                     "lat": c.get("lat",""), "lon": c.get("lon",""),
                     "straight_km": e["km_straight"], "design_km": e["km"],
                     "road_detour_km": e["km_raw"], "homes": c.get("homes",""), "fats": c.get("fats",""),
                     "survey_done": "", "measured_km": "", "terrain_note": "", "alt_path(road?)": ""})
        doc.append(f"| {pop} | {e['from']} -> {e['to']} | {c.get('label','?')} | {e['km_straight']} | {e['km']} | "
                   f"{e['km_raw']} | {c.get('homes','?')} | {c.get('fats','?')} | "
                   f"{'HIGH' if c.get('homes',0) >= 120 else ('MED' if c.get('homes',0) >= 60 else 'LOW')} |")
doc += ["\n## Survey guide per spur",
"1. Mark day-pot poles every 40-45 m; note river/bluff crossings and which side the trunk pole line should follow.",
"2. If a motorable track exists within 1.5x straight-line km, switch edge back to `road` mode with measured km.",
"3. Check the DB end: safe pole stand at FAT cluster (flood/landslide clear), 3-m guy clearance.",
"4. Photograph every 200 m; geotag; record kandos for crossing owner permission (Rural Municipality).",
"5. Output: measured_km + pole count + splice-point notes -> engineer will re-run `make_dist_routes.py`.\n",
"> Rule of thumb: 1 trail-km ≈ 22-25 poles; budget NPR ~1.1-1.3 lakh/km for labour+material on trail terrain (solar carry)."]
open(f"{OUT}/TRAIL_SURVEY.md","w").write("\n".join(doc))
with open(f"{OUT}/trail_survey_checklist.csv","w",newline="") as f:
    w = csv.DictWriter(f, fieldnames=list(rows[0].keys())); w.writeheader(); w.writerows(rows)

# ---------- 2) hotspot priority ----------
hc = []
for pop, v in DR.items():
    for e in v["edges"]:
        c = cl.get(e["to"]); 
        if not c: continue
        km = max(e["km"], 0.3)
        hc.append({"db": e["to"], "pop": pop, "label": c["label"], "homes": c["homes"], "fats": c["fats"],
                   "path_km": DR[pop]["path_km"][e["to"]], "edge_km": e["km"], "mode": e["mode"],
                   "eff": c["homes"]/km, "eff_path": c["homes"]/max(DR[pop]["path_km"][e["to"]],0.5)})
hc.sort(key=lambda r: -r["eff"])
for i, r in enumerate(hc, 1): r["rank"] = i
with open(f"{OUT}/hotspot_priority.csv","w",newline="") as f:
    w = csv.DictWriter(f, fieldnames=["rank","db","pop","label","homes","fats","edge_km","path_km","mode","eff","eff_path"])
    w.writeheader(); w.writerows(hc)

# ---------- 3) lean phasing -> payback @900 recompute ----------
covered = sorted(hc, key=lambda r: -r["eff"])
homes_tot = sum(r["homes"] for r in covered)
sel_h, sel = [], []
hacc = 0
for r in covered:                       # greedy until 92% homes
    if hacc >= 0.92*homes_tot: break
    sel.append(r); hacc += r["homes"]
cut_ids = {r["db"] for r in covered if r not in sel and r["mode"] == "trail"}
# defer = all trail spurs + the ~8% least-efficient road DBs
defer = [r for r in covered if r not in sel] + [r for r in covered if r["db"] in cut_ids and r in sel]
seen = set(); defer = [r for r in defer if not (r["db"] in seen or seen.add(r["db"]))]
d_homes  = sum(r["homes"] for r in defer)
d_fats   = sum(r["fats"] for r in defer)
d_km     = sum(r["edge_km"] for r in defer)
d_cost   = int(d_km*110_000 + d_fats*FAT_HW + len(defer)*HAS_FDC_PT)
keep_h   = homes_tot - d_homes
kop      = keep_h/homes_tot

ph = {r["phase"]: float(r["est_cost_npr"]) for r in csv.DictReader(open(f"{OUT}/phased_capex.csv"))}
CAPEX_B = [("P1", ph["Phase1"], 0), ("P2", ph["Phase2"], 12), ("P3", ph["Phase3"]+5_000_000, 24)]
CAPEX_L = [("P1-lean", ph["Phase1"]*kop - d_cost*0.55, 0), ("P2-lean", ph["Phase2"]*kop - d_cost*0.35, 12),
           ("P3-lean", ph["Phase3"]*kop - d_cost*0.10, 24), ("P4 = trail/tail DBs", d_cost, 48)]

AVG_B = [1600, 3600, 4500, 5000, 5200]
AVG_L = [int(x*kop) for x in AVG_B]
def run(arpu, capex, avg):
    cash = 0.0; payback = None; CPE_NET = 1600; COGS = 220; OPEX = {0:570_000,12:810_000,24:920_000}
    for m in range(72):
        subs = avg[min(m//12, 4)]
        if m < 6: subs = int(subs*m/6)
        ebitda = subs*arpu*0.94 - subs*COGS - max((v for k, v in OPEX.items() if m >= k), default=570_000)
        for _, amt, mm in capex:
            if mm == m: cash -= amt
        prev = avg[min((m-1)//12, 4)] if m else 0
        cash -= max(0, subs-prev)*1500; cash += ebitda
        if payback is None and cash > 0: payback = m+1
    return payback

pb900_b = run(900, CAPEX_B, AVG_B); pb900_l = run(900, CAPEX_L, AVG_L)
pb1100_b = run(1100, CAPEX_B, AVG_B); pb1100_l = run(1100, CAPEX_L, AVG_L)

top10 = hc[:10]
hdoc = ["# Hotspot-first phasing: make ARPU-900 payback work\n",
f"Homes total: {homes_tot}. Ranking = homes per design feeder-km (edge cost, see hotspot_priority.csv).",
f"Cheap tier (top {len(sel)} DBs) covers {keep_h} homes ({100*keep_h/homes_tot:.0f}%).\n",
f"## Deferred tail (Phase-4, demand-triggered)",
f"- DBs: {len(defer)} ({', '.join(sorted(r['db'] for r in defer))})",
f"- Homes deferred: {d_homes} ({100*d_homes/homes_tot:.1f}%), feeder km deferred: {d_km:.1f}",
f"- Capex deferred: NPR {d_cost/1e6:.1f}M -> booked as Phase-4 at month 48 (or when take-rate proves demand)",
f"- Capex in P1-P3 reduced proportionally to kept-home share ({100*kop:.1f}%).\n",
"## Payback (NPR, indicative)",
"| Scenario | ARPU 900 | ARPU 1100 |",
"|--|--|--|",
f"| Base phasing | {'month '+str(pb900_b) if pb900_b else '>6y (NO)' } | month {pb1100_b} |",
f"| Lean + Phase-4 tail | {'month '+str(pb900_l) if pb900_l else '>6y (NO)'} | month {pb1100_l} |\n",
"## Top-10 hotspot DBs (build-first)",
"| Rank | DB | POP | Locality | Homes | Edge km | Homes/km |","|--|--|--|--|--:|--:|--:|"]
for r in top10:
    hdoc.append(f"| {r['rank']} | {r['db']} | {r['pop']} | {r['label']} | {r['homes']} | {r['edge_km']} | {r['eff']:.0f} |")
hdoc += ["\n## Recommendation",
"1. **Order phase**: POP4/POP5-side + HE + POP1/POP2 top-efficiency DBs first (P1 allocates homes/km, not geography).",
"2. **Trail spurs**: build only after `TRAIL_SURVEY.md` returns measured routes; hold their FDC+FAT hardware in Phase-4.",
"3. If survey finds a motorable alternate for a spur with <2x straight km, promote it back to its original phase.",
"4. Re-run `make_payback.py` after real quotes; every NPR 1M shaved ≈ 1-2 months payback at ARPU 900."]
open(f"{OUT}/HOTSPOT_OPTIMIZATION.md","w").write("\n".join(hdoc))

print(f"trail spurs listed: {len(rows)} | hotspot rows: {len(hc)}")
print(f"deferred: {len(defer)} DBs, {d_homes} homes, {d_km:.1f} km, NPR {d_cost/1e6:.1f}M")
print(f"payback@900: base={pb900_b} lean={pb900_l} | @1100: base={pb1100_b} lean={pb1100_l}")
print("wrote TRAIL_SURVEY.md, trail_survey_checklist.csv, hotspot_priority.csv, HOTSPOT_OPTIMIZATION.md")
