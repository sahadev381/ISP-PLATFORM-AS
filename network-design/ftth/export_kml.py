#!/usr/bin/env python3
"""Export FTTH design to KML (Google Earth / MAPS.ME / OsmAnd field use)."""
import json, os, html

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "deliverables")
data_pops = json.load(open(f"{OUT}/legs.json"))
clusters = json.load(open(f"{OUT}/clusters.json"))
counts_file = json.load(open(f"{OUT}/building_counts.json"))
dbcsv = open(f"{OUT}/db_points.csv").read().splitlines()

POPS = data_pops["pops"]
LEGS = data_pops["legs"]
BACKUP = data_pops["backup_wireless"]

def esc(s): return html.escape(str(s))

KML_HEAD = """<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2"><Document>
<name>FTTH Design - Melamchi/Helambu</name>
<Style id="he"><IconStyle><color>ff2828c6</color><scale>1.6</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/ranger_station.png</href></Icon></IconStyle></Style>
<Style id="pop"><IconStyle><color>ffc63f3f</color><scale>1.3</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/communications.png</href></Icon></IconStyle></Style>
<Style id="db"><IconStyle><color>ff00a5ff</color><scale>0.9</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/shaded_dot.png</href></Icon></IconStyle></Style>
<Style id="ringA"><LineStyle><color>ff3535e5</color><width>4</width></LineStyle></Style>
<Style id="ringB"><LineStyle><color>ffe5881e</color><width>4</width></LineStyle></Style>
<Style id="ringC"><LineStyle><color>ff47a043</color><width>4</width></LineStyle></Style>
<Style id="spur"><LineStyle><color>ff008cfb</color><width>4</width></LineStyle></Style>
<Style id="wireless"><LineStyle><color>ffaa248e</color><width>2</width></LineStyle><PolyStyle><fill>0</fill></PolyStyle></Style>
"""

def gstyle(group):
    g = group.lower()
    if g.startswith("ring a"): return "ringA"
    if g.startswith("ring b"): return "ringB"
    if g.startswith("ring c") or "shared" in g: return "ringC"
    return "spur"

parts = [KML_HEAD]

# POPs / Headend
parts.append("<Folder><name>POPs / Headend</name>")
for k, p in POPS.items():
    style = "he" if k == "HE" else "pop"
    parts.append(f"""<Placemark><name>{esc(k)}</name><styleUrl>#{style}</styleUrl>
<description>{esc(p['label'])} | OLT {p['pon']} PON</description>
<Point><coordinates>{p['lon']},{p['lat']},0</coordinates></Point></Placemark>""")
parts.append("</Folder>")

# Backbone legs (straight lines; road km in name - geometry: use master map for road-following)
parts.append("<Folder><name>Backbone rings (straight, road-km labeled)</name>")
for l in LEGS:
    a, b = POPS[l["a"]], POPS[l["b"]]
    parts.append(f"""<Placemark><name>{l['a']}-{l['b']} | {l['km']} road-km | {l['core']}F</name>
<styleUrl>#{gstyle(l['group'])}</styleUrl><description>{esc(l['group'])}</description>
<LineString><tessellate>1</tessellate><coordinates>{a['lon']},{a['lat']},0 {b['lon']},{b['lat']},0</coordinates></LineString></Placemark>""")
# wireless backup
a, b = POPS["HE"], POPS["POP7"]
parts.append(f"""<Placemark><name>Wireless PTP backup HE-POP7 (~14 km LOS)</name><styleUrl>#wireless</styleUrl>
<LineString><tessellate>1</tessellate><coordinates>{a['lon']},{a['lat']},0 {b['lon']},{b['lat']},0</coordinates></LineString></Placemark>""")
parts.append("</Folder>")

# DB points from CSV (has homes/fats already computed)
import csv, io
parts.append("<Folder><name>DB / FAT sites (77)</name>")
for row in csv.DictReader(io.StringIO("\n".join(dbcsv))):
    parts.append(f"""<Placemark><name>{esc(row['db_id'])} - {esc(row['locality'])}</name><styleUrl>#db</styleUrl>
<description>POP: {esc(row['pop'])}&lt;br&gt;Villages: {esc(row['villages'])}&lt;br&gt;OSM buildings(450m): {esc(row['osm_buildings_450m'])}&lt;br&gt;Est. homes: {esc(row['est_homes'])}&lt;br&gt;FAT boxes: {esc(row['fat_boxes'])}</description>
<Point><coordinates>{row['lon']},{row['lat']},0</coordinates></Point></Placemark>""")
parts.append("</Folder>")

# Distribution tree edges from fiber_segments.csv (12F only)
parts.append("<Folder><name>Distribution tree (MST, straight)</name>")
coords_idx = {k: (p["lon"], p["lat"]) for k, p in POPS.items()}
for d in clusters["clusters"]:
    coords_idx[f"DB-{d['cluster_id']}"] = (d["lon"], d["lat"])
with open(f"{OUT}/fiber_segments.csv") as f:
    for row in csv.DictReader(f):
        if not row["type"].startswith("distribution"): continue
        if row["from"] in coords_idx and row["to"] in coords_idx:
            x1, y1 = coords_idx[row["from"]]; x2, y2 = coords_idx[row["to"]]
            parts.append(f"""<Placemark><name>{row['from']} -&gt; {row['to']} ({row['km']} km est)</name>
<Style><LineStyle><color>ffae9490</color><width>1.5</width></LineStyle></Style>
<LineString><tessellate>1</tessellate><coordinates>{x1},{y1},0 {x2},{y2},0</coordinates></LineString></Placemark>""")
parts.append("</Folder>")

parts.append("</Document></kml>")
open(f"{OUT}/FTTH_network.kml", "w").write("\n".join(parts))
print("wrote", f"{OUT}/FTTH_network.kml")
