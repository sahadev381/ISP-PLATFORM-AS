<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$page_title = "Renew User";
$active = "recharge";

$msg = $error = '';

/* ===============================
   DELETE INVOICE + ROLLBACK
   (POST + CSRF: this used to be a GET link, so a single crafted
    <img> tag was enough to wipe an invoice and roll back an expiry)
================================ */
if (isset($_POST['del_invoice'])) {
    csrf_check();

    $inv_id = (int) $_POST['del_invoice'];
    $inv    = db_one($conn, "SELECT * FROM invoices WHERE id = ?", [$inv_id]);

    $redirectUser = trim($_POST['user'] ?? '');

    if ($inv) {
        $invUser = $inv['username'];
        $months  = (int) $inv['months'];

        // Get plan validity
        $p = db_one($conn, "
            SELECT p.validity
            FROM customers c
            JOIN plans p ON c.plan_id = p.id
            WHERE c.username = ?
        ", [$invUser]);

        if ($p) {
            $days = (int) $p['validity'] * $months;

            // Rollback expiry
            db_exec($conn, "
                UPDATE customers
                SET expiry = DATE_SUB(expiry, INTERVAL ? DAY)
                WHERE username = ?
            ", [$days, $invUser]);
        }

        // Delete invoice & recharge record
        db_exec($conn, "DELETE FROM invoices WHERE id = ?", [$inv_id]);
        db_exec($conn, "DELETE FROM recharge WHERE id = ?", [$inv_id]);

        logActivity('invoice_delete', "Deleted invoice #$inv_id for $invUser");
        $redirectUser = $redirectUser ?: $invUser;
    }

    header("Location: recharge.php?user=" . urlencode($redirectUser));
    exit;
}

/* ===============================
   USER + PLAN
================================ */
$username = trim($_GET['user'] ?? '');
if ($username === '') {
    die("No user specified. <a href='users.php'>Back</a>");
}

$user = db_one($conn, "SELECT * FROM customers WHERE username = ?", [$username]);
if (!$user) die("User not found.");

$plan = db_one($conn, "SELECT * FROM plans WHERE id = ?", [(int) $user['plan_id']]);
if (!$plan) die("User has no plan assigned.");

/* ===============================
   RENEW LOGIC
=============================== */
if (isset($_POST['renew'])) {
    csrf_check();

    $months = (int) ($_POST['months'] ?? 0);

    if ($months > 0 && $months <= 36) {

        $price = $plan['price'] * $months;

        $current_expiry = strtotime($user['expiry']);
        $today          = strtotime(date('Y-m-d'));

        $base       = max($current_expiry, $today);
        $new_expiry = date('Y-m-d', $base + ($plan['validity'] * 86400 * $months));

        $radius_expiry = date('d M Y 08:00:00', strtotime($new_expiry));

        // Wrap the whole renewal so a partial failure cannot leave the
        // customer row and the RADIUS tables out of sync.
        $conn->begin_transaction();
        try {
            db_exec($conn, "UPDATE customers SET expiry = ?, status = 'active', blocked = 0 WHERE username = ?",
                [$new_expiry, $username]);

            $check = db_one($conn, "SELECT id FROM radcheck WHERE username = ? AND attribute = 'Expiration'", [$username]);

            if ($check) {
                db_exec($conn, "UPDATE radcheck SET value = ?, op = ':=' WHERE username = ? AND attribute = 'Expiration'",
                    [$radius_expiry, $username]);
            } else {
                db_exec($conn, "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)",
                    [$username, $radius_expiry]);
            }

            db_exec($conn, "DELETE FROM radreply WHERE username = ?", [$username]);
            db_exec($conn, "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)",
                [$username, $plan['speed']]);

            db_exec($conn, "INSERT INTO invoices (username, amount, months, expiry_date, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$username, $price, $months, $new_expiry]);

            db_exec($conn, "INSERT INTO recharge (username, amount, months, created_at) VALUES (?, ?, ?, NOW())",
                [$username, $price, $months]);

            $conn->commit();

            logActivity('recharge', "Renewed $username for $months month(s), amount $price");
            $msg = "User renewed for $months month(s). Invoice generated: $price";

            $user = db_one($conn, "SELECT * FROM customers WHERE username = ?", [$username]);
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Renewal failed for ' . $username . ': ' . $e->getMessage());
            $error = "Renewal failed. Nothing was charged — please try again.";
        }

    } else {
        $error = "Select a valid number of months!";
    }
}


/* ===============================
   HISTORY
================================ */
$history = db_all($conn, "
    SELECT * FROM recharge
    WHERE username = ?
    ORDER BY created_at DESC
", [$username]);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="main">

    <?php if($msg){ ?>
        <div style="background:#2ecc71;color:#fff;padding:10px;border-radius:10px;margin-bottom:15px;">
            <?= e($msg) ?>
        </div>
    <?php } ?>

    <?php if($error){ ?>
        <div style="background:#e74c3c;color:#fff;padding:10px;border-radius:10px;margin-bottom:15px;">
            <?= e($error) ?>
        </div>
    <?php } ?>

    <div style="margin-bottom:20px;padding:15px;background:rgba(255,255,255,0.1);border-radius:12px;">
        <strong>User:</strong> <?= e($user['username']) ?> |
        <strong>Wallet:</strong> <?= e($user['wallet']) ?> |
        <strong>Plan Expiry:</strong> <?= e($user['expiry']) ?> |
        <strong>Status:</strong> <?= e(ucfirst($user['status'])) ?>
    </div>


     <!-- Hidden Form -->
    <div id="addAdminBox" style="display:none;margin-top:15px;">
    <!-- Renewal Form -->
    <div class="table-box" style="margin-bottom:30px;">
        <h3>
            Renew Plan: <?= e($plan['name']) ?>
            (Price: <?= e($plan['price']) ?> / month)
        </h3>

        <form method="post">
            <?= csrf_field() ?>
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
        <button class="btn" data-action="toggleAddAdmin" type="button">
            <i class="fa fa-plus"></i> Add
        </button>
       </div>
        <table>
            <tr>
                <th>ID</th>
                <th>Amount</th>
                <th>Months</th>
		<th>Date</th>
                <th></th>
            </tr>
            <?php foreach($history as $h){ ?>
            <tr>
                <td><?= e($h['id']) ?></td>
                <td><?= e($h['amount']) ?></td>
                <td><?= e($h['months']) ?></td>
                <td><?= e($h['created_at']) ?></td>
                <td>
                    <form method="post" style="display:inline"
                          <?= action_attr('confirmFirst', ['Delete this invoice and roll back the expiry?'], 'submit') ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="del_invoice" value="<?= (int) $h['id'] ?>">
                        <input type="hidden" name="user" value="<?= e($username) ?>">
                        <button class="btn" type="submit"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
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


<?php include __DIR__ . '/includes/footer.php'; ?>

