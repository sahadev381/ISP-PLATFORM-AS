<?php
include '../config.php';
include '../includes/auth.php';

$esewa_merchant = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='esewa_merchant_id'")->fetch_assoc()['setting_value'] ?? 'EPAYTEST';
$esewa_mode = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='esewa_mode'")->fetch_assoc()['setting_value'] ?? 'test';

$oid = $_GET['oid'] ?? '';
$amt = $_GET['amt'] ?? '';
$refId = $_GET['refId'] ?? '';

if (!$oid || !$amt || !$refId) die("Invalid verification request");

$verify_url = ($esewa_mode == 'test') ? "https://uat.esewa.com.np/epay/transrec" : "https://esewa.com.np/epay/transrec";

$data = [
    'amt' => $amt,
    'rid' => $refId,
    'pid' => $oid,
    'scd' => $esewa_merchant
];

$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$username = $_SESSION['username'];

if (strpos($response, "Success") !== false) {
    // Payment verified!
    $conn->begin_transaction();
    try {
        // 1. Update wallet
        $conn->query("UPDATE customers SET wallet = wallet + $amt WHERE username = '$username'");
        
        // 2. Log transaction
        $stmt = $conn->prepare("INSERT INTO wallet_transactions (username, amount, gateway, status, txn_id) VALUES (?, ?, 'eSewa', 'completed', ?)");
        $stmt->bind_param("sds", $username, $amt, $refId);
        $stmt->execute();
        
        $conn->commit();
        $success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $success = false;
    }
} else {
    $success = false;
}

$page_title = "Payment Verification";
include '../includes/header.php';
?>

<div class="main-content-inner" style="display:flex; justify-content:center; align-items:center; height:80vh;">
    <div style="text-align:center; background:#fff; padding:40px; border-radius:15px; box-shadow:0 10px 25px rgba(0,0,0,0.05); max-width:400px; width:100%;">
        <?php if($success): ?>
            <div style="color:#10b981; font-size:60px; margin-bottom:20px;"><i class="fa fa-check-circle"></i></div>
            <h2 style="color:#1e293b;">Payment Successful!</h2>
            <p style="color:#64748b; margin-bottom:30px;">NPR <?= number_format($amt, 2) ?> has been added to your wallet.</p>
            <a href="../user_view.php?user=<?= $username ?>" class="btn btn-primary" style="text-decoration:none; display:inline-block; padding:12px 30px; border-radius:8px;">Back to Profile</a>
        <?php else: ?>
            <div style="color:#ef4444; font-size:60px; margin-bottom:20px;"><i class="fa fa-times-circle"></i></div>
            <h2 style="color:#1e293b;">Verification Failed</h2>
            <p style="color:#64748b; margin-bottom:30px;">We couldn't verify your payment. Please contact support.</p>
            <a href="khalti_pay.php" class="btn btn-danger" style="text-decoration:none; display:inline-block; padding:12px 30px; border-radius:8px;">Try Again</a>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
