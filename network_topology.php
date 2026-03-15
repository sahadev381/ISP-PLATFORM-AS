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
        
        .status-indicator.offline {
            background: #ef4444;
            box-shadow: 0 0 10px #ef4444;
            animation: none;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .map-area {
            flex: 1;
            position: relative;
            overflow: visible;
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
        
        .device-node.selected .node-icon {
            box-shadow: 0 0 30px #3b82f6, 0 0 50px #3b82f6;
            border: 3px solid #3b82f6;
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
        
        /* Offline styling */
        .device-node.offline { opacity: 0.5; }
        .device-node.offline .node-icon { filter: grayscale(100%); }
        .offline-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            border: 2px solid #fff;
        }
        .status-dot {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #1e293b;
        }
        .status-dot.online {
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
            animation: pulse 2s infinite;
        }
        .status-dot.offline {
            background: #ef4444;
            box-shadow: 0 0 10px #ef4444;
        }

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
            font-weight: 600;
            padding: 20px;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .close-info {
            margin-left: auto;
            background: rgba(239, 68, 68, 0.2);
            border: none;
            color: #ef4444;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-info:hover {
            background: rgba(239, 68, 68, 0.4);
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

        /* Add Button - Side Position */
.add-device-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: all 0.3s;
            z-index: 200;
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
        
        .add-device-btn i {
            color: #fff !important;
        }
        
        .add-device-btn:hover { 
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }
        
        .add-device-btn i {
            color: #fff !important;
        }
        
        .mobile-toggle {
            display: none;
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            font-size: 20px;
            z-index: 300;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            cursor: pointer;
        }
        
        .mobile-zoom {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 20px;
            gap: 10px;
            z-index: 300;
        }
        
        .mobile-zoom button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(30, 41, 59, 0.95);
            color: white;
            border: 1px solid #334155;
            font-size: 18px;
            cursor: pointer;
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
    <div style="display:flex; align-items:center; gap:15px;">
        <!-- Cable Type Selector -->
        <div id="cableTypeSelector" style="display:flex; gap:8px;">
            <button class="cable-type-btn active" onclick="selectCableType('fiber')" style="padding:6px 14px; border:2px solid #ef4444; background:#ef4444; color:#fff; border-radius:20px; cursor:pointer; font-size:12px; font-weight:600;">
                <i class="fa fa-bolt"></i> Fiber
            </button>
            <button class="cable-type-btn" onclick="selectCableType('copper')" style="padding:6px 14px; border:2px solid #f59e0b; background:#f59e0b; color:#fff; border-radius:20px; cursor:pointer; font-size:12px; font-weight:600;">
                <i class="fa fa-ethernet"></i> Copper
            </button>
            <button class="cable-type-btn" onclick="selectCableType('wifi')" style="padding:6px 14px; border:2px solid #10b981; background:#10b981; color:#fff; border-radius:20px; cursor:pointer; font-size:12px; font-weight:600;">
                <i class="fa fa-wifi"></i> WiFi
            </button>
        </div>
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
            <div class="device-card <?= $dev['status'] ?>" data-model="<?= htmlspecialchars($dev['model'] ?? '') ?>" data-location="<?= htmlspecialchars($dev['location'] ?? '') ?>" onclick="handleDeviceClick('<?= $dev['id'] ?>', '<?= $dev['name'] ?>', '<?= $dev['type'] ?>', '<?= $dev['ip'] ?>')">
                <div class="device-icon-small <?= $dev['type'] ?>">
                    <i class="fa fa-<?= $dev['type'] == 'olt' ? 'server' : ($dev['type'] == 'mikrotik' ? 'microchip' : ($dev['type'] == 'switch' ? 'network-wired' : 'router')) ?>"></i>
                </div>
                <div class="device-info">
                    <div class="device-name"><?= htmlspecialchars($dev['name']) ?></div>
                    <div class="device-ip"><?= $dev['ip'] ?></div>
                </div>
                <div class="status-indicator <?= $dev['status'] ?>"></div>
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
    <button class="mobile-toggle" onclick="togglePanel('devicePanel')">
        <i class="fa fa-bars"></i>
    </button>
    
    <div class="mobile-zoom">
        <button onclick="zoomOut()"><i class="fa fa-minus"></i></button>
        <button onclick="resetZoom()"><i class="fa fa-compress"></i></button>
        <button onclick="zoomIn()"><i class="fa fa-plus"></i></button>
    </div>
    
    <div class="map-area" id="mapArea">
        <div class="topology-grid"></div>
        
        <!-- SVG Connections -->
        <svg class="connections-svg" id="connectionsSvg">
            <?php foreach($connections as $conn): 
                $from = $positions[$conn['from']] ?? ['x' => 0, 'y' => 0];
                $to = $positions[$conn['to']] ?? ['x' => 0, 'y' => 0];
            ?>
            <line class="conn-line <?= $conn['type'] ?>" 
                  data-from="<?= $conn['from'] ?>" data-to="<?= $conn['to'] ?>"
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
        <div class="device-node <?= $dev['status'] ?>" 
             data-id="<?= $dev['id'] ?>"
             data-status="<?= $dev['status'] ?>"
             style="left:<?= $positions[$idx]['x'] ?>px; top:<?= $positions[$idx]['y'] ?>px; <?= $dev['status'] === 'offline' ? 'opacity: 0.5;' : '' ?>" 
             onclick="handleDeviceClick('<?= $dev['id'] ?>', '<?= $dev['name'] ?>', '<?= $dev['type'] ?>', '<?= $dev['ip'] ?>')">
            <div class="node-icon <?= $dev['type'] ?> <?= $dev['status'] ?>">
                <i class="fa fa-<?= $dev['type'] == 'olt' ? 'server' : ($dev['type'] == 'mikrotik' ? 'microchip' : ($dev['type'] == 'switch' ? 'network-wired' : 'router')) ?>"></i>
                <?php if($dev['status'] === 'offline'): ?>
                <div class="offline-badge"><i class="fa fa-times"></i></div>
                <?php endif; ?>
            </div>
            <div class="node-label"><?= htmlspecialchars($dev['name']) ?></div>
            <div class="node-ip"><?= $dev['ip'] ?></div>
            <div class="status-dot <?= $dev['status'] ?>"></div>
        </div>
        <?php endforeach; ?>
        
        <!-- Info Panel -->
        <div class="info-panel" id="infoPanel">
            <div class="info-title">
                <i class="fa fa-server" id="infoIcon"></i> Device Details
                <button class="close-info" onclick="closeInfoPanel()"><i class="fa fa-times"></i></button>
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
                <a href="#" class="btn btn-warning" id="btnEdit" onclick="return false;"><i class="fa fa-edit"></i> Edit</a>
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
            <button onclick="startCableMode()" id="cableAddBtn" style="width:40px; height:40px; border-radius:8px; background:#3b82f6; border:none; color:white; cursor:pointer; font-size:16px;" title="Add Cable">
                <i class="fa fa-plus"></i>
            </button>
            <button onclick="startDeleteMode()" id="cableDeleteBtn" style="width:40px; height:40px; border-radius:8px; background:#ef4444; border:none; color:white; cursor:pointer; font-size:16px;" title="Delete Cable">
                <i class="fa fa-trash"></i>
            </button>
            <button onclick="cancelMode()" id="cableCancelBtn" style="width:40px; height:40px; border-radius:8px; background:#475569; border:none; color:white; cursor:pointer; font-size:16px; display:none;" title="Cancel">
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
                <button type="button" class="btn" style="background:#475569; color:#fff;" onclick="closeAddModal()">Cancel</button>
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
                <button type="button" class="btn" style="background:#ef4444; color:#fff;" onclick="deleteDevice()"><i class="fa fa-trash"></i> Delete</button>
                <button type="button" class="btn" style="background:#475569; color:#fff;" onclick="closeEditModal()">Cancel</button>
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
                <button type="button" class="btn" style="background:#ef4444; color:#fff;" onclick="deleteCable()"><i class="fa fa-trash"></i> Delete Cable</button>
                <button type="button" class="btn" style="background:#475569; color:#fff;" onclick="closeCableModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
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

// Select device from panel
function selectDevice(id, name, type, ip) {
    document.querySelectorAll('.device-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.device-card')?.classList.add('selected');
    
    showDeviceDetails(id, name, type, ip);
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

// Edit Device Modal
function openEditModal(id, name, type, ip, model, location) {
    document.getElementById('editDeviceId').value = id;
    document.getElementById('editDeviceName').value = name;
    document.getElementById('editDeviceIp').value = ip;
    document.getElementById('editDeviceType').value = type;
    document.getElementById('editDeviceModel').value = model || '';
    document.getElementById('editDeviceLocation').value = location || '';
    document.getElementById('editDeviceModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editDeviceModal').classList.remove('show');
}

function deleteDevice() {
    if(confirm('Are you sure you want to delete this device?')) {
        const id = document.getElementById('editDeviceId').value;
        fetch('api/network_topology.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_device&id=' + id
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

document.getElementById('editDeviceForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'edit_device');
    
    fetch('api/network_topology.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Device updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
};

// Cable Modal
let currentCableFrom = null;
let currentCableTo = null;

function openCableModal(fromId, toId, type, name) {
    currentCableFrom = fromId;
    currentCableTo = toId;
    document.getElementById('cableFromId').value = fromId;
    document.getElementById('cableToId').value = toId;
    document.getElementById('cableType').value = type || 'copper';
    document.getElementById('cableName').value = name || '';
    document.getElementById('cableModal').classList.add('show');
}

function closeCableModal() {
    document.getElementById('cableModal').classList.remove('show');
}

function deleteCable() {
    if(confirm('Delete this cable connection?')) {
        const fromId = document.getElementById('cableFromId').value;
        const toId = document.getElementById('cableToId').value;
        fetch('api/network_topology.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_connection&from_id=' + fromId + '&to_id=' + toId
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

document.getElementById('cableForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_connection');
    
    fetch('api/network_topology.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Cable updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
};

// Click on cable to edit or delete
document.querySelector('.connections-svg').addEventListener('click', function(e) {
    if(e.target.tagName === 'line') {
        const fromId = e.target.dataset.from;
        const toId = e.target.dataset.to;
        
        if(cableMode === 'delete') {
            // Delete cable immediately
            e.target.remove();
            fetch('api/network_topology.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete_connection&from_id=' + fromId + '&to_id=' + toId
            });
        } else if(!cableMode) {
            // Normal click - edit cable
            const type = e.target.classList.contains('fiber') ? 'fiber' : 
                         e.target.classList.contains('copper') ? 'copper' : 'wifi';
            openCableModal(fromId, toId, type, '');
        }
    }
});

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

// Cable Mode System
let cableMode = null; // 'add' or 'delete' or null
let drawSource = null;

function startCableMode() {
    cableMode = 'add';
    drawSource = null;
    document.getElementById('cableAddBtn').style.background = '#059669';
    document.getElementById('cableAddBtn').style.transform = 'scale(1.2)';
    document.getElementById('cableDeleteBtn').style.transform = 'scale(1)';
    document.getElementById('cableCancelBtn').style.display = 'inline-block';
}

function startDeleteMode() {
    cableMode = 'delete';
    drawSource = null;
    document.getElementById('cableDeleteBtn').style.background = '#dc2626';
    document.getElementById('cableDeleteBtn').style.transform = 'scale(1.2)';
    document.getElementById('cableAddBtn').style.transform = 'scale(1)';
    document.getElementById('cableCancelBtn').style.display = 'inline-block';
}

function cancelMode() {
    cableMode = null;
    drawSource = null;
    document.getElementById('cableAddBtn').style.background = '#3b82f6';
    document.getElementById('cableAddBtn').style.transform = 'scale(1)';
    document.getElementById('cableDeleteBtn').style.background = '#ef4444';
    document.getElementById('cableDeleteBtn').style.transform = 'scale(1)';
    document.getElementById('cableCancelBtn').style.display = 'none';
}

function handleDeviceClick(id, name, type, ip) {
    if(cableMode === 'add') {
        if(!drawSource) {
            drawSource = {id, name, type, ip};
            document.querySelectorAll('.device-node').forEach(n => n.style.boxShadow = '');
            document.querySelector('.device-node[data-id="' + id + '"]')?.style.boxShadow = '0 0 20px #10b981';
        } else {
            if(drawSource.id !== id) {
                createConnection(drawSource.id, id, selectedCableType);
            }
            document.querySelectorAll('.device-node').forEach(n => n.style.boxShadow = '');
            drawSource = null;
            alert('Cable added!');
        }
        return false;
    }
    if(cableMode === 'delete') {
        return false; // Don't show device details when in delete mode
    }
    // Show device details
    showDeviceDetails(id, name, type, ip);
    return true;
}

function showDeviceDetails(id, name, type, ip) {
    // Get device status from the clicked node
    const deviceNode = document.querySelector('.device-node[data-id="' + id + '"]');
    const status = deviceNode ? deviceNode.dataset.status : 'offline';
    
    // Update info panel
    document.getElementById('infoName').innerText = name;
    document.getElementById('infoType').innerText = type.toUpperCase();
    document.getElementById('infoIp').innerText = ip;
    
    // Update status
    const statusEl = document.querySelector('#infoPanel .info-row:last-child .info-value');
    if(status === 'online') {
        statusEl.innerHTML = '<span style="color:#10b981;">● Online</span>';
    } else {
        statusEl.innerHTML = '<span style="color:#ef4444;">● Offline</span>';
    }
    
    // Update icon
    const icon = document.getElementById('infoIcon');
    const iconClass = type === 'olt' ? 'server' : (type === 'mikrotik' ? 'microchip' : (type === 'switch' ? 'network-wired' : 'router'));
    icon.className = 'fa fa-' + iconClass;
    icon.style.color = type === 'olt' ? '#ef4444' : (type === 'mikrotik' ? '#f59e0b' : (type === 'switch' ? '#10b981' : '#3b82f6'));
    
    // Set manage URL
    const manageUrl = type === 'olt' ? 'olt_dashboard.php?id=' + id :
                      type === 'mikrotik' ? 'mikrotik_dashboard.php?id=' + id :
                      type === 'switch' ? 'switch_dashboard.php?id=' + id : 'nas.php?id=' + id;
    document.getElementById('btnManage').href = manageUrl;
    
    // Set Edit button - get model and location from device card
    const deviceCard = document.querySelector('.device-card[data-id="' + id + '"]');
    const model = deviceCard ? deviceCard.dataset.model : '';
    const location = deviceCard ? deviceCard.dataset.location : '';
    document.getElementById('btnEdit').onclick = function() {
        openEditModal(id, name, type, ip, model, location);
        return false;
    };
    
    // Show panel
    const infoPanel = document.getElementById('infoPanel');
    infoPanel.classList.add('show');
    
    // Close device panel on mobile
    if(window.innerWidth <= 768) {
        document.getElementById('devicePanel').classList.remove('show');
    }
}

function closeInfoPanel() {
    document.getElementById('infoPanel').classList.remove('show');
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

// Drag functionality for devices
let draggedNode = null;
let dragOffsetX = 0;
let dragOffsetY = 0;

document.addEventListener('DOMContentLoaded', function() {
    const mapArea = document.querySelector('.map-area');
    
    document.querySelectorAll('.device-node').forEach(node => {
        // Mouse events
        node.addEventListener('mousedown', startDrag);
        
        // Touch events for mobile
        node.addEventListener('touchstart', startDrag, {passive: false});
    });
    
    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', drag, {passive: false});
    
    document.addEventListener('mouseup', endDrag);
    document.addEventListener('touchend', endDrag);
});

function startDrag(e) {
    if(e.target.closest('.device-node')) {
        draggedNode = e.target.closest('.device-node');
        const rect = draggedNode.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        dragOffsetX = clientX - rect.left;
        dragOffsetY = clientY - rect.top;
        draggedNode.style.zIndex = 100;
    }
}

function drag(e) {
    if(!draggedNode) return;
    e.preventDefault();
    
    const mapArea = document.querySelector('.map-area');
    const mapRect = mapArea.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    
    let newX = clientX - mapRect.left - dragOffsetX;
    let newY = clientY - mapRect.top - dragOffsetY;
    
    // Bounds
    newX = Math.max(0, Math.min(newX, mapRect.width - 70));
    newY = Math.max(0, Math.min(newY, mapRect.height - 100));
    
    draggedNode.style.left = newX + 'px';
    draggedNode.style.top = newY + 'px';
    draggedNode.style.transform = 'none';
    
    updateConnections();
}

function endDrag() {
    if(draggedNode) {
        draggedNode.style.zIndex = 10;
        draggedNode = null;
    }
}

// Update SVG connections on drag
function updateConnections() {
    const svg = document.querySelector('.connections-svg');
    if(!svg) return;
    
    document.querySelectorAll('.device-node').forEach(node => {
        const id = node.dataset.id;
        const x = parseFloat(node.style.left) + 35;
        const y = parseFloat(node.style.top) + 35;
        
        document.querySelectorAll(`.conn-line[data-from="${id}"]`).forEach(line => {
            const toId = line.dataset.to;
            const toNode = document.querySelector(`.device-node[data-id="${toId}"]`);
            if(toNode) {
                const toX = parseFloat(toNode.style.left) + 35;
                const toY = parseFloat(toNode.style.top) + 35;
                line.setAttribute('x2', toX);
                line.setAttribute('y2', toY);
            }
        });
        
        document.querySelectorAll(`.conn-line[data-to="${id}"]`).forEach(line => {
            const fromId = line.dataset.from;
            const fromNode = document.querySelector(`.device-node[data-id="${fromId}"]`);
            if(fromNode) {
                const fromX = parseFloat(fromNode.style.left) + 35;
                const fromY = parseFloat(fromNode.style.top) + 35;
                line.setAttribute('x1', fromX);
                line.setAttribute('y1', fromY);
            }
        });
    });
}

// Responsive: Toggle panels on mobile
function togglePanel(panelId) {
    const panel = document.getElementById(panelId);
    if(window.innerWidth <= 768) {
        panel.classList.toggle('show');
    }
}

// Mobile zoom
let scale = 1;
function zoomIn() {
    scale = Math.min(scale + 0.1, 2);
    document.querySelector('.map-area').style.transform = 'scale(' + scale + ')';
}
function zoomOut() {
    scale = Math.max(scale - 0.1, 0.5);
    document.querySelector('.map-area').style.transform = 'scale(' + scale + ')';
}
function resetZoom() {
    scale = 1;
    document.querySelector('.map-area').style.transform = 'scale(1)';
}
</script>

<style>
/* Responsive Styles */
@media (max-width: 1024px) {
    .device-panel { width: 280px; }
    .node-icon { width: 60px; height: 60px; font-size: 22px; }
    .stats-bar { flex-wrap: wrap; gap: 10px; }
    .stat-item { padding: 10px 15px; }
}

@media (max-width: 768px) {
    .noc-header { flex-direction: column; gap: 10px; padding: 10px 15px; }
    .noc-title { font-size: 18px; }
    
    .stats-bar { 
        padding: 10px; 
        justify-content: center;
    }
    .stat-item { 
        flex: 1 1 45%; 
        min-width: 120px;
        padding: 8px 12px;
    }
    .stat-icon { width: 35px; height: 35px; font-size: 16px; }
    .stat-count { font-size: 18px; }
    .stat-label { font-size: 10px; }
    
    .main-container { flex-direction: column; height: calc(100vh - 180px); }
    
    .device-panel {
        position: fixed;
        top: 180px;
        left: -100%;
        width: 100%;
        height: calc(100% - 180px);
        z-index: 50;
        transition: left 0.3s;
        border-right: none;
    }
    .device-panel.show { left: 0; }
    
    .map-area { width: 100%; }
    
    .node-icon { width: 55px; height: 55px; font-size: 20px; }
    .node-label { font-size: 10px; top: 70px; }
    .node-ip { top: 88px; font-size: 9px; }
    
    .info-panel {
        position: fixed;
        bottom: -100%;
        left: 0;
        width: 100%;
        height: auto;
        max-height: 50%;
        border-radius: 20px 20px 0 0;
        transition: bottom 0.3s;
        z-index: 60;
    }
    .info-panel.show { bottom: 0; }
    
    .action-buttons { flex-wrap: wrap; }
    .action-buttons .btn { flex: 1 1 45%; }
    
    .mobile-toggle {
        display: flex !important;
    }
    
    .toolbar { flex-wrap: wrap; gap: 5px; }
    .toolbar .btn { font-size: 12px; padding: 8px 12px; }
}

@media (max-width: 480px) {
    .stat-item { flex: 1 1 100%; }
    .node-icon { width: 50px; height: 50px; font-size: 18px; }
    .device-node { transform: scale(0.9); }
}

.mobile-toggle {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    border: none;
    font-size: 20px;
    z-index: 40;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.mobile-zoom {
    display: none;
    position: fixed;
    bottom: 20px;
    left: 20px;
    gap: 10px;
    z-index: 40;
}

@media (max-width: 768px) {
    .mobile-toggle, .mobile-zoom { display: flex; }
    .mobile-zoom button {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: rgba(30, 41, 59, 0.9);
        color: white;
        border: 1px solid #334155;
        font-size: 18px;
    }
}
</style>

</body>
</html>
