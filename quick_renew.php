<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

/* SHOW ERRORS (important) */

$msg = "";
$error = "";

/* RENEW USER */
if (isset($_POST['renew'])) {

    csrf_check();

    if (!isset($_POST['username'], $_POST['months'])) {
        $error = "Invalid request!";
    } else {

        $username = $_POST['username'];
// Refuse customers belonging to another branch.
require_customer_access($conn, $username);

        $months   = intval($_POST['months']);

        if ($months <= 0) {
            $error = "Invalid months!";
        } else {

            /* Get customer + plan */
            $row = db_one($conn, "
                SELECT
                    c.username,
                    c.expiry,
                    p.price,
                    p.validity
                FROM customers c
                JOIN plans p ON c.plan_id = p.id
                WHERE c.username = ?
            ", [$username]);

            if (!$row) {
                $error = "User not found!";
            } else {

                /* Calculate amount */
                $amount = $row['price'] * $months;

                /* Calculate expiry */
                $today = date('Y-m-d');
                $baseDate = ($row['expiry'] >= $today) ? $row['expiry'] : $today;
                $days = (int) $row['validity'] * $months;

                $newExpiry = date('Y-m-d', strtotime("+$days days", strtotime($baseDate)));

                // Expiry and the invoice must move together, otherwise a
                // failure between the two leaves the customer renewed with no
                // invoice (or billed with no extension).
                $conn->begin_transaction();
                try {
                    /* Update customer */
                    db_exec($conn, "
                        UPDATE customers
                        SET expiry = ?, status = 'active'
                        WHERE username = ?
                    ", [$newExpiry, $username]);

                    /* Insert invoice */
                    db_exec($conn, "
                        INSERT INTO invoices (username, amount, created_at, status)
                        VALUES (?, ?, NOW(), 'paid')
                    ", [$username, $amount]);

                    $conn->commit();
                    $msg = "Renewal successful! New expiry: $newExpiry";
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('quick_renew failed: ' . $e->getMessage());
                    $error = "Renewal failed. Please try again.";
                }
            }
        }
    }
}

/* GET USERS */
$users = $conn->query("SELECT username FROM customers ORDER BY username ASC");

/* PAGE TITLE */
$page_title = "User Renewal";

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main">

    <div class="topbar">
        <h1>User Renewal</h1>
    </div>

    <?php if ($msg) { ?>
        <div style="background:#2ecc71;color:#fff;padding:12px;border-radius:10px;margin-bottom:15px;">
            <?= e($msg) ?>
        </div>
    <?php } ?>

    <?php if ($error) { ?>
        <div style="background:#e74c3c;color:#fff;padding:12px;border-radius:10px;margin-bottom:15px;">
            <?= e($error) ?>
        </div>
    <?php } ?>

    <div class="table-box">
        <h3>Renew User</h3>

        <form method="post">
            <table>
                <tr>
                    <td>User</td>
                    <td>
                        <select name="username" required>
                            <option value="">Select User</option>
                            <?php while ($u = $users->fetch_assoc()) { ?>
                                <option value="<?= e($u['username']) ?>">
                                    <?= e($u['username']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <td>Months</td>
                    <td>
                        <select name="months" required>
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
                            Renew Now
                        </button>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <br>

    <div class="table-box">
        <h3>Renewal History</h3>

        <table>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Amount</th>
                <th>Date</th>
            </tr>

            <?php
            $h = $conn->query("SELECT * FROM invoices ORDER BY id DESC");
            while ($i = $h->fetch_assoc()) {
            ?>
                <tr>
                    <td><?= e($i['id']) ?></td>
                    <td><?= e($i['username']) ?></td>
                    <td><?= e($i['amount']) ?></td>
                    <td><?= e($i['created_at']) ?></td>
                </tr>
            <?php } ?>
        </table>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

