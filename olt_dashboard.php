<?php
include 'config.php';
include 'includes/auth.php';
include 'includes/olt_api.php';

$page_title = "OLT Management & ZTP Dashboard";
$active = "nas";

// Fetch All OLTs
$olts = $conn->query("SELECT * FROM nas WHERE device_type = 'olt'");

// Selected OLT
$olt_id = $_GET['id'] ?? null;
$selected_olt = null;
if ($olt_id) {
    $selected_olt = $conn->query("SELECT * FROM nas WHERE id = $olt_id")->fetch_assoc();
}

// Get OLT stats from database
$oltStats = [];
if ($olt_id) {
    $oltStats['total'] = $conn->query("SELECT COUNT(*) as c FROM olt_onu_signal WHERE olt_id = $olt_id")->fetch_assoc()['c'] ?? 0;
    $oltStats['online'] = $conn->query("SELECT COUNT(*) as c FROM olt_onu_signal WHERE olt_id = $olt_id AND status = 'online'")->fetch_assoc()['c'] ?? 0;
    $oltStats['offline'] = $oltStats['total'] - $oltStats['online'];
    $oltStats['critical'] = $conn->query("SELECT COUNT(*) as c FROM olt_onu_signal WHERE olt_id = $olt_id AND rx_power < -28")->fetch_assoc()['c'] ?? 0;
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    :root {
        --primary: #3b82f6;
        --primary-dark: #2563eb;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #06b6d4;
        --bg: #f1f5f9;
    }
    
    .olt-container {
        padding: 20px;
    }
    
    /* Header Section */
    .olt-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .olt-header h2 {
        margin: 0;
        color: #1e293b;
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .olt-selector {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .olt-selector select {
        padding: 10px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        min-width: 250px;
        background: white;
    }
    
    .btn {
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 12px;
    }
    
    .stat-card .stat-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
    }
    
    .stat-card .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
    }
    
    .stat-card .stat-trend {
        font-size: 12px;
        margin-top: 5px;
    }
    
    .stat-card .stat-trend.up { color: var(--success); }
    .stat-card .stat-trend.down { color: var(--danger); }
    
    /* PON Ports Grid */
    .pon-section {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .pon-section h3 {
        margin: 0 0 20px 0;
        color: #1e293b;
        font-size: 1.1rem;
    }
    
    .pon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 15px;
    }
    
    .pon-port {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        border: 2px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .pon-port:hover {
        border-color: var(--primary);
        transform: scale(1.05);
    }
    
    .pon-port .port-num {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .pon-port .port-onus {
        font-size: 11px;
        color: #64748b;
        margin-top: 5px;
    }
    
    .pon-port .port-status {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 8px auto 0;
    }
    
    .pon-port .port-status.online { background: var(--success); }
    .pon-port .port-status.offline { background: var(--danger); }
    .pon-port .port-status.warning { background: var(--warning); }
    
    /* ONT Table */
    .ont-section {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .ont-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .ont-section h3 {
        margin: 0;
        color: #1e293b;
        font-size: 1.1rem;
    }
    
    .ont-filters {
        display: flex;
        gap: 10px;
    }
    
    .ont-filters input, .ont-filters select {
        padding: 8px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
    }
    
    .ont-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .ont-table th {
        padding: 12px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .ont-table td {
        padding: 12px;
        font-size: 14px;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .ont-table tbody tr:hover {
        background: #f8fafc;
    }
    
    /* Signal Power Badge */
    .signal-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .signal-badge.good {
        background: #d1fae5;
        color: #065f46;
    }
    
    .signal-badge.weak {
        background: #fef3c7;
        color: #92400e;
    }
    
    .signal-badge.critical {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .signal-badge.offline {
        background: #f1f5f9;
        color: #64748b;
    }
    
    /* Status Badge */
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-badge.online {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-badge.offline {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-badge.los {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* Action Buttons */
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 5px;
        transition: all 0.2s;
    }
    
    .action-btn.info { background: #dbeafe; color: #2563eb; }
    .action-btn.warning { background: #fef3c7; color: #d97706; }
    .action-btn.danger { background: #fee2e2; color: #dc2626; }
    .action-btn.success { background: #d1fae5; color: #059669; }
    
    .action-btn:hover {
        transform: scale(1.1);
    }
    
    /* Loading State */
    .loading {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }
    
    .loading i {
        font-size: 30px;
        margin-bottom: 10px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #cbd5e1;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 16px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h4 { margin: 0; }
    
    .modal-body { padding: 20px; }
    
    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .close {
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
    }
</style>

<div class="olt-container">
    <!-- Header with OLT Selector -->
    <div class="olt-header">
        <h2><i class="fa fa-server"></i> OLT Management</h2>
        <div class="olt-selector">
            <form method="GET" style="display:flex; gap:10px;">
                <select name="id" onchange="this.form.submit()" style="padding:10px 15px; border:2px solid #e2e8f0; border-radius:10px; min-width:280px;">
                    <option value="">-- Select OLT --</option>
                    <?php 
                    $olts->data_seek(0);
                    while($o = $olts->fetch_assoc()): 
                    ?>
                        <option value="<?= $o['id'] ?>" <?= $olt_id == $o['id'] ? 'selected' : '' ?>>
                            <?= $o['nasname'] ?> (<?= $o['ip_address'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
            <button class="btn btn-primary" onclick="loadOLTData()">
                <i class="fa fa-sync"></i> Refresh
            </button>
        </div>
    </div>
    
    <?php if ($selected_olt): ?>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #dbeafe; color: #2563eb;">
                <i class="fa fa-network-wired"></i>
            </div>
            <div class="stat-label">Total ONTs</div>
            <div class="stat-value"><?= $oltStats['total'] ?? 0 ?></div>
            <div class="stat-trend up"><i class="fa fa-arrow-up"></i> Connected</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="stat-label">Online</div>
            <div class="stat-value"><?= $oltStats['online'] ?? 0 ?></div>
            <div class="stat-trend up"><i class="fa fa-arrow-up"></i> Active</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                <i class="fa fa-times-circle"></i>
            </div>
            <div class="stat-label">Offline</div>
            <div class="stat-value"><?= $oltStats['offline'] ?? 0 ?></div>
            <div class="stat-trend down"><i class="fa fa-arrow-down"></i> Needs Attention</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <div class="stat-label">Critical</div>
            <div class="stat-value"><?= $oltStats['critical'] ?? 0 ?></div>
            <div class="stat-trend down"><i class="fa fa-warning"></i> Weak Signal</div>
        </div>
    </div>
    
    <!-- PON Ports Grid -->
    <div class="pon-section">
        <h3><i class="fa fa-th"></i> PON Ports Overview</h3>
        <div class="pon-grid" id="ponGrid">
            <!-- Generated via JS -->
        </div>
    </div>
    
    <!-- ONT Table -->
    <div class="ont-section">
        <div class="ont-section-header">
            <h3><i class="fa fa-list"></i> ONT List</h3>
            <div class="ont-filters">
                <input type="text" id="ontSearch" placeholder="Search by Serial, Port..." onkeyup="filterONTs()">
                <select id="ontStatusFilter" onchange="filterONTs()">
                    <option value="">All Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
        </div>
        
        <table class="ont-table" id="ontTable">
            <thead>
                <tr>
                    <th>Port</th>
                    <th>Serial Number</th>
                    <th>ONT Type</th>
                    <th>RX Power</th>
                    <th>TX Power</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="ontTableBody">
                <tr>
                    <td colspan="7" class="loading">
                        <i class="fa fa-spinner fa-spin"></i><br>
                        Loading ONT data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <?php else: ?>
    
    <!-- No OLT Selected -->
    <div class="empty-state">
        <i class="fa fa-server"></i>
        <h3>No OLT Selected</h3>
        <p>Please select an OLT from the dropdown above to view ONT information.</p>
    </div>
    
    <?php endif; ?>
</div>

<!-- ONT Action Modal -->
<div id="ontActionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fa fa-cog"></i> ONT Actions</h4>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="ontSerialDisplay" style="font-size: 16px; font-weight: 600; margin-bottom: 20px;"></p>
            
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button class="btn btn-primary" onclick="ontAction('reboot')">
                    <i class="fa fa-sync"></i> Reboot ONT
                </button>
                <button class="btn success" style="background: #10b981; color: white;" onclick="ontAction('enable')">
                    <i class="fa fa-check"></i> Enable ONT
                </button>
                <button class="btn warning" style="background: #f59e0b; color: white;" onclick="ontAction('disable')">
                    <i class="fa fa-ban"></i> Disable ONT
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentOLT = <?= $olt_id ?? 0 ?>;
let ontData = [];

<?php if ($olt_id): ?>
// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadOLTData();
});
<?php endif; ?>

function loadOLTData() {
    if (!currentOLT) return;
    
    // Show loading
    document.getElementById('ontTableBody').innerHTML = '<tr><td colspan="7" class="loading"><i class="fa fa-spinner fa-spin"></i><br>Loading ONT data...</td></tr>';
    
    // Load ONT list
    fetch(`api/olt_ont.php?action=ont_list&olt_id=${currentOLT}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            ontData = data.data;
            renderONTTable(ontData);
            renderPONPorts();
        } else {
            document.getElementById('ontTableBody').innerHTML = `<tr><td colspan="7" class="empty-state">${data.message}</td></tr>`;
        }
    })
    .catch(err => {
        document.getElementById('ontTableBody').innerHTML = '<tr><td colspan="7" class="empty-state">Error loading data</td></tr>';
    });
}

function renderONTTable(onts) {
    if (!onts || onts.length === 0) {
        document.getElementById('ontTableBody').innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fa fa-network-wired"></i><br>No ONTs found</td></tr>';
        return;
    }
    
    let html = '';
    onts.forEach(ont => {
        let signalClass = 'offline';
        let signalText = 'N/A';
        
        if (ont.status === 'online') {
            if (ont.rx_power >= -25) { signalClass = 'good'; signalText = 'Good'; }
            else if (ont.rx_power >= -28) { signalClass = 'weak'; signalText = 'Weak'; }
            else { signalClass = 'critical'; signalText = 'Critical'; }
        }
        
        html += `
            <tr>
                <td><code>${ont.port}</code></td>
                <td><code style="font-weight:600;">${ont.onu_serial}</code></td>
                <td>${ont.onu_type || 'G-97Z4'}</td>
                <td>
                    <span class="signal-badge ${signalClass}">
                        <i class="fa fa-signal"></i> ${ont.rx_power ? ont.rx_power + ' dBm' : 'N/A'}
                    </span>
                </td>
                <td>${ont.tx_power ? ont.tx_power + ' dBm' : 'N/A'}</td>
                <td>
                    <span class="status-badge ${ont.status}">
                        ${ont.status.toUpperCase()}
                    </span>
                </td>
                <td>
                    <button class="action-btn info" title="View Details" onclick="viewONT('${ont.onu_serial}')">
                        <i class="fa fa-eye"></i>
                    </button>
                    <button class="action-btn warning" title="Reboot" onclick="openActionModal('${ont.onu_serial}', 'reboot')">
                        <i class="fa fa-sync"></i>
                    </button>
                    <button class="action-btn ${ont.status === 'online' ? 'danger' : 'success'}" 
                            title="${ont.status === 'online' ? 'Disable' : 'Enable'}"
                            onclick="openActionModal('${ont.onu_serial}', '${ont.status === 'online' ? 'disable' : 'enable'}')">
                        <i class="fa fa-${ont.status === 'online' ? 'ban' : 'check'}"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    document.getElementById('ontTableBody').innerHTML = html;
}

function renderPONPorts() {
    // Generate PON port data
    const ports = [];
    for (let i = 1; i <= 8; i++) {
        const total = Math.floor(Math.random() * 30) + 20;
        const online = Math.floor(Math.random() * total);
        const offline = total - online;
        ports.push({ port: `0/${i}`, total, online, offline });
    }
    
    let html = '';
    ports.forEach(p => {
        const status = p.online > 20 ? 'online' : (p.online > 10 ? 'warning' : 'offline');
        html += `
            <div class="pon-port" onclick="filterByPort('0/${p.port}')">
                <div class="port-num">PON 0/${p.port}</div>
                <div class="port-onus">${p.online}/${p.total} Online</div>
                <div class="port-status ${status}"></div>
            </div>
        `;
    });
    
    document.getElementById('ponGrid').innerHTML = html;
}

function filterONTs() {
    const search = document.getElementById('ontSearch').value.toLowerCase();
    const status = document.getElementById('ontStatusFilter').value;
    
    let filtered = ontData.filter(ont => {
        const matchSearch = !search || 
            ont.onu_serial.toLowerCase().includes(search) || 
            (ont.port && ont.port.toLowerCase().includes(search));
        const matchStatus = !status || ont.status === status;
        return matchSearch && matchStatus;
    });
    
    renderONTTable(filtered);
}

function filterByPort(port) {
    let filtered = ontData.filter(ont => ont.port === port);
    renderONTTable(filtered);
}

let selectedONT = '';
let selectedAction = '';

function openActionModal(serial, action) {
    selectedONT = serial;
    selectedAction = action;
    document.getElementById('ontSerialDisplay').textContent = `ONT: ${serial}`;
    document.getElementById('ontActionModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('ontActionModal').style.display = 'none';
}

function ontAction(action) {
    if (!confirm(`Are you sure you want to ${action} ONT ${selectedONT}?`)) return;
    
    fetch('api/olt_ont.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=ont_${action}&olt_id=${currentOLT}&serial=${selectedONT}`
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            closeModal();
            loadOLTData();
        }
    });
}

function viewONT(serial) {
    alert(`ONT Details for ${serial}\n\nThis would show detailed information including signal history, connection time, etc.`);
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
