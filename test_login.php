<?php
include 'config.php';

$username = 'admin';
$password = 'admin123';

$stmt = $conn->prepare("SELECT id, username, password, role, branch_id FROM admins WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    echo "User found: " . $row['username'] . "<br>";
    echo "Password verify: " . (password_verify($password, $row['password']) ? "SUCCESS" : "FAIL") . "<br>";
    if (password_verify($password, $row['password'])) {
        echo "Password matches!";
    } else {
        echo "Password does NOT match!";
        echo "<br>Stored hash: " . $row['password'];
    }
} else {
    echo "User not found!";
}
?>