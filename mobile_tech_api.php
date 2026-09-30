<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$branch_id = $_SESSION['branch_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

// Every state-changing action below is a same-session POST from the technician
// app, so it needs CSRF protection.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

if ($action == 'get_jobs') {
    // Fetch assigned tickets
    $query = "
        SELECT t.*, c.full_name, c.address, c.phone, c.username, c.lat, c.lng, c.onu_mac, c.olt_port, c.master_box
        FROM tickets t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.status != 'Closed'
    ";
    
    $params = [];
    if ($role != 'superadmin') {
        $query .= " AND t.branch_id = ?";
        $params[] = (int) $branch_id;
    }

    $query .= " ORDER BY t.created_at DESC";

    $jobs = db_all($conn, $query, $params);

    // Fetch active network faults
    $f_res = db_all($conn, "SELECT * FROM network_faults WHERE is_resolved = 0");
    $faults = $f_res;
    
    echo json_encode(['jobs' => $jobs, 'faults' => $faults]);
}

if ($action == 'send_otp') {
    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
    $otp = sprintf("%06d", random_int(100000, 999999));

    // Clear old OTPs for this ticket
    db_exec($conn, "DELETE FROM job_otps WHERE ticket_id = ?", [$ticket_id]);
    
    // Save new OTP
    $stmt = $conn->prepare("INSERT INTO job_otps (ticket_id, otp) VALUES (?, ?)");
    $stmt->bind_param("is", $ticket_id, $otp);
    
    if ($stmt->execute()) {
        // In real system, call SMS API here
        // simulate_sms($phone, "Your job completion OTP is: $otp");
        // The OTP must never be echoed back to the caller: the technician app
        // is the party being verified, so returning it here would let anyone
        // close a ticket without the customer ever seeing the code.
        echo json_encode(['status' => 'success', 'message' => 'OTP sent to customer']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}

if ($action == 'verify_otp') {
    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
    $otp = (string) ($_POST['otp'] ?? '');

    $rows = db_all($conn, "SELECT * FROM job_otps WHERE ticket_id = ? AND otp = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)", [$ticket_id, $otp]);

    if (count($rows) > 0) {
        // Correct OTP - Close the ticket
        db_exec($conn, "UPDATE tickets SET status = 'Closed' WHERE id = ?", [$ticket_id]);
        db_exec($conn, "DELETE FROM job_otps WHERE ticket_id = ?", [$ticket_id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP.']);
    }
}

if ($action == 'collect_payment') {
    $username = (string) ($_GET['user'] ?? '');
    $u = db_one($conn, "SELECT c.*, p.name as plan_name, p.price FROM customers c JOIN plans p ON c.plan_id = p.id WHERE c.username = ?", [$username]);
    
    if (!$u) die(json_encode(['status' => 'error', 'message' => 'User or Plan not found']));
    
    $amount = $u['price']; // Default 1 month
    // Generate a Fonepay/Khalti style QR link (Simulated)
    // For demo, we use a public QR API to show a "Scan to Pay" image
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . rawurlencode("PAYMENT_FOR_{$username}_AMT_{$amount}");
    
    echo json_encode(['status' => 'success', 'qr_url' => $qr_url, 'amount' => $amount, 'plan' => $u['plan_name']]);
}

if ($action == 'check_updates') {
    // We check for tickets created in the last 1 minute or since last check
    $new_jobs = db_value($conn, "SELECT COUNT(*) FROM tickets WHERE status = 'Open' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)", [], 0);
    $new_faults = db_value($conn, "SELECT COUNT(*) FROM network_faults WHERE is_resolved = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)", [], 0);
    
    echo json_encode(['new_jobs' => (int)$new_jobs, 'new_faults' => (int)$new_faults]);
}

if ($action == 'confirm_collection') {
    $username = (string) ($_POST['user'] ?? '');
    $months = 1; // Default

    // Logic from recharge.php
    $user = db_one($conn, "SELECT * FROM customers WHERE username = ?", [$username]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
        return;
    }

    $plan = db_one($conn, "SELECT * FROM plans WHERE id = ?", [(int) $user['plan_id']]);
    if (!$plan) {
        echo json_encode(['status' => 'error', 'message' => 'Customer has no valid plan']);
        return;
    }

    // Bill the plan price, not a client-supplied amount.
    $amount = (float) $plan['price'];

    $current_expiry = $user['expiry'] ? strtotime($user['expiry']) : 0;
    $today = strtotime(date('Y-m-d'));
    $base = max($current_expiry, $today);
    $validity = (int) ($plan['validity'] ?? 30);
    $new_expiry = date('Y-m-d', $base + ($validity * 86400 * $months));

    // Update DB
    db_exec($conn, "UPDATE customers SET expiry = ?, status = 'active', blocked = 0 WHERE username = ?", [$new_expiry, $username]);
    db_exec($conn, "INSERT INTO invoices (username, amount, months, expiry_date, created_at) VALUES (?, ?, ?, ?, NOW())", [$username, $amount, $months, $new_expiry]);
    db_exec($conn, "INSERT INTO recharge (username, amount, months, created_at) VALUES (?, ?, ?, NOW())", [$username, $amount, $months]);

    echo json_encode(['status' => 'success', 'new_expiry' => $new_expiry, 'amount' => $amount]);
}

if ($action == 'get_tech_stats') {
    $admin_id = (int) ($_SESSION['user_id'] ?? 0);

    // Jobs done today
    $today_jobs = db_value($conn, "SELECT COUNT(*) FROM tickets WHERE admin_id = ? AND status = 'Closed' AND updated_at >= CURDATE()", [$admin_id], 0);

    // Weekly performance (Jobs per day for last 7 days)
    $weekly = db_all($conn, "
        SELECT DATE(updated_at) as day, COUNT(*) as count
        FROM tickets
        WHERE admin_id = ? AND status = 'Closed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(updated_at)
    ", [$admin_id]);
    
    echo json_encode(['today' => (int)$today_jobs, 'weekly' => $weekly]);
}

if ($action == 'save_signature') {
    $id = (int) ($_POST['id'] ?? 0);
    $sig = (string) ($_POST['signature'] ?? '');
    $stmt = $conn->prepare("UPDATE tickets SET signature = ? WHERE id = ?");
    $stmt->bind_param("si", $sig, $id);
    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

if ($action == 'save_speedtest') {
    $id = (int) ($_POST['id'] ?? 0);
    $dl = (float) ($_POST['download'] ?? 0);
    $ul = (float) ($_POST['upload'] ?? 0);
    $stmt = $conn->prepare("UPDATE tickets SET download_speed = ?, upload_speed = ? WHERE id = ?");
    $stmt->bind_param("ddi", $dl, $ul, $id);
    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

if ($action == 'update_status') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');

    // Restrict to the known workflow states so the column cannot be set to
    // arbitrary caller-supplied text.
    $allowed = ['Open', 'In Progress', 'Pending', 'Closed'];
    if (!in_array($status, $allowed, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
        return;
    }

    db_exec($conn, "UPDATE tickets SET status = ? WHERE id = ?", [$status, $id]);
    echo json_encode(['status' => 'success']);
}
?>
