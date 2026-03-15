<?php
session_start();
include '../config.php';

$token = $_POST['token'] ?? '';
$amount = $_POST['amount'] ?? 0;
$username = $_POST['username'] ?? '';

if(empty($token) || empty($amount) || empty($username)){
    die("Invalid request. <a href='khalti_pay.php'>Go Back</a>");
}

$secret_key = KHALTI_SECRET_KEY;
$verify_url = KHALTI_VERIFY_URL;

$data = [
    'token' => $token,
    'amount' => $amount * 100
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verify_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Key $secret_key",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);

if(isset($res['idx']) && $res['idx']){
    $amount_val = floatval($amount);
    $txn_id = $res['idx'];
    
    $conn->query("
        UPDATE customers 
        SET wallet = wallet + $amount_val 
        WHERE username = '$username'
    ");
    
    $conn->query("
        INSERT INTO wallet_transactions (username, amount, gateway, status, txn_id)
        VALUES ('$username', $amount_val, 'khalti', 'success', '$txn_id')
    ");
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Payment Success</title>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='../assets/css/theme.css'>
        <style>
            body { 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
                background: var(--bg-main);
                font-family: 'Inter', sans-serif;
            }
            .success-box {
                background: var(--bg-card);
                padding: 40px;
                border-radius: 16px;
                text-align: center;
                box-shadow: var(--shadow-lg);
                max-width: 400px;
            }
            .success-icon {
                width: 80px;
                height: 80px;
                background: #d1fae5;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                color: #10b981;
                font-size: 40px;
            }
            h2 { color: #10b981; margin-bottom: 15px; }
            p { color: var(--text-muted); margin-bottom: 25px; }
            .amount { font-size: 24px; font-weight: 700; color: var(--text-main); }
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: var(--primary);
                color: white;
                border-radius: 10px;
                text-decoration: none;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class='success-box'>
            <div class='success-icon'>
                <i class='fa fa-check'></i>
            </div>
            <h2>Payment Successful!</h2>
            <p>Your wallet has been recharged successfully.</p>
            <div class='amount'>NPR $amount_val</div>
            <br>
            <a href='../dashboard.php' class='btn'>
                <i class='fa fa-home'></i> Go to Dashboard
            </a>
        </div>
    </body>
    </html>";
} else {
    $conn->query("
        INSERT INTO wallet_transactions (username, amount, gateway, status, txn_id)
        VALUES ('$username', $amount, 'khalti', 'failed', '".($res['idx'] ?? 'failed')."')
    ");
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Payment Failed</title>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='../assets/css/theme.css'>
        <style>
            body { 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
                background: var(--bg-main);
                font-family: 'Inter', sans-serif;
            }
            .error-box {
                background: var(--bg-card);
                padding: 40px;
                border-radius: 16px;
                text-align: center;
                box-shadow: var(--shadow-lg);
                max-width: 400px;
            }
            .error-icon {
                width: 80px;
                height: 80px;
                background: #fee2e2;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                color: #ef4444;
                font-size: 40px;
            }
            h2 { color: #ef4444; margin-bottom: 15px; }
            p { color: var(--text-muted); margin-bottom: 25px; }
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: var(--primary);
                color: white;
                border-radius: 10px;
                text-decoration: none;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <div class='error-icon'>
                <i class='fa fa-times'></i>
            </div>
            <h2>Payment Failed!</h2>
            <p>There was an error processing your payment. Please try again.</p>
            <a href='khalti_pay.php' class='btn'>
                <i class='fa fa-redo'></i> Try Again
            </a>
        </div>
    </body>
    </html>";
}
