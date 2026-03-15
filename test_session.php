<?php
session_start();
echo "Session ID: " . session_id() . "<br>";

if (isset($_GET['login'])) {
    include 'config.php';
    include 'includes/security.php';
    
    $security = new Security($conn);
    $username = 'admin';
    $password = 'admin123';
    
    $stmt = $conn->prepare("SELECT id, username, password, role, branch_id FROM admins WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $security->recordLoginAttempt($username, true);
            $security->logActivity($row['id'], $username, 'login', 'Admin login successful');
            
            $_SESSION['user_id']   = (int)$row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role']     = $row['role'] ?? '';
            $_SESSION['branch_id']= $row['branch_id'] ?? null;
            $_SESSION['login_time'] = time();
            
            echo "Login successful! Session variables set.<br>";
            echo "User ID: " . $_SESSION['user_id'] . "<br>";
            echo "Username: " . $_SESSION['username'] . "<br>";
        }
    }
} else if (isset($_GET['test'])) {
    if (isset($_SESSION['user_id'])) {
        echo "Session valid!<br>";
        echo "User ID: " . $_SESSION['user_id'] . "<br>";
        echo "Username: " . $_SESSION['username'] . "<br>";
    } else {
        echo "Session invalid!";
    }
} else {
    echo "Click <a href='?login'>login</a> to test.";
}
?>