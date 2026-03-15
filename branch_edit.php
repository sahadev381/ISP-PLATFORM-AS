<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
include 'includes/header.php';

if(!isSuperAdmin()) die("Access Denied");

$id = (int)$_GET['id'];
$branch = $conn->query("SELECT * FROM branches WHERE id=$id")->fetch_assoc();
$error = '';

if(isset($_POST['update'])){
    $name = $_POST['name'];
    $code = $_POST['code'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE branches SET name=?, code=?, address=?, phone=?, status=? WHERE id=?");
    $stmt->bind_param("sssssi",$name,$code,$address,$phone,$status,$id);
    if($stmt->execute()){
        header("Location: branches.php");
        exit;
    } else {
        $error = "Failed to update branch";
    }
}
?>
<div class="main">
<h2>Edit Branch</h2>
<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
    <input type="text" name="name" value="<?= htmlspecialchars($branch['name']) ?>" class="form-control" required><br>
    <input type="text" name="code" value="<?= htmlspecialchars($branch['code']) ?>" class="form-control" required><br>
    <textarea name="address" class="form-control"><?= htmlspecialchars($branch['address']) ?></textarea><br>
    <input type="text" name="phone" value="<?= htmlspecialchars($branch['phone']) ?>" class="form-control"><br>
    <select name="status" class="form-control">
        <option value="active" <?= $branch['status']=='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $branch['status']=='inactive'?'selected':'' ?>>Inactive</option>
    </select><br>
    <button type="submit" name="update" class="btn btn-primary">Update Branch</button>
</form>
</div>
