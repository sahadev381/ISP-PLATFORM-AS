<?php
include 'config.php';
include 'includes/auth.php';

if(!isSuperAdmin()) die("Access Denied");

$id = (int)$_GET['id'];
$conn->query("DELETE FROM branches WHERE id=$id");
header("Location: branches.php");
exit;

