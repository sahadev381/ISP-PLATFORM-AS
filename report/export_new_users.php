<?php
include '../config.php';
include '../includes/auth.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="new_users.csv"');

$output = fopen('php://output', 'w');

// CSV Header
fputcsv($output, ['Username', 'Plan', 'Speed', 'Expiry Date', 'Status']);

$limit = 30;

$result = $conn->query("
    SELECT
        u.username,
        u.expiry,
        u.status,
        p.name AS plan_name,
        p.speed
    FROM customers u
    LEFT JOIN plans p ON u.plan_id = p.id
    ORDER BY u.id DESC
    LIMIT $limit
");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['username'],
        $row['plan_name'],
        $row['speed'],
        $row['expiry'],
        $row['status']
    ]);
}

fclose($output);
exit;

