<?php
define('RBAC_JSON_ENDPOINT', true); // errors from the RBAC guards must be JSON
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

$username = $_GET['user'] ?? '';
// Refuse customers belonging to another branch.
require_customer_access($conn, $username);

$range    = $_GET['range'] ?? 'daily';

$username = (string) $username;

$labels = [];
$upload = [];
$download = [];

switch ($range) {

    case 'daily':
        $sql = "
            SELECT
                DATE_FORMAT(acctstarttime, '%H:00') label,
                SUM(acctinputoctets) upload,
                SUM(acctoutputoctets) download
            FROM radacct
            WHERE username = ?
              AND DATE(acctstarttime) = CURDATE()
            GROUP BY HOUR(acctstarttime)
            ORDER BY acctstarttime
        ";
        break;

    case 'weekly':
        $sql = "
            SELECT
                DATE(acctstarttime) label,
                SUM(acctinputoctets) upload,
                SUM(acctoutputoctets) download
            FROM radacct
            WHERE username = ?
              AND acctstarttime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(acctstarttime)
            ORDER BY acctstarttime
        ";
        break;

    case 'monthly':
        $sql = "
            SELECT
                DATE(acctstarttime) label,
                SUM(acctinputoctets) upload,
                SUM(acctoutputoctets) download
            FROM radacct
            WHERE username = ?
              AND MONTH(acctstarttime) = MONTH(CURDATE())
              AND YEAR(acctstarttime) = YEAR(CURDATE())
            GROUP BY DATE(acctstarttime)
            ORDER BY acctstarttime
        ";
        break;

    case 'yearly':
        $sql = "
            SELECT
                DATE_FORMAT(acctstarttime, '%Y-%m') label,
                SUM(acctinputoctets) upload,
                SUM(acctoutputoctets) download
            FROM radacct
            WHERE username = ?
              AND YEAR(acctstarttime) = YEAR(CURDATE())
            GROUP BY YEAR(acctstarttime), MONTH(acctstarttime)
            ORDER BY acctstarttime
        ";
        break;

    default:
        echo json_encode([]);
        exit;
}

$q = db_all($conn, $sql, [$username]);

foreach ($q as $r) {
    $labels[]   = $r['label'];
    $upload[]   = round($r['upload'] / 1024 / 1024, 2);
    $download[] = round($r['download'] / 1024 / 1024, 2);
}

echo json_encode([
    'labels' => $labels,
    'upload' => $upload,
    'download' => $download
]);
exit;

