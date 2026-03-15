<?php
include 'config.php';
include 'includes/auth.php';

// Only Super Admins can see full logs
if (!isSuperAdmin()) {
    die("Access denied.");
}

$page_title = "Admin Activity Audit Logs";
$active = "logs";

$logs = $conn->query("
    SELECT * FROM activity_log 
    ORDER BY created_at DESC 
    LIMIT 1000
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 20px;">
    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3><i class="fa fa-shield-halved"></i> System Audit Trail</h3>
            <span style="font-size:12px; color:#64748b;">Showing last 1000 actions</span>
        </div>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;">Admin User</th>
                    <th style="padding: 15px;">Action</th>
                    <th style="padding: 15px;">Description</th>
                    <th style="padding: 15px;">IP Address</th>
                    <th style="padding: 15px;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php while($l = $logs->fetch_assoc()): ?>
                <tr style="border-bottom: 1px solid #f1f5f9; font-size: 13px;">
                    <td style="padding: 15px;"><b><?= htmlspecialchars($l['username']) ?></b></td>
                    <td style="padding: 15px;">
                        <span class="badge" style="background:#f1f5f9; color:#475569; font-size:10px;">
                            <?= strtoupper($l['action']) ?>
                        </span>
                    </td>
                    <td style="padding: 15px; color:#475569;"><?= htmlspecialchars($l['description']) ?></td>
                    <td style="padding: 15px; font-family:monospace; color:#94a3b8;"><?= $l['ip_address'] ?></td>
                    <td style="padding: 15px; color:#64748b;"><?= date('M d, h:i A', strtotime($l['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
