<?php
include '../config.php';
include '../includes/customer.php';
require_once __DIR__ . '/../includes/csrf.php';
include '../includes/user-header.php';

$id = (int) ($_SESSION['customer_id'] ?? 0);
if (!$id) {
    header("Location: index.php");
    exit;
}

$msg = '';

if (isset($_POST['save'])) {
    csrf_check();

    // Bug fix: $address was never read from the request, so the address
    // column was always overwritten with an empty/undefined value.
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid email address.';
    } else {
        db_exec(
            $conn,
            "UPDATE customers SET phone = ?, address = ?, email = ? WHERE id = ?",
            [$phone, $address, $email, $id]
        );
        $msg = 'Profile updated.';
    }
}

$c = db_one($conn, "SELECT * FROM customers WHERE id = ?", [$id]);
if (!$c) {
    exit('Customer not found.');
}
?>

<?php if ($msg): ?>
    <p><?= e($msg) ?></p>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <input name="phone" value="<?= e($c['phone']) ?>">
    <input name="email" value="<?= e($c['email']) ?>">
    <input name="address" value="<?= e($c['address']) ?>">
    <button name="save">Save</button>
</form>
