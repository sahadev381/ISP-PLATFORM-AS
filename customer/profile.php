<?php
include '../user-config.php';
include '../includes/customer.php';
include '../includes/user-header.php';


$id = $_SESSION['customer_id'];


if(isset($_POST['save'])){
$phone = $_POST['phone'];
$email = $_POST['email'];
$conn->query("UPDATE customers SET phone='$phone', address='$address',  email='$email' WHERE id=$id");
}


$c = $conn->query("SELECT * FROM customers WHERE id=$id")->fetch_assoc();
?>


<form method="post">
<input name="phone" value="<?= $c['phone'] ?>">
<input name="email" value="<?= $c['email'] ?>">
<input name="address" value="<?=$c['address']?>">
<button name="save">Save</button>
</form>
