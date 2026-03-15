<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base_path = '../';

include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Payment - Khalti";

include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/header.php';

$username = $_SESSION['username'] ?? '';
$public_key = KHALTI_PUBLIC_KEY;
?>

<div class="main">
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-wallet"></i> Recharge Wallet via Khalti</h3>
        </div>
        
        <div style="max-width: 500px; margin: 0 auto;">
            <div class="form-group">
                <label class="form-label">Your Username</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
            </div>
            
            <div class="form-group">
                <label class="form-label">Amount (NPR)</label>
                <input type="number" id="amount" class="form-control" placeholder="Enter amount in NPR" min="10" required>
            </div>
            
            <button type="button" id="pay-button" class="btn btn-primary" style="width: 100%;">
                <i class="fa fa-credit-card"></i> Pay with Khalti
            </button>
            
            <div style="text-align: center; margin-top: 20px; color: var(--text-muted); font-size: 13px;">
                <i class="fa fa-info-circle"></i> Minimum amount: NPR 10
            </div>
        </div>
    </div>
    
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-history"></i> Recent Transactions</h3>
        </div>
        
        <?php
        $transactions = $conn->query("
            SELECT * FROM wallet_transactions 
            WHERE username = '$username' 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        ?>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount (NPR)</th>
                    <th>Gateway</th>
                    <th>Status</th>
                    <th>Transaction ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if($transactions && $transactions->num_rows > 0): ?>
                    <?php while($t = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i', strtotime($t['created_at'])) ?></td>
                            <td><?= number_format($t['amount'], 2) ?></td>
                            <td><?= ucfirst($t['gateway']) ?></td>
                            <td>
                                <?php if($t['status'] == 'success'): ?>
                                    <span class="badge active">Success</span>
                                <?php elseif($t['status'] == 'pending'): ?>
                                    <span class="badge warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge expired">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['txn_id'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted);">
                            No transactions yet
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://khalti.com/static/khalti-checkout.js"></script>
<script>
var config = {
    "publicKey": "<?= $public_key ?>",
    "productIdentity": "wallet-<?= $username ?>",
    "productName": "ISP Wallet Recharge",
    "productUrl": window.location.href,
    "eventHandler": {
        onSuccess: function(payload) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'khalti_verify.php';
            
            var fields = {
                'token': payload.token,
                'amount': document.getElementById('amount').value,
                'username': '<?= $username ?>'
            };
            
            for(var key in fields) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        },
        onError: function(error) {
            alert('Payment Failed! Please try again.');
            console.log(error);
        },
        onClose: function() {
            console.log('Khalti widget closed');
        }
    }
};

var checkout = new KhaltiCheckout(config);
var payButton = document.getElementById('pay-button');
payButton.addEventListener('click', function() {
    var amount = document.getElementById('amount').value;
    
    if(!amount || amount < 10) {
        alert('Please enter valid amount (minimum NPR 10)');
        return;
    }
    
    checkout.show({amount: amount * 100});
});
</script>

<?php include $base_path . 'includes/footer.php'; ?>
