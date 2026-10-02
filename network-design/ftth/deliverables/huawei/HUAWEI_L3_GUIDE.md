# Huawei L3 Ring + Per-POP BNG — Architecture Note

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
