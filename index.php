<?php
include 'config.php';
include 'includes/security.php';

$security = new Security($conn);
$error = '';
$remainingAttempts = 0;

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Check if account is locked out
    if ($security->isLockedOut($username)) {
        $error = "Too many failed attempts. Please try again after 15 minutes.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role, branch_id FROM admins WHERE username=? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // Login successful
                $security->recordLoginAttempt($username, true);
                $security->logActivity($row['id'], $username, 'login', 'Admin login successful');
                
                $_SESSION['user_id']   = (int)$row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role']     = $row['role'] ?? '';
                $_SESSION['branch_id']= $row['branch_id'] ?? null;
                $_SESSION['login_time'] = time();
                
                header("Location: dashboard.php");
                exit;
            }
        }
        
        // Login failed
        $security->recordLoginAttempt($username, false);
        
        $failedCount = $security->getFailedAttempts($username);
        $remainingAttempts = 5 - $failedCount;
        
        if ($remainingAttempts <= 0) {
            $error = "Too many failed attempts. Account locked for 15 minutes.";
        } else {
            $error = "Invalid username or password. $remainingAttempts attempts remaining.";
        }
    }
}

$logo = '';
$config = $conn->query("SELECT logo FROM system_config LIMIT 1")->fetch_assoc();
if ($config) {
    $logo = $config['logo'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 900px;
            background: var(--bg-card);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .login-left {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-right {
            width: 380px;
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-right::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .login-logo {
            margin-bottom: 40px;
        }
        
        .login-logo img {
            max-height: 50px;
        }
        
        .login-logo h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 15px;
        }
        
        .login-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 40px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-main);
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .login-right-content {
            position: relative;
            z-index: 1;
            text-align: center;
            color: white;
        }
        
        .login-right-content h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .login-right-content p {
            opacity: 0.9;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .login-right-features {
            margin-top: 40px;
            text-align: left;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            opacity: 0.95;
        }
        
        .feature-item i {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-item span {
            font-size: 14px;
            font-weight: 500;
        }
        
        .login-footer {
            margin-top: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
            }
            .login-right {
                width: 100%;
                padding: 40px 30px;
            }
            .login-left {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-left">
            <div class="login-logo">
                <?php if(!empty($logo) && file_exists('uploads/'.$logo)): ?>
                    <img src="uploads/<?= htmlspecialchars($logo) ?>" alt="ISP SYSTEM">
                <?php else: ?>
                    <i class="fa fa-wifi" style="font-size: 48px; color: var(--primary);"></i>
                <?php endif; ?>
            </div>
            
            <h1 class="login-title">ISP SYSTEM</h1>
            <p class="login-subtitle">Broadband Management Platform</p>
            
            <?php if($error): ?>
                <div class="error-message">
                    <i class="fa fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fa fa-user"></i>
                        <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fa fa-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" name="login" class="btn-login">
                    <i class="fa fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div class="login-footer">
                &copy; <?= date('Y') ?> ISP Management System
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-right-content">
                <h2>ISP Management</h2>
                <p>Complete solution for broadband network management</p>
                
                <div class="login-right-features">
                    <div class="feature-item">
                        <i class="fa fa-wifi"></i>
                        <span>TR-069 Device Management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa fa-chart-line"></i>
                        <span>Real-time Analytics</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa fa-credit-card"></i>
                        <span>Online Payments</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa fa-headset"></i>
                        <span>Tickets & Support</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
