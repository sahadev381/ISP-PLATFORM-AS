<?php
include '../user-config.php';
include '../includes/customer.php';
include '../includes/user-header.php';


$id = $_SESSION['customer_id'];
$invoices = $conn->query("SELECT * FROM invoices WHERE username=$id ORDER BY id DESC");
?>


<h3>My Invoices</h3>
<table>
<tr><th>ID</th><th>Amount</th><th>Status</th><th>Due</th><th></th></tr>
<?php while($i=$invoices->fetch_assoc()){ ?>
<tr>
<td>#<?= $i['id'] ?></td>
<td><?= $i['amount'] ?></td>
<td><?= $i['status'] ?></td>
<td><?= $i['due_date'] ?></td>
<td><a href="invoice_view.php?id=<?= $i['id'] ?>">View</a></td>
</tr>
<?php } ?>
</table>
