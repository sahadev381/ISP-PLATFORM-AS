<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$base_path = './';
include 'config.php';
include 'includes/auth.php';

$page_title = "Customer Management";
$active = "users";

/* ============================
   ONLINE USERS LIST
============================ */
$online_list = [];
$res_online = $conn->query("SELECT DISTINCT username FROM radacct WHERE acctstoptime IS NULL");
if($res_online){
    while ($row = $res_online->fetch_assoc()) {
        $online_list[] = $row['username'];
    }
}

/*=====================
   STATS CALCULATION
============================ */
$total_users   = $conn->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'];
$active_users  = $conn->query("SELECT COUNT(*) c FROM customers WHERE status='active'")->fetch_assoc()['c'];
$expired_users = $conn->query("SELECT COUNT(*) c FROM customers WHERE expiry < CURDATE()")->fetch_assoc()['c'];
$online_users  = $conn->query("SELECT COUNT(DISTINCT username) c FROM radacct WHERE acctstoptime IS NULL")->fetch_assoc()['c'];

/* ============================
   SEARCH & FILTER LOGIC
============================ */
$q = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';
$q_safe = $conn->real_escape_string($q);

$where_clauses = [];
if ($q) {
    $where_clauses[] = "(c.username LIKE '%$q_safe%' OR c.full_name LIKE '%$q_safe%' OR c.phone LIKE '%$q_safe%' OR c.address LIKE '%$q_safe%')";
}
if ($status_filter == 'active') {
    $where_clauses[] = "c.expiry >= CURDATE()";
} elseif ($status_filter == 'expired') {
    $where_clauses[] = "c.expiry < CURDATE()";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$query = "
    SELECT c.*, p.name AS plan_name, p.speed, b.name AS branch_name
    FROM customers c
    LEFT JOIN plans p ON c.plan_id = p.id
    LEFT JOIN branches b ON c.branch_id = b.id
    $where_sql
    ORDER BY c.created_at DESC
";
$users = $conn->query($query);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .users-container { padding: 20px; }
    
    /* Stats Cards */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9; }
    .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-data h3 { margin: 0; font-size: 20px; color: #1e293b; }
    .stat-data p { margin: 0; font-size: 13px; color: #64748b; font-weight: 500; }
    
    /* Search Bar */
    .action-bar { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; }
    .search-box { display: flex; gap: 10px; flex: 1; max-width: 500px; }
    .search-input { flex: 1; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; transition: all 0.2s; }
    .search-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    
    /* Table Styling */
    .table-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: #f8fafc; padding: 15px 20px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; }
    tr:hover { background: #f8fafc; }
    
    /* Badges */
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; }
    .badge-online { background: #dcfce7; color: #10b981; }
    .badge-offline { background: #f1f5f9; color: #94a3b8; }
    .badge-active { background: #e0f2fe; color: #0ea5e9; }
    .badge-expired { background: #fee2e2; color: #ef4444; }
    
    .user-avatar { width: 35px; height: 35px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #475569; font-size: 13px; }
    .user-info-cell { display: flex; align-items: center; gap: 12px; }
    
    .btn-icon { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; text-decoration: none; }
    .btn-view { background: #eff6ff; color: #3b82f6; }
    .btn-edit { background: #f0fdf4; color: #22c55e; }
    .btn-delete { background: #fef2f2; color: #ef4444; border: none; cursor: pointer; }
    .btn-icon:hover { transform: translateY(-2px); filter: brightness(0.95); }
</style>

<div class="users-container">
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i class="fa fa-users"></i></div>
            <div class="stat-data"><h3><?= number_format($total_users) ?></h3><p>Total Customers</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fa fa-signal"></i></div>
            <div class="stat-data"><h3><?= number_format($online_users) ?></h3><p>Currently Online</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;"><i class="fa fa-user-check"></i></div>
            <div class="stat-data"><h3><?= number_format($active_users) ?></h3><p>Active Subscription</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;"><i class="fa fa-user-xmark"></i></div>
            <div class="stat-data"><h3><?= number_format($expired_users) ?></h3><p>Expired Users</p></div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <form method="GET" class="search-box">
            <input type="text" name="q" class="search-input" placeholder="Search by name, phone or username..." value="<?= htmlspecialchars($q) ?>">
            <select name="status" class="search-input" style="max-width: 150px;">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter=='active'?'selected':'' ?>>Active</option>
                <option value="expired" <?= $status_filter=='expired'?'selected':'' ?>>Expired</option>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Search</button>
        </form>
        
        <a href="user_add.php" class="btn btn-primary">
            <i class="fa fa-user-plus"></i> New Customer
        </a>
    </div>

    <!-- Users Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Plan & Speed</th>
                        <th>PPP Status</th>
                        <th>Expiry Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while($u = $users->fetch_assoc()): 
                            $initials = strtoupper(substr($u['username'], 0, 2));
                            $is_online = in_array($u['username'], $online_list);
                            $is_expired = strtotime($u['expiry']) < time();
                        ?>
                        <tr>
                            <td>
                                <div class="user-info-cell">
                                    <div class="user-avatar"><?= $initials ?></div>
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($u['username']) ?></div>
                                        <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($u['full_name'] ?? 'No Name') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 500;"><?= htmlspecialchars($u['phone'] ?? '-') ?></div>
                                <div style="font-size: 11px; color: #94a3b8;"><?= htmlspecialchars($u['address'] ?? 'No Address') ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($u['plan_name'] ?? 'N/A') ?></div>
                                <div style="font-size: 12px; color: #10b981; font-weight: 600;"><?= htmlspecialchars($u['speed'] ?? '-') ?></div>
                            </td>
                            <td>
                                <?php if($is_online): ?>
                                    <span class="badge badge-online"><i class="fa fa-circle" style="font-size: 7px;"></i> Online</span>
                                <?php else: ?>
                                    <span class="badge badge-offline">Offline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($is_expired): ?>
                                    <span class="badge badge-expired">Expired</span>
                                    <div style="font-size: 11px; color: #ef4444; margin-top: 4px; font-weight: 500;"><?= date('M d, Y', strtotime($u['expiry'])) ?></div>
                                <?php else: ?>
                                    <span class="badge badge-active">Active</span>
                                    <div style="font-size: 11px; color: #0ea5e9; margin-top: 4px; font-weight: 500;"><?= date('M d, Y', strtotime($u['expiry'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="user_view.php?user=<?= urlencode($u['username']) ?>" class="btn-icon btn-view" title="View Details">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="user_edit.php?user=<?= urlencode($u['username']) ?>" class="btn-icon btn-edit" title="Edit Customer">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a href="users.php?del=<?= urlencode($u['username']) ?>" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Permanently delete this customer?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 50px; color: #94a3b8;">No customers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
