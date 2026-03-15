<?php
session_start();
$page_title = "Hotspot Plans";
$page = 'plans';
$base_path = '.';

chdir(__DIR__ . '/../..');
include_once 'config.php';
include_once 'includes/auth.php';
include_once 'hotspot/includes/plan_manager.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$planMgr = new PlanManager();

$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Generate PINs
    if ($action == 'generate_pins') {
        $profileId = $_POST['profile_id'] ?? 1;
        $count = (int)($_POST['count'] ?? 10);
        
        $pins = $planMgr->generatePINs($profileId, $count);
        $message = "Generated " . count($pins) . " PINs: " . implode(', ', array_slice($pins, 0, 10));
        if (count($pins) > 10) $message .= "...";
    }
    
    // Create new plan
    if ($action == 'create_plan') {
        $planId = $planMgr->createPlan($_POST);
        $message = "Plan created successfully!";
    }
    
    // Delete plan
    if ($action == 'delete_plan' && isset($_POST['plan_id'])) {
        $planMgr->deletePlan($_POST['plan_id']);
        $message = "Plan deleted";
    }
    
    // Generate vouchers
    if ($action == 'generate_vouchers' && isset($_POST['voucher_type_id'])) {
        $result = $planMgr->generateVouchers($_POST['voucher_type_id'], $_POST['count'] ?? 10);
        if ($result['status'] == 'success') {
            $message = "Generated {$result['count']} vouchers";
        } else {
            $message = $result['message'];
        }
    }
    
    // Create invoice
    if ($action == 'create_invoice') {
        $result = $planMgr->createInvoice($_POST['user_id'], $_POST['plan_id']);
        if ($result['status'] == 'success') {
            $message = "Invoice {$result['invoice_number']} created for Rs.{$result['total']}";
        }
    }
}

// Get data
$profiles = $planMgr->getAllPlans();
$voucherTypes = $planMgr->getAllVoucherTypes();
$stats = $planMgr->getStats();

// Get old profiles
$oldProfiles = $conn->query("SELECT * FROM hotspot_profiles ORDER BY type, name");

include 'includes/header_hotspot.php';
?>

<div class="row mb-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
    <div class="stat-card" style="border-left: 4px solid var(--primary);">
        <div class="stat-icon blue"><i class="fa fa-tags"></i></div>
        <div>
            <div class="stat-label">Total Plans</div>
            <div class="stat-value"><?= $stats['total_plans'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card" style="border-left: 4px solid var(--success);">
        <div class="stat-icon green"><i class="fa fa-check-circle"></i></div>
        <div>
            <div class="stat-label">Available PINs</div>
            <div class="stat-value"><?= $stats['available_vouchers'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card" style="border-left: 4px solid var(--info);">
        <div class="stat-icon" style="background: #cffafe; color: var(--info);"><i class="fa fa-user-check"></i></div>
        <div>
            <div class="stat-label">Used PINs</div>
            <div class="stat-value"><?= $stats['used_vouchers'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card" style="border-left: 4px solid var(--warning);">
        <div class="stat-icon orange"><i class="fa fa-sack-dollar"></i></div>
        <div>
            <div class="stat-label">Revenue (Month)</div>
            <div class="stat-value">Rs.<?= number_format($stats['revenue_month'] ?? 0, 0) ?></div>
        </div>
    </div>
</div>
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5>Pending</h5>
                    <h3>Rs.<?= number_format($stats['pending_invoices'], 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5>Voucher Types</h5>
                    <h3><?= count($voucherTypes) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="planTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#plans">New Plans</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profiles">Profiles (Old)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pins">Generate PINs</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vouchers">Voucher Types</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#invoices">Invoices</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- New Plans Tab -->
        <div class="tab-pane fade show active" id="plans">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-plus-circle"></i> Create New Plan</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="create_plan">
                        
                        <div class="col-md-3">
                            <label>Plan Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label>Type</label>
                            <select name="type" class="form-select">
                                <option value="prepaid">Prepaid</option>
                                <option value="postpaid">Postpaid</option>
                                <option value="smart_bytes">Smart Bytes</option>
                                <option value="daily">Daily</option>
                                <option value="day_night">Day & Night</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Data Limit (MB)</label>
                            <input type="number" name="data_limit_mb" class="form-control" value="0" placeholder="0=Unlimited">
                        </div>
                        <div class="col-md-2">
                            <label>Speed (Kbps)</label>
                            <input type="number" name="speed_kbps" class="form-control" value="1024">
                        </div>
                        <div class="col-md-2">
                            <label>Price (Rs.)</label>
                            <input type="number" name="price" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-1">
                            <label>Days</label>
                            <input type="number" name="validity_days" class="form-control" value="30">
                        </div>
                        <div class="col-md-12">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Plans</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Data</th>
                                <th>Speed</th>
                                <th>Price</th>
                                <th>Validity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profiles as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><span class="badge bg-<?= $p['type'] == 'prepaid' ? 'success' : ($p['type'] == 'postpaid' ? 'danger' : 'info') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $p['type'])) ?>
                                </span></td>
                                <td><?= $p['data_limit_mb'] > 0 ? $p['data_limit_mb'] . ' MB' : 'Unlimited' ?></td>
                                <td><?= round($p['speed_kbps']/1024) ?> Mbps</td>
                                <td>Rs.<?= $p['price'] ?></td>
                                <td><?= $p['validity_days'] ?> days</td>
                                <td><span class="badge bg-<?= $p['status'] == 'active' ? 'success' : 'secondary' ?>"><?= $p['status'] ?></span></td>
                                <td>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="delete_plan">
                                        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this plan?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Old Profiles Tab -->
        <div class="tab-pane fade" id="profiles">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tags"></i> Current Profiles (Voucher-based)</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Data</th>
                                <th>Validity</th>
                                <th>Price</th>
                                <th>Speed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $oldProfiles->fetch_assoc()): ?>
                            <tr>
                                <td><?= $p['name'] ?></td>
                                <td><?= ucfirst($p['type']) ?></td>
                                <td><?= $p['data_limit_mb'] > 0 ? $p['data_limit_mb'] . ' MB' : 'Unlimited' ?></td>
                                <td><?= $p['validity_hours'] ?> hrs</td>
                                <td>Rs.<?= $p['price'] ?></td>
                                <td><?= round($p['speed_kbps']/1024) ?> Mbps</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Generate PINs Tab -->
        <div class="tab-pane fade" id="pins">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-ticket-alt"></i> Generate 4-Digit PINs</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="generate_pins">
                        
                        <div class="col-md-4">
                            <label>Select Profile</label>
                            <select name="profile_id" class="form-select">
                                <?php 
                                $oldProfiles = $conn->query("SELECT * FROM hotspot_profiles WHERE status='active'");
                                while ($p = $oldProfiles->fetch_assoc()): ?>
                                    <option value="<?= $p['id'] ?>"><?= $p['name'] ?> - Rs.<?= $p['price'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Number of PINs</label>
                            <select name="count" class="form-select">
                                <option value="10">10 PINs</option>
                                <option value="20">20 PINs</option>
                                <option value="50">50 PINs</option>
                                <option value="100">100 PINs</option>
                                <option value="500">500 PINs</option>
                                <option value="1000">1000 PINs</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Generate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Voucher Types Tab -->
        <div class="tab-pane fade" id="vouchers">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-plus-circle"></i> Add Voucher Type</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="generate_vouchers">
                        
                        <div class="col-md-3">
                            <label>Voucher Type</label>
                            <select name="voucher_type_id" class="form-select">
                                <?php foreach ($voucherTypes as $vt): ?>
                                    <option value="<?= $vt['id'] ?>"><?= $vt['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Quantity</label>
                            <select name="count" class="form-select">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Generate
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Available Voucher Types</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Price</th>
                                <th>Validity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($voucherTypes as $vt): ?>
                            <tr>
                                <td><?= $vt['name'] ?></td>
                                <td><span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $vt['type'])) ?></span></td>
                                <td><?= $vt['value'] ?><?= $vt['unit'] ?></td>
                                <td>Rs.<?= $vt['price'] ?></td>
                                <td><?= $vt['validity_days'] ?> days</td>
                                <td><span class="badge bg-<?= $vt['status'] == 'active' ? 'success' : 'secondary' ?>"><?= $vt['status'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Invoices Tab -->
        <div class="tab-pane fade" id="invoices">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-file-invoice"></i> Recent Invoices</h5>
                </div>
                <div class="card-body">
                    <?php
                    $invoices = $conn->query("
                        SELECT i.*, u.username, u.phone
                        FROM hotspot_invoices i
                        LEFT JOIN hotspot_users u ON i.user_id = u.id
                        ORDER BY i.created_at DESC LIMIT 50
                    ");
                    ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Tax</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($inv = $invoices->fetch_assoc()): ?>
                            <tr>
                                <td><?= $inv['invoice_number'] ?></td>
                                <td><?= $inv['username'] ?? 'N/A' ?></td>
                                <td>Rs.<?= $inv['amount'] ?></td>
                                <td>Rs.<?= $inv['tax'] ?></td>
                                <td>Rs.<?= $inv['total'] ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $inv['status'] == 'paid' ? 'success' : 
                                        ($inv['status'] == 'pending' ? 'warning' : 'danger') 
                                    ?>">
                                        <?= $inv['status'] ?>
                                    </span>
                                </td>
                                <td><?= $inv['due_date'] ?></td>
                                <td><?= date('M d, H:i', strtotime($inv['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
