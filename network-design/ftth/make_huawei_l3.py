#!/usr/bin/env python3
"""Per-POP L3 aggregation switch configs (Huawei S6730/S5735) for the OSPF+BFD ring
+ per-POP BNG note (MikroTik CCR, RADIUS-central). -> huawei/L3-*.txt + HUAWEI_L3_GUIDE.md"""
import csv, os, json

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "deliverables")
HDIR = os.path.join(OUT, "huawei"); os.makedirs(HDIR, exist_ok=True)
POPS = json.load(open(f"{OUT}/legs.json"))["pops"]
pops_rows = {s["pop"]: s for s in csv.DictReader(open(f"{OUT}/pops.csv"))}

VLAN_BASE = {"HE":1000,"POP1":1100,"POP2":1200,"POP3":1300,"POP4":1400,"POP5":1500,"POP6":1600,"POP7":1700}
MGMT_GW  = {"HE":"10.20.0.1","POP1":"10.20.1.1","POP2":"10.20.2.1","POP3":"10.20.3.1",
            "POP4":"10.20.4.1","POP5":"10.20.5.1","POP6":"10.20.6.1","POP7":"10.20.7.1"}
LOOP = {"HE":"10.10.0.1","POP1":"10.10.0.11","POP2":"10.10.0.12","POP3":"10.10.0.13",
        "POP4":"10.10.0.14","POP5":"10.10.0.15","POP6":"10.10.0.16","POP7":"10.10.0.17"}
ARMS = {
 "HE":   [(2001,"10.10.1.0/31","10.10.1.0","HE-POP2 RingA east",10),(2002,"10.10.1.6/31","10.10.1.6","HE-POP2 RingC (2nd cable)",10),
          (2003,"10.10.1.8/31","10.10.1.8","HE-POP5 RingB",10),(2004,"10.10.1.12/31","10.10.1.13","POP4-HE RingB return",10),
          (2005,"10.10.1.20/31","10.10.1.21","POP3-HE RingC return",10),(2006,"10.10.1.24/31","10.10.1.24","HE-POP7 spur fiber",10),
          (2007,"10.10.1.26/31","10.10.1.26","HE-POP7 wireless backup",100)],
 "POP1": [(2011,"10.10.1.2/31","10.10.1.3","POP2-POP1 RingA",10),(2012,"10.10.1.4/31","10.10.1.4","POP1-HE RingA return",10)],
 "POP2": [(2021,"10.10.1.0/31","10.10.1.1","HE-POP2 RingA east",10),(2022,"10.10.1.2/31","10.10.1.2","POP2-POP1 RingA",10),
          (2023,"10.10.1.6/31","10.10.1.7","HE-POP2 RingC",10),(2024,"10.10.1.16/31","10.10.1.16","POP2-POP6 RingC",10)],
 "POP3": [(2041,"10.10.1.18/31","10.10.1.19","POP6-POP3 RingC",10),(2042,"10.10.1.20/31","10.10.1.20","POP3-HE RingC return",10)],
 "POP4": [(2051,"10.10.1.10/31","10.10.1.11","POP5-POP4 RingB",10),(2052,"10.10.1.12/31","10.10.1.12","POP4-HE RingB return",10)],
 "POP5": [(2061,"10.10.1.8/31","10.10.1.9","HE-POP5 RingB",10),(2062,"10.10.1.10/31","10.10.1.10","POP5-POP4 RingB",10)],
 "POP6": [(2071,"10.10.1.16/31","10.10.1.17","POP2-POP6 RingC",10),(2072,"10.10.1.18/31","10.10.1.18","POP6-POP3 RingC",10)],
 "POP7": [(2081,"10.10.1.24/31","10.10.1.25","HE-POP7 spur fiber",10),(2082,"10.10.1.26/31","10.10.1.27","HE-POP7 wireless backup",100)],
}
BIG = {"HE", "POP2"}

for site, arms in ARMS.items():
    used = int(pops_rows[site]["pon_used"]); base = VLAN_BASE[site]
    L = [f"! L3-{site} — Huawei {'S6730-H (24x10GE)' if site in BIG else 'S5735-S32ST4X (4x10GE)'} — ring aggregation",
         f"! Ring arms: {len(arms)} | OLT trunk + BNG trunk | mgmt GW {MGMT_GW[site]} | loop {LOOP[site]}",
         "system-view",
         f"sysname L3-{site}",
         "vlan 100",
         "interface vlanif 100",
         f"  ip address {MGMT_GW[site]} 255.255.255.128",
         "quit",
         "interface LoopBack 0",
         f"  ip address {LOOP[site]} 255.255.255.255",
         "quit",
         "! --- ring arms (each = dedicated VLAN + VLANIF, L3 PTP) ---"]
    for i,(vl,subnet,ip,desc,cost) in enumerate(arms, 1):
        L += [f"vlan {vl}",
              f"  description {desc}",
              f"interface XGigabitEthernet 0/0/{i}",
              f"  description {desc} (48F fiber)",
              "  port link-type access",
              f"  port default vlan {vl}",
              "quit",
              f"interface Vlanif {vl}",
              f"  ip address {ip} 255.255.255.254",
              f"  ospf cost {cost}",
              "  ospf bfd enable",
              "quit"]
    L += ["! --- OLT aggregation trunk (2x10GE LACP, matches OLT side) ---",
          "interface Eth-Trunk 1",
          "  description to-OLT LACP",
          "  port link-type trunk",
          f"  port trunk allow-pass vlan 100 {base+1} to {base+max(used,1)}",
          "  mode lacp",
          "quit",
          f"! (members: XGE0/0/{len(arms)+2}-0/0/{len(arms)+3})",
          "! --- BNG trunk (MikroTik CCR2004 PPPoE, local POP) ---",
          ("interface XGigabitEthernet 0/0/20" if site in BIG else "interface GigabitEthernet 0/0/24"),
          "  description to-BNG-CCR2004",
          "  port link-type trunk",
          f"  port trunk allow-pass vlan 100 {base+1} to {base+max(used,1)}",
          "quit",
          "! --- OSPF ---",
          "bfd",
          "quit",
          f"ospf 1 router-id {LOOP[site]}",
          "  bfd all-interfaces enable",
          "  area 0.0.0.0",
          f"    network {LOOP[site]} 0.0.0.0"]
    for vl,subnet,ip,desc,cost in arms:
        net = subnet.split("/")[0]
        L.append(f"    network {net} 0.0.0.1")
    L += ["  quit","quit",
          "snmp-agent sys-info version v2c",
          "snmp-agent community write/privateRO publicRO@FTTH26   ! CHANGE",
          "sntp unicast-server 202.79.35.170",
          "save","y"]
    open(f"{HDIR}/L3-{site}.txt", "w").write("\n".join(L))
    print(f"L3-{site}: {len(arms)} arms, VLAN {base+1}-{base+max(used,1)}")

guide = """# Huawei L3 Ring + Per-POP BNG — Architecture Note

## Correction to earlier plan
Central PPPoE BNG at HE CANNOT cross the L3 (OSPF) ring — PPPoE discovery is L2 broadcast, dies at the first router hop.
**Architecture: L3 ring (Huawei) + PPPoE BNG per POP (MikroTik CCR2004, central RADIUS at HE)** — fits your ISP platform's existing MikroTik integration (`api_mikrotik.php`, RADIUS).

## Hardware per POP
| Role | Model | Ports used |
|------|-------|-----------|
| L3 ring switch (HE, POP2) | Huawei S6730-H (24x10GE) | 4-7 ring arms + OLT trunk + BNG |
| L3 ring switch (others) | Huawei S5735-S32ST4X (24GE+4x10GE) | 2 ring arms + OLT trunk + BNG |
| BNG PPPoE router | MikroTik CCR2004-1G-12S+2XS | trunk from L3 sw (all site S-VLANs), uplink to HE/transit |
| OLT | MA5800-X2 (already scripted) | 2x10GE LACP to L3 sw |

Configs: `huawei/L3-<SITE>.txt` (8 files) — ring arms as dedicated L3 VLANIF + OSPF/BFD, Eth-Trunk to OLT, BNG trunk.

## MikroTik BNG skeleton (per POP — RADIUS to central platform at HE)
```
/interface vlan : for v from=<base+1> to=<base+used> do={ add name=s$v vlan-id=$v interface=sfp28trunk }
/ppp profile add name=resi-50m local-address=10.30.0.1 remote-address=pool-resi
/interface pppoe-server server : per S-VLAN VLAN interface (scripted loop, ~28 max)
/radius add service=ppp address=10.50.0.10 secret=<platform-radius-secret>  ! HE server farm
```
- BNG loopbacks: 10.10.0.61-67 (POP1..POP7); HE server/RADIUS farm 10.50.0.0/24 (RADIUS=10.50.0.10)
- BNG joins OSPF on a stub /31 toward the POP L3 switch (preferred) or static default; HE BNG full OSPF for transit redundancy.

## Consistency check vs other docs
- OLT mgmt gw = site L3 sw VLANIF100 (.1) — matches OLT scripts' default route
- Customer path: ONU -> OLT -> Eth-Trunk -> L3 sw (bridged L2) -> BNG trunk -> CCR PPPoE auth -> routed over ring to HE/upstream
- Ring failover <200 ms via OSPF+BFD; arms listed per-site in L3-*.txt headers
"""
open(f"{HDIR}/HUAWEI_L3_GUIDE.md", "w").write(guide)
print("wrote HUAWEI_L3_GUIDE.md")
