<?php
require_once __DIR__ . '/../includes/session.php';
session_boot();

// Destroy all session data
session_kill();

// Redirect to login page
header("Location: index.php");
exit;
?>

