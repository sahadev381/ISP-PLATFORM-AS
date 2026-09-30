<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="active_users.csv"');

$output = fopen('php://output', 'w');

/**
 * Prefix values a spreadsheet would treat as a formula.
 */
function csv_cell($value) {
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

// CSV Header
fputcsv($output, ['Username', 'Plan', 'Speed', 'Expiry Date', 'Status']);

$limit = 30;

// The filter below used to sit in the file as bare SQL outside any string,
// which made this script a parse error and meant the export never ran (and
// would have returned every customer if it had).
$rows = db_all($conn, "
    SELECT
        u.username,
        u.expiry,
        u.status,
        p.name AS plan_name,
        p.speed
    FROM customers u
    LEFT JOIN plans p ON u.plan_id = p.id
    WHERE EXISTS (
        SELECT 1 FROM radacct r
        WHERE r.username = u.username
        AND r.acctstoptime IS NULL
    )
    ORDER BY u.id DESC
    LIMIT ?
", [$limit]);

foreach ($rows as $row) {
    fputcsv($output, array_map('csv_cell', [
        $row['username'],
        $row['plan_name'],
        $row['speed'],
        $row['expiry'],
        $row['status']
    ]));
}

fclose($output);
exit;
