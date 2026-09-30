<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$page_title = "Invoices";
$active = "invoices";

// Deleting an invoice rolls back the customer's expiry, so it must not be
// reachable by a GET link that any page (or an <img> tag) can trigger.
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del'])){
    csrf_check();

    $id = intval($_POST['del']);

    /* Get invoice */
    $inv = db_one($conn, "SELECT * FROM invoices WHERE id = ?", [$id]);

    if(!$inv){
        header("Location: invoices.php?user=" . urlencode($_POST['user'] ?? ''));
        exit;
    }

    $username = $inv['username'];
    $months   = (int) $inv['months'];

    $conn->begin_transaction();
    try {
        /* Get plan validity */
        $p = db_one($conn, "
            SELECT p.validity
            FROM customers c
            JOIN plans p ON c.plan_id = p.id
            WHERE c.username = ?
        ", [$username]);

        // A customer with no plan used to produce validity * months on null.
        $days = (int) (($p['validity'] ?? 0) * $months);

        /* Rollback expiry */
        if ($days > 0) {
            db_exec($conn, "
                UPDATE customers
                SET expiry = DATE_SUB(expiry, INTERVAL ? DAY)
                WHERE username = ?
            ", [$days, $username]);
        }

        /* Delete invoice */
        db_exec($conn, "DELETE FROM invoices WHERE id = ?", [$id]);

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('invoice delete failed: ' . $e->getMessage());
        die("Could not delete the invoice. Please try again.");
    }

    header("Location: invoices.php?user=" . urlencode($username));
    exit;
}



$user = $_GET['user'] ?? '';
if(!$user){
    die("No user specified. <a href='users.php'>Back</a>");
}

$invoice_rows = db_all($conn, "SELECT * FROM invoices WHERE username = ? ORDER BY created_at DESC", [$user]);

include 'includes/header.php';
include 'includes/sidebar.php';
$page_title = "Invoices";
?>

<div class="main">

    <div class="table-box">
	<div>
        <h1>Invoices: <?= htmlspecialchars($user) ?></h1>
        <div><a href="users.php" class="btn"><i class="fa fa-arrow-left"></i> Back to Users</a></div>
    </div>

        <table>
            <tr>
                <th>ID</th>
                <th>Amount</th>
                <th>Months</th>
		<th>Expire Date</th>
		<th>Date</th>
		<th>Admin</th>
		<th>Action</th>
            </tr>
            <?php foreach($invoice_rows as $i){ ?>
            <tr>
                <td><?= $i['id'] ?></td>
		<td><?= $i['amount'] ?></td>
		<td><?= $i['months'] ?></td>
		<td><?= $i['expiry_date'] ?></td>
		<td><?= $i['created_at'] ?></td>
		<td><?= $i['admin'] ?></td>
		<td>
    		<form method="post" style="display:inline"
       		onsubmit="return confirm('Delete this invoice?')">
       		<?= csrf_field() ?>
       		<input type="hidden" name="user" value="<?= e($user) ?>">
       		<input type="hidden" name="del" value="<?= (int) $i['id'] ?>">
       		<button type="submit" style="color:red;background:none;border:0;padding:0;cursor:pointer;">Delete</button>
    		</form>
		</td>

            </tr>
            <?php } ?>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

