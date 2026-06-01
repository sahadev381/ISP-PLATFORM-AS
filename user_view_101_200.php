    }
}

/* ============================
   TR-069 LIVE DATA
============================ */
$device = null;
$genieacsDevice = null;
$target_serial = strtoupper(trim($user['onu_serial'] ?? ''));

if (!empty($target_serial)) {
    $deviceId = $user['tr069_device_id'];
    if (!$deviceId) {
        $devices = genieacs_request("/devices?_limit=1&query=".urlencode(json_encode(["_deviceId._SerialNumber" => $target_serial])), "GET");
        if (is_array($devices) && count($devices) > 0) {
            $device = $devices[0];
            $deviceId = $device['_id'];
            $conn->query("UPDATE customers SET tr069_device_id='$deviceId' WHERE username='$username'");
        }
    }

    if ($deviceId) {
        $projection = urlencode(json_encode([
            "InternetGatewayDevice.DeviceInfo.SoftwareVersion",
            "InternetGatewayDevice.DeviceInfo.Manufacturer",
            "InternetGatewayDevice.DeviceInfo.ProductClass",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase"
        ]));
        $genieacsDevice = genieacs_request("/devices/$deviceId?projection=$projection", "GET");
    }
}

/* ============================
   CURRENT SESSION
============================ */
$session = $conn->query("
    SELECT *, TIMESTAMPDIFF(SECOND, acctstarttime, NOW()) AS duration
    FROM radacct
    WHERE username='$username' AND acctstoptime IS NULL
    ORDER BY acctstarttime DESC LIMIT 1
")->fetch_assoc();

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$page_title = "Profile: " . $user['username'];
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<script>
function showTab(tabId, btn) {
    var contents = document.querySelectorAll('.tab-content');
    contents.forEach(function(c) {
        c.classList.remove('active');
        c.style.display = 'none';
    });
    var tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    var target = document.getElementById(tabId);
    if(target) {
        target.classList.add('active');
        target.style.display = 'block';
    }
    if(btn) btn.classList.add('active');
    if(tabId === 'livegraph' && typeof initLiveChart === 'function') initLiveChart();
    if(tabId === 'optical_power' && typeof initPowerChart === 'function') initPowerChart();
    if(tabId === 'overview' && typeof userMapObj !== 'undefined' && userMapObj) {
        setTimeout(function(){ userMapObj.invalidateSize(); }, 200);
    }
}
</script>

<style>
    .profile-container { padding: 25px; }
    .profile-header { background: #fff; border-radius: 15px; padding: 30px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
    .profile-id { display: flex; align-items: center; gap: 20px; }
    .profile-avatar { width: 70px; height: 70px; background: #3b82f6; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; }
    .profile-name h1 { margin: 0; font-size: 24px; color: #1e293b; }

    /* Modern Tabs */
    .nav-tabs { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0; overflow-x: auto; }
    .nav-tab { padding: 12px 20px; border-radius: 10px 10px 0 0; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border: none; background: none; transition: all 0.2s; white-space: nowrap; border-bottom: 2px solid transparent; margin-bottom: -2px; }
    .nav-tab:hover { color: #3b82f6; background: #f8fafc; }
    .nav-tab.active { color: #3b82f6; border-bottom: 2px solid #3b82f6; background: #eff6ff; }

    .tab-content { display: none; animation: fadeIn 0.3s ease; }
    .tab-content.active { display: block !important; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
    .info-card { background: #fff; border-radius: 15px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; height: 100%; }
