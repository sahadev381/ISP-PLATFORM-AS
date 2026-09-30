<?php
include '../config.php';
require_once __DIR__ . '/../includes/csrf.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    csrf_check();

    $full_name        = trim($_POST['full_name'] ?? '');
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $plan_id          = (int) ($_POST['plan_id'] ?? 0);
    $password         = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    // Validation
    if ($username === '' || $full_name === '') {
        $error = "Name and username are required";
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
        $error = "Username may only contain letters, numbers, dot, dash and underscore (3-32 chars)";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif (!$plan_id) {
        $error = "Please choose a plan";
    } else {
        // Check if username exists
        if (db_one($conn, "SELECT username FROM customers WHERE username = ?", [$username])) {
            $error = "Username already exists";
        } elseif ($email !== '' && db_one($conn, "SELECT email FROM customers WHERE email = ?", [$email])) {
            $error = "Email already registered";
        } else {
            // Get plan details
            $plan = db_one($conn, "SELECT * FROM plans WHERE id = ?", [$plan_id]);

            if (!$plan) {
                $error = "Selected plan is not available";
            } else {
                // Calculate expiry
                $validity = $plan['validity'] ?? 30;
                $expiry   = date('Y-m-d', strtotime("+$validity days"));

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $conn->begin_transaction();
                try {
                    // Insert customer
                    db_exec($conn, "
                        INSERT INTO customers (username, password, full_name, email, phone, address, plan_id, expiry, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ", [$username, $hashed_password, $full_name, $email, $phone, $address, $plan_id, $expiry]);

                    // Create first invoice
                    db_exec($conn, "
                        INSERT INTO invoices (username, amount, expiry_date, status, admin)
                        VALUES (?, ?, ?, 'pending', 'system')
                    ", [$username, $plan['price'], $expiry]);

                    $conn->commit();
                    $success = "Registration successful! Please login.";
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Customer registration failed: ' . $e->getMessage());
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}

// Get available plans
$plans = $conn->query("SELECT * FROM plans ORDER BY price ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ISP Portal</title>
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
            --success: #10b981;
            --error: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .register-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .register-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 48px;
            color: var(--primary);
        }
        
        .logo h2 {
            color: var(--text-main);
            font-size: 28px;
            margin-top: 10px;
        }
        
        .logo p {
            color: var(--text-muted);
        }
        
        h1 {
            color: var(--text-main);
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .subtitle {
            color: var(--text-muted);
            margin-bottom: 25px;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-main);
            transition: 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .btn-register {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            background: var(--primary-dark);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 25px;
        }
        
        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .plan-info {
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .plan-info h4 {
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .plan-info p {
            color: var(--text-muted);
            font-size: 13px;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo">
                <i class="fa fa-wifi"></i>
                <h2>ISP Portal</h2>
                <p>New Customer Registration</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                    <br><br>
                    <a href="login.php" class="btn-register" style="display: inline-block; width: auto; padding: 12px 24px;">Go to Login</a>
                </div>
            <?php else: ?>
            
            <h1>Create Account</h1>
            <p class="subtitle">Fill in your details to get started</p>
            
            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Select Plan *</label>
                    <select name="plan_id" class="form-control" required>
                        <option value="">-- Select Plan --</option>
                        <?php while($plan = $plans->fetch_assoc()): ?>
                            <option value="<?= e($plan['id']) ?>">
                                <?= htmlspecialchars($plan['name']) ?> - <?= htmlspecialchars($plan['speed']) ?> - NPR <?= number_format($plan['price']) ?>/<?= e($plan['validity']) ?> days
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="plan-info">
                    <h4><i class="fa fa-info-circle"></i> After Registration</h4>
                    <p>Your account will be created with selected plan. An invoice will be generated for payment. Once payment is confirmed, your service will be activated.</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                
                <button type="submit" name="register" class="btn-register">
                    <i class="fa fa-user-plus"></i> Register Now
                </button>
            </form>
            
            <?php endif; ?>
            
            <div class="footer-links">
                <a href="login.php">
                    <i class="fa fa-sign-in-alt"></i> Already have an account? Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
