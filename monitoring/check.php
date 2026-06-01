<?php
include "db.php";

// Fetch all devices from the database
$result = $conn->query("SELECT * FROM devices");
if (!$result) {
    die("Error fetching devices: " . $conn->error);
}

function pingDevice($ip) {
    // Use exec or shell_exec to ping
    $pingResult = shell_exec("ping -c 1 -W 1 " . escapeshellarg($ip));
    if (strpos($pingResult, '1 packets transmitted, 1 received') !== false) {
        return true; // device is up
    } else {
        return false; // device is down
    }
}

$devices = [];
while ($device = $result->fetch_assoc()) {
    $devices[] = $device;
}

$mh = curl_multi_init();
$curl_handles = [];

foreach ($devices as &$device) {
    $device['status'] = 'DOWN'; // default status
    $ip = $device['ip_address'];
    $type = $device['type'];

    if ($type == 'ping') {
        // Ping check
        $safe_ip = escapeshellarg($ip);
        exec("ping -c 1 $safe_ip", $out, $return_var);
        $device['status'] = ($return_var === 0) ? 'UP' : 'DOWN';
    } elseif ($type == 'http') {
        // HTTP check - initialize handle for concurrent execution
        $ch = curl_init($ip);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($mh, $ch);
        $curl_handles[$device['id']] = $ch;
    }
}
unset($device);

// Execute the multi-handle
$active = null;
do {
    $mrc = curl_multi_exec($mh, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($mh) != -1) {
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    } else {
        // If select fails, wait a tiny bit to prevent 100% CPU
        usleep(100);
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
}

// Process results and update database
foreach ($devices as $device) {
    $id = $device['id'];
    $status = $device['status'];

    if ($device['type'] == 'http') {
        $ch = $curl_handles[$id];
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $status = ($http_code >= 200 && $http_code < 400) ? 'UP' : 'DOWN';
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    // Update device status
    $stmt = $conn->prepare("UPDATE devices SET status=?, last_checked=NOW() WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
}

curl_multi_close($mh);
?>
