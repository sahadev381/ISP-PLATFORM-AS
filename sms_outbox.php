<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "SMS Outbox & Logs";
$active = "sms";

$logs = $conn->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 500");
$stats = $conn->query("SELECT status, COUNT(*) as count FROM sms_logs GROUP BY status")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 20px;">
    <!-- SMS Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <?php foreach($stats as $s): 
            $color = ($s['status'] == 'sent') ? '#10b981' : (($s['status'] == 'failed') ? '#ef4444' : '#f59e0b');
        ?>
            <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 5px solid <?= $color ?>; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="font-size: 13px; color: #64748b; font-weight: 600;"><?= strtoupper($s['status']) ?> MESSAGES</div>
                <div style="font-size: 24px; font-weight: 700; color: #1e293b;"><?= $s['count'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <h3><i class="fa fa-paper-plane"></i> Message Transmission Logs</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;">Recipient</th>
                    <th style="padding: 15px;">Message</th>
                    <th style="padding: 15px;">Status</th>
                    <th style="padding: 15px;">Time Sent</th>
                </tr>
            </thead>
            <tbody>
                <?php while($l = $logs->fetch_assoc()): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 15px;">
                        <b><?= htmlspecialchars($l['customer_user']) ?></b><br>
                        <small style="color: #64748b;"><?= $l['phone_number'] ?></small>
                    </td>
                    <td style="padding: 15px; font-size: 13px; max-width: 400px;"><?= htmlspecialchars($l['message']) ?></td>
                    <td style="padding: 15px;">
                        <span class="badge" style="background: <?= ($l['status'] == 'sent') ? '#dcfce7; color: #16a34a;' : '#fee2e2; color: #ef4444;' ?>">
                            <?= strtoupper($l['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 15px; font-size: 12px; color: #64748b;">
                        <?= date('M d, Y h:i A', strtotime($l['sent_at'])) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
