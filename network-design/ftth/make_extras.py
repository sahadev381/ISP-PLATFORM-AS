#!/usr/bin/env python3
"""Generate: platform SQL seed, splice plan, IP/VLAN plan, wireless budget, phased capex."""
import json, math, csv, os, collections

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "deliverables")
LEGS = json.load(open(f"{OUT}/legs.json"))
D = json.load(open(f"{OUT}/clusters.json"))
COUNTS = json.load(open(f"{OUT}/building_counts.json"))
POPS = LEGS["pops"]

pops_rows = list(csv.DictReader(open(f"{OUT}/pops.csv")))
dbs = list(csv.DictReader(open(f"{OUT}/db_points.csv")))
pon_alloc = list(csv.DictReader(open(f"{OUT}/pon_allocation.csv")))
dist_edges = [r for r in csv.DictReader(open(f"{OUT}/fiber_segments.csv")) if r["type"].startswith("distribution")]

HOME_FACTOR, HOMES_PER_FAT, FATS_PER_PON = 0.75, 12, 8

# ---------- per-site FAT layout ----------
site_fats = {}   # db_id -> fats
for d in dbs: site_fats[d["db_id"]] = int(d["fat_boxes"])
fdc_struct = collections.defaultdict(list)  # pop -> list of (fdc_id, db_id, fats_in_fdc)
for r in pon_alloc:
    fdc_struct[r["pop"]].append((r["fdc_id"], r["db_site"], int(r["fats_in_fdc"])))

# ======================================================================
# 1) PLATFORM SQL SEED  (ftth_nodes / port_assignments / fiber_routes)
# ======================================================================
N = []   # nodes (id, name, type, lat, lng, capacity, meta)
P = []   # port_assignments (node_id, port_no, linked_node_id, port_name)
R = []   # fiber_routes
nid = 1
olt_ids = {}
for pop in ["HE","POP1","POP2","POP3","POP4","POP5","POP6","POP7"]:
    p = POPS[pop]
    st = next(s for s in pops_rows if s["pop"] == pop)
    N.append((nid, f"OLT-{pop}", "OLT", p["lat"], p["lon"], p["pon"],
              {"pop": pop, "label": p["label"], "pon_used": int(st["pon_used"]), "est_homes": int(st["est_homes"])}))
    for port in range(1, p["pon"]+1): P.append((nid, port, None, f"PON{port}"))
    olt_ids[pop] = nid; nid += 1
# second OLT for POP1/POP2 (virtual extra ports beyond 16)
extra_used = {}
for pop in ["POP1","POP2"]:
    st = next(s for s in pops_rows if s["pop"] == pop)
    used = int(st["pon_used"])
    if used > 16:
        p = POPS[pop]
        need = used - 16
        N.append((nid, f"OLT-{pop}-2nd", "OLT", p["lat"], p["lon"], 16,
                  {"pop": pop, "note": f"second 16-port OLT ({need} PON used)", "est_homes": 0}))
        for port in range(1, 17): P.append((nid, port, None, f"PON{port}"))
        extra_used[pop] = (nid, 16)
        nid += 1

# MASTER_BOX (FDC) + DB_BOX (FAT); link OLT port -> FDC; FDC port -> FAT
for pop, fdcs in fdc_struct.items():
    port_no = 0
    for fdc_id, db_id, fats_in in fdcs:
        d = next(x for x in dbs if x["db_id"] == db_id)
        st = next(s for s in pops_rows if s["pop"] == pop)
        port_no += 1
        this_port = port_no
        olt_node = olt_ids[pop]
        if pop in extra_used and this_port > 16:
            this_port = this_port - 16
            olt_node = extra_used[pop][0]
        fdc_nid = nid
        N.append((fdc_nid, f"{fdc_id}", "MASTER_BOX", float(d["lat"]), float(d["lon"]), 8,
                  {"pop": pop, "db_site": db_id, "locality": d["locality"], "splitter": "1:8"}))
        P = [x for x in P]  # keep
        for fp in range(1, 9):
            P.append((fdc_nid, fp, None, f"OUT{fp}"))
        # link OLT port -> FDC node
        P = [(n, pnu, (fdc_nid if (n == olt_node and pnu == this_port) else lk), nm) for (n, pnu, lk, nm) in P]
        # FAT boxes under this FDC
        for k in range(fats_in):
            fat_nid = nid + 1
            N.append((fat_nid, f"{db_id}-FAT{k+1}", "DB_BOX", round(float(d["lat"]) + 0.0003*k, 6), round(float(d["lon"]) + 0.0003*k, 6), 8,
                      {"pop": pop, "fdc": fdc_id, "site": db_id, "locality": d["locality"], "splitter": "1:8"}))
            for cp in range(1, 9):
                P.append((fat_nid, cp, None, f"CUST{cp}"))
            # link FDC port (k+1) -> FAT node
            P = [(n, pnu, (fat_nid if (n == fdc_nid and pnu == k+1) else lk), nm) for (n, pnu, lk, nm) in P]
            nid = fat_nid
        nid = fdc_nid if nid < fdc_nid else nid
        nid += 1

# fiber routes: backbone
for l in LEGS["legs"]:
    a, b = POPS[l["a"]], POPS[l["b"]]
    loss = round(0.35*l["km"] + 0.6, 2)
    R.append((f"BACKBONE {l['a']}-{l['b']} ({l['group']}) {l['core']}F",
              [[a["lat"], a["lon"]], [b["lat"], b["lon"]]], round(l["km"]*1000), loss, l["core"]))
R.append((f"WIRELESS-BACKUP HE-POP7 ~{LEGS['backup_wireless']['km_straight']} km LOS (survey)",
          [[POPS['HE']['lat'], POPS['HE']['lon']], [POPS['POP7']['lat'], POPS['POP7']['lon']]], 14100, 0, 0))
# distribution edges
for e in dist_edges:
    f_ = e["from"]; t_ = e["to"]
    def coord(x):
        if x in POPS: return [POPS[x]["lat"], POPS[x]["lon"]]
        dd = next((z for z in dbs if z["db_id"] == x), None)
        return [float(dd["lat"]), float(dd["lon"])] if dd else None
    c1, c2 = coord(f_), coord(t_)
    if c1 and c2:
        R.append((f"DIST {e['segment']} {f_}->{t_}", [c1, c2], round(float(e["km"])*1000), round(0.35*float(e["km"])+1.2, 2), 12))

def vals(rows, fmt):
    return ",\n".join("(" + ", ".join(fmt(v) for v in r) + ")" for r in rows)

qstr = lambda s: "'" + str(s).replace("'", "''") + "'"
sql = []
sql.append("-- FTTH design seed for ISP-PLATFORM (ftth_nodes, port_assignments, fiber_routes)")
sql.append("-- Generated from network-design/ftth (design 2026-10-02). Review before running!")
sql.append("-- For a CLEAN import on a fresh platform you may uncomment:")
sql.append("-- SET FOREIGN_KEY_CHECKS=0; TRUNCATE ftth_nodes; TRUNCATE port_assignments; TRUNCATE fiber_routes; SET FOREIGN_KEY_CHECKS=1;")
sql.append("-- NOTE: ids start at 1; adjust if tables already contain data.\n")
CH = 400
node_rows = [(i, qstr(nm), qstr(tp), la, lo, cap, qstr(json.dumps(meta, ensure_ascii=False))) for (i, nm, tp, la, lo, cap, meta) in N]
for i in range(0, len(node_rows), CH):
    sql.append("INSERT INTO ftth_nodes (id, name, type, lat, lng, capacity, metadata) VALUES\n" +
               vals(node_rows[i:i+CH], str) + ";")
port_rows = [(n, p, ("NULL" if lk is None else lk), qstr(nm)) for (n, p, lk, nm) in P]
for i in range(0, len(port_rows), CH):
    sql.append("INSERT INTO port_assignments (node_id, port_number, linked_node_id, port_name) VALUES\n" +
               vals(port_rows[i:i+CH], str) + ";")
route_rows = [(qstr(nm), qstr(json.dumps(path)), m, loss, cores) for (nm, path, m, loss, cores) in R]
for i in range(0, len(route_rows), CH):
    sql.append("INSERT INTO fiber_routes (name, path_data, calculated_length_m, predicted_loss_db, total_cores) VALUES\n" +
               vals(route_rows[i:i+CH], str) + ";")
open(f"{OUT}/ftth_seed.sql", "w").write("\n\n".join(sql))
fat_total = sum(1 for x in N if x[2] == "DB_BOX")
print(f"SQL seed: {len(N)} nodes (10 OLT, {len([x for x in N if x[2]=='MASTER_BOX'])} FDC, {fat_total} FAT), {len(port_rows)} ports, {len(route_rows)} routes")

# ======================================================================
# 2) FIBER SPLICE / CORE PLAN
# ======================================================================
splice_rows = [
 ("HE ODF", "Ring A east arm (to POP2)", "1-4", "POP2 drop/through", "all 48 terminate on ODF"),
 ("HE ODF", "Ring C west arm (to POP3)", "5-8", "POP3 drop/through", "separate cable recommended on shared HE-POP2"),
 ("HE ODF", "Ring B south arm (to POP5)", "9-12", "POP5 drop/through", ""),
 ("HE ODF", "POP7 spur (24F cable)", "1-4", "POP7", "wireless = path protection"),
 ("HE ODF", "Spare dark", "13-48", "-", "future expansion / enterprise lease"),
 ("POP2 closure", "Ring A: from HE / to POP1", "1-4", "POP2 x4 drop (2 each arm)", "44F express, ~16 splices"),
 ("POP2 closure", "Ring C: from HE(2nd) / to POP6", "5-8", "POP2 x4 drop", "44F express"),
 ("POP1 closure", "Ring A: from POP2 / back to HE", "1-8", "POP1 x4 drop (cores 3+7... see plan)", "attenuation check C+ OK"),
 ("POP5 closure", "Ring B: from HE / to POP4", "9-12", "POP5 x4 drop", "44F express"),
 ("POP4 closure", "Ring B: from POP5 / back to HE", "13-16", "POP4 x4 drop", "44F express"),
 ("POP3 closure", "Ring C: from POP2 / to HE side", "5-8", "POP3 x4 drop", "44F express"),
 ("POP6 closure", "Ring C: from POP3 / to POP2", "5-8 alt", "POP6 x4 drop", "44F express"),
 ("POP7 closure", "Spur from HE (24F)", "1-4", "POP7 x2 active + 2 spare", "20F dark spare"),
]
with open(f"{OUT}/splice_plan.csv", "w", newline="") as f:
    w = csv.writer(f); w.writerow(["location","cable/arm","cores","usage","note"])
    w.writerows(splice_rows)

# ======================================================================
# 3) PHASED CAPEX
# ======================================================================
U = {"bb48": 95, "bb24": 55, "d12": 32, "pole": 5500, "lab_km": 32000, "fdc": 9500, "fat": 2400, "olt16": 350000, "olt32": 650000, "sfp": 2200, "upl": 24000, "pwr": 220000, "l3": 150000, "drop": 2800, "ont": 2300, "clos": 3200}
PH = {"Phase1": {"legs": [("HE","POP2"),("HE","POP5"),("POP5","POP4"),("POP4","HE")], "pops": ["HE","POP2","POP4","POP5"], "dbs": ["HE","POP2","POP4","POP5"], "olt16": 2, "olt32": 1, "extra": "Ring A shared leg + Ring B + Melamchi/POP4/POP5 DBs"},
      "Phase2": {"legs": [("HE","POP3"),("POP3","POP6"),("POP6","POP2"),("POP2","POP1"),("POP1","HE")], "pops": ["POP1","POP3","POP6"], "dbs": ["POP1","POP3","POP6"], "olt16": 4, "olt32": 0, "extra": "Ring A+C closure, Thangpal/Haibung DBs, 2nd OLT POP1+POP2"},
      "Phase3": {"legs": [("HE","POP7")], "pops": ["POP7"], "dbs": ["POP7"], "olt16": 1, "olt32": 0, "extra": "Timbu spur + wireless backup + solar"}}
legmap = {(l["a"], l["b"]): l for l in LEGS["legs"]}
legmap.update({(l["b"], l["a"]): l for l in LEGS["legs"]})
st_by_pop = {s["pop"]: s for s in pops_rows}
rows, totals = [], {}
for ph, cfg in PH.items():
    bb = sum(legmap[t]["km"] for t in cfg["legs"])
    bb24 = sum(legmap[t]["km"] for t in cfg["legs"] if legmap[t]["core"] == 24)
    bb48 = bb - bb24
    dkm = sum(float(st_by_pop[p]["dist_route_km"]) for p in cfg["dbs"])
    fats = sum(int(st_by_pop[p]["fats"]) for p in cfg["dbs"])
    fdcs = sum(int(st_by_pop[p]["pon_used"]) for p in cfg["dbs"])
    homes = sum(int(st_by_pop[p]["est_homes"]) for p in cfg["dbs"])
    cust_y1 = round(homes*0.55)   # ~55% of Y1 customers land in earlier phases handled by drop/ont per sub
    cost = (bb48*1.0615*1000*(U["bb48"]+32) + bb24*1.06*1000*(U["bb24"]+32)
            + dkm*1.0833*1000*(U["d12"]+32)
            + math.ceil((bb+dkm*0.5)*25)*U["pole"]
            + fdcs*U["fdc"] + fats*U["fat"] + (fdcs+fats+4)*U["clos"]
            + cfg["olt16"]*U["olt16"] + cfg["olt32"]*U["olt32"]
            + (fdcs+10)*U["sfp"] + 4*U["upl"] + len(cfg["pops"])*U["pwr"] + len(cfg["pops"])*U["l3"])
    rows.append([ph, cfg["extra"], round(bb,1), round(dkm,1), fdcs, fats, homes, cfg["olt16"]+cfg["olt32"], round(cost)])
    totals[ph] = cost
with open(f"{OUT}/phased_capex.csv", "w", newline="") as f:
    w = csv.writer(f); w.writerow(["phase","scope","backbone_km","dist_km","fdcs","fats","homes_passed","olts","est_cost_npr"]); w.writerows(rows)
for k, v in totals.items(): print(k, f"NPR {v:,.0f}")

# ======================================================================
# 4) Markdown docs: splice plan, IP/VLAN, wireless, phased
# ======================================================================
splice_md = """# Fiber Core & Splice Plan (48F rings / 24F spur)

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
""" + "\n".join(f"| {l} | {c} | {co} | {u} — {n} |" for l, c, co, u, n in splice_rows) + """

## Field rules
- Splice loss target ≤0.05 dB per fusion joint; closure attenuation test OTDR both directions @1310/1550.
- Slack: 30 m at every closure, 60 m at POP closures; loop on pole above joint.
- HE-POP2 (5.85 km) gets TWO independent 48F cables (Ring A + Ring C both originate here) — core plan above is per-cable.
- POP7 spur: 24F; fibers 1-2 active duplex, 3-4 spare, 5-24 dark.
"""
open(f"{OUT}/FIBER_SPLICE_PLAN.md", "w").write(splice_md)

ip_md = """# IP & VLAN Plan (GPON + Rings)

## Routing
- L3 ring with OSPF process 1 area 0.0.0.0 + BFD (failover <200 ms). L2 RSTP only as fallback.
- Loopbacks: HE=10.10.0.1/32, POP1..POP7=10.10.0.11-17/32 (router-id=loopback).

## Point-to-point /31s (per ring arm)
| Arm | Subnet | Arm | Subnet |
|-----|--------|-----|--------|
| HE-POP2 (RingA) | 10.10.1.0/31 | HE-POP5 | 10.10.1.8/31 |
| POP2-POP1 | 10.10.1.2/31 | POP5-POP4 | 10.10.1.10/31 |
| POP1-HE | 10.10.1.4/31 | POP4-HE | 10.10.1.12/31 |
| HE-POP2 (RingC, 2nd cable) | 10.10.1.6/31 | POP3-HE | 10.10.1.20/31 |
| POP2-POP6 | 10.10.1.16/31 | HE-POP7 fiber | 10.10.1.24/31 |
| POP6-POP3 | 10.10.1.18/31 | HE-POP7 wireless | 10.10.1.26/31 |

OSPF costs: wireless spur 100 (backup-only), fiber 10. ECMP off (deterministic paths).

## VLANs
- 100 MGMT: OLT/switch mgmt, per-POP /25: HE 10.20.0.0/25, POP1 10.20.1.0/25 ... POP7 10.20.7.0/25
- 200 VOIP (reserved), 300 IPTV (reserved)
- Customer (per-PON, per-POP numbered): HE=1000+PON#, POP1=1100+, POP2=1200+, POP3=1300+, POP4=1400+, POP5=1500+, POP6=1600+, POP7=1700+
  → e.g. DB-POP2 site off PON3 = VLAN 1203; customer PPPoE to central BNG (existing ISP platform has RADIUS/user mgmt).
- OLT side: each PON = S-VLAN (above), C-VLAN 10-20 optional per service; ONU profile: router mode OFF (bridge) — PPPoE from customer router.

## IPv6
- Example allocation 2400:xxxx::/40: /48 per POP (HE=:0000, POP1=:0001, ...), /64 per VLAN, DHCPv6-PD /56 per customer.

## Sample MikroTik (POP2, excerpt)
```
/interface vlan add name=v100-mgmt vlan-id=100 interface=sfp1
/routing ospf instance add name=main router-id=10.10.0.12
/routing ospf area add name=backbone instance=main area-id=0.0.0.0
/routing ospf interface-template add networks=10.10.1.0/31 area=backbone type=ptp bfd=yes
/routing ospf interface-template add networks=10.10.1.2/31 area=backbone type=ptp bfd=yes
/routing ospf interface-template add networks=10.10.1.6/31 area=backbone type=ptp bfd=yes
/ip address add address=10.10.0.12/32 interface=lo0
```
"""
open(f"{OUT}/IP_VLAN_PLAN.md", "w").write(ip_md)

# wireless budget
D_KM, F_GHZ, TX, G = 14.1, 5.8, 20, 30  # km, GHz, dBm, antenna dBi each
r1 = math.sqrt((3e8/(F_GHZ*1e9))*(D_KM*500)*(D_KM*500)/(D_KM*1000))
fspl = 32.44 + 20*math.log10(D_KM) + 20*math.log10(F_GHZ*1000)
rsl = TX + 2*G - fspl
wl_md = f"""# POP7 (Timbu) Wireless Backup — Path Check HE ↔ POP7

- Distance: {D_KM} km straight. Terrain: Melamchi khola river corridor (north-south), likely LOS; **walk/drone survey + path profile mandatory** (mid-path saddle check between Melamchi bazaar ~870 m and Timbu ~2000 m).
- 1st Fresnel radius at mid-path (5.8 GHz): **{r1:.1f} m** → require ≥60% clearance = **{0.6*r1:.1f} m** above obstructions both ends (plan 25-40 m masts/tree clearance).
- FSPL(5.8 GHz, {D_KM} km): {fspl:.0f} dB
- Budget example (airFiber 5XHD/AF-5G30): TX {TX} dBm + 2×{G} dBi dish − {fspl:.0f} dB = **RSL ≈ {rsl:+.0f} dBm** → fade margin ~{rsl+80:.0f} dB vs −80 dBm sens → excellent (target ≥15-20 dB).
- Capacity: 400-700 Mbps real (more than POP7's 16×2.5G PON day-1 need aggregated via shaping; run as L3 backup /31 with OSPF cost 100).
- Alternative: Ubiquiti AF-11X licensed 11 GHz for higher reliability in monsoon (rain fade @5/6 GHz low, 11 GHz moderate).
"""
open(f"{OUT}/WIRELESS_BACKUP_POP7.md", "w").write(wl_md)

ph_md = "# Phase-wise CAPEX (indicative, NPR)\n\n| Phase | Scope | Backbone km | Dist km | FDC | FAT | Homes | OLTs | Est. cost |\n|---|---|--:|--:|--:|--:|--:|--:|--:|\n"
for r in rows:
    ph_md += f"| {r[0]} | {r[1]} | {r[2]} | {r[3]} | {r[4]} | {r[5]} | {r[6]} | {r[7]} | {r[8]:,.0f} |\n"
ph_md += f"\nTotal (passive+active excl. customer side): NPR {sum(totals.values()):,.0f}\nCustomer-side drops+ONT as connected (Y1 ≈ NPR {3224*(U['drop']+U['ont']):,.0f} additional, usually recovered via install charges).\n"
open(f"{OUT}/PHASED_CAPEX.md", "w").write(ph_md)
print("docs written: FIBER_SPLICE_PLAN.md, IP_VLAN_PLAN.md, WIRELESS_BACKUP_POP7.md, PHASED_CAPEX.md, splice_plan.csv, phased_capex.csv, ftth_seed.sql")
