<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// Check if 'id' parameter is provided
if (!isset($_GET['id'])) {
    die("Error: Device ID not specified.");
}

$id = intval($_GET['id']); // Sanitize input

// Prepare and execute delete statement
$stmt = $conn->prepare("DELETE FROM devices WHERE id = ?");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Redirect back to dashboard after deletion
    header("Location: dashboard.php");
    exit;
} else {
    die("Error deleting device: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>
