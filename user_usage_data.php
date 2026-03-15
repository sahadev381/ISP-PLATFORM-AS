<?php
include 'config.php';
include 'includes/auth.php';

header('Content-Type: application/json');

$username = $_GET['user'] ?? '';
$range    = $_GET['range'] ?? 'daily';

$username = $conn->real_escape_string($username);

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
            WHERE username='$username'
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
            WHERE username='$username'
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
            WHERE username='$username'
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
            WHERE username='$username'
              AND YEAR(acctstarttime) = YEAR(CURDATE())
            GROUP BY YEAR(acctstarttime), MONTH(acctstarttime)
            ORDER BY acctstarttime
        ";
        break;

    default:
        echo json_encode([]);
        exit;
}

$q = $conn->query($sql);

while ($r = $q->fetch_assoc()) {
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

