<?php
include 'config.php';
include 'includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$branch_id = $_SESSION['branch_id'] ?? 0;
$role = $_SESSION['role'];

if ($action == 'get_jobs') {
    // Fetch assigned tickets
    $query = "
        SELECT t.*, c.full_name, c.address, c.phone, c.username, c.lat, c.lng, c.onu_mac, c.olt_port, c.master_box
        FROM tickets t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.status != 'Closed'
    ";
    
    if ($role != 'superadmin') {
        $query .= " AND t.branch_id = '$branch_id'";
    }
    
    $query .= " ORDER BY t.created_at DESC";
    
    $res = $conn->query($query);
    $jobs = $res->fetch_all(MYSQLI_ASSOC);
    
    // Fetch active network faults
    $f_res = $conn->query("SELECT * FROM network_faults WHERE is_resolved = 0");
    $faults = $f_res->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['jobs' => $jobs, 'faults' => $faults]);
}

if ($action == 'send_otp') {
    $ticket_id = $_POST['ticket_id'];
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    
    // Clear old OTPs for this ticket
    $conn->query("DELETE FROM job_otps WHERE ticket_id = $ticket_id");
    
    // Save new OTP
    $stmt = $conn->prepare("INSERT INTO job_otps (ticket_id, otp) VALUES (?, ?)");
    $stmt->bind_param("is", $ticket_id, $otp);
    
    if ($stmt->execute()) {
        // In real system, call SMS API here
        // simulate_sms($phone, "Your job completion OTP is: $otp");
        echo json_encode(['status' => 'success', 'message' => 'OTP sent to customer (Simulated)', 'debug_otp' => $otp]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}

if ($action == 'verify_otp') {
    $ticket_id = $_POST['ticket_id'];
    $otp = $_POST['otp'];
    
    $res = $conn->query("SELECT * FROM job_otps WHERE ticket_id = $ticket_id AND otp = '$otp' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    
    if ($res->num_rows > 0) {
        // Correct OTP - Close the ticket
        $conn->query("UPDATE tickets SET status = 'Closed' WHERE id = $ticket_id");
        $conn->query("DELETE FROM job_otps WHERE ticket_id = $ticket_id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP.']);
    }
}

if ($action == 'collect_payment') {
    $username = $_GET['user'];
    $res = $conn->query("SELECT c.*, p.name as plan_name, p.price FROM customers c JOIN plans p ON c.plan_id = p.id WHERE c.username = '$username'");
    $u = $res->fetch_assoc();
    
    if (!$u) die(json_encode(['status' => 'error', 'message' => 'User or Plan not found']));
    
    $amount = $u['price']; // Default 1 month
    // Generate a Fonepay/Khalti style QR link (Simulated)
    // For demo, we use a public QR API to show a "Scan to Pay" image
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=PAYMENT_FOR_".$username."_AMT_".$amount;
    
    echo json_encode(['status' => 'success', 'qr_url' => $qr_url, 'amount' => $amount, 'plan' => $u['plan_name']]);
}

if ($action == 'check_updates') {
    // We check for tickets created in the last 1 minute or since last check
    $new_jobs = $conn->query("SELECT COUNT(*) as total FROM tickets WHERE status = 'Open' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)")->fetch_assoc()['total'];
    $new_faults = $conn->query("SELECT COUNT(*) as total FROM network_faults WHERE is_resolved = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)")->fetch_assoc()['total'];
    
    echo json_encode(['new_jobs' => (int)$new_jobs, 'new_faults' => (int)$new_faults]);
}

if ($action == 'confirm_collection') {
    $username = $_POST['user'];
    $amount = $_POST['amount'];
    $months = 1; // Default
    
    // Logic from recharge.php
    $user = $conn->query("SELECT * FROM customers WHERE username='$username'")->fetch_assoc();
    $plan = $conn->query("SELECT * FROM plans WHERE id='{$user['plan_id']}'")->fetch_assoc();
    
    $current_expiry = strtotime($user['expiry']);
    $today = strtotime(date('Y-m-d'));
    $base = max($current_expiry, $today);
    $new_expiry = date('Y-m-d', $base + ($plan['validity'] * 86400 * $months));
    
    // Update DB
    $conn->query("UPDATE customers SET expiry='$new_expiry', status='active', blocked=0 WHERE username='$username'");
    $conn->query("INSERT INTO invoices (username, amount, months, expiry_date, created_at) VALUES ('$username', $amount, $months, '$new_expiry', NOW())");
    $conn->query("INSERT INTO recharge (username, amount, months, created_at) VALUES ('$username', $amount, $months, NOW())");
    
    echo json_encode(['status' => 'success', 'new_expiry' => $new_expiry]);
}

if ($action == 'get_tech_stats') {
    $admin_id = $_SESSION['user_id'];
    
    // Jobs done today
    $today_jobs = $conn->query("SELECT COUNT(*) as total FROM tickets WHERE admin_id = $admin_id AND status = 'Closed' AND updated_at >= CURDATE()")->fetch_assoc()['total'];
    
    // Weekly performance (Jobs per day for last 7 days)
    $weekly = $conn->query("
        SELECT DATE(updated_at) as day, COUNT(*) as count 
        FROM tickets 
        WHERE admin_id = $admin_id AND status = 'Closed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(updated_at)
    ")->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['today' => (int)$today_jobs, 'weekly' => $weekly]);
}

if ($action == 'save_signature') {
    $id = $_POST['id'];
    $sig = $_POST['signature'];
    $stmt = $conn->prepare("UPDATE tickets SET signature = ? WHERE id = ?");
    $stmt->bind_param("si", $sig, $id);
    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

if ($action == 'save_speedtest') {
    $id = $_POST['id'];
    $dl = $_POST['download'];
    $ul = $_POST['upload'];
    $stmt = $conn->prepare("UPDATE tickets SET download_speed = ?, upload_speed = ? WHERE id = ?");
    $stmt->bind_param("ddi", $dl, $ul, $id);
    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

if ($action == 'update_status') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $conn->query("UPDATE tickets SET status = '$status' WHERE id = $id");
    echo json_encode(['status' => 'success']);
}
?>
