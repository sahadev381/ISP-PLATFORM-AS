<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../user-config.php';

$error = '';

if (isset($_POST['login'])) {

    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';

    $q = $conn->prepare("SELECT id, password FROM customers WHERE username=?");
    $q->bind_param("s", $u);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();

    if ($res && password_verify($p, $res['password'])) {
        $_SESSION['customer_id'] = $res['id'];
        header("Location: dashboard.php");
        exit; // VERY IMPORTANT
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Roboto', sans-serif; }
        body {
            background: linear-gradient(135deg, #6B73FF, #000DFF);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 40px 50px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            width: 350px;
            text-align: center;
            color: #fff;
            animation: fadeIn 1s ease forwards;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-30px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        h1 { margin-bottom: 30px; font-size: 2em; letter-spacing: 1px; }
        .input-box { margin-bottom: 20px; position: relative; }
        input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border-radius: 10px;
            border: none;
            outline: none;
            font-size: 1em;
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        input::placeholder { color: rgba(255,255,255,0.7); }
        .input-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
        }
        button {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: none;
            background: #ff6b6b;
            color: #fff;
            font-size: 1em;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover { background: #ff4757; }
        .footer-text { margin-top: 20px; font-size: 0.85em; color: rgba(255,255,255,0.7); }
        .error { background:#e74c3c; color:#fff; padding:10px; border-radius:10px; margin-bottom:15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Customer Login</h1>

        <?php if($error){ echo "<div class='error'>$error</div>"; } ?>

        <form method="POST">
            <div class="input-box">
                <input type="text" name="username" placeholder="Username" required>
                <i class="fa fa-user"></i>
            </div>
            <div class="input-box">
                <input type="password" name="password" placeholder="Password" required>
                <i class="fa fa-lock"></i>
            </div>
            <button type="submit" name="login">Login</button>
        </form>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> ISP System. All rights reserved.
        </div>
    </div>
</body>
</html>
