<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Network Device Monitoring";
$active = "nas";

$devices = $conn->query("SELECT * FROM nas ORDER BY device_type, nasname");
$olt_count = $mikrotik_count = $switch_count = 0;
$olt_online = $mikrotik_online = $switch_online = 0;

$device_list = [];
while($d = $devices->fetch_assoc()) {
    $device_list[] = $d;
    if($d['device_type'] == 'olt') { $olt_count++; }
    if($d['device_type'] == 'mikrotik') { $mikrotik_count++; }
    if($d['device_type'] == 'switch') { $switch_count++; }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 25px;">
    
    <div style="margin-bottom: 30px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-server"></i> Network Device Monitoring</h2>
        <p style="color:#64748b;">Unified view of all network infrastructure - OLTs, MikroTik Routers, and Switches</p>
    </div>

    <!-- Device Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <!-- OLT Card -->
        <div style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 25px; border-radius: 16px; color: #fff; position: relative; overflow: hidden;">
            <div style="position: absolute; right: -20px; top: -20px; opacity: 0.2; font-size: 80px;"><i class="fa fa-server"></i></div>
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">OLT DEVICES</div>
            <div style="font-size: 36px; font-weight: 800; margin: 10px 0;"><?= $olt_count ?></div>
            <div style="display: flex; gap: 20px; font-size: 13px; opacity: 0.85;">
                <span><i class="fa fa-check-circle"></i> Online: <?= $olt_online ?></span>
                <span><i class="fa fa-times-circle"></i> Offline: <?= $olt_count - $olt_online ?></span>
            </div>
            <a href="olt_dashboard.php" style="display: inline-block; margin-top: 15px; color: #fff; font-size: 13px; font-weight: 600;">
                Manage OLTs <i class="fa fa-arrow-right"></i>
            </a>
        </div>

        <!-- MikroTik Card -->
        <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 25px; border-radius: 16px; color: #fff; position: relative; overflow: hidden;">
            <div style="position: absolute; right: -20px; top: -20px; opacity: 0.2; font-size: 80px;"><i class="fa fa-microchip"></i></div>
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">MIKROTIK ROUTERS</div>
            <div style="font-size: 36px; font-weight: 800; margin: 10px 0;"><?= $mikrotik_count ?></div>
            <div style="display: flex; gap: 20px; font-size: 13px; opacity: 0.85;">
                <span><i class="fa fa-check-circle"></i> Online: <?= $mikrotik_online ?></span>
                <span><i class="fa fa-times-circle"></i> Offline: <?= $mikrotik_count - $mikrotik_online ?></span>
            </div>
            <a href="mikrotik_dashboard.php" style="display: inline-block; margin-top: 15px; color: #fff; font-size: 13px; font-weight: 600;">
                Manage MikroTik <i class="fa fa-arrow-right"></i>
            </a>
        </div>

        <!-- Switch Card -->
        <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 25px; border-radius: 16px; color: #fff; position: relative; overflow: hidden;">
            <div style="position: absolute; right: -20px; top: -20px; opacity: 0.2; font-size: 80px;"><i class="fa fa-network-wired"></i></div>
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">SWITCH DEVICES</div>
            <div style="font-size: 36px; font-weight: 800; margin: 10px 0;"><?= $switch_count ?></div>
            <div style="display: flex; gap: 20px; font-size: 13px; opacity: 0.85;">
                <span><i class="fa fa-check-circle"></i> Online: <?= $switch_online ?></span>
                <span><i class="fa fa-times-circle"></i> Offline: <?= $switch_count - $switch_online ?></span>
            </div>
            <a href="switch_dashboard.php" style="display: inline-block; margin-top: 15px; color: #fff; font-size: 13px; font-weight: 600;">
                Manage Switches <i class="fa fa-arrow-right"></i>
            </a>
        </div>

        <!-- Total Card -->
        <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 25px; border-radius: 16px; color: #fff; position: relative; overflow: hidden;">
            <div style="position: absolute; right: -20px; top: -20px; opacity: 0.2; font-size: 80px;"><i class="fa fa-network-wired"></i></div>
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">TOTAL DEVICES</div>
            <div style="font-size: 36px; font-weight: 800; margin: 10px 0;"><?= $olt_count + $mikrotik_count + $switch_count ?></div>
            <div style="font-size: 13px; opacity: 0.85;">
                <i class="fa fa-layer-group"></i> All Network Infrastructure
            </div>
        </div>

    </div>

    <!-- Device Filter Tabs -->
    <div style="background:#fff; border-radius:15px; padding:20px; margin-bottom:20px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="filter-btn active" data-filter="all" onclick="filterDevices('all')" style="padding:10px 20px; border:none; background:#3b82f6; color:#fff; border-radius:8px; cursor:pointer; font-weight:600;">
                <i class="fa fa-layer-group"></i> All Devices
            </button>
            <button class="filter-btn" data-filter="olt" onclick="filterDevices('olt')" style="padding:10px 20px; border:none; background:#f1f5f9; color:#64748b; border-radius:8px; cursor:pointer; font-weight:600;">
                <i class="fa fa-server"></i> OLTs
            </button>
            <button class="filter-btn" data-filter="mikrotik" onclick="filterDevices('mikrotik')" style="padding:10px 20px; border:none; background:#f1f5f9; color:#64748b; border-radius:8px; cursor:pointer; font-weight:600;">
                <i class="fa fa-microchip"></i> MikroTik
            </button>
            <button class="filter-btn" data-filter="switch" onclick="filterDevices('switch')" style="padding:10px 20px; border:none; background:#f1f5f9; color:#64748b; border-radius:8px; cursor:pointer; font-weight:600;">
                <i class="fa fa-network-wired"></i> Switches
            </button>
            <input type="text" id="searchDevice" onkeyup="searchDevices()" placeholder="Search devices..." style="padding:10px 15px; border:1px solid #e2e8f0; border-radius:8px; margin-left:auto; width:250px;">
        </div>
    </div>

    <!-- Devices Table -->
    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse;" id="devicesTable">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;"><i class="fa fa-cube"></i> Device</th>
                    <th style="padding: 15px;"><i class="fa fa-tag"></i> Type</th>
                    <th style="padding: 15px;"><i class="fa fa-network-wired"></i> IP Address</th>
                    <th style="padding: 15px;"><i class="fa fa-code"></i> Model</th>
                    <th style="padding: 15px;"><i class="fa fa-heartbeat"></i> Status</th>
                    <th style="padding: 15px;"><i class="fa fa-cog"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($device_list as $dev): ?>
                <tr class="device-row" data-type="<?= $dev['device_type'] ?>">
                    <td style="padding: 15px;">
                        <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($dev['nasname']) ?></div>
                        <small style="color:#94a3b8;"><?= htmlspecialchars($dev['shortname'] ?? '') ?></small>
                    </td>
                    <td style="padding: 15px;">
                        <?php 
                        $type_labels = [
                            'olt' => ['label' => 'OLT', 'color' => '#3b82f6'],
                            'mikrotik' => ['label' => 'MikroTik', 'color' => '#f59e0b'],
                            'switch' => ['label' => 'Switch', 'color' => '#10b981']
                        ];
                        $type_info = $type_labels[$dev['device_type']] ?? ['label' => 'Unknown', 'color' => '#64748b'];
                        ?>
                        <span style="padding: 5px 12px; border-radius: 20px; background: <?= $type_info['color'] ?>20; color: <?= $type_info['color'] ?>; font-weight: 600; font-size: 12px;">
                            <?= $type_info['label'] ?>
                        </span>
                    </td>
                    <td style="padding: 15px; font-family: monospace; color: #3b82f6;"><?= $dev['ip_address'] ?></td>
                    <td style="padding: 15px; color: #64748b;"><?= htmlspecialchars($dev['model'] ?? 'N/A') ?></td>
                    <td style="padding: 15px;">
                        <span class="badge active"><i class="fa fa-check-circle"></i> Online</span>
                    </td>
                    <td style="padding: 15px;">
                        <a href="<?= 
                            $dev['device_type'] == 'olt' ? 'olt_dashboard.php?id=' . $dev['id'] : 
                            ($dev['device_type'] == 'mikrotik' ? 'mikrotik_dashboard.php?id=' . $dev['id'] : 
                            'switch_dashboard.php?id=' . $dev['id'])
                        ?>" class="btn btn-sm" style="background:#3b82f6; color:#fff; padding:8px 15px; border-radius:8px; text-decoration:none; font-size:12px;">
                            <i class="fa fa-eye"></i> Manage
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($device_list)): ?>
                <tr>
                    <td colspan="6" style="padding: 40px; text-align: center; color: #94a3b8;">
                        <i class="fa fa-network-wired" style="font-size: 40px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                        No network devices found. <a href="nas.php">Add devices</a> to start monitoring.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<style>
.filter-btn.active {
    background: #3b82f6 !important;
    color: #fff !important;
}
.device-row {
    transition: all 0.2s;
}
.device-row:hover {
    background: #f8fafc;
}
</style>

<script>
function filterDevices(type) {
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-filter="${type}"]`).classList.add('active');
    
    document.querySelectorAll('.device-row').forEach(row => {
        if(type === 'all' || row.dataset.type === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchDevices() {
    const search = document.getElementById('searchDevice').value.toLowerCase();
    document.querySelectorAll('.device-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
