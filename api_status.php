<?php
include 'config.php';
include 'includes/genieacs_api.php';

header('Content-Type: application/json');

// 1. Database Details
$db_online = (isset($conn) && !$conn->connect_error);
$db_uptime = 0;
$db_version = "Unknown";
if($db_online) {
    $uptime_res = $conn->query("SHOW STATUS LIKE 'Uptime'");
    $db_uptime = $uptime_res->fetch_assoc()['Value'] ?? 0;
    $ver_res = $conn->query("SELECT VERSION() as ver");
    $db_version = $ver_res->fetch_assoc()['ver'] ?? "N/A";
}

// 2. FreeRADIUS Details
$radius_process = shell_exec("pgrep freeradius || pgrep radiusd");
$radius_online = !empty($radius_process);
// Stats for today
$auth_ok = $conn->query("SELECT COUNT(*) as c FROM radpostauth WHERE reply='Access-Accept' AND DATE(authdate)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$auth_fail = $conn->query("SELECT COUNT(*) as c FROM radpostauth WHERE reply='Access-Reject' AND DATE(authdate)=CURDATE()")->fetch_assoc()['c'] ?? 0;

// 3. GenieACS Details
$acs_test = genieacs_request("/devices?_limit=1");
$acs_online = (isset($acs_test) && !isset($acs_test['error']));
$acs_tasks = genieacs_request("/tasks?_limit=1"); // Check if task queue is moving
$pending_tasks = is_array($acs_tasks) ? count($acs_tasks) : 0;

// 4. System Info (Server)
$load = sys_getloadavg();
$mem_info = shell_exec("free -m");
$mem_lines = explode("\n", $mem_info);
$mem_stats = preg_split('/\s+/', $mem_lines[1]);
$mem_usage = round(($mem_stats[2] / $mem_stats[1]) * 100, 1); // Used / Total

echo json_encode([
    'database' => [
        'status' => $db_online,
        'uptime' => round($db_uptime / 3600, 1) . " hours",
        'version' => $db_version
    ],
    'radius' => [
        'status' => $radius_online,
        'success_today' => $auth_ok,
        'failed_today' => $auth_fail
    ],
    'acs' => [
        'status' => $acs_online,
        'pending_tasks' => $pending_tasks
    ],
    'system' => [
        'cpu_load' => $load[0],
        'mem_usage' => $mem_usage,
        'uptime' => shell_exec("uptime -p")
    ]
]);
