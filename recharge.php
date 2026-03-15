<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Renew User";
$active = "recharge";

/* ===============================
   DELETE INVOICE + ROLLBACK
================================ */
if(isset($_GET['del_invoice'])){
    $inv_id = intval($_GET['del_invoice']);

    $inv = $conn->query("SELECT * FROM invoices WHERE id='$inv_id'")->fetch_assoc();
    if($inv){

        $username = $inv['username'];
        $months   = $inv['months'];

        // Get plan validity
        $p = $conn->query("
            SELECT p.validity 
            FROM customers c 
            JOIN plans p ON c.plan_id = p.id 
            WHERE c.username='$username'
        ")->fetch_assoc();

        if($p){
            $days = $p['validity'] * $months;

            // Rollback expiry
            $conn->query("
                UPDATE customers 
                SET expiry = DATE_SUB(expiry, INTERVAL $days DAY)
                WHERE username='$username'
            ");
        }

        // Delete invoice & recharge record
        $conn->query("DELETE FROM invoices WHERE id='$inv_id'");
        $conn->query("DELETE FROM recharge WHERE id='$inv_id'");
    }

    header("Location: recharge.php?user=".$username);
    exit;
}

/* ===============================
   USER + PLAN
================================ */
$username = $_GET['user'] ?? '';
if(!$username){
    die("No user specified. <a href='users.php'>Back</a>");
}

$user = $conn->query("SELECT * FROM customers WHERE username='$username'")->fetch_assoc();
if(!$user) die("User not found.");

$plan = $conn->query("SELECT * FROM plans WHERE id='{$user['plan_id']}'")->fetch_assoc();
if(!$plan) die("User has no plan assigned.");

/* ===============================
   RENEW LOGIC
=============================== */
if(isset($_POST['renew'])){
    $months = intval($_POST['months']);
    if($months > 0){

        $price = $plan['price'] * $months;

        $current_expiry = strtotime($user['expiry']);
        $today = strtotime(date('Y-m-d'));

        $base = max($current_expiry, $today);
        $new_expiry = date('Y-m-d', $base + ($plan['validity'] * 86400 * $months));
        
        $radius_expiry = date('d M Y 08:00:00', strtotime($new_expiry));

        $conn->query("UPDATE customers SET expiry='$new_expiry', status='active' WHERE username='$username'");

        $check = $conn->query("SELECT id FROM radcheck WHERE username='$username' AND attribute='Expiration'");

        if ($check->num_rows > 0) {
            $conn->query("UPDATE radcheck SET value='$radius_expiry', op=':=' WHERE username='$username' AND attribute='Expiration'");
        } else {
            $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Expiration', ':=', '$radius_expiry')");
        }

        $conn->query("DELETE FROM radreply WHERE username='$username'");
        $conn->query("INSERT INTO radreply (username,attribute,op,value) VALUES ('$username','Mikrotik-Rate-Limit',':=','".$plan['speed']."')");
        
        $conn->query("INSERT INTO invoices (username, amount, months, expiry_date, created_at) VALUES ('$username', $price, $months, '$new_expiry', NOW())");

        $conn->query("INSERT INTO recharge (username, amount, months, created_at) VALUES ('$username', $price, $months, NOW())");

        $msg = "User renewed for $months month(s). Invoice generated: $price";

        $conn->query("UPDATE customers SET blocked=0, status='active' WHERE username='$username'");

        $user = $conn->query("SELECT * FROM customers WHERE username='$username'")->fetch_assoc();

    } else {
        $error = "Select a valid number of months!";
    }
}


/* ===============================
   HISTORY
================================ */
$history = $conn->query("
    SELECT * FROM recharge 
    WHERE username='$username' 
    ORDER BY created_at DESC
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main">

    <?php if(isset($msg)){ ?>
        <div style="background:#2ecc71;color:#fff;padding:10px;border-radius:10px;margin-bottom:15px;">
            <?= $msg ?>
        </div>
    <?php } ?>

    <?php if(isset($error)){ ?>
        <div style="background:#e74c3c;color:#fff;padding:10px;border-radius:10px;margin-bottom:15px;">
            <?= $error ?>
        </div>
    <?php } ?>

    <div style="margin-bottom:20px;padding:15px;background:rgba(255,255,255,0.1);border-radius:12px;">
        <strong>User:</strong> <?= $user['username'] ?> |
        <strong>Wallet:</strong> <?= $user['wallet'] ?> |
        <strong>Plan Expiry:</strong> <?= $user['expiry'] ?> |
        <strong>Status:</strong> <?= ucfirst($user['status']) ?>
    </div>


     <!-- Hidden Form -->
    <div id="addAdminBox" style="display:none;margin-top:15px;">
    <!-- Renewal Form -->
    <div class="table-box" style="margin-bottom:30px;">
        <h3>
            Renew Plan: <?= $plan['name'] ?>
            (Price: <?= $plan['price'] ?> / month)
        </h3>

        <form method="post">
            <table>
                <tr>
                    <td>Months</td>
                    <td>
                        <select name="months" required>
    			<option value="">Select Months</option>
    			<option value="1">1 Month</option>
    			<option value="2">2 Months</option>
    			<option value="3">3 Months</option>
    			<option value="6">6 Months</option>
    			<option value="12">12 Months</option>
			</select>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <button class="btn" name="renew">
                            <i class="fa fa-sync"></i> Renew User
                        </button>
                    </td>
                </tr>
            </table>
	</form>
	</div>
    </div>

    <!-- Recharge History -->
    <div class="table-box">
	<div>
	<h3>Recharge / Renewal History</h3>
            <a href="users.php" class="btn">
                <i class="fa fa-arrow-left"></i> Back to Users
	    </a>
		  <!-- Toggle Button -->
        <button class="btn" onclick="toggleAddAdmin()" type="button">
            <i class="fa fa-plus"></i> Add
        </button>
       </div>
        <table>
            <tr>
                <th>ID</th>
                <th>Amount</th>
                <th>Months</th>
		<th>Date</th>
            </tr>
            <?php while($h = $history->fetch_assoc()){ ?>
            <tr>
                <td><?= $h['id'] ?></td>
                <td><?= $h['amount'] ?></td>
                <td><?= $h['months'] ?></td>
                <td><?= $h['created_at'] ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>
<script>
function toggleAddAdmin() {
    var box = document.getElementById('addAdminBox');
    box.style.display = (box.style.display === 'none') ? 'block' : 'none';
}
</script>


<?php include 'includes/footer.php'; ?>

