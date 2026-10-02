#!/usr/bin/env python3
"""Simple payback / cashflow model, 3 ARPU scenarios. Writes PAYBACK_MODEL.md + payback.csv"""
import csv, os, json
HERE = os.path.dirname(os.path.abspath(__file__)); OUT = os.path.join(HERE, "deliverables")

_ph = {r["phase"]: float(r["est_cost_npr"]) for r in csv.DictReader(open(f"{OUT}/phased_capex.csv"))}
CAPEX = [("P1 backbone+HE core", _ph["Phase1"], 0), ("P2 rings A/C+POPs", _ph["Phase2"], 12), ("P3 POP7+redundancy buffer", _ph["Phase3"]+5_000_000, 24)]
# avg subscribers per year (design: Y1 end 3224, Y3 end 4836; ramp midpoints)
AVG_SUBS = [1600, 3600, 4500, 5000, 5200]
CPE_NET = 1600            # net sub capex/connection after install-charge recovery
COGS_SUB = 220            # upstream bandwidth + content per sub/mo
GOVT = 0.06               # NTA royalty 4% + RTDF 2%
OPEX_MONTH = [570_000, 570_000, 570_000, 570_000, 570_000, 570_000, 570_000, 570_000, 570_000,
              570_000, 570_000, 570_000, 810_000, 920_000]  # 570k P1, +240k P2, +110k P3 (month index>=12/24)

def run(arpu):
    cash = 0.0; capex_spent = 0.0; rows = []; payback = None
    for m in range(60):
        yr = m // 12
        subs = AVG_SUBS[min(yr, 4)]
        if m < 6: subs = int(subs * m / 6)  # build ramp first 6 months
        rev = subs * arpu
        cogs = subs * COGS_SUB + subs * rev/ (subs or 1) * 0  # placeholder
        ebitda = rev*(1-GOVT) - subs*COGS_SUB - OPEX_MONTH[min(m, len(OPEX_MONTH)-1)]
        # phased capex + monthly customer-side capex (subs added this month)
        for label, amt, mm in CAPEX:
            if mm == m: cash -= amt; capex_spent += amt
        prev = AVG_SUBS[min((m-1)//12, 4)] if m > 0 else 0
        if m == 0: prev = 0
        delta = max(0, subs - prev)
        cash -= delta * CPE_NET
        cash += ebitda
        rows.append((m+1, subs, rev, ebitda, round(cash)))
        if payback is None and cash > 0: payback = m+1
    return payback, rows

doc = ["# Payback / Cashflow Model (planning aid — replace with real quotes)\n",
       f"Capex: P1 {_ph['Phase1']/1e6:.1f}M m0, P2 {_ph['Phase2']/1e6:.1f}M m12, P3 {(_ph['Phase3']+5_000_000)/1e6:.1f}M m24 (POP7 + redundancy: POP2-POP7 ring closure or wireless relay) - from phased_capex.csv (road-measured distribution).",
       "Avg subs by year: 1.6k / 3.6k / 4.5k / 5.0k / 5.2k (design targets 3224 Y1-end, 4836 Y3-end).",
       "COGS 220/sub/mo (upstream), OpEx 570k->920k/mo phased, NTA royalty+RTDF 6% of revenue, net CPE 1600/sub.\n",
       "| ARPU (NPR/mo) | Payback | Y3 monthly EBITDA |","|--:|---|--:|"]
for arpu in (700, 900, 1100):
    pb, rows = run(arpu)
    y3 = rows[35][3]
    doc.append(f"| {arpu} | {'month '+str(pb)+' (~' + format(pb//12) + 'y' + str(pb%12) + 'm)' if pb else 'NOT within 5 years'} | {y3:,.0f} |")
    with open(f"{OUT}/payback_arpu{arpu}.csv", "w", newline="") as f:
        w = csv.writer(f); w.writerow(["month","subs","revenue","ebitda","cum_cashflow"]); w.writerows(rows)
    print(f"ARPU {arpu}: payback = {pb}, Y3 EBITDA/mo = {y3:,}")
doc.append("\nNotes: excludes tax on profit, interest, and bad debt; add ~10-15% ARPU headroom if bundling TV/phone. Field-verify take-rates against the 0.75 homes-passed factor after survey.")
open(f"{OUT}/PAYBACK_MODEL.md", "w").write("\n".join(doc))
print("wrote PAYBACK_MODEL.md + payback_arpu{700,900,1100}.csv")
