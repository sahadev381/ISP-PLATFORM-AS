<?php
session_start();
$page_title = "Hotspot Login";
$error = '';
$success = '';

chdir(__DIR__ . '/..');
include_once 'config.php';
include_once 'hotspot/includes/auth.php';

$auth = new HotspotAuth();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loginType = $_POST['login_type'] ?? 'voucher';
    
    $credentials = [
        'login_type' => $loginType,
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? '',
        'pin' => $_POST['pin'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'otp' => $_POST['otp'] ?? ''
    ];
    
    if ($loginType == 'sms' && empty($_POST['otp']) && !empty($_POST['phone'])) {
        $result = $auth->sendSMSOTP($_POST['phone']);
        if ($result['status'] == 'success') {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $result = $auth->authenticate($credentials);
        
        if ($result['status'] == 'success') {
            header('Location: success.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$portalName = 'Hotspot Portal';
$portalLogo = '';
$result = $conn->query("SELECT setting_value FROM hotspot_settings WHERE setting_key = 'portal_title'");
if ($result && $row = $result->fetch_assoc()) {
    $portalName = $row['setting_value'];
}

$portalProfile = null;
$result = $conn->query("SELECT * FROM hotspot_portal_profiles WHERE status = 'active' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $portalProfile = $result->fetch_assoc();
    $portalName = $portalProfile['name'] ?? $portalName;
    $portalLogo = $portalProfile['logo'] ?? '';
}

// Load captive portal customization settings
$captiveSettings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM hotspot_settings WHERE setting_key LIKE 'captive_%'");
while ($row = $result->fetch_assoc()) {
    $captiveSettings[$row['setting_key']] = $row['setting_value'];
}

$primaryColor = $captiveSettings['captive_primary_color'] ?? ($portalProfile['primary_color'] ?? '#6366f1');
$welcomeMessage = $captiveSettings['captive_welcome_message'] ?? 'Welcome to our WiFi';
$showFeatures = $captiveSettings['captive_show_features'] ?? 1;
$bgType = $captiveSettings['captive_bg_type'] ?? 'gradient';
$enableVideo = $captiveSettings['captive_enable_video_bg'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $portalName ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --primary-dark: #4f46e5;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Video Background */
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .video-background video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.85) 0%, rgba(79, 70, 229, 0.9) 100%);
            z-index: -1;
        }
        
        /* Animated Background Shapes */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape-1 {
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }
        
        .shape-2 {
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            bottom: 10%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .shape-3 {
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            top: 50%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }
        
        .navbar-custom a:hover {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        /* Dropdown Menu */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            min-width: 220px;
            padding: 8px 0;
            z-index: 1000;
            animation: dropdownFade 0.2s ease;
        }
        
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu a {
            display: flex !important;
            align-items: center;
            gap: 12px;
            padding: 12px 20px !important;
            color: #374151 !important;
            text-decoration: none !important;
            font-weight: 500 !important;
            transition: all 0.2s;
        }
        
        .dropdown-menu a:hover {
            background: #f3f4f6 !important;
            color: var(--primary) !important;
        }
        
        .dropdown-menu a i {
            width: 20px;
            text-align: center;
            color: var(--primary);
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 0;
        }
        
        .navbar-custom a {
            transition: all 0.3s;
        }
        
        /* Navbar */
        .navbar-custom {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
            text-decoration: none;
        }
        
        .navbar-brand img {
            height: 50px;
            border-radius: 10px;
        }
        
        .navbar-brand span {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        /* Main Container */
        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 20px 50px;
        }
        
        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 480px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 35px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .login-header img {
            max-height: 70px;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .login-header h2 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .login-header p {
            margin: 8px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }
        
        .login-body {
            padding: 35px 30px;
        }
        
        /* Tabs */
        .login-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 25px;
        }
        
        .login-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            border-radius: 10px;
            font-weight: 500;
            color: #64748b;
            transition: all 0.3s;
            border: none;
            background: none;
        }
        
        .login-tab.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .login-tab:hover:not(.active) {
            color: var(--primary);
        }
        
        /* Forms */
        .login-form {
            display: none;
        }
        
        .login-form.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .input-icon .form-control {
            padding-left: 45px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        /* Alerts */
        .alert {
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border: none;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* OTP Timer */
        .otp-timer {
            font-size: 0.9rem;
            color: #64748b;
            text-align: center;
            margin-top: 10px;
        }
        
        .otp-timer span {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* Footer */
        .login-footer {
            background: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .login-footer p {
            margin: 0;
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .login-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        /* Social Login */
        .social-login {
            margin-top: 20px;
            text-align: center;
        }
        
        .social-login p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .social-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .social-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-3px);
        }
        
        /* Scroll Indicator */
        .scroll-indicator {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 0.9rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateX(-50%) translateY(0); }
            40% { transform: translateX(-50%) translateY(-10px); }
            60% { transform: translateX(-50%) translateY(-5px); }
        }
        
        /* Features Section */
        .features-section {
            padding: 80px 20px;
            background: rgba(255, 255, 255, 0.95);
            margin-top: 50px;
        }
        
        .feature-card {
            text-align: center;
            padding: 30px;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar-custom {
                padding: 15px 20px;
            }
            
            .navbar-brand span {
                font-size: 1.2rem;
            }
            
            .login-card {
                margin: 20px;
            }
            
            .login-header, .login-body {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>
    
    <!-- Video Background (Optional - uncomment if you have video file) -->
    <!--
    <div class="video-background">
        <video autoplay muted loop playsinline>
            <source src="assets/video/background.mp4" type="video/mp4">
        </video>
    </div>
    <div class="video-overlay" style="background: linear-gradient(135deg, <?= $primaryColor ?>cc 0%, <?= $primaryColor ?>e6 100%);"></div>
    -->
    
    <!-- Navbar -->
    <nav class="navbar-custom">
        <a href="#" class="navbar-brand">
            <?php if (!empty($portalLogo) && file_exists('../uploads/' . $portalLogo)): ?>
                <img src="../uploads/<?= $portalLogo ?>" alt="Logo">
            <?php else: ?>
                <i class="fas fa-wifi" style="font-size: 2rem;"></i>
            <?php endif; ?>
            <span><?= $portalName ?></span>
        </a>
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="#" style="color: white; text-decoration: none; font-weight: 500; padding: 8px 15px; border-radius: 8px; transition: all 0.3s;">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="#" style="color: white; text-decoration: none; font-weight: 500; padding: 8px 15px; border-radius: 8px; transition: all 0.3s;">
                <i class="fas fa-info-circle"></i> About
            </a>
            <a href="captive_portal.php" style="color: white; text-decoration: none; font-weight: 500; padding: 8px 15px; border-radius: 8px; transition: all 0.3s;">
                <i class="fas fa-desktop"></i> Captive Portal
            </a>
            
            <!-- Settings Dropdown -->
            <div class="dropdown">
                <a href="#" style="color: white; text-decoration: none; font-weight: 500; padding: 8px 15px; border-radius: 8px; transition: all 0.3s; cursor: pointer;" onclick="toggleDropdown(event)">
                    <i class="fas fa-cog"></i> Settings <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
                </a>
                <div class="dropdown-menu" id="settingsDropdown">
                    <a href="captive_portal.php">
                        <i class="fas fa-desktop"></i> Captive Portal
                    </a>
                    <a href="../hotspot/admin/settings.php">
                        <i class="fas fa-sms"></i> SMS Gateway
                    </a>
                    <a href="../hotspot/admin/plans.php">
                        <i class="fas fa-tags"></i> Plans & Vouchers
                    </a>
                    <a href="../hotspot/admin/blacklist.php">
                        <i class="fas fa-shield-alt"></i> Access Control
                    </a>
                    <a href="../hotspot/admin/users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../hotspot/admin/index.php">
                        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                    </a>
                </div>
            </div>
            
            <a href="tel:+9779800000000" style="color: white; text-decoration: none; font-weight: 500;">
                <i class="fas fa-phone-alt"></i> Contact
            </a>
        </div>
    </nav>
    
    <!-- Main Container -->
    <div class="main-container">
        <div class="login-card">
            <div class="login-header">
                <?php if (!empty($portalLogo) && file_exists('../uploads/' . $portalLogo)): ?>
                    <img src="../uploads/<?= $portalLogo ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-wifi" style="font-size: 3rem; margin-bottom: 10px;"></i>
                <?php endif; ?>
                <h2><?= $welcomeMessage ?></h2>
                <p>Connect to the internet securely</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <!-- Login Tabs -->
                <div class="login-tabs">
                    <button class="login-tab active" onclick="showTab('voucher')">
                        <i class="fas fa-ticket-alt"></i> Voucher
                    </button>
                    <button class="login-tab" onclick="showTab('sms')">
                        <i class="fas fa-sms"></i> SMS OTP
                    </button>
                    <button class="login-tab" onclick="showTab('password')">
                        <i class="fas fa-key"></i> Password
                    </button>
                </div>
                
                <!-- Voucher Login -->
                <form method="POST" class="login-form active" id="voucher-form">
                    <input type="hidden" name="login_type" value="voucher">
                    <div class="form-group">
                        <label>Username / Voucher</label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" class="form-control" placeholder="Enter username or voucher" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password / PIN</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="Enter password or PIN" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Connect Now
                    </button>
                </form>
                
                <!-- SMS OTP Login -->
                <form method="POST" class="login-form" id="sms-form">
                    <input type="hidden" name="login_type" value="sms">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <div class="input-icon">
                            <i class="fas fa-phone"></i>
                            <input type="tel" name="phone" class="form-control" placeholder="Enter phone number" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-paper-plane"></i> Send OTP
                    </button>
                    <div class="otp-timer" id="otp-timer" style="display: none;">
                        Resend OTP in <span id="timer">60</span> seconds
                    </div>
                </form>
                
                <!-- Password Login -->
                <form method="POST" class="login-form" id="password-form">
                    <input type="hidden" name="login_type" value="password">
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                <p>&copy; 2026 <?= $portalName ?>. All rights reserved.</p>
                <p style="margin-top: 5px;">
                    <a href="#">Terms of Service</a> | <a href="#">Privacy Policy</a>
                </p>
            </div>
        </div>
    </div>
    
    <?php if ($showFeatures): ?>
    <!-- Features Section -->
    <div class="features-section">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: #dbeafe; color: #3b82f6;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h4>High Speed</h4>
                        <p style="color: #64748b;">Blazing fast internet connection for seamless browsing</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: #d1fae5; color: #10b981;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Secure</h4>
                        <p style="color: #64748b;">Protected connection with advanced encryption</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: #fef3c7; color: #f59e0b;">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4>24/7 Support</h4>
                        <p style="color: #64748b;">Round the clock technical support available</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Update tabs
            document.querySelectorAll('.login-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update forms
            document.querySelectorAll('.login-form').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(tabName + '-form').classList.add('active');
        }
        
        // Dropdown Toggle
        function toggleDropdown(event) {
            event.preventDefault();
            event.stopPropagation();
            document.getElementById('settingsDropdown').classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.dropdown');
            if (dropdown && !dropdown.contains(event.target)) {
                document.getElementById('settingsDropdown').classList.remove('show');
            }
        });
        
        // OTP Timer
        let timerInterval;
        function startTimer() {
            let seconds = 60;
            document.getElementById('otp-timer').style.display = 'block';
            document.getElementById('timer').textContent = seconds;
            
            timerInterval = setInterval(() => {
                seconds--;
                document.getElementById('timer').textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(timerInterval);
                    document.getElementById('otp-timer').style.display = 'none';
                }
            }, 1000);
        }
        
        // Scroll Animation
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const shapes = document.querySelectorAll('.shape');
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.5;
                shape.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
