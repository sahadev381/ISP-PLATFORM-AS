<?php
require_once __DIR__ . '/../config.php';

// How many users disabled today
$disabled_count = 0;

// 1. Fetch expired users
$today = date('Y-m-d');
$expired_users = db_all($conn, "
    SELECT username, status
    FROM customers
    WHERE expiry < ?
      AND status = 'Active'
", [$today]);

foreach($expired_users as $user){
    $username = $user['username'];

    // 2. Disable in database
    db_exec($conn, "
        UPDATE customers
        SET status = 'Expired'
        WHERE username = ?
    ", [$username]);

    // 3. Optional: disconnect online session via FreeRADIUS
    // You can run radclient command here if needed

    $disabled_count++;
}

// Optional: log for monitoring
file_put_contents(__DIR__ . '/auto_disable_log.txt', date('Y-m-d H:i:s')." Disabled $disabled_count users\n", FILE_APPEND);

