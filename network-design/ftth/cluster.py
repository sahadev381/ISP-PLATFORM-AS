#!/usr/bin/env python3
"""Cluster settlements -> DB candidate points, assign to nearest POP."""
import math, json, os
from data_settlements import SETTLEMENTS

POPS = {
 "HE":   {"lat":27.829537390474155,"lon":85.57499272608827,"pon":32,"type":"headend","label":"Melamchi Bazaar (Headend/DC)"},
 "POP1": {"lat":27.885702646495595,"lon":85.63752691551393,"pon":16,"type":"pop","label":"Thangpal/Larke khola corridor"},
 "POP2": {"lat":27.850960058830545,"lon":85.54447626218794,"pon":16,"type":"pop","label":"North of Melamchi (Helambu road)"},
 "POP3": {"lat":27.827761887767213,"lon":85.48634873615820,"pon":16,"type":"pop","label":"Haibung / Thakani ridge"},
 "POP4": {"lat":27.768840004877315,"lon":85.52522143691486,"pon":16,"type":"pop","label":"Chauki Bjy. / Dubachaur south"},
 "POP5": {"lat":27.739832022953937,"lon":85.60296683890036,"pon":16,"type":"pop","label":"Indrawati south / Baluwapati"},
 "POP6": {"lat":27.828876819580096,"lon":85.44831652699213,"pon":16,"type":"pop","label":"Chisapani (Haibung west)"},
 "POP7": {"lat":27.953790941984032,"lon":85.54790792673954,"pon":16,"type":"pop","label":"Timbu (Helambu)"},
}

R = 6371.0
def hav(a_lat, a_lon, b_lat, b_lon):
    la1, lo1, la2, lo2 = map(math.radians, (a_lat, a_lon, b_lat, b_lon))
    h = math.sin((la2-la1)/2)**2 + math.cos(la1)*math.cos(la2)*math.sin((lo2-lo1)/2)**2
    return 2*R*math.asin(math.sqrt(h))

COVER_KM   = 6.5   # max straight-line distance from a POP to be considered serviceable
CLUSTER_M  = 500   # DB candidate clustering radius (m)

# ---- assign settlements to nearest POP, drop out-of-coverage ----
assigned = []
for nid, name, lat, lon, ptype in SETTLEMENTS:
    dists = {p: hav(lat, lon, d["lat"], d["lon"]) for p, d in POPS.items()}
    pop = min(dists, key=dists.get)
    if dists[pop] <= COVER_KM:
        assigned.append({"id": nid, "name": name, "lat": lat, "lon": lon,
                         "type": ptype, "pop": pop, "dist_km": round(dists[pop], 2)})

print(f"Settlements in coverage (<= {COVER_KM} km of a POP): {len(assigned)} / {len(SETTLEMENTS)}")
by_pop = {}
for s in assigned:
    by_pop.setdefault(s["pop"], []).append(s)
for p in sorted(by_pop):
    print(f"  {p}: {len(by_pop[p])} settlements")

# ---- greedy clustering (per POP area) ----
clusters = []
for pop, items in by_pop.items():
    pts = sorted(items, key=lambda s: (s["type"] == "isolated_dwelling", -(s["type"] == "town")))  # towns/villages first as seeds
    used = set()
    for seed in pts:
        if seed["id"] in used:
            continue
        members = [seed]; used.add(seed["id"])
        changed = True
        while changed:  # grow cluster transitively (single-linkage-ish)
            changed = False
            for o in pts:
                if o["id"] in used:
                    continue
                if any(hav(o["lat"], o["lon"], m["lat"], m["lon"]) <= CLUSTER_M/1000 for m in members):
                    members.append(o); used.add(o["id"]); changed = True
        clat = sum(m["lat"] for m in members)/len(members)
        clon = sum(m["lon"] for m in members)/len(members)
        seed_name = seed["name"] or next((m["name"] for m in members if m["name"]), None)
        clusters.append({
            "cluster_id": f"{pop}-C{len([c for c in clusters if c['pop']==pop])+1:02d}",
            "pop": pop, "lat": round(clat, 6), "lon": round(clon, 6),
            "label": seed_name, "n_settlements": len(members),
            "members": [m["name"] or f"#{m['id']}" for m in members],
        })

print(f"\nDB candidate clusters: {len(clusters)}")
from collections import Counter
print(Counter(c["pop"] for c in clusters))

os.makedirs("deliverables", exist_ok=True)
json.dump({"pops": POPS, "settlements": assigned, "clusters": clusters},
          open("deliverables/clusters.json", "w"), indent=1)
print("saved deliverables/clusters.json")

# ---- emit Overpass batch queries: OSM building ways within 450m of each cluster centroid ----
BATCH = 25
qs = []
for c in clusters:
    qs.append(f'way["building"](around:450,{c["lat"]},{c["lon"]});out count;')
for i in range(0, len(qs), BATCH):
    batch = "".join(qs[i:i+BATCH])
    q = f'[out:json][timeout:120];{batch}'
    fn = f"deliverables/overpass_batch_{i//BATCH}.url"
    open(fn, "w").write(q)
    print(fn, f"({min(BATCH, len(qs)-i)} clusters)")
