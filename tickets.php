<?php
$base_path = './';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Support Tickets";
$active = "tickets";

if (!isset($conn)) { die("Database connection failed."); }

if (isset($_GET['delete'])) {
    $ticket_id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    if ($stmt->execute()) {
        header("Location: tickets.php?msg=deleted");
        exit;
    }
}

$total_tickets = $conn->query("SELECT COUNT(*) as c FROM tickets")->fetch_assoc()['c'];
$open_tickets = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='Open'")->fetch_assoc()['c'];
$inprogress_tickets = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='In Progress'")->fetch_assoc()['c'];
$closed_tickets = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='Closed'")->fetch_assoc()['c'];

$tickets = $conn->query("
    SELECT t.*, c.username, c.full_name
    FROM tickets t 
    LEFT JOIN customers c ON t.customer_id = c.id 
    ORDER BY t.created_at DESC
");

include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .ticket-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .ticket-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        border: 1px solid #e2e8f0;
    }
    .ticket-card h4 { margin: 0 0 10px; font-size: 13px; color: #64748b; text-transform: uppercase; }
    .ticket-card .num { font-size: 28px; font-weight: 700; color: #1e293b; }
    .ticket-card.total { border-left: 4px solid #3b82f6; }
    .ticket-card.open { border-left: 4px solid #ef4444; }
    .ticket-card.progress { border-left: 4px solid #f59e0b; }
    .ticket-card.closed { border-left: 4px solid #10b981; }
    .btn-new { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
    .btn-new:hover { background: #2563eb; }
    .filter-links { margin-bottom: 20px; }
    .filter-links a { margin-right: 15px; color: #64748b; text-decoration: none; font-size: 14px; }
    .filter-links a:hover, .filter-links a.active { color: #3b82f6; font-weight: 600; }
    .action-btn { width: 30px; height: 30px; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .btn-view { background: #eff6ff; color: #3b82f6; }
    .btn-view:hover { background: #3b82f6; color: #fff; }
    .btn-delete { background: #fef2f2; color: #ef4444; }
    .btn-delete:hover { background: #ef4444; color: #fff; }
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .badge-open { background: #fee2e2; color: #dc2626; }
    .badge-inprogress { background: #fef3c7; color: #d97706; }
    .badge-pending { background: #e0e7ff; color: #4f46e5; }
    .badge-closed { background: #f1f5f9; color: #64748b; }
    .priority-high { background: #fee2e2; color: #dc2626; }
    .priority-medium { background: #fef3c7; color: #d97706; }
    .priority-low { background: #dcfce7; color: #16a34a; }
</style>

<div class="dashboard-container" style="padding: 20px;">
    
    <?php if(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
        <div style="background: #dcfce7; color: #16a34a; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">Ticket deleted successfully!</div>
    <?php endif; ?>
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0; color: #1e293b; font-weight: 700;">Support Tickets</h3>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 13px;">Manage customer support requests</p>
    </div>

    <!-- Stats Cards -->
    <div class="ticket-grid">
        <div class="ticket-card total">
            <h4><i class="fa fa-ticket-alt"></i> Total Tickets</h4>
            <div class="num"><?= $total_tickets ?></div>
        </div>
        <div class="ticket-card open">
            <h4><i class="fa fa-envelope-open"></i> Open</h4>
            <div class="num"><?= $open_tickets ?></div>
        </div>
        <div class="ticket-card progress">
            <h4><i class="fa fa-spinner"></i> In Progress</h4>
            <div class="num"><?= $inprogress_tickets ?></div>
        </div>
        <div class="ticket-card closed">
            <h4><i class="fa fa-check-circle"></i> Closed</h4>
            <div class="num"><?= $closed_tickets ?></div>
        </div>
    </div>
    
    <!-- Filter Links -->
    <div class="filter-links">
        <a href="tickets.php" class="active">All (<?= $total_tickets ?>)</a>
        <a href="tickets.php?status=Open">Open (<?= $open_tickets ?>)</a>
        <a href="tickets.php?status=In+Progress">In Progress (<?= $inprogress_tickets ?>)</a>
        <a href="tickets.php?status=Closed">Closed (<?= $closed_tickets ?>)</a>
        <a href="ticket_new.php" class="btn-new" style="float: right;"><i class="fa fa-plus"></i> New Ticket</a>
    </div>

    <!-- Tickets Table -->
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-list"></i> All Tickets</h3>
        </div>
        <table style="width:100%;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="padding: 12px; text-align: left;">ID</th>
                    <th style="padding: 12px; text-align: left;">Customer</th>
                    <th style="padding: 12px; text-align: left;">Subject</th>
                    <th style="padding: 12px; text-align: left;">Priority</th>
                    <th style="padding: 12px; text-align: left;">Status</th>
                    <th style="padding: 12px; text-align: left;">Created</th>
                    <th style="padding: 12px; text-align: left;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($ticket = $tickets->fetch_assoc()): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px;">#<?= $ticket['id'] ?></td>
                    <td style="padding: 12px;">
                        <strong><?= htmlspecialchars($ticket['full_name'] ?: $ticket['username'] ?: 'N/A') ?></strong>
                        <?php if($ticket['username']): ?>
                        <br><small style="color: #64748b;">@<?= $ticket['username'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px;"><?= htmlspecialchars($ticket['subject']) ?></td>
                    <td style="padding: 12px;">
                        <span class="badge priority-<?= strtolower($ticket['priority']) ?>"><?= $ticket['priority'] ?></span>
                    </td>
                    <td style="padding: 12px;">
                        <span class="badge badge-<?= strtolower(str_replace(' ', '', $ticket['status'])) ?>"><?= $ticket['status'] ?></span>
                    </td>
                    <td style="padding: 12px; color: #64748b; font-size: 13px;"><?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?></td>
                    <td style="padding: 12px;">
                        <div style="display: flex; gap: 5px;">
                            <a href="ticket_view.php?id=<?= $ticket['id'] ?>" class="action-btn btn-view" title="View"><i class="fa fa-eye"></i></a>
                            <a href="?delete=<?= $ticket['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Delete this ticket?')"><i class="fa fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>
