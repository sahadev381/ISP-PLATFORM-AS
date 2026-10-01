<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php'; // must contain session check
require_once __DIR__ . '/includes/csrf.php';

$msg = "";

if (isset($_POST['change'])) {
    // This branch writes; the token must be checked before it does.
    csrf_check();


    $new_password = $_POST['password'];

    if (strlen($new_password) < 6) {
        $msg = "Password must be at least 6 characters!";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        // $admin_id was never defined - the line that would have set it
        // was commented out. bind_param() therefore bound null, the
        // statement became "WHERE id = NULL", it matched no rows, and
        // execute() still returned true. The page reported "Password
        // updated successfully!" every time without changing anything.
        $admin_id = (int) ($_SESSION['user_id'] ?? 0);

        if ($admin_id <= 0) {
            $msg = "Your session has expired. Please sign in again.";
        } else {
            db_exec($conn, "UPDATE admins SET password = ? WHERE id = ?", [$hash, $admin_id]);

            // Report on rows actually changed, not on "the query ran".
            if ($conn->affected_rows === 1) {
                $msg = "Password updated successfully!";
            } else {
                $msg = "Failed to update password!";
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main">
<h1>Change Password</h1>

<?php if($msg){ ?>
<div style="background:#2ecc71;color:#fff;padding:10px;border-radius:10px;">
<?= htmlspecialchars($msg) ?>
</div>
<?php } ?>

<form method="post" class="table-box">
<?= csrf_field() ?>
    <input class="input" type="password" name="password" placeholder="New password" required>
    <br><br>
    <button class="btn" name="change">Change Password</button>
</form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

