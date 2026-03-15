<?php
session_start();
$page_title = "Hotspot Settings";

chdir(__DIR__ . '/../..');
$base_path = '.';
include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_path . '/index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $settings = [
        'sms_api_url' => $_POST['sms_api_url'] ?? '',
        'sms_api_key' => $_POST['sms_api_key'] ?? '',
        'sms_sender_id' => $_POST['sms_sender_id'] ?? '',
        'sms_username' => $_POST['sms_username'] ?? '',
        'sms_password' => $_POST['sms_password'] ?? '',
        'portal_title' => $_POST['portal_title'] ?? 'Hotspot Portal',
        'session_timeout' => $_POST['session_timeout'] ?? 3600,
        'otp_expiry' => $_POST['otp_expiry'] ?? 300,
    ];

    foreach ($settings as $key => $value) {
        $conn->query("
            INSERT INTO hotspot_settings (setting_key, setting_value) 
            VALUES ('$key', '$value')
            ON DUPLICATE KEY UPDATE setting_value = '$value'
        ");
    }

    $message = 'Settings saved successfully!';
}

$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM hotspot_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
        }
        body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background: #f1f5f9; }
        
        .hotspot-wrapper { padding-top: 60px; min-height: 100vh; }
        
        .top-nav {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 0 25px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
        }
        .top-nav-brand i { font-size: 24px; color: var(--primary); }
        
        .top-nav-menu { display: flex; gap: 5px; }
        .top-nav-item {
            padding: 10px 18px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        .top-nav-item:hover, .top-nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .top-nav-item.active { background: var(--primary); color: white; }
        
        .top-nav-actions { display: flex; align-items: center; gap: 15px; }
        .top-nav-user {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .content-wrapper { padding: 25px; max-width: 900px; margin: 0 auto; }
        
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 24px; font-weight: 600; color: #1e293b; margin: 0; }
        
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .content-card .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .content-card .card-header h5 { margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; }
        .content-card .card-body { padding: 20px; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #64748b; color: white; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }
        
        .row { display: flex; flex-wrap: wrap; margin: -10px; }
        .col-md-6 { width: 50%; padding: 10px; }
        
        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <a href="index.php" class="top-nav-brand">
            <i class="fas fa-wifi"></i>
            <span>HOTSPOT PORTAL</span>
        </a>
        <div class="top-nav-menu">
            <a href="index.php" class="top-nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="plans.php" class="top-nav-item">
                <i class="fas fa-ticket"></i> Plans & Vouchers
            </a>
            <a href="users.php" class="top-nav-item">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="settings.php" class="top-nav-item active">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
        <div class="top-nav-actions">
            <div class="top-nav-user">
                <i class="fas fa-user-circle" style="font-size: 24px;"></i>
                <span><?= $_SESSION['username'] ?? 'Admin' ?></span>
                <a href="../../logout.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; margin-left: 10px;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>
    
    <div class="hotspot-wrapper">
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-cog"></i> Hotspot Settings</h1>
                <div style="color: #64748b; margin-top: 5px;">
                    <a href="../../dashboard.php" style="color: var(--primary); text-decoration: none;">Home</a>
                    <span style="margin: 0 8px;">/</span>
                    <span>Settings</span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- SMS Settings -->
                <div class="content-card">
                    <div class="card-header">
                        <h5><i class="fas fa-sms"></i> SMS Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>SMS API URL</label>
                                    <input type="text" name="sms_api_url" class="form-control" 
                                           value="<?= $settings['sms_api_url'] ?? '' ?>"
                                           placeholder="https://sms.example.com/api/send">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>API Key</label>
                                    <input type="text" name="sms_api_key" class="form-control" 
                                           value="<?= $settings['sms_api_key'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Sender ID</label>
                                    <input type="text" name="sms_sender_id" class="form-control" 
                                           value="<?= $settings['sms_sender_id'] ?? 'HOTSPOT' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Username (if required)</label>
                                    <input type="text" name="sms_username" class="form-control" 
                                           value="<?= $settings['sms_username'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password (if required)</label>
                                    <input type="password" name="sms_password" class="form-control" 
                                           value="<?= $settings['sms_password'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        <div class="help-text" style="margin-top: 15px;">
                            <i class="fas fa-info-circle"></i> 
                            For local SMS providers (e.g., Nepali providers), enter their API endpoint URL. 
                            The system will send POST requests with: to, message, api_key parameters.
                        </div>
                    </div>
                </div>

                <!-- Portal Settings -->
                <div class="content-card">
                    <div class="card-header">
                        <h5><i class="fas fa-globe"></i> Portal Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Portal Title</label>
                                    <input type="text" name="portal_title" class="form-control" 
                                           value="<?= $settings['portal_title'] ?? 'Hotspot Portal' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Session Timeout (seconds)</label>
                                    <input type="number" name="session_timeout" class="form-control" 
                                           value="<?= $settings['session_timeout'] ?? 3600 ?>">
                                    <div class="help-text">3600 = 1 hour, 7200 = 2 hours</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>OTP Expiry (seconds)</label>
                                    <input type="number" name="otp_expiry" class="form-control" 
                                           value="<?= $settings['otp_expiry'] ?? 300 ?>">
                                    <div class="help-text">300 = 5 minutes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </form>
        </div>
    </div>
</body>
</html>
