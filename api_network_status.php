<?php
include 'config.php';
header('Content-Type: application/json');

$devices_query = $conn->query("SELECT id, nasname, ip_address, device_type FROM nas");
$devices = [];
$ips = [];

while($d = $devices_query->fetch_assoc()) {
    $devices[] = $d;
    $ips[] = $d['ip_address'];
}

$results_map = [];
if (!empty($ips)) {
    // Run nmap in parallel to check all IPs at once
    $ips_string = implode(' ', array_map('escapeshellarg', array_unique($ips)));
    $xml_output = shell_exec("nmap -sn -n -oX - $ips_string");

    if ($xml_output) {
        $xml = simplexml_load_string($xml_output);
        if ($xml) {
            foreach ($xml->host as $host) {
                $ip = (string)$host->address['addr'];
                $status = (string)$host->status['state'];
                $latency = 0;
                if (isset($host->times)) {
                    // srtt is in microseconds
                    $latency = round((int)$host->times['srtt'] / 1000, 2);
                }
                $results_map[$ip] = [
                    'online' => ($status === 'up'),
                    'latency' => $latency
                ];
            }
        }
    }
}

$results = [];
foreach ($devices as $d) {
    $ip = $d['ip_address'];
    $is_online = isset($results_map[$ip]) ? $results_map[$ip]['online'] : false;
    $latency = isset($results_map[$ip]) ? $results_map[$ip]['latency'] : 0;

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
