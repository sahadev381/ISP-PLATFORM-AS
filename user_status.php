<?php
include 'config.php';

// Use the same parameter as in your URL: 'user'
if (!isset($_GET['user']) || !isset($_GET['status'])) {
    die("Username and status are required");
}

$user   = $conn->real_escape_string($_GET['user']);
$status = $conn->real_escape_string($_GET['status']);

// Update query: use the correct column name
$conn->query("UPDATE customers SET status='$status' WHERE username='$user'");

// Redirect back
header("Location: user_view.php?user=$user");
exit;
?>

