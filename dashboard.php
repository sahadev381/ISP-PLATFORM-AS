<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'includes/genieacs_api.php';

$page_title = "Dashboard";
$active = "dashboard";

include 'includes/auth.php';

// Main stats
$total_users   = $conn->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'] ?? 0;
$active_users  = $conn->query("SELECT COUNT(*) c FROM customers WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$expired_users = $conn->query("SELECT COUNT(*) c FROM customers WHERE expiry < CURDATE()")->fetch_assoc()['c'] ?? 0;
$online_users  = $conn->query("SELECT COUNT(*) c FROM radacct WHERE acctstoptime IS NULL")->fetch_assoc()['c'] ?? 0;

// Financial stats
$today_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM invoices WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$month_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM invoices WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;

// Expiring soon
$expiring_soon = $conn->query("SELECT COUNT(*) c FROM customers WHERE expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0;
$total_tickets = $conn->query("SELECT COUNT(*) c FROM tickets WHERE status='Open'")->fetch_assoc()['c'] ?? 0;

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .monitor-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .monitor-card {
        background: #fff;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        border: 1px solid #f1f5f9;
    }
    .monitor-card h4 {
        margin: 0 0 15px;
        font-size: 14px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .monitor-stat {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 13px;
    }
    .monitor-stat label { color: #94a3b8; font-weight: 500; }
    .monitor-stat span { color: #1e293b; font-weight: 600; }
    
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .dot-online { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.5); }
    .dot-offline { background: #ef4444; }

    .progress-bar { height: 6px; background: #f1f5f9; border-radius: 10px; margin-top: 10px; overflow: hidden; }
    .progress-fill { height: 100%; background: #3b82f6; width: 0%; transition: width 0.5s ease; }
</style>

<div class="dashboard-container" style="padding: 20px;">
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0; color: #1e293b; font-weight: 700;">Infrastructure Monitor</h3>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 13px;">Real-time backend service analytics</p>
    </div>

    <!-- Real-time Detailed Monitors -->
    <div class="monitor-grid">
        <!-- Database Card -->
        <div class="monitor-card">
            <h4><i class="fa fa-database" style="color: #3b82f6;"></i> Database Server</h4>
            <div class="monitor-stat">
                <label>Status</label>
                <span id="db-status"><span class="status-dot dot-online"></span> Online</span>
            </div>
            <div class="monitor-stat">
                <label>Uptime</label>
                <span id="db-uptime">Loading...</span>
            </div>
            <div class="monitor-stat">
                <label>Version</label>
                <span id="db-version">Loading...</span>
            </div>
        </div>

        <!-- RADIUS Card -->
        <div class="monitor-card">
            <h4><i class="fa fa-shield-alt" style="color: #8b5cf6;"></i> FreeRADIUS Engine</h4>
            <div class="monitor-stat">
                <label>Auth Today</label>
                <span id="rad-auth" style="color: #10b981;">0 Success</span>
            </div>
            <div class="monitor-stat">
                <label>Rejections</label>
                <span id="rad-fail" style="color: #ef4444;">0 Failed</span>
            </div>
            <div class="monitor-stat">
                <label>Process</label>
                <span id="rad-status">Checking...</span>
            </div>
        </div>

        <!-- GenieACS Card -->
        <div class="monitor-card">
            <h4><i class="fa fa-microchip" style="color: #f59e0b;"></i> GenieACS API</h4>
            <div class="monitor-stat">
                <label>Connection</label>
                <span id="acs-status">Checking...</span>
            </div>
            <div class="monitor-stat">
                <label>Task Queue</label>
                <span id="acs-tasks">0 Pending</span>
            </div>
            <div class="monitor-stat">
                <label>API Latency</label>
                <span id="acs-lat">Stable</span>
            </div>
        </div>

        <!-- System Resources Card -->
        <div class="monitor-card">
            <h4><i class="fa fa-server" style="color: #64748b;"></i> Server Resources</h4>
            <div class="monitor-stat">
                <label>CPU Load</label>
                <span id="sys-cpu">0.00</span>
            </div>
            <div class="monitor-stat">
                <label>RAM Usage</label>
                <span id="sys-mem">0%</span>
            </div>
            <div class="progress-bar"><div id="mem-bar" class="progress-fill"></div></div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 8px;" id="sys-uptime">Server uptime: Loading...</div>
        </div>

        <!-- NEW: Network Health Card -->
        <div class="monitor-card" style="border-left: 5px solid #ef4444; background: #fff;">
            <h4><i class="fa fa-triangle-exclamation" style="color: #ef4444;"></i> Infrastructure Alarms</h4>
            <div id="network-alarms" style="max-height: 100px; overflow-y: auto;">
                <div style="padding: 10px; text-align: center; color: #10b981; font-size: 13px;">
                    <i class="fa fa-check-circle"></i> All devices online
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts - Splynx Style -->
    <div style="margin-bottom: 25px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="add_user.php" class="shortcut-btn" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; text-decoration: none; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);">
                <i class="fa fa-user-plus"></i> Add Customer
            </a>
            <a href="tickets.php?action=new" class="shortcut-btn" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; text-decoration: none; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3);">
                <i class="fa fa-ticket-alt"></i> New Ticket
            </a>
            <a href="recharge.php" class="shortcut-btn" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: linear-gradient(135deg, #10b981, #059669); color: white; text-decoration: none; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);">
                <i class="fa fa-credit-card"></i> Recharge
            </a>
            <a href="leads.php" class="shortcut-btn" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; text-decoration: none; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 6px rgba(139, 92, 246, 0.3);">
                <i class="fa fa-user-plus"></i> Add Lead
            </a>
            <a href="report/new_users.php" class="shortcut-btn" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: linear-gradient(135deg, #64748b, #475569); color: white; text-decoration: none; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 6px rgba(100, 116, 139, 0.3);">
                <i class="fa fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>

    <!-- Main Stats Section - Clickable Cards -->
    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0; color: #1e293b; font-weight: 700;">Business Summary</h3>
    </div>

    <div class="cards" style="margin-bottom: 25px;">
        <a href="users.php" class="card" style="text-decoration: none; color: inherit;"><div class="card-header"><div><div class="card-title">Total Customers</div><div class="card-value"><?= number_format($total_users) ?></div></div><div class="card-icon blue"><i class="fa fa-users"></i></div></div></a>
        <a href="users.php?status=active" class="card" style="text-decoration: none; color: inherit;"><div class="card-header"><div><div class="card-title">Active Users</div><div class="card-value"><?= number_format($active_users) ?></div></div><div class="card-icon green"><i class="fa fa-user-check"></i></div></div></a>
        <a href="online_users.php" class="card" style="text-decoration: none; color: inherit;"><div class="card-header"><div><div class="card-title">Online Now</div><div class="card-value"><?= number_format($online_users) ?></div></div><div class="card-icon cyan"><i class="fa fa-wifi"></i></div></div></a>
        <a href="expired_users.php" class="card" style="text-decoration: none; color: inherit;"><div class="card-header"><div><div class="card-title">Expired Users</div><div class="card-value"><?= number_format($expired_users) ?></div></div><div class="card-icon red"><i class="fa fa-user-xmark"></i></div></div></a>
    </div>

    <div class="cards" style="margin-bottom: 25px;">
        <div class="card"><div class="card-header"><div><div class="card-title">Today's Revenue</div><div class="card-value">NPR <?= number_format($today_revenue) ?></div></div><div class="card-icon green"><i class="fa fa-sack-dollar"></i></div></div></div>
        <div class="card"><div class="card-header"><div><div class="card-title">Monthly Revenue</div><div class="card-value">NPR <?= number_format($month_revenue) ?></div></div><div class="card-icon blue"><i class="fa fa-calendar"></i></div></div></div>
        <a href="report/expiring_users.php" class="card" style="text-decoration: none; color: inherit;"><div class="card-header"><div><div class="card-title">Expiring Soon</div><div class="card-value"><?= $expiring_soon ?></div></div><div class="card-icon orange"><i class="fa fa-clock"></i></div></div></a>
        <a href="tickets.php" class="card" style="text-decoration: none; color: inherit;"><div class="card-header"><div><div class="card-title">Open Tickets</div><div class="card-value"><?= $total_tickets ?></div></div><div class="card-icon red"><i class="fa fa-headset"></i></div></div></a>
    </div>

    <!-- Customer Statistics - Splynx Style -->
    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px; color: #1e293b; font-weight: 700;">Customer Statistics</h3>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <?php
            // Get customer stats by status
            $active_cust = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='active'")->fetch_assoc()['c'] ?? 0;
            $blocked_cust = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='blocked'")->fetch_assoc()['c'] ?? 0;
            $new_today = $conn->query("SELECT COUNT(*) as c FROM customers WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
            $new_week = $conn->query("SELECT COUNT(*) as c FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0;
            ?>
            <div style="background: white; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Active Customers</div>
                <div style="font-size: 24px; font-weight: 700; color: #10b981;"><?= number_format($active_cust) ?></div>
                <div style="font-size: 11px; color: #94a3b8;"><?= round(($active_cust / max($total_users, 1)) * 100) ?>% of total</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Blocked Customers</div>
                <div style="font-size: 24px; font-weight: 700; color: #ef4444;"><?= number_format($expired_users) ?></div>
                <div style="font-size: 11px; color: #94a3b8;">Requires attention</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">New Today</div>
                <div style="font-size: 24px; font-weight: 700; color: #3b82f6;"><?= $new_today ?></div>
                <div style="font-size: 11px; color: #94a3b8;"><?= date('M d, Y') ?></div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">New This Week</div>
                <div style="font-size: 24px; font-weight: 700; color: #8b5cf6;"><?= $new_week ?></div>
                <div style="font-size: 11px; color: #94a3b8;">Last 7 days</div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="table-box">
            <div class="table-header"><h3>Recent Network Activity</h3></div>
            <table style="width:100%;">
                <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
                <tbody>
                    <?php
                    $logs = $conn->query("SELECT * FROM radacct ORDER BY radacctid DESC LIMIT 5");
                    while($l = $logs->fetch_assoc()):
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($l['username']) ?></td>
                        <td><span class="badge <?= empty($l['acctstoptime']) ? 'active' : 'expired' ?>"><?= empty($l['acctstoptime']) ? 'Online' : 'Offline' ?></span></td>
                        <td style="font-size:12px; color:#64748b;"><?= date('h:i A', strtotime($l['acctstarttime'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="table-box">
            <div class="table-header"><h3>Live Activity Feed</h3></div>
            <div style="max-height: 250px; overflow-y: auto;">
                <?php
                // Get recent activities
                $activities = $conn->query("
                    (SELECT 'ticket' as type, id, subject, status, created_at FROM tickets ORDER BY created_at DESC LIMIT 5)
                    UNION ALL
                    (SELECT 'recharge' as type, id, id as subject, 'completed' as status, created_at FROM recharge ORDER BY id DESC LIMIT 5)
                    ORDER BY created_at DESC LIMIT 8
                ");
                while($a = $activities->fetch_assoc()):
                    $icon = $a['type'] == 'ticket' ? 'fa-ticket-alt' : 'fa-credit-card';
                    $color = $a['type'] == 'ticket' ? '#f59e0b' : '#10b981';
                ?>
                <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; border-bottom: 1px solid #f1f5f9;">
                    <div style="width: 32px; height: 32px; background: <?= $color ?>20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa <?= $icon ?>" style="color: <?= $color ?>; font-size: 12px;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 13px; font-weight: 600; color: #1e293b;">
                            <?= $a['type'] == 'ticket' ? '#' . $a['id'] . ' ' . htmlspecialchars(substr($a['subject'], 0, 30)) : 'Recharge #' . $a['id'] ?>
                        </div>
                        <div style="font-size: 11px; color: #94a3b8;">
                            <?= date('M d, h:i A', strtotime($a['created_at'])) ?>
                        </div>
                    </div>
                    <span class="badge" style="background: <?= $a['status'] == 'Open' ? '#fef3c7' : '#d1fae5' ?>; color: <?= $a['status'] == 'Open' ? '#d97706' : '#059669' ?>; font-size: 10px;">
                        <?= ucfirst($a['status']) ?>
                    </span>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="table-box">
            <div class="table-header"><h3>Quick Control</h3></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <a href="users.php" class="btn-action" style="background:#eff6ff; color:#3b82f6; text-decoration:none; padding:15px; border-radius:10px; display:flex; align-items:center; gap:10px; font-weight:600;"><i class="fa fa-users"></i> User List</a>
                <a href="users.php" class="btn-action" style="background:#f0fdf4; color:#16a34a; text-decoration:none; padding:15px; border-radius:10px; display:flex; align-items:center; gap:10px; font-weight:600;"><i class="fa fa-bolt"></i> Quick Renew</a>
                <a href="genieacs_devices.php" class="btn-action" style="background:#fff7ed; color:#ea580c; text-decoration:none; padding:15px; border-radius:10px; display:flex; align-items:center; gap:10px; font-weight:600;"><i class="fa fa-microchip"></i> ACS Panel</a>
                <a href="online.php" class="btn-action" style="background:#f5f3ff; color:#8b5cf6; text-decoration:none; padding:15px; border-radius:10px; display:flex; align-items:center; gap:10px; font-weight:600;"><i class="fa fa-wifi"></i> Live PPP</a>
            </div>
        </div>
    </div>

    <!-- Charts Section - Splynx Style -->
    <div style="margin-top: 25px;">
        <h3 style="margin: 0 0 15px; color: #1e293b; font-weight: 700;">Analytics Overview</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="table-box">
                <div class="table-header"><h3>Revenue Trend (Last 7 Days)</h3></div>
                <canvas id="revenueChart" height="150"></canvas>
            </div>
            <div class="table-box">
                <div class="table-header"><h3>Customer Status Distribution</h3></div>
                <canvas id="customerChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Revenue (NPR)',
                data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Customer Distribution Chart
    const customerCtx = document.getElementById('customerChart').getContext('2d');
    new Chart(customerCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Expired', 'Blocked', 'Pending'],
            datasets: [{
                data: [<?= $active_users ?>, <?= $expired_users ?>, 50, 20],
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#64748b'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: { 
                legend: { position: 'right' }
            },
            cutout: '65%'
        }
    });

    function updateMonitor() {
    $.getJSON('api_status.php', function(data) {
        // Database
        $('#db-status').html(data.database.status ? '<span class="status-dot dot-online"></span> Online' : '<span class="status-dot dot-offline"></span> Offline');
        $('#db-uptime').text(data.database.uptime);
        $('#db-version').text(data.database.version);

        // Radius
        $('#rad-status').html(data.radius.status ? '<span class="status-dot dot-online"></span> Running' : '<span class="status-dot dot-offline"></span> Stopped');
        $('#rad-auth').text(data.radius.success_today + ' Success');
        $('#rad-fail').text(data.radius.failed_today + ' Failed');

        // ACS
        $('#acs-status').html(data.acs.status ? '<span class="status-dot dot-online"></span> Linked' : '<span class="status-dot dot-offline"></span> Error');
        $('#acs-tasks').text(data.acs.pending_tasks + ' Pending Tasks');

        // System
        $('#sys-cpu').text(data.system.cpu_load);
        $('#sys-mem').text(data.system.mem_usage + '%');
        $('#mem-bar').css('width', data.system.mem_usage + '%');
        $('#sys-uptime').text('Server: ' + data.system.uptime);
        
        // Color RAM bar based on usage
        if(data.system.mem_usage > 80) $('#mem-bar').css('background', '#ef4444');
        else if(data.system.mem_usage > 60) $('#mem-bar').css('background', '#f59e0b');
        else $('#mem-bar').css('background', '#3b82f6');
    });

    // Network Device Monitor
    $.getJSON('api_network_status.php', function(data) {
        let alarmHtml = '';
        let offlineCount = 0;
        data.forEach(d => {
            if(!d.status) {
                offlineCount++;
                alarmHtml += `<div style="padding:5px 0; border-bottom:1px solid #f1f5f9; color:#ef4444; font-size:12px;">
                    <i class="fa fa-times-circle"></i> <b>${d.name}</b> (${d.ip}) is DOWN
                </div>`;
            }
        });
        if(offlineCount === 0) {
            $('#network-alarms').html('<div style="padding:10px; text-align:center; color:#10b981; font-size:13px;"><i class="fa fa-check-circle"></i> All nodes stable</div>');
        } else {
            $('#network-alarms').html(alarmHtml);
        }
    });
}

$(document).ready(function() {
    updateMonitor();
    setInterval(updateMonitor, 10000); // Update every 10 seconds
});
</script>

<?php include 'includes/footer.php'; ?>
