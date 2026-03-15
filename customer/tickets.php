<?php
include '../user-config.php';
include '../includes/customer.php';
include '../includes/user-header.php';
/*include '../includes/sidebar.php';
include '../includes/topbar.php';
 */


$tickets = $conn->query("SELECT * FROM tickets WHERE customer_id='".$_SESSION['customer_id']."' ORDER BY id DESC");
?>


<h3>My Support Tickets</h3>
<table>
<tr><th>ID</th><th>Subject</th><th>Status</th><th>Priority</th><th></th></tr>
<?php while($t=$tickets->fetch_assoc()){ ?>
<tr>
<td>#<?= $t['id'] ?></td>
<td><?= $t['subject'] ?></td>
<td><?= $t['status'] ?></td>
<td><?= $t['priority'] ?></td>
<td><a href="ticket_view.php?id=<?= $t['id'] ?>">View</a></td>
</tr>
<?php } ?>
</table>
