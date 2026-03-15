<?php
include 'config.php';

header('Content-Type: application/json');

$user = $_GET['user'] ?? '';
$range = $_GET['range'] ?? 'daily';

if (!$user) {
    echo json_encode([]);
    exit;
}

// Escape user
$user = $conn->real_escape_string($user);

// Example: Fetch last 30 days usage for this user
$sql = "SELECT date, usage_mb FROM usage_logs WHERE username='$user' ORDER BY date ASC";
$result = $conn->query($sql);

$data = ['labels' => [], 'values' => []];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $data['labels'][] = $row['date'];
        $data['values'][] = (float)$row['usage_mb'];
    }
}

echo json_encode($data);

