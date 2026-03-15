<?php
$base_path = '../';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Financial Analytics";
$active = "reports";

// Check connection
if (!isset($conn)) { die("DB Connection Error"); }

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

// 1. Total Revenue (from recharge table as it's the most reliable for ISP payments)
$revenue_query = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count,
        SUM(amount) as total
    FROM recharge 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$revenue_res = $conn->query($revenue_query);

$revenue_data = [];
$total_revenue = 0;
$total_recharges = 0;
if ($revenue_res) {
    while ($row = $revenue_res->fetch_assoc()) {
        $revenue_data[] = $row;
        $total_revenue += $row['total'];
        $total_recharges += $row['count'];
    }
}

// 2. Payment Gateway Breakdown (from wallet_transactions)
$gateway_query = "
    SELECT 
        gateway,
        COUNT(*) as count,
        SUM(amount) as total
    FROM wallet_transactions 
    WHERE status IN ('success', 'PAID', 'Completed')
    AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY gateway
";
$gateway_res = $conn->query($gateway_query);

// 3. Top Paying Customers
$top_cust_query = "
    SELECT 
        username,
        COUNT(*) as count,
        SUM(amount) as total
    FROM recharge 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY username
    ORDER BY total DESC
    LIMIT 5
";
$top_cust_res = $conn->query($top_cust_query);

include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .fin-container { padding: 20px; }
    .filter-card { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .stat-card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid #3b82f6; }
    .stat-card.green { border-left-color: #10b981; }
    .stat-card h3 { margin: 0; font-size: 24px; color: #1e293b; }
    .stat-card p { margin: 5px 0 0; color: #64748b; font-size: 14px; text-transform: uppercase; }
    
    .chart-container { background: #fff; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    
    .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .data-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .data-card-header { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #1e293b; }
    
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 12px 20px; font-size: 12px; color: #64748b; text-transform: uppercase; background: #f8fafc; }
    td { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
</style>

<div class="fin-container">
    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 13px; margin-bottom: 5px; color: #64748b;">Start Date</label>
                <input type="date" name="start" value="<?= $start_date ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 13px; margin-bottom: 5px; color: #64748b;">End Date</label>
                <input type="date" name="end" value="<?= $end_date ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                <i class="fa fa-filter"></i> Apply Filter
            </button>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <h3>NPR <?= number_format($total_revenue, 2) ?></h3>
            <p>Total Revenue</p>
        </div>
        <div class="stat-card green">
            <h3><?= number_format($total_recharges) ?></h3>
            <p>Total Recharges</p>
        </div>
        <div class="stat-card" style="border-left-color: #8b5cf6;">
            <h3>NPR <?= $total_recharges > 0 ? number_format($total_revenue / $total_recharges, 2) : 0 ?></h3>
            <p>Avg. Per Recharge</p>
        </div>
    </div>

    <!-- Chart -->
    <div class="chart-container">
        <h4 style="margin-top: 0; color: #1e293b;">Daily Revenue Trend</h4>
        <canvas id="revChart" height="100"></canvas>
    </div>

    <!-- Tables Grid -->
    <div class="data-grid">
        <div class="data-card">
            <div class="data-card-header">Payment Gateways</div>
            <table>
                <thead>
                    <tr><th>Gateway</th><th>Count</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    <?php if ($gateway_res && $gateway_res->num_rows > 0): ?>
                        <?php while($g = $gateway_res->fetch_assoc()): ?>
                            <tr>
                                <td><span style="text-transform: capitalize;"><?= htmlspecialchars($g['gateway']) ?></span></td>
                                <td><?= $g['count'] ?></td>
                                <td style="font-weight: 600;">NPR <?= number_format($g['total'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8;">No gateway data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="data-card">
            <div class="data-card-header">Top Customers (By Revenue)</div>
            <table>
                <thead>
                    <tr><th>Customer</th><th>Count</th><th>Total Paid</th></tr>
                </thead>
                <tbody>
                    <?php if ($top_cust_res && $top_cust_res->num_rows > 0): ?>
                        <?php while($tc = $top_cust_res->fetch_assoc()): ?>
                            <tr>
                                <td><a href="<?= $base_path ?>user_view.php?username=<?= urlencode($tc['username']) ?>" style="text-decoration: none; color: #3b82f6; font-weight: 600;"><?= htmlspecialchars($tc['username']) ?></a></td>
                                <td><?= $tc['count'] ?></td>
                                <td style="font-weight: 600; color: #10b981;">NPR <?= number_format($tc['total'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8;">No customer data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('revChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(function($d) { return date('M d', strtotime($d['date'])); }, $revenue_data)) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_column($revenue_data, 'total')) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>

<?php include $base_path . 'includes/footer.php'; ?>
