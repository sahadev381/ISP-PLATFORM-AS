<?php
include 'config.php';
include 'includes/auth.php';

$username = $_GET['user'] ?? '';
if (!$username) die('User not specified');

$username_safe = $conn->real_escape_string($username);

/* Fetch user + plan */
$user = $conn->query("
    SELECT customers.username, plans.speed
    FROM customers
    LEFT JOIN plans ON customers.plan_id = plans.id
    WHERE customers.username = '$username_safe'
")->fetch_assoc();

if (!$user) die('User not found');

/* Extract numeric speed (10M ? 10) */
$planSpeed = (int) filter_var($user['speed'], FILTER_SANITIZE_NUMBER_INT);
if ($planSpeed <= 0) $planSpeed = 10; // fallback

$page_title = "Live Usage - {$user['username']}";
$active = "users";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';

?>

<div class="main">

    <div class="table-box">
        <canvas id="liveChart" height="120"></canvas>
    </div>
</div>

<script>
    const username   = <?= json_encode($user['username']) ?>;
    const PLAN_SPEED = <?= $planSpeed ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/live_chart.js"></script>

<?php include 'includes/footer.php'; ?>

