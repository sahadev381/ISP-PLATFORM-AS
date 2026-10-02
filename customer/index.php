<?php
include __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/csrf.php';
// session_start(); removed as it's in config.php

$error = '';

if (isset($_POST['login'])) {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $user = db_one($conn, "SELECT * FROM customers WHERE username = ? LIMIT 1", [$username]);

    // Always run a verify so the response time does not reveal whether the
    // account exists, and never fall back to a plaintext comparison.
    $hash = $user['password'] ?? '$2y$10$usesomesillystringforsalt0000000000000000000000000000000';

    if ($user && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION['customer_id']   = $user['id'];
        $_SESSION['customer_user'] = $user['username'];
        header("Location: dashboard.php");
        exit;
    }

    // Generic message: do not leak which customers exist.
    $error = "Invalid username or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - ISP Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; text-align: center; }
        .logo { font-size: 28px; font-weight: 800; color: #3b82f6; margin-bottom: 30px; }
        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
        input { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box; outline: none; }
        input:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        .btn-login { width: 100%; padding: 12px; background: #3b82f6; color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-login:hover { background: #2563eb; }
        .alert { background: #fee2e2; color: #ef4444; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo"><i class="fa fa-wifi"></i> ISP Portal</div>
        <h3>Customer Login</h3>
        <?php if($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login" class="btn-login">Sign In</button>
        </form>
        <p style="margin-top: 25px; color: #64748b; font-size: 13px;">Contact support if you cannot log in.</p>
    </div>
</body>
</html>
