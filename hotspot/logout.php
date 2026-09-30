<?php
session_start();

chdir(__DIR__ . '/..');
include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/includes/auth.php';

$auth = new HotspotAuth();
$auth->logout();

header('Location: index.php');
exit;
?>
