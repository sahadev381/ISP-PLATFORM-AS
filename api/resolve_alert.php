<?php
header('Content-Type: application/json');
include_once '../config.php';
require_once __DIR__ . '/../includes/api_auth.php';

api_require_auth();
api_require_post();

$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Alert ID required']);
    exit;
}

db_exec($conn, "UPDATE network_alerts SET status = 'resolved', resolved_at = NOW() WHERE id = ?", [$id]);

echo json_encode(['success' => true, 'message' => 'Alert resolved']);
