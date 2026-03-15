<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'includes/auth.php';

$page_title = "Edit Network Device";
$active = "nas";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: nas.php");
    exit;
}

$id = intval($_GET['id']);
$msg = "";
$msg_type = "";

// Fetch Device data
$stmt = $conn->prepare("SELECT * FROM nas WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$nas = $result->fetch_assoc();

if (!$nas) {
    header("Location: nas.php");
    exit;
}

// Update Logic
if (isset($_POST['update'])) {
    $nasname = $_POST['nasname'];
    $shortname = $_POST['shortname'];
    $secret = $_POST['secret'];
    $device_type = $_POST['device_type'];
    $ports = intval($_POST['ports'] ?: 1812);
    $ip_address = $_POST['ip_address'];
    $api_user = $_POST['api_user'];
    $api_pass = $_POST['api_pass'];
    $api_port = intval($_POST['api_port'] ?: 8728);
    $lat = !empty($_POST['lat']) ? $_POST['lat'] : NULL;
    $lng = !empty($_POST['lng']) ? $_POST['lng'] : NULL;
    $snmp_community = $_POST['snmp_community'] ?? 'public';
    $snmp_version = $_POST['snmp_version'] ?? '2c';
    $brand = $_POST['brand'] ?? 'generic';
    $pon_ports = intval($_POST['pon_ports'] ?: 8);

    $update = $conn->prepare("UPDATE nas SET nasname=?, shortname=?, secret=?, device_type=?, ports=?, ip_address=?, api_user=?, api_pass=?, api_port=?, lat=?, lng=?, snmp_community=?, snmp_version=?, pon_ports=?, brand=? WHERE id=?");
    $update->bind_param("ssssissssddssisi", $nasname, $shortname, $secret, $device_type, $ports, $ip_address, $api_user, $api_pass, $api_port, $lat, $lng, $snmp_community, $snmp_version, $pon_ports, $brand, $id);

    if ($update->execute()) {
        $msg = "Device configuration updated successfully!";
        $msg_type = "success";
        // Refresh data
        $stmt->execute();
        $nas = $stmt->get_result()->fetch_assoc();
    } else {
        $msg = "Update failed: " . $conn->error;
        $msg_type = "error";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .edit-container { padding: 25px; max-width: 900px; margin: 0 auto; }
    .card { background: #fff; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
    .card-header { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; background: #fafbfc; }
    .card-header i { color: #3b82f6; font-size: 20px; }
    .card-header h2 { margin: 0; font-size: 18px; color: #1e293b; font-weight: 600; }
    .card-body { padding: 30px; }
    
    .form-section { margin-bottom: 30px; }
    .section-title { font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
    
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; }
    .form-control { width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.2s; outline: none; background: #f8fafc; }
    .form-control:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    
    .btn-save { background: #3b82f6; color: #fff; padding: 12px 30px; border-radius: 10px; border: none; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 10px; }
    .btn-save:hover { background: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); }
    
    .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; display: flex; align-items: center; gap: 12px; }
    .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
</style>

<div class="edit-container">
    
    <?php if($msg): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <i class="fa <?= $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fa fa-network-wired"></i>
            <h2>Edit Network Device</h2>
        </div>
        
        <div class="card-body">
            <form method="POST">
                
                <!-- Section 1: Device Info -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-id-card"></i> Device Identity</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Device Name (NASName)</label>
                            <input type="text" name="nasname" class="form-control" value="<?= htmlspecialchars($nas['nasname']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Short Name / Identifier</label>
                            <input type="text" name="shortname" class="form-control" value="<?= htmlspecialchars($nas['shortname']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Device Type</label>
                            <select name="device_type" class="form-control">
                                <option value="mikrotik" <?= $nas['device_type'] == 'mikrotik' ? 'selected' : '' ?>>MikroTik Router</option>
                                <option value="olt" <?= $nas['device_type'] == 'olt' ? 'selected' : '' ?>>OLT</option>
                                <option value="switch" <?= $nas['device_type'] == 'switch' ? 'selected' : '' ?>>Managed Switch</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>IP Address</label>
                            <input type="text" name="ip_address" class="form-control" value="<?= htmlspecialchars($nas['ip_address']) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Section 2: SNMP & API -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-plug"></i> Protocol Configuration</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>RADIUS Secret</label>
                            <input type="text" name="secret" class="form-control" value="<?= htmlspecialchars($nas['secret'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>RADIUS Port</label>
                            <input type="number" name="ports" class="form-control" value="<?= htmlspecialchars($nas['ports']) ?>">
                        </div>
                        <div class="form-group">
                            <label>SNMP Community</label>
                            <input type="text" name="snmp_community" class="form-control" value="<?= htmlspecialchars($nas['snmp_community'] ?? 'public') ?>">
                        </div>
                        <div class="form-group">
                            <label>SNMP Version</label>
                            <select name="snmp_version" class="form-control">
                                <option value="1" <?= ($nas['snmp_version'] ?? '') == '1' ? 'selected' : '' ?>>v1</option>
                                <option value="2c" <?= ($nas['snmp_version'] ?? '2c') == '2c' ? 'selected' : '' ?>>v2c</option>
                                <option value="3" <?= ($nas['snmp_version'] ?? '') == '3' ? 'selected' : '' ?>>v3</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>API User</label>
                            <input type="text" name="api_user" class="form-control" value="<?= htmlspecialchars($nas['api_user']) ?>">
                        </div>
                        <div class="form-group">
                            <label>API Password</label>
                            <input type="text" name="api_pass" class="form-control" value="<?= htmlspecialchars($nas['api_pass']) ?>">
                        </div>
                        <div class="form-group">
                            <label>API Port</label>
                            <input type="number" name="api_port" class="form-control" value="<?= htmlspecialchars($nas['api_port'] ?: 8728) ?>">
                        </div>
                        <?php if($nas['device_type'] == 'olt'): ?>
                        <div class="form-group">
                            <label>OLT Brand</label>
                            <select name="brand" class="form-control">
                                <option value="generic" <?= ($nas['brand'] ?? 'generic') == 'generic' ? 'selected' : '' ?>>Generic / Default</option>
                                <option value="bdcom" <?= ($nas['brand'] ?? '') == 'bdcom' ? 'selected' : '' ?>>BDCOM</option>
                                <option value="huawei" <?= ($nas['brand'] ?? '') == 'huawei' ? 'selected' : '' ?>>Huawei</option>
                                <option value="zte" <?= ($nas['brand'] ?? '') == 'zte' ? 'selected' : '' ?>>ZTE</option>
                                <option value="vsol" <?= ($nas['brand'] ?? '') == 'vsol' ? 'selected' : '' ?>>VSOL</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>PON Ports</label>
                            <input type="number" name="pon_ports" class="form-control" value="<?= htmlspecialchars($nas['pon_ports'] ?: 8) ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 3: Geo Location -->
                <div class="form-section">
                    <div class="section-title"><i class="fa fa-location-dot"></i> Geographical Location</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: flex-end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Latitude</label>
                            <input type="text" name="lat" id="lat" class="form-control" value="<?= htmlspecialchars($nas['lat'] ?? '') ?>" placeholder="e.g. 27.7172">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Longitude</label>
                            <input type="text" name="lng" id="lng" class="form-control" value="<?= htmlspecialchars($nas['lng'] ?? '') ?>" placeholder="e.g. 85.3240">
                        </div>
                        <button type="button" onclick="getLocation()" class="btn-save" style="background:#64748b; padding: 10px 15px;">
                            <i class="fa fa-crosshairs"></i> Get Current
                        </button>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid #f1f5f9; padding-top: 30px;">
                    <button type="submit" name="update" class="btn-save">
                        <i class="fa fa-save"></i> Save Device Settings
                    </button>
                    <a href="nas.php" style="background:#f1f5f9; color:#475569; padding:12px 30px; border-radius:10px; text-decoration:none; font-weight:600; font-size:14px; display:inline-flex; align-items:center; gap:10px;">
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
        navigator.geolocation.getCurrentPosition(function(pos) {
            document.getElementById("lat").value = pos.coords.latitude;
            document.getElementById("lng").value = pos.coords.longitude;
        }, function(err) {
            alert("Error: " + err.message);
        });
    } else { 
        alert("Geolocation not supported.");
    }
}
</script>

<?php include 'includes/footer.php'; ?>
