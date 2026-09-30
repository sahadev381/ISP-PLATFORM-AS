<?php
require_once __DIR__ . '/../user-config.php';
require_once __DIR__ . '/../includes/customer.php';
require_once __DIR__ . '/../includes/user-header.php';


$id = (int) $_SESSION['customer_id'];

// invoices.username holds the customer's username, not their numeric id, so
// the old "WHERE username=$id" never matched and this page was always empty.
$invoices = db_all($conn, "
    SELECT i.*
    FROM invoices i
    JOIN customers c ON c.username = i.username
    WHERE c.id = ?
    ORDER BY i.id DESC
", [$id]);
?>


<h3>My Invoices</h3>
<table>
<tr><th>ID</th><th>Amount</th><th>Status</th><th>Due</th><th></th></tr>
<?php foreach($invoices as $i){ ?>
<tr>
<td>#<?= (int) $i['id'] ?></td>
<td><?= e($i['amount']) ?></td>
<td><?= e($i['status']) ?></td>
<td><?= e($i['due_date']) ?></td>
<td><a href="invoice_view.php?id=<?= (int) $i['id'] ?>">View</a></td>
</tr>
<?php } ?>
</table>
