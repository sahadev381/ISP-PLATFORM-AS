<?php
include "db.php";
require_once "parallel_checker.php";

// Fetch all devices from the database
$result = $conn->query("SELECT * FROM devices");
if (!$result) {
    die("Error fetching devices: " . $conn->error);
}

$devices = [];
while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

// Group devices by type
$ping_devices = [];
$http_devices = [];

foreach ($devices as $device) {
    if ($device['type'] == 'ping') {
        $ping_devices[] = $device;
    } elseif ($device['type'] == 'http') {
        $http_devices[] = $device;
    }
}

// Extract IPs/URLs for parallel checking
$ping_ips = array_column($ping_devices, 'ip_address');
$http_urls = array_column($http_devices, 'ip_address');

// Perform parallel checks
$ping_results = parallelPing($ping_ips);
$http_results = parallelHttpCheck($http_urls);

// Combine results
$all_results = [];
foreach ($ping_devices as $device) {
    $all_results[$device['id']] = $ping_results[$device['ip_address']] ?? 'DOWN';
}
foreach ($http_devices as $device) {
    $all_results[$device['id']] = $http_results[$device['ip_address']] ?? 'DOWN';
}

// Update device statuses
$stmt = $conn->prepare("UPDATE devices SET status=?, last_checked=NOW() WHERE id=?");
foreach ($all_results as $id => $status) {
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
}
$stmt->close();

/**
 * Kept for backward compatibility if used elsewhere,
 * but now uses the logic from parallel_checker if possible or just sequential for single.
 */
function pingDevice($ip) {
    $results = parallelPing([$ip]);
    return isset($results[$ip]) && $results[$ip] === 'UP';
}

/**
 * Added for completeness and consistency with monitor.php
 */
function httpCheck($url) {
    $results = parallelHttpCheck([$url]);
    return isset($results[$url]) && $results[$url] === 'UP';
}
?>
