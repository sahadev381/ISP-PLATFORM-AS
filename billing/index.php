<?php
session_start();
$page_title = "Billing Dashboard";

chdir(__DIR__ . '/..');
$base_path = '.';

include_once 'config.php';
include_once 'includes/auth.php';
include_once 'includes/payment_gateway.php';

$paymentGateway = new PaymentGateway();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';

// Get billing stats
$stats = [
    'total_customers' => $conn->query("SELECT COUNT(*) as cnt FROM customer_subscriptions")->fetch_assoc()['cnt'] ?? 0,
    'active_subscriptions' => $conn->query("SELECT COUNT(*) as cnt FROM customer_subscriptions WHERE status = 'active'")->fetch_assoc()['cnt'] ?? 0,
    'pending_payments' => $conn->query("SELECT COUNT(*) as cnt FROM payment_transactions WHERE status = 'pending'")->fetch_assoc()['cnt'] ?? 0,
    'monthly_revenue' => $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payment_transactions WHERE status = 'completed' AND MONTH(created_at) = MONTH(NOW())")->fetch_assoc()['total'] ?? 0,
];

$recentTransactions = $conn->query("
    SELECT t.*, c.username, c.full_name, g.gateway_name
    FROM payment_transactions t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN payment_gateways g ON t.gateway_id = g.id
    ORDER BY t.created_at DESC LIMIT 10
");

$upcomingRenewals = $conn->query("
    SELECT cs.*, c.username, c.full_name, c.email, c.phone,
           p.name as plan_name, p.price as plan_price
    FROM customer_subscriptions cs
    JOIN customers c ON cs.customer_id = c.id
    JOIN plans p ON cs.plan_id = p.id
    WHERE cs.status = 'active' AND cs.next_billing_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY cs.next_billing_date ASC LIMIT 10
");

$recentInvoices = $conn->query("
    SELECT i.*, c.username, c.full_name
    FROM billing_invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    ORDER BY i.created_at DESC LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
        }
        body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background: #f1f5f9; }
        
        .billing-wrapper {
            padding-top: 60px;
            min-height: 100vh;
        }
        
        /* Top Navigation */
        .top-nav {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 0 25px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
        }
        .top-nav-brand i { font-size: 24px; color: var(--primary); }
        
        .top-nav-menu {
            display: flex;
            gap: 5px;
        }
        .top-nav-item {
            padding: 10px 18px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        .top-nav-item:hover, .top-nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .top-nav-item.active {
            background: var(--primary);
            color: white;
        }
        
        .top-nav-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .top-nav-user {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        .top-nav-user img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .content-wrapper {
            padding: 25px;
        }
        
        .page-header {
            margin-bottom: 25px;
        }
        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        .page-header .breadcrumb {
            margin: 5px 0 0;
            font-size: 13px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }
        .stat-card .stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .content-card .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .content-card .card-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }
        .content-card .card-body {
            padding: 20px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-info { background: var(--info); color: white; }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .table td {
            padding: 12px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        .table tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #cffafe; color: #0e7490; }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <a href="../dashboard.php" class="top-nav-brand">
            <i class="fas fa-wifi"></i>
            <span>ISP SYSTEM</span>
        </a>
        <div class="top-nav-menu">
            <a href="index.php" class="top-nav-item active">
                <i class="fas fa-file-invoice-dollar"></i> Billing
            </a>
            <a href="subscriptions.php" class="top-nav-item">
                <i class="fas fa-users-cog"></i> Subscriptions
            </a>
            <a href="invoices.php" class="top-nav-item">
                <i class="fas fa-file-invoice"></i> Invoices
            </a>
            <a href="payments.php" class="top-nav-item">
                <i class="fas fa-credit-card"></i> Payments
            </a>
            <a href="gateways.php" class="top-nav-item">
                <i class="fas fa-cog"></i> Gateways
            </a>
        </div>
        <div class="top-nav-actions">
            <div class="top-nav-user">
                <i class="fas fa-user-circle" style="font-size: 24px;"></i>
                <span><?= $_SESSION['username'] ?? 'Admin' ?></span>
                <a href="../logout.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; margin-left: 10px;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>
    
    <div class="billing-wrapper">
        <div class="content-wrapper">
        <div class="container-fluid p-4">
            <div class="page-header">
                <h1><i class="fas fa-file-invoice-dollar"></i> Billing Dashboard</h1>
                <div class="breadcrumb" style="color: #64748b;">
                    <a href="../dashboard.php" style="color: var(--primary); text-decoration: none;">Home</a>
                    <span style="margin: 0 8px;">/</span>
                    <span>Billing Dashboard</span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div style="background: #dbeafe; color: #1e40af; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #dbeafe; color: #1d4ed8;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-value"><?= $stats['total_customers'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-label">Active Subscriptions</div>
                        <div class="stat-value"><?= $stats['active_subscriptions'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-label">Pending Payments</div>
                        <div class="stat-value"><?= $stats['pending_payments'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #cffafe; color: #0891b2;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-label">Monthly Revenue</div>
                        <div class="stat-value">Rs.<?= number_format($stats['monthly_revenue'], 0) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Upcoming Renewals -->
                <div class="col-md-6 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-alt"></i> Upcoming Renewals (Next 7 Days)</h5>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Plan</th>
                                        <th>Amount</th>
                                        <th>Renewal Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($r = $upcomingRenewals->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $r['full_name'] ?></td>
                                        <td><?= $r['plan_name'] ?></td>
                                        <td>Rs.<?= number_format($r['plan_price'], 2) ?></td>
                                        <td><?= date('M d, Y', strtotime($r['next_billing_date'])) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($upcomingRenewals->num_rows == 0): ?>
                                    <tr><td colspan="4" class="text-muted">No upcoming renewals</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Invoices -->
                <div class="col-md-6 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-receipt"></i> Recent Invoices</h5>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($inv = $recentInvoices->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $inv['invoice_number'] ?></td>
                                        <td><?= $inv['full_name'] ?></td>
                                        <td>Rs.<?= number_format($inv['total_amount'], 2) ?></td>
                                        <td>
                                            <span class="badge <?= 
                                                $inv['status'] == 'paid' ? 'badge-success' : 
                                                ($inv['status'] == 'pending' ? 'badge-warning' : 'badge-danger')
                                            ?>">
                                                <?= ucfirst($inv['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($recentInvoices->num_rows == 0): ?>
                                    <tr><td colspan="4" class="text-muted">No invoices yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Transactions</h5>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($t = $recentTransactions->fetch_assoc()): ?>
                            <tr>
                                <td><code><?= substr($t['transaction_id'], 0, 12) ?>...</code></td>
                                <td><?= $t['full_name'] ?></td>
                                <td><?= $t['gateway_name'] ?: 'Direct' ?></td>
                                <td>Rs.<?= number_format($t['amount'], 2) ?></td>
                                <td>
                                    <span class="badge <?= 
                                        $t['status'] == 'completed' ? 'badge-success' : 
                                        ($t['status'] == 'pending' ? 'badge-warning' : 'badge-danger')
                                    ?>">
                                        <?= ucfirst($t['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, H:i', strtotime($t['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($recentTransactions->num_rows == 0): ?>
                            <tr><td colspan="6" class="text-muted">No transactions yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <a href="subscriptions.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Manage Subscriptions
                            </a>
                            <a href="invoices.php" class="btn btn-secondary" style="background: #64748b; color: white;">
                                <i class="fas fa-file-invoice"></i> Invoices
                            </a>
                            <a href="payments.php" class="btn btn-info">
                                <i class="fas fa-credit-card"></i> Payments
                            </a>
                            <a href="gateways.php" class="btn btn-warning">
                                <i class="fas fa-cog"></i> Payment Gateways
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
