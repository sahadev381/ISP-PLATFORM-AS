<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

csrf_check();

// Without this, any logged-in account could open admin_edit.php?id=1 and
// give itself the superadmin role.
require_role('superadmin');

$page_title = "Edit Admin";
$active = "admin";

/* =========================
   GET ADMIN DATA
========================= */
$id = (int)($_GET['id'] ?? 0);
$admin = db_one($conn, "SELECT * FROM admins WHERE id = ?", [$id]);
if (!$admin) die("Admin not found");

/* =========================
   FETCH BRANCHES
========================= */
$branches = $conn->query("SELECT * FROM branches WHERE status='active'");

/* =========================
   HANDLE FORM SUBMIT
========================= */
$msg = '';

if (isset($_POST['update'])) {
    $username  = trim($_POST['username']);
    $role      = strtolower($_POST['role'] ?? 'support');
    $branch_id = $_POST['branch_id'] ?: null;

    // Validate ENUM role
    $allowed_roles = ['manager','support'];
    if (!in_array($role, $allowed_roles)) {
        die("Invalid role selected!");
    }

    $stmt = $conn->prepare("UPDATE admins SET username=?, role=?, branch_id=? WHERE id=?");
    $stmt->bind_param("ssii", $username, $role, $branch_id, $id);
    if ($stmt->execute()) {
        $msg = "Admin updated successfully!";
        // Refresh data
        $admin = db_one($conn, "SELECT * FROM admins WHERE id = ?", [$id]);
    } else {
        $msg = "Error: " . $stmt->error;
    }
}

/* =========================
   HANDLE PASSWORD CHANGE
========================= */
if (isset($_POST['change_password'])) {
    $plain = trim($_POST['new_password'] ?? '');

    if (strlen($plain) < 8) {
        $msg = "Password must be at least 8 characters.";
    } else {
        $new_password = password_hash($plain, PASSWORD_DEFAULT);
        db_exec($conn, "UPDATE admins SET password = ? WHERE id = ?", [$new_password, $id]);
        logActivity('admin_password_change', "Changed password for admin #$id");
        $msg = "Password changed successfully!";
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="main">

<?php if($msg){ ?>
<div style="background:#2ecc71;color:#fff;padding:10px;border-radius:10px;margin-bottom:15px;">
    <?= htmlspecialchars($msg) ?>
</div>
<?php } ?>

<div class="table-box">
<form method="post">
<?= csrf_field() ?>
<table>
<tr>
    <td>Username</td>
    <td><input class="input" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required></td>
</tr>

<tr>
    <td>Role</td>
    <td>
        <select class="input" name="role" required>
            <?php
            $roles = ['superadmin','manager','support'];
            foreach ($roles as $r) {
                $sel = ($admin['role'] === $r) ? 'selected' : '';
                echo "<option value='" . e($r) . "' $sel>" . e(strtoupper($r)) . "</option>";
            }
            ?>
        </select>
    </td>
</tr>

<tr>
    <td>Branch</td>
    <td>
        <select class="input" name="branch_id">
            <option value="">All</option>
            <?php while($b=$branches->fetch_assoc()) {
                $sel = ($admin['branch_id'] == $b['id']) ? 'selected' : '';
                echo "<option value='" . (int) $b['id'] . "' $sel>" . e($b['name']) . "</option>";
            } ?>
        </select>
    </td>
</tr>

<tr>
    <td></td>
    <td><button class="btn" name="update">
        <i class="fa fa-save"></i> Update Admin
    </button></td>
</tr>
</table>
</form>
</div>

<!-- Change Password Form -->
<div class="table-box" style="margin-top:20px;">
<h3>Change Password</h3>
<form method="post">
<?= csrf_field() ?>
<table>
<tr>
    <td>New Password</td>
    <td><input class="input" type="password" name="new_password" required></td>
</tr>
<tr>
    <td></td>
    <td><button class="btn" name="change_password">
        <i class="fa fa-key"></i> Change Password
    </button></td>
</tr>
</table>
</form>
</div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

