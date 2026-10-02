<?php
include __DIR__ . '/config.php';
require_once __DIR__ . '/includes/csrf.php';

/* Page info */
$page_title = "Admin Users";
$active = "admin";

/* Auth check */
include __DIR__ . '/includes/auth.php';

/* Only Super Admin */
if ($_SESSION['role'] !== 'superadmin') {
    die("Access Denied");
}

/* Fetch branches */
$branches = $conn->query("SELECT id,name FROM branches WHERE status='active'");

/* ADD / UPDATE ADMIN */
if (isset($_POST['add'])) {
    // This branch writes; the token must be checked before it does.
    csrf_check();

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $branch_id = $_POST['branch_id'] ?: null;

    // Allowed ENUM roles
    $allowed_roles = ['superadmin','manager','support'];
    $role = strtolower($_POST['role'] ?? 'support');

    if (!in_array($role, $allowed_roles)) {
        die("Invalid role selected!");
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO admins (username, password, role, branch_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("sssi", $username, $password_hash, $role, $branch_id);
    if ($stmt->execute()) {
        $msg = "Admin added successfully!";
    } else {
        $msg = "Could not add the admin. Please check the details and try again.";
        error_log("admin.php add failed: " . $stmt->error);
    }
}


/* DELETE ADMIN */
if(isset($_GET['del'])){
    // GET deletes need the token checked explicitly; csrf_check() skips GET.
    csrf_check_request();
    $id = (int)$_GET['del'];
    if($id != $_SESSION['user_id']){
        db_exec($conn, "DELETE FROM admins WHERE id = ?", [(int) $id]);
        $msg = "Admin deleted!";
    }
}

/* Fetch admin users */
$admins = $conn->query("
    SELECT 
        a.id,
        a.username,
        a.role,
        a.branch_id,
        b.name AS branch_name
    FROM admins a
    LEFT JOIN branches b ON a.branch_id = b.id
    ORDER BY a.id DESC
");

if(isset($_POST['change_password'])){
    $id = (int)$_POST['admin_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    /* Prevent superadmin password change */
    $check = db_one($conn, "SELECT role FROM admins WHERE id = ?", [(int) $id]);
    if($check['role'] === 'superadmin'){
        die("Action not allowed");
    }

    if($new_password !== $confirm_password){
        $msg = "? Passwords do not match!";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password=? WHERE id=?");
        $stmt->bind_param("si",$hash,$id);
        $stmt->execute();
        $msg = "? Password changed successfully!";
    }
}

/* Layout */
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="main">

<div id="addAdminBox" style="display:none;margin-top:15px;">
<div class="table-box">
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="edit_id" id="edit_id">

<table>
<tr>
    <td>Username</td>
    <td><input class="input" name="username" id="username" required></td>
</tr>

<tr>
    <td>Password</td>
    <td><input class="input" type="password" name="password" placeholder="Leave blank to keep unchanged"></td>
</tr>

<tr>
    <td>Role</td>
    <td>
        <select class="input" name="role" id="role">
            <option value="branchadmin">Branch Admin</option>
            <option value="staff">Staff</option>
        </select>
    </td>
</tr>

<tr>
    <td>Branch</td>
    <td>
        <select class="input" name="branch_id" id="branch_id">
            <option value="">All Branches</option>
            <?php while($b=$branches->fetch_assoc()){ ?>
                <option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option>
            <?php } ?>
        </select>
    </td>
</tr>

<tr>
    <td></td>
    <td>
        <button class="btn" name="save_admin">
            <i class="fa fa-save"></i> Save Admin
        </button>
    </td>
</tr>
</table>
</form>
</div>
</div>

<div class="table-box">
 <h3 style="margin-bottom:15px;">Existing Admin Users</h3>
        <!-- Toggle Button -->
        <button class="btn" data-action="toggleAddAdmin" type="button">
            <i class="fa fa-user-plus"></i> Add
        </button>
<table>
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Role</th>
        <th>Branch</th>
        <th>Action</th>
    </tr>

    <?php while($a = $admins->fetch_assoc()){ ?>
    <tr>
        <td><?= e($a['id']) ?></td>
        <td><?= htmlspecialchars($a['username']) ?></td>
        <td><?= e(strtoupper($a['role'] ?? '')) ?></td>
        <td><?= e($a['branch_name'] ?? 'All') ?></td>
        <td>
<?php
$isSelf = ($a['id'] == $_SESSION['user_id']);
$isSuper = ($a['role'] === 'superadmin');
?>

<?php if($isSelf){ ?>

    <span class="badge active">Logged In</span>

<?php } elseif($isSuper){ ?>

    <span class="badge locked">Protected</span>

<?php } else { ?>

<!-- EDIT -->
<a class="btn" href="admin_edit.php?id=<?= e($a['id']) ?>">
            <i class="fa fa-edit"></i> Edit
        </a>


    <!-- CHANGE PASSWORD (ONLY ONCE) -->
    <button class="btn" style="background:#3498db;color:#fff;"
        <?= action_attr('showElement', ['changePass' . $a['id']]) ?>>
        <i class="fa fa-key"></i> Password
    </button>

    <!-- DELETE -->
    <a class="btn" style="background:#e74c3c;color:#fff;"
       href="?del=<?= (int) $a['id'] ?>&amp;_csrf=<?= e(csrf_token()) ?>"
       <?= action_attr('confirmFirst', ['Delete this admin?']) ?>>
       <i class="fa fa-trash"></i>
    </a>

    <!-- CHANGE PASSWORD MODAL -->
    <div id="changePass<?= e($a['id']) ?>"
         style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);">
        <div style="background:#fff;padding:20px;border-radius:10px;width:320px;margin:120px auto;position:relative;">
            <span style="position:absolute;top:10px;right:15px;cursor:pointer;font-weight:bold;"
                onclick="this.parentElement.parentElement.style.display='none'">&times;</span>

            <h4>Change Password</h4>

            <form method="post">
<?= csrf_field() ?>
                <input type="hidden" name="admin_id" value="<?= e($a['id']) ?>">

                <input class="input" type="password" name="new_password"
                       placeholder="New Password" required>

                <input class="input" type="password" name="confirm_password"
                       placeholder="Confirm Password" required
                       style="margin-top:8px;">

                <button class="btn" name="change_password" style="margin-top:10px;">
                    Save
                </button>
            </form>
        </div>
    </div>

<?php } ?>

        </td>
    </tr>
    <?php } ?>
</table>
</div>
</div>
<script>
function toggleAddAdmin(){
    let box = document.getElementById('addAdminBox');
    box.style.display = (box.style.display === 'none') ? 'block' : 'none';
    document.querySelector("form").reset();
    document.getElementById('edit_id').value='';
}

function editAdmin(id,username,role,branch){
    toggleAddAdmin();
    document.getElementById('edit_id').value = id;
    document.getElementById('username').value = username;
    document.getElementById('role').value = role;
    document.getElementById('branch_id').value = branch;
}

function checkPassMatch(p1,p2,msgId){
    document.getElementById(msgId).innerText =
        (p1.value !== p2.value) ? "Passwords do not match" : "";
}
</script>

