<?php
include 'config.php';
header('Content-Type: application/json');

function ping($host) {
    // Standard ICMP ping (works on Linux)
    $output = []; $result = 0;
    exec("ping -c 1 -W 1 " . escapeshellarg($host), $output, $result);
    return ($result === 0);
}

$devices = $conn->query("SELECT id, nasname, ip_address, device_type FROM nas");
$results = [];
$status_updates = [];
$log_entries = [];

while($d = $devices->fetch_assoc()) {
    $start_time = microtime(true);
    $is_online = ping($d['ip_address']);
    $latency = round((microtime(true) - $start_time) * 1000, 2);
    $status_val = $is_online ? 1 : 0;
    
    $status_updates[$d['id']] = $status_val;
    $log_entries[] = [
        'type' => strtoupper($d['device_type'] ?: 'UNKNOWN'),
        'id' => $d['id'],
        'status' => $is_online ? 'online' : 'offline',
        'latency' => $latency
    ];

    $results[] = [
        'id' => $d['id'],
        'name' => $d['nasname'],
        'ip' => $d['ip_address'],
        'status' => $is_online,
        'latency' => $latency
    ];
}

// Batch update NAS status
if (!empty($status_updates)) {
    $ids = implode(',', array_keys($status_updates));
    $cases = "";
    foreach ($status_updates as $id => $val) {
        $cases .= "WHEN id = " . (int)$id . " THEN " . (int)$val . " ";
    }
    $conn->query("UPDATE nas SET status = CASE $cases END WHERE id IN ($ids)");
}

// Batch insert logs
if (!empty($log_entries)) {
    $values = [];
    foreach ($log_entries as $log) {
        $values[] = sprintf(
            "('%s', %d, '%s', %f, NOW())",
            $conn->real_escape_string($log['type']),
            (int)$log['id'],
            $conn->real_escape_string($log['status']),
            (float)$log['latency']
        );
    }
    $conn->query("INSERT INTO uptime_logs (target_type, target_id, status, latency_ms, checked_at) VALUES " . implode(',', $values));
}

echo json_encode($results);
?>
