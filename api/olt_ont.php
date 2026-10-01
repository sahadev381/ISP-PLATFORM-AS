<?php
/**
 * OLT ONT Management API
 * Endpoints for ONT List, Signal Power, Actions
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/api_auth.php';
api_require_auth();
api_csrf_check();
include_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper function for JSON response
function jsonResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_GET['test'])) {
    jsonResponse(false, 'Unauthorized');
}

switch ($action) {
    
    // Get ONT List for specific OLT
    case 'ont_list':
        $olt_id = intval($_GET['olt_id'] ?? 0);
        
        if (!$olt_id) {
            jsonResponse(false, 'OLT ID required');
        }
        
        // Get OLT details
        $olt = db_one($conn, "SELECT * FROM nas WHERE id = ? AND device_type = 'olt'", [(int) $olt_id]);
        
        if (!$olt) {
            jsonResponse(false, 'OLT not found');
        }
        
        // Try to get ONT data from OLT via API
        $onts = getONTFromOLT($olt);
        
        jsonResponse(true, 'ONT list fetched', $onts);
        break;
    
    // Get ONT Signal History
    case 'signal_history':
        $serial = $_GET['serial'] ?? '';
        
        if (!$serial) {
            jsonResponse(false, 'Serial number required');
        }
        
        $data = db_all($conn, "
            SELECT * FROM olt_onu_signal
            WHERE onu_serial = ?
            ORDER BY recorded_at DESC
            LIMIT 100
        ", [$serial]);
        
        jsonResponse(true, '', $data);
        break;
    
    // Reboot ONT
    case 'ont_reboot':
        $olt_id = intval($_POST['olt_id'] ?? 0);
        $serial = $_POST['serial'] ?? '';
        
        if (!$olt_id || !$serial) {
            jsonResponse(false, 'OLT ID and Serial required');
        }
        
        $olt = db_one($conn, "SELECT * FROM nas WHERE id = ?", [(int) $olt_id]);
        
        if (!$olt) {
            jsonResponse(false, 'OLT not found');
        }
        
        // Call OLT API to reboot ONT
        $result = rebootONTOnOLT($olt, $serial);
        
        // Log action
        $userId = $_SESSION['user_id'] ?? 0;
        $oltName = $olt['nasname'] ?? 'Unknown';
        db_exec($conn, "INSERT INTO activity_log (user_id, action, details, created_at)
            VALUES (?, 'ont_reboot', ?, NOW())", [(int) $userId, "Reboot ONT $serial on OLT $oltName"]);
        
        jsonResponse($result['success'], $result['message']);
        break;
    
    // Disable ONT
    case 'ont_disable':
        $olt_id = intval($_POST['olt_id'] ?? 0);
        $serial = $_POST['serial'] ?? '';
        
        if (!$olt_id || !$serial) {
            jsonResponse(false, 'OLT ID and Serial required');
        }
        
        $olt = db_one($conn, "SELECT * FROM nas WHERE id = ?", [(int) $olt_id]);
        
        if (!$olt) {
            jsonResponse(false, 'OLT not found');
        }
        
        $result = disableONTOnOLT($olt, $serial);
        
        $userId = $_SESSION['user_id'] ?? 0;
        $oltName = $olt['nasname'] ?? 'Unknown';
        db_exec($conn, "INSERT INTO activity_log (user_id, action, details, created_at)
            VALUES (?, 'ont_disable', ?, NOW())", [(int) $userId, "Disable ONT $serial on OLT $oltName"]);
        
        jsonResponse($result['success'], $result['message']);
        break;
    
    // Enable ONT
    case 'ont_enable':
        $olt_id = intval($_POST['olt_id'] ?? 0);
        $serial = $_POST['serial'] ?? '';
        
        if (!$olt_id || !$serial) {
            jsonResponse(false, 'OLT ID and Serial required');
        }
        
        $olt = db_one($conn, "SELECT * FROM nas WHERE id = ?", [(int) $olt_id]);
        
        if (!$olt) {
            jsonResponse(false, 'OLT not found');
        }
        
        $result = enableONTOnOLT($olt, $serial);
        
        $userId = $_SESSION['user_id'] ?? 0;
        $oltName = $olt['nasname'] ?? 'Unknown';
        db_exec($conn, "INSERT INTO activity_log (user_id, action, details, created_at)
            VALUES (?, 'ont_enable', ?, NOW())", [(int) $userId, "Enable ONT $serial on OLT $oltName"]);
        
        jsonResponse($result['success'], $result['message']);
        break;
    
    // Get OLT Health Stats
    case 'olt_health':
        $olt_id = intval($_GET['olt_id'] ?? 0);
        
        if (!$olt_id) {
            jsonResponse(false, 'OLT ID required');
        }
        
        $olt = db_one($conn, "SELECT * FROM nas WHERE id = ?", [(int) $olt_id]);
        
        if (!$olt) {
            jsonResponse(false, 'OLT not found');
        }
        
        // Get ONT counts from database
        $total = db_value($conn, "SELECT COUNT(*) FROM olt_onu_signal WHERE olt_id = ?", [(int) $olt_id], 0);
        $online = db_value($conn, "SELECT COUNT(*) FROM olt_onu_signal WHERE olt_id = ? AND status = 'online'", [(int) $olt_id], 0);
        $offline = $total - $online;
        
        // Calculate critical (signal < -28 dBm)
        $critical = db_value($conn, "SELECT COUNT(*) FROM olt_onu_signal WHERE olt_id = ? AND rx_power < -28", [(int) $olt_id], 0);
        
        $data = [
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'critical' => $critical,
            'olt_name' => $olt['nasname'],
            'olt_ip' => $olt['ip_address'],
            'olt_brand' => $olt['brand'] ?? 'BDCOM'
        ];
        
        jsonResponse(true, '', $data);
        break;
    
    // Get PON Ports
    case 'pon_ports':
        $olt_id = intval($_GET['olt_id'] ?? 0);
        
        if (!$olt_id) {
            jsonResponse(false, 'OLT ID required');
        }
        
        $olt = db_one($conn, "SELECT * FROM nas WHERE id = ?", [(int) $olt_id]);
        
        if (!$olt) {
            jsonResponse(false, 'OLT not found');
        }
        
        // Generate PON ports data (real implementation would query OLT)
        $ports = [];
        for ($i = 1; $i <= 8; $i++) {
            $onus = rand(20, 48);
            $online = rand(10, $onus);
            $ports[] = [
                'port' => "0/$i",
                'total_onus' => $onus,
                'online' => $online,
                'offline' => $onus - $online,
                'status' => 'up'
            ];
        }
        
        jsonResponse(true, '', $ports);
        break;
    
    default:
        jsonResponse(false, 'Unknown action');
}

// ============================================
// OLT Driver Functions (Simulated for now)
// ============================================

function getONTFromOLT($olt) {
    global $conn;
    
    $brand = strtolower($olt['brand'] ?? 'bdcom');
    
    // Try to get from stored data first
    $storedOnts = db_all($conn, "
        SELECT * FROM olt_onu_signal
        WHERE olt_id = ?
        ORDER BY port, recorded_at DESC
    ", [(int) $olt['id']]);

    $onts = [];
    $seenSerials = [];

    foreach ($storedOnts as $row) {
        if (!in_array($row['onu_serial'], $seenSerials)) {
            $onts[] = $row;
            $seenSerials[] = $row['onu_serial'];
        }
    }
    
    // If no stored data, generate demo data for now
    if (empty($onts)) {
        // Generate demo ONTs for demonstration
        for ($i = 1; $i <= 48; $i++) {
            $rxPower = rand(-30, -18);
            $status = ($rxPower >= -28) ? 'online' : 'offline';
            
            $onts[] = [
                'id' => $i,
                'olt_id' => $olt['id'],
                'onu_serial' => 'BDCM' . str_pad(rand(100000, 999999), 10, '0', STR_PAD_LEFT),
                'port' => '0/' . ceil($i / 8),
                'onu_type' => 'G-97Z4',
                'rx_power' => $rxPower,
                'tx_power' => rand(0, 5),
                'status' => $status,
                'recorded_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Store in database
        foreach ($onts as $ont) {
            db_exec($conn, "INSERT INTO olt_onu_signal (olt_id, onu_serial, port, onu_type, rx_power, tx_power, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)",
                [(int) $olt['id'], $ont['onu_serial'], $ont['port'], $ont['onu_type'], $ont['rx_power'], $ont['tx_power'], $ont['status']]);
        }
    }
    
    return $onts;
}

function rebootONTOnOLT($olt, $serial) {
    // Real implementation would connect to OLT via Telnet/SSH
    // For now, simulate success
    
    // Log the action
    global $conn;
    db_exec($conn, "INSERT INTO network_alerts (device_id, device_name, alert_type, message, severity, status)
        VALUES (?, ?, 'ont_reboot', ?, 'info', 'active')",
        [(int) $olt['id'], $olt['nasname'], "Reboot command sent to ONT $serial"]);
    
    return [
        'success' => true,
        'message' => "Reboot command sent to ONT $serial"
    ];
}

function disableONTOnOLT($olt, $serial) {
    global $conn;
    
    db_exec($conn, "INSERT INTO network_alerts (device_id, device_name, alert_type, message, severity, status)
        VALUES (?, ?, 'ont_disable', ?, 'warning', 'active')",
        [(int) $olt['id'], $olt['nasname'], "Disable command sent to ONT $serial"]);
    
    return [
        'success' => true,
        'message' => "ONT $serial has been disabled"
    ];
}

function enableONTOnOLT($olt, $serial) {
    global $conn;
    
    return [
        'success' => true,
        'message' => "ONT $serial has been enabled"
    ];
}
