<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/olt_api.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$username = $_GET['username'] ?? '';

if (!$username) die(json_encode(['status' => 'error', 'message' => 'Username required']));

if ($action == 'refresh') {
    // Get user OLT info
    $u_res = $conn->query("SELECT olt, onu_mac FROM customers WHERE username = '$username'");
    $u_data = $u_res->fetch_assoc();
    
    if (!$u_data || !$u_data['onu_mac']) {
        die(json_encode(['status' => 'error', 'message' => 'ONU MAC/Serial not found for this user']));
    }

    // Initialize OLT Driver (In real system, fetch OLT IP/Auth from DB)
    $olt_data = ['ip_address' => '10.0.0.1', 'brand' => 'Huawei']; 
    $olt = new OLT_Driver($olt_data);
    
    $power = $olt->getONUPower($u_data['onu_mac']);
    
    // Save to history
    $stmt = $conn->prepare("INSERT INTO onu_power_history (username, rx_power, tx_power) VALUES (?, ?, ?)");
    $stmt->bind_param("sdd", $username, $power['rx'], $power['tx']);
    $stmt->execute();
    
    echo json_encode(['status' => 'success', 'power' => $power]);
}

if ($action == 'get_history') {
    $res = $conn->query("SELECT rx_power, tx_power, timestamp FROM onu_power_history WHERE username = '$username' ORDER BY timestamp DESC LIMIT 20");
    $history = $res->fetch_all(MYSQLI_ASSOC);
    echo json_encode(array_reverse($history));
}
?>
