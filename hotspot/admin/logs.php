<?php
session_start();
$page_title = "Access Logs";
$page = 'logs';
$base_path = '.';

chdir(__DIR__ . '/../..');
include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get filter parameters
$filterAction = $_GET['action'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterUser = $_GET['username'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$sql = "SELECT * FROM hotspot_access_logs WHERE 1=1";
if ($filterAction) $sql .= " AND action = '$filterAction'";
if ($filterStatus) $sql .= " AND status = '$filterStatus'";
if ($filterUser) $sql .= " AND username LIKE '%$filterUser%'";
if ($dateFrom) $sql .= " AND DATE(created_at) >= '$dateFrom'";
if ($dateTo) $sql .= " AND DATE(created_at) <= '$dateTo'";
$sql .= " ORDER BY created_at DESC LIMIT 500";

$logs = $conn->query($sql);

// Get stats
$stats = $conn->query("
    SELECT action, status, COUNT(*) as cnt 
    FROM hotspot_access_logs 
    WHERE DATE(created_at) = CURDATE() 
    GROUP BY action, status
")->fetch_all(MYSQLI_ASSOC);

include 'includes/header_hotspot.php';
?>

<div class="container-fluid p-4">
    <div style="margin-bottom: 25px;">
        <h2 style="margin: 0; color: #1e293b; font-weight: 700;">
            <i class="fa fa-history" style="color: var(--primary);"></i> Access Logs
        </h2>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label>Action</label>
                    <select name="action" class="form-select">
                        <option value="">All</option>
                        <option value="login" <?= $filterAction == 'login' ? 'selected' : '' ?>>Login</option>
                        <option value="logout" <?= $filterAction == 'logout' ? 'selected' : '' ?>>Logout</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="success" <?= $filterStatus == 'success' ? 'selected' : '' ?>>Success</option>
                        <option value="failed" <?= $filterStatus == 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="blocked" <?= $filterStatus == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($filterUser) ?>">
                </div>
                <div class="col-md-2">
                    <label>From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
                </div>
                <div class="col-md-2">
                    <label>To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-body">
            <table class="table table-striped table-sm" id="logsTable">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Username</th>
                        <th>IP Address</th>
                        <th>MAC Address</th>
                        <th>Action</th>
                        <th>Auth Method</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('M d, H:i:s', strtotime($log['created_at'])) ?></td>
                        <td><?= htmlspecialchars($log['username'] ?? '-') ?></td>
                        <td><code><?= $log['ip_address'] ?? '-' ?></code></td>
                        <td><code><?= $log['mac_address'] ?? '-' ?></code></td>
                        <td><?= $log['action'] ?></td>
                        <td><?= $log['auth_method'] ?? '-' ?></td>
                        <td>
                            <span class="badge bg-<?= 
                                $log['status'] == 'success' ? 'success' : 
                                ($log['status'] == 'failed' ? 'warning' : 'danger')
                            ?>">
                                <?= $log['status'] ?>
                            </span>
                        </td>
                        <td><small><?= htmlspecialchars($log['message'] ?? '-') ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>