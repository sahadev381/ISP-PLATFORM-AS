<?php
include '../config.php';
// session_start(); handled in config.php

if (!isset($_SESSION['customer_user'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['customer_user'];

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

function getGB($bytes) {
    return round($bytes / (1024 * 1024 * 1024), 2);
}

$usage_history = $conn->query("
    SELECT 
        DATE_FORMAT(acctstarttime, '%b %Y') as month,
        SUM(acctoutputoctets) as download,
        SUM(acctinputoctets) as upload
    FROM radacct 
    WHERE username = '$username' 
    GROUP BY month 
    ORDER BY acctstarttime ASC 
    LIMIT 12
");

$data = $usage_history->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usage History - ISP Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 0; color: #1e293b; }
        .header { background: #fff; padding: 15px 20px; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .container { padding: 20px; max-width: 900px; margin: 0 auto; }
        
        .usage-card { background: #fff; border-radius: 24px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid #f1f5f9; }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .stat-box { background: #f8fafc; padding: 15px; border-radius: 15px; border: 1px solid #e2e8f0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #f1f5f9; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; font-weight: 500; }
        
        .month-col { font-weight: 700; color: #1e293b; }
        .total-col { color: #3b82f6; font-weight: 800; }
    </style>
</head>
<body>

<div class="header">
    <a href="dashboard.php" style="color:#1e293b; text-decoration:none; width:40px; height:40px; display:flex; align-items:center; justify-content:center; background:#f1f5f9; border-radius:12px; margin-right:15px;"><i class="fa fa-arrow-left"></i></a>
    <div style="font-weight: 800; font-size: 18px;">Usage Analytics</div>
</div>

<div class="container">
    
    <div class="usage-card">
        <h4 style="margin:0 0 20px; font-size:14px; text-transform:uppercase; color:#64748b;"><i class="fa fa-chart-line"></i> Monthly Data Trend (GB)</h4>
        <div style="height: 250px; width:100%;">
            <canvas id="usageChart"></canvas>
        </div>
    </div>

    <div class="usage-card">
        <h4 style="margin:0 0 15px; font-size:14px; text-transform:uppercase; color:#64748b;"><i class="fa fa-table"></i> Detailed Logs</h4>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Download</th>
                        <th>Upload</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach(array_reverse($data) as $row): ?>
                    <tr>
                        <td class="month-col"><?= $row['month'] ?></td>
                        <td><?= formatBytes($row['download']) ?></td>
                        <td><?= formatBytes($row['upload']) ?></td>
                        <td class="total-col"><?= formatBytes($row['download'] + $row['upload']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('usageChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($data, 'month')) ?>,
        datasets: [{
            label: 'Total Usage (GB)',
            data: <?= json_encode(array_map(function($r){ return getGB($r['download'] + $r['upload']); }, $data)) ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.2)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});
</script>

</body>
</html>
