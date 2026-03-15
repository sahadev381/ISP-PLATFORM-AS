<?php
header('Content-Type: application/json');
include_once '../../config.php';

$transaction_id = $_GET['transaction_id'] ?? '';

if (!$transaction_id) {
    echo json_encode(['error' => 'Missing transaction ID']);
    exit;
}

$transaction = $conn->query("
    SELECT t.*, c.username, c.first_name, c.last_name, c.email, c.phone, c.address,
           g.name as gateway_name, g.type as gateway_type,
           i.invoice_number
    FROM payment_transactions t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN payment_gateways g ON t.gateway_id = g.id
    LEFT JOIN billing_invoices i ON t.invoice_id = i.id
    WHERE t.transaction_id = '$transaction_id'
")->fetch_assoc();

if (!$transaction) {
    echo json_encode(['error' => 'Transaction not found']);
    exit;
}
?>
<div class="table-responsive">
    <table class="table table-bordered">
        <tr><th>Transaction ID</th><td><code><?= $transaction['transaction_id'] ?></code></td></tr>
        <tr><th>Customer</th><td><?= $transaction['full_name'] ?></td></tr>
        <tr><th>Email</th><td><?= $transaction['email'] ?: '-' ?></td></tr>
        <tr><th>Phone</th><td><?= $transaction['phone'] ?: '-' ?></td></tr>
        <tr><th>Invoice</th><td><?= $transaction['invoice_number'] ?: '-' ?></td></tr>
        <tr><th>Gateway</th><td><?= $transaction['gateway_name'] ?: 'Direct' ?></td></tr>
        <tr><th>Amount</th><td>Rs.<?= number_format($transaction['amount'], 2) ?></td></tr>
        <tr><th>Status</th><td>
            <span class="badge bg-<?= 
                $transaction['status'] == 'completed' ? 'success' : 
                ($transaction['status'] == 'pending' ? 'warning' : 'danger')
            ?>">
                <?= ucfirst($transaction['status']) ?>
            </span>
        </td></tr>
        <tr><th>Created</th><td><?= date('M d, Y H:i:s', strtotime($transaction['created_at'])) ?></td></tr>
        <?php if ($transaction['verified_at']): ?>
        <tr><th>Verified</th><td><?= date('M d, Y H:i:s', strtotime($transaction['verified_at'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($transaction['gateway_response']): ?>
        <tr><th>Gateway Response</th><td><pre><?= json_encode(json_decode($transaction['gateway_response']), JSON_PRETTY_PRINT) ?: $transaction['gateway_response'] ?></pre></td></tr>
        <?php endif; ?>
    </table>
</div>
