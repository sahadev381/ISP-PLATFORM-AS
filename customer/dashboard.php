<?php
include '../config.php';
// session_start(); removed as it's in config.php

if (!isset($_SESSION['customer_user'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['customer_user'];

// Fetch detailed customer info
$user = $conn->query("
    SELECT c.*, p.name as plan_name, p.speed as plan_speed,
    COALESCE(du.used_quota, 0) as used_quota
    FROM customers c
    LEFT JOIN plans p ON c.plan_id = p.id
    LEFT JOIN data_usage du ON c.username = du.username
    WHERE c.username = '$username'
")->fetch_assoc();

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

$page_title = "Customer Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; color: #1e293b; }
        .header { background: #fff; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .container { padding: 25px; max-width: 1000px; margin: 0 auto; }
        
        .welcome-card { background: #1e293b; color: #fff; padding: 30px; border-radius: 20px; margin-bottom: 25px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; }
        
        .action-btn { background: #3b82f6; color: #fff; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; margin-top: 15px; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-top: 30px; }
        .menu-item { background: #fff; padding: 20px; border-radius: 15px; text-align: center; text-decoration: none; color: #475569; border: 1px solid #f1f5f9; transition: 0.2s; }
        .menu-item:hover { transform: translateY(-3px); border-color: #3b82f6; color: #3b82f6; }
        .menu-item i { display: block; font-size: 24px; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="header">
    <div style="font-weight: 800; font-size: 20px; color: #3b82f6;"><i class="fa fa-wifi"></i> ISP Portal</div>
    <a href="logout.php" style="color: #64748b; text-decoration: none; font-weight: 600;"><i class="fa fa-sign-out"></i> Logout</a>
</div>

<div class="container">
    <div class="welcome-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h1 style="margin:0;">Hi, <?= htmlspecialchars($user['full_name']) ?></h1>
                <p style="opacity:0.7;">Account ID: <?= $user['username'] ?></p>
            </div>
            <div style="text-align:right;">
                <div style="font-size:12px; opacity:0.7;">WALLET BALANCE</div>
                <div style="font-size:28px; font-weight:800;">NPR <?= number_format($user['wallet'], 2) ?></div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <!-- Subscription -->
        <div class="stat-card">
            <h4 style="margin:0; color:#64748b; font-size:12px; text-transform:uppercase;">Current Plan</h4>
            <div style="font-size:20px; font-weight:700; margin:10px 0;"><?= $user['plan_name'] ?></div>
            <div style="font-size:14px; color:#10b981; font-weight:600;"><i class="fa fa-gauge-high"></i> Speed: <?= $user['plan_speed'] ?></div>
            <div style="font-size:13px; color:#ef4444; margin-top:10px;">Expires: <?= date('M d, Y', strtotime($user['expiry'])) ?></div>
            <a href="../payment/recharge_wallet.php" class="action-btn" style="width:100%; box-sizing:border-box; justify-content:center;">Recharge Now</a>
        </div>

        <!-- Usage -->
        <div class="stat-card">
            <h4 style="margin:0; color:#64748b; font-size:12px; text-transform:uppercase;">Data Consumption</h4>
            <div style="font-size:20px; font-weight:700; margin:10px 0;"><?= formatBytes($user['used_quota']) ?> Used</div>
            <div style="background:#f1f5f9; height:8px; border-radius:10px; margin:15px 0;">
                <div style="background:#3b82f6; width:45%; height:100%; border-radius:10px;"></div>
            </div>
            <p style="font-size:12px; color:#64748b;">Usage cycle resets on 1st of every month.</p>
        </div>
    </div>

    <div class="menu-grid">
        <a href="wifi_settings.php" class="menu-item">
            <i class="fa fa-wifi"></i>
            <span>Wi-Fi Settings</span>
        </a>
        <a href="usage_history.php" class="menu-item">
            <i class="fa fa-chart-line"></i>
            <span>Usage Logs</span>
        </a>
        <a href="tickets.php" class="menu-item">
            <i class="fa fa-headset"></i>
            <span>Support</span>
        </a>
        <a href="invoices.php" class="menu-item">
            <i class="fa fa-file-invoice"></i>
            <span>Invoices</span>
        </a>
    </div>
</div>

</body>
</html>
