<?php
session_start();
if(!isset($_SESSION['customer_id'])){
header("Location:../customer/login.php");
exit;
}
?>
