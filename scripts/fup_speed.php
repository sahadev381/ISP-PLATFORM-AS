<?php
include '../config.php';

function getSpeedByUsage($username) {
    global $conn;
    
    $result = $conn->query("
        SELECT 
            COALESCE(u.used_quota, 0) as used,
            COALESCE(p.data_limit, 1073741824000) as total
        FROM customers c
        LEFT JOIN data_usage u ON c.username = u.username
        LEFT JOIN plans p ON c.plan_id = p.id
        WHERE c.username = '$username'
    ");
    
    $row = $result->fetch_assoc();
    
    $used = floatval($row['used']);
    $total = floatval($row['total']);
    
    if ($total == 0) $total = 1073741824000; // Default 1000GB
    
    $percentage = ($used / $total) * 100;
    
    if ($percentage < 60) {
        return ['download' => 100000000, 'upload' => 50000000, 'tier' => 'Normal']; // 100Mbps
    } elseif ($percentage < 80) {
        return ['download' => 50000000, 'upload' => 25000000, 'tier' => 'Tier 1']; // 50Mbps
    } elseif ($percentage < 100) {
        return ['download' => 25000000, 'upload' => 10000000, 'tier' => 'Tier 2']; // 25Mbps
    } else {
        return ['download' => 10000000, 'upload' => 5000000, 'tier' => 'Tier 3']; // 10Mbps
    }
}

function updateMikrotikSpeed($nas_ip, $api_user, $api_pass, $api_port, $username, $speed) {
    $router = new RouterOSClient($nas_ip, $api_user, $api_pass, $api_port);
    
    $queueName = "FUP-" . $username;
    
    $router->add('/queue/simple/add', [
        'name' => $queueName,
        'target' => $username,
        'max-limit' => $speed . 'M/' . $speed . 'M',
    ]);
}

function setRadiusSpeed($username, $download_bps, $upload_bps) {
    global $conn;
    
    $download_kbps = $download_bps / 1000;
    $upload_kbps = $upload_bps / 1000;
    
    $conn->query("DELETE FROM radreply WHERE username='$username' AND attribute LIKE 'Mikrotik-%'");
    
    $conn->query("
        INSERT INTO radreply (username, attribute, op, value)
        VALUES 
        ('$username', 'Mikrotik-Rate-Limit', ':=', '${download_kbps}k/${upload_kbps}k'),
        ('$username', 'Ascend-Data-Rate', ':=', '${download_kbps}000 ${upload_kbps}000')
    ");
}

if (isset($_GET['username'])) {
    $username = $_GET['username'];
    $speed = getSpeedByUsage($username);
    
    echo json_encode($speed);
}
?>
