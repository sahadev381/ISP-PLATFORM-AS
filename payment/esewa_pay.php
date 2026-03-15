<?php
include '../config.php';
include '../includes/auth.php';

// Fetch Settings
$esewa_merchant = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='esewa_merchant_id'")->fetch_assoc()['setting_value'] ?? 'EPAYTEST';
$esewa_mode = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='esewa_mode'")->fetch_assoc()['setting_value'] ?? 'test';

$username = $_SESSION['username'];
$amount = $_POST['amount'] ?? 0;
if ($amount <= 0) die("Invalid amount");

$txn_id = "ISP-" . $username . "-" . time();
$esewa_url = ($esewa_mode == 'test') ? "https://uat.esewa.com.np/epay/main" : "https://esewa.com.np/epay/main";

// Current URL base for callbacks
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$success_url = $base_url . "/esewa_verify.php";
$failure_url = $base_url . "/khalti_pay.php?msg=failed";

$page_title = "Processing eSewa Payment";
include '../includes/header.php';
?>

<div class="main-content-inner" style="display:flex; justify-content:center; align-items:center; height:80vh;">
    <div style="text-align:center; background:#fff; padding:40px; border-radius:15px; box-shadow:0 10px 25px rgba(0,0,0,0.05); max-width:400px; width:100%;">
        <img src="https://blog.esewa.com.np/wp-content/uploads/2021/12/esewa_logo.png" style="width:150px; margin-bottom:20px;">
        <h3>Secure Checkout</h3>
        <p style="color:#64748b;">Redirecting to eSewa gateway...</p>
        <div style="font-size:24px; font-weight:700; margin:20px 0; color:#1e293b;">NPR <?= number_format($amount, 2) ?></div>
        
        <form id="esewaForm" method="POST" action="<?= $esewa_url ?>">
            <input type="hidden" name="amt" value="<?= $amount ?>">
            <input type="hidden" name="pdc" value="0">
            <input type="hidden" name="psc" value="0">
            <input type="hidden" name="txAmt" value="0">
            <input type="hidden" name="tAmt" value="<?= $amount ?>">
            <input type="hidden" name="pid" value="<?= $txn_id ?>">
            <input type="hidden" name="scd" value="<?= $esewa_merchant ?>">
            <input type="hidden" name="su" value="<?= $success_url ?>">
            <input type="hidden" name="fu" value="<?= $failure_url ?>">
            
            <div style="padding:20px; border-top:1px solid #eee;">
                <p style="font-size:12px; color:#94a3b8;">Please do not close this window.</p>
            </div>
        </form>
    </div>
</div>

<script>
    setTimeout(() => {
        document.getElementById('esewaForm').submit();
    }, 2000);
</script>

<?php include '../includes/footer.php'; ?>
