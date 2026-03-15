<?php
include 'config.php';
$conn->query("UPDATE customers SET status='expired' WHERE expiry<CURDATE()");
$conn->query("DELETE FROM radreply WHERE username IN (SELECT username FROM customers WHERE status='expired')");
?>

