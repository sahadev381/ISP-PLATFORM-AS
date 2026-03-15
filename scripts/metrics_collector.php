#!/usr/bin/php
<?php
/**
 * Metrics Collector: Run every 5 minutes via Cron
 */

include __DIR__ . '/../config.php';
include __DIR__ . '/../includes/olt_api.php';

// Re-establish connection for CLI
$conn = new mysqli("localhost", "radius", "radiuspass", "radius");

function logMetric($type, $target, $id, $val) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO performance_metrics (metric_type, target_type, target_id, metric_value) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssid", $type, $target, $id, $val);
    $stmt->execute();
}

// 1. Collect Server Stats
$cpu = sys_getloadavg()[0];
$mem = round(shell_exec("free | grep Mem | awk '{print $3/$2 * 100.0}'"), 2);

logMetric('CPU', 'SERVER', 0, $cpu);
logMetric('MEM', 'SERVER', 0, $mem);

// 2. Collect OLT Stats
$olts = $conn->query("SELECT * FROM nas WHERE device_type = 'olt'");
while($o = $olts->fetch_assoc()) {
    $driver = new OLT_Driver($o);
    $h = $driver->getHealth();
    
    logMetric('CPU', 'OLT', $o['id'], $h['cpu']);
    logMetric('MEM', 'OLT', $o['id'], rand(20, 40)); // Mocking RAM for OLT
}

echo "[" . date('Y-m-d H:i:s') . "] Performance metrics logged successfully.\n";
?>
