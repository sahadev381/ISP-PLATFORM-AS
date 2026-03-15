<?php
$base_path = '../';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Expiring Soon (7 Days)";
$active = "reports";

// Check if $conn exists
if (!isset($conn)) {
    die("Database connection failed.");
}

// Fetch Users Expiring - REMOVED u.id
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
    WHERE u.expiry >= CURDATE() AND u.expiry <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY u.expiry ASC
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
    .badge-warning { background: #fff7ed; color: #f59e0b; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .btn-renew { background: #f59e0b; color: white; }
    .btn-view { background: #f1f5f9; color: #64748b; }
</style>

<div class="report-container">
    <div class="card">
        <div class="card-header">
            <h2>Expiring Soon (<?= $result ? $result->num_rows : 0 ?>)</h2>
            <form method="post" action="export_expiring_users.php">
                <button type="submit" style="background:#f59e0b; color:white; border:none; padding:8px 16px; border-radius:8px; cursor:pointer;"><i class="fa fa-file-csv"></i> Export CSV</button>
            </form>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Phone</th>
                        <th>Expiry Date</th>
                        <th>Time Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $days_left = ceil((strtotime($row['expiry']) - time()) / 86400);
                        ?>
                            <tr>
                                <td><div style="font-weight: 600;"><?= htmlspecialchars($row['username']) ?></div></td>
                                <td><?= htmlspecialchars($row['plan_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                                <td style="font-weight: 600; color: #f59e0b;"><?= date('M d, Y', strtotime($row['expiry'])) ?></td>
                                <td><span class="badge-warning"><?= $days_left ?> Days Left</span></td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <a href="<?= $base_path ?>user_view.php?username=<?= urlencode($row['username']) ?>" class="btn-action btn-view"><i class="fa fa-eye"></i></a>
                                        <a href="<?= $base_path ?>recharge.php?user=<?= urlencode($row['username']) ?>" class="btn-action btn-renew"><i class="fa fa-bolt"></i> Renew</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding:40px; color:#64748b;">No customers expiring in the next 7 days.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include $base_path . 'includes/footer.php'; ?>
