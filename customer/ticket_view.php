<?php
require_once __DIR__ . '/../user-config.php';
require_once __DIR__ . '/../includes/customer.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/user-header.php';
/*include '../includes/sidebar.php';
include '../includes/topbar.php';
*/

$id = (int) ($_GET['id'] ?? 0);
$customer_id = (int) $_SESSION['customer_id'];

// Load the ticket first and confirm it belongs to the logged-in customer.
// The reply handler used to insert against any ticket id supplied in the URL
// without checking ownership, so one customer could post into another's ticket.
$ticket = db_one($conn, "SELECT * FROM tickets WHERE id = ? AND customer_id = ?", [$id, $customer_id]);

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

if (isset($_POST['reply'])) {
    csrf_check();

    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        db_exec($conn, "INSERT INTO ticket_replies (ticket_id, sender, message) VALUES (?, 'Customer', ?)", [$id, $msg]);
        header('Location: ticket_view.php?id=' . $id);
        exit;
    }
}

$replies = db_all($conn, "SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY id ASC", [$id]);
?>


<h3><?= e($ticket['subject']) ?></h3>
<p>Status: <?= e($ticket['status']) ?></p>


<?php foreach($replies as $r){ ?>
<div><b><?= e($r['sender']) ?>:</b> <?= nl2br(e($r['message'])) ?></div>
<?php } ?>


<form method="post">
<?= csrf_field() ?>
<textarea name="message" required></textarea>
<button name="reply">Reply</button>
</form>
