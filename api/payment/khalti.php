<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config.php';
include_once '../../includes/payment_gateway.php';

$paymentGateway = new PaymentGateway();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'initiate':
        initiatePayment($input);
        break;
    case 'verify':
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
    
    $gateway = $conn->query("SELECT * FROM payment_gateways WHERE type = 'khalti' AND status = 'active' LIMIT 1")->fetch_assoc();
    
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
    
    $conn->query("INSERT INTO payment_transactions (transaction_id, gateway_id, customer_id, invoice_id, amount, status, created_at) 
                  VALUES ('$transaction_id', {$gateway['id']}, $customer_id, $invoice_id, $amount, 'pending', NOW())");
    
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
    
    $transaction = $conn->query("SELECT * FROM payment_transactions WHERE transaction_id = '$transaction_id'")->fetch_assoc();
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        return;
    }
    
    $gateway = $conn->query("SELECT * FROM payment_gateways WHERE id = {$transaction['gateway_id']}")->fetch_assoc();
    
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
    
    if (isset($result['success']) && $result['success'] === true) {
        $conn->query("UPDATE payment_transactions SET 
                      status = 'completed', 
                      gateway_response = '" . $conn->real_escape_string($response) . "',
                      verified_at = NOW(),
                      updated_at = NOW()
                      WHERE id = {$transaction['id']}");
        
        if ($transaction['invoice_id']) {
            $conn->query("UPDATE billing_invoices SET status = 'paid', paid_at = NOW() WHERE id = {$transaction['invoice_id']}");
        }
        
        echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
    } else {
        $conn->query("UPDATE payment_transactions SET 
                      status = 'failed',
                      gateway_response = '" . $conn->real_escape_string($response) . "'
                      WHERE id = {$transaction['id']}");
        
        echo json_encode(['success' => false, 'error' => 'Payment verification failed']);
    }
}

function handleWebhook($data) {
    global $conn;
    
    $event = $data['event'] ?? '';
    $transaction_id = $data['transaction_id'] ?? '';
    
    if ($event === 'payment.success') {
        $token = $data['token'] ?? '';
        
        $transaction = $conn->query("SELECT * FROM payment_transactions WHERE transaction_id = '$transaction_id'")->fetch_assoc();
        
        if ($transaction && $transaction['status'] == 'pending') {
            $conn->query("UPDATE payment_transactions SET 
                          status = 'completed',
                          gateway_response = '" . $conn->real_escape_string(json_encode($data)) . "',
                          verified_at = NOW()
                          WHERE id = {$transaction['id']}");
            
            if ($transaction['invoice_id']) {
                $conn->query("UPDATE billing_invoices SET status = 'paid', paid_at = NOW() WHERE id = {$transaction['invoice_id']}");
            }
        }
    }
    
    http_response_code(200);
    echo json_encode(['received' => true]);
}
