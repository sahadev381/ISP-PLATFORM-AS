<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Network Observability & Performance";
$active = "nas";

// Fetch last 24 hours of Server CPU/MEM
$server_metrics = $conn->query("
    SELECT metric_type, metric_value, recorded_at 
    FROM performance_metrics 
    WHERE target_type = 'SERVER' AND recorded_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY recorded_at ASC
")->fetch_all(MYSQLI_ASSOC);

// Format for ChartJS
$labels = []; $cpu_data = []; $mem_data = [];
foreach($server_metrics as $m) {
    $time = date('H:i', strtotime($m['recorded_at']));
    if(!in_array($time, $labels)) $labels[] = $time;
    if($m['metric_type'] == 'CPU') $cpu_data[] = $m['metric_value'];
    if($m['metric_type'] == 'MEM') $mem_data[] = $m['metric_value'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 25px;">
    
    <div style="margin-bottom: 30px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-gauge-high"></i> Network Observability</h2>
        <p style="color:#64748b;">Infrastructure health and time-series performance analytics.</p>
    </div>

    <!-- Chart Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
        
        <!-- CPU Chart -->
        <div style="background:#fff; padding:25px; border-radius:15px; box-shadow:0 4px 6px rgba(0,0,0,0.02); border:1px solid #f1f5f9;">
            <h4 style="margin-top:0; color:#1e293b;"><i class="fa fa-microchip" style="color:#3b82f6;"></i> Server CPU Load (24h)</h4>
            <div style="height: 300px;"><canvas id="cpuChart"></canvas></div>
        </div>

        <!-- RAM Chart -->
        <div style="background:#fff; padding:25px; border-radius:15px; box-shadow:0 4px 6px rgba(0,0,0,0.02); border:1px solid #f1f5f9;">
            <h4 style="margin-top:0; color:#1e293b;"><i class="fa fa-memory" style="color:#10b981;"></i> Memory Utilization (24h)</h4>
            <div style="height: 300px;"><canvas id="memChart"></canvas></div>
        </div>

    </div>

    <!-- OLT Health Table -->
    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <h3>Real-time OLT Load Monitor</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top:20px;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;">OLT Hardware</th>
                    <th style="padding: 15px;">Current CPU</th>
                    <th style="padding: 15px;">Current Temp</th>
                    <th style="padding: 15px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $olts = $conn->query("SELECT * FROM nas WHERE device_type='olt'");
                while($o = $olts->fetch_assoc()): 
                    include_once 'includes/olt_api.php';
                    $driver = new OLT_Driver($o);
                    $h = $driver->getHealth();
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 15px;"><b><?= $o['nasname'] ?></b><br><small><?= $o['ip_address'] ?></small></td>
                    <td style="padding: 15px; font-weight:700;"><?= $h['cpu'] ?>%</td>
                    <td style="padding: 15px;"><?= $h['temp'] ?>°C</td>
                    <td style="padding: 15px;"><span class="badge active">HEALTHY</span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // CPU Chart
    new Chart(document.getElementById('cpuChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'CPU Usage %',
                data: <?= json_encode($cpu_data) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // MEM Chart
    new Chart(document.getElementById('memChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'RAM Usage %',
                data: <?= json_encode($mem_data) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>

<?php include 'includes/footer.php'; ?>
