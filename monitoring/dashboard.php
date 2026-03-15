<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// Fetch devices
$devices = $conn->query("SELECT * FROM devices");

// Check if query was successful
if ($devices === false) {
    die("Database query failed: " . $conn->error);
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Monitoring Dashboard</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<h2>Device Monitoring Dashboard</h2>
<a class="btn" href="add_device.php">Add Device</a>

<table>
<tr>
<th>Name</th><th>Address</th><th>Type</th><th>Status</th><th>Last Check</th><th>Fail Count</th><th>Action</th>
</tr>

<?php while($d = $devices->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($d['name']) ?></td>
<td><?= htmlspecialchars($d['ip_address']) ?></td>
<td><?= strtoupper($d['type']) ?></td>
<td class="<?= $d['status'] === 'UP' ? 'up' : 'down' ?>">
<?= htmlspecialchars($d['status']) ?></td>
<td><?= htmlspecialchars($d['last_checked']) ?></td>
<td><?= htmlspecialchars($d['fail_count']) ?></td>
<td>
<a href="edit_device.php?id=<?= $d['id'] ?>">Edit</a>
<a href="delete_device.php?id=<?= $d['id'] ?>">Delete</a>

</td>
</tr>
<?php endwhile; ?>
</table>

</body>
</html>

