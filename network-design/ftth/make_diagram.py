#!/usr/bin/env python3
"""Static SVG topology schematic (geographic-ish layout) of the FTTH design."""
import json, math, os

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "deliverables")
LEGS = json.load(open(f"{OUT}/legs.json"))
clusters = json.load(open(f"{OUT}/clusters.json"))

POPS = LEGS["pops"]
# stats from pops.csv
import csv
stats = {}
with open(f"{OUT}/pops.csv") as f:
    for row in csv.DictReader(f):
        stats[row["pop"]] = row

# project lat/lon -> svg coords (equirectangular, pad)
lats = [p["lat"] for p in POPS.values()]; lons = [p["lon"] for p in POPS.values()]
minlat, maxlat = min(lats), max(lats); minlon, maxlon = min(lons), max(lons)
W, H, PAD = 1180, 900, 90
def xy(lat, lon):
    x = PAD + (lon - minlon) / (maxlon - minlon) * (W - 2*PAD)
    y = H - PAD - (lat - minlat) / (maxlat - minlat) * (H - 2*PAD)
    return x, y

def grpcol(g):
    g = g.lower()
    if g.startswith("ring a"): return "#e53935"
    if g.startswith("ring b"): return "#1e88e5"
    if g.startswith("ring c") or "shared" in g: return "#43a047"
    return "#fb8c00"

s = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" font-family="Arial,Helvetica,sans-serif">']
s.append(f'<rect width="{W}" height="{H}" fill="#fdfdfd"/>')
s.append('<defs><marker id="dot" markerWidth="4" markerHeight="4" refX="2" refY="2"><circle cx="2" cy="2" r="1.5" fill="#888"/></marker></defs>')
s.append(f'<text x="{W/2}" y="34" text-anchor="middle" font-size="21" font-weight="bold">FTTH Backbone Topology — Melamchi / Helambu / Thangpal / Haibung</text>')
s.append(f'<text x="{W/2}" y="56" text-anchor="middle" font-size="12" fill="#666">Road distances from OSRM · 48F rings, 24F spur · 2026-10-02</text>')

# legs
for l in LEGS["legs"]:
    a, b = POPS[l["a"]], POPS[l["b"]]
    x1, y1 = xy(a["lat"], a["lon"]); x2, y2 = xy(b["lat"], b["lon"])
    col = grpcol(l["group"])
    s.append(f'<line x1="{x1:.0f}" y1="{y1:.0f}" x2="{x2:.0f}" y2="{y2:.0f}" stroke="{col}" stroke-width="5" stroke-opacity="0.75"/>')
    mx, my = (x1+x2)/2, (y1+y2)/2
    s.append(f'<rect x="{mx-46:.0f}" y="{my-11:.0f}" width="92" height="15" rx="3" fill="#fff" stroke="{col}" stroke-width="0.8"/>')
    s.append(f'<text x="{mx:.0f}" y="{my:.0f}" text-anchor="middle" font-size="10.5" fill="{col}" font-weight="bold">{l["km"]} km · {l["core"]}F</text>')

# wireless backup dashed
a, b = POPS["HE"], POPS["POP7"]
x1, y1 = xy(a["lat"], a["lon"]); x2, y2 = xy(b["lat"], b["lon"])
s.append(f'<line x1="{x1:.0f}" y1="{y1:.0f}" x2="{x2:.0f}" y2="{y2:.0f}" stroke="#8e24aa" stroke-width="2" stroke-dasharray="4 6"/>')

# POP nodes
for k, p in POPS.items():
    x, y = xy(p["lat"], p["lon"])
    st = stats.get(k, {})
    r = 15 if k == "HE" else 12
    fill = "#c62828" if k == "HE" else "#1565c0"
    s.append(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{r}" fill="{fill}" stroke="#fff" stroke-width="3"/>')
    s.append(f'<text x="{x:.0f}" y="{y-22:.0f}" text-anchor="middle" font-size="14" font-weight="bold">{k}</text>')
    detail = f'PON {st.get("pon_used","?")}/{p["pon"]} · DB {st.get("db_sites","?")} · ~{st.get("est_homes","?")} HH'
    s.append(f'<text x="{x:.0f}" y="{y+30:.0f}" text-anchor="middle" font-size="11" fill="#333">{detail}</text>')

# legend
lx, ly = 60, H-170
for i,(col,txt) in enumerate([("#e53935","Ring A: HE-POP2-POP1-HE (40.2 km)"),("#1e88e5","Ring B: HE-POP5-POP4-HE (45.6 km)"),("#43a047","Ring C: HE-POP2-POP6-POP3-HE (56.9 km)"),("#fb8c00","Spur: HE-POP7 24F + wireless PTP backup"),("#8e24aa","Wireless backup ~14 km (LOS survey)")]):
    s.append(f'<line x1="{lx}" y1="{ly+i*20}" x2="{lx+34}" y2="{ly+i*20}" stroke="{col}" stroke-width="4"/>')
    s.append(f'<text x="{lx+42}" y="{ly+4+i*20}" font-size="12" fill="#222">{txt}</text>')
s.append(f'<text x="{W-60}" y="{H-40}" text-anchor="end" font-size="11" fill="#777">Total backbone: 171.2 road-km · Distribution (to 77 DB sites): ~156 km est</text>')
s.append('</svg>')

open(f"{OUT}/FTTH_topology.svg", "w").write("\n".join(s))
print("wrote", f"{OUT}/FTTH_topology.svg")
