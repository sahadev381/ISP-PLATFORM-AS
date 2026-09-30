<?php
/**
 * Delete a branch. Superadmin only, POST + CSRF token required
 * (it used to be a plain GET link, so any image tag could trigger it).
 */
include 'config.php';
include 'includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isSuperAdmin()) {
    http_response_code(403);
    die("Access Denied");
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    die("POST required");
}

csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    db_exec($conn, "DELETE FROM branches WHERE id = ?", [$id]);
    logActivity('branch_delete', "Deleted branch #$id");
}

header("Location: branches.php");
exit;
