<?php
include '../user-config.php';
include '../includes/customer.php';
include '../includes/user-header.php';
/*include '../includes/sidebar.php';
include '../includes/topbar.php';
*/

$id = (int)$_GET['id'];


if(isset($_POST['reply'])){
$msg = $_POST['message'];
$stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, sender, message) VALUES (?, 'Customer', ?)");
$stmt->bind_param("is", $id, $msg);
$stmt->execute();
}


$ticket = $conn->query("SELECT * FROM tickets WHERE id=$id AND customer_id='".$_SESSION['customer_id']."'")->fetch_assoc();
$replies = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id=$id ORDER BY id ASC");
?>


<h3><?= $ticket['subject'] ?></h3>
<p>Status: <?= $ticket['status'] ?></p>


<?php while($r=$replies->fetch_assoc()){ ?>
<div><b><?= $r['sender'] ?>:</b> <?= nl2br($r['message']) ?></div>
<?php } ?>


<form method="post">
<textarea name="message" required></textarea>
<button name="reply">Reply</button>
</form>
