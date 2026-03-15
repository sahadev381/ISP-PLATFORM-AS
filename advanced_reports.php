<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Advanced ISP Reports";
$active = "reports";

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 20px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-chart-pie"></i> Advanced Reporting Center</h2>
        <div style="color:#64748b; font-size:13px;">Generate detailed insights and export to Excel</div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
        
        <!-- Financial Report Card -->
        <div class="report-card">
            <div class="rc-icon" style="background:#eff6ff; color:#3b82f6;"><i class="fa fa-money-bill-wave"></i></div>
            <div class="rc-content">
                <h3>Financial & Collection</h3>
                <p>Daily collection, online payments, and due invoices.</p>
                <form action="report_api.php" method="POST" target="_blank">
                    <input type="hidden" name="type" value="financial">
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <input type="date" name="from" class="form-control" required value="<?= date('Y-m-01') ?>">
                        <input type="date" name="to" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <button class="btn btn-primary" style="width:100%; margin-top:10px;"><i class="fa fa-download"></i> Export Excel</button>
                </form>
            </div>
        </div>

        <!-- Expiry & Renewals -->
        <div class="report-card">
            <div class="rc-icon" style="background:#fef2f2; color:#ef4444;"><i class="fa fa-calendar-xmark"></i></div>
            <div class="rc-content">
                <h3>Expiry & Renewals</h3>
                <p>List of expired users, upcoming expiries, and inactive accounts.</p>
                <form action="report_api.php" method="POST" target="_blank">
                    <input type="hidden" name="type" value="expiry">
                    <div style="margin-top:10px;">
                        <select name="status" class="form-control">
                            <option value="expired">Already Expired</option>
                            <option value="upcoming_3">Expiring in 3 Days</option>
                            <option value="upcoming_7">Expiring in 7 Days</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" style="width:100%; margin-top:10px;"><i class="fa fa-download"></i> Export Excel</button>
                </form>
            </div>
        </div>

        <!-- Infrastructure & Faults -->
        <div class="report-card">
            <div class="rc-icon" style="background:#fff7ed; color:#f59e0b;"><i class="fa fa-triangle-exclamation"></i></div>
            <div class="rc-content">
                <h3>Faults & Maintenance</h3>
                <p>History of network faults, resolution times, and affected areas.</p>
                <form action="report_api.php" method="POST" target="_blank">
                    <input type="hidden" name="type" value="faults">
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <input type="date" name="from" class="form-control" required value="<?= date('Y-m-01') ?>">
                        <input type="date" name="to" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <button class="btn btn-primary" style="width:100%; margin-top:10px;"><i class="fa fa-download"></i> Export Excel</button>
                </form>
            </div>
        </div>

        <!-- Wire Lease & Fiber Usage -->
        <div class="report-card">
            <div class="rc-icon" style="background:#f0fdf4; color:#16a34a;"><i class="fa fa-network-wired"></i></div>
            <div class="rc-content">
                <h3>Wire Usage & Leases</h3>
                <p>Fiber route utilization, core availability, and lease revenue.</p>
                <form action="report_api.php" method="POST" target="_blank">
                    <input type="hidden" name="type" value="fiber">
                    <button class="btn btn-primary" style="width:100%; margin-top:52px;"><i class="fa fa-download"></i> Export Inventory</button>
                </form>
            </div>
        </div>

    </div>
</div>

<style>
    .report-card { background: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; gap: 20px; align-items: flex-start; }
    .rc-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .rc-content { flex: 1; }
    .rc-content h3 { margin: 0 0 5px 0; font-size: 16px; color: #1e293b; }
    .rc-content p { margin: 0; color: #64748b; font-size: 13px; margin-bottom: 15px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
</style>

<?php include 'includes/footer.php'; ?>
