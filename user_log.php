<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'includes/auth.php';

$username = isset($_GET['user']) ? trim($_GET['user']) : '';

if ($username === '') {
    header("Location: users.php");
    exit;
}

$username_safe = $conn->real_escape_string($username);

/* Run query safely */
$sql = "
    SELECT 
        username,
        pass,
        reply,
        authdate
    FROM radpostauth
    WHERE username='$username_safe'
    ORDER BY authdate DESC
    LIMIT 50
";

$login_attempts = $conn->query($sql);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';

/* Failure reason detector (PHP 7 compatible) */
function radius_reason($reply, $pass) {

    if ($reply === 'Access-Accept') {
        return 'Login successful';
    }

    if ($reply === 'Access-Reject') {
        if ($pass === '' || $pass === null) {
            return 'Password not provided';
        }
        return 'Authentication rejected (wrong password / MAC lock )';
    }

    return 'Unknown result';
}
?>

<div class="main">

<?php if ($login_attempts === false) { ?>

    <div class="table-box">
        <h2 style="color:red;">Database Error</h2>
        <pre style="color:#fff;"><?= htmlspecialchars($conn->error) ?></pre>
    </div>

<?php } elseif ($login_attempts->num_rows > 0) { ?>

<div class="table-box">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>PPPoE Authentication Log</h1>

        <a href="user_view.php?user=<?= urlencode($username) ?>" class="btn">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>

    <table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="background:#2c3e50;color:#fff;">
            <th>#</th>
            <th>Username</th>
            <th>Password</th>
            <th>Date & Time</th>
            <th>Status</th>
            <th>Reason</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $sn = 1;
    while ($row = $login_attempts->fetch_assoc()) {
        $success = ($row['reply'] === 'Access-Accept');
        $reason  = radius_reason($row['reply'], $row['pass']);
    ?>
        <tr style="background:#34495e;color:#ecf0f1;">
            <td><?= $sn++ ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= htmlspecialchars($row['pass']) ?></td>
            <td><?= date('Y-m-d H:i:s', strtotime($row['authdate'])) ?></td>
            <td>
                <span class="badge <?= $success ? 'active' : 'expired' ?>">
                    <?= $success ? 'Success' : 'Failed' ?>
                </span>
            </td>
            <td><?= htmlspecialchars($reason) ?></td>
        </tr>
    <?php } ?>
    </tbody>
</table>
</div>

<?php } else { ?>

    <p style="color:#fff;">No authentication logs found.</p>

<?php } ?>

</div>

<?php include 'includes/footer.php'; ?>

