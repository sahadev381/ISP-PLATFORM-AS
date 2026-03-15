<?php
/**
 * SMS Gateway for Hotspot Module
 * Supports multiple SMS providers
 */

class SMSGateway {
    private $config = [];
    
    public function __construct() {
        $this->loadConfig();
    }
    
    private function loadConfig() {
        chdir(__DIR__ . '/../..');
        include 'config.php';
        
        $result = $conn->query("SELECT setting_key, setting_value FROM hotspot_settings");
        while ($row = $result->fetch_assoc()) {
            $this->config[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    public function sendSMS($phone, $message) {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Add country code if not present
        if (substr($phone, 0, 1) == '9' && strlen($phone) == 10) {
            $phone = '+977' . $phone;
        }
        
        // Try local SMS API first
        $result = $this->sendLocalSMS($phone, $message);
        
        if (!$result) {
            // Fallback to generic HTTP SMS
            $result = $this->sendGenericSMS($phone, $message);
        }
        
        return $result;
    }
    
    private function sendLocalSMS($phone, $message) {
        $apiUrl = $this->config['sms_api_url'] ?? '';
        
        if (empty($apiUrl)) {
            return false;
        }
        
        // Check for common local SMS APIs
        if (strpos($apiUrl, 'sms') !== false || strpos($apiUrl, 'api') !== false) {
            $params = [
                'to' => $phone,
                'message' => $message,
                'sender' => $this->config['sms_sender_id'] ?? 'HOTSPOT'
            ];
            
            // Add authentication if available
            if (!empty($this->config['sms_api_key'])) {
                $params['api_key'] = $this->config['sms_api_key'];
            }
            if (!empty($this->config['sms_username'])) {
                $params['username'] = $this->config['sms_username'];
            }
            if (!empty($this->config['sms_password'])) {
                $params['password'] = $this->config['sms_password'];
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode == 200 || $httpCode == 201;
        }
        
        return false;
    }
    
    private function sendGenericSMS($phone, $message) {
        // Try common Nepal SMS providers
        
        // SmsApi.com.np (common in Nepal)
        $apiUrl = 'https://smsapi.com.np/api/send';
        
        $params = [
            'sender' => 'HOTSPOT',
            'recipient' => $phone,
            'message' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200 || $httpCode == 201;
    }
    
    public function sendOTP($phone) {
        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Save to database
        include '../../config.php';
        
        // Delete old OTPs for this phone
        $conn->query("DELETE FROM hotspot_sms_otp WHERE phone = '$phone'");
        
        // Insert new OTP
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes
        $conn->query("INSERT INTO hotspot_sms_otp (phone, otp, expires_at) VALUES ('$phone', '$otp', '$expires')");
        
        // Log SMS
        $conn->query("INSERT INTO hotspot_sms_logs (phone, message, otp, status) VALUES ('$phone', 'OTP: $otp', '$otp', 'sent')");
        
        // Send SMS
        $result = $this->sendSMS($phone, "Your OTP is: $otp. Valid for 5 minutes.");
        
        return $result;
    }
    
    public function verifyOTP($phone, $otp) {
        include '../../config.php';
        
        $result = $conn->query("
            SELECT * FROM hotspot_sms_otp 
            WHERE phone = '$phone' AND otp = '$otp' 
            AND used = 0 AND expires_at > NOW()
            LIMIT 1
        ");
        
        if ($result->num_rows > 0) {
            // Mark as used
            $conn->query("UPDATE hotspot_sms_otp SET used = 1 WHERE phone = '$phone' AND otp = '$otp'");
            return true;
        }
        
        return false;
    }
}
