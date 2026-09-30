<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php';

$username = $_GET['user'] ?? '';
// Refuse customers belonging to another branch.
require_customer_access($conn, $username);

if (!$username) die('User not specified');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="main">

    <div style="margin-bottom:15px;">
        <button class="btn range" data-range="daily">Daily</button>
        <button class="btn range" data-range="weekly">Weekly</button>
        <button class="btn range" data-range="monthly">Monthly</button>
        <button class="btn range" data-range="yearly">Yearly</button>
    </div>

    <div class="table-box">
        <canvas id="usageChart" height="120"></canvas>
    </div>
</div>

<script>
const username = <?= json_encode($username) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="assets/js/chart.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>

