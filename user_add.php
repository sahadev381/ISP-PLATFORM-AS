<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$base_path = './';
include 'config.php';
include 'includes/auth.php';

$page_title = "Add New Customer";
$active = "users";

/* =========================
   FETCH PLANS & BRANCHES
========================= */
$plans = $conn->query("SELECT * FROM plans");
$branches = $conn->query("SELECT * FROM branches WHERE status='active'");

/* =========================
   HANDLE FORM SUBMIT
========================= */
$msg = '';
$msg_type = '';

if (isset($_POST['add'])) {
    $username   = trim($_POST['username']);
    $password   = trim($_POST['password']);
    $full_name  = trim($_POST['full_name']);
    $phone      = trim($_POST['phone']);
    $email      = trim($_POST['email']);
    $address    = trim($_POST['address']);
    $lat        = !empty($_POST['lat']) ? $_POST['lat'] : NULL;
    $lng        = !empty($_POST['lng']) ? $_POST['lng'] : NULL;
    $plan_id    = (int)$_POST['plan_id'];
    $branch_id  = $_POST['branch_id'] ?: null;
    $expiry     = $_POST['expiry'];
    $onu_serial = trim($_POST['onu_serial']);
    $vlan       = (int)$_POST['vlan'];
    
    // FTTH Fields
    $olt        = trim($_POST['olt']);
    $olt_port   = (int)$_POST['olt_port'];
    $master_box = trim($_POST['master_box']);
    $db_box     = trim($_POST['db_box']);
    $db_port    = (int)$_POST['db_port'];

    // Fetch plan
    $plan_stmt = $conn->prepare("SELECT name, speed FROM plans WHERE id=?");
    $plan_stmt->bind_param("i", $plan_id);
    $plan_stmt->execute();
    $plan = $plan_stmt->get_result()->fetch_assoc();
    
    if (!$plan) {
        $msg = "Invalid plan selected.";
        $msg_type = "error";
    } else {
        $plan_name = $plan['name'];
        $speed = $plan['speed'];
        $expiry_radius = date("d M Y", strtotime($expiry));

        // Auto-generate Wi-Fi credentials
        $wifi_ssid = "ISP_" . $username;
        $wifi_password = substr(md5($username . time()), 0, 10);

        $conn->begin_transaction();
        try {
            // RADIUS CONFIG
            $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$username','Mikrotik-Rate-Limit',':=','$speed')");
            
            $stmt1 = $conn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
            $stmt1->bind_param("ss", $username, $password);
            $stmt1->execute();

            $stmt2 = $conn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)");
            $stmt2->bind_param("ss", $username, $expiry_radius);
            $stmt2->execute();

            $stmt3 = $conn->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
            $stmt3->bind_param("ss", $username, $plan_name);
            $stmt3->execute();

            // CUSTOMER TABLE
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt4 = $conn->prepare("INSERT INTO customers (username, password, full_name, phone, email, address, lat, lng, plan_id, branch_id, expiry, status, onu_serial, vlan, wifi_ssid, wifi_password, created_at, olt, olt_port, master_box, db_box, db_port) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)");
            $stmt4->bind_param("ssssssddiisssssisssi", $username, $hashed, $full_name, $phone, $email, $address, $lat, $lng, $plan_id, $branch_id, $expiry, $onu_serial, $vlan, $wifi_ssid, $wifi_password, $olt, $olt_port, $master_box, $db_box, $db_port);
            $stmt4->execute();

            $conn->commit();
            header("Location: users.php?msg=added");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .form-container { padding: 25px; max-width: 1000px; margin: 0 auto; }
    .card { background: #fff; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
    .card-header { background: #f8fafc; padding: 20px 25px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; }
    .card-header i { color: #3b82f6; font-size: 20px; }
    .card-header h2 { margin: 0; font-size: 18px; color: #1e293b; font-weight: 600; }
    
    .card-body { padding: 30px; }
    .form-section { margin-bottom: 30px; }
    .section-title { font-size: 14px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
    
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group.full-width { grid-column: span 2; }
    
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; }
    .input-wrapper { position: relative; }
    .input-wrapper i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; }
    
    .form-control { width: 100%; padding: 10px 12px 10px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.2s; color: #1e293b; }
    .form-control:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    textarea.form-control { min-height: 80px; padding-top: 10px; }
    
    .btn-submit { background: #3b82f6; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; width: 100%; justify-content: center; margin-top: 20px; }
    .btn-submit:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(37, 99, 235, 0.2); }
    
    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
    .alert.error { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
    
    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } }
</style>

<div class="form-container">
    <?php if($msg): ?>
        <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fa fa-user-plus"></i>
            <h2>Register New Customer</h2>
        </div>
        
        <div class="card-body">
            <form method="POST">
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-info-circle"></i> Personal Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <div class="input-wrapper">
                                <i class="fa fa-user"></i>
                                <input type="text" name="full_name" class="form-control" placeholder="E.g. Ram Bahadur" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <div class="input-wrapper">
                                <i class="fa fa-phone"></i>
                                <input type="text" name="phone" class="form-control" placeholder="98XXXXXXXX" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-wrapper">
                                <i class="fa fa-envelope"></i>
                                <input type="email" name="email" class="form-control" placeholder="example@mail.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Installation Address</label>
                            <div class="input-wrapper">
                                <i class="fa fa-map-marker-alt"></i>
                                <input type="text" name="address" class="form-control" placeholder="City, Ward No, Street">
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Geo Location (For Map)</label>
                            <div style="display: flex; gap: 10px;">
                                <div class="input-wrapper" style="flex: 1;">
                                    <i class="fa fa-map-pin"></i>
                                    <input type="text" name="lat" id="lat" class="form-control" placeholder="Latitude (e.g. 27.7172)">
                                </div>
                                <div class="input-wrapper" style="flex: 1;">
                                    <i class="fa fa-map-pin"></i>
                                    <input type="text" name="lng" id="lng" class="form-control" placeholder="Longitude (e.g. 85.3240)">
                                </div>
                                <button type="button" onclick="getLocation()" class="btn-submit" style="width: auto; margin-top: 0; padding: 10px 15px;">
                                    <i class="fa fa-crosshairs"></i> Get Location
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FTTH & Network Inventory -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-network-wired"></i> FTTH & Network Inventory</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select OLT</label>
                            <div class="input-wrapper">
                                <i class="fa fa-server"></i>
                                <input type="text" name="olt" class="form-control" placeholder="OLT Name or ID">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>OLT Port</label>
                            <div class="input-wrapper">
                                <i class="fa fa-plug"></i>
                                <input type="number" name="olt_port" class="form-control" placeholder="Port Number">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Master Splitter / Box</label>
                            <div class="input-wrapper">
                                <i class="fa fa-box"></i>
                                <input type="text" name="master_box" class="form-control" placeholder="Master Box Name/ID">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Distribution Box (DB)</label>
                            <div class="input-wrapper">
                                <i class="fa fa-boxes"></i>
                                <input type="text" name="db_box" class="form-control" placeholder="DB Name or ID">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>DB Port</label>
                            <div class="input-wrapper">
                                <i class="fa fa-sign-in-alt"></i>
                                <input type="number" name="db_port" class="form-control" placeholder="DB Port Number">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account & Authentication -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-key"></i> PPP Credentials</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>PPP Username</label>
                            <div class="input-wrapper">
                                <i class="fa fa-id-card"></i>
                                <input type="text" name="username" class="form-control" placeholder="Unique username" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>PPP Password</label>
                            <div class="input-wrapper">
                                <i class="fa fa-lock"></i>
                                <input type="password" name="password" class="form-control" placeholder="Secret password" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Details -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-wifi"></i> Service Configuration</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Internet Plan</label>
                            <div class="input-wrapper">
                                <i class="fa fa-list-ul" style="z-index: 5;"></i>
                                <select name="plan_id" class="form-control" required style="padding-left: 38px;">
                                    <option value="">Select a plan</option>
                                    <?php while($p = $plans->fetch_assoc()): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['speed'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <div class="input-wrapper">
                                <i class="fa fa-calendar-alt"></i>
                                <input type="date" name="expiry" class="form-control" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Assign Branch</label>
                            <div class="input-wrapper">
                                <i class="fa fa-sitemap" style="z-index: 5;"></i>
                                <select name="branch_id" class="form-control" style="padding-left: 38px;">
                                    <option value="">Main Branch</option>
                                    <?php while($b = $branches->fetch_assoc()): ?>
                                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>VLAN ID</label>
                            <div class="input-wrapper">
                                <i class="fa fa-network-wired"></i>
                                <input type="number" name="vlan" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>ONU Serial Number (TR-069)</label>
                            <div class="input-wrapper">
                                <i class="fa fa-microchip"></i>
                                <input type="text" name="onu_serial" class="form-control" placeholder="Enter ONU Serial for Auto-Configuration">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="add" class="btn-submit">
                    <i class="fa fa-save"></i> Register Customer & Activate
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition, showError);
    } else { 
        alert("Geolocation is not supported by this browser.");
    }
}

function showPosition(position) {
    document.getElementById("lat").value = position.coords.latitude;
    document.getElementById("lng").value = position.coords.longitude;
}

function showError(error) {
    switch(error.code) {
        case error.PERMISSION_DENIED:
            alert("User denied the request for Geolocation.");
            break;
        case error.POSITION_UNAVAILABLE:
            alert("Location information is unavailable.");
            break;
        case error.TIMEOUT:
            alert("The request to get user location timed out.");
            break;
        case error.UNKNOWN_ERROR:
            alert("An unknown error occurred.");
            break;
    }
}
</script>
