# IP & VLAN Plan (GPON + Rings)

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
