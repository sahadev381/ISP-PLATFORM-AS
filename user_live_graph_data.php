<?php
include __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api_auth.php';
api_require_auth();


header('Content-Type: application/json');

$username = $_GET['user'] ?? '';
if (!$username) {
    echo json_encode(['offline' => true]);
    exit;
}

$cacheFile = sys_get_temp_dir() . '/radacct_' . md5($username) . '.json';
$now = time();

$session = db_one($conn, "
    SELECT radacctid, acctinputoctets, acctoutputoctets, acctsessiontime, acctstoptime
    FROM radacct
    WHERE username = ?
      AND acctstoptime IS NULL
    ORDER BY radacctid DESC
    LIMIT 1
", [$username]);

if (!$session) {
    echo json_encode(['offline' => true]);
    exit;
}

$r = $session;

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
