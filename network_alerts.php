<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Network Alerts";
$active = "nas";

$filter = $_GET['filter'] ?? 'all';
$severity = $_GET['severity'] ?? '';

$where = "1=1";
if($filter == 'active') $where = "status = 'active'";
if($filter == 'resolved') $where = "status = 'resolved'";
if($severity) $where .= " AND severity = '$severity'";

$alerts = $conn->query("SELECT * FROM network_alerts WHERE $where ORDER BY created_at DESC LIMIT 100");

$stats = $conn->query("
    SELECT 
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
        SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning,
        COUNT(*) as total
    FROM network_alerts
")->fetch_assoc();

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 25px;">
    
    <div style="margin-bottom: 30px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-bell"></i> Network Alerts</h2>
        <p style="color:#64748b;">Real-time network monitoring alerts from OLT, MikroTik, and Switch devices</p>
    </div>

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 25px; border-radius: 16px; color: #fff;">
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">CRITICAL</div>
            <div style="font-size: 36px; font-weight: 800;"><?= $stats['critical'] ?? 0 ?></div>
        </div>
        <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 25px; border-radius: 16px; color: #fff;">
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">WARNING</div>
            <div style="font-size: 36px; font-weight: 800;"><?= $stats['warning'] ?? 0 ?></div>
        </div>
        <div style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 25px; border-radius: 16px; color: #fff;">
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">ACTIVE ALERTS</div>
            <div style="font-size: 36px; font-weight: 800;"><?= $stats['active'] ?? 0 ?></div>
        </div>
        <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 25px; border-radius: 16px; color: #fff;">
            <div style="font-size: 14px; opacity: 0.9; font-weight: 600;">TOTAL</div>
            <div style="font-size: 36px; font-weight: 800;"><?= $stats['total'] ?? 0 ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div style="background:#fff; border-radius:15px; padding:20px; margin-bottom:20px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
        <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">
            <a href="?filter=all" style="padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; <?= $filter=='all'?'background:#3b82f6; color:#fff;':'background:#f1f5f9; color:#64748b;' ?>">All</a>
            <a href="?filter=active" style="padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; <?= $filter=='active'?'background:#3b82f6; color:#fff;':'background:#f1f5f9; color:#64748b;' ?>">Active</a>
            <a href="?filter=resolved" style="padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; <?= $filter=='resolved'?'background:#3b82f6; color:#fff;':'background:#f1f5f9; color:#64748b;' ?>">Resolved</a>
            
            <select onchange="window.location.href='?filter=<?= $filter ?>&severity='+this.value" style="padding:10px 15px; border:1px solid #e2e8f0; border-radius:8px; margin-left:auto;">
                <option value="">All Severities</option>
                <option value="critical" <?= $severity=='critical'?'selected':'' ?>>Critical</option>
                <option value="warning" <?= $severity=='warning'?'selected':'' ?>>Warning</option>
                <option value="info" <?= $severity=='info'?'selected':'' ?>>Info</option>
            </select>
        </div>
    </div>

    <!-- Alerts Table -->
    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;"><i class="fa fa-exclamation-circle"></i> Alert</th>
                    <th style="padding: 15px;"><i class="fa fa-server"></i> Device</th>
                    <th style="padding: 15px;"><i class="fa fa-bolt"></i> Type</th>
                    <th style="padding: 15px;"><i class="fa fa-flag"></i> Severity</th>
                    <th style="padding: 15px;"><i class="fa fa-clock"></i> Time</th>
                    <th style="padding: 15px;"><i class="fa fa-cog"></i> Status</th>
                    <th style="padding: 15px;"><i class="fa fa-cog"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($alerts->num_rows > 0): ?>
                    <?php while($alert = $alerts->fetch_assoc()): ?>
                    <?php 
                        $severity_colors = [
                            'critical' => '#ef4444',
                            'warning' => '#f59e0b', 
                            'info' => '#3b82f6'
                        ];
                        $color = $severity_colors[$alert['severity']] ?? '#64748b';
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px;">
                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($alert['message']) ?></div>
                        </td>
                        <td style="padding: 15px;">
                            <div style="font-weight: 600;"><?= htmlspecialchars($alert['device_name']) ?></div>
                            <small style="color:#94a3b8;"><?= $alert['device_ip'] ?? '' ?></small>
                        </td>
                        <td style="padding: 15px;">
                            <span style="padding: 5px 10px; border-radius: 6px; background: #f1f5f9; font-size: 12px; font-weight: 600;">
                                <?= htmlspecialchars($alert['alert_type']) ?>
                            </span>
                        </td>
                        <td style="padding: 15px;">
                            <span style="padding: 5px 12px; border-radius: 20px; background: <?= $color ?>20; color: <?= $color ?>; font-weight: 700; font-size: 12px; text-transform: uppercase;">
                                <?= $alert['severity'] ?>
                            </span>
                        </td>
                        <td style="padding: 15px; font-size: 12px; color: #64748b;">
                            <?= date('M d, H:i', strtotime($alert['created_at'])) ?>
                        </td>
                        <td style="padding: 15px;">
                            <span class="badge <?= $alert['status'] == 'active' ? 'active' : 'inactive' ?>">
                                <?= $alert['status'] ?>
                            </span>
                        </td>
                        <td style="padding: 15px;">
                            <?php if($alert['status'] == 'active'): ?>
                            <button onclick="resolveAlert(<?= $alert['id'] ?>)" class="btn btn-sm" style="background:#dcfce7; color:#16a34a; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px;">
                                <i class="fa fa-check"></i> Resolve
                            </button>
                            <?php else: ?>
                            <span style="color:#94a3b8; font-size:12px;">Resolved</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding: 50px; color: #94a3b8;">
                            <i class="fa fa-bell-slash" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                            No alerts found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function resolveAlert(id) {
    if(confirm('Mark this alert as resolved?')) {
        fetch('api/resolve_alert.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            if(data.success) location.reload();
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>
