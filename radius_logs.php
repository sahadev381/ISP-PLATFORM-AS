<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "RADIUS Authentication Logs";
$active = "nas";

// Fetch last 100 auth attempts
$logs = $conn->query("
    SELECT * FROM radpostauth 
    ORDER BY authdate DESC 
    LIMIT 100
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 20px;">
    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3><i class="fa fa-clipboard-list" style="color:#3b82f6;"></i> RADIUS Live Auth Logs</h3>
            <button onclick="location.reload()" class="btn btn-sm btn-primary"><i class="fa fa-sync"></i> Refresh Logs</button>
        </div>



        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;">Time</th>
                    <th style="padding: 15px;">Username</th>
                    <th style="padding: 15px;">Password (Raw)</th>
                    <th style="padding: 15px;">Server Reply</th>
                </tr>
            </thead>
            <tbody>
                <?php if($logs->num_rows > 0): ?>
                    <?php while($l = $logs->fetch_assoc()): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px; font-size: 12px; color: #64748b;"><?= $l['authdate'] ?></td>
                        <td style="padding: 15px;"><b><?= htmlspecialchars($l['username']) ?></b></td>
                        <td style="padding: 15px; font-family: monospace; font-size: 12px;"><?= htmlspecialchars($l['pass']) ?></td>
                        <td style="padding: 15px;">
                            <?php if($l['reply'] == 'Access-Accept'): ?>
                                <span class="badge" style="background:#dcfce7; color:#16a34a;">ACCEPTED</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fee2e2; color:#ef4444;">REJECTED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 50px; color: #94a3b8;">No authentication requests detected yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
