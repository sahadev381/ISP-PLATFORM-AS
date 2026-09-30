<?php
session_start();
$page_title = "Leads Management";
$base_path = '';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';

$lead_statuses = ['new', 'contacted', 'qualified', 'converted', 'lost'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    csrf_check();

    $lead_id = intval($_POST['lead_id'] ?? 0);

    if ($_POST['action'] == 'add_lead') {
        db_exec($conn, "INSERT INTO leads (name, phone, email, company, address, plan_interested, source, status, created_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'new', ?)", [
            $_POST['name'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['email'] ?? '',
            $_POST['company'] ?? '',
            $_POST['address'] ?? '',
            $_POST['plan_interested'] ?? '',
            $_POST['source'] ?? '',
            (int) $_SESSION['user_id'],
        ]);
        $message = 'Lead added successfully';
    }

    if ($_POST['action'] == 'update_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, $lead_statuses, true)) {
            $message = 'Invalid status';
        } else {
            db_exec($conn, "UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $lead_id]);
            $message = 'Lead status updated';
        }
    }

    if ($_POST['action'] == 'delete_lead') {
        db_exec($conn, "DELETE FROM leads WHERE id = ?", [$lead_id]);
        $message = 'Lead deleted';
    }

    if ($_POST['action'] == 'convert_lead') {
        $lead = db_one($conn, "SELECT * FROM leads WHERE id = ?", [$lead_id]);
        if (!$lead) {
            $message = 'Lead not found';
        } elseif ($lead['status'] === 'converted') {
            // Without this check, converting twice creates a duplicate customer.
            $message = 'Lead has already been converted';
        } else {
            // The username was derived from the name with no uniqueness check,
            // so two leads named the same silently collided on insert.
            $base = preg_replace('/[^a-z0-9]/', '', strtolower($lead['name'])) ?: 'customer';
            $username = $base;
            $suffix = 1;
            while (db_value($conn, "SELECT COUNT(*) FROM customers WHERE username = ?", [$username], 0) > 0) {
                $username = $base . (++$suffix);
            }

            db_exec($conn, "INSERT INTO customers (username, full_name, phone, email, address, created_at)
                          VALUES (?, ?, ?, ?, ?, NOW())",
                [$username, $lead['name'], $lead['phone'], $lead['email'], $lead['address']]);
            db_exec($conn, "UPDATE leads SET status = 'converted', updated_at = NOW() WHERE id = ?", [$lead_id]);
            $message = 'Lead converted to customer: ' . $username;
        }
    }
}

$filter_status = $_GET['status'] ?? '';
$where = '';
$where_params = [];
if ($filter_status !== '' && in_array($filter_status, $lead_statuses, true)) {
    $where = "WHERE status = ?";
    $where_params[] = $filter_status;
}

$leads = db_all($conn, "SELECT * FROM leads $where ORDER BY created_at DESC", $where_params);

$stats = [
    'total' => db_value($conn, "SELECT COUNT(*) FROM leads", [], 0),
    'new' => db_value($conn, "SELECT COUNT(*) FROM leads WHERE status = 'new'", [], 0),
    'qualified' => db_value($conn, "SELECT COUNT(*) FROM leads WHERE status = 'qualified'", [], 0),
    'converted' => db_value($conn, "SELECT COUNT(*) FROM leads WHERE status = 'converted'", [], 0),
];

$plans = db_all($conn, "SELECT * FROM plans ORDER BY name");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .lead-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .lead-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        border: 1px solid #e2e8f0;
    }
    .lead-card h4 { margin: 0 0 10px; font-size: 13px; color: #64748b; text-transform: uppercase; }
    .lead-card .num { font-size: 28px; font-weight: 700; color: #1e293b; }
    .lead-card.total { border-left: 4px solid #3b82f6; }
    .lead-card.new { border-left: 4px solid #10b981; }
    .lead-card.qualified { border-left: 4px solid #f59e0b; }
    .lead-card.converted { border-left: 4px solid #8b5cf6; }
    .filter-links { margin-bottom: 20px; }
    .filter-links a { margin-right: 15px; color: #64748b; text-decoration: none; font-size: 14px; }
    .filter-links a:hover, .filter-links a.active { color: #3b82f6; font-weight: 600; }
    .btn-new { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
    .btn-new:hover { background: #2563eb; }
    .action-btn { width: 30px; height: 30px; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .btn-view { background: #eff6ff; color: #3b82f6; }
    .btn-view:hover { background: #3b82f6; color: #fff; }
    .btn-edit { background: #fef3c7; color: #f59e0b; }
    .btn-edit:hover { background: #f59e0b; color: #fff; }
    .btn-delete { background: #fef2f2; color: #ef4444; }
    .btn-delete:hover { background: #ef4444; color: #fff; }
    .btn-convert { background: #ecfdf5; color: #10b981; }
    .btn-convert:hover { background: #10b981; color: #fff; }
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .badge-new { background: #dbeafe; color: #2563eb; }
    .badge-contacted { background: #fef3c7; color: #d97706; }
    .badge-qualified { background: #e0e7ff; color: #4f46e5; }
    .badge-proposal { background: #fce7f3; color: #db2777; }
    .badge-converted { background: #d1fae5; color: #059669; }
    .badge-lost { background: #fee2e2; color: #dc2626; }
    .fab-add {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #3b82f6;
        color: #fff;
        border: none;
        font-size: 24px;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        cursor: pointer;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    .fab-add:hover {
        transform: scale(1.1);
        background: #2563eb;
    }
    
    @media (max-width: 768px) {
        .lead-grid { grid-template-columns: 1fr 1fr; }
        .filter-links a { display: block; margin: 5px 0; }
        .table-box { overflow-x: auto; }
    }
    @media (max-width: 480px) {
        .lead-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="dashboard-container" style="padding: 20px;">
    
    <!-- Floating Add Button -->
    <button class="fab-add" data-bs-toggle="modal" data-bs-target="#addLeadModal" title="Add New Lead">
        <i class="fa fa-plus"></i>
    </button>
    
    <?php if ($message): ?>
    <div style="background: #dcfce7; color: #16a34a; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fa fa-check-circle"></i> <?= $message ?>
    </div>
    <?php endif; ?>
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0; color: #1e293b; font-weight: 700;">Leads Management</h3>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 13px;">Track and manage potential customers</p>
    </div>

    <!-- Stats Cards -->
    <div class="lead-grid">
        <div class="lead-card total">
            <h4><i class="fa fa-users"></i> Total Leads</h4>
            <div class="num"><?= $stats['total'] ?></div>
        </div>
        <div class="lead-card new">
            <h4><i class="fa fa-star"></i> New Leads</h4>
            <div class="num"><?= $stats['new'] ?></div>
        </div>
        <div class="lead-card qualified">
            <h4><i class="fa fa-check-circle"></i> Qualified</h4>
            <div class="num"><?= $stats['qualified'] ?></div>
        </div>
        <div class="lead-card converted">
            <h4><i class="fa fa-trophy"></i> Converted</h4>
            <div class="num"><?= $stats['converted'] ?></div>
        </div>
    </div>
    
    <!-- Filter Links -->
    <div class="filter-links">
        <a href="leads.php" class="<?= !$filter_status ? 'active' : '' ?>">All (<?= $stats['total'] ?>)</a>
        <a href="leads.php?status=new" class="<?= $filter_status == 'new' ? 'active' : '' ?>">New (<?= $stats['new'] ?>)</a>
        <a href="leads.php?status=contacted" class="<?= $filter_status == 'contacted' ? 'active' : '' ?>">Contacted</a>
        <a href="leads.php?status=qualified" class="<?= $filter_status == 'qualified' ? 'active' : '' ?>">Qualified</a>
        <a href="leads.php?status=proposal" class="<?= $filter_status == 'proposal' ? 'active' : '' ?>">Proposal</a>
        <a href="leads.php?status=converted" class="<?= $filter_status == 'converted' ? 'active' : '' ?>">Converted</a>
        <a href="leads.php?status=lost" class="<?= $filter_status == 'lost' ? 'active' : '' ?>">Lost</a>
            <i class="fa fa-plus"></i> Add Lead
        </button>
    </div>

    <!-- Leads Table -->
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-list"></i> All Leads</h3>
        </div>
        <table style="width:100%; min-width: 800px;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="padding: 12px; text-align: left;">ID</th>
                    <th style="padding: 12px; text-align: left;">Name</th>
                    <th style="padding: 12px; text-align: left;">Phone</th>
                    <th style="padding: 12px; text-align: left;">Email</th>
                    <th style="padding: 12px; text-align: left;">Company</th>
                    <th style="padding: 12px; text-align: left;">Plan</th>
                    <th style="padding: 12px; text-align: left;">Source</th>
                    <th style="padding: 12px; text-align: left;">Status</th>
                    <th style="padding: 12px; text-align: left;">Created</th>
                    <th style="padding: 12px; text-align: left;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px;">#<?= $lead['id'] ?></td>
                    <td style="padding: 12px;"><strong><?= htmlspecialchars($lead['name']) ?></strong></td>
                    <td style="padding: 12px; font-family: monospace;"><?= $lead['phone'] ?: '-' ?></td>
                    <td style="padding: 12px;"><?= $lead['email'] ?: '-' ?></td>
                    <td style="padding: 12px;"><?= $lead['company'] ?: '-' ?></td>
                    <td style="padding: 12px;"><span style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 12px;"><?= $lead['plan_interested'] ?: '-' ?></span></td>
                    <td style="padding: 12px;"><?= ucfirst($lead['source'] ?: '-') ?></td>
                    <td style="padding: 12px;">
                        <span class="badge badge-<?= $lead['status'] ?>"><?= ucfirst($lead['status']) ?></span>
                    </td>
                    <td style="padding: 12px; color: #64748b; font-size: 13px;"><?= date('M d, Y', strtotime($lead['created_at'])) ?></td>
                    <td style="padding: 12px;">
                        <div style="display: flex; gap: 5px;">
                            <button class="action-btn btn-view" onclick="viewLead(<?= $lead['id'] ?>)" title="View"><i class="fa fa-eye"></i></button>
                            <button class="action-btn btn-edit" onclick="editLead(<?= $lead['id'] ?>)" title="Edit"><i class="fa fa-edit"></i></button>
                            <?php if ($lead['status'] != 'converted'): ?>
                                <button class="action-btn btn-convert" onclick="convertLead(<?= $lead['id'] ?>)" title="Convert"><i class="fa fa-user-plus"></i></button>
                            <?php endif; ?>
                            <button class="action-btn btn-delete" onclick="deleteLead(<?= $lead['id'] ?>)" title="Delete"><i class="fa fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Lead Modal -->
<div class="modal fade" id="addLeadModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5>Add New Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_lead">
                    <div class="mb-3">
                        <label>Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Company</label>
                        <input type="text" name="company" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Plan Interested</label>
                        <select name="plan_interested" class="form-select">
                            <option value="">Select Plan</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['name'] ?>"><?= $p['name'] ?> - Rs.<?= $p['price'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Source</label>
                        <select name="source" class="form-select">
                            <option value="website">Website</option>
                            <option value="facebook">Facebook</option>
                            <option value="referral">Referral</option>
                            <option value="call">Phone Call</option>
                            <option value="visit">Field Visit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewLead(id) { alert('View lead #' + id); }
    function editLead(id) { alert('Edit lead #' + id); }
    function convertLead(id) {
        if (confirm('Convert this lead to customer?')) {
            submitAction('convert_lead', id);
        }
    }
    function deleteLead(id) {
        if (confirm('Are you sure you want to delete this lead?')) {
            submitAction('delete_lead', id);
        }
    }
    function submitAction(action, id) {
        let form = document.createElement('form');
        form.method = 'POST';
        // These dynamically built forms bypass the normal csrf_field() markup,
        // so the token has to be attached explicitly.
        form.innerHTML = `<input type="hidden" name="action" value="${action}">`
            + `<input type="hidden" name="lead_id" value="${id}">`
            + `<input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">`;
        document.body.appendChild(form);
        form.submit();
    }
</script>

<?php include 'includes/footer.php'; ?>
