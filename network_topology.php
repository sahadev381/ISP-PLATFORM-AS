<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php';

// Create network_topology_links table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS network_topology_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_device_id INT NOT NULL,
    to_device_id INT NOT NULL,
    cable_type ENUM('fiber','copper','wifi') DEFAULT 'copper',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_link (from_device_id, to_device_id)
)");

$page_title = "Network Topology - NOC Monitor";
$active = "nas";

$devices = $conn->query("SELECT * FROM nas ORDER BY device_type, nasname");

$stats = [
    'olt' => ['total' => 0, 'online' => 0],
    'mikrotik' => ['total' => 0, 'online' => 0],
    'switch' => ['total' => 0, 'online' => 0],
    'router' => ['total' => 0, 'online' => 0]
];

// Function to check if device is reachable via ping
function checkDeviceStatus($ip) {
    if(empty($ip)) return 'offline';
    
    // Fast ping check (1 second timeout)
    $ping = stripos(PHP_OS, 'WIN') === 0 ? "ping -n 1 -w 1 $ip" : "ping -c 1 -W 1 $ip";
    $output = @shell_exec($ping . " 2>&1");
    
    if($output && (stripos($output, 'bytes from') !== false || stripos($output, '1 received') !== false)) {
        return 'online';
    }
    
    return 'offline';
}

$device_list = [];
while($d = $devices->fetch_assoc()) {
    $type = $d['device_type'] ?? 'router';
    if(!isset($stats[$type])) $type = 'router';
    $stats[$type]['total']++;
    
    // Check actual device status
    $status = checkDeviceStatus($d['ip_address']);
    if($status === 'online') {
        $stats[$type]['online']++;
    }
    
    $device_list[] = [
        'id' => $d['id'],
        'name' => $d['nasname'],
        'ip' => $d['ip_address'],
        'type' => $type,
        'model' => $d['model'] ?? '',
        'location' => $d['location'] ?? '',
        'status' => $status
    ];
}

// Auto-layout with hierarchical positions
$positions = [];
$layers = ['olt' => [], 'mikrotik' => [], 'switch' => [], 'router' => []];

// Group by type
foreach($device_list as $idx => $dev) {
    $layers[$dev['type']][] = $idx;
}

$startX = 120;
$startY = 100;
$layerGapY = 180;
$nodeGapX = 180;

// OLT Layer (Top)
$oltCount = count($layers['olt']);
$oltStartX = $startX + (max(0, 4 - $oltCount) * $nodeGapX / 2);
foreach($layers['olt'] as $i => $idx) {
    $positions[$idx] = ['x' => $oltStartX + $i * $nodeGapX, 'y' => $startY];
}

// MikroTik Layer
$mikroCount = count($layers['mikrotik']);
$mikroStartX = $startX + (max(0, 4 - $mikroCount) * $nodeGapX / 2);
foreach($layers['mikrotik'] as $i => $idx) {
    $positions[$idx] = ['x' => $mikroStartX + $i * $nodeGapX, 'y' => $startY + $layerGapY];
}

// Switch Layer
$switchCount = count($layers['switch']);
$switchStartX = $startX + (max(0, 6 - $switchCount) * $nodeGapX / 2);
foreach($layers['switch'] as $i => $idx) {
    $positions[$idx] = ['x' => $switchStartX + $i * $nodeGapX, 'y' => $startY + $layerGapY * 2];
}

// Router Layer (Bottom)
$routerCount = count($layers['router']);
$routerStartX = $startX + (max(0, 4 - $routerCount) * $nodeGapX / 2);
foreach($layers['router'] as $i => $idx) {
    $positions[$idx] = ['x' => $routerStartX + $i * $nodeGapX, 'y' => $startY + $layerGapY * 3];
}

// If no devices, create sample layout
if(empty($device_list)) {
    $positions = [
        0 => ['x' => 300, 'y' => 100],
        1 => ['x' => 300, 'y' => 280],
        2 => ['x' => 200, 'y' => 460],
        3 => ['x' => 400, 'y' => 460]
    ];
}

// Generate connections from database (manual cable links)
$connections = [];
$db_connections = $conn->query("SELECT * FROM network_topology_links");
while($c = $db_connections->fetch_assoc()) {
    $from_idx = array_search($c['from_device_id'], array_column($device_list, 'id'));
    $to_idx = array_search($c['to_device_id'], array_column($device_list, 'id'));
    if($from_idx !== false && $to_idx !== false) {
        $connections[] = [
            'from' => $from_idx,
            'to' => $to_idx,
            'type' => $c['cable_type']
        ];
    }
}

// If no database connections, use auto-generated topology
if(empty($connections)) {
    // Connect each OLT to each MikroTik (star topology)
    foreach($layers['olt'] as $oltIdx) {
        foreach($layers['mikrotik'] as $mikroIdx) {
            $connections[] = [
                'from' => $oltIdx,
                'to' => $mikroIdx,
                'type' => 'fiber'
            ];
        }
    }
    // Connect MikroTik to Switches
    foreach($layers['mikrotik'] as $mikroIdx) {
        foreach($layers['switch'] as $switchIdx) {
            $connections[] = [
                'from' => $mikroIdx,
                'to' => $switchIdx,
                'type' => 'copper'
            ];
        }
    }
    // Connect Switches to Routers
    foreach($layers['switch'] as $switchIdx) {
        foreach($layers['router'] as $routerIdx) {
            $connections[] = [
                'from' => $switchIdx,
                'to' => $routerIdx,
                'type' => 'wifi'
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Network Topology - NOC Monitor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="assets/css/network-topology.css">
<?php include_once __DIR__ . '/includes/actions_tag.php'; ?>
</head>
<body>

<div class="noc-header">
    <div class="noc-title">
        <i class="fa fa-project-diagram fa-lg"></i>
        NETWORK TOPOLOGY - NOC CENTER
    </div>
    <div style="display:flex; align-items:center; gap:15px;">
        <!-- Cable Type Selector -->
        <div id="cableTypeSelector" style="display:flex; gap:8px;">
            <button class="cable-type-btn active" data-action="selectCableType" data-args='["fiber"]' style="padding:6px 14px; border:2px solid #ef4444; background:#ef4444; color:#fff; border-radius:20px; cursor:pointer; font-size:12px; font-weight:600;">
                <i class="fa fa-bolt"></i> Fiber
            </button>
            <button class="cable-type-btn" data-action="selectCableType" data-args='["copper"]' style="padding:6px 14px; border:2px solid #f59e0b; background:#f59e0b; color:#fff; border-radius:20px; cursor:pointer; font-size:12px; font-weight:600;">
                <i class="fa fa-ethernet"></i> Copper
            </button>
            <button class="cable-type-btn" data-action="selectCableType" data-args='["wifi"]' style="padding:6px 14px; border:2px solid #10b981; background:#10b981; color:#fff; border-radius:20px; cursor:pointer; font-size:12px; font-weight:600;">
                <i class="fa fa-wifi"></i> WiFi
            </button>
        </div>
        <div class="noc-time" id="currentTime"></div>
        <button class="fullscreen-btn" data-action="toggleFullscreen">
            <i class="fa fa-expand"></i>
        </button>
    </div>
</div>

<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-icon olt"><i class="fa fa-server"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= e($stats['olt']['total']) ?></div>
            <div class="stat-label">OLT</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon mikrotik"><i class="fa fa-microchip"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= e($stats['mikrotik']['total']) ?></div>
            <div class="stat-label">MikroTik</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon switch"><i class="fa fa-network-wired"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= e($stats['switch']['total']) ?></div>
            <div class="stat-label">Switches</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon router"><i class="fa fa-router"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= e($stats['router']['total']) ?></div>
            <div class="stat-label">Routers</div>
        </div>
    </div>
    <div class="stat-item" style="margin-left:auto; background: rgba(16, 185, 129, 0.2); border-color: #10b981;">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fa fa-signal"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= array_sum(array_column($stats, 'total')) ?></div>
            <div class="stat-label">Total Online</div>
        </div>
    </div>
</div>

<div class="main-container">
    <!-- Device List Panel -->
    <div class="device-panel">
        <div class="panel-title">
            <i class="fa fa-list"></i> All Devices
        </div>
        <div class="device-list" id="deviceList">
            <?php foreach($device_list as $dev): ?>
            <div class="device-card <?= e($dev['status']) ?>" data-model="<?= htmlspecialchars($dev['model'] ?? '') ?>" data-location="<?= htmlspecialchars($dev['location'] ?? '') ?>" <?= action_attr('handleDeviceClick', [$dev['id'], $dev['name'], $dev['type'], $dev['ip']]) ?>>
                <div class="device-icon-small <?= e($dev['type']) ?>">
                    <i class="fa fa-<?= $dev['type'] == 'olt' ? 'server' : ($dev['type'] == 'mikrotik' ? 'microchip' : ($dev['type'] == 'switch' ? 'network-wired' : 'router')) ?>"></i>
                </div>
                <div class="device-info">
                    <div class="device-name"><?= htmlspecialchars($dev['name']) ?></div>
                    <div class="device-ip"><?= e($dev['ip']) ?></div>
                </div>
                <div class="status-indicator <?= e($dev['status']) ?>"></div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($device_list)): ?>
            <div style="text-align:center; padding:30px; color:#64748b;">
                <i class="fa fa-network-wired" style="font-size:30px; margin-bottom:10px; display:block; opacity:0.5;"></i>
                No devices. Add devices to see topology.
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Topology Map -->
    <!-- Mobile Toggle Buttons -->
    <button class="mobile-toggle" data-action="togglePanel" data-args='["devicePanel"]'>
        <i class="fa fa-bars"></i>
    </button>
    
    <div class="mobile-zoom">
        <button data-action="zoomOut"><i class="fa fa-minus"></i></button>
        <button data-action="resetZoom"><i class="fa fa-compress"></i></button>
        <button data-action="zoomIn"><i class="fa fa-plus"></i></button>
    </div>
    
    <div class="map-area" id="mapArea">
        <div class="topology-grid"></div>
        
        <!-- SVG Connections -->
        <svg class="connections-svg" id="connectionsSvg">
            <?php foreach($connections as $conn): 
                $from = $positions[$conn['from']] ?? ['x' => 0, 'y' => 0];
                $to = $positions[$conn['to']] ?? ['x' => 0, 'y' => 0];
            ?>
            <line class="conn-line <?= e($conn['type']) ?>" 
                  data-from="<?= e($conn['from']) ?>" data-to="<?= e($conn['to']) ?>"
                  x1="<?= $from['x'] + 35 ?>" y1="<?= $from['y'] + 35 ?>"
                  x2="<?= $to['x'] + 35 ?>" y2="<?= $to['y'] + 35 ?>" />
            <?php endforeach; ?>
        </svg>
        
        <!-- Layer Labels -->
        <div class="layer-label" style="top: <?= $startY + 25 ?>px;">OLT Layer</div>
        <div class="layer-label" style="top: <?= $startY + $layerGapY + 25 ?>px;">MikroTik</div>
        <div class="layer-label" style="top: <?= $startY + $layerGapY * 2 + 25 ?>px;">Switches</div>
        <div class="layer-label" style="top: <?= $startY + $layerGapY * 3 + 25 ?>px;">Routers</div>
        
        <!-- Device Nodes -->
        <?php foreach($device_list as $idx => $dev): ?>
        <div class="device-node <?= e($dev['status']) ?>" 
             data-id="<?= e($dev['id']) ?>"
             data-status="<?= e($dev['status']) ?>"
             style="left:<?= e($positions[$idx]['x']) ?>px; top:<?= e($positions[$idx]['y']) ?>px; <?= $dev['status'] === 'offline' ? 'opacity: 0.5;' : '' ?>" 
             <?= action_attr('handleDeviceClick', [$dev['id'], $dev['name'], $dev['type'], $dev['ip']]) ?>>
            <div class="node-icon <?= e($dev['type']) ?> <?= e($dev['status']) ?>">
                <i class="fa fa-<?= $dev['type'] == 'olt' ? 'server' : ($dev['type'] == 'mikrotik' ? 'microchip' : ($dev['type'] == 'switch' ? 'network-wired' : 'router')) ?>"></i>
                <?php if($dev['status'] === 'offline'): ?>
                <div class="offline-badge"><i class="fa fa-times"></i></div>
                <?php endif; ?>
            </div>
            <div class="node-label"><?= htmlspecialchars($dev['name']) ?></div>
            <div class="node-ip"><?= e($dev['ip']) ?></div>
            <div class="status-dot <?= e($dev['status']) ?>"></div>
        </div>
        <?php endforeach; ?>
        
        <!-- Info Panel -->
        <div class="info-panel" id="infoPanel">
            <div class="info-title">
                <i class="fa fa-server" id="infoIcon"></i> Device Details
                <button class="close-info" data-action="closeInfoPanel"><i class="fa fa-times"></i></button>
            </div>
            <div class="info-row">
                <span class="info-label">Name</span>
                <span class="info-value" id="infoName">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Type</span>
                <span class="info-value" id="infoType">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">IP Address</span>
                <span class="info-value" id="infoIp">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value" style="color:#10b981;">● Online</span>
            </div>
            <div class="info-row">
                <span class="info-label">Uptime</span>
                <span class="info-value" id="infoUptime">-</span>
            </div>
            <div class="info-actions">
                <a href="#" class="btn btn-primary" id="btnManage"><i class="fa fa-cog"></i> Manage</a>
                <a href="#" class="btn btn-success" id="btnPing"><i class="fa fa-broadcast-tower"></i> Ping</a>
                <a href="#" class="btn btn-warning" id="btnEdit"><i class="fa fa-edit"></i> Edit</a>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="legend">
            <div class="legend-item">
                <div class="legend-line fiber"></div>
                <span>Fiber (OLT)</span>
            </div>
            <div class="legend-item">
                <div class="legend-line copper"></div>
                <span>Copper</span>
            </div>
            <div class="legend-item">
                <div class="legend-line wifi"></div>
                <span>Wireless</span>
            </div>
        </div>
        
        <!-- Cable Control Panel - Top Right Corner -->
        <div style="position:absolute; top:10px; right:10px; background:rgba(30,41,59,0.95); padding:10px; border-radius:10px; border:1px solid #475569; z-index:250; display:flex; gap:5px; align-items:center;">
            <span style="color:#94a3b8; font-size:11px; margin-right:5px;">CABLE:</span>
            <button data-action="startCableMode" id="cableAddBtn" style="width:40px; height:40px; border-radius:8px; background:#3b82f6; border:none; color:white; cursor:pointer; font-size:16px;" title="Add Cable">
                <i class="fa fa-plus"></i>
            </button>
            <button data-action="startDeleteMode" id="cableDeleteBtn" style="width:40px; height:40px; border-radius:8px; background:#ef4444; border:none; color:white; cursor:pointer; font-size:16px;" title="Delete Cable">
                <i class="fa fa-trash"></i>
            </button>
            <button data-action="cancelMode" id="cableCancelBtn" style="width:40px; height:40px; border-radius:8px; background:#475569; border:none; color:white; cursor:pointer; font-size:16px; display:none;" title="Cancel">
                <i class="fa fa-times"></i>
            </button>
        </div>
    </div>
</div>

<!-- Add Device Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-content">
        <div class="modal-title"><i class="fa fa-plus-circle"></i> Add Network Device</div>
        <form id="addDeviceForm">
            <div class="form-group">
                <label class="form-label">Device Name</label>
                <input type="text" name="nasname" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">IP Address</label>
                <input type="text" name="ip_address" class="form-input" placeholder="192.168.1.1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Device Type</label>
                <select name="device_type" class="form-select" required>
                    <option value="olt">OLT</option>
                    <option value="mikrotik">MikroTik</option>
                    <option value="switch">Switch</option>
                    <option value="router">Router</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Model</label>
                <input type="text" name="model" class="form-input" placeholder="e.g., BDCOM P3608">
            </div>
            <div class="form-group">
                <label class="form-label">SNMP Community</label>
                <input type="text" name="snmp_community" class="form-input" placeholder="public">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" style="background:#475569; color:#fff;" data-action="closeAddModal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Device</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Device Modal -->
<div class="modal-overlay" id="editDeviceModal">
    <div class="modal-content">
        <div class="modal-title"><i class="fa fa-edit"></i> Edit Device</div>
        <form id="editDeviceForm">
            <input type="hidden" name="device_id" id="editDeviceId">
            <div class="form-group">
                <label class="form-label">Device Name</label>
                <input type="text" name="nasname" id="editDeviceName" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">IP Address</label>
                <input type="text" name="ip_address" id="editDeviceIp" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Device Type</label>
                <select name="device_type" id="editDeviceType" class="form-select" required>
                    <option value="olt">OLT</option>
                    <option value="mikrotik">MikroTik</option>
                    <option value="switch">Switch</option>
                    <option value="router">Router</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Model</label>
                <input type="text" name="model" id="editDeviceModel" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="location" id="editDeviceLocation" class="form-input">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" style="background:#ef4444; color:#fff;" data-action="deleteDevice"><i class="fa fa-trash"></i> Delete</button>
                <button type="button" class="btn" style="background:#475569; color:#fff;" data-action="closeEditModal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Cable Info Modal -->
<div class="modal-overlay" id="cableModal">
    <div class="modal-content">
        <div class="modal-title"><i class="fa fa-link"></i> Cable Details</div>
        <form id="cableForm">
            <input type="hidden" name="from_id" id="cableFromId">
            <input type="hidden" name="to_id" id="cableToId">
            <div class="form-group">
                <label class="form-label">Cable Name</label>
                <input type="text" name="cable_name" id="cableName" class="form-input" placeholder="e.g., Main Fiber to OLT">
            </div>
            <div class="form-group">
                <label class="form-label">Cable Type</label>
                <select name="cable_type" id="cableType" class="form-select">
                    <option value="fiber">Fiber</option>
                    <option value="copper">Copper</option>
                    <option value="wifi">Wireless</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" style="background:#ef4444; color:#fff;" data-action="deleteCable"><i class="fa fa-trash"></i> Delete Cable</button>
                <button type="button" class="btn" style="background:#475569; color:#fff;" data-action="closeCableModal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/network-topology.js"></script>


</body>
</html>
