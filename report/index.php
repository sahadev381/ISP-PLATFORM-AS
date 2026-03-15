<?php
$base_path = '../';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Reports Dashboard";
$active = "reports";

// Check if $conn exists
if (!isset($conn)) {
    die("Database connection failed. Please check config.php.");
}

// Helper function for statistics
function getStat($db, $query) {
    $res = $db->query($query);
    return $res ? $res->fetch_assoc()['total'] : 0;
}

// 1. Total Customers
$total_users = getStat($conn, "SELECT COUNT(*) AS total FROM customers");

// 2. New Customers (Last 30 days) - Updated logic to handle cases where created_at might be null or format issue
$new_users = getStat($conn, "SELECT COUNT(*) AS total FROM customers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

// 3. Expiring Soon (Next 7 days)
$expiring_users = getStat($conn, "SELECT COUNT(*) AS total FROM customers WHERE expiry >= CURDATE() AND expiry <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");

// 4. Expired Customers
$expired_users = getStat($conn, "SELECT COUNT(*) AS total FROM customers WHERE expiry < CURDATE()");

// 5. Active Online Users (PPPoE)
$active_users = getStat($conn, "SELECT COUNT(DISTINCT username) AS total FROM radacct WHERE acctstoptime IS NULL");

include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        padding: 20px;
    }

    .stat-card {
        background: #fff;
        border-radius: 15px;
        padding: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
        border: 1px solid #f0f0f0;
        text-decoration: none;
        color: inherit;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px rgba(0,0,0,0.1);
    }

    .stat-info h2 {
        font-size: 28px;
        font-weight: 700;
        margin: 0;
        color: #1e293b;
    }

    .stat-info p {
        margin: 5px 0 0;
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    /* Card Themes */
    .card-total { border-left: 5px solid #3b82f6; }
    .card-total .stat-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

    .card-new { border-left: 5px solid #10b981; }
    .card-new .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }

    .card-expiring { border-left: 5px solid #f59e0b; }
    .card-expiring .stat-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .card-expired { border-left: 5px solid #ef4444; }
    .card-expired .stat-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .card-active { border-left: 5px solid #8b5cf6; }
    .card-active .stat-icon { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

    .section-header {
        padding: 0 20px;
        margin-top: 20px;
    }
    
    .section-header h3 {
        color: #1e293b;
        font-weight: 600;
    }
</style>

<div class="section-header">
    <h3>Business Overview</h3>
</div>

<div class="report-grid">
    <!-- Total Users -->
    <a href="<?= $base_path ?>users.php" class="stat-card card-total">
        <div class="stat-info">
            <h2><?= number_format($total_users) ?></h2>
            <p>Total Customers</p>
        </div>
        <div class="stat-icon">
            <i class="fa fa-users"></i>
        </div>
    </a>

    <!-- New Users -->
    <a href="new_users.php" class="stat-card card-new">
        <div class="stat-info">
            <h2><?= number_format($new_users) ?></h2>
            <p>New (30 Days)</p>
        </div>
        <div class="stat-icon">
            <i class="fa fa-user-plus"></i>
        </div>
    </a>

    <!-- Active Users -->
    <a href="active_users.php" class="stat-card card-active">
        <div class="stat-info">
            <h2><?= number_format($active_users) ?></h2>
            <p>Currently Online</p>
        </div>
        <div class="stat-icon">
            <i class="fa fa-signal"></i>
        </div>
    </a>

    <!-- Expiring Users -->
    <a href="expiring_users.php" class="stat-card card-expiring">
        <div class="stat-info">
            <h2><?= number_format($expiring_users) ?></h2>
            <p>Expiring (7 Days)</p>
        </div>
        <div class="stat-icon">
            <i class="fa fa-clock"></i>
        </div>
    </a>

    <!-- Expired Users -->
    <a href="expired_users.php" class="stat-card card-expired">
        <div class="stat-info">
            <h2><?= number_format($expired_users) ?></h2>
            <p>Expired Customers</p>
        </div>
        <div class="stat-icon">
            <i class="fa fa-user-xmark"></i>
        </div>
    </a>
</div>

<div class="section-header" style="margin-top: 40px;">
    <h3>Quick Actions</h3>
</div>

<div class="report-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <a href="<?= $base_path ?>reports/financial.php" class="stat-card" style="justify-content: center; gap: 15px; border-bottom: 3px solid #64748b;">
        <i class="fa fa-file-invoice-dollar" style="color: #64748b; font-size: 20px;"></i>
        <span style="font-weight: 600;">Financial Report</span>
    </a>
    <a href="<?= $base_path ?>export_report.php" class="stat-card" style="justify-content: center; gap: 15px; border-bottom: 3px solid #64748b;">
        <i class="fa fa-download" style="color: #64748b; font-size: 20px;"></i>
        <span style="font-weight: 600;">Export All Data</span>
    </a>
</div>

<?php include $base_path . 'includes/footer.php'; ?>
