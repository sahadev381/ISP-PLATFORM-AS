<?php
$base_path = '../';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "New Customers (Last 30 Days)";
$active = "reports";

// Check if $conn exists
if (!isset($conn)) {
    die("Database connection failed.");
}

// Fetch New Users - REMOVED u.id
$query = "
    SELECT 
        u.username,
        u.expiry,
        u.status,
        u.created_at,
        p.name AS plan_name,
        p.speed
    FROM customers u
    LEFT JOIN plans p ON u.plan_id = p.id
    WHERE u.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY u.created_at DESC
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
    .badge-new { background: #dcfce7; color: #10b981; }
    .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .btn-view { background: #f1f5f9; color: #64748b; }
</style>

<div class="report-container">
    <div class="card">
        <div class="card-header">
            <h2>New Customers (<?= $result ? $result->num_rows : 0 ?>)</h2>
            <form method="post" action="export_new_users.php">
                <button type="submit" style="background:#10b981; color:white; border:none; padding:8px 16px; border-radius:8px; cursor:pointer;"><i class="fa fa-file-csv"></i> Export CSV</button>
            </form>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Joined Date</th>
                        <th>Expiry</th>
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
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td><?= date('M d, Y', strtotime($row['expiry'])) ?></td>
                                <td><span class="badge badge-new">New</span></td>
                                <td>
                                    <a href="<?= $base_path ?>user_view.php?username=<?= urlencode($row['username']) ?>" class="btn-action btn-view"><i class="fa fa-eye"></i> View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color: #64748b;">No new customers found in last 30 days.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include $base_path . 'includes/footer.php'; ?>
