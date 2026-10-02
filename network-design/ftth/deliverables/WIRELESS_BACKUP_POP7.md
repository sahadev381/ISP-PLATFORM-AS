# POP7 (Timbu) Wireless Backup — Path Check HE ↔ POP7

- Distance: 14.1 km straight. Terrain: Melamchi khola river corridor (north-south), likely LOS; **walk/drone survey + path profile mandatory** (mid-path saddle check between Melamchi bazaar ~870 m and Timbu ~2000 m).
- 1st Fresnel radius at mid-path (5.8 GHz): **13.5 m** → require ≥60% clearance = **8.1 m** above obstructions both ends (plan 25-40 m masts/tree clearance).
- FSPL(5.8 GHz, 14.1 km): 131 dB
- Budget example (airFiber 5XHD/AF-5G30): TX 20 dBm + 2×30 dBi dish − 131 dB = **RSL ≈ -51 dBm** → fade margin ~29 dB vs −80 dBm sens → excellent (target ≥15-20 dB).
- Capacity: 400-700 Mbps real (more than POP7's 16×2.5G PON day-1 need aggregated via shaping; run as L3 backup /31 with OSPF cost 100).
- Alternative: Ubiquiti AF-11X licensed 11 GHz for higher reliability in monsoon (rain fade @5/6 GHz low, 11 GHz moderate).
