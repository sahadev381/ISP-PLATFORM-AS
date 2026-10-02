#!/usr/bin/env python3
"""TENDER_PACKAGE.md + tender_lots.csv: lot-wise tender structure (material + works),
quantities from the design deliverables. Indicative planning values (NPR 2026)."""
import csv, json, math, os

HERE = os.path.dirname(os.path.abspath(__file__)); OUT = os.path.join(HERE, "deliverables")
LEGS = json.load(open(f"{OUT}/legs.json"))
DR   = json.load(open(f"{OUT}/dist_routes.json"))
P    = {r["pop"]: r for r in csv.DictReader(open(f"{OUT}/pops.csv"))}
php  = {r["phase"]: r for r in csv.DictReader(open(f"{OUT}/phased_capex.csv"))}

bb48 = sum(l["km"] for l in LEGS["legs"] if l["core"] == 48)
bb24 = sum(l["km"] for l in LEGS["legs"] if l["core"] == 24)
bb   = sum(l["km"] for l in LEGS["legs"])
dist = sum(v["design_km"] for v in DR.values())
road = sum(v["road_km"]*1.10 for v in DR.values())
trail= sum(v["trail_km"] for v in DR.values())
bb_poles   = math.ceil(bb*25)
dist_poles = math.ceil(dist*25*0.5)
tot_fats = sum(int(p["fats"]) for p in P.values())
tot_fdcs = sum(int(p["pon_used"]) for p in P.values())
cust_y1  = 3224

lots = [
 # ---- material lots ----
 ("M1", "MATERIAL: fiber optic cable supply", 
  [f"48F ADSS single-mode (G.652D) — {(bb48*1.06):.0f} km qty", f"24F ADSS — {(bb24*1.06):.0f} km qty (POP7 spur)",
   f"12F ADSS — {(dist*1.08):.0f} km qty (distribution + 8% service loop)"],
  "Manufacturer test cert per drum; attenuation test at 1310/1490/1550 on delivery; delivery HE warehouse 60 days from PO."),
 ("M2", "MATERIAL: poles + aerial hardware",
  [f"8 m round wooden poles (CCC/treated): {bb_poles+dist_poles:,} pcs", f"Lashing wire: ~{(bb+dist)*1.02:.0f} km",
   f"Pole straps/bolts, dead-end & suspension clamps: {bb_poles+dist_poles:,} sets","Duct pipe + GI wire for culvert crossings (as per route survey)"],
  "Zinc-coating, ISI-spec; pole transport lot-wise to each POP staging."),
 ("M3", "MATERIAL: passive closures + ODN boxes",
  [f"FDC closure (1:8 cassette + splice tray): {tot_fdcs} pcs", f"FAT/DB box + 1:8 PLC splitter: {tot_fats} pcs",
   f"Splice closures + pigtails: {tot_fats+tot_fdcs+12} pcs", "Patch cords + splice trays 10% spares"],
  "IP65 min, SC/APC PLC G.671; sample set to be vendor-qualified before full PO."),
 ("M4", "MATERIAL: active electronics (Huawei stack)",
  ["8x Huawei MA5800-X2 OLT (HE/POP1/POP2 with 2x GPHF service boards; others 1x GPHF = 11 boards total)",
   "2x Huawei S6730-H-24x10GE aggregation (HE, POP2) + 6x S5735-S32ST4X (all other POPs)",
   "8x MikroTik CCR2004-1G-12S+2XS BNG (per-POP PPPoE/AA to central RADIUS)",
   f"{tot_fdcs+30}x GPON SFP C+/C++ (incl. 30 spares)","18x pairs 10G LR optics + DAC/AOC for in-POP",
   "8x POP power set (UPS/rectifier + 100Ah battery x2)"],
  "Huawei gold-partner supply with 3-yr AMC option; config handover per huawei/*.txt scripts included in this repo."),
 ("M5", "MATERIAL: customer-side supply (Year 1)",
  [f"ONT/ONU {cust_y1:,} pcs (1GE basic + WiFi mix per PRICING_BANDS.md)", f"GJYXCH drop cable: {cust_y1*0.1:.0f} km",
   "FAT port tails, 30-m pre-connectorized drops: Y1 qty"],
  "Vendor stock-and-call-off basis (12 months); ONT warranty pass-through to customer."),
 # ---- works lots ----
 ("W1", "WORKS: backbone aerial stringing",
  [f"Route km: {bb:.1f} km (Ring A 40.2 / Ring B 45.6 / Ring C 56.9 (shared arm 6.0 with A) / POP7 spur 34.3)",
   "New pole setting where poles not available from NEA route-share: route plan per FTTH_master_map",
   "OTDR + power-meter test per segment, splice report per cassette, as-built KML delivery"],
  "Rate basis: per route-km (lashing) + per pole-set; trail subsections excluded at this lot."),
 ("W2", "WORKS: distribution Phase-1 stringing",
  [f"Ring B + shared leg side: {php['Phase1']['dist_km']} km, {php['Phase1']['fats']} FAT, {php['Phase1']['fats']} FAT cluster poles",
   f"Homes passed target {php['Phase1']['homes_passed']}", "Incl. FAT/FDC pole mounting + fusion splicing at FAT DB"],
  "Start on PO issue; trail spur HE-C03→HE-C12 line to be re-measured against field survey before award final."),
 ("W3", "WORKS: distribution Phase-2 stringing",
  [f"Ring A + ring C closure side: {php['Phase2']['dist_km']} km, {php['Phase2']['fats']} FAT", f"Homes passed target {php['Phase2']['homes_passed']}"],
  "Award after P1 acceptance (liquidated-damage clause for consistent mobilization)."),
 ("W4", "WORKS: distribution Phase-3 + trail spurs",
  [f"POP7 side: {php['Phase3']['dist_km']} km", "Trail spur work: see TRAIL_SURVEY.md (9 spurs / 47.2 km trail-est)",
   "Wireless backup tower erection POP7 (see WIRELESS_BACKUP_POP7.md)"],
  "Trail package ONLY to bidders with Himalayan trail work references."),
 ("W5", "WORKS: active installation + commissioning",
  ["8 OLT + 8 L3 + 8 BNG installation per huawei/ scripts","OLT-BNG-RADIUS integration testing",
   "PON link budget verification every site (≥24 dB margin @ worst DB), go-live certification"],
  "Unit-rate per site; commissioning engineer to certify against HUAWEI_L3_GUIDE.md."),
 ("W6", "WORKS: customer drop installation service (Year 1)",
  [f"{cust_y1:,} connections (100-m drop, FAT port work, ONT install + auth to BNG/PPPoE)",
   "Same-day activation target ≥ 85%; recurring-consume less than 3%"],
  "Per-connection unit rate, homeowner sign-off sheet required for billing."),
]

doc = ["# FTTH rollout - tender package structure (lot-wise, indicative)\n",
"> Draft procurement structure only. Final documents must follow Public Procurement (Monitoring) Act/Rule and the entity's own bid conditions.",
"All quantities below come from this repo's design stack (clusters.json/dist_routes.json/phased_capex.csv).",
f"\n**Headline quantities**: backbone {bb:.1f} road-km · distribution {dist:.1f} km design ({road:.1f} road + {trail:.1f} trail-est) · "
f"{bb_poles+dist_poles:,} poles · {tot_fats} FAT · {tot_fdcs} FDC · {len(LEGS['pops'])} POPs · Y1 {cust_y1:,} connections.\n",
"## Lot register", "| Lot | Description | Key quant|", "|---|---|---|"]
rows = []
for code, name, det, note in lots:
    t = " ".join(det)
    cq = t.split(";")[0][:80] + ("…" if len(t) > 80 else "")
    doc.append(f"| **{code}** | {name} | {len(det)} sub-items |")
    for d in det: doc.append(f"| | - {d} |")
    doc.append(f"| | *{note}* |")
    rows.append({"lot": code, "desc": name, "items": " | ".join(det), "conditions": note})

doc += ["\n## Evaluation & award strategy (recommended)",
"1. **Quality-and-rate, lowest evaluated bidder per lot**, with award cap: a single bidder may win maximum 2 lots (ensures parallel mobilization).",
"2. M1-M4 materiaLPO first (longest lead), W1 awarded with them; distribution lots can be trailing (P1 first, see HOTSPOT_OPTIMIZATION.md phase order).",
"3. **M4 + W5 must go together** (same Huawei partner if possible) — installer accountability.",
"4. Trail sub-package (inside W4) is conditional: award AFTER the TRAIL_SURVEY.md checklists return measured fields. Substitute with 'per-km rate x measured km' fixed-form.",
"5. Require per-lot performance security 5% and 1-yr defects liability (fiber cuts, splice failures).",
"\n## Measurement & payment basis",
"- Stringing: NPR per route-km measured on OSRM/field-measured chainage (as-built KML accepted).",
"- Pole-set: per erected, plumb-verified pole with anchor details.",
"- FAT/FDC: per commissioned unit passing OTDR + budget test.",
"- Customer drops: per activated sub after successful PPPoE auth + speed test screenshot.",
"\n## Timeline (indicative)",
"PO issue week 0 → M1-M4 delivery day 60-75 → W1 backbone complete day 100-120 → P1 distribution day 150 → first live customers day 160 → P2 day 300 → P3 (+trail spurs) day 450-540.",
"\n## Files that form part of this package",
"`FTTH_master_map.html`, KML, Splice plan, IP_VLAN_PLAN.md, huawei/ scripts, TRAIL_SURVEY.md, PHASED_CAPEX.md, HOTSPOT_OPTIMIZATION.md, PRICING_BANDS.md — all versioned in this repo."]
open(f"{OUT}/TENDER_PACKAGE.md","w").write("\n".join(doc))
with open(f"{OUT}/tender_lots.csv","w",newline="") as f:
    w=csv.DictWriter(f,fieldnames=["lot","desc","items","conditions"]); w.writeheader(); w.writerows(rows)
print(f"{len(lots)} lots | bb {bb:.1f} km, dist {dist:.1f} km, {bb_poles+dist_poles:,} poles")
print("wrote TENDER_PACKAGE.md + tender_lots.csv")
