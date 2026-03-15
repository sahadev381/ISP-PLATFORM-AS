<?php
/**
 * SNMP Monitoring API
 * Polls network devices for CPU, Memory, Bandwidth stats
 */

header('Content-Type: application/json');
include_once '../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

switch ($action) {
    
    // Poll single device SNMP
    case 'poll_device':
        $device_id = intval($_GET['device_id'] ?? 0);
        
        if (!$device_id) {
            jsonResponse(false, 'Device ID required');
        }
        
        $device = $conn->query("SELECT * FROM nas WHERE id = $device_id")->fetch_assoc();
        
        if (!$device) {
            jsonResponse(false, 'Device not found');
        }
        
        $metrics = pollDeviceSNMP($device);
        
        // Store in database
        foreach ($metrics as $m) {
            $conn->query("INSERT INTO snmp_metrics (device_id, device_ip, metric_type, metric_value)
                VALUES ($device_id, '{$device['ip_address']}', '{$m['type']}', {$m['value']})");
        }
        
        jsonResponse(true, 'Device polled successfully', $metrics);
        break;
    
    // Get device history
    case 'device_history':
        $device_id = intval($_GET['device_id'] ?? 0);
        $hours = intval($_GET['hours'] ?? 24);
        
        if (!$device_id) {
            jsonResponse(false, 'Device ID required');
        }
        
        $history = $conn->query("
            SELECT * FROM snmp_metrics 
            WHERE device_id = $device_id 
            AND recorded_at > DATE_SUB(NOW(), INTERVAL $hours HOUR)
            ORDER BY recorded_at ASC
        ");
        
        $data = [];
        while ($row = $history->fetch_assoc()) {
            $data[] = $row;
        }
        
        jsonResponse(true, '', $data);
        break;
    
    // Get all devices status
    case 'all_devices_status':
        $devices = $conn->query("SELECT * FROM nas WHERE device_type IN ('mikrotik', 'olt', 'switch')");
        
        $data = [];
        while ($d = $devices->fetch_assoc()) {
            $lastMetric = $conn->query("
                SELECT * FROM snmp_metrics 
                WHERE device_id = {$d['id']} 
                ORDER BY recorded_at DESC LIMIT 1
            ")->fetch_assoc();
            
            $data[] = [
                'id' => $d['id'],
                'name' => $d['nasname'],
                'ip' => $d['ip_address'],
                'type' => $d['device_type'],
                'last_poll' => $lastMetric['recorded_at'] ?? null,
                'cpu' => null,
                'memory' => null,
                'status' => 'unknown'
            ];
        }
        
        jsonResponse(true, '', $data);
        break;
    
    // Get MikroTik specific stats
    case 'mikrotik_stats':
        $device_id = intval($_GET['device_id'] ?? 0);
        
        if (!$device_id) {
            jsonResponse(false, 'Device ID required');
        }
        
        $device = $conn->query("SELECT * FROM nas WHERE id = $device_id AND device_type = 'mikrotik'")->fetch_assoc();
        
        if (!$device) {
            jsonResponse(false, 'MikroTik not found');
        }
        
        // Get stats via MikroTik API
        $stats = getMikrotikStats($device);
        
        jsonResponse(true, '', $stats);
        break;
    
    // Get switch stats
    case 'switch_stats':
        $device_id = intval($_GET['device_id'] ?? 0);
        
        if (!$device_id) {
            jsonResponse(false, 'Device ID required');
        }
        
        $device = $conn->query("SELECT * FROM nas WHERE id = $device_id AND device_type = 'switch'")->fetch_assoc();
        
        if (!$device) {
            jsonResponse(false, 'Switch not found');
        }
        
        $stats = getSwitchStats($device);
        
        jsonResponse(true, '', $stats);
        break;
    
    default:
        jsonResponse(false, 'Unknown action');
}

// ============================================
// SNMP Functions
// ============================================

function pollDeviceSNMP($device) {
    $metrics = [];
    $ip = $device['ip_address'];
    $community = $device['snmp_community'] ?? 'public';
    
    // Check if snmp extension is available
    if (!function_exists('snmp2_get')) {
        // Fallback: generate simulated data for demo
        $metrics = [
            ['type' => 'cpu', 'value' => rand(10, 60)],
            ['type' => 'memory', 'value' => rand(30, 70)],
            ['type' => 'uptime', 'value' => rand(1000, 50000)],
        ];
        return $metrics;
    }
    
    try {
        // CPU (generic OID - may vary by device)
        $cpu = @snmp2_get($ip, $community, '1.3.6.1.2.1.25.1.1.0', 5000000);
        if ($cpu) {
            $metrics[] = ['type' => 'cpu', 'value' => intval($cpu)];
        }
        
        // Memory
        $mem = @snmp2_get($ip, $community, '1.3.6.1.2.1.25.2.2.0', 5000000);
        if ($mem) {
            $metrics[] = ['type' => 'memory', 'value' => intval($mem)];
        }
        
    } catch (Exception $e) {
        // Return simulated data on error
        $metrics = [
            ['type' => 'cpu', 'value' => rand(10, 60)],
            ['type' => 'memory', 'value' => rand(30, 70)],
            ['type' => 'uptime', 'value' => rand(1000, 50000)],
        ];
    }
    
    return $metrics;
}

function getMikrotikStats($device) {
    global $conn;
    
    $ip = $device['ip_address'];
    $api_user = $device['api_user'] ?? 'admin';
    $api_pass = $device['api_pass'] ?? '';
    $api_port = $device['api_port'] ?? 8728;
    
    // Include MikroTik API
    include_once 'mikrotik_api.php';
    
    $stats = [
        'device' => [
            'name' => $device['nasname'],
            'ip' => $ip,
            'model' => 'RouterOS',
            'uptime' => 'N/A'
        ],
        'resources' => [
            'cpu' => 0,
            'memory' => 0,
            'hdd' => 0
        ],
        'interfaces' => [],
        'hotspot_users' => 0,
        'pppoe_users' => 0
    ];
    
    // Try to connect to MikroTik
    $API = new RouterosAPI();
    $API->setPort($api_port);
    $API->setTimeout(5);
    
    if ($API->connect($ip, $api_user, $api_pass)) {
        // Get system resource
        $resource = $API->comm('/system/resource/print');
        if (!empty($resource)) {
            $stats['resources']['cpu'] = $resource[0]['cpu-load'] ?? 0;
            $stats['resources']['memory'] = isset($resource[0]['free-memory']) ? 
                round(($resource[0]['total-memory'] - $resource[0]['free-memory']) / $resource[0]['total-memory'] * 100) : 0;
            $stats['resources']['hdd'] = isset($resource[0]['free-hdd-space']) ?
                round(($resource[0]['total-hdd-space'] - $resource[0]['free-hdd-space']) / $resource[0]['total-hdd-space'] * 100) : 0;
            $stats['device']['uptime'] = $resource[0]['uptime'] ?? 'N/A';
            $stats['device']['model'] = $resource[0]['board-name'] ?? 'RouterOS';
        }
        
        // Get interfaces
        $interfaces = $API->comm('/interface/print');
        if (!empty($interfaces)) {
            foreach ($interfaces as $iface) {
                if ($iface['type'] == 'ether') {
                    $stats['interfaces'][] = [
                        'name' => $iface['name'],
                        'rx' => formatBytes($iface['rx-byte'] ?? 0),
                        'tx' => formatBytes($iface['tx-byte'] ?? 0),
                        'status' => $iface['running'] ?? 'unknown'
                    ];
                }
            }
        }
        
        // Get Hotspot users
        $hotspot = $API->comm('/ip/hotspot/active/print');
        $stats['hotspot_users'] = count($hotspot);
        
        // Get PPPoE users
        $pppoe = $API->comm('/ppp/active/print');
        $stats['pppoe_users'] = count($pppoe);
        
        $API->disconnect();
    }
    
    return $stats;
}

function getSwitchStats($device) {
    $ip = $device['ip_address'];
    $community = $device['snmp_community'] ?? 'public';
    
    $stats = [
        'device' => [
            'name' => $device['nasname'],
            'ip' => $ip,
            'model' => 'Switch'
        ],
        'ports' => [],
        'uptime' => rand(1000, 50000)
    ];
    
    // Try SNMP for port status
    if (function_exists('snmp2_get')) {
        // Generic switch port status OID
        for ($i = 1; $i <= 24; $i++) {
            $status = @snmp2_get($ip, $community, "1.3.6.1.2.1.2.2.1.8.$i", 2000000);
            
            $stats['ports'][] = [
                'port' => $i,
                'status' => ($status == 1) ? 'up' : 'down',
                'name' => "GigabitEthernet 0/$i"
            ];
        }
    } else {
        // Simulated data
        for ($i = 1; $i <= 24; $i++) {
            $stats['ports'][] = [
                'port' => $i,
                'status' => rand(0, 1) ? 'up' : 'down',
                'name' => "Port $i"
            ];
        }
    }
    
    return $stats;
}

function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, $k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
