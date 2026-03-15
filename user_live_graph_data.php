<?php
include 'config.php';

header('Content-Type: application/json');

$username = $_GET['user'] ?? '';
if (!$username) {
    echo json_encode(['offline' => true]);
    exit;
}

$username = $conn->real_escape_string($username);

$cacheFile = sys_get_temp_dir() . '/radacct_' . md5($username) . '.json';
$now = time();

$q = $conn->query("
    SELECT radacctid, acctinputoctets, acctoutputoctets, acctsessiontime, acctstoptime
    FROM radacct
    WHERE username='$username'
      AND acctstoptime IS NULL
    ORDER BY radacctid DESC
    LIMIT 1
");

if (!$q || $q->num_rows === 0) {
    echo json_encode(['offline' => true]);
    exit;
}

$r = $q->fetch_assoc();

$currIn = (int)$r['acctinputoctets'];
$currOut = (int)$r['acctoutputoctets'];
$sessionTime = (int)$r['acctsessiontime'];

$download_mbps = 0;
$upload_mbps = 0;

if(file_exists($cacheFile)) {
    $prev = json_decode(file_get_contents($cacheFile), true);
    $prevIn = (int)$prev['in'];
    $prevOut = (int)$prev['out'];
    $prevTime = (int)$prev['time'];
    
    $timeDiff = max($now - $prevTime, 1);
    
    $inRate = ($currIn - $prevIn) / $timeDiff;
    $outRate = ($currOut - $prevOut) / $timeDiff;
    
    $download_mbps = max(0, ($inRate * 8) / 1000000);
    $upload_mbps = max(0, ($outRate * 8) / 1000000);
}

file_put_contents($cacheFile, json_encode([
    'in' => $currIn,
    'out' => $currOut,
    'time' => $now
]));

echo json_encode([
    'download_mbps' => round($download_mbps, 2),
    'upload_mbps' => round($upload_mbps, 2),
    'total_in' => $currIn,
    'total_out' => $currOut,
    'session_time' => $sessionTime
]);
