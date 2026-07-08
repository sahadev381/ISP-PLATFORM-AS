<?php
$base_path = './';
include 'config.php';
include 'includes/auth.php';
include 'includes/genieacs_api.php';
include 'includes/tr069_pppoe.php';

/* ============================
   LOAD USER
============================ */
$username = $_GET['user'] ?? '';
$user = $conn->query("
    SELECT u.*, p.name as plan_name, p.speed as plan_speed, p.data_limit, b.name as branch_name,
    COALESCE(du.used_quota, 0) as used_quota
    FROM customers u
    LEFT JOIN plans p ON u.plan_id = p.id
    LEFT JOIN branches b ON u.branch_id = b.id
    LEFT JOIN data_usage du ON u.username = du.username
    WHERE u.username='$username'
")->fetch_assoc();

if (!$user) die("User not found");

/* ============================
   POST ACTIONS (TAB HANDLERS)
============================ */
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_grace') {
        $days = (int)$_POST['grace_days'];
        if ($days > 0) {
            $conn->query("UPDATE customers SET expiry = DATE_ADD(expiry, INTERVAL $days DAY), status='active' WHERE username='$username'");
            $success_msg = "Grace period of $days days added successfully.";
            // Reload user data
            $user['expiry'] = date('Y-m-d', strtotime($user['expiry'] . " + $days days"));
        }
    } elseif ($action === 'lock_pppoe') {
        $mac = '';
        $session_res = $conn->query("SELECT callingstationid FROM radacct WHERE username='$username' AND acctstoptime IS NULL LIMIT 1");
        if ($session_res->num_rows > 0) {
            $mac = $session_res->fetch_assoc()['callingstationid'];
        } else {
            $history_res = $conn->query("SELECT callingstationid FROM radacct WHERE username='$username' AND callingstationid != '' ORDER BY acctstarttime DESC LIMIT 1");
            if ($history_res && $history_res->num_rows > 0) $mac = $history_res->fetch_assoc()['callingstationid'];
        }
        if ($mac) {
            $conn->query("DELETE FROM radcheck WHERE username='$username' AND attribute='Calling-Station-Id'");
            $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Calling-Station-Id', '==', '$mac')");
            $success_msg = "Locked to MAC: $mac";
        }
    } elseif ($action === 'unlock_pppoe') {
        $conn->query("DELETE FROM radcheck WHERE username='$username' AND attribute='Calling-Station-Id'");
        $success_msg = "MAC Lock Removed";
    } elseif ($action === 'reset_fup') {
        // 1. Set fup_reset flag to reset usage tracking
        $conn->query("UPDATE data_usage SET used_quota = 0, fup_reset = 1, updated_at = NOW() WHERE username = '$username'");

        // 2. Get base plan speed
        $plan = $conn->query("SELECT p.speed FROM plans p JOIN customers c ON c.plan_id = p.id WHERE c.username = '$username'")->fetch_assoc();
        $plan_speed = $plan['speed'] ?? '10M/10M';

        // 3. Reset Speed in radreply to base plan speed
        $conn->query("DELETE FROM radreply WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'");
        $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Mikrotik-Rate-Limit', ':=', '$plan_speed')");

        $success_msg = "FUP reset! Usage cleared. Speed restored to $plan_speed.";
        $user['used_quota'] = 0;
    } elseif ($action === 'disconnect') {
        $nas = $conn->query("SELECT * FROM nas WHERE status=1 LIMIT 1")->fetch_assoc();
        if ($nas) {
            $nas_ip = $nas['ip_address'];
            $nas_secret = $nas['secret'];
            shell_exec("echo 'User-Name = $username' | /usr/bin/radclient -x $nas_ip:3799 disconnect $nas_secret 2>&1");
            $success_msg = "Disconnect command sent to NAS.";
        }
    } elseif (isset($_POST['reboot'])) {
        $deviceId = $_POST['deviceId'] ?? '';
        if ($deviceId) {
            genieacs_request("/devices/$deviceId/tasks", "POST", ["name" => "reboot"]);
            $success_msg = "Reboot command sent to device.";
        }
    } elseif (isset($_POST['setwifi'])) {
        $deviceId = $_POST['deviceId'] ?? '';
        $ssid = $_POST['ssid'] ?? '';
        $pass = $_POST['wifi_pass'] ?? '';
        if ($deviceId && $ssid && $pass) {
            genieacs_request("/devices/$deviceId/tasks", "POST", [
                "name" => "setParameterValues",
                "parameterValues" => [
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", $ssid, "xsd:string"],
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase", $pass, "xsd:string"]
                ]
            ]);
            $conn->query("UPDATE customers SET wifi_ssid='$ssid', wifi_password='$pass' WHERE username='$username'");
            $success_msg = "WiFi updated and synced.";
        }
