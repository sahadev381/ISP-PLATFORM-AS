#!/usr/bin/env python3
"""Huawei MA5800-X2 provisioning configs per OLT site, from design data.
Generates: huawei/OLT-<SITE>.txt + HUAWEI_OLT_GUIDE.md"""
import json, csv, os, collections

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "deliverables")
HDIR = os.path.join(OUT, "huawei")
os.makedirs(HDIR, exist_ok=True)

LEGS = json.load(open(f"{OUT}/legs.json"))
POPS = LEGS["pops"]
pops_rows = {s["pop"]: s for s in csv.DictReader(open(f"{OUT}/pops.csv"))}
pon_alloc = list(csv.DictReader(open(f"{OUT}/pon_allocation.csv")))

SITES = ["HE","POP1","POP2","POP3","POP4","POP5","POP6","POP7"]
MGMT = {"HE": ("10.20.0.10","10.20.0.1","25"), "POP1": ("10.20.1.10","10.20.1.1","25"),
        "POP2": ("10.20.2.10","10.20.2.1","25"), "POP3": ("10.20.3.10","10.20.3.1","25"),
        "POP4": ("10.20.4.10","10.20.4.1","25"), "POP5": ("10.20.5.10","10.20.5.1","25"),
        "POP6": ("10.20.6.10","10.20.6.1","25"), "POP7": ("10.20.7.10","10.20.7.1","25")}
VLAN_BASE = {"HE":1000,"POP1":1100,"POP2":1200,"POP3":1300,"POP4":1400,"POP5":1500,"POP6":1600,"POP7":1700}
NMS = "10.20.0.2"  # ISP platform / NMS server at HE mgmt

site_fdc = collections.defaultdict(list)
for r in pon_alloc: site_fdc[r["pop"]].append(r)

def pon_iface(global_idx):
    """global PON index (1-based) -> '0/{slot}/{port}' (16 ports per GPHF board)."""
    slot = 1 if global_idx <= 16 else 2
    return f"0/{slot}/{(global_idx-1) % 16}"

cfg_files = {}
for site in SITES:
    st = pops_rows[site]
    used = int(st["pon_used"])
    boards = [1] if used <= 16 else [1, 2]
    ip, gw, plen = MGMT[site]
    base = VLAN_BASE[site]
    L = []
    A = L.append
    A(f"! =============================================================")
    A(f"! OLT-{site} — Huawei MA5800-X2 provisioning script")
    A(f"! {POPS[site]['label']} | PON used: {used}/{'16 (1x GPHF)' if len(boards)==1 else '32 (2x GPHF)'}")
    A(f"! Customer VLANs: {base+1}-{base+used} (S-VLAN per PON) | MGMT: {ip}/25 via VLAN 100")
    A(f"! Generated from FTTH design 2026-10-02. Review passwords/keys before paste!")
    A(f"! =============================================================")
    A(f"enable\nconfig")
    A(f"undo enable alarm output all")
    A(f"sysname OLT-{site}")
    for s in boards:
        A(f"board confirm 0 frameid 0 slotid {s} boardname GPHF")
    A("! --- DBA + profiles (create once, same on every OLT) ---")
    A(f"dba-profile add profile-id 20 profile-name FTTH-RESI type4 max 1000000")
    A(f"dba-profile add profile-id 21 profile-name FTTH-100M type3 assure 50000 max 102400")
    A(f"dba-profile add profile-id 22 profile-name FTTH-50M  type3 assure 25000 max 51200")
    A(f"gpon line-profile add profile-id 20 profile-name FTTH-LINE")
    A(f"  tcont 1 dba-profile-id 20")
    A(f"  gem add 1 eth tcont 1")
    A(f"  gem mapping 1 0 vlan 10")
    A(f"  commit\nquit")
    A(f"gpon service-profile add profile-id 20 profile-name FTTH-SVC")
    A(f"  port vlan eth 1 translation 10 user-vlan 10")
    A(f"  commit\nquit")
    A("! --- Management (VLAN 100) ---")
    A("vlan 100 smart")
    A("port vlan 100 0/19 *")
    A("port vlan 100 0/20 *")
    A(f"interface vlanif 100")
    A(f"  ip address {ip} 255.255.255.128")
    A("quit")
    A(f"ip route-static 0.0.0.0 0.0.0.0 {gw}")
    A(f"! --- Uplink 2x10GE to {site} L3 switch (LACP trunk) ---")
    A(f"link-aggregation add port 0/19/0 port 0/20/0 mode work lacp-trunk")
    A(f"! --- SNMP/NMS (ISP platform monitoring) ---")
    A(f"snmp-agent sys-info version v2c")
    A(f"snmp-agent community write/privateRO publicRO@FTTH26")   # CHANGE THESE
    A(f"snmp-agent target-host trap-hostname NMS address {NMS} udp-port 162 trap-paramsname NMSNORM")
    A(f"snmp-agent trap enable standard")
    A(f"sntp unicast-server 202.79.35.170")
    A(f"! --- Customer S-VLANs: {base+1}-{base+max(used,1)} ---")
    if used > 0:
        A(f"vlan {base+1} to {base+used} smart")
        for s in boards + [19, 20]:
            A(f"port vlan {base+1} to {base+used} 0/{s} *" if s in (19,20) else f"port vlan {base+1} to {base+used} 0/{s} 0-{( (used-1)%16 ) if s==boards[-1] else 15}")
    A(f"! --- PON port activation ({used} used) ---")
    for i in range(1, used+1):
        fr = pon_iface(i)
        f, s, p = fr.split("/")
        A(f"interface gpon 0/{s}")
        A(f"  port {p} ont-auto-find enable")
    if used > 0:
        A("! quit from interface context before next section")
    A(f"! --- EXAMPLE: first customer on PON1 (VLAN {base+1}, FDC={site_fdc[site][0]['fdc_id'] if site_fdc[site] else '-'}) ---")
    if used > 0:
        i0 = pon_iface(1); f, s, p = i0.split("/")
        ex_fdc = site_fdc[site][0]["fdc_id"]
        A(f"interface gpon 0/{s}")
        A(f"  ont add {p} 0 sn-auth 48575443XXXXXXXX picasso ont-lineprofile-id 20 ont-srvprofile-id 20 desc CUST-{ex_fdc}-001")
        A("  ont port native-vlan <port> 0 eth 1 vlan 10 priority 0")
        A("quit")
        A(f"service-port {base+1} vlan {base+1} gpon 0/{s}/{p} ont 0 gemindex 1 multi-service user-vlan 10 tag-transform translate")
    if len(boards) == 1:
        A("! NOTE: slot 2 FREE — future: second GPHF doubles capacity without new chassis")
    A("save\ny")
    cfg_files[site] = "\n".join(L)
    open(f"{HDIR}/OLT-{site}.txt", "w").write("\n".join(L))

guide = f"""# Huawei MA5800-X2 OLT — Deployment Guide
Per-site ready-to-paste scripts in `huawei/OLT-<SITE>.txt` (8 files).

## Hardware model (matches design exactly)
| Site | PON used | Chassis | Boards | Notes |
|------|---------:|---------|--------|-------|
| HE (Melamchi) | 31/32 | MA5800-X2 | 2x GPHF (16P) | 1 spare PON port |
| POP1 | 25 | MA5800-X2 | 2x GPHF | BOM's "2nd OLT" = 2nd board only |
| POP2 | 28 | MA5800-X2 | 2x GPHF | same |
| POP3 | 0* | MA5800-X2 | 1x GPHF | *survey pending; slot 2 spare |
| POP4 | 14 | MA5800-X2 | 1x GPHF | |
| POP5 | 4 | MA5800-X2 | 1x GPHF | |
| POP6 | 8 | MA5800-X2 | 1x GPHF | |
| POP7 | 14 | MA5800-X2 | 1x GPHF | optical budget C++ optics |

**Budget note:** BOM line "2 x extra 16-port OLT (350k)" becomes "2 x GPHF board" for POP1/POP2 — typically cheaper per port; ask vendor.

## Key mapping
- X2 main control boards: slots 0/19, 0/20 — also carry 10GE uplinks (ports 0-1) — LACP to site L3 switch
- Service slots 0/1, 0/2 — GPHF 16x GPON each
- Per-PON S-VLAN: HE=1001..1031, POP1=1101..1125, POP2=1201..1228, POP4=1401..1414, POP5=1501..1504, POP6=1601..1608, POP7=1701..1714 (from IP_VLAN_PLAN)
- C-VLAN 10 (internet) translated to S-VLAN at service-port
- PPPoE from customer router — BNG at HE (existing ISP platform RADIUS)

## OLT optics: POP7 uses Class C++ SFP (27 dB est. budget); others Class C+ fine

## SNMP OIDs for ISP platform dashboards (MA5800 series)
- ONU optical Rx power: `1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4.<frame>.<slot>.<port>.<onuid>` (0.01 dBm units, signed)
- ONU online status: `1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15`
- OLT PON port Rx power: `1.3.6.1.4.1.2011.6.128.1.1.2.21.1.9`
- CPU: `1.3.6.1.4.1.2011.6.3.4.1.2.0.2.0` ; Temp: `1.3.6.1.4.1.2011.6.3.4.1.3.0.2.0`
Walk with community from scripts (CHANGE the defaults!). Trap host NMS = {NMS}.

## Provisioning flow per customer
1. ONU spliced in — autofind (enabled per port) shows SN
2. `display ont autofind all` — ont add per script template (desc CUST-<FDC>-<seq>)
3. service-port with PON's S-VLAN — customer VLAN active — PPPoE via RADIUS (existing platform)
4. Check: `display ont optical-info <port> <onuid>` — Rx should be -8..-25 dBm (C+ budget)
"""
open(f"{HDIR}/HUAWEI_OLT_GUIDE.md", "w").write(guide)
print(f"generated: huawei/ ({len(cfg_files)} site configs + HUAWEI_OLT_GUIDE.md)")
