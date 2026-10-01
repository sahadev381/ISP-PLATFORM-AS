<?php
/**
 * Cron: block customers whose subscription has expired.
 * Only runnable from the CLI or with a valid API key — it must not be
 * triggerable by an anonymous web request.
 */
include __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api_auth.php';

if (PHP_SAPI !== 'cli' && !api_has_valid_key()) {
    http_response_code(403);
    exit('Forbidden');
}

// Get block time from system_config
$cfg = $conn->query("SELECT config_value FROM system_config WHERE config_key='expire_block_time'")->fetch_assoc();
$block_time = $cfg && !empty($cfg['config_value']) ? $cfg['config_value'] : '08:00';

// Today's date
$today = date('Y-m-d');

// Block all users whose expiry <= today AND not already blocked
$sql = "
    UPDATE customers
    SET blocked = 1, status='blocked'
    WHERE expiry <= '$today'
    AND blocked = 0
";
$conn->query($sql);

// Optional: log action
file_put_contents(__DIR__ . '/logs/block_expired.log', date('Y-m-d H:i:s') . " - Blocked expired users\n", FILE_APPEND);

echo "Expired users blocked successfully at $block_time\n";

