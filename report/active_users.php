<?php
$base_path = '../';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Online Active Users";
$active = "reports";

// Check if $conn exists
if (!isset($conn)) {
    die("Database connection failed. Please check config.php.");
}

/**
 * Fetch Active Users from radacct
 * We joined with customers to get plan and id.
 * Using username as a common link.
 */
$query = "
    SELECT 
        u.username as cust_user,
        u.status,
        p.name AS plan_name,
        p.speed,
        r.username as rad_user,
        r.acctstarttime,
        r.nasipaddress
    FROM radacct r
    LEFT JOIN customers u ON r.username = u.username
    LEFT JOIN plans p ON u.plan_id = p.id
    WHERE r.acctstoptime IS NULL
    ORDER BY r.acctstarttime DESC
";

$result = $conn->query($query);

// Handle Query Error
if ($result === false) {
    // If query failed, we create a dummy object to prevent fatal errors on num_rows
    $error_msg = $conn->error;
    $num_rows = 0;
} else {
    $num_rows = $result->num_rows;
    $error_msg = "";
}

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
    .badge-online { background: #ede9fe; color: #8b5cf6; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .btn-view { background: #f1f5f9; color: #64748b; }
    .btn-disconnect { background: #fee2e2; color: #ef4444; border: none; cursor: pointer; }
    .error-box { background: #fff1f2; color: #e11d48; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fda4af; }
</style>

<div class="report-container">
    <?php if (!empty($error_msg)): ?>
        <div class="error-box">
            <i class="fa fa-exclamation-triangle"></i> <strong>Query Error:</strong> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>Online Users (<?= $num_rows ?>)</h2>
            <form method="post" action="export_active_users.php">
                <button type="submit" style="background:#8b5cf6; color:white; border:none; padding:8px 16px; border-radius:8px; cursor:pointer;"><i class="fa fa-file-csv"></i> Export CSV</button>
            </form>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>NAS IP</th>
                        <th>Login Time</th>
                        <th>Uptime</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $uname = $row['rad_user'];
                            $uptime_sec = time() - strtotime($row['acctstarttime']);
                            $h = floor($uptime_sec / 3600);
                            $m = floor(($uptime_sec % 3600) / 60);
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($uname) ?></div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?= htmlspecialchars($row['cust_user'] ? "Linked Account" : "Unknown User") ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['plan_name'] ?? 'N/A') ?></td>
                                <td><code style="background:#f1f5f9; padding:2px 5px; border-radius:4px;"><?= htmlspecialchars($row['nasipaddress']) ?></code></td>
                                <td><?= date('H:i:s', strtotime($row['acctstarttime'])) ?><br><small><?= date('M d', strtotime($row['acctstarttime'])) ?></small></td>
                                <td style="font-weight: 600; color: #8b5cf6;"><?= $h ?>h <?= $m ?>m</td>
                                <td><span class="badge-online"><i class="fa fa-circle" style="font-size: 8px; vertical-align: middle;"></i> ONLINE</span></td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <a href="<?= $base_path ?>user_view.php?username=<?= urlencode($uname) ?>" class="btn-action btn-view"><i class="fa fa-eye"></i></a>
                                        <form action="<?= $base_path ?>disconnect_user.php" method="POST" onsubmit="return confirm('Disconnect this user?')">
                                            <input type="hidden" name="username" value="<?= htmlspecialchars($uname) ?>">
                                            <button type="submit" class="btn-action btn-disconnect"><i class="fa fa-power-off"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px; color:#64748b;">No users currently online.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include $base_path . 'includes/footer.php'; ?>
