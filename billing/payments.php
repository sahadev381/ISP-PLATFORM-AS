<?php
session_start();
$page_title = "Payment History";

chdir(__DIR__ . '/..');
$base_path = '.';

include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    
    if ($_POST['action'] == 'verify_payment') {
        $conn->query("UPDATE payment_transactions SET status = 'completed', completed_at = NOW() WHERE id = $payment_id");
        $message = 'Payment verified successfully';
    }
    
    if ($_POST['action'] == 'reject_payment') {
        $conn->query("UPDATE payment_transactions SET status = 'failed', notes = 'Rejected by admin' WHERE id = $payment_id");
        $message = 'Payment rejected';
    }
}

$filter_status = $_GET['status'] ?? '';

$where = [];
if ($filter_status) $where[] = "t.status = '$filter_status'";
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$transactions = $conn->query("
    SELECT t.*, c.username, c.full_name, c.email, c.phone,
           g.gateway_name
    FROM payment_transactions t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN payment_gateways g ON t.gateway_id = g.id
    $where_clause
    ORDER BY t.created_at DESC
");

$total_received = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payment_transactions WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0;
$total_pending = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payment_transactions WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0;
$total_failed = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payment_transactions WHERE status = 'failed'")->fetch_assoc()['total'] ?? 0;
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
        
        .billing-wrapper { padding-top: 60px; min-height: 100vh; }
        
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
        
        .top-nav-menu { display: flex; gap: 5px; }
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
        .top-nav-item.active { background: var(--primary); color: white; }
        
        .top-nav-actions { display: flex; align-items: center; gap: 15px; }
        .top-nav-user {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .content-wrapper { padding: 25px; }
        
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 24px; font-weight: 600; color: #1e293b; margin: 0; }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
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
        .stat-card .stat-label { font-size: 13px; color: #64748b; font-weight: 500; }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
        
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .content-card .card-header h5 { margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; }
        .content-card .card-body { padding: 0; }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        .table { width: 100%; border-collapse: collapse; }
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
        .table tbody tr:hover { background: #f8fafc; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f1f5f9; color: #64748b; }
        .badge-info { background: #cffafe; color: #0e7490; }
        
        .btn-group { display: flex; gap: 5px; }
        
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
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
            <a href="index.php" class="top-nav-item">
                <i class="fas fa-file-invoice-dollar"></i> Billing
            </a>
            <a href="subscriptions.php" class="top-nav-item">
                <i class="fas fa-users-cog"></i> Subscriptions
            </a>
            <a href="invoices.php" class="top-nav-item">
                <i class="fas fa-file-invoice"></i> Invoices
            </a>
            <a href="payments.php" class="top-nav-item active">
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
            <div class="page-header">
                <h1><i class="fas fa-credit-card"></i> Payment History</h1>
                <div style="color: #64748b; margin-top: 5px;">
                    <a href="../dashboard.php" style="color: var(--primary); text-decoration: none;">Home</a>
                    <span style="margin: 0 8px;">/</span>
                    <span>Payments</span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div style="background: #dbeafe; color: #1e40af; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-label">Total Received</div>
                        <div class="stat-value">Rs.<?= number_format($total_received, 0) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value">Rs.<?= number_format($total_pending, 0) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-label">Failed</div>
                        <div class="stat-value">Rs.<?= number_format($total_failed, 0) ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Transactions</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Customer</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($t = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><code><?= substr($t['transaction_id'], 0, 16) ?>...</code></td>
                                <td>
                                    <strong><?= $t['full_name'] ?></strong><br>
                                    <small style="color: #64748b;"><?= $t['username'] ?></small>
                                </td>
                                <td><?= $t['gateway_name'] ?: '-' ?></td>
                                <td><strong>Rs.<?= number_format($t['amount'], 2) ?></strong></td>
                                <td><?= $t['payment_method'] ?: '-' ?></td>
                                <td>
                                    <span class="badge <?= 
                                        $t['status'] == 'completed' ? 'badge-success' : 
                                        ($t['status'] == 'pending' ? 'badge-warning' : 
                                        ($t['status'] == 'failed' ? 'badge-danger' : 'badge-secondary'))
                                    ?>">
                                        <?= ucfirst($t['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($t['status'] == 'pending'): ?>
                                            <button class="btn btn-success btn-sm" onclick="submitAction('verify_payment', <?= $t['id'] ?>)" title="Verify">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="submitAction('reject_payment', <?= $t['id'] ?>)" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($transactions->num_rows == 0): ?>
                            <tr><td colspan="8" style="text-align: center; color: #64748b; padding: 40px;">No transactions found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function submitAction(action, id) {
            if (action === 'reject_payment' && !confirm('Reject this payment?')) {
                return;
            }
            let form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="payment_id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
