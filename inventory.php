<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Stock Management Dashboard";
$active = "inventory";

// 1. Add Stock Logic
if (isset($_POST['add_stock'])) {
    $name = $_POST['item_name'];
    $brand = $_POST['brand'];
    $sn = $_POST['serial_number'];
    $mac = $_POST['mac_address'];
    $stmt = $conn->prepare("INSERT INTO inventory_items (item_name, brand, serial_number, mac_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $brand, $sn, $mac);
    $stmt->execute();
}

// 2. Issue Stock Logic
if (isset($_POST['issue_stock'])) {
    $id = $_POST['item_id'];
    $user = $_POST['customer_user'];
    $stmt = $conn->prepare("UPDATE inventory_items SET status='issued', issued_to_user=? WHERE id=?");
    $stmt->bind_param("si", $user, $id);
    $stmt->execute();
    $conn->query("UPDATE customers SET onu_mac = (SELECT mac_address FROM inventory_items WHERE id=$id) WHERE username='$user'");
}

// 3. Handle Item Return
if (isset($_GET['return'])) {
    $id = intval($_GET['return']);
    $conn->query("UPDATE inventory_items SET status='in_stock', issued_to_user=NULL WHERE id=$id");
    header("Location: inventory.php?msg=returned");
    exit;
}

// 4. Handle Mark Faulty
if (isset($_GET['faulty'])) {
    $id = intval($_GET['faulty']);
    $conn->query("UPDATE inventory_items SET status='faulty' WHERE id=$id");
    header("Location: inventory.php?msg=faulty");
    exit;
}

// Fetch Data for Charts & Table
$type_stats = $conn->query("SELECT item_name, COUNT(*) as count FROM inventory_items GROUP BY item_name")->fetch_all(MYSQLI_ASSOC);
$status_stats = $conn->query("SELECT status, COUNT(*) as count FROM inventory_items GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$recent = $conn->query("SELECT * FROM inventory_items WHERE status != 'in_stock' ORDER BY created_at DESC LIMIT 5");
$items = $conn->query("SELECT * FROM inventory_items ORDER BY status ASC, created_at DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 25px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-boxes-stacked"></i> Stock Management</h2>
        <button onclick="document.getElementById('addStockModal').style.display='block'" class="btn btn-primary">
            <i class="fa fa-plus-circle"></i> Register New Stock
        </button>
    </div>

    <!-- Analytics Row -->
    <div style="display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div style="background:#fff; padding:25px; border-radius:15px; box-shadow:0 4px 6px rgba(0,0,0,0.02); border:1px solid #f1f5f9;">
            <h4 style="margin-top:0; font-size:14px; color:#64748b; text-transform:uppercase;">Stock Distribution</h4>
            <div style="height: 200px;"><canvas id="typeChart"></canvas></div>
        </div>

        <div style="background:#1e293b; padding:25px; border-radius:15px; color:#fff;">
            <h4 style="margin-top:0; font-size:12px; opacity:0.6; text-transform:uppercase;">Warehouse Health</h4>
            <?php foreach($status_stats as $s): 
                $color = ($s['status'] == 'in_stock') ? '#10b981' : (($s['status'] == 'issued') ? '#3b82f6' : '#ef4444');
            ?>
                <div style="display:flex; justify-content:space-between; margin-top:15px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:8px;">
                    <span><?= ucfirst(str_replace('_', ' ', $s['status'])) ?></span>
                    <b style="color:<?= $color ?>;"><?= $s['count'] ?></b>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="background:#fff; padding:25px; border-radius:15px; box-shadow:0 4px 6px rgba(0,0,0,0.02); border:1px solid #f1f5f9;">
            <h4 style="margin-top:0; font-size:14px; color:#64748b; text-transform:uppercase;">Recent Activity</h4>
            <?php while($r = $recent->fetch_assoc()): ?>
                <div style="margin-top:10px; font-size:12px; border-left:3px solid #3b82f6; padding-left:10px;">
                    <b><?= $r['item_name'] ?></b><br><small>Issued to: <?= $r['issued_to_user'] ?></small>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;">Hardware</th>
                    <th style="padding: 15px;">SN / MAC</th>
                    <th style="padding: 15px;">Status</th>
                    <th style="padding: 15px;">Location</th>
                    <th style="padding: 15px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($i = $items->fetch_assoc()): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 15px;"><b><?= $i['item_name'] ?></b><br><small><?= $i['brand'] ?></small></td>
                    <td style="padding: 15px; font-size:11px;">SN: <?= $i['serial_number'] ?><br>MAC: <?= $i['mac_address'] ?></td>
                    <td style="padding: 15px;"><span class="badge" style="background:<?= ($i['status'] == 'in_stock') ? '#dcfce7; color:#16a34a;' : '#eff6ff; color:#3b82f6;' ?>"><?= strtoupper($i['status']) ?></span></td>
                    <td style="padding: 15px;"><?= $i['issued_to_user'] ?: 'Warehouse' ?></td>
                    <td style="padding: 15px;">
                        <div style="display:flex; gap:5px;">
                            <?php if($i['status'] == 'in_stock'): ?>
                                <button onclick="openIssueModal(<?= $i['id'] ?>, '<?= $i['item_name'] ?>')" class="btn-action-sm" style="background:#3b82f6; color:#fff; border:none; padding:5px 8px; border-radius:4px; cursor:pointer;">Issue</button>
                            <?php else: ?>
                                <a href="?return=<?= $i['id'] ?>" class="btn-action-sm" style="background:#64748b; color:#fff; padding:5px 8px; border-radius:4px; text-decoration:none; font-size:11px;">Return</a>
                            <?php endif; ?>
                            <a href="?faulty=<?= $i['id'] ?>" style="color:#ef4444; padding:5px;"><i class="fa fa-triangle-exclamation"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Stock -->
<div id="addStockModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(3px);">
    <div style="background:#fff; margin:5% auto; padding:25px; border-radius:15px; width:400px; box-shadow:0 20px 40px rgba(0,0,0,0.2);">
        <h3>Register New Hardware</h3>
        <form method="POST">
            <label>Category</label>
            <select name="item_name" class="form-control" style="width:100%; padding:10px; margin-bottom:15px;">
                <option value="XPON ONU">XPON ONU</option>
                <option value="WiFi 6 Router">WiFi 6 Router</option>
                <option value="Fiber Roll">Fiber Roll</option>
            </select>
            <label>Brand</label><input type="text" name="brand" class="form-control" style="width:100%; padding:10px; margin-bottom:15px;">
            <label>Serial Number</label><input type="text" name="serial_number" class="form-control" style="width:100%; padding:10px; margin-bottom:15px;" required>
            <label>MAC Address</label><input type="text" name="mac_address" class="form-control" style="width:100%; padding:10px; margin-bottom:15px;">
            <button type="submit" name="add_stock" class="btn btn-primary" style="width:100%;">Save to Warehouse</button>
            <button type="button" onclick="document.getElementById('addStockModal').style.display='none'" style="width:100%; margin-top:10px; border:none; background:none;">Cancel</button>
        </form>
    </div>
</div>

<!-- Modal: Issue Stock -->
<div id="issueModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(3px);">
    <div style="background:#fff; margin:10% auto; padding:25px; border-radius:15px; width:400px;">
        <h3>Issue Hardware</h3>
        <p id="issueItemName" style="color:#64748b; font-weight:600;"></p>
        <form method="POST">
            <input type="hidden" name="item_id" id="issueItemId">
            <label>Customer Username</label>
            <input type="text" name="customer_user" class="form-control" style="width:100%; padding:10px; margin-bottom:15px;" required>
            <button type="submit" name="issue_stock" class="btn btn-primary" style="width:100%;">Complete Assignment</button>
            <button type="button" onclick="document.getElementById('issueModal').style.display='none'" style="width:100%; margin-top:10px; border:none; background:none;">Cancel</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('typeChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($type_stats, 'item_name')) ?>,
            datasets: [{ data: <?= json_encode(array_column($type_stats, 'count')) ?>, backgroundColor: '#3b82f6', borderRadius: 5 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
    function openIssueModal(id, name) {
        document.getElementById('issueItemId').value = id;
        document.getElementById('issueItemName').innerText = name;
        document.getElementById('issueModal').style.display = 'block';
    }
</script>
<?php include 'includes/footer.php'; ?>
