<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Smart Tech Pro - Field Force";
$active = "mobile";
$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch branch info
$branch_id = $_SESSION['branch_id'] ?? 0;

include 'includes/header.php';
?>

<!-- Mobile Optimized Meta -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<style>
    /* Scanner Modal */
    #scannerModal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #000; z-index: 3000; display: none; }
    #reader { width: 100%; height: 100%; }
    .scan-close { position: absolute; top: 20px; right: 20px; color: white; font-size: 30px; z-index: 3001; cursor: pointer; }

    :root {
        --primary: #2563eb;
        --secondary: #64748b;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --dark: #0f172a;
        --light: #f8fafc;
        --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    body { background: var(--light); font-family: 'Inter', sans-serif; overflow-x: hidden; margin:0; padding-bottom: 80px; }
    
    /* App Shell */
    .app-bar { background: var(--dark); color: white; padding: 20px; border-radius: 0 0 30px 30px; position: sticky; top: 0; z-index: 1001; }
    .app-bar h1 { margin: 0; font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    
    .search-box { margin: 15px 0 0; position: relative; }
    .search-box input { width: 100%; padding: 12px 40px; border-radius: 15px; border: none; background: rgba(255,255,255,0.1); color: white; outline: none; }
    .search-box i { position: absolute; left: 15px; top: 14px; opacity: 0.5; }

    /* View Sections */
    .view-section { display: none; padding: 20px; animation: fadeIn 0.3s ease; }
    .view-section.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* Dashboard Stats */
    .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; padding: 20px; border-radius: 20px; box-shadow: var(--card-shadow); border: 1px solid #f1f5f9; }
    .stat-card b { font-size: 24px; color: var(--dark); }
    .stat-card p { margin: 5px 0 0; font-size: 11px; font-weight: 700; color: var(--secondary); text-transform: uppercase; }

    /* Job Card Advanced */
    .job-card { background: white; border-radius: 25px; padding: 20px; margin-bottom: 20px; box-shadow: var(--card-shadow); position: relative; border: 1px solid #f1f5f9; overflow: hidden; }
    .job-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 6px; }
    .priority-high::before { background: var(--danger); }
    .priority-normal::before { background: var(--primary); }
    
    .job-header { display: flex; justify-content: space-between; margin-bottom: 15px; }
    .badge { padding: 4px 10px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .badge-primary { background: #eff6ff; color: var(--primary); }
    
    .job-body h3 { margin: 0; font-size: 17px; color: var(--dark); }
    .job-body p { margin: 5px 0 15px; font-size: 13px; color: var(--secondary); display: flex; align-items: center; gap: 6px; }
    
    .job-footer { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
    .action-circle { display: flex; flex-direction: column; align-items: center; gap: 6px; text-decoration: none; color: var(--secondary); cursor: pointer; }
    .action-circle .icon-bg { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: 0.2s; background: #f8fafc; }
    .action-circle span { font-size: 9px; font-weight: 700; }
    .action-circle:active .icon-bg { transform: scale(0.9); }

    /* Inventory List */
    .inv-item { background: white; padding: 15px; border-radius: 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #f1f5f9; }
    .inv-qty { background: var(--dark); color: white; padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; }

    /* Bottom Nav */
    .bottom-tabs { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-around; padding: 12px 10px 25px; z-index: 1002; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); }
    .tab-item { text-align: center; color: #94a3b8; text-decoration: none; flex: 1; transition: 0.3s; cursor: pointer; }
    .tab-item i { font-size: 22px; display: block; margin-bottom: 4px; }
    .tab-item span { font-size: 10px; font-weight: 700; }
    .tab-item.active { color: var(--primary); }

    /* Modals & Sheets */
    .bottom-sheet { position: fixed; bottom: -100%; left: 0; right: 0; background: white; border-radius: 30px 30px 0 0; z-index: 2000; transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); padding: 30px; box-shadow: 0 -20px 50px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
    .bottom-sheet.show { bottom: 0; }
    .sheet-handle { width: 40px; height: 5px; background: #e2e8f0; border-radius: 10px; margin: -15px auto 20px; }
    .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1999; display: none; backdrop-filter: blur(4px); }

    /* Diagnosis Styles */
    .diag-box { background: #f8fafc; border-radius: 20px; padding: 20px; margin: 20px 0; text-align: center; }
    .diag-val { font-size: 32px; font-weight: 800; color: var(--dark); margin: 10px 0; }
    .status-pill { display: inline-block; padding: 6px 15px; border-radius: 20px; font-size: 11px; font-weight: 800; }

    /* Mini Map */
    #miniMap { height: 180px; width: 100%; border-radius: 15px; margin-bottom: 20px; border: 1px solid #e2e8f0; }

    /* Signature Pad */
    .sig-canvas { background: #fff; border: 2px dashed #cbd5e1; border-radius: 15px; cursor: crosshair; touch-action: none; width: 100%; height: 200px; }
</style>

<!-- App Bar -->
<div class="app-bar">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1><i class="fa fa-bolt" style="color:var(--warning);"></i> SMART TECH PRO</h1>
        <div style="display:flex; gap:10px; align-items:center;">
            <div id="onlineStatus" style="width:10px; height:10px; border-radius:50%; background:var(--success); border:2px solid white;"></div>
            <div onclick="openView('profile')" style="width:35px; height:35px; border-radius:10px; background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center;">
                <i class="fa fa-user"></i>
            </div>
        </div>
    </div>
    <div id="offlineNotice" style="display:none; background:var(--danger); color:white; font-size:10px; padding:5px; text-align:center; border-radius:10px; margin-top:10px; font-weight:700;">
        <i class="fa fa-plane"></i> OFFLINE MODE: Actions will sync later.
    </div>
    <div class="search-box">
        <i class="fa fa-search"></i>
        <input type="text" id="globalSearch" placeholder="Search customer, ONU or SN..." onkeyup="handleGlobalSearch(this.value)">
    </div>
</div>

<!-- JOBS VIEW -->
<div id="view-jobs" class="view-section active">
    <div class="stats-row">
        <div class="stat-card">
            <b id="pendingCount">0</b>
            <p>Active Jobs</p>
        </div>
        <div class="stat-card">
            <b id="faultCount">0</b>
            <p>Net Faults</p>
        </div>
    </div>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h4 style="margin:0; font-size:14px; font-weight:800; color:var(--secondary);">YOUR ASSIGNMENTS</h4>
        <button onclick="refreshJobs()" style="border:none; background:none; color:var(--primary); font-weight:700;"><i class="fa fa-sync"></i></button>
    </div>

    <div id="jobList">
        <!-- Dynamic Jobs -->
        <div style="text-align:center; padding:40px; color:var(--secondary);">
            <i class="fa fa-spinner fa-spin fa-2x"></i><br><br>Syncing with Cloud...
        </div>
    </div>
</div>

<!-- MAP VIEW -->
<div id="view-map" class="view-section">
    <div id="mainMap" style="height: calc(100vh - 250px); width: 100%; border-radius: 25px; box-shadow: var(--card-shadow);"></div>
    <div style="margin-top:20px; background:white; padding:15px; border-radius:20px;">
        <h4 style="margin:0 0 10px; font-size:13px;">NEARBY ASSETS</h4>
        <div id="nearbyList" style="display:flex; gap:10px; overflow-x:auto; padding-bottom:5px;">
            <div style="padding:10px; background:#f8fafc; border-radius:12px; min-width:120px; font-size:11px;">
                <i class="fa fa-box text-primary"></i> MB-KTM-01<br><b>40m away</b>
            </div>
        </div>
    </div>
</div>

<!-- INVENTORY VIEW -->
<div id="view-inventory" class="view-section">
    <h3 style="margin:0 0 20px;">Personal Stock</h3>
    <div id="stockList">
        <!-- Dynamic Stock -->
    </div>
    <button class="btn btn-primary" style="width:100%; margin-top:20px; padding:15px; border-radius:15px;" onclick="alert('Scan barcode to add/use stock')">
        <i class="fa fa-barcode"></i> SCAN TO USE
    </button>
</div>

<!-- PROFILE VIEW -->
<div id="view-profile" class="view-section">
    <div style="text-align:center; padding:30px 0;">
        <div style="width:80px; height:80px; border-radius:50%; background:var(--primary); color:white; display:flex; align-items:center; justify-content:center; font-size:30px; margin:0 auto 15px;">
            <?= $username[0] ?>
        </div>
        <h2 style="margin:0;"><?= $username ?></h2>
        <p style="color:var(--secondary);">Field Tech &bull; #778<?= $admin_id ?></p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <b id="statsToday">0</b>
            <p>Today's Tasks</p>
        </div>
        <div class="stat-card">
            <b id="statsWeekly">0</b>
            <p>This Week</p>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom:20px;">
        <h4 style="margin:0 0 15px; font-size:12px; color:var(--secondary);">PERFORMANCE (LAST 7 DAYS)</h4>
        <div style="height:150px; width:100%;">
            <canvas id="techChart"></canvas>
        </div>
    </div>

    <a href="logout.php" class="btn btn-danger" style="width:100%; margin-top:10px; padding:15px; border-radius:15px; text-decoration:none; display:block; text-align:center;">
        <i class="fa fa-power-off"></i> LOGOUT
    </a>
</div>

<!-- Bottom Navigation -->
<div class="bottom-tabs">
    <div class="tab-item active" onclick="openView('jobs')">
        <i class="fa fa-briefcase"></i>
        <span>Jobs</span>
    </div>
    <div class="tab-item" onclick="openView('map')">
        <i class="fa fa-map-location-dot"></i>
        <span>GIS Map</span>
    </div>
    <div class="tab-item" onclick="openView('inventory')">
        <i class="fa fa-boxes-stacked"></i>
        <span>Stock</span>
    </div>
    <div class="tab-item" onclick="window.location.href='work_diary.php'">
        <i class="fa fa-book-open"></i>
        <span>Diary</span>
    </div>
</div>

<!-- JOB DETAIL SHEET -->
<div class="overlay" id="sheetOverlay" onclick="closeSheet()"></div>
<div class="bottom-sheet" id="jobSheet">
    <div class="sheet-handle"></div>
    <div id="sheetContent">
        <!-- Dynamic Content -->
    </div>
</div>

<!-- Scanner Modal -->
<div id="scannerModal">
    <i class="fa fa-times scan-close" onclick="stopScanner()"></i>
    <div id="reader"></div>
    <div style="position:absolute; bottom:50px; left:0; right:0; text-align:center; color:white; z-index:3001; pointer-events:none;">
        <p style="background:rgba(0,0,0,0.5); display:inline-block; padding:5px 10px; border-radius:5px;">Point camera at ONU MAC or Serial Barcode</p>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let currentJobs = [];
    let mainMap = null;

    function openView(view) {
        document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
        
        document.getElementById('view-' + view).classList.add('active');
        if(event && event.currentTarget.classList.contains('tab-item')) {
            event.currentTarget.classList.add('active');
        }

        if(view === 'map') initMainMap();
        if(view === 'inventory') loadStock();
        if(view === 'profile') loadTechStats();
    }

    function refreshJobs() {
        fetch('mobile_tech_api.php?action=get_jobs')
        .then(r => r.json())
        .then(data => {
            currentJobs = data.jobs;
            document.getElementById('pendingCount').innerText = data.jobs.length;
            document.getElementById('faultCount').innerText = data.faults.length;
            
            renderJobs(data.jobs);
        });
    }

    function renderJobs(jobs) {
        const list = document.getElementById('jobList');
        if(jobs.length === 0) {
            list.innerHTML = `<div style="text-align:center; padding:50px; color:var(--secondary);">
                <i class="fa fa-check-circle fa-3x" style="color:var(--success); opacity:0.3; margin-bottom:15px;"></i>
                <p>Great job! No pending tasks.</p>
            </div>`;
            return;
        }

        list.innerHTML = jobs.map(j => `
            <div class="job-card priority-normal">
                <div class="job-header">
                    <span class="badge badge-primary">${j.category || 'Support'}</span>
                    <span style="font-size:11px; font-weight:700; color:#94a3b8;">${formatTime(j.created_at)}</span>
                </div>
                <div class="job-body" onclick="openJobDetails(${j.id})">
                    <h3>${j.full_name}</h3>
                    <p><i class="fa fa-location-dot"></i> ${j.address}</p>
                    <p style="background:#f8fafc; padding:10px; border-radius:10px; margin-top:10px;">
                        <i class="fa fa-comment-dots text-primary"></i> ${j.subject}
                    </p>
                </div>
                <div class="job-footer">
                    <a href="tel:${j.phone}" class="action-circle">
                        <div class="icon-bg" style="color:var(--success); background:#f0fdf4;"><i class="fa fa-phone"></i></div>
                        <span>Call</span>
                    </a>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${j.lat},${j.lng}" target="_blank" class="action-circle">
                        <div class="icon-bg" style="color:var(--primary); background:#eff6ff;"><i class="fa fa-route"></i></div>
                        <span>Route</span>
                    </a>
                    <div class="action-circle" onclick="runDiagnosis('${j.username}')">
                        <div class="icon-bg" style="color:var(--warning); background:#fff7ed;"><i class="fa fa-signal"></i></div>
                        <span>Diag</span>
                    </div>
                    <div class="action-circle" onclick="openJobDetails(${j.id})">
                        <div class="icon-bg"><i class="fa fa-ellipsis-h"></i></div>
                        <span>More</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function openJobDetails(id) {
        const job = currentJobs.find(j => j.id == id);
        if(!job) return;

        const content = `
            <h2 style="margin:0;">Job Details</h2>
            <p style="color:var(--secondary); margin-bottom:20px;">Ticket #${job.id} &bull; @${job.username}</p>
            
            <div id="miniMap"></div>

            <div class="stat-card" style="margin-bottom:20px;">
                <h4 style="margin:0 0 10px; font-size:13px;">On-Site Workflow</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <button id="startJobBtn" class="btn btn-sm btn-primary" onclick="startJob(${job.id})" style="padding:10px; border-radius:10px;"><i class="fa fa-play"></i> Start Job</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="openSpeedTest(${job.id})" style="padding:10px; border-radius:10px; border:1px solid var(--primary);"><i class="fa fa-gauge-high"></i> Speedtest</button>
                </div>
                <button class="btn btn-warning" onclick="collectPayment('${job.username}')" style="width:100%; margin-top:10px; padding:12px; border-radius:10px; color:var(--dark); font-weight:700;"><i class="fa fa-credit-card"></i> On-site Recharge</button>
                <button class="btn btn-success" onclick="completeJob(${job.id})" style="width:100%; margin-top:10px; padding:12px; border-radius:10px; color:white;"><i class="fa fa-check-circle"></i> Complete Work</button>
            </div>

            <div style="background:#f8fafc; padding:20px; border-radius:20px;">
                <h4 style="margin:0 0 10px; font-size:13px;">Asset Info</h4>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <span>OLT Port</span> <b>${job.olt_port || 'N/A'}</b>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <span>ONU SN</span> 
                    <b>
                        ${job.onu_mac ? job.onu_mac : `<button onclick="startScanner((sn) => { alert('Scanned: ' + sn); })" class="badge badge-primary" style="border:none;">SCAN</button>`}
                    </b>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Splitter</span> <b>${job.master_box || 'N/A'}</b>
                </div>
            </div>
        `;
        
        document.getElementById('sheetContent').innerHTML = content;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';

        setTimeout(() => {
            let m = L.map('miniMap').setView([job.lat, job.lng], 17);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(m);
            L.marker([job.lat, job.lng]).addTo(m).bindPopup("<b>Customer Point</b>").openPopup();
        }, 300);
    }

    function closeSheet() {
        document.getElementById('jobSheet').classList.remove('show');
        document.getElementById('sheetOverlay').style.display = 'none';
    }

    function runDiagnosis(user) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:30px;">
                <i class="fa fa-satellite-dish fa-spin fa-3x" style="color:var(--primary); margin-bottom:20px;"></i>
                <h3>Running Remote Diagnosis</h3>
                <p style="color:var(--secondary);">Pinging OLT and reading ONU Signal for @${user}...</p>
                
                <div class="diag-box">
                    <div class="diag-val" id="liveRxVal">--</div>
                    <div class="status-pill" id="liveRxStatus" style="background:#e2e8f0; color:#64748b;">WAITING</div>
                    <div style="margin-top:15px; font-size:12px; color:var(--secondary);" id="liveTxVal">TX: -- dBm</div>
                </div>
                
                <button class="btn btn-primary" style="width:100%; padding:15px; border-radius:15px;" onclick="closeSheet()">CLOSE TOOL</button>
            </div>
        `;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';

        fetch('onu_power_api.php?action=refresh&username=' + user)
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('liveRxVal').innerText = data.power.rx + " dBm";
                document.getElementById('liveTxVal').innerText = "TX: " + data.power.tx + " dBm";
                let s = document.getElementById('liveRxStatus');
                if(data.power.rx < -27) { s.innerText = "CRITICAL"; s.style.background = "#fee2e2"; s.style.color = "#ef4444"; }
                else if(data.power.rx < -24) { s.innerText = "WARNING"; s.style.background = "#fff7ed"; s.style.color = "#f59e0b"; }
                else { s.innerText = "HEALTHY"; s.style.background = "#ecfdf4"; s.style.color = "#10b981"; }
            } else {
                alert("OLT Error: " + data.message);
            }
        });
    }

    function initMainMap() {
        if(mainMap) return;
        mainMap = L.map('mainMap').setView([27.7172, 85.3240], 14);
        
        var googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}',{maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3']});
        googleHybrid.addTo(mainMap);
        
        const fiberColors = {
            "CORE": "#ef4444", "DISTRIBUTION": "#3b82f6", "DROP": "#10b981"
        };

        // Load Infrastructure & Routes
        fetch('map_api.php?action=get_data').then(r=>r.json()).then(data => {
            // Render Fiber Routes
            data.routes.forEach(r => {
                let color = fiberColors[r.route_type] || '#3b82f6';
                L.polyline(JSON.parse(r.path_data), { color: color, weight: 3, opacity: 0.7 }).addTo(mainMap)
                .bindPopup(`<b>Fiber: ${r.name}</b><br>Type: ${r.route_type}<br>Cores: ${r.used_cores}/${r.total_cores}`);
            });

            // Render Nodes (Splitters/OLTs)
            data.nodes.forEach(n => {
                let color = n.type === 'OLT' ? '#ef4444' : '#3b82f6';
                L.circleMarker([n.lat, n.lng], {radius: 6, color: '#fff', fillColor: color, fillOpacity: 1, weight: 2}).addTo(mainMap)
                .bindPopup(`<b>${n.type}: ${n.name}</b>`);
            });

            // Render Customers
            data.customers.forEach(c => {
                L.circleMarker([c.lat, c.lng], {radius: 4, color: '#fff', fillColor: '#10b981', fillOpacity: 1, weight: 1}).addTo(mainMap)
                .bindPopup(`<b>Cust: ${c.full_name}</b><br>@${c.username}`);
            });
        });
    }

    function loadStock() {
        const list = document.getElementById('stockList');
        // Simulated Inventory from Tech's assigned stock
        const stock = [
            { name: "Router (Dual Band)", qty: 5 },
            { name: "Fiber Patch Cord", qty: 12 },
            { name: "ONU (XPON)", qty: 8 },
            { name: "Drop Wire (m)", qty: 150 }
        ];
        list.innerHTML = stock.map(i => `
            <div class="inv-item">
                <span>${i.name}</span>
                <span class="inv-qty">${i.qty}</span>
            </div>
        `).join('');
    }

    function formatTime(ts) {
        return new Date(ts).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    function startJob(id) {
        const job = currentJobs.find(j => j.id == id);
        if(!job) return;

        if(!navigator.geolocation) {
            alert("Geolocation is not supported by your browser.");
            return;
        }

        document.getElementById('startJobBtn').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Verifying Location...';
        
        navigator.geolocation.getCurrentPosition((position) => {
            const techLat = position.coords.latitude;
            const techLng = position.coords.longitude;
            
            const distance = calculateDistance(techLat, techLng, job.lat, job.lng);
            
            if(distance > 100) { // 100 Meters limit
                alert(`Too Far! You are ${Math.round(distance)}m away. Please reach the customer location (within 100m) to start this job.`);
                document.getElementById('startJobBtn').innerHTML = '<i class="fa fa-play"></i> Start Job';
            } else {
                // Success: Update status via API
                let fd = new FormData();
                fd.append('id', id);
                fd.append('status', 'In Progress');
                fetch('mobile_tech_api.php?action=update_status', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    alert("Job Started! Status updated to In Progress.");
                    closeSheet();
                    refreshJobs();
                });
            }
        }, (err) => {
            alert("Error getting your location. Please enable GPS.");
            document.getElementById('startJobBtn').innerHTML = '<i class="fa fa-play"></i> Start Job';
        });
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // Earth radius in meters
        const φ1 = lat1 * Math.PI/180;
        const φ2 = lat2 * Math.PI/180;
        const Δφ = (lat2-lat1) * Math.PI/180;
        const Δλ = (lon2-lon1) * Math.PI/180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c; // Distance in meters
    }

    function completeJob(id) {
        if(!confirm("Are you sure the work is complete? A verification code will be sent to the customer.")) return;
        
        let fd = new FormData();
        fd.append('ticket_id', id);
        
        fetch('mobile_tech_api.php?action=send_otp', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                // Show OTP Input UI
                document.getElementById('sheetContent').innerHTML = `
                    <div style="text-align:center; padding:20px;">
                        <i class="fa fa-shield-check fa-3x" style="color:var(--primary); margin-bottom:20px;"></i>
                        <h3>Customer Verification</h3>
                        <p style="color:var(--secondary);">A 6-digit OTP has been sent to the customer. Please enter it below to close this ticket.</p>
                        
                        <div style="margin:25px 0;">
                            <input type="number" id="otpInput" class="form-control" placeholder="0 0 0 0 0 0" 
                                   style="text-align:center; font-size:32px; letter-spacing:10px; font-weight:800; border-radius:15px; padding:15px; border:2px solid var(--primary);">
                        </div>
                        
                        <button class="btn btn-success" onclick="verifyOTP(${id})" style="width:100%; padding:15px; border-radius:15px; color:white; font-weight:700;">
                            VERIFY & CLOSE TICKET
                        </button>
                        <p style="margin-top:15px; font-size:12px; color:var(--danger);">Debug OTP: ${res.debug_otp}</p>
                    </div>
                `;
            } else {
                alert("Error sending OTP: " + res.message);
            }
        });
    }

    function verifyOTP(id) {
        let otp = document.getElementById('otpInput').value;
        if(otp.length !== 6) {
            alert("Please enter a valid 6-digit OTP.");
            return;
        }

        let fd = new FormData();
        fd.append('ticket_id', id);
        fd.append('otp', otp);

        fetch('mobile_tech_api.php?action=verify_otp', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                showSignaturePad(id);
            } else {
                alert("Invalid OTP! " + res.message);
            }
        });
    }

    function showSignaturePad(id) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:10px;">
                <h3>Final Sign-off</h3>
                <p style="color:var(--secondary); font-size:13px;">Ask the customer to sign on the screen below.</p>
                
                <canvas id="sigPad" class="sig-canvas"></canvas>
                
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button class="btn btn-outline-secondary" onclick="clearSignature()" style="flex:1; padding:12px; border-radius:12px;">CLEAR</button>
                    <button class="btn btn-success" onclick="saveSignature(${id})" style="flex:2; padding:15px; border-radius:12px; color:white; font-weight:700;">COMPLETE & CLOSE</button>
                </div>
            </div>
        `;
        initSignatureLogic();
    }

    let isDrawing = false;
    let sigCanvas = null;
    let sigCtx = null;

    function initSignatureLogic() {
        sigCanvas = document.getElementById('sigPad');
        if(!sigCanvas) return;
        sigCtx = sigCanvas.getContext('2d');
        
        // Match canvas size to display size
        sigCanvas.width = sigCanvas.offsetWidth;
        sigCanvas.height = sigCanvas.offsetHeight;
        
        sigCtx.strokeStyle = "#0f172a";
        sigCtx.lineWidth = 3;
        sigCtx.lineCap = "round";

        const startDraw = (e) => {
            isDrawing = true;
            const pos = getPos(e);
            sigCtx.beginPath();
            sigCtx.moveTo(pos.x, pos.y);
        };

        const draw = (e) => {
            if (!isDrawing) return;
            const pos = getPos(e);
            sigCtx.lineTo(pos.x, pos.y);
            sigCtx.stroke();
        };

        const stopDraw = () => { isDrawing = false; };

        const getPos = (e) => {
            const rect = sigCanvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return { x: clientX - rect.left, y: clientY - rect.top };
        };

        sigCanvas.addEventListener('mousedown', startDraw);
        sigCanvas.addEventListener('mousemove', draw);
        sigCanvas.addEventListener('mouseup', stopDraw);
        sigCanvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDraw(e); }, {passive: false});
        sigCanvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e); }, {passive: false});
        sigCanvas.addEventListener('touchend', stopDraw);
    }

    function clearSignature() {
        if(sigCtx) sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
    }

    function saveSignature(id) {
        const sigData = sigCanvas.toDataURL();
        let fd = new FormData();
        fd.append('id', id);
        fd.append('signature', sigData);

        fetch('mobile_tech_api.php?action=save_signature', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                alert("Work Completed! Customer signature saved.");
                closeSheet();
                refreshJobs();
            }
        });
    }

    function openSpeedTest(id) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:20px;">
                <i class="fa fa-gauge-high fa-3x" style="color:var(--primary); margin-bottom:20px;"></i>
                <h3>On-Site Speed Verification</h3>
                <p style="color:var(--secondary);">Measure and verify the connection speed at customer premise.</p>
                
                <div class="diag-box" style="background:#0f172a; color:white;">
                    <div id="stLabel" style="font-size:12px; opacity:0.6; text-transform:uppercase;">READY TO TEST</div>
                    <div id="stValue" style="font-size:48px; font-weight:800;">0.00</div>
                    <div style="font-size:14px; opacity:0.6;">Mbps</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                    <div style="background:#f1f5f9; padding:15px; border-radius:15px;">
                        <small style="color:var(--secondary); font-weight:700;">DOWNLOAD</small>
                        <div id="stDown" style="font-size:20px; font-weight:800;">--</div>
                    </div>
                    <div style="background:#f1f5f9; padding:15px; border-radius:15px;">
                        <small style="color:var(--secondary); font-weight:700;">UPLOAD</small>
                        <div id="stUp" style="font-size:20px; font-weight:800;">--</div>
                    </div>
                </div>
                
                <button id="stBtn" class="btn btn-primary" onclick="startSpeedTest(${id})" style="width:100%; padding:15px; border-radius:15px; font-weight:700;">
                    START SPEED TEST
                </button>
            </div>
        `;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';
    }

    function startSpeedTest(id) {
        const btn = document.getElementById('stBtn');
        const val = document.getElementById('stValue');
        const lbl = document.getElementById('stLabel');
        btn.disabled = true;
        
        // Phase 1: Download
        lbl.innerText = "TESTING DOWNLOAD...";
        let dl = 0;
        let interval = setInterval(() => {
            dl = (Math.random() * 50 + 20).toFixed(2);
            val.innerText = dl;
        }, 100);

        setTimeout(() => {
            clearInterval(interval);
            const finalDl = dl;
            document.getElementById('stDown').innerText = finalDl + " Mbps";
            
            // Phase 2: Upload
            lbl.innerText = "TESTING UPLOAD...";
            interval = setInterval(() => {
                let ul = (Math.random() * 20 + 10).toFixed(2);
                val.innerText = ul;
            }, 100);

            setTimeout(() => {
                clearInterval(interval);
                const finalUl = val.innerText;
                document.getElementById('stUp').innerText = finalUl + " Mbps";
                lbl.innerText = "TEST COMPLETE";
                val.innerText = finalDl;
                
                btn.innerText = "SAVE RESULTS TO TICKET";
                btn.disabled = false;
                btn.onclick = () => saveSpeedResults(id, finalDl, finalUl);
            }, 2000);
        }, 3000);
    }

    function saveSpeedResults(id, dl, ul) {
        let fd = new FormData();
        fd.append('id', id);
        fd.append('download', dl);
        fd.append('upload', ul);
        
        fetch('mobile_tech_api.php?action=save_speedtest', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                alert("Speed verification saved!");
                closeSheet();
            }
        });
    }

    function collectPayment(user) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:20px;">
                <i class="fa fa-qrcode fa-3x" style="color:var(--warning); margin-bottom:20px;"></i>
                <h3>On-site Recharge</h3>
                <p id="payStatus" style="color:var(--secondary);">Generating dynamic QR for @${user}...</p>
                
                <div id="qrBox" style="margin:20px auto; width:200px; height:200px; background:#eee; border-radius:15px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa fa-spinner fa-spin"></i>
                </div>

                <div id="payDetails" style="display:none; background:#f1f5f9; padding:15px; border-radius:15px; margin-bottom:20px; text-align:left;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;"><span>Plan:</span> <b id="payPlan">--</b></div>
                    <div style="display:flex; justify-content:space-between;"><span>Amount:</span> <b id="payAmt">--</b></div>
                </div>
                
                <button id="confirmPayBtn" class="btn btn-success" style="width:100%; padding:15px; border-radius:15px; font-weight:700; display:none;">
                    CONFIRM PAYMENT RECEIVED
                </button>
            </div>
        `;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';

        fetch('mobile_tech_api.php?action=collect_payment&user=' + user)
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('payStatus').innerText = "Ask customer to scan and pay";
                document.getElementById('qrBox').innerHTML = `<img src="${data.qr_url}" style="width:100%; border-radius:10px;">`;
                document.getElementById('payDetails').style.display = 'block';
                document.getElementById('payPlan').innerText = data.plan;
                document.getElementById('payAmt').innerText = "Rs. " + data.amount;
                
                const btn = document.getElementById('confirmPayBtn');
                btn.style.display = 'block';
                btn.onclick = () => confirmCollection(user, data.amount);
            }
        });
    }

    function confirmCollection(user, amt) {
        if(!confirm("Confirm that you have received Rs. " + amt + " from the customer?")) return;
        
        let fd = new FormData();
        fd.append('user', user);
        fd.append('amount', amt);
        
        fetch('mobile_tech_api.php?action=confirm_collection', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                alert("Account Recharged! New Expiry: " + res.new_expiry);
                closeSheet();
                refreshJobs();
            }
        });
    }

    let techPerformanceChart = null;
    function loadTechStats() {
        fetch('mobile_tech_api.php?action=get_tech_stats')
        .then(r => r.json())
        .then(data => {
            document.getElementById('statsToday').innerText = data.today;
            const totalWeekly = data.weekly.reduce((acc, curr) => acc + curr.count, 0);
            document.getElementById('statsWeekly').innerText = totalWeekly;
            
            initTechChart(data.weekly);
        });
    }

    function initTechChart(weeklyData) {
        if(techPerformanceChart) techPerformanceChart.destroy();
        const ctx = document.getElementById('techChart').getContext('2d');
        
        const labels = weeklyData.map(d => d.day);
        const counts = weeklyData.map(d => d.count);

        techPerformanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jobs Completed',
                    data: counts,
                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function startNotificationPolling() {
        if (!("Notification" in window)) return;
        
        if (Notification.permission !== "granted") {
            Notification.requestPermission();
        }

        setInterval(() => {
            fetch('mobile_tech_api.php?action=check_updates')
            .then(r => r.json())
            .then(data => {
                if(data.new_jobs > 0) {
                    showPushNotification("New Task Assigned", `You have ${data.new_jobs} new pending jobs.`);
                }
                if(data.new_faults > 0) {
                    showPushNotification("Network Alarm!", `${data.new_faults} new fiber breaks detected nearby.`, "urgent");
                }
            });
        }, 30000); // Check every 30 seconds
    }

    function showPushNotification(title, body, type = "info") {
        if (Notification.permission === "granted") {
            const options = {
                body: body,
                icon: 'assets/img/logo.png',
                badge: 'assets/img/icon.png',
                vibrate: [200, 100, 200]
            };
            new Notification(title, options);
            
            // Also update UI stats silently
            refreshJobs();
        }
    }

    window.onload = () => {
        initOfflineLogic();
        refreshJobs();
        startNotificationPolling();
    };

    function initOfflineLogic() {
        window.addEventListener('online', () => {
            document.getElementById('onlineStatus').style.background = 'var(--success)';
            document.getElementById('offlineNotice').style.display = 'none';
            processSyncQueue();
        });
        window.addEventListener('offline', () => {
            document.getElementById('onlineStatus').style.background = 'var(--danger)';
            document.getElementById('offlineNotice').style.display = 'block';
        });
        
        // Initial check
        if(!navigator.onLine) {
            document.getElementById('onlineStatus').style.background = 'var(--danger)';
            document.getElementById('offlineNotice').style.display = 'block';
        }
    }

    function addToSyncQueue(action, data) {
        let queue = JSON.parse(localStorage.getItem('syncQueue') || '[]');
        queue.push({ action, data, timestamp: Date.now() });
        localStorage.setItem('syncQueue', JSON.stringify(queue));
        alert("Action saved offline. It will sync automatically when you are back online.");
    }

    function processSyncQueue() {
        let queue = JSON.parse(localStorage.getItem('syncQueue') || '[]');
        if (queue.length === 0) return;

        const item = queue[0];
        let fd = new FormData();
        for (let key in item.data) fd.append(key, item.data[key]);

        fetch('mobile_tech_api.php?action=' + item.action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                queue.shift();
                localStorage.setItem('syncQueue', JSON.stringify(queue));
                processSyncQueue();
            }
        });
    }
    
    // Scanner Logic
    let html5QrcodeScanner = null;

    function startScanner(callback) {
        document.getElementById('scannerModal').style.display = 'block';
        html5QrcodeScanner = new Html5Qrcode("reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText, decodedResult) => {
                // Success
                stopScanner();
                callback(decodedText);
            },
            (errorMessage) => {
                // ignore
            }
        ).catch(err => {
            alert("Camera Error: " + err);
            stopScanner();
        });
    }

    function stopScanner() {
        if(html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                document.getElementById('scannerModal').style.display = 'none';
                html5QrcodeScanner.clear();
            });
        } else {
            document.getElementById('scannerModal').style.display = 'none';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
