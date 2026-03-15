<?php
$base_path = '../';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Expired Customers List";
$active = "reports";

// Check if $conn exists
if (!isset($conn)) {
    die("Database connection failed.");
}

// Fetch Expired Users with Plan Info - REMOVED u.id
$query = "
    SELECT 
        u.username,
        u.expiry,
        u.status,
        u.phone,
        p.name AS plan_name,
        p.speed
    FROM customers u
    LEFT JOIN plans p ON u.plan_id = p.id
    WHERE u.expiry < CURDATE()
    ORDER BY u.expiry DESC
";
$result = $conn->query($query);

include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .report-container { padding: 20px; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; overflow: hidden; }
    .card-header { padding: 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
    .card-header h2 { margin: 0; font-size: 18px; font-weight: 600; color: #1e293b; }
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: #f8fafc; padding: 15px 20px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; }
    tr:hover { background: #f8fafc; }
    .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .badge-expired { background: #fee2e2; color: #ef4444; }
    .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s; }
    .btn-renew { background: #3b82f6; color: #fff; }
    .btn-view { background: #f1f5f9; color: #64748b; }
    .btn-export { background: #10b981; color: #fff; border: none; cursor: pointer; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .empty-state { padding: 40px; text-align: center; color: #64748b; }
</style>

<div class="report-container">
    <div class="card">
        <div class="card-header">
            <h2>Expired Customers (<?= $result ? $result->num_rows : 0 ?>)</h2>
            <form method="post" action="export_expired_users.php">
                <button type="submit" class="btn-export"><i class="fa fa-file-csv"></i> Export CSV</button>
            </form>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan & Speed</th>
                        <th>Phone</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><div style="font-weight: 600;"><?= htmlspecialchars($row['username']) ?></div></td>
                                <td>
                                    <div><?= htmlspecialchars($row['plan_name'] ?? 'No Plan') ?></div>
                                    <div style="font-size: 12px; color: #10b981;"><?= htmlspecialchars($row['speed'] ?? '-') ?></div>
                                </td>
                                <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <div style="color: #ef4444; font-weight: 500;"><?= date('M d, Y', strtotime($row['expiry'])) ?></div>
                                    <div style="font-size: 12px; color: #94a3b8;"><?= floor((time() - strtotime($row['expiry'])) / 86400) ?> days ago</div>
                                </td>
                                <td><span class="badge badge-expired">Expired</span></td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="<?= $base_path ?>user_view.php?username=<?= urlencode($row['username']) ?>" class="btn-action btn-view" title="View Details"><i class="fa fa-eye"></i></a>
                                        <a href="<?= $base_path ?>recharge.php?user=<?= urlencode($row['username']) ?>" class="btn-action btn-renew" title="Recharge Now"><i class="fa fa-bolt"></i> Renew</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><div class="empty-state"><p>No expired customers found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include $base_path . 'includes/footer.php'; ?>
