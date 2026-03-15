<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// Check if 'id' parameter is provided
if (!isset($_GET['id'])) {
    die("Error: Device ID not specified.");
}

$id = intval($_GET['id']); // Sanitize input

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $_POST['name'];
    $ip_address = $_POST['ip_address'];
    $type = $_POST['type'];
    $status = $_POST['status'];
    $last_checked = $_POST['last_checked'];

    // Prepare update statement
    $stmt = $conn->prepare("UPDATE devices SET name=?, ip_address=?, type=?, status=?, last_checked=? WHERE id=?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sssssi", $name, $ip_address, $type, $status, $last_checked, $id);

    if ($stmt->execute()) {
        // Redirect to dashboard after update
        header("Location: dashboard.php");
        exit;
    } else {
        die("Error updating device: " . $stmt->error);
    }

    $stmt->close();
} else {
    // Fetch current device data to pre-fill the form
    $stmt = $conn->prepare("SELECT * FROM devices WHERE id=?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Device not found.");
    }

    $device = $result->fetch_assoc();

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Device</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<h2>Edit Device</h2>
<form method="POST" action="">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($device['name']) ?>" required><br><br>

    <label>IP Address:</label><br>
    <input type="text" name="ip_address" value="<?= htmlspecialchars($device['ip_address']) ?>" required><br><br>

    <label>Type:</label><br>
    <input type="text" name="type" value="<?= htmlspecialchars($device['type']) ?>" required><br><br>

    <label>Status:</label><br>
    <select name="status" required>
        <option value="UP" <?= $device['status'] === 'UP' ? 'selected' : '' ?>>UP</option>
        <option value="DOWN" <?= $device['status'] === 'DOWN' ? 'selected' : '' ?>>DOWN</option>
    </select><br><br>

    <label>Last Checked:</label><br>
    <input type="datetime-local" name="last_checked" value="<?= htmlspecialchars($device['last_checked']) ?>"><br><br>

    <button type="submit">Update Device</button>
</form>

</body>
</html>
