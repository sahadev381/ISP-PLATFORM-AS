<?php
require_once __DIR__ . '/session.php';
session_boot();

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: /index.php");
    exit;
}

// Session timeout check
$timeout = 30; // default 30 minutes
$idleTimeout = 15; // default 15 minutes

// Load settings from database if available
if (isset($conn) && function_exists('db_all')) {
    $result = db_all($conn, "SELECT setting_key, setting_value FROM system_settings
                             WHERE setting_key IN ('session_timeout', 'session_idle_timeout')");
    foreach ($result as $row) {
        if ($row['setting_key'] == 'session_timeout') {
            $timeout = (int)$row['setting_value'];
        }
        if ($row['setting_key'] == 'session_idle_timeout') {
            $idleTimeout = (int)$row['setting_value'];
        }
    }
}

// A session with no login_time recorded never hit the absolute timeout
// below, because the whole check was wrapped in isset(). Treat a missing
// value as "started now" so the clock at least starts.
if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
}

// Check if session is expired (absolute timeout)
if (isset($_SESSION['login_time'])) {
    $sessionDuration = time() - $_SESSION['login_time'];
    $maxDuration = $timeout * 60;
    
    if ($sessionDuration > $maxDuration) {
        session_kill();
        header("Location: /index.php?timeout=1");
        exit;
    }
}

// Check if session is idle (no activity)
if (isset($_SESSION['last_activity'])) {
    $idleTime = time() - $_SESSION['last_activity'];
    $maxIdle = $idleTimeout * 60;
    
    if ($idleTime > $maxIdle) {
        session_kill();
        header("Location: /index.php?idle=1");
        exit;
    }
}

// Update last activity time
$_SESSION['last_activity'] = time();

$USER_ID   = $_SESSION['user_id'];
$USERNAME  = $_SESSION['username'];
$ROLE      = $_SESSION['role'] ?? '';
$BRANCH_ID = $_SESSION['branch_id'] ?? null;

/* Branch check */
if($ROLE !== 'superadmin' && empty($BRANCH_ID)){
    die("Branch not assigned");
}

/* Role helpers and branch scoping live in rbac.php. It also keeps
   isSuperAdmin()/isBranchAdmin()/isStaff() working for existing callers. */
require_once __DIR__ . '/rbac.php';

/* Log activity function */
function logActivity($action, $description = '') {
    global $conn, $USER_ID, $USERNAME;
    
    if (!isset($conn) || !isset($USER_ID)) return;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $stmt = $conn->prepare("
        INSERT INTO activity_log (user_id, username, action, description, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $USER_ID, $USERNAME, $action, $description, $ip);
    $stmt->execute();
}
