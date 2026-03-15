<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Interactive FTTH Infrastructure Map";
$active = "map";

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Leaflet & Draw -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />

<style>
    #map-wrapper { position: relative; height: calc(100vh - 80px); width: 100%; }
    #map { height: 100%; width: 100%; }
    
    .map-toolbar { position: absolute; top: 20px; left: 20px; z-index: 1000; background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 220px; }
    .tool-btn { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px; margin-bottom: 8px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; cursor: pointer; transition: all 0.2s; font-size: 13px; font-weight: 600; text-align: left; color: #333; }
    .tool-btn:hover { background: #eff6ff; border-color: #3b82f6; }
    .tool-btn.active { background: #3b82f6 !important; color: #fff !important; border-color: #2563eb; }
    
    .ftth-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); }
    .ftth-modal-content { background: #fff; margin: 2% auto; padding: 25px; border-radius: 15px; width: 95%; max-width: 900px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; position: sticky; top: -25px; background: #fff; z-index: 10; padding: 10px 0; border-bottom: 1px solid #eee; }
    
    .modal-body label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 12px; color: #475569; }
    .modal-body select, .modal-body input, .modal-body textarea { width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px; font-size: 12px; }
    
    .splicing-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .splicing-table th { background:#f8fafc; padding:8px; border:1px solid #e2e8f0; text-align:left; }
    .splicing-table td { padding: 5px; border: 1px solid #e2e8f0; vertical-align: middle; }
    
    /* Dynamic Color Dropdown Styling */
    .color-select { font-weight: bold; border: 2px solid transparent !important; transition: all 0.2s; }
    .color-select option { background: white; color: black; }

    .popup-actions { display: flex; flex-direction: column; gap: 5px; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
    .btn-action-sm { padding: 6px; border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px; width: 100%; text-decoration: none; }
    .btn-view { background: #eff6ff; color: #3b82f6; }
    .btn-del { background: #fef2f2; color: #ef4444; }
</style>

<div id="map-wrapper">
    <div id="map"></div>
    <div class="map-toolbar">
        <h4 style="margin:0 0 15px 0; font-size: 14px; color:#1e293b;">Infrastructure Editor</h4>
        <button class="tool-btn" data-type="OLT" onclick="setMode('add_node', this)"><i class="fa fa-server" style="color:#ef4444;"></i> OLT Node</button>
        <button class="tool-btn" data-type="POLE" onclick="setMode('add_node', this)"><i class="fa fa-broadcast-tower" style="color:#64748b;"></i> Pole</button>
        <button class="tool-btn" data-type="MASTER_BOX" onclick="setMode('add_node', this)"><i class="fa fa-box" style="color:#f59e0b;"></i> Master Box</button>
        <button class="tool-btn" data-type="DB_BOX" onclick="setMode('add_node', this)"><i class="fa fa-boxes" style="color:#3b82f6;"></i> DB Box</button>
        <button class="tool-btn" data-type="ENCLOSURE" onclick="setMode('add_node', this)"><i class="fa fa-shield-halved" style="color:#8b5cf6;"></i> Enclosure</button>
        <button class="tool-btn" data-type="JOINT" onclick="setMode('add_node', this)"><i class="fa fa-link" style="color:#10b981;"></i> Joint / Tiffin</button>
        <hr style="border:0; border-top:1px solid #eee; margin:10px 0;">
        <button class="tool-btn" onclick="openFaultTool()"><i class="fa fa-magnifying-glass-location" style="color:#ef4444;"></i> Fault Localizer</button>
        <button class="tool-btn" id="fiberBtn" onclick="toggleFiberDrawing()"><i class="fa fa-pen-nib"></i> Trace Fiber</button>
        <button class="tool-btn" onclick="openLeaseManager()"><i class="fa fa-handshake" style="color:#8b5cf6;"></i> Wire Leases</button>
        <a href="map_export.php" class="tool-btn" style="text-decoration:none;"><i class="fa fa-file-export" style="color:#10b981;"></i> Export KML</a>
        <button class="tool-btn" onclick="refreshData()" style="background:#fff;"><i class="fa fa-sync"></i> Refresh Data</button>
    </div>
</div>

<div id="nodeModal" class="ftth-modal">
    <div class="ftth-modal-content">
        <div class="modal-header"><h3 id="modalTitle">Asset Manager</h3><span onclick="closeModal('nodeModal')" style="font-size:24px; cursor:pointer;">&times;</span></div>
        <div class="modal-body">
            <input type="hidden" id="nodeId"><input type="hidden" id="nodeLat"><input type="hidden" id="nodeLng"><input type="hidden" id="nodeType">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div><label>Asset Name / ID</label><input type="text" id="nodeName"></div>
                <div><label>Capacity</label><select id="nodeCapacity" onchange="handleCapacityChange()"></select></div>
            </div>

            <div style="margin-top:10px; background:#f8fafc; padding:15px; border-radius:10px; border:1px solid #e2e8f0;">
                <label id="connectionLabel" style="font-size:13px; margin-bottom:10px; color:#1e293b;">Port & Fiber Mapping</label>
                <div id="splicingMatrix" style="overflow-x:auto;">
                    <table class="splicing-table">
                        <thead>
                            <tr id="tableHeader">
                                <th>Port / Link Name</th>
                                <th>Out Color</th>
                                <th style="text-align:center;">Splice</th>
                                <th>In Color</th>
                                <th>Target / User</th>
                            </tr>
                        </thead>
                        <tbody id="connectionBody"></tbody>
                    </table>
                </div>
            </div>

            <button class="btn btn-primary" onclick="saveNode()" style="width:100%; padding:12px; font-weight:700; margin-top:20px;"><i class="fa fa-save"></i> Save Infrastructure</button>
        </div>
    </div>
</div>

<!-- Fault Localization Modal -->
<div id="faultModal" class="ftth-modal">
    <div class="ftth-modal-content" style="max-width: 400px;">
        <div class="modal-header"><h3><i class="fa fa-bolt"></i> Fault Localizer</h3><span onclick="closeModal('faultModal')" style="cursor:pointer;">&times;</span></div>
        <div class="modal-body">
            <p style="font-size:12px; color:#64748b; margin-bottom:15px;">Pinpoint a fiber break by entering the distance from the source (OLT).</p>
            <label>Select Fiber Route</label>
            <select id="faultRouteId" class="form-control">
                <!-- Dynamic -->
            </select>
            <label>Distance from Source (Meters)</label>
            <input type="number" id="faultDistance" class="form-control" placeholder="e.g. 450">
            <button class="btn btn-primary" onclick="predictBreak()" style="width:100%; padding:12px;">Predict Break Point</button>
        </div>
    </div>
</div>

<!-- Wire Lease Manager Modal -->
<div id="leaseModal" class="ftth-modal">
    <div class="ftth-modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fa fa-handshake"></i> Wire Lease Management</h3>
            <span onclick="closeModal('leaseModal')" style="cursor:pointer;">&times;</span>
        </div>
        <div class="modal-body">
            <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap:30px;">
                <!-- Add New Lease Form -->
                <div style="background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                    <h4 style="margin-top:0;">New Lease Agreement</h4>
                    <form id="leaseForm">
                        <label>Select Fiber Route</label>
                        <select name="route_id" id="leaseRouteId" class="form-control" required></select>
                        
                        <label>Client Name / ISP</label>
                        <input type="text" name="client_name" class="form-control" placeholder="e.g. WorldLink" required>
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <div>
                                <label>Core Number</label>
                                <input type="number" name="core_number" class="form-control" min="1" required>
                            </div>
                            <div>
                                <label>Monthly Price</label>
                                <input type="number" name="monthly_price" class="form-control" placeholder="500" required>
                            </div>
                        </div>

                        <label>Lease Start Date</label>
                        <input type="date" name="lease_start" class="form-control" required value="<?= date('Y-m-d') ?>">
                        
                        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:15px; padding:12px;"><i class="fa fa-plus-circle"></i> Create Lease</button>
                    </form>
                </div>

                <!-- Active Leases List -->
                <div>
                    <h4 style="margin-top:0;">Active Leases</h4>
                    <div id="leaseList" style="max-height:400px; overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
    function openLeaseManager() {
        document.getElementById('leaseModal').style.display = 'block';
        loadLeases();
        
        // Populate Routes Dropdown
        let sel = document.getElementById('leaseRouteId');
        sel.innerHTML = '<option value="">-- Select Fiber Route --</option>';
        // We assume 'data.routes' is globally available from map initialization, or we fetch again
        // Ideally, we fetch fresh list
        fetch('map_api.php?action=get_data').then(r=>r.json()).then(d => {
            d.routes.forEach(r => {
                let opt = document.createElement('option');
                opt.value = r.id;
                opt.innerText = `${r.name} (${r.total_cores} Cores)`;
                sel.appendChild(opt);
            });
        });
    }

    function loadLeases() {
        fetch('wire_lease_api.php?action=list')
        .then(r => r.json())
        .then(data => {
            let list = document.getElementById('leaseList');
            if(data.length === 0) {
                list.innerHTML = '<div style="color:#94a3b8; text-align:center; padding:20px;">No active leases found.</div>';
                return;
            }
            list.innerHTML = data.map(l => `
                <div style="background:#fff; border:1px solid #e2e8f0; padding:15px; border-radius:10px; margin-bottom:10px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <b style="color:#1e293b;">${l.client_name}</b>
                        <span class="badge ${l.status==='Active'?'bg-success':'bg-danger'}">${l.status}</span>
                    </div>
                    <div style="font-size:12px; color:#64748b;">
                        <div>Route: <b>${l.route_name}</b> (Core #${l.core_number})</div>
                        <div>Started: ${l.lease_start}</div>
                        <div>Price: Rs. ${l.monthly_price}/mo</div>
                    </div>
                    ${l.status === 'Active' ? `
                    <button onclick="terminateLease(${l.id})" class="btn-action-sm btn-del" style="margin-top:10px;">
                        <i class="fa fa-ban"></i> Terminate Lease
                    </button>` : ''}
                </div>
            `).join('');
        });
    }

    document.getElementById('leaseForm').onsubmit = function(e) {
        e.preventDefault();
        let fd = new FormData(this);
        fd.append('action', 'add');
        
        fetch('wire_lease_api.php?action=add', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                this.reset();
                loadLeases();
                alert("Lease created successfully!");
            } else {
                alert("Error: " + res.message);
            }
        });
    };

    function terminateLease(id) {
        if(!confirm("Are you sure you want to terminate this lease? Core will be freed.")) return;
        let fd = new FormData();
        fd.append('id', id);
        fetch('wire_lease_api.php?action=terminate', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') loadLeases();
        });
    }

    const fiberColors = {
        "Blue": "#0000FF", "Orange": "#FF8C00", "Green": "#008000", "Brown": "#8B4513", 
        "Slate": "#808080", "White": "#FFFFFF", "Red": "#FF0000", "Black": "#000000", 
        "Yellow": "#FFFF00", "Violet": "#EE82EE", "Rose": "#FFC0CB", "Aqua": "#00FFFF"
    };

    var map = L.map('map').setView([27.7172, 85.3240], 15);

    var googleStreets = L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',{maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3'], attribution: 'Google'});
    var googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}',{maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3'], attribution: 'Google'});
    var googleTerrain = L.tileLayer('http://{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}',{maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3'], attribution: 'Google'});
    googleHybrid.addTo(map);
    L.control.layers({"Satellite": googleHybrid, "Roadmap": googleStreets, "Terrain": googleTerrain}).addTo(map);

    var markersLayer = L.layerGroup().addTo(map), routesLayer = L.layerGroup().addTo(map), currentMode = null, currentNodeType = null, allNodes = [];

    var drawControl = new L.Control.Draw({ draw: { polyline: { shapeOptions: { color: '#3b82f6', weight: 4 } }, polygon: false, circle: false, rectangle: false, marker: false, circlemarker: false } });
    map.addControl(drawControl);

    function updateDropdownColor(sel) {
        let color = fiberColors[sel.value] || 'white';
        sel.style.backgroundColor = color;
        sel.style.color = (sel.value === 'Black' || sel.value === 'Blue' || sel.value === 'Brown' || sel.value === 'Red') ? 'white' : 'black';
    }

    function setMode(mode, btn) {
        currentMode = mode; currentNodeType = btn ? btn.getAttribute('data-type') : null;
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        if(btn) btn.classList.add('active');
    }

    function toggleFiberDrawing() { new L.Draw.Polyline(map, drawControl.options.draw.polyline).enable(); }

    map.on('click', function(e) { if (currentMode === 'add_node') openManager(null, currentNodeType, e.latlng); });

    function openManager(id, type, latlng) {
        document.getElementById('nodeId').value = id || '';
        document.getElementById('nodeType').value = type;
        document.getElementById('nodeLat').value = latlng ? latlng.lat : '';
        document.getElementById('nodeLng').value = latlng ? latlng.lng : '';
        document.getElementById('modalTitle').innerText = (id ? "Edit " : "Add ") + type;
        
        let sel = document.getElementById('nodeCapacity'); sel.innerHTML = '';
        if(type === 'OLT') [4, 8, 16, 32, 48, 64].forEach(v => sel.add(new Option(v + " Ports", v)));
        else if(type === 'MASTER_BOX' || type === 'DB_BOX') [4, 8, 16].forEach(v => sel.add(new Option(v + " Ports", v)));
        else if(['ENCLOSURE', 'JOINT'].includes(type)) [2, 4, 6, 12, 24, 48].forEach(v => sel.add(new Option(v + " Cores", v)));
        else sel.add(new Option("N/A", 0));

        if (id) {
            fetch('map_api.php?action=get_node_details&id=' + id).then(r => r.json()).then(data => {
                document.getElementById('nodeName').value = data.node.name;
                document.getElementById('nodeCapacity').value = data.node.capacity;
                handleCapacityChange(data.ports, JSON.parse(data.node.metadata || '{}'));
                document.getElementById('nodeModal').style.display = 'block';
            });
        } else {
            document.getElementById('nodeName').value = '';
            handleCapacityChange();
            document.getElementById('nodeModal').style.display = 'block';
        }
    }

    function handleCapacityChange(existingPorts = [], metadata = {}) {
        let type = document.getElementById('nodeType').value, cap = document.getElementById('nodeCapacity').value;
        let body = document.getElementById('connectionBody'), h = '';
        let colorNames = Object.keys(fiberColors);
        
        for(let i=1; i<=cap; i++) {
            let pData = existingPorts.find(p => p.port_number == i);
            let inCol = pData?.in_color || colorNames[(i-1)%12];
            let outCol = pData?.out_color || colorNames[(i-1)%12];
            let pName = pData?.port_name || '';
            
            h += `<tr>`;
            h += `<td><input type="text" class="p-name" data-port="${i}" value="${pName}" placeholder="P${i} Name"></td>`;
            h += `<td><select class="color-select p-out-col" data-port="${i}" onchange="updateDropdownColor(this)">${colorNames.map(c=>`<option value="${c}" ${outCol==c?'selected':''}>${c}</option>`).join('')}</select></td>`;
            h += `<td style="text-align:center;">&rarr;</td>`;
            h += `<td><select class="color-select p-in-col" data-port="${i}" onchange="updateDropdownColor(this)">${colorNames.map(c=>`<option value="${c}" ${inCol==c?'selected':''}>${c}</option>`).join('')}</select></td>`;

            if(type === 'DB_BOX') {
                h += `<td><input type="text" class="p-user" data-port="${i}" value="${pData?.customer_username || ''}" placeholder="Username"></td>`;
            } else if(type === 'OLT' || type === 'MASTER_BOX') {
                let targetType = (type === 'OLT') ? 'MASTER_BOX' : 'DB_BOX';
                let options = allNodes.filter(n => n.type === targetType).map(n => `<option value="${n.id}" ${pData?.linked_node_id == n.id ? 'selected' : ''}>${n.name}</option>`).join('');
                h += `<td><select class="p-link" data-port="${i}"><option value="">-- Link --</option>${options}</select></td>`;
            } else {
                h += `<td>Spliced</td>`;
            }
            h += `</tr>`;
        }
        body.innerHTML = h;
        document.querySelectorAll('.color-select').forEach(updateDropdownColor);
    }

    function saveNode() {
        let id = document.getElementById('nodeId').value, type = document.getElementById('nodeType').value;
        let fd = new FormData();
        fd.append('id', id); fd.append('name', document.getElementById('nodeName').value); fd.append('type', type);
        fd.append('lat', document.getElementById('nodeLat').value); fd.append('lng', document.getElementById('nodeLng').value);
        fd.append('capacity', document.getElementById('nodeCapacity').value);

        fetch('map_api.php?action=' + (id ? 'update_node_data' : 'add_node'), { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let newNodeId = id || data.id;
            let promises = [];
            for(let i=1; i<=document.getElementById('nodeCapacity').value; i++) {
                let pfd = new FormData();
                pfd.append('node_id', newNodeId); pfd.append('port_number', i);
                let pNameInp = document.querySelector(`.p-name[data-port="${i}"]`);
                let outColSel = document.querySelector(`.p-out-col[data-port="${i}"]`);
                let inColSel = document.querySelector(`.p-in-col[data-port="${i}"]`);
                let linkSel = document.querySelector(`.p-link[data-port="${i}"]`);
                let userInp = document.querySelector(`.p-user[data-port="${i}"]`);
                if(pNameInp) pfd.append('port_name', pNameInp.value);
                if(outColSel) pfd.append('out_color', outColSel.value);
                if(inColSel) pfd.append('in_color', inColSel.value);
                if(linkSel) pfd.append('linked_node_id', linkSel.value);
                if(userInp) pfd.append('username', userInp.value);
                promises.push(fetch('map_api.php?action=update_port', { method: 'POST', body: pfd }));
            }
            Promise.all(promises).then(() => { closeModal('nodeModal'); refreshData(); });
        });
    }

    function openFaultTool() {
        fetch('map_api.php?action=get_data').then(r => r.json()).then(data => {
            let sel = document.getElementById('faultRouteId'); sel.innerHTML = '';
            data.routes.forEach(r => sel.add(new Option(r.name, r.id)));
            document.getElementById('faultModal').style.display = 'block';
        });
    }

    function predictBreak() {
        let rid = document.getElementById('faultRouteId').value;
        let dist = document.getElementById('faultDistance').value;
        if(!rid || !dist) return alert("Select route and distance!");
        
        let fd = new FormData(); fd.append('route_id', rid); fd.append('distance', dist);
        fetch('map_api.php?action=predict_fault', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if(data.status === 'success') {
                L.marker([data.point.lat, data.point.lng], {
                    icon: L.divIcon({ className: '', html: `<div style="color:#ef4444; font-size:24px; text-shadow:0 0 10px #fff;"><i class="fa fa-bolt"></i></div>` })
                }).addTo(map).bindPopup("<b>PREDICTED BREAK</b><br>Dist: "+dist+"m").openPopup();
                map.setView([data.point.lat, data.point.lng], 18);
                closeModal('faultModal');
            }
        });
    }

    function refreshData() {
        fetch('map_api.php?action=get_data').then(r => r.json()).then(data => {
            allNodes = data.nodes; markersLayer.clearLayers(); routesLayer.clearLayers();
            data.nodes.forEach(n => {
                let colors = { 'OLT': '#ef4444', 'POLE': '#64748b', 'MASTER_BOX': '#f59e0b', 'DB_BOX': '#3b82f6', 'ENCLOSURE': '#8b5cf6', 'JOINT': '#10b981' };
                let icons = { 'OLT': 'fa-server', 'POLE': 'fa-broadcast-tower', 'MASTER_BOX': 'fa-box', 'DB_BOX': 'fa-boxes', 'ENCLOSURE': 'fa-shield-halved', 'JOINT': 'fa-link' };
                let m = L.marker([n.lat, n.lng], { draggable: true, icon: L.divIcon({ className: '', html: `<div style="background-color:${colors[n.type]}; width:24px; height:24px; border-radius:50%; border:2px solid white; box-shadow:0 0 8px rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; color:white; font-size:12px;"><i class="fa ${icons[n.type]}"></i></div>`, iconSize: [24, 24], iconAnchor: [12, 12] }) }).addTo(markersLayer);
                m.on('dragend', (e) => {
                    let fd = new FormData(); fd.append('id', n.id); fd.append('lat', e.target.getLatLng().lat); fd.append('lng', e.target.getLatLng().lng);
                    fetch('map_api.php?action=update_node_pos', { method: 'POST', body: fd });
                });
                m.bindPopup(`<b>${n.type}: ${n.name}</b><div class="popup-actions"><button class="btn-action-sm btn-view" onclick="openManager(${n.id}, '${n.type}', null)"><i class="fa fa-edit"></i> View / Edit</button><button class="btn-action-sm btn-del" onclick="deleteNode(${n.id})"><i class="fa fa-trash"></i> Delete</button></div>`);
            });
            data.routes.forEach(r => {
                let color = '#3b82f6';
                if (r.total_cores > 0) {
                    let utilization = (r.used_cores / r.total_cores);
                    if (utilization >= 0.8) color = '#ef4444'; // 80%+ Full
                    else if (utilization > 0) color = '#10b981'; // In use
                }

                L.polyline(JSON.parse(r.path_data), { 
                    color: color, 
                    weight: 4,
                    opacity: 0.8 
                }).addTo(routesLayer).bindPopup(`
                    <div style="min-width:180px;">
                        <b style="font-size:14px;">Fiber: ${r.name}</b><br>
                        <hr style="margin:8px 0; border:0; border-top:1px solid #eee;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span>Length:</span> <b>${Math.round(r.calculated_length_m)}m</b>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span>Cores:</span> <b>${r.used_cores} / ${r.total_cores} Used</b>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <span>Loss (Est):</span> <b>${r.predicted_loss_db} dB</b>
                        </div>
                        <button class="btn-action-sm btn-del" onclick="deleteRoute(${r.id})">
                            <i class="fa fa-trash"></i> Delete Route
                        </button>
                    </div>
                `);
            });
            data.customers.forEach(c => {
                let color = '#10b981'; // Active (Green)
                let status_label = 'Active';
                
                if (c.blocked == 1) {
                    color = '#ef4444'; // Blocked (Red)
                    status_label = 'Blocked';
                } else if (c.expiry) {
                    let expiryDate = new Date(c.expiry);
                    let today = new Date();
                    if (expiryDate < today) {
                        color = '#f59e0b'; // Expired (Orange)
                        status_label = 'Expired';
                    }
                }

                L.circleMarker([c.lat, c.lng], { 
                    radius: 6, 
                    fillColor: color, 
                    color: "#fff", 
                    weight: 2, 
                    fillOpacity: 1 
                }).addTo(map).bindPopup(`
                    <div style="text-align:center; min-width:150px;">
                        <div style="margin-bottom:8px;">
                            <b style="font-size:14px;">${c.full_name}</b><br>
                            <small style="color:#64748b;">@${c.username}</small>
                        </div>
                        <div style="display:inline-block; padding:2px 8px; border-radius:12px; background:${color}22; color:${color}; font-size:11px; font-weight:700; margin-bottom:10px;">
                            ${status_label}
                        </div>
                        <div class="popup-actions">
                            <a href="user_view.php?user=${c.username}" class="btn-action-sm btn-view" target="_blank">
                                <i class="fa fa-user"></i> View Profile
                            </a>
                            <a href="https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${c.lat},${c.lng}" class="btn-action-sm" style="background:#f8fafc; color:#1e293b;" target="_blank">
                                <i class="fa fa-street-view"></i> Street View
                            </a>
                        </div>
                    </div>
                `);
            });
            
            // Render Active Faults
            if(data.faults) {
                data.faults.forEach(f => {
                    L.marker([f.predicted_lat, f.predicted_lng], {
                        icon: L.divIcon({ className: '', html: `<div style="color:#ef4444; font-size:20px; animation: pulse 1s infinite;"><i class="fa fa-bolt"></i></div>` })
                    }).addTo(map).bindPopup(`<b>BREAK POINT</b><br>${f.description}`);
                });
            }
        });
    }

    function deleteNode(id) { if(confirm('Delete?')) fetch('map_api.php?action=delete_node', { method: 'POST', body: new URLSearchParams({id}) }).then(() => refreshData()); }
    function deleteRoute(id) { if(confirm('Delete?')) fetch('map_api.php?action=delete_route', { method: 'POST', body: new URLSearchParams({id}) }).then(() => refreshData()); }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; setMode(null, null); }

    // --- Pillar 2: GIS Intelligence Helpers ---
    function calculatePathLength(latlngs) {
        let total = 0;
        for (let i = 0; i < latlngs.length - 1; i++) {
            total += latlngs[i].distanceTo(latlngs[i+1]);
        }
        return total; // meters
    }

    function predictSignalLoss(lengthM, spliceCount = 2) {
        const lossPerKm = 0.35; // Standard 1310nm
        const spliceLoss = 0.1;
        return (lengthM / 1000 * lossPerKm) + (spliceCount * spliceLoss);
    }

    map.on(L.Draw.Event.CREATED, function (e) {
        let path = e.layer.getLatLngs();
        let lengthM = calculatePathLength(path);
        let lossDB = predictSignalLoss(lengthM);
        
        let name = prompt(`Fiber Name:\nLength: ${Math.round(lengthM)}m\nPredicted Loss: ${lossDB.toFixed(2)}dB`);
        if (name) {
            let cores = prompt("Number of Cores (e.g., 2, 4, 6, 12, 24):", "4");
            let fd = new FormData(); 
            fd.append('name', name); 
            fd.append('path', JSON.stringify(path));
            fd.append('length', lengthM);
            fd.append('loss', lossDB);
            fd.append('total_cores', cores || 4);
            fetch('map_api.php?action=save_route', { method: 'POST', body: fd }).then(() => refreshData());
        }
    });

    window.onload = refreshData;
</script>

<?php include 'includes/footer.php'; ?>
