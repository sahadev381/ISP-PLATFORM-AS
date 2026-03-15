<?php
session_start();
$page_title = "Access Control - Blacklist";

chdir(__DIR__ . '/../..');
$base_path = '.';
include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_path . '/index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $listType = $conn->real_escape_string($_POST['list_type']);
        $value = $conn->real_escape_string($_POST['value']);
        $description = $conn->real_escape_string($_POST['description']);
        $blockAction = $conn->real_escape_string($_POST['action_type']);
        
        $conn->query("INSERT INTO hotspot_access_lists (list_type, value, description, action, is_active) 
            VALUES ('$listType', '$value', '$description', '$blockAction', 1)");
        $message = "Entry added successfully";
    }
    
    if ($action == 'toggle' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE hotspot_access_lists SET is_active = NOT is_active WHERE id = $id");
        $message = "Status updated";
    }
    
    if ($action == 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM hotspot_access_lists WHERE id = $id");
        $message = "Entry deleted";
    }
}

$entries = $conn->query("SELECT * FROM hotspot_access_lists ORDER BY created_at DESC");

$counts = [
    'ip' => 0, 'mac' => 0, 'geo' => 0, 'url' => 0, 'whitelist' => 0
];
$stats = $conn->query("SELECT list_type, COUNT(*) as cnt FROM hotspot_access_lists WHERE is_active = 1 GROUP BY list_type");
while ($row = $stats->fetch_assoc()) {
    $counts[$row['list_type']] = $row['cnt'];
}
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
        
        .hotspot-wrapper { padding-top: 60px; min-height: 100vh; }
        
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
            width: 450px;
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
        <a href="../index.php" class="top-nav-brand">
            <i class="fas fa-wifi"></i>
            <span>HOTSPOT PORTAL</span>
        </a>
        <div class="top-nav-menu">
            <a href="index.php" class="top-nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="plans.php" class="top-nav-item">
                <i class="fas fa-ticket"></i> Plans & Vouchers
            </a>
            <a href="users.php" class="top-nav-item">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="hotel.php" class="top-nav-item">
                <i class="fas fa-hotel"></i> Hotel
            </a>
            <a href="blacklist.php" class="top-nav-item active">
                <i class="fas fa-shield-alt"></i> Access Control
            </a>
            <a href="logs.php" class="top-nav-item">
                <i class="fas fa-list"></i> Logs
            </a>
        </div>
        <div class="top-nav-actions">
            <div class="top-nav-user">
                <i class="fas fa-user-circle" style="font-size: 24px;"></i>
                <span><?= $_SESSION['username'] ?? 'Admin' ?></span>
                <a href="../../logout.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; margin-left: 10px;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>
    
    <div class="hotspot-wrapper">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-shield-alt"></i> Access Control</h1>
                <div style="color: #64748b; margin-top: 5px;">
                    <a href="../../dashboard.php" style="color: var(--primary); text-decoration: none;">Home</a>
                    <span style="margin: 0 8px;">/</span>
                    <span>Access Control</span>
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
                        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="stat-label">IP Blocked</div>
                        <div class="stat-value"><?= $counts['ip'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <div class="stat-label">MAC Blocked</div>
                        <div class="stat-value"><?= $counts['mac'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="stat-label">Geo Blocked</div>
                        <div class="stat-value"><?= $counts['geo'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-label">Whitelisted</div>
                        <div class="stat-value"><?= $counts['whitelist'] ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Access List Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Access Control List</h5>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='block'">
                        <i class="fas fa-plus"></i> Add Entry
                    </button>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Description</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($e = $entries->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $e['action'] == 'block' ? 'badge-danger' : 'badge-success' ?>">
                                        <?= strtoupper($e['list_type']) ?>
                                    </span>
                                </td>
                                <td><code><?= htmlspecialchars($e['value']) ?></code></td>
                                <td><?= htmlspecialchars($e['description']) ?></td>
                                <td>
                                    <span class="badge <?= $e['action'] == 'block' ? 'badge-danger' : 'badge-success' ?>">
                                        <?= $e['action'] == 'block' ? 'BLOCK' : 'ALLOW' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $e['is_active'] ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $e['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($e['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn <?= $e['is_active'] ? 'btn-warning' : 'btn-success' ?> btn-sm" onclick="submitAction('toggle', <?= $e['id'] ?>)" title="<?= $e['is_active'] ? 'Disable' : 'Enable' ?>">
                                            <i class="fas <?= $e['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteEntry(<?= $e['id'] ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($entries->num_rows == 0): ?>
                            <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 40px;">No access control entries</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Add Access Control Entry</h5>
                <button class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>List Type</label>
                        <select name="list_type" class="form-control" required>
                            <option value="ip">IP Address</option>
                            <option value="mac">MAC Address</option>
                            <option value="geo">Geo Location</option>
                            <option value="url">URL/Domain</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Value</label>
                        <input type="text" name="value" class="form-control" placeholder="e.g., 192.168.1.100 or AA:BB:CC:DD:EE:FF" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Reason for blocking">
                    </div>
                    <div class="form-group">
                        <label>Action</label>
                        <select name="action_type" class="form-control" required>
                            <option value="block">Block</option>
                            <option value="allow">Allow (Whitelist)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #e2e8f0;" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function submitAction(action, id) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteEntry(id) {
            if (confirm('Are you sure you want to delete this entry?')) {
                submitAction('delete', id);
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
