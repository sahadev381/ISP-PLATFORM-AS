<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/mikrotik_api.php';

$page_title = "MikroTik SDN Controller";
$active = "nas";

$mikrotiks = $conn->query("SELECT * FROM nas WHERE device_type = 'mikrotik'");

$nas_id = $_GET['id'] ?? null;
$selected_nas = null;
$api_error = "";
$resources = [];
$interfaces = [];
$secrets = [];
$active_ppp = 0;
$gpon_status = [];
$olt_devices = [];

if ($nas_id) {
    $selected_nas = $conn->query("SELECT * FROM nas WHERE id = $nas_id")->fetch_assoc();
    if ($selected_nas) {
        $api = new RouterosAPI();
        $api->debug = false;
        $api->setPort($selected_nas['api_port'] ?: 8728);
        
        if (@$api->connect($selected_nas['ip_address'], $selected_nas['api_user'], $selected_nas['api_pass'])) {
            $resources = $api->comm("/system/resource/print")[0] ?? [];
            $interfaces = $api->comm("/interface/print");
            $secrets = $api->comm("/ppp/secret/print", ["count-only" => ""]);
            $active_ppp = $api->comm("/ppp/active/print", ["count-only" => ""]);
            
            // Get GPON/SFP interfaces (connects to OLT)
            $gpon_status = $api->getGPONStatus();
            
            // Get PPPoE sessions
            $pppoe_sessions = $api->getPPPoESessions();
            
            // Get Hotspot users
            $hotspot_active = $api->comm("/ip/hotspot/active/print", ["count-only" => ""]);
            
            $api->disconnect();
        } else {
            $api_error = "Cannot connect to " . $selected_nas['ip_address'] . ". Check if MikroTik is reachable and API port (8728) is enabled.";
        }
        
        // Get all OLTs
        $all_olts = $conn->query("SELECT * FROM nas WHERE device_type = 'olt'");
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 20px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-microchip"></i> MikroTik SDN Controller</h2>
        
        <form method="GET" style="display:flex; gap:10px;">
            <select name="id" class="form-control" onchange="this.form.submit()" style="padding:10px; border-radius:8px; min-width:250px;">
                <option value="">-- Select MikroTik Router --</option>
                <?php 
                $mikrotiks = $conn->query("SELECT * FROM nas WHERE device_type = 'mikrotik'");
                while($m = $mikrotiks->fetch_assoc()): 
                ?>
                    <option value="<?= $m['id'] ?>" <?= $nas_id == $m['id'] ? 'selected' : '' ?>><?= $m['nasname'] ?> (<?= $m['ip_address'] ?>)</option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>

    <?php if ($api_error): ?>
        <div style="background:#fee2e2; color:#ef4444; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #fecaca;">
            <i class="fa fa-circle-exclamation"></i> <?= $api_error ?>
        </div>
    <?php endif; ?>

    <?php if ($selected_nas && !$api_error): ?>
        
        <!-- Quick Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 5px solid #3b82f6; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;">CPU Load</div>
                <div style="font-size: 28px; font-weight: 800; color: #1e293b;"><?= $resources['cpu-load'] ?? '0' ?>%</div>
                <small style="color:#94a3b8;"><?= $resources['cpu'] ?? '' ?> (<?= $resources['cpu-count'] ?? '1' ?> Core)</small>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 5px solid #10b981; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;">Free Memory</div>
                <div style="font-size: 28px; font-weight: 800; color: #1e293b;"><?= round(($resources['free-memory'] ?? 0) / 1048576, 1) ?> MB</div>
                <small style="color:#94a3b8;">Total: <?= round(($resources['total-memory'] ?? 0) / 1048576, 1) ?> MB</small>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 5px solid #8b5cf6; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;">PPPoE Sessions</div>
                <div style="font-size: 28px; font-weight: 800; color: #1e293b;"><?= $active_ppp ?? '0' ?></div>
                <small style="color:#94a3b8;">Active Connections</small>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 5px solid #f59e0b; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;">Hotspot Users</div>
                <div style="font-size: 28px; font-weight: 800; color: #1e293b;"><?= $hotspot_active ?? '0' ?></div>
                <small style="color:#94a3b8;">Currently Online</small>
            </div>
        </div>

        <!-- GPON/SFP Interfaces (Connected to OLT) -->
        <?php if(!empty($gpon_status)): ?>
        <div style="background:#fff; border-radius:15px; padding:25px; margin-bottom:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0; color:#1e293b;"><i class="fa fa-network-wired" style="color:#3b82f6;"></i> Fiber Interfaces (Connected to OLT)</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top:20px;">
                <?php foreach($gpon_status as $gpon): ?>
                <?php $is_up = ($gpon['status'] ?? '') == 'true'; ?>
                <div style="padding:15px; border-radius:12px; border:2px solid <?= $is_up ? '#10b981' : '#e2e8f0' ?>; background:<?= $is_up ? '#10b98110' : '#f8fafc' ?>;">
                    <div style="font-weight:700; color:<?= $is_up ? '#10b981' : '#94a3b8' ?>;">
                        <i class="fa fa-<?= $is_up ? 'link' : 'unlink' ?>"></i> <?= $gpon['name'] ?>
                    </div>
                    <div style="font-size:11px; color:#64748b; margin-top:8px;">
                        <span class="badge <?= $is_up ? 'active' : 'inactive' ?>"><?= $is_up ? 'UP' : 'DOWN' ?></span>
                    </div>
                    <div style="font-size:11px; color:#64748b; margin-top:8px;">
                        <div><i class="fa fa-arrow-down"></i> RX: <?= round(($gpon['rx_bytes'] ?? 0)/1024/1024, 2) ?> MB</div>
                        <div><i class="fa fa-arrow-up"></i> TX: <?= round(($gpon['tx_bytes'] ?? 0)/1024/1024, 2) ?> MB</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Connected OLTs -->
        <div style="background:#fff; border-radius:15px; padding:25px; margin-bottom:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0; color:#1e293b;"><i class="fa fa-server" style="color:#ef4444;"></i> Connected OLTs</h3>
            <p style="color:#64748b; font-size:13px;">OLT devices accessible through this MikroTik router</p>
            
            <?php 
            $olts = $conn->query("SELECT * FROM nas WHERE device_type = 'olt'");
            if($olts->num_rows > 0):
            ?>
            <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                <thead>
                    <tr style="background:#f8fafc; text-align:left;">
                        <th style="padding:12px;">OLT Name</th>
                        <th style="padding:12px;">IP Address</th>
                        <th style="padding:12px;">Type</th>
                        <th style="padding:12px;">Status</th>
                        <th style="padding:12px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($olt = $olts->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px; font-weight:600;"><?= htmlspecialchars($olt['nasname']) ?></td>
                        <td style="padding:12px; font-family:monospace; color:#3b82f6;"><?= $olt['ip_address'] ?></td>
                        <td style="padding:12px;"><span style="padding:4px 10px; background:#f1f5f9; border-radius:6px; font-size:12px;"><?= $olt['brand'] ?? 'Generic' ?></span></td>
                        <td style="padding:12px;"><span class="badge active">Online</span></td>
                        <td style="padding:12px;">
                            <a href="olt_dashboard.php?id=<?= $olt['id'] ?>" class="btn btn-sm" style="background:#3b82f6; color:#fff; padding:6px 12px; border-radius:6px; text-decoration:none;">
                                <i class="fa fa-eye"></i> Manage ONT
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align:center; padding:30px; color:#94a3b8;">
                <i class="fa fa-server" style="font-size:30px; margin-bottom:10px; display:block; opacity:0.5;"></i>
                No OLT devices configured. <a href="nas.php?type=olt">Add OLT</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Interface Monitor -->
        <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
            <h4 style="margin-top:0;"><i class="fa fa-network-wired"></i> Real-time Interface Traffic</h4>
            <table style="width: 100%; border-collapse: collapse; margin-top:20px;">
                <thead>
                    <tr style="text-align: left; background: #f8fafc;">
                        <th style="padding: 15px;">Interface</th>
                        <th style="padding: 15px;">Type</th>
                        <th style="padding: 15px;">Rx</th>
                        <th style="padding: 15px;">Tx</th>
                        <th style="padding: 15px;">Status</th>
                        <th style="padding: 15px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($interfaces as $iface): ?>
                    <?php $is_running = ($iface['running'] ?? '') == 'true'; ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px;"><b><?= $iface['name'] ?></b></td>
                        <td style="padding: 15px; font-size:12px; color:#64748b;"><?= $iface['type'] ?></td>
                        <td style="padding: 15px; color:#10b981; font-weight:600;"><i class="fa fa-arrow-down"></i> <?= formatBytes($iface['rx-byte'] ?? 0) ?></td>
                        <td style="padding: 15px; color:#3b82f6; font-weight:600;"><i class="fa fa-arrow-up"></i> <?= formatBytes($iface['tx-byte'] ?? 0) ?></td>
                        <td style="padding: 15px;">
                            <span class="badge <?= $is_running ? 'active' : 'inactive' ?>">
                                <?= $is_running ? 'Running' : 'Down' ?>
                            </span>
                        </td>
                        <td style="padding: 15px; text-align:right;">
                            <button class="btn btn-sm btn-primary" onclick="openMonitor('<?= $iface['name'] ?>')"><i class="fa fa-chart-line"></i> Monitor</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif (!$nas_id): ?>
        <div style="text-align:center; padding:100px; background:#fff; border-radius:15px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
            <i class="fa fa-microchip" style="font-size:60px; color:#e2e8f0; margin-bottom:20px;"></i>
            <h3>No MikroTik Selected</h3>
            <p style="color:#64748b;">Please select a MikroTik Router from the dropdown to manage resources, interfaces, and connected OLTs.</p>
        </div>
    <?php endif; ?>

</div>

<!-- Traffic Monitor Modal -->
<div id="monitorModal" class="ftth-modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(5px);">
    <div class="ftth-modal-content" style="background:#fff; margin:5% auto; padding:30px; border-radius:20px; width:95%; max-width:800px; box-shadow:0 25px 60px rgba(0,0,0,0.4);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;"><i class="fa fa-chart-line"></i> Real-time Traffic: <span id="monitorIfaceName" style="color:#3b82f6;"></span></h3>
            <span onclick="closeMonitor()" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div style="height:400px; width:100%;">
            <canvas id="trafficChart"></canvas>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:15px; border-top:1px solid #f1f5f9;">
            <div style="color:#10b981; font-weight:700;">RX: <span id="curRx">0.00</span> Mbps</div>
            <div style="color:#3b82f6; font-weight:700;">TX: <span id="curTx">0.00</span> Mbps</div>
        </div>
    </div>
</div>

<?php
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, $k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let trafficChart = null;
let monitorInterval = null;
let currentIface = '';

function openMonitor(iface) {
    currentIface = iface;
    document.getElementById('monitorIfaceName').innerText = iface;
    document.getElementById('monitorModal').style.display = 'block';
    
    initTrafficChart();
    
    if(monitorInterval) clearInterval(monitorInterval);
    monitorInterval = setInterval(fetchTraffic, 2000);
}

function closeMonitor() {
    document.getElementById('monitorModal').style.display = 'none';
    if(monitorInterval) clearInterval(monitorInterval);
}

function initTrafficChart() {
    if(trafficChart) trafficChart.destroy();
    const ctx = document.getElementById('trafficChart').getContext('2d');
    trafficChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'RX (Mbps)', borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', data: [], fill: true, tension: 0.4 },
                { label: 'TX (Mbps)', borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', data: [], fill: true, tension: 0.4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Mbps' } } }
        }
    });
}

function fetchTraffic() {
    fetch(`mikrotik_traffic_api.php?nas_id=<?= $nas_id ?>&interface=${currentIface}`)
    .then(r => r.json())
    .then(data => {
        if(data.error) return;
        
        const now = data.timestamp;
        trafficChart.data.labels.push(now);
        trafficChart.data.datasets[0].data.push(data.rx);
        trafficChart.data.datasets[1].data.push(data.tx);
        
        if(trafficChart.data.labels.length > 30) {
            trafficChart.data.labels.shift();
            trafficChart.data.datasets[0].data.shift();
            trafficChart.data.datasets[1].data.shift();
        }
        
        trafficChart.update();
        document.getElementById('curRx').innerText = data.rx;
        document.getElementById('curTx').innerText = data.tx;
    });
}
</script>

<?php include 'includes/footer.php'; ?>
