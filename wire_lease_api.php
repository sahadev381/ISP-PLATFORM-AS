<?php
include 'config.php';
include 'includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'list') {
    $res = $conn->query("
        SELECT l.*, r.name as route_name 
        FROM wire_leases l 
        JOIN fiber_routes r ON l.route_id = r.id 
        ORDER BY l.created_at DESC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

if ($action == 'add') {
    $route_id = $_POST['route_id'];
    $client = $_POST['client_name'];
    $core = $_POST['core_number'];
    $start = $_POST['lease_start'];
    $price = $_POST['monthly_price'];
    
    // Check if core is already leased
    $check = $conn->query("SELECT id FROM wire_leases WHERE route_id = $route_id AND core_number = $core AND status = 'Active'");
    if ($check->num_rows > 0) {
        die(json_encode(['status' => 'error', 'message' => 'Core already leased!']));
    }
    
    // Check total cores
    $r = $conn->query("SELECT total_cores, used_cores FROM fiber_routes WHERE id = $route_id")->fetch_assoc();
    if ($core > $r['total_cores']) {
        die(json_encode(['status' => 'error', 'message' => 'Core number exceeds route capacity!']));
    }

    $stmt = $conn->prepare("INSERT INTO wire_leases (route_id, client_name, core_number, lease_start, monthly_price) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssd", $route_id, $client, $core, $start, $price);
    
    if ($stmt->execute()) {
        // Update used cores count
        $conn->query("UPDATE fiber_routes SET used_cores = used_cores + 1 WHERE id = $route_id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}

if ($action == 'terminate') {
    $id = $_POST['id'];
    $l = $conn->query("SELECT route_id FROM wire_leases WHERE id = $id")->fetch_assoc();
    
    $conn->query("UPDATE wire_leases SET status = 'Terminated', lease_end = NOW() WHERE id = $id");
    $conn->query("UPDATE fiber_routes SET used_cores = GREATEST(used_cores - 1, 0) WHERE id = {$l['route_id']}");
    
    echo json_encode(['status' => 'success']);
}
?>
