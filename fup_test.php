<?php
include 'config.php';
include 'includes/auth.php';

// Fetch all plans for the JS simulator
$plans_res = $conn->query("SELECT * FROM plans ORDER BY price ASC");
$plans = [];
while($p = $plans_res->fetch_assoc()) {
    $plans[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'base_speed' => $p['speed'],
        'data_limit' => (float)$p['data_limit'] / 1073741824, // to GB
        'fup1_limit' => (float)$p['fup1_limit'] / 1073741824,
        'fup1_speed' => $p['fup1_speed'],
        'fup2_limit' => (float)$p['fup2_limit'] / 1073741824,
        'fup2_speed' => $p['fup2_speed'],
        'fup3_limit' => (float)$p['fup3_limit'] / 1073741824,
        'fup3_speed' => $p['fup3_speed'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUP Tester Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #2d4343;
            --card-bg: #364f4f;
            --text-main: #ffffff;
            --text-dim: #cbd5e1;
            --accent-blue: #3b82f6;
            --highlight-bg: #f0f9ff;
            --highlight-text: #3b82f6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .tester-container {
            background-color: var(--card-bg);
            width: 100%;
            max-width: 600px;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        h1 { margin: 0 0 25px 0; font-size: 28px; font-weight: 500; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 10px; color: var(--text-dim); font-size: 14px; }

        select {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #4a6161;
            background: #fff;
            color: #333;
            font-size: 16px;
            outline: none;
        }

        /* Range Slider Styling */
        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
            margin: 15px 0;
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 6px;
            cursor: pointer;
            background: #4a6161;
            border-radius: 3px;
        }
        input[type=range]::-webkit-slider-thumb {
            height: 20px;
            width: 20px;
            border-radius: 50%;
            background: #94a3b8;
            cursor: pointer;
            -webkit-appearance: none;
            margin-top: -7px;
            box-shadow: 0 0 5px rgba(0,0,0,0.5);
        }

        .usage-text { font-size: 16px; margin-bottom: 10px; }
        .usage-text b { font-size: 18px; }

        /* Progress Bar */
        .progress-container {
            background: #4a6161;
            height: 14px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .progress-bar {
            height: 100%;
            background: var(--accent-blue);
            width: 0%;
            transition: width 0.1s ease;
        }

        /* Result Card */
        .result-card {
            background: var(--highlight-bg);
            color: #333;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .result-speed {
            font-size: 24px;
            font-weight: 700;
            color: var(--highlight-text);
            margin-bottom: 5px;
        }
        .result-tier { font-size: 14px; color: #64748b; }

        /* Tiers Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            border: 1px solid #4a6161;
        }
        th {
            background: var(--accent-blue);
            color: white;
            text-align: center;
            padding: 12px;
            border: 1px solid #4a6161;
        }
        td {
            text-align: center;
            padding: 12px;
            border: 1px solid #4a6161;
        }
        tr:nth-child(even) { background: rgba(255,255,255,0.03); }
    </style>
</head>
<body>

<div class="tester-container">
    <h1>FUP Tester Panel</h1>

    <div class="form-group">
        <label>Select Plan</label>
        <select id="planSelect" onchange="updateSimulation()">
            <?php foreach($plans as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['base_speed'] ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Usage (GB)</label>
        <input type="range" id="usageSlider" min="0" max="2000" value="0" oninput="updateSimulation()">
        <div class="usage-text">Usage: <b id="usageVal">0</b> <b>GB</b></div>
    </div>

    <div class="progress-container">
        <div id="progressBar" class="progress-bar"></div>
    </div>

    <div class="result-card">
        <div id="resSpeed" class="result-speed">Speed: --</div>
        <div id="resTier" class="result-tier">FUP Tier: --</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 33%;">FUP Tier</th>
                <th style="width: 33%;">Limit (GB)</th>
                <th>Speed</th>
            </tr>
        </thead>
        <tbody id="tierTableBody">
            <!-- Dynamic -->
        </tbody>
    </table>
</div>

<script>
    const plans = <?= json_encode($plans) ?>;
    
    function updateSimulation() {
        const planId = document.getElementById('planSelect').value;
        const usage = parseInt(document.getElementById('usageSlider').value);
        const plan = plans.find(p => p.id == planId);

        if(!plan) return;

        // Update Usage Text
        document.getElementById('usageVal').innerText = usage;

        // Logic
        let currentSpeed = plan.base_speed;
        let currentTier = "Normal Speed";
        
        if (plan.fup3_limit > 0 && usage >= plan.fup3_limit) {
            currentSpeed = plan.fup3_speed || '1M/1M';
            currentTier = "Tier 3 (Critical)";
        } else if (plan.fup2_limit > 0 && usage >= plan.fup2_limit) {
            currentSpeed = plan.fup2_speed;
            currentTier = "Tier 2 Speed";
        } else if (plan.fup1_limit > 0 && usage >= plan.fup1_limit) {
            currentSpeed = plan.fup1_speed;
            currentTier = "Tier 1 Speed";
        }

        if (plan.data_limit > 0 && usage >= plan.data_limit) {
            currentSpeed = "512k/512k";
            currentTier = "Exceeded (Blocked)";
        }

        // Update Result Card
        document.getElementById('resSpeed').innerText = "Speed: " + currentSpeed;
        document.getElementById('resTier').innerText = "FUP Tier: " + currentTier;

        // Update Progress Bar (relative to highest limit or slider max)
        const maxLimit = Math.max(plan.data_limit, plan.fup3_limit, plan.fup2_limit, plan.fup1_limit, 100);
        const progress = Math.min(100, (usage / maxLimit) * 100);
        document.getElementById('progressBar').style.width = progress + "%";

        // Update Table
        let tableHtml = `
            <tr><td>FUP 1</td><td>${Math.round(plan.fup1_limit)} GB</td><td>${plan.fup1_speed || '--'}</td></tr>
            <tr><td>FUP 2</td><td>${Math.round(plan.fup2_limit)} GB</td><td>${plan.fup2_speed || '--'}</td></tr>
            <tr><td>FUP 3</td><td>${Math.round(plan.fup3_limit)} GB</td><td>${plan.fup3_speed || '--'}</td></tr>
        `;
        document.getElementById('tierTableBody').innerHTML = tableHtml;
    }

    // Initial run
    updateSimulation();
</script>

</body>
</html>
