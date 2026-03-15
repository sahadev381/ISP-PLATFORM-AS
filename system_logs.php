<?php
session_start();
$page_title = "System Logs";
$base_path = '';

include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!isSuperAdmin()) {
    die("Access denied.");
}

$active = 'logs';
$log_type = $_GET['type'] ?? 'activity';

// Filter handling
$filter_date = $_GET['date'] ?? '';
$where = "";
if ($filter_date) {
    $where = "WHERE DATE(created_at) = '$filter_date'";
}

$message = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .log-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .log-tab {
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #64748b;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .log-tab:hover {
            background: #f1f5f9;
            color: #3b82f6;
        }
        .log-tab.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        .log-level {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .log-level.info { background: #dbeafe; color: #1d4ed8; }
        .log-level.success { background: #d1fae5; color: #059669; }
        .log-level.warning { background: #fef3c7; color: #d97706; }
        .log-level.danger { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-4">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div>
                    <h2 style="margin: 0; color: #1e293b; font-weight: 700;">
                        <i class="fa fa-server"></i> System Logs
                    </h2>
                    <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Monitor system activities and security events</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <input type="date" class="form-control" value="<?= $filter_date ?>" onchange="window.location.href='?type=<?= $log_type ?>&date='+this.value" style="width: 180px;">
                    <a href="?type=<?= $log_type ?>" class="btn btn-secondary">
                        <i class="fa fa-redo"></i> Reset
                    </a>
                </div>
            </div>
            
            <!-- Log Type Tabs -->
            <div class="log-tabs">
                <a href="?type=activity" class="log-tab <?= $log_type == 'activity' ? 'active' : '' ?>">
                    <i class="fa fa-shield-alt"></i> Activity Log
                </a>
                <a href="?type=login" class="log-tab <?= $log_type == 'login' ? 'active' : '' ?>">
                    <i class="fa fa-sign-in-alt"></i> Login Attempts
                </a>
                <a href="?type=sms" class="log-tab <?= $log_type == 'sms' ? 'active' : '' ?>">
                    <i class="fa fa-sms"></i> SMS Logs
                </a>
                <a href="?type=uptime" class="log-tab <?= $log_type == 'uptime' ? 'active' : '' ?>">
                    <i class="fa fa-clock"></i> Uptime Logs
                </a>
                <a href="?type=auto_invoice" class="log-tab <?= $log_type == 'auto_invoice' ? 'active' : '' ?>">
                    <i class="fa fa-file-invoice"></i> Auto Invoice
                </a>
            </div>
            
            <!-- Log Content -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h5><i class="fa fa-list"></i> 
                        <?php 
                        $titles = [
                            'activity' => 'Admin Activity Log',
                            'login' => 'Login Attempts',
                            'sms' => 'SMS Delivery Logs',
                            'uptime' => 'Network Uptime Logs',
                            'auto_invoice' => 'Auto Invoice Logs'
                        ];
                        echo $titles[$log_type] ?? 'System Logs';
                        ?>
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-primary" onclick="exportLogs()">
                            <i class="fa fa-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="logsTable" class="table table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <?php if($log_type == 'activity'): ?>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                <?php elseif($log_type == 'login'): ?>
                                    <th>Time</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                    <th>IP Address</th>
                                    <th>Message</th>
                                <?php elseif($log_type == 'sms'): ?>
                                    <th>Time</th>
                                    <th>Recipient</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Gateway</th>
                                <?php elseif($log_type == 'uptime'): ?>
                                    <th>Time</th>
                                    <th>Device Type</th>
                                    <th>Target ID</th>
                                    <th>Status</th>
                                    <th>Latency</th>
                                <?php elseif($log_type == 'auto_invoice'): ?>
                                    <th>Time</th>
                                    <th>Month/Year</th>
                                    <th>Total Invoices</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($log_type == 'activity') {
                                $logs = $conn->query("SELECT * FROM activity_log $where ORDER BY created_at DESC LIMIT 500");
                                while ($l = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d, H:i', strtotime($l['created_at'])) ?></td>
                                        <td><strong><?= htmlspecialchars($l['username']) ?></strong></td>
                                        <td><span class="log-level info"><?= strtoupper($l['action']) ?></span></td>
                                        <td><?= htmlspecialchars($l['description']) ?></td>
                                        <td><code><?= $l['ip_address'] ?></code></td>
                                    </tr>
                                <?php endwhile;
                                
                            } elseif ($log_type == 'login') {
                                $logs = $conn->query("SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT 500");
                                while ($l = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d, H:i', strtotime($l['attempt_time'])) ?></td>
                                        <td><strong><?= htmlspecialchars($l['username']) ?></strong></td>
                                        <td>
                                            <?php if($l['status'] == 'success'): ?>
                                                <span class="log-level success">Success</span>
                                            <?php else: ?>
                                                <span class="log-level danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= $l['ip_address'] ?? '-' ?></code></td>
                                        <td><?= htmlspecialchars($l['message'] ?? '-') ?></td>
                                    </tr>
                                <?php endwhile;
                                
                            } elseif ($log_type == 'sms') {
                                $logs = $conn->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 500");
                                while ($l = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d, H:i', strtotime($l['sent_at'])) ?></td>
                                        <td><?= htmlspecialchars($l['phone_number']) ?></td>
                                        <td><?= htmlspecialchars(substr($l['message'], 0, 50)) ?>...</td>
                                        <td>
                                            <?php if($l['status'] == 'sent' || $l['status'] == 'delivered'): ?>
                                                <span class="log-level success"><?= ucfirst($l['status']) ?></span>
                                            <?php else: ?>
                                                <span class="log-level danger"><?= ucfirst($l['status'] ?? 'failed') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $l['gateway'] ?? '-' ?></td>
                                    </tr>
                                <?php endwhile;
                                
                            } elseif ($log_type == 'uptime') {
                                $logs = $conn->query("SELECT * FROM uptime_logs ORDER BY checked_at DESC LIMIT 500");
                                while ($l = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d, H:i', strtotime($l['checked_at'])) ?></td>
                                        <td><strong><?= htmlspecialchars($l['target_type']) ?></strong></td>
                                        <td><code><?= $l['target_id'] ?></code></td>
                                        <td>
                                            <?php if($l['status'] == 'online'): ?>
                                                <span class="log-level success">UP</span>
                                            <?php else: ?>
                                                <span class="log-level danger">DOWN</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $l['latency_ms'] ?? '-' ?></td>
                                    </tr>
                                <?php endwhile;
                                
                            } elseif ($log_type == 'auto_invoice') {
                                $logs = $conn->query("SELECT * FROM auto_invoice_log ORDER BY generated_at DESC LIMIT 500");
                                while ($l = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d, H:i', strtotime($l['generated_at'])) ?></td>
                                        <td><?= $l['month_year'] ?></td>
                                        <td><?= $l['total_invoices'] ?? '-' ?></td>
                                    </tr>
                                <?php endwhile;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#logsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 50
            });
        });
        
        function exportLogs() {
            window.location.href = '?type=<?= $log_type ?>&export=1';
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
