<?php
/**
 * Change a customer's status.
 *
 * Hardened: requires an authenticated admin, a POST request with a valid
 * CSRF token, and only accepts a fixed set of status values.
 * (It used to be an unauthenticated GET with the value interpolated
 * straight into the UPDATE statement.)
 */
include 'config.php';
include 'includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('POST required');
}

csrf_check();

$user   = trim($_POST['user'] ?? '');

// Refuse customers belonging to another branch.
require_customer_access($conn, $user);

$status = strtolower(trim($_POST['status'] ?? ''));

if ($user === '' || $status === '') {
    http_response_code(400);
    exit('Username and status are required');
}

$allowed = ['active', 'inactive', 'blocked', 'suspended', 'pending'];
if (!in_array($status, $allowed, true)) {
    http_response_code(400);
    exit('Invalid status');
}

db_exec($conn, "UPDATE customers SET status = ? WHERE username = ?", [$status, $user]);

if (function_exists('logActivity')) {
    logActivity('customer_status_change', "Set status of $user to $status");
}

header('Location: user_view.php?user=' . urlencode($user));
exit;
