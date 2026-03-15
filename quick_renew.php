<?php
include 'config.php';
include 'includes/auth.php';

/* SHOW ERRORS (important) */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$msg = "";
$error = "";

/* RENEW USER */
if (isset($_POST['renew'])) {

    if (!isset($_POST['username'], $_POST['months'])) {
        $error = "Invalid request!";
    } else {

        $username = $_POST['username'];
        $months   = intval($_POST['months']);

        if ($months <= 0) {
            $error = "Invalid months!";
        } else {

            /* Get customer + plan */
            $q = $conn->query("
                SELECT 
                    c.username,
                    c.expiry,
                    p.price,
                    p.validity
                FROM customers c
                JOIN plans p ON c.plan_id = p.id
                WHERE c.username = '$username'
            ");

            if ($q === false || $q->num_rows == 0) {
                $error = "User not found!";
            } else {

                $row = $q->fetch_assoc();

                /* Calculate amount */
                $amount = $row['price'] * $months;

                /* Calculate expiry */
                $today = date('Y-m-d');
                $baseDate = ($row['expiry'] >= $today) ? $row['expiry'] : $today;
                $days = $row['validity'] * $months;

                $newExpiry = date('Y-m-d', strtotime("+$days days", strtotime($baseDate)));

                /* Update customer */
                $conn->query("
                    UPDATE customers 
                    SET expiry='$newExpiry', status='active'
                    WHERE username='$username'
                ");

                /* Insert invoice */
                $conn->query("
                    INSERT INTO invoices (username, amount, created_at, status)
                    VALUES ('$username', '$amount', NOW(), 'paid')
                ");

                $msg = "Renewal successful! New expiry: $newExpiry";
            }
        }
    }
}

/* GET USERS */
$users = $conn->query("SELECT username FROM customers ORDER BY username ASC");

/* PAGE TITLE */
$page_title = "User Renewal";

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">

    <div class="topbar">
        <h1>User Renewal</h1>
    </div>

    <?php if ($msg) { ?>
        <div style="background:#2ecc71;color:#fff;padding:12px;border-radius:10px;margin-bottom:15px;">
            <?= $msg ?>
        </div>
    <?php } ?>

    <?php if ($error) { ?>
        <div style="background:#e74c3c;color:#fff;padding:12px;border-radius:10px;margin-bottom:15px;">
            <?= $error ?>
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
                                <option value="<?= $u['username'] ?>">
                                    <?= $u['username'] ?>
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
                    <td><?= $i['id'] ?></td>
                    <td><?= $i['username'] ?></td>
                    <td><?= $i['amount'] ?></td>
                    <td><?= $i['created_at'] ?></td>
                </tr>
            <?php } ?>
        </table>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

