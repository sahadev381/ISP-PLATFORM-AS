<?php
$base_path = './';
include "config.php";
include "includes/auth.php";
include "includes/genieacs_api.php";

$page_title = "TR-069 Device Management";
$active = "operations";

/* ============================
   HANDLE ACTIONS
============================ */
$msg = "";
$msg_type = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceId = $_POST['deviceId'] ?? '';
    
    // Reboot Action
    if (isset($_POST['reboot']) && !empty($deviceId)) {
        genieacs_request("/devices/$deviceId/tasks", "POST", ["name" => "reboot"]);
        $msg = "Reboot command sent successfully!";
    }

    // Update WiFi Action
    if (isset($_POST['setwifi']) && !empty($deviceId)) {
        $ssid = $_POST['ssid'] ?? '';
        $pass = $_POST['wifi_pass'] ?? '';
        
        if (!empty($ssid) && !empty($pass)) {
            genieacs_request("/devices/$deviceId/tasks", "POST", [
                "name" => "setParameterValues",
                "parameterValues" => [
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", $ssid, "xsd:string"],
                    ["InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase", $pass, "xsd:string"]
                ]
            ]);

            // Sync with Database
            $stmt = $conn->prepare("UPDATE customers SET wifi_ssid=?, wifi_password=? WHERE tr069_device_id=?");
            $stmt->bind_param("sss", $ssid, $pass, $deviceId);
            $stmt->execute();

            $msg = "WiFi credentials updated and synced with ACS!";
        } else {
            $msg = "SSID and Password are required.";
            $msg_type = "error";
        }
    }
}

/* ============================
   FETCH DEVICES
============================ */
$devices = genieacs_request("/devices?_limit=100&_sort=-_lastInform", "GET", null);

// Calculate stats
$total_devices = is_array($devices) ? count($devices) : 0;
$online_count = 0;
if ($total_devices > 0) {
    foreach ($devices as $d) {
        $last_inform = isset($d['_lastInform']) ? strtotime($d['_lastInform']) : 0;
        if ((time() - $last_inform) < 300) { // Considered online if informed in last 5 mins
            $online_count++;
        }
    }
}

include "includes/header.php";
include "includes/sidebar.php";
include "includes/topbar.php";
?>

<style>
    .acs-container { padding: 25px; }
    
    /* Stats Section */
    .acs-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .acs-stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px; }
    .acs-stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    
    /* Table Styling */
    .table-card { background: #fff; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: #f8fafc; padding: 15px 20px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; }
    td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; }
    tr:hover { background: #f8fafc; }
    
    /* Status Badges */
    .status-indicator { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
    .status-online { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.5); }
    .status-offline { background: #94a3b8; }
    
    .device-info small { display: block; color: #64748b; font-size: 12px; margin-top: 2px; }
    
    /* Action Buttons */
    .btn-action { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all 0.2s; }
    .btn-reboot { background: #fef2f2; color: #ef4444; }
    .btn-wifi { background: #eff6ff; color: #3b82f6; }
    .btn-action:hover { transform: translateY(-2px); filter: brightness(0.95); }

    /* Modal */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
    .modal-content { background: #fff; margin: 10% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 400px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .modal-header { padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .modal-body { padding: 25px; }
    
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #475569; }
    .form-control { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; }
    
    .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
</style>

<div class="acs-container">
    
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <i class="fa fa-<?= $msg_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="acs-stats">
        <div class="acs-stat-card">
            <div class="acs-stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i class="fa fa-microchip"></i></div>
            <div class="stat-data">
                <h3><?= number_format($total_devices) ?></h3>
                <p>Managed Devices</p>
            </div>
        </div>
        <div class="acs-stat-card">
            <div class="acs-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fa fa-plug"></i></div>
            <div class="stat-data">
                <h3><?= number_format($online_count) ?></h3>
                <p>Online Now</p>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-size: 20px; color: #1e293b; font-weight: 700;">Active ONU Devices (Last 100)</h2>
        <div style="position: relative; width: 300px;">
            <i class="fa fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
            <input type="text" id="deviceSearch" placeholder="Search Serial Number..." style="width: 100%; padding: 10px 15px 10px 35px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none;">
        </div>
    </div>

    <!-- Devices Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table id="devicesTable">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Device Identity</th>
                        <th>Manufacturer / Model</th>
                        <th>Last Inform</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($devices) && count($devices) > 0): ?>
                        <?php foreach ($devices as $d): 
                            $serial = $d['_deviceId']['_SerialNumber'] ?? 'N/A';
                            $manufacturer = $d['_deviceId']['_Manufacturer'] ?? 'N/A';
                            $model = $d['_deviceId']['_ProductClass'] ?? 'N/A';
                            $lastInformTs = isset($d['_lastInform']) ? strtotime($d['_lastInform']) : 0;
                            $lastInform = $lastInformTs > 0 ? date('M d, H:i:s', $lastInformTs) : 'Never';
                            $deviceId = $d['_id'] ?? '';
                            $isOnline = (time() - $lastInformTs) < 300;
                        ?>
                        <tr>
                            <td>
                                <span class="status-indicator <?= $isOnline ? 'status-online' : 'status-offline' ?>"></span>
                                <span style="font-size: 12px; font-weight: 600; color: <?= $isOnline ? '#10b981' : '#94a3b8' ?>;">
                                    <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?>
                                </span>
                            </td>
                            <td>
                                <div class="device-info">
                                    <span style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($serial) ?></span>
                                    <small>ID: <?= htmlspecialchars($deviceId) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="device-info">
                                    <span><?= htmlspecialchars($manufacturer) ?></span>
                                    <small><?= htmlspecialchars($model) ?></small>
                                </div>
                            </td>
                            <td style="color: #64748b; font-size: 13px;"><?= $lastInform ?></td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <form method="POST" onsubmit="return confirm('Send Reboot command to this device?');" style="display: inline;">
                                        <input type="hidden" name="deviceId" value="<?= $deviceId ?>">
                                        <button type="submit" name="reboot" class="btn-action btn-reboot" title="Reboot Device">
                                            <i class="fa fa-power-off"></i>
                                        </button>
                                    </form>
                                    <button onclick="openWifiModal('<?= $deviceId ?>', '<?= htmlspecialchars($serial) ?>')" class="btn-action btn-wifi" title="Configure WiFi">
                                        <i class="fa fa-wifi"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 50px; color: #94a3b8;">No devices found in ACS.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- WiFi Config Modal -->
<div id="wifiModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Set Wi-Fi Details</h3>
            <span style="cursor:pointer; font-size: 24px; color: #94a3b8;" onclick="closeWifiModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="deviceId" id="modalDeviceId">
                <div class="form-group">
                    <label>Wi-Fi Name (SSID)</label>
                    <input type="text" name="ssid" class="form-control" placeholder="E.g. Home_Network" required>
                </div>
                <div class="form-group">
                    <label>Wi-Fi Password (WPA Key)</label>
                    <input type="text" name="wifi_pass" class="form-control" placeholder="Minimum 8 characters" required>
                </div>
                <button type="submit" name="setwifi" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 10px; font-weight: 600; margin-top: 10px;">
                    <i class="fa fa-save"></i> Push WiFi Settings
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openWifiModal(id, serial) {
    document.getElementById('modalDeviceId').value = id;
    document.getElementById('modalTitle').innerText = 'WiFi Config: ' + serial;
    document.getElementById('wifiModal').style.display = 'block';
}

function closeWifiModal() {
    document.getElementById('wifiModal').style.display = 'none';
}

// Search Functionality
document.getElementById('deviceSearch').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let rows = document.querySelector("#devicesTable tbody").rows;
    
    for (let i = 0; i < rows.length; i++) {
        let serial = rows[i].cells[1].innerText;
        if (serial.toUpperCase().indexOf(filter) > -1) {
            rows[i].style.display = "";
        } else {
            rows[i].style.display = "none";
        }      
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
</script>

<?php include "includes/footer.php"; ?>
