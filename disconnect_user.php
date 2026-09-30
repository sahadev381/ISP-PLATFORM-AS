<?php
define('RBAC_JSON_ENDPOINT', true); // errors from the RBAC guards must be JSON
/**
 * Send a RADIUS Disconnect-Request (CoA) for a PPPoE user.
 *
 * Fixed here:
 *  - the old code wrapped escapeshellarg() output inside single quotes,
 *    which produced broken shell quoting and made the command fail;
 *  - the NAS IP and the shared secret were interpolated unescaped, so
 *    anyone able to edit a NAS record got shell execution;
 *  - the shared secret was echoed back in the error message.
 */
include 'config.php';
include 'includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'msg' => 'POST required']);
    exit;
}

csrf_check();

$username = trim($_POST['username'] ?? '');
// Refuse customers belonging to another branch.
require_customer_access($conn, $username);

if ($username === '') {
    echo json_encode(['success' => false, 'msg' => 'Username required']);
    exit;
}

$nas = db_one($conn, "SELECT ip_address, secret FROM nas WHERE status = 1 LIMIT 1");
if (!$nas) {
    echo json_encode(['success' => false, 'msg' => 'NAS not configured']);
    exit;
}

// The NAS address comes from the database but is still validated before it
// reaches a shell.
$nasIp = trim((string) $nas['ip_address']);
if (!filter_var($nasIp, FILTER_VALIDATE_IP)) {
    error_log("disconnect_user: invalid NAS IP in database: $nasIp");
    echo json_encode(['success' => false, 'msg' => 'NAS address is invalid']);
    exit;
}

$radclient = '/usr/bin/radclient';
if (!is_executable($radclient)) {
    echo json_encode(['success' => false, 'msg' => 'radclient is not installed']);
    exit;
}

// Feed the attribute to radclient on stdin instead of building an `echo`
// pipeline, so the username never touches the shell at all.
$cmd = sprintf(
    '%s -x %s disconnect %s 2>&1',
    escapeshellcmd($radclient),
    escapeshellarg($nasIp . ':3799'),
    escapeshellarg((string) $nas['secret'])
);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    echo json_encode(['success' => false, 'msg' => 'Could not start radclient']);
    exit;
}

fwrite($pipes[0], 'User-Name = "' . str_replace('"', '\"', $username) . "\"\n");
fclose($pipes[0]);

$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);

if (stripos($output, 'Received Disconnect-ACK') !== false) {
    if (function_exists('logActivity')) {
        logActivity('disconnect_user', "Disconnected $username");
    }
    echo json_encode(['success' => true, 'msg' => 'User disconnected successfully.']);
    exit;
}

// Never return raw radclient output: it contains the RADIUS shared secret.
error_log("disconnect_user failed for $username: $output");
echo json_encode(['success' => false, 'msg' => 'Disconnect failed. See the server log for details.']);
