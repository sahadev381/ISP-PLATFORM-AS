<?php
include '../config.php';
include '../includes/genieacs_api.php';
session_start();

if (!isset($_SESSION['customer_user'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['customer_user'];
$user = $conn->query("SELECT * FROM customers WHERE username = '$username'")->fetch_assoc();
$deviceId = $user['tr069_device_id'] ?? '';

$msg = '';
$msg_type = '';

// Handle Update
if (isset($_POST['update_wifi']) && $deviceId) {
    $new_ssid = $_POST['ssid'];
    $new_pass = $_POST['password'];
    
    // Push to GenieACS
    $payload = [
        "name" => "setParameterValues",
        "parameterValues" => [
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", $new_ssid, "xsd:string"],
            ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase", $new_pass, "xsd:string"]
        ]
    ];
    
    $response = genieacs_request("devices/$deviceId/tasks", "POST", $payload);
    
    if (!isset($response['error'])) {
        $msg = "Wi-Fi settings have been queued for update. Your router will update shortly.";
        $msg_type = "success";
        
        // Update local cache
        $conn->query("UPDATE customers SET wifi_ssid='$new_ssid', wifi_password='$new_pass' WHERE username='$username'");
    } else {
        $msg = "Failed to reach your router. Please ensure it is powered on.";
        $msg_type = "error";
    }
}

// Fetch live SSID if possible
$live_ssid = $user['wifi_ssid'];
if ($deviceId) {
    $device_data = genieacs_request("devices/" . urlencode($deviceId) . "?projection=InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID");
    if ($device_data && isset($device_data['InternetGatewayDevice'])) {
        $live_ssid = $device_data['InternetGatewayDevice']['LANDevice'][1]['WLANConfiguration'][1]['SSID']['_value'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wi-Fi Settings - ISP Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .header { background: #fff; padding: 15px 25px; display: flex; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .container { padding: 25px; max-width: 600px; margin: 0 auto; }
        .card { background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; }
        input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box; }
        .btn-update { width: 100%; padding: 15px; background: #3b82f6; color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #16a34a; }
        .alert-error { background: #fee2e2; color: #ef4444; }
    </style>
</head>
<body>

<div class="header">
    <a href="dashboard.php" style="color:#1e293b; text-decoration:none; margin-right:20px;"><i class="fa fa-arrow-left"></i></a>
    <div style="font-weight: 800; font-size: 18px; color: #3b82f6;">Wi-Fi Configuration</div>
</div>

<div class="container">
    <div class="card">
        <h3><i class="fa fa-sliders"></i> Manage Router</h3>
        <p style="color:#64748b; font-size:14px; margin-bottom:25px;">You can change your Wi-Fi name and password here. The update may take 30-60 seconds.</p>

        <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Wi-Fi Name (SSID)</label>
                <input type="text" name="ssid" value="<?= htmlspecialchars($live_ssid) ?>" required>
            </div>
            <div class="form-group">
                <label>New Wi-Fi Password</label>
                <input type="text" name="password" value="<?= htmlspecialchars($user['wifi_password']) ?>" required minlength="8">
            </div>
            <button type="submit" name="update_wifi" class="btn-update">
                <i class="fa fa-sync"></i> Push Update to Router
            </button>
        </form>
    </div>
</div>

</body>
</html>
