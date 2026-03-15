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


while ($device = $result->fetch_assoc()) {
    $ip = $device['ip_address'];
    $type = $device['type'];
    $id = $device['id'];
    $status = 'DOWN'; // default status

    if ($type == 'ping') {
        // Ping check
        $safe_ip = escapeshellarg($ip);
        exec("ping -c 1 $safe_ip", $out, $return_var);
        $status = ($return_var === 0) ? 'UP' : 'DOWN';
    } elseif ($type == 'http') {
        // HTTP check
        $ch = curl_init($ip);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($http_code >= 200 && $http_code < 400) ? 'UP' : 'DOWN';
    }

    // Update device status
    $stmt = $conn->prepare("UPDATE devices SET status=?, last_checked=NOW() WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
}
?>
