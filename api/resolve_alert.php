<?php
header('Content-Type: application/json');
include_once '../config.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Alert ID required']);
    exit;
}

$conn->query("UPDATE network_alerts SET status = 'resolved', resolved_at = NOW() WHERE id = $id");

echo json_encode(['success' => true, 'message' => 'Alert resolved']);
