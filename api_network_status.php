<?php
include 'config.php';
header('Content-Type: application/json');

function ping($host) {
    // Standard ICMP ping (works on Linux)
    $output = []; $result = 0;
    exec("ping -c 1 -W 1 " . escapeshellarg($host), $output, $result);
    return ($result === 0);
}

$devices = $conn->query("SELECT id, nasname, ip_address FROM nas");
$results = [];

while($d = $devices->fetch_assoc()) {
    $is_online = ping($d['ip_address']);
    $status_val = $is_online ? 1 : 0;
    
    // Update DB status column silently
    $conn->query("UPDATE nas SET status = $status_val WHERE id = " . $d['id']);
    
    $results[] = [
        'id' => $d['id'],
        'name' => $d['nasname'],
        'ip' => $d['ip_address'],
        'status' => $is_online
    ];
}

echo json_encode($results);
?>
