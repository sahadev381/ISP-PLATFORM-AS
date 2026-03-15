<?php
$base_path = './';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$active = "users";

/* =========================
   CHECK USERNAME
========================= */
if (!isset($_GET['user']) || empty($_GET['user'])) {
    die("No customer username provided");
}

$username = $conn->real_escape_string($_GET['user']);

/* =========================
   FETCH CURRENT DATA
========================= */
$res = $conn->query("
    SELECT u.*, p.name as plan_name 
    FROM customers u 
    LEFT JOIN plans p ON u.plan_id = p.id 
    WHERE u.username='$username'
");
$row = $res->fetch_assoc();

if (!$row) {
    die("User not found");
}

$page_title = "Update Expiry: " . $username;

/* =========================
   HANDLE FORM SUBMIT
========================= */
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['expiry'])) {
        $msg = "<div class='alert error'>Please select an expiry date.</div>";
    } else {
        $expiry = $conn->real_escape_string($_POST['expiry']); 
        
        // Convert for RADIUS (Expiration attribute)
        $timestamp = strtotime($expiry . ' 23:59:59');
        $radius_expiry = date('d M Y H:i:s', $timestamp);

        // 1. Update customers table
        $conn->query("UPDATE customers SET expiry='$expiry', status='active' WHERE username='$username'");

        // 2. Update radcheck (Expiration)
        $check = $conn->query("SELECT id FROM radcheck WHERE username='$username' AND attribute='Expiration'");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE radcheck SET value='$radius_expiry' WHERE username='$username' AND attribute='Expiration'");
        } else {
            $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Expiration', ':=', '$radius_expiry')");
        }

        // 3. Optional: Add to recharge log if you have one
        // $conn->query("INSERT INTO recharge (username, amount, created_at) VALUES ('$username', '0', NOW())");

        echo "<script>alert('Expiry updated successfully for $username'); window.location='user_view.php?username=$username';</script>";
        exit;
    }
}

include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .expiry-container { padding: 30px; max-width: 800px; margin: 0 auto; }
    .expiry-card { background: #fff; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
    .card-header { background: #f8fafc; padding: 25px; border-bottom: 1px solid #f1f5f9; text-align: center; }
    .card-header i { font-size: 40px; color: #3b82f6; margin-bottom: 15px; }
    .card-header h2 { margin: 0; font-size: 22px; color: #1e293b; }
    
    .card-body { padding: 30px; }
    .user-info-mini { background: #f1f5f9; padding: 15px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-around; text-align: center; }
    .info-item label { display: block; font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
    .info-item span { font-weight: 700; color: #1e293b; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: #475569; }
    .date-input { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 16px; transition: all 0.3s; color: #1e293b; }
    .date-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    
    .btn-update { width: 100%; padding: 15px; background: #3b82f6; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .btn-update:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3); }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .error { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
</style>

<div class="expiry-container">
    <div class="expiry-card">
        <div class="card-header">
            <i class="fa fa-calendar-check"></i>
            <h2>Extend Subscription</h2>
            <p style="color: #64748b; margin-top: 5px;">Manually update expiry for <strong><?= htmlspecialchars($username) ?></strong></p>
        </div>
        
        <div class="card-body">
            <?= $msg ?>
            
            <div class="user-info-mini">
                <div class="info-item">
                    <label>Current Plan</label>
                    <span><?= htmlspecialchars($row['plan_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <span style="color: <?= ($row['status']=='active') ? '#10b981' : '#ef4444' ?>; text-transform: uppercase;"><?= htmlspecialchars($row['status']) ?></span>
                </div>
                <div class="info-item">
                    <label>Current Expiry</label>
                    <span style="color: #ef4444;"><?= date('M d, Y', strtotime($row['expiry'])) ?></span>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="expiry">New Expiry Date</label>
                    <input type="date" name="expiry" id="expiry" class="date-input" required value="<?= htmlspecialchars($row['expiry']); ?>">
                    <small style="color: #94a3b8; display: block; margin-top: 8px;">The service will expire at the end of this day (23:59:59).</small>
                </div>
                
                <button type="submit" class="btn-update">
                    <i class="fa fa-save"></i> Save Changes
                </button>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="user_view.php?username=<?= urlencode($username) ?>" style="text-decoration: none; color: #64748b; font-size: 14px; font-weight: 500;">
                        <i class="fa fa-arrow-left"></i> Back to Profile
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>
