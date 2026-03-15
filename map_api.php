<?php
include 'config.php';
include 'includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'add_node') {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $capacity = $_POST['capacity'] ?? 0;
    $metadata = $_POST['metadata'] ?? '{}';
    
    $stmt = $conn->prepare("INSERT INTO ftth_nodes (name, type, lat, lng, capacity, metadata) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddis", $name, $type, $lat, $lng, $capacity, $metadata);
    if($stmt->execute()) {
        $node_id = $conn->insert_id;
        if ($capacity > 0) {
            for ($i = 1; $i <= $capacity; $i++) {
                $conn->query("INSERT INTO port_assignments (node_id, port_number) VALUES ($node_id, $i)");
            }
        }
        echo json_encode(['status' => 'success', 'id' => $node_id]);
    }
}

if ($action == 'update_port') {
    $node_id = $_POST['node_id'];
    $port_no = $_POST['port_number'];
    $user    = $_POST['username'] ?? '';
    $linked_node = !empty($_POST['linked_node_id']) ? $_POST['linked_node_id'] : null;
    $in_color = $_POST['in_color'] ?? null;
    $out_color = $_POST['out_color'] ?? null;
    $port_name = $_POST['port_name'] ?? null;
    $status = (!empty($user) || !empty($linked_node)) ? 'occupied' : 'empty';
    $stmt = $conn->prepare("UPDATE port_assignments SET customer_username = ?, linked_node_id = ?, in_color = ?, out_color = ?, port_name = ?, status = ? WHERE node_id = ? AND port_number = ?");
    $stmt->bind_param("sissssii", $user, $linked_node, $in_color, $out_color, $port_name, $status, $node_id, $port_no);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
}

if ($action == 'get_node_details') {
    $id = $_GET['id'];
    $node = $conn->query("SELECT * FROM ftth_nodes WHERE id = $id")->fetch_assoc();
    $ports = $conn->query("SELECT p.*, n.name as linked_node_name FROM port_assignments p LEFT JOIN ftth_nodes n ON p.linked_node_id = n.id WHERE p.node_id = $id")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['node' => $node, 'ports' => $ports]);
}

if ($action == 'save_route') {
    $name = $_POST['name'];
    $path = $_POST['path'];
    $length = $_POST['length'] ?? 0;
    $loss = $_POST['loss'] ?? 0;
    $cores = $_POST['total_cores'] ?? 4;
    $stmt = $conn->prepare("INSERT INTO fiber_routes (name, path_data, calculated_length_m, predicted_loss_db, total_cores) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddi", $name, $path, $length, $loss, $cores);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
}

if ($action == 'predict_fault') {
    $route_id = $_POST['route_id'];
    $distance = (float)$_POST['distance'];
    $route = $conn->query("SELECT * FROM fiber_routes WHERE id = $route_id")->fetch_assoc();
    if (!$route) die(json_encode(['status' => 'error']));
    $path = json_decode($route['path_data'], true);
    $current_dist = 0; $predicted_point = null;
    for ($i = 0; $i < count($path) - 1; $i++) {
        $p1 = $path[$i]; $p2 = $path[$i+1];
        $d = haversineDistance($p1['lat'], $p1['lng'], $p2['lat'], $p2['lng']);
        if ($current_dist + $d >= $distance) {
            $ratio = ($distance - $current_dist) / $d;
            $predicted_point = [ 'lat' => $p1['lat'] + ($p2['lat'] - $p1['lat']) * $ratio, 'lng' => $p1['lng'] + ($p2['lng'] - $p1['lng']) * $ratio ];
            break;
        }
        $current_dist += $d;
    }
    if (!$predicted_point) $predicted_point = end($path);
    $stmt = $conn->prepare("INSERT INTO network_faults (fault_type, predicted_lat, predicted_lng, severity, description) VALUES ('FIBER_BREAK', ?, ?, 'CRITICAL', ?)");
    $desc = "Predicted break at {$distance}m on route: " . $route['name'];
    $stmt->bind_param("dds", $predicted_point['lat'], $predicted_point['lng'], $desc);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'point' => $predicted_point]);
}

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1); $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    return $earth_radius * 2 * atan2(sqrt($a), sqrt(1-$a));
}

if ($action == 'get_data') {
    $nodes = $conn->query("SELECT * FROM ftth_nodes")->fetch_all(MYSQLI_ASSOC);
    $routes = $conn->query("SELECT * FROM fiber_routes")->fetch_all(MYSQLI_ASSOC);
    $customers = $conn->query("SELECT username, full_name, lat, lng, status, blocked, expiry FROM customers WHERE lat IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
    $faults = $conn->query("SELECT * FROM network_faults WHERE is_resolved = 0")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['nodes' => $nodes, 'routes' => $routes, 'customers' => $customers, 'faults' => $faults]);
}

// ... other actions remain same ...
if ($action == 'update_node_pos') {
    $id = $_POST['id']; $lat = $_POST['lat']; $lng = $_POST['lng'];
    $stmt = $conn->prepare("UPDATE ftth_nodes SET lat = ?, lng = ? WHERE id = ?");
    $stmt->bind_param("ddi", $lat, $lng, $id); $stmt->execute();
    echo json_encode(['status' => 'success']);
}
if ($action == 'delete_node') {
    $id = $_POST['id']; $conn->query("DELETE FROM ftth_nodes WHERE id = $id"); echo json_encode(['status' => 'success']);
}
?>
