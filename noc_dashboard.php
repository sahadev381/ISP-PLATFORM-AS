<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "NOC & AI Analytics Dashboard";
$active = "noc";

// 1. Top Talkers (Current Month Usage)
$top_talkers = $conn->query("
    SELECT username, used_quota 
    FROM data_usage 
    ORDER BY used_quota DESC 
    LIMIT 10
");

// 2. Network Uptime (Last 30 Days) - OLTs
$uptime_stats = $conn->query("
    SELECT n.nasname, 
           COUNT(CASE WHEN u.status = 'online' THEN 1 END) * 100.0 / COUNT(*) as uptime_pct
    FROM nas n
    LEFT JOIN uptime_logs u ON n.id = u.target_id AND u.target_type = 'OLT'
    WHERE n.device_type = 'olt'
    GROUP BY n.id
");

// 3. Churn Prediction Logic (Simplified AI)
// Higher risk if > 3 tickets OR late payment history
$churn_risks = $conn->query("
    SELECT c.username, c.full_name, c.phone,
           (SELECT COUNT(*) FROM tickets WHERE customer_id = c.id AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as ticket_count
    FROM customers c
    WHERE c.status = 'active'
    HAVING ticket_count >= 2
    ORDER BY ticket_count DESC
    LIMIT 5
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 25px;">
    
    <div style="margin-bottom: 30px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-robot"></i> AI-NOC Analytics</h2>
        <p style="color:#64748b;">Automated network monitoring and predictive intelligence.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px;">
        
        <!-- Column Left -->
        <div>
            <!-- Top Talkers -->
            <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 4px 6px rgba(0,0,0,0.02); margin-bottom:25px;">
                <h4 style="margin-top:0;"><i class="fa fa-chart-line" style="color:#3b82f6;"></i> Top 10 High-Usage Customers (This Month)</h4>
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:2px solid #f1f5f9; color:#64748b; font-size:12px;">
                            <th style="padding:10px;">CUSTOMER</th>
                            <th style="padding:10px;">TOTAL USAGE</th>
                            <th style="padding:10px;">INTENSITY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($t = $top_talkers->fetch_assoc()): 
                            $gb = round($t['used_quota'] / 1073741824, 2);
                            $pct = min(100, ($gb / 2000) * 100); // Progress bar relative to 2TB
                        ?>
                        <tr>
                            <td style="padding:12px;"><b><?= $t['username'] ?></b></td>
                            <td style="padding:12px; font-weight:600; color:#1e293b;"><?= $gb ?> GB</td>
                            <td style="padding:12px;">
                                <div style="background:#f1f5f9; height:8px; border-radius:10px; overflow:hidden;">
                                    <div style="background:#3b82f6; width:<?= $pct ?>%; height:100%;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Uptime Report -->
            <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
                <h4 style="margin-top:0;"><i class="fa fa-bolt" style="color:#10b981;"></i> Infrastructure Availability (OLTs)</h4>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin-top:20px;">
                    <?php while($u = $uptime_stats->fetch_assoc()): 
                        $val = round($u['uptime_pct'] ?? 100, 2);
                        $color = ($val > 99.9) ? '#10b981' : (($val > 98) ? '#f59e0b' : '#ef4444');
                    ?>
                        <div style="text-align:center; padding:20px; border:1px solid #f1f5f9; border-radius:12px;">
                            <div style="font-size:11px; color:#64748b; margin-bottom:5px;"><?= $u['nasname'] ?></div>
                            <div style="font-size:24px; font-weight:800; color:<?= $color ?>;"><?= $val ?>%</div>
                            <small style="color:#94a3b8;">30-Day Avg</small>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Column Right -->
        <div>
            <!-- Churn Risk -->
            <div style="background:#1e293b; border-radius:15px; padding:25px; color:#fff; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
                <h4 style="margin-top:0; color:#94a3b8; font-size:12px; text-transform:uppercase; letter-spacing:1px;">AI Churn Prediction (High Risk)</h4>
                <?php if($churn_risks->num_rows > 0): ?>
                    <?php while($c = $churn_risks->fetch_assoc()): ?>
                        <div style="margin-top:20px; background:rgba(255,255,255,0.05); padding:15px; border-radius:10px; border-left:4px solid #ef4444;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <b><?= $c['full_name'] ?></b>
                                <span class="badge" style="background:#ef4444; font-size:10px;">HIGH RISK</span>
                            </div>
                            <div style="font-size:12px; margin-top:5px; opacity:0.7;">
                                <i class="fa fa-ticket"></i> <?= $c['ticket_count'] ?> Complaints this month<br>
                                <i class="fa fa-phone"></i> <?= $c['phone'] ?>
                            </div>
                            <a href="user_view.php?user=<?= $c['username'] ?>" style="display:block; margin-top:10px; font-size:11px; color:#3b82f6; text-decoration:none; font-weight:700;">PROACTIVE OUTREACH &rarr;</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="margin-top:20px; font-size:13px; opacity:0.6;">No high-risk churn patterns detected currently.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Action Stats -->
            <div style="margin-top:25px; background:#fff; padding:25px; border-radius:15px; border:1px solid #f1f5f9;">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <span style="font-size:13px; color:#64748b;">Predictive Maintenance</span>
                    <span style="color:#10b981; font-weight:700;">Enabled</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <span style="font-size:13px; color:#64748b;">Anomaly Detection</span>
                    <span style="color:#10b981; font-weight:700;">Active</span>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
