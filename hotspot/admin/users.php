<?php
require_once __DIR__ . '/../../includes/session.php';
session_boot();
$page_title = "Hotspot Users";
$page = 'users';
$base_path = '.';

include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$message = '';
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action == 'save_user') {
        $username      = trim($_POST['username'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $planType      = trim($_POST['plan_type'] ?? '');
        $authMethod    = trim($_POST['auth_method'] ?? '');
        $macAddress    = trim($_POST['mac_address'] ?? '');
        $ipAddress     = trim($_POST['ip_address'] ?? '');
        $maxDevices    = (int)($_POST['max_devices'] ?? 0);
        $singleSession = isset($_POST['single_session']) ? 1 : 0;
        $hosEnabled    = isset($_POST['hos_enabled']) ? 1 : 0;
        $hosStart      = $_POST['hos_start'] ?? null;
        $hosEnd        = $_POST['hos_end'] ?? null;
        $dataLimit     = (int)($_POST['data_limit_mb'] ?? 0);
        $speed         = (int)($_POST['speed_kbps'] ?? 0);
        $validUntil    = $_POST['valid_until'] ?? null;

        if (!empty($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];

            db_exec($conn, "UPDATE hotspot_users SET
                phone = ?, plan_type = ?, auth_method = ?,
                mac_address = ?, ip_address = ?,
                max_devices = ?, single_session = ?,
                hos_enabled = ?, hos_start = ?, hos_end = ?,
                data_limit_mb = ?, fup_speed_kbps = ?,
                valid_until = ? WHERE id = ?", [
                $phone, $planType, $authMethod, $macAddress, $ipAddress,
                $maxDevices, $singleSession, $hosEnabled, $hosStart, $hosEnd,
                $dataLimit, $speed, $validUntil, $userId,
            ]);

            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                db_exec($conn, "UPDATE hotspot_users SET password = ? WHERE id = ?", [$password, $userId]);
            }
            $message = 'User updated successfully!';
        } else {
            $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
            db_exec($conn, "INSERT INTO hotspot_users (username, password, phone, plan_type, auth_method, mac_address, ip_address, max_devices, single_session, hos_enabled, hos_start, hos_end, data_limit_mb, fup_speed_kbps, valid_until, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')", [
                $username, $password, $phone, $planType, $authMethod, $macAddress, $ipAddress,
                $maxDevices, $singleSession, $hosEnabled, $hosStart, $hosEnd,
                $dataLimit, $speed, $validUntil,
            ]);
            $message = 'User created successfully!';
        }
    }
    
    if ($action == 'delete_user' && isset($_POST['user_id'])) {
        db_exec($conn, "DELETE FROM hotspot_users WHERE id = ?", [(int)$_POST['user_id']]);
        $message = 'User deleted successfully!';
    }
    
    if ($action == 'toggle_status' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $user = db_one($conn, "SELECT status FROM hotspot_users WHERE id = ?", [$userId]);
        $newStatus = ($user['status'] ?? '') == 'active' ? 'blocked' : 'active';
        db_exec($conn, "UPDATE hotspot_users SET status = ? WHERE id = ?", [$newStatus, $userId]);
        $message = "User $newStatus successfully!";
    }
    
    if ($action == 'topup' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $mb = (int)$_POST['topup_mb'];
        db_exec($conn, "UPDATE hotspot_users SET data_limit_mb = data_limit_mb + ? WHERE id = ?", [$mb, $userId]);
        $message = "Added {$mb}MB to user account";
    }
    
    if ($action == 'recharge' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $amount = (float)$_POST['recharge_amount'];
        db_exec($conn, "UPDATE hotspot_users SET current_balance = current_balance + ? WHERE id = ?", [$amount, $userId]);
        db_exec($conn, "INSERT INTO hotspot_invoices (user_id, description, amount, total, status, paid_at) VALUES (?, 'Manual Recharge', ?, ?, 'paid', NOW())", [$userId, $amount, $amount]);
        $message = "Added Rs.{$amount} to balance";
    }
    
    if ($action == 'bulk_action' && !empty($_POST['user_ids'])) {
        $userIds = array_values(array_filter(array_map('intval', (array) $_POST['user_ids'])));
        $bulkAction = $_POST['bulk_action'] ?? '';

        if ($userIds) {
            $in = implode(',', array_fill(0, count($userIds), '?'));

            if ($bulkAction == 'delete') {
                db_exec($conn, "DELETE FROM hotspot_users WHERE id IN ($in)", $userIds);
                $message = count($userIds) . ' users deleted';
            } elseif ($bulkAction == 'active' || $bulkAction == 'blocked') {
                db_exec($conn, "UPDATE hotspot_users SET status = ? WHERE id IN ($in)",
                    array_merge([$bulkAction], $userIds));
                $message = count($userIds) . ' users '
                    . ($bulkAction == 'active' ? 'activated' : 'blocked');
            }
        }
    }
}

// Build query
$where  = "1=1";
$params = [];
if ($filter == 'active')  $where .= " AND u.status = 'active'";
if ($filter == 'blocked') $where .= " AND u.status = 'blocked'";
if ($search) {
    $where   .= " AND (u.username LIKE ? OR u.phone LIKE ?)";
    $params[] = db_like($search);
    $params[] = db_like($search);
}

$users = db_all($conn, "SELECT u.*, p.name as profile_name FROM hotspot_users u
    LEFT JOIN hotspot_profiles p ON u.profile_id = p.id
    WHERE $where ORDER BY u.id DESC LIMIT 500", $params);

$stats = db_one($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as blocked FROM hotspot_users");

// Get recent activity
$recentActivity = $conn->query("SELECT * FROM hotspot_access_logs ORDER BY created_at DESC LIMIT 10");
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<?php include __DIR__ . '/includes/header_hotspot.php'; ?>


<style>
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
}

body { background: #f3f4f6; }

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: white;
}

.avatar-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.avatar-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.avatar-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.avatar-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.avatar-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

.stat-card {
    border: none;
    border-radius: 16px;
    transition: all 0.3s ease;
    overflow: hidden;
}
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); }

.search-modern {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 20px;
    transition: all 0.3s;
}
.search-modern:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }

.filter-chip {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 13px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    color: #6b7280;
}
.filter-chip:hover { background: #f3f4f6; }
.filter-chip.active { background: var(--primary); color: white; }

.action-dropdown .dropdown-toggle::after { display: none; }
.action-dropdown .dropdown-menu {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    padding: 8px;
}
.action-dropdown .dropdown-item {
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 13px;
}

.modal-content { border: none; border-radius: 16px; overflow: hidden; }
.modal-header { border-bottom: none; padding: 20px 24px; }
.modal-body { padding: 24px; }
.modal-footer { border-top: none; padding: 16px 24px; }

.btn-primary { background: var(--primary); border-color: var(--primary); }
.btn-primary:hover { background: var(--primary-dark); }

.form-control, .form-select {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 14px;
}
.form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }

.quick-btn {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid #e5e7eb;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}
.quick-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

.table thead th { border-bottom: 2px solid #f3f4f6; font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
.table tbody tr { transition: all 0.2s; }
.table tbody tr:hover { background: #f9fafb; }

.dataTables_wrapper { padding: 20px 0; }
.dataTables_length select, .dataTables_filter input { border-radius: 8px; }

.checkbox-wrapper { position: relative; width: 20px; height: 20px; }
.checkbox-wrapper input { opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer; }
.checkbox-wrapper .checkmark {
    position: absolute;
    width: 20px;
    height: 20px;
    background: white;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    transition: all 0.2s;
}
.checkbox-wrapper input:checked ~ .checkmark { background: var(--primary); border-color: var(--primary); }
.checkbox-wrapper .checkmark::after {
    content: '';
    position: absolute;
    display: none;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.checkbox-wrapper input:checked ~ .checkmark::after { display: block; }

.loading-spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

.fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-users text-primary"></i> User Management</h2>
            <p class="text-muted mb-0">Manage hotspot users, permissions and access</p>
        </div>
        <button class="btn btn-primary btn-lg shadow" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="fas fa-plus-circle me-2"></i> Add New User
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="opacity-75 mb-1">Total Users</p>
                            <h2 class="mb-0"><?= e($stats['total'] ?? 0) ?></h2>
                        </div>
                        <div style="opacity: 0.5;"><i class="fas fa-users fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-white" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="opacity-75 mb-1">Active</p>
                            <h2 class="mb-0"><?= (int) ($stats['active'] ?? 0) ?></h2>
                        </div>
                        <div style="opacity: 0.5;"><i class="fas fa-user-check fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-white" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="opacity-75 mb-1">Blocked</p>
                            <h2 class="mb-0"><?= (int) ($stats['blocked'] ?? 0) ?></h2>
                        </div>
                        <div style="opacity: 0.5;"><i class="fas fa-user-slash fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="opacity-75 mb-1">Showing</p>
                            <h2 class="mb-0"><?= count($users) ?></h2>
                        </div>
                        <div style="opacity: 0.5;"><i class="fas fa-list fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control search-modern"
                           placeholder="Search by username or phone" value="<?= e($search) ?>">
                </div>
                <div class="col-md-4">
                    <?php foreach (['all' => 'All', 'active' => 'Active', 'blocked' => 'Blocked'] as $key => $label): ?>
                        <a href="?filter=<?= e_url($key) ?><?= $search ? '&search=' . e_url($search) : '' ?>"
                           class="filter-chip <?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk actions + user table.
         The checkboxes live inside this form so bulkAction() can append
         the selected ids to it and submit. -->
    <form id="bulkForm" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_action">

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex gap-2 mb-3">
                    <select name="bulk_action" class="form-select" style="max-width: 220px;">
                        <option value="">Bulk action...</option>
                        <option value="active">Activate</option>
                        <option value="blocked">Block</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="button" class="btn btn-outline-primary" data-action="bulkAction">Apply</button>
                </div>

                <div class="table-responsive">
                    <table id="usersTable" class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <label class="checkbox-wrapper">
                                        <input type="checkbox" onclick="toggleAll(this)">
                                        <span class="checkmark"></span>
                                    </label>
                                </th>
                                <th>User</th>
                                <th>Phone</th>
                                <th>Plan</th>
                                <th>Data</th>
                                <th>Balance</th>
                                <th>Valid until</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $i => $u): ?>
                            <tr>
                                <td>
                                    <label class="checkbox-wrapper">
                                        <input type="checkbox" class="user-checkbox" value="<?= (int) $u['id'] ?>">
                                        <span class="checkmark"></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar avatar-<?= ($i % 5) + 1 ?>">
                                            <?= e(strtoupper(substr($u['username'], 0, 2))) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= e($u['username']) ?></div>
                                            <div class="text-muted small"><?= e($u['profile_name'] ?? '-') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($u['phone'] ?? '-') ?></td>
                                <td><?= e($u['plan_type'] ?? '-') ?></td>
                                <td><?= (int) $u['data_limit_mb'] ?> MB</td>
                                <td>Rs. <?= number_format((float) $u['current_balance'], 2) ?></td>
                                <td><?= e($u['valid_until'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'blocked' ? 'danger' : 'secondary') ?>">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown action-dropdown">
                                        <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                        data-bs-target="#userModal"
                                                        data-user="<?= e(json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                                                    <i class="fas fa-edit me-2"></i> Edit
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                        data-bs-target="#topupModal"
                                                        data-user-id="<?= (int) $u['id'] ?>"
                                                        data-username="<?= e($u['username']) ?>">
                                                    <i class="fas fa-database me-2"></i> Top up data
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                        data-bs-target="#rechargeModal"
                                                        data-user-id="<?= (int) $u['id'] ?>"
                                                        data-username="<?= e($u['username']) ?>">
                                                    <i class="fas fa-wallet me-2"></i> Recharge
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="submit" form="toggleForm<?= (int) $u['id'] ?>" class="dropdown-item">
                                                    <i class="fas fa-power-off me-2"></i>
                                                    <?= $u['status'] === 'active' ? 'Block' : 'Activate' ?>
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal"
                                                        data-user-id="<?= (int) $u['id'] ?>"
                                                        data-username="<?= e($u['username']) ?>">
                                                    <i class="fas fa-trash me-2"></i> Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>

    <?php /* Status toggles are separate forms: a form cannot be nested
             inside the bulk form, so they are declared here and the
             buttons above reference them with form="...". */ ?>
    <?php foreach ($users as $u): ?>
        <form id="toggleForm<?= (int) $u['id'] ?>" method="post" class="d-none">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
        </form>
    <?php endforeach; ?>
</div>

<!-- Add / edit user -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="user_id" id="user_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <!-- Never pre-filled: the stored value is a hash and
                                 blank means "keep the current password". -->
                            <input type="password" name="password" id="password" class="form-control"
                                   autocomplete="new-password" placeholder="Leave blank to keep current">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Plan type</label>
                            <select name="plan_type" id="plan_type" class="form-select">
                                <option value="prepaid">Prepaid</option>
                                <option value="postpaid">Postpaid</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Auth method</label>
                            <select name="auth_method" id="auth_method" class="form-select">
                                <option value="password">Password</option>
                                <option value="otp">OTP</option>
                                <option value="mac">MAC</option>
                                <option value="voucher">Voucher</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">MAC address</label>
                            <input type="text" name="mac_address" id="mac_address" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IP address</label>
                            <input type="text" name="ip_address" id="ip_address" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max devices</label>
                            <input type="number" name="max_devices" id="max_devices" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data limit (MB, 0 = unlimited)</label>
                            <input type="number" name="data_limit_mb" id="data_limit_mb" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Speed (kbps)</label>
                            <input type="number" name="speed_kbps" id="speed_kbps" class="form-control" value="1024" min="0">
                            <div class="mt-2 d-flex gap-1">
                                <button type="button" class="quick-btn" onclick="setValue('speed_kbps', 1024)">1M</button>
                                <button type="button" class="quick-btn" onclick="setValue('speed_kbps', 5120)">5M</button>
                                <button type="button" class="quick-btn" onclick="setValue('speed_kbps', 10240)">10M</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valid until</label>
                            <input type="datetime-local" name="valid_until" id="valid_until" class="form-control">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="single_session" id="single_session" value="1" checked>
                                <label class="form-check-label" for="single_session">Single session only</label>
                            </div>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="hos_enabled" id="hos_enabled" value="1">
                                <label class="form-check-label" for="hos_enabled">Restrict to hours</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">From</label>
                            <input type="time" name="hos_start" id="hos_start" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">To</label>
                            <input type="time" name="hos_end" id="hos_end" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save user</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Top up data -->
<div class="modal fade" id="topupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup">
                <input type="hidden" name="user_id" id="topup_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Top up <span id="topup_username"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Data to add (MB)</label>
                    <input type="number" name="topup_mb" class="form-control" value="1024" min="1" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Recharge balance -->
<div class="modal fade" id="rechargeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="recharge">
                <input type="hidden" name="user_id" id="recharge_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Recharge <span id="recharge_username"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Amount (Rs.)</label>
                    <input type="number" step="0.01" name="recharge_amount" class="form-control" min="0.01" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Recharge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirmation -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Delete user</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Permanently delete <strong id="delete_username"></strong>? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: { search: "Search:", lengthMenu: "Show _MENU_" }
    });
});

function setValue(inputName, value) {
    document.querySelector(`[name="${inputName}"]`).value = value;
}

function toggleAll(source) {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = source.checked);
}

function bulkAction() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    if (checked.length === 0) {
        Swal.fire('Error', 'Please select at least one user', 'error');
        return;
    }
    const action = document.querySelector('[name="bulk_action"]').value;
    if (!action) {
        Swal.fire('Error', 'Please select an action', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Confirm Action',
        text: `Apply "${action}" to ${checked.length} user(s)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        confirmButtonText: 'Yes, proceed!'
    }).then(result => {
        if (result.isConfirmed) {
            const form = document.getElementById('bulkForm');
            checked.forEach(cb => {
                let input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = cb.value;
                form.appendChild(input);
            });
            form.submit();
        }
    });
}

document.getElementById('userModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    const userData = btn.getAttribute('data-user');
    
    if (userData) {
        const user = JSON.parse(userData);
        document.getElementById('user_id').value = user.id;
        document.getElementById('username').value = user.username;
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('plan_type').value = user.plan_type || 'prepaid';
        document.getElementById('auth_method').value = user.auth_method || 'browser';
        document.getElementById('mac_address').value = user.mac_address || '';
        document.getElementById('ip_address').value = user.ip_address || '';
        document.getElementById('max_devices').value = user.max_devices || 1;
        document.getElementById('data_limit_mb').value = user.data_limit_mb || 0;
        document.getElementById('speed_kbps').value = user.fup_speed_kbps || 1024;
        document.getElementById('valid_until').value = user.valid_until || '';
        document.getElementById('single_session').checked = user.single_session == 1;
        document.getElementById('password').required = false;
        document.getElementById('modalTitle').textContent = 'Edit User';
    } else {
        document.getElementById('user_id').value = '';
        document.getElementById('username').value = '';
        document.getElementById('password').required = true;
        document.getElementById('modalTitle').textContent = 'Add New User';
    }
});

document.getElementById('topupModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('topup_user_id').value = btn.getAttribute('data-user-id');
    document.getElementById('topup_username').textContent = btn.getAttribute('data-username');
});

document.getElementById('rechargeModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('recharge_user_id').value = btn.getAttribute('data-user-id');
    document.getElementById('recharge_username').textContent = btn.getAttribute('data-username');
});

document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('delete_user_id').value = btn.getAttribute('data-user-id');
    document.getElementById('delete_username').textContent = btn.getAttribute('data-username');
});
</script>

</div><!-- /.main-content -->
</body>
</html>
