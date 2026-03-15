<?php
class Notification {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function sendSMS($phone, $message) {
        $api_key = $this->getSetting('sms_api_key');
        $api_url = $this->getSetting('sms_api_url');
        
        if (empty($api_key) || empty($api_url)) {
            return ['success' => false, 'error' => 'SMS API not configured'];
        }
        
        // Example: SMS API integration (modify as per your provider)
        $data = [
            'api_key' => $api_key,
            'sender_id' => $this->getSetting('sms_sender_id', 'ISP'),
            'phone' => $phone,
            'message' => $message
        ];
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function sendEmail($to, $subject, $body, $is_html = true) {
        $smtp_host = $this->getSetting('smtp_host');
        $smtp_user = $this->getSetting('smtp_user');
        $smtp_pass = $this->getSetting('smtp_pass');
        $smtp_port = $this->getSetting('smtp_port', 587);
        $from_email = $this->getSetting('smtp_from_email', 'noreply@isp.com');
        $from_name = $this->getSetting('smtp_from_name', 'ISP System');
        
        if (empty($smtp_host) || empty($smtp_user)) {
            return ['success' => false, 'error' => 'SMTP not configured'];
        }
        
        $headers = [
            "From: $from_name <$from_email>",
            "Reply-To: $from_email",
            "MIME-Version: 1.0",
            "Content-Type: " . ($is_html ? "text/html" : "text/plain") . "; charset=UTF-8"
        ];
        
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        return ['success' => $success];
    }
    
    public function notifyCustomerRegistration($customer_id) {
        $customer = $this->conn->query("SELECT * FROM customers WHERE id=$customer_id")->fetch_assoc();
        
        $message = "Welcome {$customer['full_name']}! Your ISP account has been created. " .
                   "Username: {$customer['username']}. " .
                   "Please make payment to activate your service.";
        
        $this->sendSMS($customer['phone'], $message);
        $this->sendEmail($customer['email'], 'Welcome to ISP', $message);
    }
    
    public function notifyPaymentReceived($username, $amount) {
        $customer = $this->conn->query("SELECT * FROM customers WHERE username='$username'")->fetch_assoc();
        
        $message = "Payment of NPR $amount received! Thank you for your payment.";
        
        if (!empty($customer['phone'])) {
            $this->sendSMS($customer['phone'], $message);
        }
        if (!empty($customer['email'])) {
            $this->sendEmail($customer['email'], 'Payment Received', $message);
        }
    }
    
    public function notifyExpiryReminder($username) {
        $customer = $this->conn->query("SELECT * FROM customers WHERE username='$username'")->fetch_assoc();
        
        $message = "Dear {$customer['full_name']}, your internet service will expire on {$customer['expiry']}. " .
                   "Please renew to avoid interruption.";
        
        if (!empty($customer['phone'])) {
            $this->sendSMS($customer['phone'], $message);
        }
        if (!empty($customer['email'])) {
            $this->sendEmail($customer['email'], 'Expiry Reminder', $message);
        }
    }
    
    public function notifyTicketReply($ticket_id, $customer_email) {
        $message = "Your support ticket #$ticket_id has been updated. Please login to view the response.";
        
        $this->sendEmail($customer_email, 'Ticket Update', $message);
    }
    
    private function getSetting($key, $default = '') {
        $result = $this->conn->query("SELECT setting_value FROM system_settings WHERE setting_key='$key'");
        $row = $result->fetch_assoc();
        return $row ? $row['setting_value'] : $default;
    }
}
