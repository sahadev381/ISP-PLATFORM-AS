<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/olt_api.php';
require_once __DIR__ . '/includes/csrf.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

if ($action == 'search_customer') {
    $q = $_GET['q'] ?? '';
    // Escape the LIKE wildcards so a query of "%" cannot dump every customer.
    $like = db_like($q);
    $rows = db_all($conn, "SELECT id, username, full_name FROM customers WHERE (username LIKE ? OR full_name LIKE ?) AND (onu_serial IS NULL OR onu_serial = '') LIMIT 10", [$like, $like]);
    echo json_encode($rows);
}

if ($action == 'provision') {
    $olt_id = (int) ($_POST['olt_id'] ?? 0);
    $customer_id = (int) ($_POST['customer_id'] ?? 0);
    $sn = (string) ($_POST['sn'] ?? '');
    $port = (int) ($_POST['port'] ?? 0);
    $vlan = (int) ($_POST['vlan'] ?? 100);

    // Get OLT data
    $olt_data = db_one($conn, "SELECT * FROM nas WHERE id = ?", [$olt_id]);
    
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
    $olt_id = (int) ($_POST['olt_id'] ?? 0);
    $sn = (string) ($_POST['sn'] ?? '');
    $olt_data = db_one($conn, "SELECT * FROM nas WHERE id = ?", [$olt_id]);
    if (!$olt_data) die(json_encode(['status' => 'error', 'message' => 'OLT not found']));
    $driver = new OLT_Driver($olt_data);
    if($driver->rebootONT($sn)) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => 'Reboot failed']);
}

if ($action == 'delete') {
    $olt_id = (int) ($_POST['olt_id'] ?? 0);
    $sn = (string) ($_POST['sn'] ?? '');
    $olt_data = db_one($conn, "SELECT * FROM nas WHERE id = ?", [$olt_id]);
    if (!$olt_data) die(json_encode(['status' => 'error', 'message' => 'OLT not found']));
    $driver = new OLT_Driver($olt_data);
    if($driver->deleteONT($sn)) {
        db_exec($conn, "UPDATE customers SET onu_serial = NULL, onu_mac = NULL, olt_port = 0 WHERE onu_serial = ? OR onu_mac = ?", [$sn, $sn]);
        echo json_encode(['status' => 'success']);
    }
    else echo json_encode(['status' => 'error', 'message' => 'Delete failed']);
}

if ($action == 'get_power') {
    $olt_id = (int) ($_GET['olt_id'] ?? 0);
    $sn = (string) ($_GET['sn'] ?? '');
    $olt_data = db_one($conn, "SELECT * FROM nas WHERE id = ?", [$olt_id]);
    if (!$olt_data) die(json_encode(['status' => 'error', 'message' => 'OLT not found']));
    $driver = new OLT_Driver($olt_data);
    $power = $driver->getONUPower($sn);
    echo json_encode(['status' => 'success', 'power' => $power]);
}
?>
