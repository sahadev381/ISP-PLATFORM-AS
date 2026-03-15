<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<h2>PPPoE Live Usage (MB)</h2>
<div class="table-box">
    <canvas id="usageChart" style="width:100%; height:400px;"></canvas>
</div>

<script>
    // Pass PHP username to JS
    var username = <?= json_encode($user['username']) ?>;
</script>

<!-- Load Chart.js & jQuery -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/live_chart.js"></script>

