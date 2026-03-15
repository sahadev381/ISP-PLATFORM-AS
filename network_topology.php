<?php
include 'config.php';
include 'includes/auth.php';

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

$device_list = [];
while($d = $devices->fetch_assoc()) {
    $type = $d['device_type'] ?? 'router';
    if(!isset($stats[$type])) $type = 'router';
    $stats[$type]['total']++;
    $stats[$type]['online']++;
    
    $device_list[] = [
        'id' => $d['id'],
        'name' => $d['nasname'],
        'ip' => $d['ip_address'],
        'type' => $type,
        'model' => $d['model'] ?? '',
        'location' => $d['location'] ?? '',
        'status' => 'online'
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            overflow: hidden; 
        }

        .noc-header {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
            backdrop-filter: blur(10px);
        }
        
        .noc-title {
            color: #f8fafc;
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .noc-title i { 
            background: linear-gradient(135deg, #6366f1, #0ea5e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .noc-time {
            color: #94a3b8;
            font-size: 14px;
            font-family: monospace;
        }

        .stats-bar {
            display: flex;
            gap: 15px;
            padding: 15px 30px;
            background: rgba(30, 41, 59, 0.8);
            border-bottom: 1px solid #334155;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background: rgba(15, 23, 42, 0.8);
            border-radius: 12px;
            border: 1px solid #334155;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            transform: translateY(-2px);
            border-color: #6366f1;
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
        }
        
        .stat-icon.olt { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-icon.mikrotik { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.switch { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.router { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        
        .stat-info { color: #f8fafc; }
        .stat-count { font-size: 24px; font-weight: 800; }
        .stat-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .main-container {
            display: flex;
            height: calc(100vh - 130px);
        }
        
        .device-panel {
            width: 320px;
            background: rgba(30, 41, 59, 0.9);
            border-right: 1px solid #334155;
            overflow-y: auto;
            backdrop-filter: blur(10px);
        }
        
        .panel-title {
            color: #f8fafc;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 20px 10px;
            border-bottom: 1px solid #334155;
        }
        
        .device-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 15px;
        }
        
        .device-card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .device-card:hover {
            border-color: #6366f1;
            transform: translateX(5px);
            background: rgba(99, 102, 241, 0.1);
        }
        
        .device-card.selected {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.15);
        }
        
        .device-icon-small {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            flex-shrink: 0;
        }
        
        .device-icon-small.olt { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .device-icon-small.mikrotik { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .device-icon-small.switch { background: linear-gradient(135deg, #10b981, #059669); }
        .device-icon-small.router { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        
        .device-info { flex: 1; min-width: 0; }
        .device-name { color: #f8fafc; font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .device-ip { color: #94a3b8; font-size: 11px; font-family: monospace; }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .map-area {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        /* Grid Background */
        .topology-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                linear-gradient(rgba(51, 65, 85, 0.3) 1px, transparent 1px),
                linear-gradient(90deg, rgba(51, 65, 85, 0.3) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
        }

        /* SVG Connections */
        .connections-svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .conn-line {
            stroke: #475569;
            stroke-width: 2;
            fill: none;
            transition: all 0.3s;
        }

        .conn-line.fiber {
            stroke: #ef4444;
            stroke-width: 3;
            stroke-dasharray: 8, 4;
        }

        .conn-line.copper {
            stroke: #f59e0b;
            stroke-width: 2;
        }

        .conn-line.wifi {
            stroke: #10b981;
            stroke-width: 2;
            stroke-dasharray: 4, 4;
        }

        .conn-line.active {
            stroke: #10b981;
            filter: drop-shadow(0 0 5px #10b981);
        }

        /* Device Nodes */
        .device-node {
            position: absolute;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 10;
        }

        .device-node:hover {
            transform: scale(1.15);
            z-index: 20;
        }

        .node-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #fff;
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.4), 0 10px 20px rgba(0,0,0,0.3);
            border: 3px solid rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .device-node:hover .node-icon {
            box-shadow: 0 0 50px rgba(99, 102, 241, 0.6), 0 15px 30px rgba(0,0,0,0.4);
            border-color: rgba(255,255,255,0.4);
        }

        .node-icon.olt { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .node-icon.mikrotik { background: linear-gradient(135deg, #f59e0b, #b45309); }
        .node-icon.switch { background: linear-gradient(135deg, #10b981, #047857); }
        .node-icon.router { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

        .node-label {
            position: absolute;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            color: #f8fafc;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            text-align: center;
            background: rgba(15, 23, 42, 0.9);
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid #334155;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .node-ip {
            position: absolute;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            color: #94a3b8;
            font-size: 10px;
            font-family: monospace;
        }

        /* Layer Labels */
        .layer-label {
            position: absolute;
            left: 30px;
            color: #64748b;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 5px 10px;
            background: rgba(15, 23, 42, 0.8);
            border-radius: 5px;
            border: 1px solid #334155;
        }

        /* Info Panel */
        .info-panel {
            position: absolute;
            right: 20px;
            top: 20px;
            width: 280px;
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 20px;
            display: none;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .info-panel.show { display: block; animation: slideIn 0.3s ease; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .info-title {
            color: #f8fafc;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-title i {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .info-title i.olt { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .info-title i.mikrotik { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .info-title i.switch { background: linear-gradient(135deg, #10b981, #059669); }
        .info-title i.router { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #334155;
        }
        
        .info-row:last-child { border-bottom: none; }
        
        .info-label { color: #94a3b8; font-size: 12px; }
        .info-value { color: #f8fafc; font-weight: 600; font-size: 12px; font-family: monospace; }
        
        .info-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4); }
        
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
        .btn-success:hover { transform: translateY(-2px); }

        /* Add Button */
.add-device-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.5);
            transition: all 0.3s;
            z-index: 100;
        }
        
        .add-device-btn:hover { 
            transform: scale(1.1);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.6);
        }

        .add-device-btn.active {
            background: linear-gradient(135deg, #10b981, #059669);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5); }
            50% { box-shadow: 0 10px 40px rgba(16, 185, 129, 0.8); }
        }
        
        .add-device-btn.active {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.6);
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.show { display: flex; }
        
        .modal-content {
            background: #1e293b;
            border-radius: 20px;
            padding: 30px;
            width: 450px;
            max-width: 90%;
            border: 1px solid #334155;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            animation: modalIn 0.3s ease;
        }
        
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-title {
            color: #f8fafc;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 25px;
        }
        
        .form-group { margin-bottom: 18px; }
        
        .form-label {
            display: block;
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 10px;
            color: #f8fafc;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .fullscreen-btn {
            background: #334155;
            border: none;
            color: #94a3b8;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .fullscreen-btn:hover { background: #475569; color: #f8fafc; }

        /* Legend */
        .cable-type-btn.active {
            background: #ef4444 !important;
            color: #fff !important;
        }

        .legend {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            gap: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #94a3b8;
        }

        .legend-line {
            width: 30px;
            height: 3px;
            border-radius: 2px;
        }

        .legend-line.fiber { background: #ef4444; }
        .legend-line.copper { background: #f59e0b; }
        .legend-line.wifi { background: #10b981; }
    </style>
</head>
<body>

<div class="noc-header">
    <div class="noc-title">
        <i class="fa fa-project-diagram fa-lg"></i>
        NETWORK TOPOLOGY - NOC CENTER
    </div>
    <div style="display:flex; align-items:center; gap:20px;">
        <div class="noc-time" id="currentTime"></div>
        <button class="fullscreen-btn" onclick="toggleFullscreen()">
            <i class="fa fa-expand"></i>
        </button>
    </div>
</div>

<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-icon olt"><i class="fa fa-server"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= $stats['olt']['total'] ?></div>
            <div class="stat-label">OLT</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon mikrotik"><i class="fa fa-microchip"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= $stats['mikrotik']['total'] ?></div>
            <div class="stat-label">MikroTik</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon switch"><i class="fa fa-network-wired"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= $stats['switch']['total'] ?></div>
            <div class="stat-label">Switches</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon router"><i class="fa fa-router"></i></div>
        <div class="stat-info">
            <div class="stat-count"><?= $stats['router']['total'] ?></div>
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
            <div class="device-card" onclick="handleDeviceClick('<?= $dev['id'] ?>', '<?= $dev['name'] ?>', '<?= $dev['type'] ?>', '<?= $dev['ip'] ?>')">
                <div class="device-icon-small <?= $dev['type'] ?>">
                    <i class="fa fa-<?= $dev['type'] == 'olt' ? 'server' : ($dev['type'] == 'mikrotik' ? 'microchip' : ($dev['type'] == 'switch' ? 'network-wired' : 'router')) ?>"></i>
                </div>
                <div class="device-info">
                    <div class="device-name"><?= htmlspecialchars($dev['name']) ?></div>
                    <div class="device-ip"><?= $dev['ip'] ?></div>
                </div>
                <div class="status-indicator"></div>
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
    <div class="map-area">
        <div class="topology-grid"></div>
        
        <!-- SVG Connections -->
        <svg class="connections-svg" id="connectionsSvg">
            <?php foreach($connections as $conn): 
                $from = $positions[$conn['from']] ?? ['x' => 0, 'y' => 0];
                $to = $positions[$conn['to']] ?? ['x' => 0, 'y' => 0];
            ?>
            <line class="conn-line <?= $conn['type'] ?>" 
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
        <div class="device-node" 
             style="left:<?= $positions[$idx]['x'] ?>px; top:<?= $positions[$idx]['y'] ?>px;" 
             onclick="handleDeviceClick('<?= $dev['id'] ?>', '<?= $dev['name'] ?>', '<?= $dev['type'] ?>', '<?= $dev['ip'] ?>')">
            <div class="node-icon <?= $dev['type'] ?>">
                <i class="fa fa-<?= $dev['type'] == 'olt' ? 'server' : ($dev['type'] == 'mikrotik' ? 'microchip' : ($dev['type'] == 'switch' ? 'network-wired' : 'router')) ?>"></i>
            </div>
            <div class="node-label"><?= htmlspecialchars($dev['name']) ?></div>
            <div class="node-ip"><?= $dev['ip'] ?></div>
        </div>
        <?php endforeach; ?>
        
        <!-- Info Panel -->
        <div class="info-panel" id="infoPanel">
            <div class="info-title">
                <i class="fa fa-server" id="infoIcon"></i> Device Details
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
        
        <button class="add-device-btn" id="drawModeBtn" onclick="toggleDrawMode()" style="right: 250px; background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fa fa-link"></i>
        </button>
        <div id="cableTypeSelector" style="position:absolute; bottom:25px; right:330px; display:flex; gap:5px; z-index:100;">
            <button class="cable-type-btn active" onclick="selectCableType('fiber')" style="padding:8px 12px; border:2px solid #ef4444; background:#ef4444; color:#fff; border-radius:20px; cursor:pointer; font-size:11px; font-weight:600;">
                <i class="fa fa-bolt"></i> Fiber
            </button>
            <button class="cable-type-btn" onclick="selectCableType('copper')" style="padding:8px 12px; border:2px solid #f59e0b; background:transparent; color:#f59e0b; border-radius:20px; cursor:pointer; font-size:11px; font-weight:600;">
                <i class="fa fa-ethernet"></i> Copper
            </button>
            <button class="cable-type-btn" onclick="selectCableType('wifi')" style="padding:8px 12px; border:2px solid #10b981; background:transparent; color:#10b981; border-radius:20px; cursor:pointer; font-size:11px; font-weight:600;">
                <i class="fa fa-wifi"></i> WiFi
            </button>
        </div>
        <button class="add-device-btn" onclick="clearConnections()" style="right: 20px; background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fa fa-unlink"></i>
        </button>
        <button class="add-device-btn" onclick="openAddModal()">
            <i class="fa fa-plus"></i>
        </button>
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
                <button type="button" class="btn" style="background:#475569; color:#fff;" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Device</button>
            </div>
        </form>
    </div>
</div>

<script>
// Update time
function updateTime() {
    const now = new Date();
    document.getElementById('currentTime').innerHTML = 
        now.toLocaleDateString() + ' <span style="margin-left:15px;">' + now.toLocaleTimeString() + '</span>';
}
setInterval(updateTime, 1000);
updateTime();

// Select device
function selectDevice(id, name, type, ip) {
    document.querySelectorAll('.device-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.device-card')?.classList.add('selected');
    
    document.getElementById('infoName').innerText = name;
    document.getElementById('infoType').innerText = type.toUpperCase();
    document.getElementById('infoIp').innerText = ip;
    document.getElementById('infoUptime').innerText = '10d 5h 30m';
    
    const icon = document.getElementById('infoIcon');
    icon.className = 'fa fa-' + (type === 'olt' ? 'server' : (type === 'mikrotik' ? 'microchip' : (type === 'switch' ? 'network-wired' : 'router'))) + ' ' + type;
    
    const manageUrl = type === 'olt' ? 'olt_dashboard.php?id=' + id :
                      type === 'mikrotik' ? 'mikrotik_dashboard.php?id=' + id :
                      type === 'switch' ? 'switch_dashboard.php?id=' + id : 'nas.php';
    document.getElementById('btnManage').href = manageUrl;
    document.getElementById('btnPing').onclick = () => pingDevice(ip);
    
    document.getElementById('infoPanel').classList.add('show');
}

function pingDevice(ip) {
    alert('Pinging ' + ip + '...');
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function openAddModal() {
    document.getElementById('addModal').classList.add('show');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
}

document.getElementById('addDeviceForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_device');
    
    fetch('api/network_topology.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Device added successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
};

// Auto-refresh
setTimeout(() => location.reload(), 60000);

// Cable Drawing Mode
let drawMode = false;
let drawSource = null;

function toggleDrawMode() {
    drawMode = !drawMode;
    const btn = document.getElementById('drawModeBtn');
    if(drawMode) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fa fa-times"></i> Cancel';
        alert('Click on a device to start drawing cable. Then click another device to connect.');
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fa fa-link"></i> Draw Cable';
        drawSource = null;
    }
}

function handleDeviceClick(id, name, type, ip) {
    if(drawMode) {
        if(!drawSource) {
            drawSource = {id, name, type, ip};
            alert('Source: ' + name + '. Now click another device to connect.');
        } else {
            if(drawSource.id !== id) {
                createConnection(drawSource.id, id, selectedCableType);
            }
            drawSource = null;
            toggleDrawMode();
        }
        return false;
    }
    return true;
}

function createConnection(fromId, toId, cableType) {
    cableType = cableType || 'copper';
    fetch('api/network_topology.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_connection&from_id=' + fromId + '&to_id=' + toId + '&cable_type=' + cableType
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Cable connected successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function clearConnections() {
    if(confirm('Clear all custom cable connections? This will reset to auto-generated topology.')) {
        fetch('api/network_topology.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=clear_connections'
        })
        .then(r => r.json())
        .then(data => {
            location.reload();
        });
    }
}

// Cable type selection
let selectedCableType = 'copper';
function selectCableType(type) {
    selectedCableType = type;
    document.querySelectorAll('.cable-type-btn').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');
}
</script>

</body>
</html>
