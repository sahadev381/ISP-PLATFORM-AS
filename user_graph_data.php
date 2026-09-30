<?php
include 'config.php';
require_once __DIR__ . '/includes/api_auth.php';
api_require_auth();


header('Content-Type: application/json');

$user = $_GET['user'] ?? '';
$range = $_GET['range'] ?? 'daily';

if (!$user) {
    echo json_encode([]);
    exit;
}

// Example: Fetch last 30 days usage for this user
$rows = db_all($conn, "SELECT date, usage_mb FROM usage_logs WHERE username = ? ORDER BY date ASC", [$user]);

$data = ['labels' => [], 'values' => []];
foreach ($rows as $row) {
    $data['labels'][] = $row['date'];
    $data['values'][] = (float)$row['usage_mb'];
}

echo json_encode($data);

