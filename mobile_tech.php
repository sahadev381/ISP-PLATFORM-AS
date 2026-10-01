<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php';

$page_title = "Smart Tech Pro - Field Force";
$active = "mobile";
$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch branch info
$branch_id = $_SESSION['branch_id'] ?? 0;

include __DIR__ . '/includes/header.php';
?>

<!-- Mobile Optimized Meta -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<link rel="stylesheet" href="assets/css/mobile-tech.css">

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
            <?= e($username[0]) ?>
        </div>
        <h2 style="margin:0;"><?= e($username) ?></h2>
        <p style="color:var(--secondary);">Field Tech &bull; #778<?= e($admin_id) ?></p>
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
<script src="assets/js/mobile-tech.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
