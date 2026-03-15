<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "User Reports";
$active = "reports";

// Expiry threshold in days
$expiry_threshold = 7; // users expiring within 7 days

// Fetch users
$users = $conn->query("
    SELECT u.id, u.username, u.plan_id, u.expiry, u.status,
           p.name AS plan_name, p.speed,
           (SELECT COUNT(*) FROM radacct r WHERE r.username=u.username AND r.acctstoptime IS NULL) AS online
    FROM customers u
    LEFT JOIN plans p ON u.plan_id = p.id
    ORDER BY u.id DESC
");

$new_users = $expired_users = $expiring_users = $active_users = [];

$today = date('Y-m-d');

while($row = $users->fetch_assoc()){
    $expiry_date = $row['expiry'];

    // New users: top 30 by id (most recent)
    if(count($new_users) < 30){
        $new_users[] = $row;
    }

    // Expired users
    if($expiry_date < $today){
        $expired_users[] = $row;
    }
    // Expiring soon
    elseif(strtotime($expiry_date) <= strtotime("+$expiry_threshold days")){
        $expiring_users[] = $row;
    }

    // Active users
    if($row['online'] > 0){
        $active_users[] = $row;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<h1><?= $page_title ?></h1>

<h2>New Users (<?= count($new_users) ?>)</h2>
<div>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>Username</th><th>Plan</th><th>Speed</th><th>Status</th></tr>
    <?php foreach($new_users as $u): ?>
    <tr>
        <td><?= $u['username'] ?></td>
        <td><?= $u['plan_name'] ?></td>
        <td><?= $u['speed'] ?></td>
        <td><?= $u['status'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<h2>Expiring Users (<?= count($expiring_users) ?>)</h2>
<div>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>Username</th><th>Plan</th><th>Speed</th><th>Expiry Date</th></tr>
    <?php foreach($expiring_users as $u): ?>
    <tr>
        <td><?= $u['username'] ?></td>
        <td><?= $u['plan_name'] ?></td>
        <td><?= $u['speed'] ?></td>
        <td><?= $u['expiry'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<h2>Expired Users (<?= count($expired_users) ?>)</h2>
<div>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>Username</th><th>Plan</th><th>Speed</th><th>Expiry Date</th></tr>
    <?php foreach($expired_users as $u): ?>
    <tr>
        <td><?= $u['username'] ?></td>
        <td><?= $u['plan_name'] ?></td>
        <td><?= $u['speed'] ?></td>
        <td><?= $u['expiry'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<h2>Active Users (<?= count($active_users) ?>)</h2>
<div>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>Username</th><th>Plan</th><th>Speed</th><th>Status</th></tr>
    <?php foreach($active_users as $u): ?>
    <tr>
        <td><?= $u['username'] ?></td>
        <td><?= $u['plan_name'] ?></td>
        <td><?= $u['speed'] ?></td>
        <td><?= ($u['online']>0)?"Online":"Offline" ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<form method="post" action="export_report.php">
    <input type="submit" name="export" value="Export CSV">
</form>
</body>
</html>

