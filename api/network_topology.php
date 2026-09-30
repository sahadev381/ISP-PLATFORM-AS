<?php
/**
 * Network Topology API
 * Add/Update network devices
 */

header('Content-Type: application/json');
include_once '../config.php';
require_once __DIR__ . '/../includes/api_auth.php';
api_require_auth();


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
        if (db_one($conn, "SELECT id FROM nas WHERE ip_address = ?", [$ip_address])) {
            jsonResponse(false, 'Device with this IP already exists');
        }

        try {
            $newId = db_insert($conn, "INSERT INTO nas
                    (nasname, ip_address, device_type, model, snmp_community, api_user, api_pass, shortname)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$nasname, $ip_address, $device_type, $model, $snmp_community, $api_user, $api_pass, $nasname]);
            jsonResponse(true, 'Device added successfully', ['id' => $newId]);
        } catch (Throwable $e) {
            error_log('add_device failed: ' . $e->getMessage());
            jsonResponse(false, 'Error adding device');
        }
        break;
    
    case 'edit_device':
        $id = intval($_POST['device_id'] ?? 0);
        $nasname = $_POST['nasname'] ?? '';
        $ip_address = $_POST['ip_address'] ?? '';
        $device_type = $_POST['device_type'] ?? 'router';
        $model = $_POST['model'] ?? '';
        $location = $_POST['location'] ?? '';
        
        if (!$id || empty($nasname) || empty($ip_address)) {
            jsonResponse(false, 'Invalid parameters');
        }
        
        // Column names are hardcoded; only values are bound.
        $fields = ['nasname = ?', 'ip_address = ?', 'device_type = ?'];
        $params = [$nasname, $ip_address, $device_type];
        if (!empty($model)) {
            $fields[] = 'model = ?';
            $params[] = $model;
        }
        if (!empty($location)) {
            $fields[] = 'location = ?';
            $params[] = $location;
        }
        $params[] = $id;

        try {
            db_exec($conn, "UPDATE nas SET " . implode(', ', $fields) . " WHERE id = ?", $params);
            jsonResponse(true, 'Device updated successfully');
        } catch (Throwable $e) {
            error_log('edit_device failed: ' . $e->getMessage());
            jsonResponse(false, 'Error updating device');
        }
        break;
    
    case 'delete_device':
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(false, 'Invalid device ID');
        }
        
        try {
            db_exec($conn, "DELETE FROM nas WHERE id = ?", [$id]);
            jsonResponse(true, 'Device deleted');
        } catch (Throwable $e) {
            error_log('delete_device failed: ' . $e->getMessage());
            jsonResponse(false, 'Error deleting device');
        }
        break;
    
    case 'get_devices':
        $type = $_GET['type'] ?? '';
        
        $sql    = "SELECT * FROM nas WHERE 1=1";
        $params = [];
        if ($type) {
            $sql     .= " AND device_type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY device_type, nasname";

        jsonResponse(true, '', db_all($conn, $sql, $params));
        break;
    
    case 'check_status':
        $id = intval($_GET['id'] ?? 0);
        
        $device = db_one($conn, "SELECT * FROM nas WHERE id = ?", [$id]);
        
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
            cable_name VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_link (from_device_id, to_device_id)
        )");
        
        // cable_type is an ENUM column — validate against the allowed set
        $allowedCables = ['fiber', 'copper', 'wifi'];
        if (!in_array($cable_type, $allowedCables, true)) {
            $cable_type = 'copper';
        }

        // Check if connection already exists
        $check = db_one($conn, "SELECT id FROM network_topology_links
            WHERE (from_device_id = ? AND to_device_id = ?)
            OR (from_device_id = ? AND to_device_id = ?)", [$from_id, $to_id, $to_id, $from_id]);

        if ($check) {
            jsonResponse(false, 'Connection already exists');
        }

        try {
            db_exec($conn, "INSERT INTO network_topology_links (from_device_id, to_device_id, cable_type)
                    VALUES (?, ?, ?)", [$from_id, $to_id, $cable_type]);
            jsonResponse(true, 'Connection created');
        } catch (Throwable $e) {
            error_log('add_connection failed: ' . $e->getMessage());
            jsonResponse(false, 'Error creating connection');
        }
        break;
    
    case 'get_connections':
        jsonResponse(true, '', db_all($conn, "SELECT * FROM network_topology_links"));
        break;
    
    case 'delete_connection':
        $id = intval($_POST['id'] ?? 0);
        $from_id = intval($_POST['from_id'] ?? 0);
        $to_id = intval($_POST['to_id'] ?? 0);
        
        if ($id) {
            db_exec($conn, "DELETE FROM network_topology_links WHERE id = ?", [$id]);
        } elseif ($from_id && $to_id) {
            db_exec($conn, "DELETE FROM network_topology_links WHERE
                (from_device_id = ? AND to_device_id = ?)
                OR (from_device_id = ? AND to_device_id = ?)", [$from_id, $to_id, $to_id, $from_id]);
        }
        jsonResponse(true, 'Connection deleted');
        break;
    
    case 'update_connection':
        $from_id = intval($_POST['from_id'] ?? 0);
        $to_id = intval($_POST['to_id'] ?? 0);
        $cable_type = $_POST['cable_type'] ?? 'copper';
        $cable_name = $_POST['cable_name'] ?? '';
        
        if (!$from_id || !$to_id) {
            jsonResponse(false, 'Invalid parameters');
        }
        
        $allowedCables = ['fiber', 'copper', 'wifi'];
        if (!in_array($cable_type, $allowedCables, true)) {
            $cable_type = 'copper';
        }

        // Update the connection
        db_exec($conn, "UPDATE network_topology_links SET
            cable_type = ?,
            cable_name = ?
            WHERE (from_device_id = ? AND to_device_id = ?)
            OR (from_device_id = ? AND to_device_id = ?)",
            [$cable_type, $cable_name, $from_id, $to_id, $to_id, $from_id]);
        
        jsonResponse(true, 'Connection updated');
        break;
    
    case 'clear_connections':
        $conn->query("DELETE FROM network_topology_links");
        jsonResponse(true, 'All connections cleared');
        break;
    
    default:
        jsonResponse(false, 'Unknown action');
}
