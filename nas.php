<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

include 'config.php';
include 'includes/auth.php';

$page_title = "Network Devices";
$active = "nas";

/* ADD NETWORK DEVICE */
if(isset($_POST['add'])){
    $nasname = $_POST['nasname'];
    $shortname = $_POST['shortname'];
    $secret = $_POST['secret'];
    $ports = $_POST['ports'] ?: 1812;
    $ip_address = $_POST['ip_address'];
    $api_user = $_POST['api_user'];
    $api_pass = $_POST['api_pass'];
    $api_port = $_POST['api_port'] ?: 8728;
    $device_type = $_POST['device_type'] ?? 'mikrotik';
    $snmp_community = $_POST['snmp_community'] ?? 'public';
    $snmp_version = $_POST['snmp_version'] ?? '2c';
    $brand = $_POST['brand'] ?? 'generic';
    $pon_ports = intval($_POST['pon_ports'] ?: 8);
    
    $stmt = $conn->prepare("
        INSERT INTO nas
        (nasname, shortname, type, secret, ports, server, ip_address, api_user, api_pass, api_port, status, device_type, snmp_community, snmp_version, brand, pon_ports)
        VALUES (?, ?, 'mikrotik', ?, ?, 'mikrotik', ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssisssissssi", $nasname, $shortname, $secret, $ports, $ip_address, $api_user, $api_pass, $api_port, $device_type, $snmp_community, $snmp_version, $brand, $pon_ports);
    $stmt->execute();
}

/* DELETE DEVICE */
if(isset($_GET['del'])){
    $id = intval($_GET['del']);
    $conn->query("DELETE FROM nas WHERE id=$id");
}

/* FETCH DEVICES */
$bng = $conn->query("SELECT * FROM nas ORDER BY id DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main">

<!-- Add Form (Hidden by default) -->
<div id="addPlanFormContainer" style="display:none; margin-bottom:30px; padding: 20px;">
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-plus"></i> Add Network Device</h3>
        </div>
        <form method="post">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; padding: 20px;">
                <div class="form-group">
                    <label class="form-label">Device Name</label>
                    <input type="text" name="nasname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Device Type</label>
                    <select name="device_type" class="form-control">
                        <option value="mikrotik">MikroTik Router</option>
                        <option value="olt">OLT</option>
                        <option value="switch">Managed Switch</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">IP Address</label>
                    <input type="text" name="ip_address" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">RADIUS Secret (For Mikrotik)</label>
                    <input type="text" name="secret" class="form-control">
                </div>
                
                <!-- SNMP Settings -->
                <div class="form-group">
                    <label class="form-label">SNMP Community</label>
                    <input type="text" name="snmp_community" class="form-control" value="public">
                </div>
                <div class="form-group">
                    <label class="form-label">SNMP Version</label>
                    <select name="snmp_version" class="form-control">
                        <option value="1">v1</option>
                        <option value="2c" selected>v2c</option>
                        <option value="3">v3</option>
                    </select>
                </div>

                <!-- API Settings (Optional) -->
                <div class="form-group">
                    <label class="form-label">API User</label>
                    <input type="text" name="api_user" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">API Password</label>
                    <input type="password" name="api_pass" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">OLT Brand</label>
                    <select name="brand" class="form-control">
                        <option value="generic">Generic / Default</option>
                        <option value="bdcom">BDCOM</option>
                        <option value="huawei">Huawei</option>
                        <option value="zte">ZTE</option>
                        <option value="vsol">VSOL</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">PON Ports</label>
                    <input type="number" name="pon_ports" class="form-control" value="8">
                </div>
            </div>
            <div style="padding: 0 20px 20px;">
                <button type="submit" name="add" class="btn btn-primary">
                    <i class="fa fa-save"></i> Save Device
                </button>
            </div>
        </form>
    </div>
</div>

<!-- NAS Table -->
<div class="table-box" style="margin: 20px; background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #f1f5f9;">
        <h3 style="margin:0;"><i class="fa fa-network-wired"></i> Network Infrastructure</h3>
        <button id="toggleAddForm" class="btn btn-success" style="padding: 8px 15px; border-radius: 8px;">
            <i class="fa fa-plus"></i> Add New Device
        </button>
    </div>

<div style="overflow-x: auto;">
<table style="width:100%; border-collapse: collapse;">
    <thead>
        <tr style="text-align: left; background: #f8fafc;">
            <th style="padding: 15px;">Device Info</th>
            <th style="padding: 15px;">Type</th>
            <th style="padding: 15px;">SNMP Config</th>
            <th style="padding: 15px;">Status</th>
            <th style="padding: 15px;">Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while($n=$bng->fetch_assoc()){ 
            $type_icon = ($n['device_type'] == 'mikrotik') ? 'fa-server' : (($n['device_type'] == 'olt') ? 'fa-boxes' : 'fa-network-wired');
        ?>
        <tr style="border-bottom: 1px solid #f1f5f9;">
            <td style="padding: 15px;">
                <div style="font-weight: 600;"><?= htmlspecialchars($n['nasname']) ?></div>
                <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($n['ip_address']) ?></div>
            </td>
            <td style="padding: 15px;">
                <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                    <i class="fa <?= $type_icon ?>"></i> <?= strtoupper($n['device_type']) ?>
                </span>
            </td>
            <td style="padding: 15px;">
                <div style="font-size: 13px;"><b>Comm:</b> <?= htmlspecialchars($n['snmp_community']) ?></div>
                <div style="font-size: 11px; color: #64748b;"><b>Ver:</b> <?= htmlspecialchars($n['snmp_version']) ?></div>
            </td>
            <td style="padding: 15px;">
                <span id="status-<?= $n['id'] ?>" class="badge <?= $n['status'] ? 'active' : 'inactive' ?>">
                    <?= $n['status'] ? 'Online' : 'Offline' ?>
                </span>
            </td>
            <td style="padding: 15px;">
                <div class="action-buttons" style="display: flex; gap: 8px;">
                    <a href="nas_edit.php?id=<?= $n['id'] ?>" class="btn-icon btn-edit" title="Edit"><i class="fa fa-edit"></i></a>
                    <a href="?del=<?= $n['id'] ?>" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Delete this device?')"><i class="fa fa-trash"></i></a>
                </div>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</div>
</div>

<script>
document.getElementById('toggleAddForm').addEventListener('click', function() {
    var container = document.getElementById('addPlanFormContainer');
    if (container.style.display === 'none') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
});

function pollDevices() {
    fetch('api_network_status.php')
    .then(r => r.json())
    .then(data => {
        data.forEach(d => {
            let el = document.getElementById('status-' + d.id);
            if(el) {
                el.className = 'badge ' + (d.status ? 'active' : 'inactive');
                el.innerText = d.status ? 'Online' : 'Offline';
            }
        });
    });
}
setInterval(pollDevices, 15000); // Update every 15 seconds
pollDevices(); // Initial check
</script>

<?php include 'includes/footer.php'; ?>
