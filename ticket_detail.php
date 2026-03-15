<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'includes/auth.php';

$page_title = "Ticket Details";
$active = "tickets";

/* ===========================
   CHECK TICKET ID OR USER
=========================== */

// Get ticket ID from URL
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$username  = isset($_GET['username']) ? $conn->real_escape_string($_GET['username']) : '';

if ($ticket_id <= 0 && empty($username)) {
    die("Ticket ID or username missing!");
}

if ($ticket_id > 0) {
    $stmt = $conn->prepare("SELECT t.*, c.full_name, c.username FROM tickets t LEFT JOIN customers c ON t.username = c.username WHERE t.id = ?");
    $stmt->bind_param("i", $ticket_id);
} else {
    $stmt = $conn->prepare("SELECT t.*, c.full_name, c.username FROM tickets t LEFT JOIN customers c ON t.username = c.username WHERE t.username = ?");
    $stmt->bind_param("s", $username);
}

/* ===========================
   FETCH TICKET DETAILS
=========================== */

$stmt = $conn->prepare("SELECT t.*, c.full_name, c.username FROM tickets t LEFT JOIN customers c ON t.username = c.username WHERE t.id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();
$ticket = $result->fetch_assoc();

if (!$ticket) {
    die("Ticket not found!");
}

/* ===========================
   HANDLE REPLIES IF POSTED
=========================== */
if(isset($_POST['reply'])) {
    $reply_text = trim($_POST['reply']);

    if($reply_text) {
        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, reply_text, created_at, admin) VALUES (?, ?, NOW(), 1)");
        $stmt->bind_param("is", $ticket_id, $reply_text);
        $stmt->execute();

        header("Location: ticket_detail.php?id=$ticket_id");
        exit;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">

<div class="topbar">
    <h1>Ticket #<?= htmlspecialchars($ticket['id']) ?> - <?= htmlspecialchars($ticket['subject']) ?></h1>
</div>

<div class="table-box">
    <table>
        <tr><td>Customer:</td><td><?= htmlspecialchars($ticket['full_name']) ?> (<?= htmlspecialchars($ticket['username']) ?>)</td></tr>
        <tr><td>Subject:</td><td><?= htmlspecialchars($ticket['subject']) ?></td></tr>
        <tr><td>Status:</td><td><?= htmlspecialchars($ticket['status']) ?></td></tr>
        <tr><td>Created:</td><td><?= htmlspecialchars($ticket['created_at']) ?></td></tr>
        <tr><td>Description:</td><td><?= nl2br(htmlspecialchars($ticket['description'])) ?></td></tr>
    </table>
</div>

<h3>Replies</h3>
<div class="table-box">
    <table>
        <?php
        $replies = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id=$ticket_id ORDER BY created_at ASC");
        while($r = $replies->fetch_assoc()) {
            echo "<tr><td>" . ($r['admin'] ? 'Admin' : 'Customer') . "</td><td>" . nl2br(htmlspecialchars($r['reply_text'])) . "</td><td>" . $r['created_at'] . "</td></tr>";
        }
        ?>
    </table>
</div>

<h3>Post a Reply</h3>
<form method="post">
    <textarea name="reply" class="input" required></textarea>
    <button class="btn" name="send"><i class="fa fa-reply"></i> Reply</button>
</form>

</div>

<?php include 'includes/footer.php'; ?>

