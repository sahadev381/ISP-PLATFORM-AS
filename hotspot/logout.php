<?php
session_start();

chdir(__DIR__ . '/..');
include_once 'config.php';
include_once 'hotspot/includes/auth.php';

$auth = new HotspotAuth();
$auth->logout();

header('Location: index.php');
exit;
?>
