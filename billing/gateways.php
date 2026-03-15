<?php
session_start();
$page_title = "Payment Gateways";

chdir(__DIR__ . '/..');
$base_path = '.';

include_once 'config.php';
include_once 'includes/auth.php';
include_once 'includes/payment_gateway.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $gateway_id = intval($_POST['gateway_id'] ?? 0);
    
    if ($_POST['action'] == 'add_gateway') {
        $gateway_name = $conn->real_escape_string($_POST['gateway_name']);
        $display_name = $conn->real_escape_string($_POST['display_name']);
        $api_key = $conn->real_escape_string($_POST['api_key']);
        $api_secret = $conn->real_escape_string($_POST['api_secret']);
        $merchant_id = $conn->real_escape_string($_POST['merchant_id']);
        $public_key = $conn->real_escape_string($_POST['public_key']);
        $is_active = intval($_POST['is_active'] ?? 1);
        $is_test_mode = intval($_POST['is_test_mode'] ?? 0);
        
        $conn->query("INSERT INTO payment_gateways (gateway_name, display_name, api_key, api_secret, merchant_id, public_key, is_active, is_test_mode, created_at) 
                      VALUES ('$gateway_name', '$display_name', '$api_key', '$api_secret', '$merchant_id', '$public_key', $is_active, $is_test_mode, NOW())");
        $message = 'Gateway added successfully';
    }
    
    if ($_POST['action'] == 'update_gateway') {
        $gateway_name = $conn->real_escape_string($_POST['gateway_name']);
        $display_name = $conn->real_escape_string($_POST['display_name']);
        $api_key = $conn->real_escape_string($_POST['api_key']);
        $api_secret = $conn->real_escape_string($_POST['api_secret']);
        $merchant_id = $conn->real_escape_string($_POST['merchant_id']);
        $public_key = $conn->real_escape_string($_POST['public_key']);
        $is_active = intval($_POST['is_active'] ?? 1);
        $is_test_mode = intval($_POST['is_test_mode'] ?? 0);
        
        $conn->query("UPDATE payment_gateways SET 
                      gateway_name = '$gateway_name', display_name = '$display_name',
                      api_key = '$api_key', api_secret = '$api_secret',
                      merchant_id = '$merchant_id', public_key = '$public_key',
                      is_active = $is_active, is_test_mode = $is_test_mode,
                      updated_at = NOW() WHERE id = $gateway_id");
        $message = 'Gateway updated successfully';
    }
    
    if ($_POST['action'] == 'delete_gateway') {
        $conn->query("DELETE FROM payment_gateways WHERE id = $gateway_id");
        $message = 'Gateway deleted';
    }
    
    if ($_POST['action'] == 'toggle_gateway') {
        $conn->query("UPDATE payment_gateways SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = $gateway_id");
        $message = 'Gateway status updated';
    }
}

$gateways = $conn->query("SELECT * FROM payment_gateways ORDER BY gateway_name");

$active_count = $conn->query("SELECT COUNT(*) as c FROM payment_gateways WHERE is_active = 1")->fetch_assoc()['c'] ?? 0;
$inactive_count = $conn->query("SELECT COUNT(*) as c FROM payment_gateways WHERE is_active = 0")->fetch_assoc()['c'] ?? 0;
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
        
        .btn-group { display: flex; gap: 5px; }
        
        .gateway-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
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
        
        .checkbox-group { display: flex; gap: 20px; align-items: center; }
        .checkbox-group label { display: flex; align-items: center; gap: 6px; font-weight: normal; }
        
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
            <a href="subscriptions.php" class="top-nav-item">
                <i class="fas fa-users-cog"></i> Subscriptions
            </a>
            <a href="invoices.php" class="top-nav-item">
                <i class="fas fa-file-invoice"></i> Invoices
            </a>
            <a href="payments.php" class="top-nav-item">
                <i class="fas fa-credit-card"></i> Payments
            </a>
            <a href="gateways.php" class="top-nav-item active">
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
                <h1><i class="fas fa-cog"></i> Payment Gateways</h1>
                <div style="color: #64748b; margin-top: 5px;">
                    <a href="../dashboard.php" style="color: var(--primary); text-decoration: none;">Home</a>
                    <span style="margin: 0 8px;">/</span>
                    <span>Payment Gateways</span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div style="background: #dbeafe; color: #1e40af; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-label">Active Gateways</div>
                        <div class="stat-value"><?= $active_count ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f1f5f9; color: #64748b;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-label">Inactive Gateways</div>
                        <div class="stat-value"><?= $inactive_count ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Gateways Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Gateways</h5>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='block'">
                        <i class="fas fa-plus"></i> Add Gateway
                    </button>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Gateway</th>
                                <th>Display Name</th>
                                <th>Merchant ID</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($gw = $gateways->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="gateway-icon" style="background: #dbeafe; color: #1d4ed8;">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <strong><?= strtoupper($gw['gateway_name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= $gw['display_name'] ?></td>
                                <td><?= $gw['merchant_id'] ?: '-' ?></td>
                                <td>
                                    <span class="badge <?= $gw['is_test_mode'] ? 'badge-warning' : 'badge-success' ?>">
                                        <?= $gw['is_test_mode'] ? 'Test' : 'Live' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $gw['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $gw['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-primary btn-sm" onclick="editGateway(<?= $gw['id'] ?>, '<?= $gw['gateway_name'] ?>', '<?= $gw['display_name'] ?>', '<?= $gw['merchant_id'] ?>', '<?= $gw['api_key'] ?>', '<?= $gw['api_secret'] ?>', '<?= $gw['public_key'] ?>', <?= $gw['is_active'] ?>, <?= $gw['is_test_mode'] ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn <?= $gw['is_active'] ? 'btn-warning' : 'btn-success' ?> btn-sm" onclick="submitAction('toggle_gateway', <?= $gw['id'] ?>)" title="<?= $gw['is_active'] ? 'Disable' : 'Enable' ?>">
                                            <i class="fas <?= $gw['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteGateway(<?= $gw['id'] ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($gateways->num_rows == 0): ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 40px;">No gateways configured</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Gateway Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Add Payment Gateway</h5>
                <button class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_gateway">
                    <div class="form-group">
                        <label>Gateway</label>
                        <select name="gateway_name" class="form-control" required>
                            <option value="">Select Gateway</option>
                            <option value="khalti">Khalti</option>
                            <option value="esewa">eSewa</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" class="form-control" placeholder="e.g., Khalti Payment" required>
                    </div>
                    <div class="form-group">
                        <label>Merchant ID</label>
                        <input type="text" name="merchant_id" class="form-control" placeholder="Merchant ID">
                    </div>
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="text" name="api_key" class="form-control" placeholder="API Key">
                    </div>
                    <div class="form-group">
                        <label>API Secret</label>
                        <input type="text" name="api_secret" class="form-control" placeholder="API Secret">
                    </div>
                    <div class="form-group">
                        <label>Public Key</label>
                        <input type="text" name="public_key" class="form-control" placeholder="Public Key">
                    </div>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
                        <label><input type="checkbox" name="is_test_mode" value="1"> Test Mode</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #e2e8f0;" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Gateway</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function submitAction(action, id) {
            if (action === 'delete_gateway' && !confirm('Are you sure you want to delete this gateway?')) {
                return;
            }
            let form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="gateway_id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteGateway(id) {
            if (confirm('Are you sure you want to delete this gateway?')) {
                submitAction('delete_gateway', id);
            }
        }
        
        function editGateway(id, name, display, merchant, apiKey, apiSecret, pubKey, active, testMode) {
            alert('Edit gateway ID: ' + id + '\n' + name);
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
