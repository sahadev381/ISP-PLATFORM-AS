<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
}

$action = $input['action'] ?? ($_GET['action'] ?? '');

switch ($action) {
    case 'initiate':
        initiatePayment($input);
        break;
    case 'callback':
        handleCallback($input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function initiatePayment($data) {
    global $conn;
    
    $invoice_id = intval($data['invoice_id'] ?? 0);
    $amount = floatval($data['amount'] ?? 0);
    $customer_id = intval($data['customer_id'] ?? 0);
    
    if (!$invoice_id || !$amount) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        return;
    }

    // The amount must come from the invoice, not from the request body —
    // otherwise a caller can settle a Rs 5000 invoice by posting amount=1.
    $invoice = db_one($conn, "SELECT id, customer_id, total_amount, status FROM billing_invoices WHERE id = ?", [$invoice_id]);
    if (!$invoice) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        return;
    }
    if ($invoice['status'] === 'paid') {
        echo json_encode(['success' => false, 'error' => 'Invoice is already paid']);
        return;
    }
    $amount = (float) $invoice['total_amount'];
    $customer_id = (int) $invoice['customer_id'];

    $gateway = db_one($conn, "SELECT * FROM payment_gateways WHERE type = 'esewa' AND status = 'active' LIMIT 1");
    
    if (!$gateway) {
        echo json_encode(['success' => false, 'error' => 'eSewa gateway not configured']);
        return;
    }
    
    $transaction_id = 'ESW' . time() . rand(1000, 9999);
    $callback_url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/payment/esewa.php?action=callback';
    
    db_exec($conn, "INSERT INTO payment_transactions (transaction_id, gateway_id, customer_id, invoice_id, amount, status, created_at)
                  VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
        [$transaction_id, (int) $gateway['id'], $customer_id, $invoice_id, $amount]);
    
    $encrypted = base64_encode($invoice_id . '|' . $amount . '|' . $transaction_id);
    
    $esewa_url = 'https://esewa.com.np/epay/main';
    $post_data = [
        'amt' => $amount,
        'pdc' => 0,
        'psc' => 0,
        'txAmt' => 0,
        'tAmt' => $amount,
        'pid' => $transaction_id,
        'psc' => '',
        'pdc' => '',
        'scd' => $gateway['merchant_id'],
        'su' => $callback_url . '&status=success&transaction_id=' . $transaction_id,
        'fu' => $callback_url . '&status=fail&transaction_id=' . $transaction_id
    ];
    
    echo json_encode([
        'success' => true,
        'payment_url' => $esewa_url,
        'post_data' => $post_data,
        'transaction_id' => $transaction_id
    ]);
}

function handleCallback($data) {
    global $conn;
    
    $transaction_id = $_GET['transaction_id'] ?? '';
    $status = $_GET['status'] ?? 'fail';
    
    if (!$transaction_id) {
        echo json_encode(['success' => false, 'error' => 'Missing transaction ID']);
        return;
    }
    
    $transaction = db_one($conn, "SELECT * FROM payment_transactions WHERE transaction_id = ?", [$transaction_id]);

    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        return;
    }

    // A transaction may only be completed once — otherwise a replayed
    // callback can credit the same payment repeatedly.
    if ($transaction['status'] === 'completed') {
        echo json_encode(['success' => true, 'message' => 'Payment already verified']);
        return;
    }

    $gateway = db_one($conn, "SELECT * FROM payment_gateways WHERE id = ?", [(int) $transaction['gateway_id']]);

    if ($status === 'success') {
        $ref_id = $_GET['refId'] ?? '';
        
        $url = 'https://esewa.com.np/epay/verify';
        $post_data = [
            'amt' => $transaction['amount'],
            'rid' => $ref_id,
            'pid' => $transaction_id,
            'scd' => $gateway['merchant_id']
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (strpos((string) $response, 'Success') !== false) {
            db_exec($conn, "UPDATE payment_transactions SET
                          status = 'completed',
                          gateway_response = ?,
                          ref_id = ?,
                          verified_at = NOW()
                          WHERE id = ? AND status <> 'completed'",
                [$response, $ref_id, (int) $transaction['id']]);

            if ($transaction['invoice_id']) {
                db_exec($conn, "UPDATE billing_invoices SET status = 'paid', paid_at = NOW() WHERE id = ?",
                    [(int) $transaction['invoice_id']]);
            }

            echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
        } else {
            db_exec($conn, "UPDATE payment_transactions SET
                          status = 'failed',
                          gateway_response = ?
                          WHERE id = ?", [$response, (int) $transaction['id']]);

            echo json_encode(['success' => false, 'error' => 'Payment verification failed']);
        }
    } else {
        db_exec($conn, "UPDATE payment_transactions SET status = 'failed' WHERE id = ?", [(int) $transaction['id']]);
        
        echo json_encode(['success' => false, 'error' => 'Payment failed']);
    }
}

function verifyPayment($data) {
    global $conn;
    
    $transaction_id = $data['transaction_id'] ?? '';
    
    if (!$transaction_id) {
        echo json_encode(['success' => false, 'error' => 'Missing transaction ID']);
        return;
    }
    
    $transaction = db_one($conn, "SELECT * FROM payment_transactions WHERE transaction_id = ?", [$transaction_id]);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'status' => $transaction['status'],
        'amount' => $transaction['amount'],
        'created_at' => $transaction['created_at']
    ]);
}
