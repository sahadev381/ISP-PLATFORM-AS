<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/mikrotik_api.php';

header('Content-Type: application/json');

$nas_id = $_GET['nas_id'] ?? null;
$interface = $_GET['interface'] ?? '';

if (!$nas_id || !$interface) {
    die(json_encode(['error' => 'Missing parameters']));
}

$nas = $conn->query("SELECT * FROM nas WHERE id = $nas_id")->fetch_assoc();
if (!$nas) die(json_encode(['error' => 'NAS not found']));

$api = new RouterosAPI();
$api->setPort($nas['api_port'] ?: 8728);

if (@$api->connect($nas['ip_address'], $nas['api_user'], $nas['api_pass'])) {
    $api->write("/interface/monitor-traffic", false);
    $api->write("=interface=" . $interface, false);
    $api->write("=once=", true);
    $read = $api->read();
    $api->disconnect();

    if (isset($read[0])) {
        $rx = $read[0]['rx-bits-per-second'] ?? 0;
        $tx = $read[0]['tx-bits-per-second'] ?? 0;
        
        echo json_encode([
            'rx' => round($rx / 1048576, 2), // Mbps
            'tx' => round($tx / 1048576, 2), // Mbps
            'timestamp' => date('H:i:s')
        ]);
    } else {
        echo json_encode(['error' => 'No data']);
    }
} else {
    echo json_encode(['error' => 'Connection failed']);
}
?>
