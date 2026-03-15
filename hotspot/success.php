<?php
session_start();
chdir(__DIR__ . '/..');
include_once 'config.php';
include_once 'hotspot/includes/auth.php';

$auth = new HotspotAuth();
$session = $auth->checkSession();

if (!$session) {
    header('Location: index.php');
    exit;
}

// Get session info
$username = $_SESSION['hotspot_username'] ?? 'Guest';
$loginTime = date('Y-m-d H:i:s', $_SESSION['hotspot_login_time'] ?? time());

// Get profile info
$profile = null;
if (!empty($session['profile_id'])) {
    $result = $conn->query("SELECT * FROM hotspot_profiles WHERE id = " . $session['profile_id']);
    if ($result && $result->num_rows > 0) {
        $profile = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --success: #10b981;
        }
        
        body {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            text-align: center;
        }
        
        .success-header {
            background: var(--success);
            color: white;
            padding: 40px 30px;
        }
        
        .success-header i {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .success-header h2 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .success-body {
            padding: 30px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .info-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .btn-logout {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px 30px;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: #dc2626;
        }
        
        .countdown {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 15px;
        }
    </style>
    <meta http-equiv="refresh" content="3;url=http://google.com">
</head>
<body>
    <div class="success-card">
        <div class="success-header">
            <i class="fas fa-check-circle"></i>
            <h2>Login Successful!</h2>
            <p>Welcome to the internet</p>
        </div>
        
        <div class="success-body">
            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value"><?= htmlspecialchars($username) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Login Time</span>
                <span class="info-value"><?= $loginTime ?></span>
            </div>
            
            <?php if ($profile): ?>
            <div class="info-row">
                <span class="info-label">Plan</span>
                <span class="info-value"><?= htmlspecialchars($profile['name']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Speed</span>
                <span class="info-value"><?= round($profile['speed_kbps']/1024) ?> Mbps</span>
            </div>
            
            <?php if ($profile['data_limit_mb'] > 0): ?>
            <div class="info-row">
                <span class="info-label">Data Limit</span>
                <span class="info-value"><?= $profile['data_limit_mb'] ?> MB</span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="info-label">Session ID</span>
                <span class="info-value" style="font-size: 0.8rem"><?= substr($session['session_id'] ?? 'N/A', 0, 16) ?>...</span>
            </div>
            
            <p class="countdown">
                Redirecting you to your destination in 3 seconds...
                <br>
                <small>Click below if not redirected</small>
            </p>
            
            <a href="logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</body>
</html>