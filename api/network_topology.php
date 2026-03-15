<?php
/**
 * Network Topology API
 * Add/Update network devices
 */

header('Content-Type: application/json');
include_once '../config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

switch ($action) {
    
    case 'add_device':
        $nasname = $_POST['nasname'] ?? '';
        $ip_address = $_POST['ip_address'] ?? '';
        $device_type = $_POST['device_type'] ?? 'router';
        $model = $_POST['model'] ?? '';
        $snmp_community = $_POST['snmp_community'] ?? 'public';
        $api_user = $_POST['api_user'] ?? 'admin';
        $api_pass = $_POST['api_pass'] ?? '';
        
        if (empty($nasname) || empty($ip_address)) {
            jsonResponse(false, 'Name and IP address are required');
        }
        
        // Check if device already exists
        $existing = $conn->query("SELECT id FROM nas WHERE ip_address = '$ip_address'");
        if ($existing->num_rows > 0) {
            jsonResponse(false, 'Device with this IP already exists');
        }
        
        $sql = "INSERT INTO nas (nasname, ip_address, device_type, model, snmp_community, api_user, api_pass, shortname)
                VALUES ('$nasname', '$ip_address', '$device_type', '$model', '$snmp_community', '$api_user', '$api_pass', '$nasname')";
        
        if ($conn->query($sql)) {
            jsonResponse(true, 'Device added successfully', ['id' => $conn->insert_id]);
        } else {
            jsonResponse(false, 'Error adding device: ' . $conn->error);
        }
        break;
    
    case 'update_device':
        $id = intval($_POST['id'] ?? 0);
        $nasname = $_POST['nasname'] ?? '';
        $ip_address = $_POST['ip_address'] ?? '';
        
        if (!$id || empty($nasname) || empty($ip_address)) {
            jsonResponse(false, 'Invalid parameters');
        }
        
        $fields = [];
        if (!empty($nasname)) $fields[] = "nasname = '$nasname'";
        if (!empty($ip_address)) $fields[] = "ip_address = '$ip_address'";
        if (isset($_POST['model'])) $fields[] = "model = '" . $_POST['model'] . "'";
        if (isset($_POST['snmp_community'])) $fields[] = "snmp_community = '" . $_POST['snmp_community'] . "'";
        if (isset($_POST['api_user'])) $fields[] = "api_user = '" . $_POST['api_user'] . "'";
        if (isset($_POST['api_pass'])) $fields[] = "api_pass = '" . $_POST['api_pass'] . "'";
        
        if (empty($fields)) {
            jsonResponse(false, 'No fields to update');
        }
        
        $sql = "UPDATE nas SET " . implode(', ', $fields) . " WHERE id = $id";
        
        if ($conn->query($sql)) {
            jsonResponse(true, 'Device updated successfully');
        } else {
            jsonResponse(false, 'Error updating device');
        }
        break;
    
    case 'delete_device':
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(false, 'Invalid device ID');
        }
        
        if ($conn->query("DELETE FROM nas WHERE id = $id")) {
            jsonResponse(true, 'Device deleted');
        } else {
            jsonResponse(false, 'Error deleting device');
        }
        break;
    
    case 'get_devices':
        $type = $_GET['type'] ?? '';
        
        $sql = "SELECT * FROM nas WHERE 1=1";
        if ($type) {
            $sql .= " AND device_type = '$type'";
        }
        $sql .= " ORDER BY device_type, nasname";
        
        $devices = $conn->query($sql);
        $data = [];
        while ($d = $devices->fetch_assoc()) {
            $data[] = $d;
        }
        
        jsonResponse(true, '', $data);
        break;
    
    case 'check_status':
        $id = intval($_GET['id'] ?? 0);
        
        $device = $conn->query("SELECT * FROM nas WHERE id = $id")->fetch_assoc();
        
        if (!$device) {
            jsonResponse(false, 'Device not found');
        }
        
        // Simple ping check
        $ip = $device['ip_address'];
        $output = [];
        $returnVar = 0;
        
        // Try to ping
        exec("ping -c 1 -W 2 " . escapeshellarg($ip) . " 2>&1", $output, $returnVar);
        
        $online = ($returnVar === 0);
        
        jsonResponse(true, '', [
            'id' => $id,
            'online' => $online,
            'ip' => $ip
        ]);
        break;
    
    case 'add_connection':
        $from_id = intval($_POST['from_id'] ?? 0);
        $to_id = intval($_POST['to_id'] ?? 0);
        $cable_type = $_POST['cable_type'] ?? 'copper';
        
        if (!$from_id || !$to_id) {
            jsonResponse(false, 'Invalid device IDs');
        }
        
        // Create table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS network_topology_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_device_id INT NOT NULL,
            to_device_id INT NOT NULL,
            cable_type ENUM('fiber','copper','wifi') DEFAULT 'copper',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_link (from_device_id, to_device_id)
        )");
        
        // Check if connection already exists
        $check = $conn->query("SELECT id FROM network_topology_links 
            WHERE (from_device_id = $from_id AND to_device_id = $to_id) 
            OR (from_device_id = $to_id AND to_device_id = $from_id)");
        
        if ($check->num_rows > 0) {
            jsonResponse(false, 'Connection already exists');
        }
        
        $sql = "INSERT INTO network_topology_links (from_device_id, to_device_id, cable_type) 
                VALUES ($from_id, $to_id, '$cable_type')";
        
        if ($conn->query($sql)) {
            jsonResponse(true, 'Connection created');
        } else {
            jsonResponse(false, 'Error: ' . $conn->error);
        }
        break;
    
    case 'get_connections':
        $connections = $conn->query("SELECT * FROM network_topology_links");
        $data = [];
        while ($c = $connections->fetch_assoc()) {
            $data[] = $c;
        }
        jsonResponse(true, '', $data);
        break;
    
    case 'delete_connection':
        $id = intval($_POST['id'] ?? 0);
        if ($conn->query("DELETE FROM network_topology_links WHERE id = $id")) {
            jsonResponse(true, 'Connection deleted');
        } else {
            jsonResponse(false, 'Error deleting connection');
        }
        break;
    
    case 'clear_connections':
        $conn->query("DELETE FROM network_topology_links");
        jsonResponse(true, 'All connections cleared');
        break;
    
    default:
        jsonResponse(false, 'Unknown action');
}
