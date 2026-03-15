<?php
/**
 * Messaging Helper: Supports multiple SMS gateways and logging
 */

function sendSMS($username, $phone, $message) {
    global $conn;
    
    // 1. Fetch SMS Settings
    $settings = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'sms_%'");
    while($r = $res->fetch_assoc()) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    
    $status = 'pending';
    $gateway_url = $settings['sms_gateway_url'] ?? '';
    $api_key = $settings['sms_api_key'] ?? '';

    // 2. Simple GET/POST Gateway Logic (Placeholder for actual API call)
    if (!empty($gateway_url)) {
        // Example: Replace placeholders in URL
        $url = str_replace(['{phone}', '{message}', '{key}'], [urlencode($phone), urlencode($message), $api_key], $gateway_url);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $status = 'sent';
    } else {
        // If no gateway is configured, we log it as 'pending' or 'failed'
        $status = 'failed';
    }

    // 3. Log to Database
    $stmt = $conn->prepare("INSERT INTO sms_logs (customer_user, phone_number, message, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $phone, $message, $status);
    $stmt->execute();
    
    return ($status == 'sent');
}
