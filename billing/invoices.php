<?php
session_start();
$page_title = "Invoice Management";

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
    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    
    if ($_POST['action'] == 'mark_paid') {
        $conn->query("UPDATE billing_invoices SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = $invoice_id");
        $message = 'Invoice marked as paid';
    }
    
    if ($_POST['action'] == 'cancel_invoice') {
        $conn->query("UPDATE billing_invoices SET status = 'cancelled', updated_at = NOW() WHERE id = $invoice_id");
        $message = 'Invoice cancelled';
    }
    
    if ($_POST['action'] == 'delete_invoice') {
        $conn->query("DELETE FROM billing_invoices WHERE id = $invoice_id");
        $message = 'Invoice deleted';
    }
}

function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

$invoices = $conn->query("
    SELECT i.*, c.username, c.full_name, c.email, c.phone
    FROM billing_invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    ORDER BY i.created_at DESC
");

$pending_count = $conn->query("SELECT COUNT(*) as c FROM billing_invoices WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;
$paid_count = $conn->query("SELECT COUNT(*) as c FROM billing_invoices WHERE status = 'paid'")->fetch_assoc()['c'] ?? 0;
$overdue_count = $conn->query("SELECT COUNT(*) as c FROM billing_invoices WHERE status = 'pending' AND due_date < CURDATE()")->fetch_assoc()['c'] ?? 0;
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
            width: 600px;
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
        
        .row { display: flex; flex-wrap: wrap; margin: -10px; }
        .col-md-4 { width: 33.33%; padding: 10px; }
        .col-md-6 { width: 50%; padding: 10px; }
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
            <a href="invoices.php" class="top-nav-item active">
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
                <h1><i class="fas fa-file-invoice"></i> Invoice Management</h1>
                <div style="color: #64748b; margin-top: 5px;">
                    <a href="../dashboard.php" style="color: var(--primary); text-decoration: none;">Home</a>
                    <span style="margin: 0 8px;">/</span>
                    <span>Invoices</span>
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
                        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value"><?= $pending_count ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-label">Paid</div>
                        <div class="stat-value"><?= $paid_count ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-label">Overdue</div>
                        <div class="stat-value"><?= $overdue_count ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Invoices Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Invoices</h5>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('createModal').style.display='block'">
                        <i class="fas fa-plus"></i> Create Invoice
                    </button>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Tax</th>
                                <th>Total</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($inv = $invoices->fetch_assoc()): ?>
                            <?php $is_overdue = $inv['status'] == 'pending' && strtotime($inv['due_date']) < time(); ?>
                            <tr>
                                <td><code><?= $inv['invoice_number'] ?></code></td>
                                <td>
                                    <strong><?= $inv['full_name'] ?></strong><br>
                                    <small style="color: #64748b;"><?= $inv['username'] ?></small>
                                </td>
                                <td>Rs.<?= number_format($inv['subtotal'], 2) ?></td>
                                <td>Rs.<?= number_format($inv['tax_amount'], 2) ?></td>
                                <td><strong>Rs.<?= number_format($inv['total_amount'], 2) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($inv['issue_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                                <td>
                                    <span class="badge <?= 
                                        $inv['status'] == 'paid' ? 'badge-success' : 
                                        ($inv['status'] == 'pending' ? ($is_overdue ? 'badge-danger' : 'badge-warning') : 'badge-secondary')
                                    ?>">
                                        <?= ucfirst($inv['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($inv['status'] == 'pending'): ?>
                                            <button class="btn btn-success btn-sm" onclick="submitAction('mark_paid', <?= $inv['id'] ?>)" title="Mark Paid">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteInvoice(<?= $inv['id'] ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($invoices->num_rows == 0): ?>
                            <tr><td colspan="9" style="text-align: center; color: #64748b; padding: 40px;">No invoices found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Invoice Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Create New Invoice</h5>
                <button class="close" onclick="document.getElementById('createModal').style.display='none'">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_invoice">
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Issue Date</label>
                                <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Subtotal (Rs.)</label>
                                <input type="number" name="subtotal" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tax Rate (%)</label>
                                <input type="number" name="tax_rate" class="form-control" value="13" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Total (Rs.)</label>
                                <input type="number" name="total_amount" class="form-control" step="0.01" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Payment instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #e2e8f0;" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function submitAction(action, id) {
            if (action === 'delete_invoice' && !confirm('Are you sure you want to delete this invoice?')) {
                return;
            }
            if (action === 'mark_paid' && !confirm('Mark this invoice as paid?')) {
                return;
            }
            let form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="invoice_id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteInvoice(id) {
            if (confirm('Are you sure you want to delete this invoice?')) {
                submitAction('delete_invoice', id);
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
