#!/usr/bin/php
<?php
/**
 * Uptime Monitor Cron Script
 * Periodically pings all NAS devices and logs their status for 99.9% Uptime SLA tracking.
 */

include __DIR__ . '/../config.php';

// Re-establish connection for CLI if needed, though config.php should have it
if (!isset($conn) || $conn->connect_error) {
    // If config.php doesn't establish $conn, we would normally expect it to.
    // In this environment, we assume config.php is present and works.
    die("[" . date('Y-m-d H:i:s') . "] Database connection failed. Please ensure config.php is correctly configured.\n");
}

function ping($host) {
    $output = []; $result = 0;
    exec("ping -c 1 -W 1 " . escapeshellarg($host), $output, $result);
    return ($result === 0);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Uptime Monitor...\n";

$devices = $conn->query("SELECT id, nasname, ip_address, device_type FROM nas");
$logged = 0;

while($d = $devices->fetch_assoc()) {
    $start_time = microtime(true);
    $is_online = ping($d['ip_address']);
    $latency = round((microtime(true) - $start_time) * 1000, 2);
    $status_val = $is_online ? 1 : 0;

    // Update NAS status using prepared statement
    $upd = $conn->prepare("UPDATE nas SET status = ? WHERE id = ?");
    $upd->bind_param("ii", $status_val, $d['id']);
    $upd->execute();

    // Log to uptime_logs
    $status_str = $is_online ? 'online' : 'offline';
    $target_type = strtoupper($d['device_type'] ?: 'UNKNOWN');

    $stmt = $conn->prepare("INSERT INTO uptime_logs (target_type, target_id, status, latency_ms, checked_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sisd", $target_type, $d['id'], $status_str, $latency);
    $stmt->execute();

    $logged++;
}

echo "[" . date('Y-m-d H:i:s') . "] Uptime Monitor finished. Devices checked: $logged\n";
?>
