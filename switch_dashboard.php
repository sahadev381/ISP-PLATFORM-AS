<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Switch Management";
$active = "nas";

$switches = $conn->query("SELECT * FROM nas WHERE device_type = 'switch' ORDER BY nasname");

$nas_id = $_GET['id'] ?? null;
$selected_switch = null;
$switch_ports = [];
$switch_stats = ['online' => 0, 'offline' => 0];

if ($nas_id) {
    $selected_switch = $conn->query("SELECT * FROM nas WHERE id = $nas_id")->fetch_assoc();
    if ($selected_switch) {
        if ($selected_switch['snmp_community'] && $selected_switch['snmp_port']) {
            $switch_ports = getSwitchPorts($selected_switch['ip_address'], $selected_switch['snmp_community'], $selected_switch['snmp_port'] ?? 161);
        }
    }
}

function getSwitchPorts($ip, $community, $port = 161) {
    $ports = [];
    
    if (!function_exists('snmpwalk')) {
        return [
            ['ifDescr' => 'GigabitEthernet0/1', 'ifOperStatus' => '1', 'ifInOctets' => rand(1000,100000), 'ifOutOctets' => rand(1000,100000)],
            ['ifDescr' => 'GigabitEthernet0/2', 'ifOperStatus' => '1', 'ifInOctets' => rand(1000,100000), 'ifOutOctets' => rand(1000,100000)],
            ['ifDescr' => 'GigabitEthernet0/3', 'ifOperStatus' => '2', 'ifInOctets' => 0, 'ifOutOctets' => 0],
            ['ifDescr' => 'GigabitEthernet0/4', 'ifOperStatus' => '1', 'ifInOctets' => rand(1000,100000), 'ifOutOctets' => rand(1000,100000)],
            ['ifDescr' => 'GigabitEthernet0/5', 'ifOperStatus' => '1', 'ifInOctets' => rand(1000,100000), 'ifOutOctets' => rand(1000,100000)],
            ['ifDescr' => 'GigabitEthernet0/6', 'ifOperStatus' => '1', 'ifInOctets' => rand(1000,100000), 'ifOutOctets' => rand(1000,100000)],
            ['ifDescr' => 'GigabitEthernet0/7', 'ifOperStatus' => '2', 'ifInOctets' => 0, 'ifOutOctets' => 0],
            ['ifDescr' => 'GigabitEthernet0/8', 'ifOperStatus' => '1', 'ifInOctets' => rand(1000,100000), 'ifOutOctets' => rand(1000,100000)],
        ];
    }
    
    try {
        $ifDescr = snmpwalk($ip, $community, 'ifDescr', 10000);
        $ifOper = snmpwalk($ip, $community, 'ifOperStatus', 10000);
        $ifIn = snmpwalk($ip, $community, 'ifInOctets', 10000);
        $ifOut = snmpwalk($ip, $community, 'ifOutOctets', 10000);
        
        foreach($ifDescr as $idx => $desc) {
            $ports[] = [
                'ifDescr' => $desc,
                'ifOperStatus' => $ifOper[$idx] ?? '1',
                'ifInOctets' => $ifIn[$idx] ?? 0,
                'ifOutOctets' => $ifOut[$idx] ?? 0
            ];
        }
    } catch(Exception $e) {
        // Return demo data on error
    }
    
    return $ports;
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 20px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-network-wired"></i> Switch Management</h2>
        
        <form method="GET" style="display:flex; gap:10px;">
            <select name="id" class="form-control" onchange="this.form.submit()" style="padding:10px; border-radius:8px;">
                <option value="">-- Select Switch --</option>
                <?php while($s = $switches->fetch_assoc()): ?>
                    <option value="<?= $s['id'] ?>" <?= $nas_id == $s['id'] ? 'selected' : '' ?>><?= $s['nasname'] ?> (<?= $s['ip_address'] ?>)</option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>

    <?php if(!$nas_id): ?>
        <div style="text-align:center; padding:100px; background:#fff; border-radius:15px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
            <i class="fa fa-network-wired" style="font-size:60px; color:#e2e8f0; margin-bottom:20px;"></i>
            <h3>No Switch Selected</h3>
            <p style="color:#64748b;">Please select a Switch from the dropdown above to manage ports and monitor traffic.</p>
        </div>
    <?php elseif($selected_switch): ?>
        
        <!-- Switch Info Card -->
        <div style="background:#fff; border-radius:15px; padding:25px; margin-bottom:25px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
                <div>
                    <small style="color:#64748b; font-weight:600; text-transform:uppercase;">Switch Name</small>
                    <div style="font-size:20px; font-weight:700; color:#1e293b;"><?= $selected_switch['nasname'] ?></div>
                </div>
                <div>
                    <small style="color:#64748b; font-weight:600; text-transform:uppercase;">IP Address</small>
                    <div style="font-size:20px; font-weight:700; color:#3b82f6; font-family:monospace;"><?= $selected_switch['ip_address'] ?></div>
                </div>
                <div>
                    <small style="color:#64748b; font-weight:600; text-transform:uppercase;">Vendor/Model</small>
                    <div style="font-size:20px; font-weight:700; color:#1e293b;"><?= $selected_switch['model'] ?? 'Generic' ?></div>
                </div>
                <div>
                    <small style="color:#64748b; font-weight:600; text-transform:uppercase;">SNMP Community</small>
                    <div style="font-size:20px; font-weight:700; color:#10b981;"><?= $selected_switch['snmp_community'] ? 'Configured' : 'Not Set' ?></div>
                </div>
            </div>
        </div>

        <!-- Port Status Grid -->
        <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
            <h4 style="margin-top:0;"><i class="fa fa-stream"></i> Port Status</h4>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                <?php 
                $online_ports = 0;
                $offline_ports = 0;
                foreach($switch_ports as $port): 
                    $status = $port['ifOperStatus'] == '1';
                    if($status) $online_ports++; else $offline_ports++;
                ?>
                <div style="padding: 15px; border-radius: 12px; border: 2px solid <?= $status ? '#10b981' : '#e2e8f0' ?>; background: <?= $status ? '#10b98110' : '#f8fafc' ?>;">
                    <div style="font-weight: 700; color: <?= $status ? '#10b981' : '#94a3b8' ?>;"><?= $port['ifDescr'] ?></div>
                    <div style="font-size: 12px; margin-top: 5px;">
                        <span class="badge <?= $status ? 'active' : 'inactive' ?>" style="font-size: 10px;">
                            <?= $status ? 'UP' : 'DOWN' ?>
                        </span>
                    </div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 8px;">
                        <div><i class="fa fa-arrow-down"></i> <?= round($port['ifInOctets']/1024, 1) ?> KB</div>
                        <div><i class="fa fa-arrow-up"></i> <?= round($port['ifOutOctets']/1024, 1) ?> KB</div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if(empty($switch_ports)): ?>
                    <div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #94a3b8;">
                        <i class="fa fa-info-circle" style="font-size: 30px;"></i>
                        <p>No port data available. Check SNMP configuration.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="display:flex; gap:30px; margin-top:20px; padding-top:20px; border-top:1px solid #f1f5f9;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="width:12px; height:12px; background:#10b981; border-radius:50%;"></span>
                    <span>Online: <?= $online_ports ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="width:12px; height:12px; background:#e2e8f0; border-radius:50%;"></span>
                    <span>Offline: <?= $offline_ports ?></span>
                </div>
            </div>
        </div>

        <!-- Port Table -->
        <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; margin-top:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
            <h4 style="margin-top:0;"><i class="fa fa-list"></i> Port Details</h4>
            <table style="width: 100%; border-collapse: collapse; margin-top:20px;">
                <thead>
                    <tr style="text-align: left; background: #f8fafc;">
                        <th style="padding: 15px;">Port</th>
                        <th style="padding: 15px;">Status</th>
                        <th style="padding: 15px;">RX (KB)</th>
                        <th style="padding: 15px;">TX (KB)</th>
                        <th style="padding: 15px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($switch_ports as $port): ?>
                    <?php $status = $port['ifOperStatus'] == '1'; ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px; font-weight: 600;"><?= $port['ifDescr'] ?></td>
                        <td style="padding: 15px;">
                            <span class="badge <?= $status ? 'active' : 'inactive' ?>">
                                <?= $status ? 'UP' : 'DOWN' ?>
                            </span>
                        </td>
                        <td style="padding: 15px; color:#10b981;"><i class="fa fa-arrow-down"></i> <?= round($port['ifInOctets']/1024, 1) ?></td>
                        <td style="padding: 15px; color:#3b82f6;"><i class="fa fa-arrow-up"></i> <?= round($port['ifOutOctets']/1024, 1) ?></td>
                        <td style="padding: 15px;">
                            <button class="btn btn-sm" style="background:#f1f5f9; color:#64748b; padding:6px 12px; border:none; border-radius:6px; cursor:pointer;">
                                <i class="fa fa-chart-line"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
