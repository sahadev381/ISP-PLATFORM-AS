<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

$username = $_GET['user'] ?? '';
// Refuse customers belonging to another branch.
require_customer_access($conn, $username);

if (!$username) die('User not specified');

/* Fetch user + plan */
$user = db_one($conn, "
    SELECT customers.username, plans.speed
    FROM customers
    LEFT JOIN plans ON customers.plan_id = plans.id
    WHERE customers.username = ?
", [$username]);

if (!$user) die('User not found');

/* Extract numeric speed (10M ? 10) */
$planSpeed = (int) filter_var($user['speed'] ?? '', FILTER_SANITIZE_NUMBER_INT);
if ($planSpeed <= 0) $planSpeed = 10; // fallback

$page_title = "Live Usage - {$user['username']}";
$active = "users";
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

?>

<div class="main">

    <div class="table-box">
        <canvas id="liveChart" height="120"></canvas>
    </div>
</div>

<script>
    const username   = <?= json_encode($user['username']) ?>;
    const PLAN_SPEED = <?= (int) $planSpeed ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/live_chart.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>

