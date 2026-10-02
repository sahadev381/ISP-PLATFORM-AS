# Huawei MA5800-X2 OLT — Deployment Guide
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
- PPPoE from customer router — per-POP MikroTik CCR2004 BNG (see HUAWEI_L3_GUIDE.md); RADIUS central at HE (existing ISP platform)

## OLT optics: POP7 uses Class C++ SFP (27 dB est. budget); others Class C+ fine

## SNMP OIDs for ISP platform dashboards (MA5800 series)
- ONU optical Rx power: `1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4.<frame>.<slot>.<port>.<onuid>` (0.01 dBm units, signed)
- ONU online status: `1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15`
- OLT PON port Rx power: `1.3.6.1.4.1.2011.6.128.1.1.2.21.1.9`
- CPU: `1.3.6.1.4.1.2011.6.3.4.1.2.0.2.0` ; Temp: `1.3.6.1.4.1.2011.6.3.4.1.3.0.2.0`
Walk with community from scripts (CHANGE the defaults!). Trap host NMS = 10.20.0.2.

## Provisioning flow per customer
1. ONU spliced in — autofind (enabled per port) shows SN
2. `display ont autofind all` — ont add per script template (desc CUST-<FDC>-<seq>)
3. service-port with PON's S-VLAN — customer VLAN active — PPPoE via RADIUS (existing platform)
4. Check: `display ont optical-info <port> <onuid>` — Rx should be -8..-25 dBm (C+ budget)
