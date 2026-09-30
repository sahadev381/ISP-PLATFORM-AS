<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$esewa_merchant = db_value($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'esewa_merchant_id'", [], 'EPAYTEST');
$esewa_mode = db_value($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'esewa_mode'", [], 'test');

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

$username = $_SESSION['username'] ?? '';
$amt = (float) $amt;

if ($amt <= 0) die("Invalid amount");

if (strpos((string) $response, "Success") !== false) {
    // Payment verified!
    $conn->begin_transaction();
    try {
        // eSewa can redirect here more than once (browser refresh, back button),
        // and nothing stopped the same refId from topping the wallet up again.
        $already = db_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE txn_id = ? AND gateway = 'eSewa'", [$refId], 0);
        if ($already > 0) {
            $conn->rollback();
            $success = true;
            $duplicate = true;
        } else {
            // 1. Update wallet
            db_exec($conn, "UPDATE customers SET wallet = wallet + ? WHERE username = ?", [$amt, $username]);

            // 2. Log transaction
            db_exec($conn, "INSERT INTO wallet_transactions (username, amount, gateway, status, txn_id) VALUES (?, ?, 'eSewa', 'completed', ?)",
                [$username, $amt, $refId]);

            $conn->commit();
            $success = true;
        }
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('esewa_verify failed: ' . $e->getMessage());
        $success = false;
    }
} else {
    $success = false;
}

$page_title = "Payment Verification";
include __DIR__ . '/../includes/header.php';
?>

<div class="main-content-inner" style="display:flex; justify-content:center; align-items:center; height:80vh;">
    <div style="text-align:center; background:#fff; padding:40px; border-radius:15px; box-shadow:0 10px 25px rgba(0,0,0,0.05); max-width:400px; width:100%;">
        <?php if($success): ?>
            <div style="color:#10b981; font-size:60px; margin-bottom:20px;"><i class="fa fa-check-circle"></i></div>
            <h2 style="color:#1e293b;">Payment Successful!</h2>
            <p style="color:#64748b; margin-bottom:30px;">NPR <?= number_format($amt, 2) ?> has been added to your wallet.</p>
            <a href="../user_view.php?user=<?= e($username) ?>" class="btn btn-primary" style="text-decoration:none; display:inline-block; padding:12px 30px; border-radius:8px;">Back to Profile</a>
        <?php else: ?>
            <div style="color:#ef4444; font-size:60px; margin-bottom:20px;"><i class="fa fa-times-circle"></i></div>
            <h2 style="color:#1e293b;">Verification Failed</h2>
            <p style="color:#64748b; margin-bottom:30px;">We couldn't verify your payment. Please contact support.</p>
            <a href="khalti_pay.php" class="btn btn-danger" style="text-decoration:none; display:inline-block; padding:12px 30px; border-radius:8px;">Try Again</a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
