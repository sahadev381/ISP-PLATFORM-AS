<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost","radius","radiuspass","radius");
if($conn->connect_error) die("DB Error");
?>

