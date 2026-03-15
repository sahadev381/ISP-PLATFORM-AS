<?php
$base_path = './';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid Ticket ID");

/* Handle reply */
if (isset($_POST['reply'])) {
    $msg    = trim($_POST['message']);
    $status = $_POST['status'];

    if ($msg !== '') {
        $conn->query("UPDATE tickets SET status='$status' WHERE id=$id");
        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, sender, message, created_at) VALUES (?, 'Admin', ?, NOW())");
        $stmt->bind_param("is", $id, $msg);
        $stmt->execute();
        header("Location: ticket_view.php?id=$id&msg=replied");
        exit;
    }
}

$ticket = $conn->query("
    SELECT t.*, c.username, c.full_name, c.phone
    FROM tickets t 
    LEFT JOIN customers c ON t.customer_id = c.id 
    WHERE t.id=$id
")->fetch_assoc();

if (!$ticket) die("Ticket not found");

$replies = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id=$id ORDER BY created_at ASC");

$page_title = "Ticket #" . $id . ": " . $ticket['subject'];
include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .ticket-view-container { padding: 25px; max-width: 1000px; margin: 0 auto; }
    
    .ticket-header { background: #fff; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .ticket-info h2 { margin: 0; font-size: 20px; color: #1e293b; }
    .ticket-meta { margin-top: 10px; color: #64748b; font-size: 13px; display: flex; gap: 15px; }
    
    .chat-box { background: #fff; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; }
    .reply-item { margin-bottom: 20px; display: flex; flex-direction: column; }
    .reply-bubble { padding: 15px 20px; border-radius: 15px; max-width: 80%; position: relative; font-size: 14px; line-height: 1.6; }
    
    .admin-reply { align-self: flex-end; }
    .admin-reply .reply-bubble { background: #eff6ff; color: #1e40af; border-bottom-right-radius: 2px; }
    .admin-reply .reply-meta { align-self: flex-end; }
    
    .user-reply { align-self: flex-start; }
    .user-reply .reply-bubble { background: #f8fafc; color: #334155; border-bottom-left-radius: 2px; border: 1px solid #f1f5f9; }
    
    .reply-meta { margin-top: 5px; font-size: 11px; color: #94a3b8; padding: 0 5px; }
    
    .reply-form-card { background: #fff; border-radius: 15px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; }
    .form-control { width: 100%; padding: 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; margin-bottom: 15px; }
    .form-control:focus { border-color: #3b82f6; }
    
    .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .badge-open { background: #fee2e2; color: #ef4444; }
    .badge-progress { background: #fff7ed; color: #f59e0b; }
    .badge-closed { background: #f1f5f9; color: #94a3b8; }
</style>

<div class="ticket-view-container">
    <div class="ticket-header">
        <div class="ticket-info">
            <span class="badge badge-<?= strtolower(str_replace(' ', '', $ticket['status'])) ?>"><?= $ticket['status'] ?></span>
            <h2 style="margin-top: 10px;"><?= htmlspecialchars($ticket['subject']) ?></h2>
            <div class="ticket-meta">
                <span><i class="fa fa-user"></i> <?= htmlspecialchars($ticket['full_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($ticket['username'] ?? 'N/A') ?>)</span>
                <span><i class="fa fa-clock"></i> <?= date('M d, Y h:i A', strtotime($ticket['created_at'])) ?></span>
                <span><i class="fa fa-bolt"></i> Priority: <?= $ticket['priority'] ?></span>
            </div>
        </div>
        <div>
            <a href="tickets.php" class="btn" style="background: #f1f5f9; color: #64748b;"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="chat-box">
        <h4 style="margin-bottom: 20px; color: #475569; font-size: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Conversation History</h4>
        
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <!-- Original Message -->
            <div class="reply-item user-reply">
                <div class="reply-bubble">
                    <strong><?= htmlspecialchars($ticket['username'] ?? 'User') ?>:</strong><br>
                    <?= nl2br(htmlspecialchars($ticket['message'])) ?>
                </div>
                <div class="reply-meta"><?= date('M d, h:i A', strtotime($ticket['created_at'])) ?></div>
            </div>

            <!-- Replies -->
            <?php while($r = $replies->fetch_assoc()): ?>
                <div class="reply-item <?= $r['sender'] == 'Admin' ? 'admin-reply' : 'user-reply' ?>">
                    <div class="reply-bubble">
                        <strong><?= htmlspecialchars($r['sender']) ?>:</strong><br>
                        <?= nl2br(htmlspecialchars($r['message'])) ?>
                    </div>
                    <div class="reply-meta"><?= date('M d, h:i A', strtotime($r['created_at'])) ?></div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <?php if ($ticket['status'] != 'Closed'): ?>
    <div class="reply-form-card">
        <h4 style="margin-bottom: 15px; color: #1e293b;">Post a Reply</h4>
        <form method="POST">
            <textarea name="message" class="form-control" rows="4" placeholder="Type your reply here..." required></textarea>
            
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label style="font-size: 13px; font-weight: 600; color: #64748b;">Change Status:</label>
                    <select name="status" class="form-control" style="margin-bottom: 0; padding: 8px 15px; width: 150px;">
                        <option value="Open" <?= $ticket['status']=='Open'?'selected':'' ?>>Open</option>
                        <option value="In Progress" <?= $ticket['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                        <option value="Closed" <?= $ticket['status']=='Closed'?'selected':'' ?>>Closed</option>
                    </select>
                </div>
                <button type="submit" name="reply" class="btn btn-primary" style="padding: 10px 25px; border-radius: 8px;">
                    <i class="fa fa-paper-plane"></i> Send Reply
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <div style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 15px; border: 1px dashed #cbd5e1; color: #64748b;">
            <i class="fa fa-lock"></i> This ticket is closed. Re-open it to post a reply.
        </div>
    <?php endif; ?>
</div>

<?php include $base_path . 'includes/footer.php'; ?>
