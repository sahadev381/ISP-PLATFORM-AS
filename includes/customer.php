<?php
require_once __DIR__ . '/session.php';
session_boot();
if(!isset($_SESSION['customer_id'])){
header("Location: ../customer/index.php");
exit;
}
?>
