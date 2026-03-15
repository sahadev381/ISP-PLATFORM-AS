<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/olt_api.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'search_customer') {
    $q = $_GET['q'] ?? '';
    $res = $conn->query("SELECT id, username, full_name FROM customers WHERE (username LIKE '%$q%' OR full_name LIKE '%$q%') AND (onu_serial IS NULL OR onu_serial = '') LIMIT 10");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

if ($action == 'provision') {
    $olt_id = $_POST['olt_id'];
    $customer_id = $_POST['customer_id'];
    $sn = $_POST['sn'];
    $port = $_POST['port'];
    $vlan = $_POST['vlan'] ?? 100;
    
    // Get OLT data
    $olt_res = $conn->query("SELECT * FROM nas WHERE id = $olt_id");
    $olt_data = $olt_res->fetch_assoc();
    
    if (!$olt_data) die(json_encode(['status' => 'error', 'message' => 'OLT not found']));
    
    $driver = new OLT_Driver($olt_data);
    
    // 1. Provision on OLT
    $success = $driver->authorizeONT($sn, $port, $vlan, 'Standard');
    
    if ($success) {
        // 2. Update Customer Database
        $stmt = $conn->prepare("UPDATE customers SET onu_serial = ?, onu_mac = ?, olt = ?, olt_port = ? WHERE id = ?");
        $stmt->bind_param("sssii", $sn, $sn, $olt_data['nasname'], $port, $customer_id);
        $stmt->execute();
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'OLT Authorization Failed']);
    }
}

if ($action == 'reboot') {
    $olt_id = $_POST['olt_id'];
    $sn = $_POST['sn'];
    $olt_res = $conn->query("SELECT * FROM nas WHERE id = $olt_id");
    $olt_data = $olt_res->fetch_assoc();
    $driver = new OLT_Driver($olt_data);
    if($driver->rebootONT($sn)) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => 'Reboot failed']);
}

if ($action == 'delete') {
    $olt_id = $_POST['olt_id'];
    $sn = $_POST['sn'];
    $olt_res = $conn->query("SELECT * FROM nas WHERE id = $olt_id");
    $olt_data = $olt_res->fetch_assoc();
    $driver = new OLT_Driver($olt_data);
    if($driver->deleteONT($sn)) {
        $conn->query("UPDATE customers SET onu_serial = NULL, onu_mac = NULL, olt_port = 0 WHERE onu_serial = '$sn' OR onu_mac = '$sn'");
        echo json_encode(['status' => 'success']);
    }
    else echo json_encode(['status' => 'error', 'message' => 'Delete failed']);
}

if ($action == 'get_power') {
    $olt_id = $_GET['olt_id'];
    $sn = $_GET['sn'];
    $olt_res = $conn->query("SELECT * FROM nas WHERE id = $olt_id");
    $olt_data = $olt_res->fetch_assoc();
    $driver = new OLT_Driver($olt_data);
    $power = $driver->getONUPower($sn);
    echo json_encode(['status' => 'success', 'power' => $power]);
}
?>
