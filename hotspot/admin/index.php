<?php
session_start();
$page_title = "Hotspot Portal";

chdir(__DIR__ . '/../..');
$base_path = '.';

include_once 'config.php';
include_once 'includes/auth.php';
include_once 'hotspot/includes/voucher.php';

$voucherSys = new VoucherSystem();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'generate_pins') {
        $profileId = $_POST['profile_id'] ?? 1;
        $count = $_POST['count'] ?? 10;
        $pins = $voucherSys->generatePins($profileId, $count);
        $message = "Generated " . count($pins) . " PINs: " . implode(', ', $pins);
    }
    
    if ($_POST['action'] == 'delete_profile' && isset($_POST['profile_id'])) {
        $conn->query("DELETE FROM hotspot_profiles WHERE id = " . intval($_POST['profile_id']));
        $message = "Profile deleted";
    }
}

$profiles = $voucherSys->getAllProfiles();
$stats = $voucherSys->getStats();
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
        }
        
        .top-nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }
        
        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 25px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-body { padding: 24px; }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-icon.blue { background: #dbeafe; color: var(--primary); }
        .stat-icon.green { background: #d1fae5; color: var(--success); }
        .stat-icon.cyan { background: #cffafe; color: var(--info); }
        .stat-icon.orange { background: #fed7aa; color: var(--warning); }
        
        .stat-label { font-size: 13px; color: #64748b; text-transform: uppercase; font-weight: 600; }
        .stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.4); }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        
        .table { width: 100%; border-collapse: collapse; }
        .table th { padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
        .table td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; }
        .table tr:hover { background: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-primary { background: #dbeafe; color: #1d4ed8; }
        .badge-info { background: #cffafe; color: #0891b2; }
        .badge-secondary { background: #f1f5f9; color: #64748b; }
        
        code { background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 13px; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        
        .form-select, .form-control {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <a href="index.php" class="top-nav-brand">
            <i class="fa fa-wifi"></i>
            <span>Hotspot Portal</span>
        </a>
        
        <div class="top-nav-menu">
            <a href="index.php" class="top-nav-item active">
                <i class="fa fa-gauge-high"></i> Dashboard
            </a>
            <a href="plans.php" class="top-nav-item">
                <i class="fa fa-tags"></i> Plans
            </a>
            <a href="users.php" class="top-nav-item">
                <i class="fa fa-users"></i> Users
            </a>
            <a href="hotel.php" class="top-nav-item">
                <i class="fa fa-hotel"></i> Hotel
            </a>
            <a href="blacklist.php" class="top-nav-item">
                <i class="fa fa-shield-alt"></i> Access Control
            </a>
            <a href="logs.php" class="top-nav-item">
                <i class="fa fa-history"></i> Logs
            </a>
            <a href="settings.php" class="top-nav-item">
                <i class="fa fa-cog"></i> Settings
            </a>
        </div>
        
        <div class="top-nav-right">
            <a href="../index.php" class="btn btn-sm" style="background: rgba(255,255,255,0.1); color: white;">
                <i class="fa fa-arrow-left"></i> Back to Main
            </a>
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $message ?></div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row mb-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
            <div class="stat-card" style="border-left: 4px solid var(--primary);">
                <div class="stat-icon blue"><i class="fa fa-ticket-alt"></i></div>
                <div>
                    <div class="stat-label">Total Vouchers</div>
                    <div class="stat-value"><?= array_sum(array_filter($stats, 'is_numeric')) ?></div>
                </div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--success);">
                <div class="stat-icon green"><i class="fa fa-check-circle"></i></div>
                <div>
                    <div class="stat-label">Available</div>
                    <div class="stat-value"><?= $stats['available'] ?? 0 ?></div>
                </div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--info);">
                <div class="stat-icon cyan"><i class="fa fa-user-check"></i></div>
                <div>
                    <div class="stat-label">Used</div>
                    <div class="stat-value"><?= $stats['used'] ?? 0 ?></div>
                </div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--warning);">
                <div class="stat-icon orange"><i class="fa fa-tags"></i></div>
                <div>
                    <div class="stat-label">Profiles</div>
                    <div class="stat-value"><?= count($profiles) ?></div>
                </div>
            </div>
        </div>
        
        <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Generate PINs -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fa fa-plus-circle"></i> Generate PINs</h5>
                </div>
                <div class="card-body">
                    <form method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
                        <input type="hidden" name="action" value="generate_pins">
                        <div style="flex: 1;">
                            <label style="font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 6px;">Select Profile</label>
                            <select name="profile_id" class="form-select" style="width: 100%;">
                                <?php foreach ($profiles as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= $p['name'] ?> - Rs.<?= $p['price'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="width: 120px;">
                            <label style="font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 6px;">Count</label>
                            <select name="count" class="form-select" style="width: 100%;">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-plus"></i> Generate
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fa fa-bolt"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <a href="plans.php" class="btn" style="background: #eff6ff; color: #3b82f6; justify-content: center;"><i class="fa fa-tags"></i> Plans</a>
                        <a href="users.php" class="btn" style="background: #f0fdf4; color: #10b981; justify-content: center;"><i class="fa fa-users"></i> Users</a>
                        <a href="hotel.php" class="btn" style="background: #fff7ed; color: #f59e0b; justify-content: center;"><i class="fa fa-hotel"></i> Hotel</a>
                        <a href="logs.php" class="btn" style="background: #f5f3ff; color: #8b5cf6; justify-content: center;"><i class="fa fa-history"></i> Logs</a>
                    </div>
                </div>
            </div>
        </div>
        
        <br>
        
        <!-- Profiles -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fa fa-tags"></i> Plans/Profiles</h5>
                <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProfileModal">
                    <i class="fa fa-plus"></i> Add Profile
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Data Limit</th>
                            <th>Validity</th>
                            <th>Price</th>
                            <th>Speed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profiles as $p): ?>
                        <tr>
                            <td><strong><?= $p['name'] ?></strong></td>
                            <td><span class="badge <?= $p['type'] == 'data' ? 'badge-primary' : 'badge-info' ?>"><?= ucfirst($p['type']) ?></span></td>
                            <td><?= $p['data_limit_mb'] > 0 ? $p['data_limit_mb'] . ' MB' : 'Unlimited' ?></td>
                            <td><?= $p['validity_hours'] ?> hours</td>
                            <td><strong>Rs.<?= $p['price'] ?></strong></td>
                            <td><?= round($p['speed_kbps']/1024) ?> Mbps</td>
                            <td><span class="badge <?= $p['status'] == 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= $p['status'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deleteProfile(<?= $p['id'] ?>)"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Vouchers -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fa fa-ticket-alt"></i> Recent Vouchers</h5>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php
                $vouchers = $conn->query("
                    SELECT v.*, p.name as plan_name 
                    FROM hotspot_vouchers v
                    JOIN hotspot_profiles p ON v.profile_id = p.id
                    ORDER BY v.generated_at DESC LIMIT 20
                ");
                ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>PIN</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Used By</th>
                            <th>Generated</th>
                            <th>Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($v = $vouchers->fetch_assoc()): ?>
                        <tr>
                            <td><code><?= $v['pin_code'] ?></code></td>
                            <td><?= $v['plan_name'] ?></td>
                            <td>
                                <?php if($v['status'] == 'available'): ?>
                                    <span class="badge badge-success">Available</span>
                                <?php elseif($v['status'] == 'used'): ?>
                                    <span class="badge badge-secondary">Used</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Expired</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $v['used_by'] ?: '-' ?></td>
                            <td><?= date('M d, H:i', strtotime($v['generated_at'])) ?></td>
                            <td><?= date('M d, H:i', strtotime($v['expires_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Profile Modal -->
    <div class="modal fade" id="addProfileModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="add_profile.php">
                    <div class="modal-header">
                        <h5>Add Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="data">Data Based</option>
                                <option value="time">Time Based</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Limit (MB)</label>
                            <input type="number" name="data_limit_mb" class="form-control" value="0">
                            <small class="text-muted">0 = Unlimited</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Validity (Hours)</label>
                            <input type="number" name="validity_hours" class="form-control" value="24">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (Rs.)</label>
                            <input type="number" name="price" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Speed (Kbps)</label>
                            <input type="number" name="speed_kbps" class="form-control" value="1024">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Add Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteProfile(id) {
            if (confirm('Are you sure you want to delete this profile?')) {
                fetch('', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'delete_profile', profile_id: id })
                }).then(() => location.reload());
            }
        }
    </script>
</body>
</html>
