<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'includes/auth.php';

$page_title = "Edit Customer";
$active = "users";

/* =========================
   HELPER: SEND TO GENIEACS
========================= */
function sendToACS($deviceId, $data) {
    $ch = curl_init("http://127.0.0.1:7558/devices/$deviceId/tasks");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

/* =========================
   FETCH USER
========================= */
$username = $_GET['user'] ?? '';
if (!$username) die("No user specified.");

$stmt = $conn->prepare("SELECT * FROM customers WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("User not found.");

$user = $result->fetch_assoc();

// Fetch current cleartext password from radcheck
$rad_pass_res = $conn->query("SELECT value FROM radcheck WHERE username='$username' AND attribute='Cleartext-Password' LIMIT 1");
$current_rad_pass = ($rad_pass_res->num_rows > 0) ? $rad_pass_res->fetch_assoc()['value'] : 'N/A';

/* =========================
   FETCH PLANS & BRANCHES
========================= */
$plans_list = $conn->query("SELECT * FROM plans")->fetch_all(MYSQLI_ASSOC);
$branches_list = $conn->query("SELECT * FROM branches WHERE status='active'")->fetch_all(MYSQLI_ASSOC);

/* =========================
   HANDLE FORM SUBMIT
========================= */
$msg = '';
$msg_type = '';

if (isset($_POST['save_user'])) {

    $username_new = trim($_POST['username']);
    $full_name    = trim($_POST['full_name']);
    $phone        = trim($_POST['phone']);
    $address      = trim($_POST['address']);
    $lat          = !empty($_POST['lat']) ? $_POST['lat'] : NULL;
    $lng          = !empty($_POST['lng']) ? $_POST['lng'] : NULL;
    $plan_id      = $_POST['plan_id'] ?: null;
    $branch_id    = $_POST['branch_id'] ?: null;
    $password     = $_POST['password'] ?? '';
    $onu_serial   = trim($_POST['onu_serial']);
    $vlan	  = trim($_POST['vlan']);
    
    // FTTH Fields
    $olt        = trim($_POST['olt']);
    $olt_port   = (int)$_POST['olt_port'];
    $master_box = trim($_POST['master_box']);
    $db_box     = trim($_POST['db_box']);
    $db_port    = (int)$_POST['db_port'];

    $wifi_ssid     = $username_new;
    $wifi_password = $password ?: $user['wifi_password'];

    $conn->begin_transaction();

    try {

        /* ===== Update Customer Table ===== */
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                UPDATE customers
                SET username=?, full_name=?, phone=?, address=?, lat=?, lng=?,
                    plan_id=?, branch_id=?, password=?, 
                    onu_serial=?, vlan=?, wifi_ssid=?, wifi_password=?,
                    olt=?, olt_port=?, master_box=?, db_box=?, db_port=?
                WHERE username=?
            ");
            $stmt->bind_param(
                "ssssddiissssssisssi",
                $username_new, $full_name, $phone, $address, $lat, $lng,
                $plan_id, $branch_id, $hashed,
                $onu_serial, $vlan, $wifi_ssid, $wifi_password,
                $olt, $olt_port, $master_box, $db_box, $db_port,
                $username
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE customers
                SET username=?, full_name=?, phone=?, address=?, lat=?, lng=?,
                    plan_id=?, branch_id=?, 
                    onu_serial=?, vlan=?, wifi_ssid=?,
                    olt=?, olt_port=?, master_box=?, db_box=?, db_port=?
                WHERE username=?
            ");
            $stmt->bind_param(
                "ssssddiisssisssis",
                $username_new, $full_name, $phone, $address, $lat, $lng,
                $plan_id, $branch_id,
                $onu_serial, $vlan, $wifi_ssid,
                $olt, $olt_port, $master_box, $db_box, $db_port,
                $username
            );
        }

        $stmt->execute();

        /* ===== Update RADIUS (PPP) ===== */
        if (!empty($password)) {
            $conn->query("
                INSERT INTO radcheck (username, attribute, op, value) 
                VALUES ('$username_new', 'Cleartext-Password', ':=', '$password')
                ON DUPLICATE KEY UPDATE value='$password', username='$username_new'
            ");
        }

        if ($username !== $username_new) {
            $conn->query("UPDATE radcheck SET username='$username_new' WHERE username='$username'");
            $conn->query("UPDATE radreply SET username='$username_new' WHERE username='$username'");
            $conn->query("UPDATE radusergroup SET username='$username_new' WHERE username='$username'");
        }

        /* ===== TR-069 DEVICE UPDATE ===== */
        $deviceId = $user['tr069_device_id'];

        if ($deviceId) {
            // Update PPPoE & WiFi on physical device
            sendToACS($deviceId, [
                "name" => "setParameterValues",
                "parameterValues" => [
                    ["InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username", $username_new, "xsd:string"],
                    ["InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.Password", $wifi_password, "xsd:string"],
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", $wifi_ssid, "xsd:string"],
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase", $wifi_password, "xsd:string"]
                ]
            ]);
        }

        $conn->commit();
        $msg = "Customer updated successfully!";
        $msg_type = "success";

        // Reload user
        $stmt = $conn->prepare("SELECT * FROM customers WHERE username=? LIMIT 1");
        $stmt->bind_param("s", $username_new);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
        $msg_type = "error";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .edit-container { padding: 25px; max-width: 1000px; margin: 0 auto; }
    .form-card { background: #fff; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; margin-bottom: 30px; }
    .card-header { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; background: #fafbfc; }
    .card-header i { color: #3b82f6; font-size: 20px; }
    .card-header h2 { margin: 0; font-size: 18px; color: #1e293b; font-weight: 600; }
    
    .card-body { padding: 30px; }
    
    .form-section { margin-bottom: 35px; }
    .section-title { font-size: 14px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
    
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; }
    .form-control { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.2s; outline: none; background: #f8fafc; }
    .form-control:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    
    .input-wrapper { position: relative; }
    .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .input-wrapper .form-control { padding-left: 45px; }
    
    .btn-submit { background: #3b82f6; color: #fff; padding: 12px 30px; border-radius: 10px; border: none; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 10px; }
    .btn-submit:hover { background: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); }
    
    .btn-cancel { background: #f1f5f9; color: #475569; padding: 12px 30px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 10px; }
    .btn-cancel:hover { background: #e2e8f0; }

    .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; display: flex; align-items: center; gap: 12px; }
    .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }

    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>

<div class="edit-container">
    
    <?php if($msg): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <i class="fa <?= $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="card-header">
            <i class="fa fa-user-edit"></i>
            <h2>Modify Customer Details</h2>
        </div>
        
        <div class="card-body">
            <form method="post">
                
                <!-- Section 1: Identity -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-id-card"></i> Basic Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username (System ID)</label>
                            <div class="input-wrapper">
                                <i class="fa fa-user"></i>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <div class="input-wrapper">
                                <i class="fa fa-tag"></i>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <div class="input-wrapper">
                                <i class="fa fa-phone"></i>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Installation Address</label>
                            <div class="input-wrapper">
                                <i class="fa fa-map-marker-alt"></i>
                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Location & Mapping -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-globe"></i> Geo Location Data</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: flex-end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Latitude</label>
                            <input type="text" name="lat" id="lat" class="form-control" value="<?= htmlspecialchars($user['lat'] ?? '') ?>" placeholder="e.g. 27.7172">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Longitude</label>
                            <input type="text" name="lng" id="lng" class="form-control" value="<?= htmlspecialchars($user['lng'] ?? '') ?>" placeholder="e.g. 85.3240">
                        </div>
                        <button type="button" onclick="getLocation()" class="btn-submit" style="padding: 12px 15px; background: #64748b;">
                            <i class="fa fa-crosshairs"></i> Get Current
                        </button>
                    </div>
                </div>

                <!-- Section 3: FTTH & Network Inventory -->
                <div class="form-section" style="margin-top:35px;">
                    <div class="section-title"><i class="fa fa-network-wired"></i> FTTH & Network Inventory</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>OLT Name/ID</label>
                            <div class="input-wrapper">
                                <i class="fa fa-server"></i>
                                <input type="text" name="olt" class="form-control" value="<?= htmlspecialchars($user['olt'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>OLT Port</label>
                            <div class="input-wrapper">
                                <i class="fa fa-plug"></i>
                                <input type="number" name="olt_port" class="form-control" value="<?= htmlspecialchars($user['olt_port'] ?? 0) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Master Box</label>
                            <div class="input-wrapper">
                                <i class="fa fa-box"></i>
                                <input type="text" name="master_box" class="form-control" value="<?= htmlspecialchars($user['master_box'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Distribution Box (DB)</label>
                            <div class="input-wrapper">
                                <i class="fa fa-boxes"></i>
                                <input type="text" name="db_box" class="form-control" value="<?= htmlspecialchars($user['db_box'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>DB Port</label>
                            <div class="input-wrapper">
                                <i class="fa fa-sign-in-alt"></i>
                                <input type="number" name="db_port" class="form-control" value="<?= htmlspecialchars($user['db_port'] ?? 0) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Service Configuration -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-wifi"></i> Service Configuration</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>ONU Serial Number</label>
                            <div class="input-wrapper">
                                <i class="fa fa-microchip"></i>
                                <input type="text" name="onu_serial" class="form-control" value="<?= htmlspecialchars($user['onu_serial']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>VLAN ID</label>
                            <div class="input-wrapper">
                                <i class="fa fa-sitemap"></i>
                                <input type="number" name="vlan" class="form-control" value="<?= htmlspecialchars($user['vlan'] ?? 0) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Internet Plan</label>
                            <select name="plan_id" class="form-control">
                                <?php foreach($plans_list as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($user['plan_id']==$p['id'])?'selected':'' ?>>
                                        <?= htmlspecialchars($p['name']) ?> (<?= $p['speed'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assigned Branch</label>
                            <select name="branch_id" class="form-control">
                                <option value="">Global / No Branch</option>
                                <?php foreach($branches_list as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= ($user['branch_id']==$b['id'])?'selected':'' ?>>
                                        <?= htmlspecialchars($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Security -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-shield-alt"></i> Account Security</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Current PPPoE Password</label>
                            <div style="padding: 12px; background: #f1f5f9; border-radius: 10px; font-family: monospace; font-weight: bold; color: #1e293b; border: 1px solid #e2e8f0;">
                                <?= htmlspecialchars($current_rad_pass) ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Set New Password <small style="color: #94a3b8; font-weight: normal;">(Leave empty to keep current)</small></label>
                            <div class="input-wrapper">
                                <i class="fa fa-key"></i>
                                <input type="password" name="password" id="new_pass" class="form-control" placeholder="••••••••">
                                <i class="fa fa-eye" id="togglePass" style="left: auto; right: 15px; cursor: pointer; pointer-events: auto;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid #f1f5f9; padding-top: 30px;">
                    <button type="submit" name="save_user" class="btn-submit">
                        <i class="fa fa-save"></i> Commit Changes
                    </button>
                    <a href="users.php" class="btn-cancel">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

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
    let msg = "";
    switch(error.code) {
        case error.PERMISSION_DENIED: msg = "User denied the request for Geolocation."; break;
        case error.POSITION_UNAVAILABLE: msg = "Location information is unavailable."; break;
        case error.TIMEOUT: msg = "The request to get user location timed out."; break;
        case error.UNKNOWN_ERROR: msg = "An unknown error occurred."; break;
    }
    alert(msg);
}

// Password Toggle
document.getElementById('togglePass').addEventListener('click', function() {
    const passInput = document.getElementById('new_pass');
    const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passInput.setAttribute('type', type);
    this.classList.toggle('fa-eye-slash');
});
</script>

<?php include 'includes/footer.php'; ?>
