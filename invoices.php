<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Invoices";
$active = "invoices";

if(isset($_GET['del'])){
    $id = intval($_GET['del']);

    /* Get invoice */
    $inv = $conn->query("SELECT * FROM invoices WHERE id='$id'")->fetch_assoc();

    if($inv){
        $username = $inv['username'];
        $months   = $inv['months'];

        /* Get plan validity */
        $p = $conn->query("
            SELECT p.validity 
            FROM customers c 
            JOIN plans p ON c.plan_id = p.id
            WHERE c.username='$username'
        ")->fetch_assoc();

        $days = $p['validity'] * $months;

        /* Rollback expiry */
        $conn->query("
            UPDATE customers 
            SET expiry = DATE_SUB(expiry, INTERVAL $days DAY)
            WHERE username='$username'
        ");

        /* Delete invoice */
        $conn->query("DELETE FROM invoices WHERE id='$id'");
    }

    header("Location: invoices.php?user=".$username);
    exit;
}



$user = $_GET['user'] ?? '';
if(!$user){
    die("No user specified. <a href='users.php'>Back</a>");
}

$q = $conn->query("SELECT * FROM invoices WHERE username='$user' ORDER BY created_at DESC");

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
            <?php while($i = $q->fetch_assoc()){ ?>
            <tr>
                <td><?= $i['id'] ?></td>
		<td><?= $i['amount'] ?></td>
		<td><?= $i['months'] ?></td>
		<td><?= $i['expiry_date'] ?></td>
		<td><?= $i['created_at'] ?></td>
		<td><?= $i['admin'] ?></td>
		<td>
    		<a href="?user=<?=$user?>&del=<?=$i['id']?>"
       		onclick="return confirm('Delete this invoice?')"
       		style="color:red;">
       		Delete
    		</a>
		</td>

            </tr>
            <?php } ?>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

