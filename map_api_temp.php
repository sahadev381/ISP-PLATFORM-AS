<?php
include 'config.php';
include 'includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ... Keep existing actions (add_node, update_port, get_data, etc.) ...

if ($action == 'predict_fault') {
    $route_id = $_POST['route_id'];
    $distance = (float)$_POST['distance']; // in meters
    
    $route = $conn->query("SELECT * FROM fiber_routes WHERE id = $route_id")->fetch_assoc();
    if (!$route) die(json_encode(['status' => 'error']));
    
    $path = json_decode($route['path_data'], true);
    
    // Logic: Iterate through points, calculate distance between them, find where $distance falls.
    $current_dist = 0;
    $predicted_point = null;
    
    for ($i = 0; $i < count($path) - 1; $i++) {
        $p1 = $path[$i];
        $p2 = $path[$i+1];
        
        $d = haversineDistance($p1['lat'], $p1['lng'], $p2['lat'], $p2['lng']);
        
        if ($current_dist + $d >= $distance) {
            // The break is between p1 and p2
            $ratio = ($distance - $current_dist) / $d;
            $predicted_point = [
                'lat' => $p1['lat'] + ($p2['lat'] - $p1['lat']) * $ratio,
                'lng' => $p1['lng'] + ($p2['lng'] - $p1['lng']) * $ratio
            ];
            break;
        }
        $current_dist += $d;
    }
    
    if (!$predicted_point) $predicted_point = end($path); // Fallback to end of line

    // Log the fault
    $stmt = $conn->prepare("INSERT INTO network_faults (fault_type, predicted_lat, predicted_lng, severity, description) VALUES ('FIBER_BREAK', ?, ?, 'CRITICAL', ?)");
    $desc = "Predicted break at {$distance}m on route: " . $route['name'];
    $stmt->bind_param("dds", $predicted_point['lat'], $predicted_point['lng'], $desc);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'point' => $predicted_point, 'id' => $conn->insert_id]);
}

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

// ... Rest of map_api.php ...
// (I will merge this into the existing file)
?>
