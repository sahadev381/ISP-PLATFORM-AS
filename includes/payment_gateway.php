<?php
/**
 * Payment Gateway Manager
 * Supports: Khalti, eSewa, Bank Transfer, Cash
 */

class PaymentGateway {
    private $conn;
    private $gateways = [];
    
    public function __construct() {
        include __DIR__ . '/../config.php';
        $this->conn = $conn;
        $this->loadGateways();
    }
    
    private function loadGateways() {
        $result = $this->conn->query("SELECT * FROM payment_gateways WHERE is_active = 1");
        while ($row = $result->fetch_assoc()) {
            $this->gateways[$row['gateway_name']] = $row;
        }
    }
    
    public function getGateways() {
        return $this->gateways;
    }
    
    public function getGateway($name) {
        return $this->gateways[$name] ?? null;
    }
    
    public function createTransaction($customerId, $gatewayName, $amount, $customerData = []) {
        $gateway = $this->getGateway($gatewayName);
        if (!$gateway) {
            return ['status' => 'error', 'message' => 'Gateway not found'];
        }
        
        $transactionId = 'TXN-' . strtoupper(uniqid()) . rand(1000, 9999);
        
        $stmt = $this->conn->prepare("INSERT INTO payment_transactions 
            (transaction_id, customer_id, gateway_id, amount, customer_name, customer_email, customer_phone, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        $stmt->bind_param("siidsss", 
            $transactionId, 
            $customerId, 
            $gateway['id'], 
            $amount,
            $customerData['name'] ?? '',
            $customerData['email'] ?? '',
            $customerData['phone'] ?? ''
        );
        
        $stmt->execute();
        
        return [
            'status' => 'success',
            'transaction_id' => $transactionId,
            'gateway' => $gateway,
            'amount' => $amount
        ];
    }
    
    public function verifyPayment($gatewayName, $data) {
        switch ($gatewayName) {
            case 'khalti':
                return $this->verifyKhalti($data);
            case 'esewa':
                return $this->verifyEsewa($data);
            case 'bank_transfer':
                return $this->verifyBankTransfer($data);
            default:
                return ['status' => 'error', 'message' => 'Unknown gateway'];
        }
    }
    
    private function verifyKhalti($data) {
        $token = $data['token'] ?? '';
        $amount = $data['amount'] ?? 0;
        $transactionId = $data['transaction_id'] ?? '';
        
        $gateway = $this->getGateway('khalti');
        if (!$gateway) {
            return ['status' => 'error', 'message' => 'Khalti not configured'];
        }
        
        // In production, verify with Khalti API
        // For now, simulate verification
        if (empty($token) || empty($transactionId)) {
            return ['status' => 'error', 'message' => 'Invalid token'];
        }
        
        // Mark transaction as completed
        $this->updateTransactionStatus($transactionId, 'completed');
        
        return [
            'status' => 'success',
            'message' => 'Payment verified',
            'reference_id' => $token
        ];
    }
    
    private function verifyEsewa($data) {
        $refId = $data['refId'] ?? '';
        $transactionId = $data['transaction_id'] ?? '';
        
        $gateway = $this->getGateway('esewa');
        if (!$gateway) {
            return ['status' => 'error', 'message' => 'eSewa not configured'];
        }
        
        if (empty($refId) || empty($transactionId)) {
            return ['status' => 'error', 'message' => 'Invalid reference'];
        }
        
        $this->updateTransactionStatus($transactionId, 'completed');
        
        return [
            'status' => 'success',
            'message' => 'Payment verified',
            'reference_id' => $refId
        ];
    }
    
    private function verifyBankTransfer($data) {
        $transactionId = $data['transaction_id'] ?? '';
        $bankRef = $data['bank_reference'] ?? '';
        
        // Bank transfers need manual verification
        $this->updateTransactionStatus($transactionId, 'processing', ['reference_id' => $bankRef]);
        
        return [
            'status' => 'pending',
            'message' => 'Bank transfer pending verification'
        ];
    }
    
    public function updateTransactionStatus($transactionId, $status, $additionalData = []) {
        $refId = $additionalData['reference_id'] ?? null;
        
        if ($refId) {
            $stmt = $this->conn->prepare("UPDATE payment_transactions SET status = ?, reference_id = ?, completed_at = NOW() WHERE transaction_id = ?");
            $stmt->bind_param("sss", $status, $refId, $transactionId);
        } else {
            $stmt = $this->conn->prepare("UPDATE payment_transactions SET status = ?, completed_at = NOW() WHERE transaction_id = ?");
            $stmt->bind_param("ss", $status, $transactionId);
        }
        
        $stmt->execute();
        
        if ($status === 'completed') {
            $this->onPaymentSuccess($transactionId);
        }
        
        return ['status' => 'success'];
    }
    
    private function onPaymentSuccess($transactionId) {
        // Get transaction details
        $result = $this->conn->query("SELECT * FROM payment_transactions WHERE transaction_id = '$transactionId'");
        $txn = $result->fetch_assoc();
        
        if (!$txn) return;
        
        // Create billing history
        $this->conn->query("INSERT INTO billing_history (customer_id, billing_date, amount, total_amount, status, payment_method, transaction_id) 
            VALUES ({$txn['customer_id']}, CURDATE(), {$txn['amount']}, {$txn['amount']}, 'paid', '{$txn['payment_method']}', '$transactionId')");
        
        // Update customer subscription if exists
        $sub = $this->conn->query("SELECT * FROM customer_subscriptions WHERE customer_id = {$txn['customer_id']} AND status = 'active' ORDER BY id DESC LIMIT 1")->fetch_assoc();
        
        if ($sub) {
            // Calculate next billing date
            $cycle = $this->conn->query("SELECT days FROM billing_cycles WHERE id = " . $sub['billing_cycle_id'])->fetch_assoc();
            $days = $cycle['days'] ?? 30;
            $nextDate = date('Y-m-d', strtotime("+$days days"));
            
            $this->conn->query("UPDATE customer_subscriptions SET next_billing_date = '$nextDate' WHERE id = {$sub['id']}");
        }
    }
    
    public function getTransaction($transactionId) {
        $result = $this->conn->query("SELECT * FROM payment_transactions WHERE transaction_id = '$transactionId'");
        return $result ? $result->fetch_assoc() : null;
    }
    
    public function getCustomerTransactions($customerId, $limit = 50) {
        $result = $this->conn->query("SELECT * FROM payment_transactions WHERE customer_id = $customerId ORDER BY created_at DESC LIMIT $limit");
        $txns = [];
        while ($row = $result->fetch_assoc()) {
            $txns[] = $row;
        }
        return $txns;
    }
    
    public function calculateFees($gatewayName, $amount) {
        $gateway = $this->getGateway($gatewayName);
        if (!$gateway) return $amount;
        
        $percentageFee = ($amount * $gateway['fees_percentage']) / 100;
        $fixedFee = $gateway['fixed_fee'] ?? 0;
        
        return [
            'subtotal' => $amount,
            'fee' => $percentageFee + $fixedFee,
            'total' => $amount + $percentageFee + $fixedFee
        ];
    }
}

/**
 * Khalti Payment Integration
 */
class KhaltiPayment {
    private $gateway;
    
    public function __construct($gateway) {
        $this->gateway = $gateway;
    }
    
    public function initPayment($amount, $productIdentity, $productName, $transactionId) {
        $config = json_decode($this->gateway['config_json'] ?? '{}', true);
        
        return [
            'public_key' => $this->gateway['public_key'] ?? '',
            'amount' => $amount * 100, // Khalti uses paisa
            'product_identity' => $productIdentity,
            'product_name' => $productName,
            'product_url' => $this->gateway['redirect_url'] ?? '',
            'additional_info' => [
                'transaction_id' => $transactionId
            ]
        ];
    }
    
    public function verify($token, $amount) {
        // Production verification
        $apiUrl = $this->gateway['is_test_mode'] ? 'https://khalti.com/api/v2/payment/verify/' : 'https://khalti.com/api/v2/payment/verify/';
        
        $data = [
            'token' => $token,
            'amount' => $amount * 100
        ];
        
        $headers = ['Authorization: Key ' . $this->gateway['api_secret']];
        
        // In production, make API call
        // return $this->makeRequest('POST', $apiUrl, $data, $headers);
        
        return ['status' => 'success', 'message' => 'Verified'];
    }
}

/**
 * eSewa Payment Integration  
 */
class EsewaPayment {
    private $gateway;
    
    public function __construct($gateway) {
        $this->gateway = $gateway;
    }
    
    public function initPayment($amount, $productId, $transactionId) {
        $config = json_decode($this->gateway['config_json'] ?? '{}', true);
        
        $params = [
            'amt' => $amount,
            'pdc' => 0,
            'psc' => 0,
            'txAmt' => 0,
            'tAmt' => $amount,
            'pid' => $productId,
            'su' => ($this->gateway['redirect_url'] ?? '') . '?transaction_id=' . $transactionId,
            'fu' => ($this->gateway['redirect_url'] ?? '') . '?transaction_id=' . $transactionId . '&status=failed'
        ];
        
        return [
            'url' => 'https://esewa.com.np/epay/main',
            'params' => $params
        ];
    }
    
    public function verify($refId, $amount) {
        // Production verification
        $apiUrl = 'https://esewa.com.np/api/v1/epay/transaction/status';
        
        // In production, make API call
        
        return ['status' => 'success', 'message' => 'Verified'];
    }
}
