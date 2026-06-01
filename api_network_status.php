<?php
include 'config.php';
include 'includes/auth.php';
header('Content-Type: application/json');

function ping($host) {
    // Standard ICMP ping (works on Linux)
    $output = []; $result = 0;
    exec("ping -c 1 -W 1 " . escapeshellarg($host), $output, $result);
    return ($result === 0);
}

$devices = $conn->query("SELECT id, nasname, ip_address, device_type FROM nas");
$results = [];

while($d = $devices->fetch_assoc()) {
    $start_time = microtime(true);
    $is_online = ping($d['ip_address']);
    $latency = round((microtime(true) - $start_time) * 1000, 2);
    $status_val = $is_online ? 1 : 0;
    
    // Update DB status column silently
    $upd = $conn->prepare("UPDATE nas SET status = ? WHERE id = ?");
    $upd->bind_param("ii", $status_val, $d['id']);
    $upd->execute();

    // Log for 99.9% Uptime Tracking
    $status_str = $is_online ? 'online' : 'offline';
    $target_type = strtoupper($d['device_type'] ?: 'UNKNOWN');
    $stmt = $conn->prepare("INSERT INTO uptime_logs (target_type, target_id, status, latency_ms, checked_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sisd", $target_type, $d['id'], $status_str, $latency);
    $stmt->execute();
    
    $results[] = [
        'id' => $d['id'],
        'name' => $d['nasname'],
        'ip' => $d['ip_address'],
        'status' => $is_online,
        'latency' => $latency
    ];
}

echo json_encode($results);
?>
