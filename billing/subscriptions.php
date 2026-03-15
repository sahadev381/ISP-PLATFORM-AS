<?php
session_start();
$page_title = "Subscription Management";

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
    $subscription_id = intval($_POST['subscription_id'] ?? 0);
    
    if ($_POST['action'] == 'cancel_subscription') {
        $conn->query("UPDATE customer_subscriptions SET status = 'cancelled', updated_at = NOW() WHERE id = $subscription_id");
        $message = 'Subscription cancelled successfully';
    }
    
    if ($_POST['action'] == 'suspend_subscription') {
        $conn->query("UPDATE customer_subscriptions SET status = 'suspended', updated_at = NOW() WHERE id = $subscription_id");
        $message = 'Subscription suspended successfully';
    }
    
    if ($_POST['action'] == 'reactivate_subscription') {
        $conn->query("UPDATE customer_subscriptions SET status = 'active', updated_at = NOW() WHERE id = $subscription_id");
        $message = 'Subscription reactivated successfully';
    }
    
    if ($_POST['action'] == 'delete_subscription') {
        $conn->query("DELETE FROM customer_subscriptions WHERE id = $subscription_id");
        $message = 'Subscription deleted successfully';
    }
}

$subscriptions = $conn->query("
    SELECT cs.*, c.username, c.full_name, c.email, c.phone, c.address,
           p.name as plan_name, p.price as plan_price
    FROM customer_subscriptions cs
    JOIN customers c ON cs.customer_id = c.id
    JOIN plans p ON cs.plan_id = p.id
    ORDER BY cs.created_at DESC
");

$plans = $conn->query("SELECT * FROM plans ORDER BY name");

$counts = [
    'active' => $conn->query("SELECT COUNT(*) as c FROM customer_subscriptions WHERE status = 'active'")->fetch_assoc()['c'] ?? 0,
    'pending' => $conn->query("SELECT COUNT(*) as c FROM customer_subscriptions WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0,
    'suspended' => $conn->query("SELECT COUNT(*) as c FROM customer_subscriptions WHERE status = 'suspended'")->fetch_assoc()['c'] ?? 0,
    'cancelled' => $conn->query("SELECT COUNT(*) as c FROM customer_subscriptions WHERE status = 'cancelled'")->fetch_assoc()['c'] ?? 0,
];
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
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-info { background: var(--info); color: white; }
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
        .badge-info { background: #cffafe; color: #0e7490; }
        .badge-secondary { background: #f1f5f9; color: #64748b; }
        
        .btn-group { display: flex; gap: 5px; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h5 { margin: 0; font-size: 18px; font-weight: 600; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #374151; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }
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
            <a href="subscriptions.php" class="top-nav-item active">
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
            <div class="page-header">
                <h1><i class="fas fa-users-cog"></i> Subscription Management</h1>
                <div style="color: #64748b; margin-top: 5px;">
                    <a href="../dashboard.php" style="color: var(--primary); text-decoration: none;">Home</a>
                    <span style="margin: 0 8px;">/</span>
                    <span>Subscriptions</span>
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
                        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-label">Active</div>
                        <div class="stat-value"><?= $counts['active'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value"><?= $counts['pending'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                        <div class="stat-label">Suspended</div>
                        <div class="stat-value"><?= $counts['suspended'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f1f5f9; color: #64748b;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-label">Cancelled</div>
                        <div class="stat-value"><?= $counts['cancelled'] ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Subscriptions Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Subscriptions</h5>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='block'">
                        <i class="fas fa-plus"></i> Add Subscription
                    </button>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Plan</th>
                                <th>Price</th>
                                <th>Start Date</th>
                                <th>Next Billing</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sub = $subscriptions->fetch_assoc()): ?>
                            <tr>
                                <td><?= $sub['id'] ?></td>
                                <td>
                                    <strong><?= $sub['full_name'] ?></strong><br>
                                    <small style="color: #64748b;"><?= $sub['username'] ?></small>
                                </td>
                                <td><?= $sub['plan_name'] ?></td>
                                <td>Rs.<?= number_format($sub['plan_price'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($sub['start_date'])) ?></td>
                                <td><?= $sub['next_billing_date'] ? date('M d, Y', strtotime($sub['next_billing_date'])) : '-' ?></td>
                                <td>
                                    <span class="badge <?= 
                                        $sub['status'] == 'active' ? 'badge-success' : 
                                        ($sub['status'] == 'pending' ? 'badge-warning' : 
                                        ($sub['status'] == 'suspended' ? 'badge-danger' : 'badge-secondary'))
                                    ?>">
                                        <?= ucfirst($sub['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($sub['status'] == 'active'): ?>
                                            <button class="btn btn-warning btn-sm" onclick="submitAction('suspend_subscription', <?= $sub['id'] ?>)" title="Suspend">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php elseif ($sub['status'] == 'suspended'): ?>
                                            <button class="btn btn-success btn-sm" onclick="submitAction('reactivate_subscription', <?= $sub['id'] ?>)" title="Reactivate">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($sub['status'] != 'cancelled'): ?>
                                            <button class="btn btn-danger btn-sm" onclick="submitAction('cancel_subscription', <?= $sub['id'] ?>)" title="Cancel">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($subscriptions->num_rows == 0): ?>
                            <tr><td colspan="8" style="text-align: center; color: #64748b; padding: 40px;">No subscriptions found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Subscription Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Add New Subscription</h5>
                <button class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_subscription">
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">Select Customer</option>
                            <?php
                            $customers = $conn->query("SELECT id, username, full_name FROM customers ORDER BY username");
                            while ($c = $customers->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['username'] ?> - <?= $c['full_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan_id" class="form-control" required>
                            <option value="">Select Plan</option>
                            <?php while ($p = $plans->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['name'] ?> - Rs.<?= $p['price'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #e2e8f0;" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Subscription</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function submitAction(action, id) {
            if (action === 'cancel_subscription' && !confirm('Are you sure you want to cancel this subscription?')) {
                return;
            }
            let form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="subscription_id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
