<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/cors.php';

// This endpoint used to answer every origin with a wildcard. Gateway
// callbacks are server-to-server and our own pages are same-origin, so
// neither needs CORS; anything else must be listed in
// CORS_ALLOWED_ORIGINS. Also handles the preflight.
cors_apply(['POST', 'GET']);
require_once __DIR__ . '/../../includes/payment_gateway.php';

require_once __DIR__ . '/../../includes/api_auth.php';

$paymentGateway = new PaymentGateway();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'initiate':
        api_require_payment_caller();
        initiatePayment($input);
        break;
    case 'verify':
        api_require_payment_caller();
        verifyPayment($input);
        break;
    case 'webhook':
        handleWebhook($input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function initiatePayment($data) {
    global $paymentGateway, $conn;
    
    $invoice_id = intval($data['invoice_id'] ?? 0);
    $amount = floatval($data['amount'] ?? 0);
    $customer_id = intval($data['customer_id'] ?? 0);
    $customer_name = $data['customer_name'] ?? 'Customer';
    $customer_email = $data['customer_email'] ?? '';
    $customer_phone = $data['customer_phone'] ?? '';
    
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

    // A customer may only pay their own invoice; an admin or API key
    // caller may act for anyone.
    $caller_customer_id = api_customer_id();
    if ($caller_customer_id !== null && !api_is_logged_in() && !api_has_valid_key()
        && (int) $invoice['customer_id'] !== $caller_customer_id) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        return;
    }
    if ($invoice['status'] === 'paid') {
        echo json_encode(['success' => false, 'error' => 'Invoice is already paid']);
        return;
    }
    $amount = (float) $invoice['total_amount'];
    $customer_id = (int) $invoice['customer_id'];

    $gateway = db_one($conn, "SELECT * FROM payment_gateways WHERE type = 'khalti' AND status = 'active' LIMIT 1");
    
    if (!$gateway) {
        echo json_encode(['success' => false, 'error' => 'Khalti gateway not configured']);
        return;
    }
    
    $transaction_id = 'KHL' . time() . rand(1000, 9999);
    $amount_paisa = intval($amount * 100);
    
    $fields = [
        'public_key' => $gateway['public_key'],
        'amount' => $amount_paisa,
        'product_identity' => $invoice_id,
        'product_name' => 'ISP Payment - Invoice #' . $invoice_id,
        'product_url' => '',
        'additional_info' => [
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone
        ]
    ];
    
    db_exec($conn, "INSERT INTO payment_transactions (transaction_id, gateway_id, customer_id, invoice_id, amount, status, created_at)
                  VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
        [$transaction_id, (int) $gateway['id'], $customer_id, $invoice_id, $amount]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transaction_id' => $transaction_id,
            'public_key' => $gateway['public_key'],
            'amount' => $amount_paisa,
            'product_identity' => $invoice_id,
            'product_name' => 'ISP Payment - Invoice #' . $invoice_id
        ]
    ]);
}

function verifyPayment($data) {
    global $paymentGateway, $conn;
    
    $token = $data['token'] ?? '';
    $transaction_id = $data['transaction_id'] ?? '';
    
    if (!$token || !$transaction_id) {
        echo json_encode(['success' => false, 'error' => 'Missing token or transaction ID']);
        return;
    }
    
    $transaction = db_one($conn, "SELECT * FROM payment_transactions WHERE transaction_id = ?", [$transaction_id]);

    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        return;
    }

    // Never re-credit an already completed transaction.
    if ($transaction['status'] === 'completed') {
        echo json_encode(['success' => true, 'message' => 'Payment already verified']);
        return;
    }

    $gateway = db_one($conn, "SELECT * FROM payment_gateways WHERE id = ?", [(int) $transaction['gateway_id']]);
    
    $url = 'https://khalti.com/api/v2/payment/verify/';
    $data = [
        'token' => $token,
        'amount' => $transaction['amount'] * 100
    ];
    
    $headers = [
        'Authorization: Key ' . $gateway['api_secret'],
        'Content-Type: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    // Khalti echoes back the amount it actually captured (in paisa). Trusting
    // only $result['success'] lets a caller settle a large invoice with a tiny
    // payment, so the captured amount must match what we asked for.
    $expected_paisa = (int) round($transaction['amount'] * 100);
    $captured_paisa = (int) ($result['amount'] ?? 0);
    $verified = isset($result['success']) && $result['success'] === true
        && $captured_paisa >= $expected_paisa;

    if ($verified) {
        db_exec($conn, "UPDATE payment_transactions SET
                      status = 'completed',
                      gateway_response = ?,
                      verified_at = NOW(),
                      updated_at = NOW()
                      WHERE id = ? AND status <> 'completed'",
            [$response, (int) $transaction['id']]);

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
}

function handleWebhook($data) {
    global $conn;
    
    $event = $data['event'] ?? '';
    $transaction_id = $data['transaction_id'] ?? '';
    
    if ($event === 'payment.success') {
        $token = $data['token'] ?? '';

        $transaction = db_one($conn, "SELECT * FROM payment_transactions WHERE transaction_id = ?", [$transaction_id]);

        // The webhook body is attacker-controllable: this endpoint is public and
        // the payload carries no signature. Previously any POST of
        // {"event":"payment.success","transaction_id":"..."} marked the invoice
        // paid. Re-verify the token against Khalti before trusting the event.
        if ($transaction && $transaction['status'] === 'pending' && $token !== '') {
            $gateway = db_one($conn, "SELECT * FROM payment_gateways WHERE id = ?", [(int) $transaction['gateway_id']]);
            $expected_paisa = (int) round($transaction['amount'] * 100);

            $ch = curl_init('https://khalti.com/api/v2/payment/verify/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'token' => $token,
                'amount' => $expected_paisa,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Key ' . ($gateway['api_secret'] ?? ''),
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode((string) $response, true);
            $verified = is_array($result)
                && ($result['success'] ?? null) === true
                && (int) ($result['amount'] ?? 0) >= $expected_paisa;

            if ($verified) {
                db_exec($conn, "UPDATE payment_transactions SET
                              status = 'completed',
                              gateway_response = ?,
                              verified_at = NOW()
                              WHERE id = ? AND status = 'pending'",
                    [$response, (int) $transaction['id']]);

                if ($transaction['invoice_id']) {
                    db_exec($conn, "UPDATE billing_invoices SET status = 'paid', paid_at = NOW() WHERE id = ?",
                        [(int) $transaction['invoice_id']]);
                }
            } else {
                error_log('Khalti webhook rejected for transaction ' . $transaction['transaction_id'] . ': verification failed');
            }
        }
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
}
