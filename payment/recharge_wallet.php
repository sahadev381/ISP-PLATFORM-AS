<?php
$base_path = '../';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Recharge Wallet";
$username = $_SESSION['username'] ?? '';
$public_key = defined('KHALTI_PUBLIC_KEY') ? KHALTI_PUBLIC_KEY : '';

include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .gateway-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 30px; }
    .gateway-card { background: #fff; border: 2px solid #f1f5f9; border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
    .gateway-card:hover { border-color: #3b82f6; transform: translateY(-3px); box-shadow: 0 10px 15px rgba(0,0,0,0.05); }
    .gateway-card img { height: 40px; margin-bottom: 10px; object-fit: contain; }
    .gateway-card span { display: block; font-weight: 600; font-size: 14px; color: #1e293b; }
</style>

<div class="main-content-inner" style="padding: 25px;">
    <div class="table-box" style="background:#fff; border-radius:15px; padding:30px; box-shadow:0 4px 6px rgba(0,0,0,0.02); max-width: 800px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="margin:0; color:#1e293b;"><i class="fa fa-wallet" style="color:#3b82f6;"></i> Top-up Your Wallet</h2>
            <p style="color:#64748b;">Choose your preferred payment method to recharge.</p>
        </div>

        <div style="max-width: 400px; margin: 0 auto;">
            <label style="display:block; margin-bottom:8px; font-weight:600; color:#475569;">Amount to Recharge (NPR)</label>
            <input type="number" id="recharge_amount" class="form-control" style="width:100%; padding:15px; font-size:18px; font-weight:700; border-radius:10px; text-align:center;" placeholder="0.00" min="10">
        </div>

        <div class="gateway-grid">
            <!-- Khalti -->
            <div class="gateway-card" onclick="payWithKhalti()">
                <img src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/resources/img/khalti-logo.png">
                <span>Khalti</span>
            </div>
            
            <!-- eSewa -->
            <div class="gateway-card" onclick="payWithEsewa()">
                <img src="https://blog.esewa.com.np/wp-content/uploads/2021/12/esewa_logo.png">
                <span>eSewa</span>
            </div>

            <!-- connectIPS -->
            <div class="gateway-card" onclick="payWithConnectIPS()">
                <img src="https://www.connectips.com/wp-content/uploads/2020/07/connectips-logo.png">
                <span>connectIPS</span>
            </div>
        </div>

        <div style="margin-top: 40px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
            <h4 style="font-size:14px; color:#64748b; margin-bottom:15px;"><i class="fa fa-history"></i> Recent Transactions</h4>
            <table style="width:100%; font-size:13px;">
                <thead><tr style="text-align:left; color:#94a3b8;"><th>Date</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $txns = $conn->query("SELECT * FROM wallet_transactions WHERE username='$username' ORDER BY created_at DESC LIMIT 5");
                    while($t = $txns->fetch_assoc()):
                    ?>
                    <tr style="border-bottom:1px solid #f8fafc;">
                        <td style="padding:10px 0;"><?= date('M d, h:i A', strtotime($t['created_at'])) ?></td>
                        <td><?= $t['gateway'] ?></td>
                        <td style="font-weight:600;">NPR <?= number_format($t['amount'], 2) ?></td>
                        <td><span style="color:<?= $t['status']=='completed'?'#10b981':'#ef4444' ?>; font-weight:700;"><?= strtoupper($t['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden eSewa Form -->
<form id="hiddenEsewaForm" method="POST" action="esewa_pay.php">
    <input type="hidden" name="amount" id="esewa_amt">
</form>

<script src="https://khalti.com/static/khalti-checkout.js"></script>
<script>
function getAmount() {
    let amt = document.getElementById('recharge_amount').value;
    if(!amt || amt < 10) { alert("Please enter a valid amount (Min NPR 10)"); return false; }
    return amt;
}

function payWithEsewa() {
    let amt = getAmount();
    if(amt) {
        document.getElementById('esewa_amt').value = amt;
        document.getElementById('hiddenEsewaForm').submit();
    }
}

function payWithKhalti() {
    let amt = getAmount();
    if(amt) {
        let config = {
            "publicKey": "<?= $public_key ?>",
            "productIdentity": "wallet-<?= $username ?>",
            "productName": "ISP Wallet Recharge",
            "eventHandler": {
                onSuccess: function(payload) {
                    location.href = `khalti_verify.php?token=${payload.token}&amount=${amt}&username=<?= $username ?>`;
                },
                onError: (e) => alert("Khalti Payment Failed")
            }
        };
        let checkout = new KhaltiCheckout(config);
        checkout.show({amount: amt * 100});
    }
}

function payWithConnectIPS() {
    alert("connectIPS Integration: Please contact support to enable your merchant account.");
}
</script>

<?php include $base_path . 'includes/footer.php'; ?>
