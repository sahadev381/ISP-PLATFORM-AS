<?php
$base_path = './';
include 'config.php';
include 'includes/auth.php';
include 'includes/genieacs_api.php';
include 'includes/tr069_pppoe.php';

/* ============================
   LOAD USER
============================ */
$username = $_GET['user'] ?? '';
$user = $conn->query("
    SELECT u.*, p.name as plan_name, p.speed as plan_speed, p.data_limit, b.name as branch_name,
    COALESCE(du.used_quota, 0) as used_quota
    FROM customers u 
    LEFT JOIN plans p ON u.plan_id = p.id 
    LEFT JOIN branches b ON u.branch_id = b.id 
    LEFT JOIN data_usage du ON u.username = du.username
    WHERE u.username='$username'
")->fetch_assoc();

if (!$user) die("User not found");

/* ============================
   POST ACTIONS (TAB HANDLERS)
============================ */
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_grace') {
        $days = (int)$_POST['grace_days'];
        if ($days > 0) {
            $conn->query("UPDATE customers SET expiry = DATE_ADD(expiry, INTERVAL $days DAY), status='active' WHERE username='$username'");
            $success_msg = "Grace period of $days days added successfully.";
            // Reload user data
            $user['expiry'] = date('Y-m-d', strtotime($user['expiry'] . " + $days days"));
        }
    } elseif ($action === 'lock_pppoe') {
        $mac = '';
        $session_res = $conn->query("SELECT callingstationid FROM radacct WHERE username='$username' AND acctstoptime IS NULL LIMIT 1");
        if ($session_res->num_rows > 0) {
            $mac = $session_res->fetch_assoc()['callingstationid'];
        } else {
            $history_res = $conn->query("SELECT callingstationid FROM radacct WHERE username='$username' AND callingstationid != '' ORDER BY acctstarttime DESC LIMIT 1");
            if ($history_res && $history_res->num_rows > 0) $mac = $history_res->fetch_assoc()['callingstationid'];
        }
        if ($mac) {
            $conn->query("DELETE FROM radcheck WHERE username='$username' AND attribute='Calling-Station-Id'");
            $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Calling-Station-Id', '==', '$mac')");
            $success_msg = "Locked to MAC: $mac";
        }
    } elseif ($action === 'unlock_pppoe') {
        $conn->query("DELETE FROM radcheck WHERE username='$username' AND attribute='Calling-Station-Id'");
        $success_msg = "MAC Lock Removed";
    } elseif ($action === 'reset_fup') {
        // 1. Set fup_reset flag to reset usage tracking
        $conn->query("UPDATE data_usage SET used_quota = 0, fup_reset = 1, updated_at = NOW() WHERE username = '$username'");
        
        // 2. Get base plan speed
        $plan = $conn->query("SELECT p.speed FROM plans p JOIN customers c ON c.plan_id = p.id WHERE c.username = '$username'")->fetch_assoc();
        $plan_speed = $plan['speed'] ?? '10M/10M';
        
        // 3. Reset Speed in radreply to base plan speed
        $conn->query("DELETE FROM radreply WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'");
        $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Mikrotik-Rate-Limit', ':=', '$plan_speed')");
        
        $success_msg = "FUP reset! Usage cleared. Speed restored to $plan_speed.";
        $user['used_quota'] = 0;
    } elseif ($action === 'disconnect') {
        $nas = $conn->query("SELECT * FROM nas WHERE status=1 LIMIT 1")->fetch_assoc();
        if ($nas) {
            $nas_ip = $nas['ip_address'];
            $nas_secret = $nas['secret'];
            shell_exec("echo 'User-Name = $username' | /usr/bin/radclient -x $nas_ip:3799 disconnect $nas_secret 2>&1");
            $success_msg = "Disconnect command sent to NAS.";
        }
    } elseif (isset($_POST['reboot'])) {
        $deviceId = $_POST['deviceId'] ?? '';
        if ($deviceId) {
            genieacs_request("/devices/$deviceId/tasks", "POST", ["name" => "reboot"]);
            $success_msg = "Reboot command sent to device.";
        }
    } elseif (isset($_POST['setwifi'])) {
        $deviceId = $_POST['deviceId'] ?? '';
        $ssid = $_POST['ssid'] ?? '';
        $pass = $_POST['wifi_pass'] ?? '';
        if ($deviceId && $ssid && $pass) {
            genieacs_request("/devices/$deviceId/tasks", "POST", [
                "name" => "setParameterValues",
                "parameterValues" => [
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", $ssid, "xsd:string"],
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase", $pass, "xsd:string"]
                ]
            ]);
            $conn->query("UPDATE customers SET wifi_ssid='$ssid', wifi_password='$pass' WHERE username='$username'");
            $success_msg = "WiFi updated and synced.";
        }
    }
}

/* ============================
   TR-069 LIVE DATA
============================ */
$device = null;
$genieacsDevice = null;
$target_serial = strtoupper(trim($user['onu_serial'] ?? ''));

if (!empty($target_serial)) {
    $deviceId = $user['tr069_device_id'];
    if (!$deviceId) {
        $devices = genieacs_request("/devices?_limit=1&query=".urlencode(json_encode(["_deviceId._SerialNumber" => $target_serial])), "GET");
        if (is_array($devices) && count($devices) > 0) {
            $device = $devices[0];
            $deviceId = $device['_id'];
            $conn->query("UPDATE customers SET tr069_device_id='$deviceId' WHERE username='$username'");
        }
    }
    
    if ($deviceId) {
        $projection = urlencode(json_encode([
            "InternetGatewayDevice.DeviceInfo.SoftwareVersion",
            "InternetGatewayDevice.DeviceInfo.Manufacturer",
            "InternetGatewayDevice.DeviceInfo.ProductClass",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase"
        ]));
        $genieacsDevice = genieacs_request("/devices/$deviceId?projection=$projection", "GET");
    }
}

/* ============================
   CURRENT SESSION
============================ */
$session = $conn->query("
    SELECT *, TIMESTAMPDIFF(SECOND, acctstarttime, NOW()) AS duration 
    FROM radacct 
    WHERE username='$username' AND acctstoptime IS NULL 
    ORDER BY acctstarttime DESC LIMIT 1
")->fetch_assoc();

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$page_title = "Profile: " . $user['username'];
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<script>
function showTab(tabId, btn) {
    var contents = document.querySelectorAll('.tab-content');
    contents.forEach(function(c) {
        c.classList.remove('active');
        c.style.display = 'none';
    });
    var tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    var target = document.getElementById(tabId);
    if(target) {
        target.classList.add('active');
        target.style.display = 'block';
    }
    if(btn) btn.classList.add('active');
    if(tabId === 'livegraph' && typeof initLiveChart === 'function') initLiveChart();
    if(tabId === 'optical_power' && typeof initPowerChart === 'function') initPowerChart();
    if(tabId === 'overview' && typeof userMapObj !== 'undefined' && userMapObj) {
        setTimeout(function(){ userMapObj.invalidateSize(); }, 200);
    }
}
</script>

<style>
    .profile-container { padding: 25px; }
    .profile-header { background: #fff; border-radius: 15px; padding: 30px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
    .profile-id { display: flex; align-items: center; gap: 20px; }
    .profile-avatar { width: 70px; height: 70px; background: #3b82f6; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; }
    .profile-name h1 { margin: 0; font-size: 24px; color: #1e293b; }
    
    /* Modern Tabs */
    .nav-tabs { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0; overflow-x: auto; }
    .nav-tab { padding: 12px 20px; border-radius: 10px 10px 0 0; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border: none; background: none; transition: all 0.2s; white-space: nowrap; border-bottom: 2px solid transparent; margin-bottom: -2px; }
    .nav-tab:hover { color: #3b82f6; background: #f8fafc; }
    .nav-tab.active { color: #3b82f6; border-bottom: 2px solid #3b82f6; background: #eff6ff; }
    
    .tab-content { display: none; animation: fadeIn 0.3s ease; }
    .tab-content.active { display: block !important; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
    .info-card { background: #fff; border-radius: 15px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; height: 100%; }
    .info-card h3 { margin: 0 0 20px; font-size: 16px; color: #1e293b; display: flex; align-items: center; gap: 10px; }
    
    .detail-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
    .detail-row label { color: #64748b; font-weight: 500; }
    .detail-row span { color: #1e293b; font-weight: 600; }
    
    .session-card { background: #1e293b; color: #fff; border-radius: 15px; padding: 25px; }
    .btn-action { padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.2s; border: none; cursor: pointer; text-decoration: none; }
    .btn-primary { background: #3b82f6; color: #fff; }
    .btn-danger { background: #fee2e2; color: #ef4444; }
    
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 12px 15px; background: #f8fafc; color: #64748b; font-size: 12px; text-transform: uppercase; }
    td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    
    /* Map Styles */
    #userMap { height: 200px; width: 100%; border-radius: 10px; margin-top: 15px; z-index: 1; border: 1px solid #e2e8f0; }
    
    /* FUP Styles */
    .usage-bar-bg { background: #e2e8f0; height: 10px; border-radius: 5px; margin: 10px 0; overflow: hidden; }
    .usage-bar-fill { height: 100%; border-radius: 5px; transition: width 0.3s; }
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="profile-container">
    
    <?php if($success_msg): ?>
        <div style="background: #dcfce7; color: #16a34a; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0;"><i class="fa fa-check-circle"></i> <?= $success_msg ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="profile-header">
        <div class="profile-id">
            <div class="profile-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            <div class="profile-name">
                <h1 style="display:flex; align-items:center; gap:10px;">
                    <?= htmlspecialchars($user['full_name']) ?>
                    <a href="map.php?user=<?= $username ?>" title="View on Map" style="font-size:18px; color:#3b82f6;"><i class="fa fa-map-location-dot"></i></a>
                </h1>
                <p>@<?= htmlspecialchars($user['username']) ?> &bull; <?= htmlspecialchars($user['phone']) ?></p>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="recharge.php?user=<?= urlencode($user['username']) ?>" class="btn-action btn-primary"><i class="fa fa-bolt"></i> Renew Account</a>
            <form method="POST" onsubmit="return confirm('Disconnect session?')">
                <input type="hidden" name="action" value="disconnect">
                <button type="submit" class="btn-action btn-danger"><i class="fa fa-power-off"></i> Disconnect</button>
            </form>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="nav-tabs">
        <button class="nav-tab active" onclick="showTab('overview', this)"><i class="fa fa-th-large"></i> Overview</button>
        <button class="nav-tab" onclick="showTab('usage_history', this)"><i class="fa fa-chart-area"></i> Usage History</button>
        <button class="nav-tab" onclick="window.open('map.php?user=<?= $username ?>', '_blank')"><i class="fa fa-map-location-dot"></i> Map View</button>
        <button class="nav-tab" onclick="showTab('livegraph', this)"><i class="fa fa-chart-line"></i> Live Graph</button>
        <button class="nav-tab" onclick="showTab('tickets', this)"><i class="fa fa-headset"></i> Tickets</button>
        <button class="nav-tab" onclick="showTab('invoices', this)"><i class="fa fa-file-invoice"></i> Invoices</button>
        <button class="nav-tab" onclick="showTab('grace', this)"><i class="fa fa-gift"></i> Add Grace</button>
        <button class="nav-tab" onclick="showTab('optical_power', this)"><i class="fa fa-signal"></i> Optical Power</button>
        <button class="nav-tab" onclick="showTab('acspush', this)"><i class="fa fa-microchip"></i> ACS Push</button>
        <button class="nav-tab" onclick="showTab('authlog', this)"><i class="fa fa-history"></i> Auth Log</button>
    </div>

    <!-- TAB: Overview -->
    <div id="overview" class="tab-content active">
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fa fa-info-circle"></i> Basic Info</h3>
                <div class="detail-row"><label>Username</label><span><?= $user['username'] ?></span></div>
                <div class="detail-row"><label>Status</label><span style="color:<?= $user['status']=='active'?'#10b981':'#ef4444' ?>;"><?= ucfirst($user['status']) ?></span></div>
                <div class="detail-row"><label>Plan</label><span><?= htmlspecialchars($user['plan_name']) ?></span></div>
                <div class="detail-row"><label>Expiry</label><span style="color: #ef4444;"><?= $user['expiry'] ?></span></div>
                <div class="detail-row"><label>Address</label><span><?= $user['address'] ?: '-' ?></span></div>
            </div>
            
            <div class="session-card">
                <h3><i class="fa fa-signal"></i> Active Session</h3>
                <?php if($session): ?>
                    <div style="font-size: 28px; font-weight: 700; margin-bottom: 10px;"><?= gmdate("H:i:s", $session['duration']) ?></div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px;">
                            <small style="opacity: 0.6;">User IP</small><br>
                            <b style="font-size: 16px;"><?= $session['framedipaddress'] ?? '-' ?></b>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px;">
                            <small style="opacity: 0.6;">User MAC</small><br>
                            <b style="font-size: 16px;"><?= $session['callingstationid'] ?? '-' ?></b>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                        <div><small style="opacity: 0.6;">Download</small><br><b><?= formatBytes($session['acctoutputoctets']) ?></b></div>
                        <div><small style="opacity: 0.6;">Upload</small><br><b><?= formatBytes($session['acctinputoctets']) ?></b></div>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; opacity: 0.5;">No active session.</p>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h3><i class="fa fa-shield-alt"></i> Account Security</h3>
                <?php $is_locked = $conn->query("SELECT * FROM radcheck WHERE username='$username' AND attribute='Calling-Station-Id' LIMIT 1")->num_rows > 0; ?>
                <p>MAC Lock Status: <b style="color:<?= $is_locked?'#ef4444':'#10b981' ?>;"><?= $is_locked?'LOCKED':'UNLOCKED' ?></b></p>
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="<?= $is_locked ? 'unlock_pppoe' : 'lock_pppoe' ?>">
                    <button type="submit" class="btn-action <?= $is_locked ? 'btn-danger' : 'btn-primary' ?>" style="width: 100%; justify-content: center;">
                        <?= $is_locked ? 'Unlock Account' : 'Lock to Current MAC' ?>
                    </button>
                </form>
            </div>

            <!-- FUP Usage Card -->
            <div class="info-card">
                <h3><i class="fa fa-chart-pie"></i> Monthly FUP Usage</h3>
                <?php 
                    $limit = (float)($user['data_limit'] ?? 0);
                    $monthly = $conn->query("SELECT SUM(acctoutputoctets+acctinputoctets) as total FROM radacct WHERE username='$username' AND MONTH(acctstarttime)=MONTH(NOW()) AND YEAR(acctstarttime)=YEAR(NOW())")->fetch_assoc();
                    $used = (float)($monthly['total'] ?? 0);
                    $percent = ($limit > 0) ? min(100, round(($used / $limit) * 100, 1)) : 0;
                    $color = ($percent > 90) ? '#ef4444' : (($percent > 70) ? '#f59e0b' : '#10b981');
                ?>
                <div class="detail-row">
                    <label>Data Limit</label>
                    <span><?= $limit > 0 ? formatBytes($limit) : 'Unlimited' ?></span>
                </div>
                <div class="detail-row">
                    <label>Used (This Month)</label>
                    <span><?= formatBytes($used) ?></span>
                </div>
                <div class="detail-row">
                    <label>Downloaded</label>
                    <span><?= formatBytes($conn->query("SELECT SUM(acctoutputoctets) as d FROM radacct WHERE username='$username' AND MONTH(acctstarttime)=MONTH(NOW()) AND YEAR(acctstarttime)=YEAR(NOW())")->fetch_assoc()['d'] ?? 0) ?></span>
                </div>
                <div class="detail-row">
                    <label>Uploaded</label>
                    <span><?= formatBytes($conn->query("SELECT SUM(acctinputoctets) as u FROM radacct WHERE username='$username' AND MONTH(acctstarttime)=MONTH(NOW()) AND YEAR(acctstarttime)=YEAR(NOW())")->fetch_assoc()['u'] ?? 0) ?></span>
                </div>
                <?php if($limit > 0): ?>
                <div class="usage-bar-bg">
                    <div class="usage-bar-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                    <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?= $percent ?>% used</span>
                    <form method="POST" onsubmit="return confirm('Reset FUP usage for this user?');">
                        <input type="hidden" name="action" value="reset_fup">
                        <button type="submit" class="btn-action" style="padding: 4px 10px; font-size: 11px; background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2;">
                            <i class="fa fa-rotate-left"></i> Reset FUP
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- FTTH Inventory Card -->
            <div class="info-card">
                <h3><i class="fa fa-network-wired"></i> FTTH & Inventory</h3>
                <div class="detail-row"><label>OLT Name/ID</label><span><?= htmlspecialchars($user['olt'] ?: '-') ?></span></div>
                <div class="detail-row"><label>OLT Port</label><span><?= $user['olt_port'] ?: '-' ?></span></div>
                <div class="detail-row"><label>Master Box</label><span><?= htmlspecialchars($user['master_box'] ?: '-') ?></span></div>
                <div class="detail-row"><label>DB Name/ID</label><span><?= htmlspecialchars($user['db_box'] ?: '-') ?></span></div>
                <div class="detail-row"><label>DB Port</label><span><?= $user['db_port'] ?: '-' ?></span></div>
            </div>

            <!-- Map Card -->
            <div class="info-card">
                <h3><i class="fa fa-map-location-dot"></i> Installation Location</h3>
                <?php if (!empty($user['lat']) && !empty($user['lng'])): ?>
                    <div id="userMap"></div>
                <?php else: ?>
                    <div style="height: 200px; background: #f8fafc; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #94a3b8; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <i class="fa fa-map-marked-alt" style="font-size: 30px;"></i>
                        <span>No coordinates set</span>
                        <a href="user_edit.php?user=<?= $username ?>" class="btn-action btn-primary" style="padding: 5px 12px; font-size: 11px;">Add Location</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB: Usage History -->
    <div id="usage_history" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-history"></i> Monthly Data Usage (Last 12 Months)</h3>
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Download</th>
                        <th>Upload</th>
                        <th>Total Usage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $usage_history = $conn->query("
                        SELECT 
                            DATE_FORMAT(acctstarttime, '%Y-%M') as month,
                            SUM(acctoutputoctets) as download,
                            SUM(acctinputoctets) as upload
                        FROM radacct 
                        WHERE username = '$username' 
                        GROUP BY month 
                        ORDER BY acctstarttime DESC 
                        LIMIT 12
                    ");
                    if($usage_history->num_rows > 0):
                        while($uh = $usage_history->fetch_assoc()): ?>
                            <tr>
                                <td><b><?= $uh['month'] ?></b></td>
                                <td><?= formatBytes($uh['download']) ?></td>
                                <td><?= formatBytes($uh['upload']) ?></td>
                                <td><span class="badge" style="background:#eff6ff; color:#3b82f6;"><?= formatBytes($uh['download'] + $uh['upload']) ?></span></td>
                            </tr>
                        <?php endwhile; 
                    else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">No usage history found for this user.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Live Graph -->
    <div id="livegraph" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-chart-line"></i> Real-time Usage (60s)</h3>
            <canvas id="liveChart" height="100"></canvas>
        </div>
    </div>

    <!-- TAB: Tickets -->
    <div id="tickets" class="tab-content">
        <div class="info-card">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <h3><i class="fa fa-headset"></i> Support Tickets</h3>
                <a href="ticket_new.php?user=<?= $username ?>" class="btn-action btn-primary" style="padding: 5px 12px; font-size: 12px;"><i class="fa fa-plus"></i> New Ticket</a>
            </div>
            <table>
                <thead><tr><th>ID</th><th>Subject</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php 
                    $tks = $conn->query("SELECT * FROM tickets WHERE customer_id = (SELECT id FROM customers WHERE username='$username') ORDER BY id DESC");
                    while($tk = $tks->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $tk['id'] ?></td>
                            <td><a href="ticket_view.php?id=<?= $tk['id'] ?>"><?= htmlspecialchars($tk['subject']) ?></a></td>
                            <td><?= $tk['priority'] ?></td>
                            <td><?= $tk['status'] ?></td>
                            <td><?= date('M d, Y', strtotime($tk['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Invoices -->
    <div id="invoices" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-file-invoice"></i> Billing History</h3>
            <table>
                <thead><tr><th>ID</th><th>Amount</th><th>Months</th><th>Expiry</th><th>Date</th></tr></thead>
                <tbody>
                    <?php 
                    $invs = $conn->query("SELECT * FROM invoices WHERE username='$username' ORDER BY id DESC");
                    while($inv = $invs->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $inv['id'] ?></td>
                            <td>NPR <?= number_format($inv['amount'], 2) ?></td>
                            <td><?= $inv['months'] ?></td>
                            <td><?= $inv['expiry_date'] ?></td>
                            <td><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Grace Period -->
    <div id="grace" class="tab-content">
        <div class="info-card" style="max-width: 500px; margin: 0 auto;">
            <h3><i class="fa fa-gift"></i> Add Grace Period</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_grace">
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:8px; font-size:13px; font-weight:600; color:#64748b;">Days to Extend</label>
                    <select name="grace_days" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                        <option value="1">1 Day</option>
                        <option value="2">2 Days</option>
                        <option value="3" selected>3 Days</option>
                        <option value="7">7 Days</option>
                    </select>
                </div>
                <button type="submit" class="btn-action btn-primary" style="width:100%; justify-content:center; padding:15px;">Extend Subscription Now</button>
            </form>
        </div>
    </div>

    <!-- TAB: Optical Power -->
    <div id="optical_power" class="tab-content" style="display:none;">
        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:20px;">
            <div class="info-card">
                <h3><i class="fa fa-tachometer-alt"></i> Current Status</h3>
                <div id="powerDisplay" style="text-align:center; padding:20px 0;">
                    <div style="font-size:32px; font-weight:700; color:#1e293b;" id="currentRx">-- dBm</div>
                    <div style="font-size:12px; color:#64748b; margin-top:5px;">Optical RX Power</div>
                    <div id="powerBadge" style="display:inline-block; margin-top:15px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; background:#f1f5f9; color:#64748b;">WAITING</div>
                </div>
                <hr style="margin:20px 0; border:0; border-top:1px solid #f1f5f9;">
                <div class="detail-row"><label>TX Power</label><span id="currentTx">-- dBm</span></div>
                <div class="detail-row"><label>Last Update</label><span id="lastPowerUpdate">Never</span></div>
                <button onclick="refreshPower()" id="refreshBtn" class="btn-action btn-primary" style="width:100%; justify-content:center; margin-top:20px;">
                    <i class="fa fa-sync"></i> Refresh Power
                </button>
            </div>
            <div class="info-card">
                <h3><i class="fa fa-chart-line"></i> Power History (Last 20)</h3>
                <div style="height: 300px; width:100%;">
                    <canvas id="powerChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: ACS Push -->
    <div id="acspush" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-microchip"></i> TR-069 Operations</h3>
            <?php if($genieacsDevice): ?>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                    <div>
                        <div class="detail-row"><label>Manufacturer</label><span><?= $genieacsDevice['InternetGatewayDevice']['DeviceInfo']['Manufacturer']['_value'] ?? 'N/A' ?></span></div>
                        <div class="detail-row"><label>Model</label><span><?= $genieacsDevice['InternetGatewayDevice']['DeviceInfo']['ProductClass']['_value'] ?? 'N/A' ?></span></div>
                        <button type="submit" name="reboot" class="btn-action btn-danger" style="margin-top:20px;"><i class="fa fa-power-off"></i> Reboot ONU</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="deviceId" value="<?= $deviceId ?>">
                        <div style="margin-bottom:10px;"><label style="font-size:12px; font-weight:600;">WiFi Name (SSID)</label><input type="text" name="ssid" value="<?= htmlspecialchars($user['wifi_ssid']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></div>
                        <div style="margin-bottom:15px;"><label style="font-size:12px; font-weight:600;">WiFi Password</label><input type="text" name="wifi_pass" value="<?= htmlspecialchars($user['wifi_password']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></div>
                        <button type="submit" name="setwifi" class="btn-action btn-primary" style="width:100%; justify-content:center;">Push to ONU</button>
                    </form>
                </div>
            <?php else: ?>
                <p style="text-align:center; color:#94a3b8; padding:40px;">No ONU device connected to ACS for this user.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Auth Log -->
    <div id="authlog" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-history"></i> Recent Authentication Attempts</h3>
            <table>
                <thead><tr><th>#</th><th>Date</th><th>Status</th><th>Reason</th></tr></thead>
                <tbody>
                    <?php 
                    $logs = $conn->query("SELECT * FROM radpostauth WHERE username='$username' ORDER BY authdate DESC LIMIT 20");
                    $i=1; while($log = $logs->fetch_assoc()): 
                        $success = ($log['reply'] === 'Access-Accept');
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($log['authdate'])) ?></td>
                            <td><span class="badge <?= $success?'bg-success':'bg-danger' ?>"><?= $success?'Success':'Failed' ?></span></td>
                            <td><?= htmlspecialchars($log['reply']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var userMapObj;
var liveChart = null;

// Initialize Map
document.addEventListener("DOMContentLoaded", function() {
    <?php if (!empty($user['lat']) && !empty($user['lng'])): ?>
        var lat = <?= (float)$user['lat'] ?>;
        var lng = <?= (float)$user['lng'] ?>;
        var mapContainer = document.getElementById('userMap');
        if (mapContainer) {
            userMapObj = L.map('userMap').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(userMapObj);
            L.marker([lat, lng]).addTo(userMapObj).bindPopup("<b>Installation Location</b>").openPopup();
        }
    <?php endif; ?>
});

// Live Chart logic
function initLiveChart() {
    if(liveChart) return;
    const ctx = document.getElementById('liveChart').getContext('2d');
    liveChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'Download (Mbps)', borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', data: [], fill: true, tension: 0.4 },
                { label: 'Upload (Mbps)', borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', data: [], fill: true, tension: 0.4 }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true }, x: { display: false } },
            animation: false
        }
    });

    setInterval(function() {
        var liveTab = document.getElementById('livegraph');
        console.log('Live tab active:', liveTab && liveTab.classList.contains('active'));
        if(liveTab && liveTab.classList.contains('active')) {
            console.log('Fetching data for: <?= urlencode($username) ?>');
            fetch('user_live_graph_data.php?user=<?= urlencode($username) ?>')
                .then(r => r.json())
                .then(res => {
                    console.log('Data received:', res);
                    const now = new Date().toLocaleTimeString();
                    liveChart.data.labels.push(now);
                    liveChart.data.datasets[0].data.push(res.download_mbps);
                    liveChart.data.datasets[1].data.push(res.upload_mbps);
                    if(liveChart.data.labels.length > 20) {
                        liveChart.data.labels.shift();
                        liveChart.data.datasets[0].data.shift();
                        liveChart.data.datasets[1].data.shift();
                    }
                    liveChart.update();
                })
                .catch(err => console.error('Fetch error:', err));
        }
    }, 3000);
}

var powerChart = null;
function initPowerChart() {
    if(powerChart) return;
    const ctx = document.getElementById('powerChart').getContext('2d');
    powerChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'RX Power (dBm)',
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                data: [],
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { min: -35, max: -10, title: { display: true, text: 'dBm' } }
            }
        }
    });
    loadPowerHistory();
}

function loadPowerHistory() {
    fetch('onu_power_api.php?action=get_history&username=<?= urlencode($username) ?>')
    .then(r => r.json())
    .then(data => {
        if(data && data.length > 0) {
            powerChart.data.labels = data.map(i => new Date(i.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
            powerChart.data.datasets[0].data = data.map(i => i.rx_power);
            powerChart.update();
            let last = data[data.length-1];
            updatePowerUI(last.rx_power, last.tx_power, last.timestamp);
        }
    });
}

function refreshPower() {
    let btn = document.getElementById('refreshBtn');
    btn.innerHTML = '<i class="fa fa-sync fa-spin"></i> Reading OLT...'; btn.disabled = true;
    fetch('onu_power_api.php?action=refresh&username=<?= urlencode($username) ?>')
    .then(r => r.json()).then(res => {
        if(res.status === 'success') loadPowerHistory();
        else alert("Error: " + res.message);
        btn.innerHTML = '<i class="fa fa-sync"></i> Refresh Power'; btn.disabled = false;
    }).catch(() => { btn.innerHTML = '<i class="fa fa-sync"></i> Refresh Power'; btn.disabled = false; });
}

function updatePowerUI(rx, tx, time) {
    document.getElementById('currentRx').innerText = rx + ' dBm';
    document.getElementById('currentTx').innerText = (tx?tx:'--') + ' dBm';
    document.getElementById('lastPowerUpdate').innerText = new Date(time).toLocaleString();
    let badge = document.getElementById('powerBadge');
    if(rx < -27) { badge.innerText = 'CRITICAL'; badge.style.background = '#fef2f2'; badge.style.color = '#ef4444'; }
    else if(rx < -24) { badge.innerText = 'WARNING'; badge.style.background = '#fff7ed'; badge.style.color = '#f59e0b'; }
    else { badge.innerText = 'GOOD'; badge.style.background = '#ecfdf5'; badge.style.color = '#10b981'; }
}
</script>

<?php include 'includes/footer.php'; ?>
