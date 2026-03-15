<?php
include 'config.php';
include 'includes/auth.php'; // must contain session check

$msg = "";

if (isset($_POST['change'])) {

    $new_password = $_POST['password'];

    if (strlen($new_password) < 6) {
        $msg = "Password must be at least 6 characters!";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        // assuming auth.php sets admin ID
        //$admin_id = $_SESSION['admin_id'];

        $stmt = $conn->prepare("UPDATE admins SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $admin_id);

        if ($stmt->execute()) {
            $msg = "Password updated successfully!";
        } else {
            $msg = "Failed to update password!";
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
<h1>Change Password</h1>

<?php if($msg){ ?>
<div style="background:#2ecc71;color:#fff;padding:10px;border-radius:10px;">
<?= htmlspecialchars($msg) ?>
</div>
<?php } ?>

<form method="post" class="table-box">
    <input class="input" type="password" name="password" placeholder="New password" required>
    <br><br>
    <button class="btn" name="change">Change Password</button>
</form>
</div>

<?php include 'includes/footer.php'; ?>

