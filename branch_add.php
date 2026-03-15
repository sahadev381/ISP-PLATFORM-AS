<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
include 'includes/header.php';

if(!isSuperAdmin()) die("Access Denied");

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {

    $name    = trim($_POST['name']);
    $code    = trim($_POST['code']);
    $address = trim($_POST['address']);
    $phone   = trim($_POST['phone']);
    $status  = $_POST['status'];

    if ($name === '' || $code === '') {
        $error = "Branch name and code are required";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO branches (name, code, address, phone, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("SQL Prepare Failed: " . $conn->error);
        }

        $stmt->bind_param("sssss", $name, $code, $address, $phone, $status);

        if ($stmt->execute()) {
            header("Location: branches.php");
            exit;
        } else {
            $error = "Insert failed: " . $stmt->error;
        }
    }
}
?>
<div class="main">
<?php if(!empty($error)): ?>
<div class="alert alert-danger">
    <?= htmlspecialchars($error); ?>
</div>
<?php endif; ?>
<h2>Add New Branch</h2>
<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
    <input type="text" name="name" placeholder="Branch Name" class="form-control" required><br>
    <input type="text" name="code" placeholder="Branch Code" class="form-control" required><br>
    <textarea name="address" placeholder="Address" class="form-control"></textarea><br>
    <input type="text" name="phone" placeholder="Phone" class="form-control"><br>
    <select name="status" class="form-control">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select><br>
    <button type="submit" name="add" class="btn btn-success">Add Branch</button>
</form>
</div>
