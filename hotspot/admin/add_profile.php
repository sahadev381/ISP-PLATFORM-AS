<?php
session_start();

chdir(__DIR__ . '/../..');
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? 'data';
    $dataLimitMb = intval($_POST['data_limit_mb'] ?? 0);
    $validityHours = intval($_POST['validity_hours'] ?? 24);
    $price = floatval($_POST['price'] ?? 0);
    $speedKbps = intval($_POST['speed_kbps'] ?? 1024);
    
    if ($name && $price >= 0) {
        $conn->query("
            INSERT INTO hotspot_profiles (name, type, data_limit_mb, validity_hours, price, speed_kbps)
            VALUES ('$name', '$type', $dataLimitMb, $validityHours, $price, $speedKbps)
        ");
    }
}

header('Location: index.php');
exit;
?>
