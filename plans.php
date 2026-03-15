<?php
$base_path = './';
include 'config.php';
include 'includes/auth.php';

/* Page info */
$page_title = "Service Plans & FUP";
$active = "plans";

/* Plan Form Template */
function renderPlanForm($id_prefix, $isEdit = false) { ?>
    <form method="post">
        <?php if($isEdit) echo '<input type="hidden" name="id" id="edit_id">'; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Plan Name</label>
                <input class="form-control" name="name" id="<?= $id_prefix ?>_name" required>
            </div>
            <div class="form-group">
                <label>Base Speed</label>
                <input class="form-control" name="speed" id="<?= $id_prefix ?>_speed" placeholder="e.g. 100M/100M" required>
            </div>
            <div class="form-group">
                <label>Price (NPR)</label>
                <input type="number" step="0.01" class="form-control" name="price" id="<?= $id_prefix ?>_price" required>
            </div>
            <div class="form-group">
                <label>Validity (Days)</label>
                <input type="number" class="form-control" name="validity" id="<?= $id_prefix ?>_validity" value="30" required>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>Total Data Quota (GB)</label>
                <input type="number" class="form-control" name="data_limit" id="<?= $id_prefix ?>_data_limit" value="1000">
            </div>
        </div>

        <div class="fup-section">
            <span class="fup-title"><i class="fa fa-bolt"></i> FUP Tier 1</span>
            <div class="form-grid">
                <div class="form-group">
                    <label>Limit (GB)</label>
                    <input type="number" class="form-control" name="fup1_limit" id="<?= $id_prefix ?>_fup1_limit">
                </div>
                <div class="form-group">
                    <label>FUP Speed</label>
                    <input class="form-control" name="fup1_speed" id="<?= $id_prefix ?>_fup1_speed" placeholder="e.g. 50M/50M">
                </div>
            </div>
        </div>

        <div class="fup-section">
            <span class="fup-title"><i class="fa fa-bolt"></i> FUP Tier 2</span>
            <div class="form-grid">
                <div class="form-group">
                    <label>Limit (GB)</label>
                    <input type="number" class="form-control" name="fup2_limit" id="<?= $id_prefix ?>_fup2_limit">
                </div>
                <div class="form-group">
                    <label>FUP Speed</label>
                    <input class="form-control" name="fup2_speed" id="<?= $id_prefix ?>_fup2_speed" placeholder="e.g. 20M/20M">
                </div>
            </div>
        </div>

        <div class="fup-section">
            <span class="fup-title"><i class="fa fa-bolt"></i> FUP Tier 3</span>
            <div class="form-grid">
                <div class="form-group">
                    <label>Limit (GB)</label>
                    <input type="number" class="form-control" name="fup3_limit" id="<?= $id_prefix ?>_fup3_limit">
                </div>
                <div class="form-group">
                    <label>FUP Speed</label>
                    <input class="form-control" name="fup3_speed" id="<?= $id_prefix ?>_fup3_speed" placeholder="e.g. 10M/10M">
                </div>
            </div>
        </div>

        <button type="submit" name="<?= $isEdit?'edit':'add' ?>" class="btn btn-primary" style="width: 100%; margin-top: 20px; padding: 12px; font-weight: 700;">
            <i class="fa fa-save"></i> <?= $isEdit?'Update':'Save' ?> Plan
        </button>
    </form>
<?php }

/* Handle Add Plan */
if(isset($_POST['add'])){
    $name       = $conn->real_escape_string($_POST['name']);
    $speed      = $conn->real_escape_string($_POST['speed']);
    $price      = $conn->real_escape_string($_POST['price']);
    $validity   = $conn->real_escape_string($_POST['validity']);
    $data_limit = (float)$_POST['data_limit'] * 1073741824;
    
    $fup1_limit = (float)$_POST['fup1_limit'] * 1073741824;
    $fup1_speed = $conn->real_escape_string($_POST['fup1_speed']);
    $fup2_limit = (float)$_POST['fup2_limit'] * 1073741824;
    $fup2_speed = $conn->real_escape_string($_POST['fup2_speed']);
    $fup3_limit = (float)($_POST['fup3_limit']??0) * 1073741824;
    $fup3_speed = $conn->real_escape_string($_POST['fup3_speed']??'');

    $conn->query("
        INSERT INTO plans (name, speed, price, validity, data_limit, fup1_limit, fup1_speed, fup2_limit, fup2_speed, fup3_limit, fup3_speed)
        VALUES ('$name', '$speed', '$price', '$validity', '$data_limit', '$fup1_limit', '$fup1_speed', '$fup2_limit', '$fup2_speed', '$fup3_limit', '$fup3_speed')
    ");
    header("Location: plans.php?msg=added");
    exit;
}

/* Handle Delete Plan */
if(isset($_GET['del'])){
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM plans WHERE id='$id'");
    header("Location: plans.php?msg=deleted");
    exit;
}

/* Handle Edit Plan */
if(isset($_POST['edit'])){
    $id         = (int)$_POST['id'];
    $name       = $conn->real_escape_string($_POST['name']);
    $speed      = $conn->real_escape_string($_POST['speed']);
    $price      = $conn->real_escape_string($_POST['price']);
    $validity   = $conn->real_escape_string($_POST['validity']);
    $data_limit = (float)$_POST['data_limit'] * 1073741824;
    
    $fup1_limit = (float)$_POST['fup1_limit'] * 1073741824;
    $fup1_speed = $conn->real_escape_string($_POST['fup1_speed']);
    $fup2_limit = (float)$_POST['fup2_limit'] * 1073741824;
    $fup2_speed = $conn->real_escape_string($_POST['fup2_speed']);
    $fup3_limit = (float)($_POST['fup3_limit']??0) * 1073741824;
    $fup3_speed = $conn->real_escape_string($_POST['fup3_speed']??'');

    $conn->query("
        UPDATE plans
        SET name='$name', speed='$speed', price='$price', validity='$validity', data_limit='$data_limit',
            fup1_limit='$fup1_limit', fup1_speed='$fup1_speed',
            fup2_limit='$fup2_limit', fup2_speed='$fup2_speed',
            fup3_limit='$fup3_limit', fup3_speed='$fup3_speed'
        WHERE id='$id'
    ");
    header("Location: plans.php?msg=updated");
    exit;
}

$plans_res = $conn->query("SELECT * FROM plans ORDER BY id DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .plans-container { padding: 25px; }
    .table-card { background: #fff; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: #f8fafc; padding: 15px 20px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; }
    td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; }
    tr:hover { background: #f8fafc; }
    
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); overflow-y: auto; }
    .modal-content { background: #fff; margin: 2% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 650px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideDown 0.3s ease-out; }
    @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .modal-header { padding: 20px 25px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .modal-body { padding: 25px; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 13px; }
    .form-control { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
    
    .fup-section { background: #f8fafc; padding: 15px; border-radius: 10px; margin-top: 15px; border: 1px dashed #cbd5e1; }
    .fup-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 10px; display: block; }

    .plan-tab-btn { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .plan-tab-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
</style>

<div class="plans-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="font-size: 20px; color: #1e293b; font-weight: 700;">Service Plans & FUP</h2>
        <div style="display: flex; gap: 10px;">
            <button onclick="showPlanTab('list')" class="plan-tab-btn active" id="tab-btn-list">Plan List</button>
            <button onclick="showPlanTab('fuptest')" class="plan-tab-btn" id="tab-btn-fuptest">FUP Test Tool</button>
            <button onclick="openModal('addPlanModal')" class="btn btn-primary"><i class="fa fa-plus-circle"></i> Create New Plan</button>
        </div>
    </div>

    <div id="plan-list-tab" class="plan-tab-content">
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Base Speed</th>
                        <th>FUP Tiers</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($p = $plans_res->fetch_assoc()): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($p['name']) ?></b></td>
                        <td><span class="badge" style="background:#eff6ff; color:#3b82f6;"><?= htmlspecialchars($p['speed']) ?></span></td>
                        <td>
                            <div style="font-size: 11px; line-height: 1.4;">
                                <?php if($p['fup1_limit'] > 0): ?>
                                    <span style="color:#64748b;">T1: <?= round($p['fup1_limit']/1073741824) ?>GB &rarr; <?= $p['fup1_speed'] ?></span><br>
                                <?php endif; ?>
                                <?php if($p['fup2_limit'] > 0): ?>
                                    <span style="color:#64748b;">T2: <?= round($p['fup2_limit']/1073741824) ?>GB &rarr; <?= $p['fup2_speed'] ?></span><br>
                                <?php endif; ?>
                                <?php if($p['fup3_limit'] > 0): ?>
                                    <span style="color:#64748b;">T3: <?= round($p['fup3_limit']/1073741824) ?>GB &rarr; <?= $p['fup3_speed'] ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge" style="background:#f0fdf4; color:#16a34a;">NPR <?= number_format($p['price']) ?></span></td>
                        <td>
                            <button onclick='openEditModal(<?= json_encode($p) ?>)' class="btn-icon btn-edit"><i class="fa fa-edit"></i></button>
                            <a href="?del=<?= $p['id'] ?>" class="btn-icon btn-delete" onclick="return confirm('Delete plan?')"><i class="fa fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="plan-fuptest-tab" class="plan-tab-content" style="display:none;">
        <div class="table-card" style="height: 800px;">
            <iframe src="fup_test.php" style="width:100%; height:100%; border:none; border-radius:15px;"></iframe>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="addPlanModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Create New Plan</h3><span onclick="closeModal('addPlanModal')" style="cursor:pointer;">&times;</span></div>
        <div class="modal-body"><?php renderPlanForm('add', false); ?></div>
    </div>
</div>

<div id="editPlanModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Edit Plan</h3><span onclick="closeModal('editPlanModal')" style="cursor:pointer;">&times;</span></div>
        <div class="modal-body"><?php renderPlanForm('edit', true); ?></div>
    </div>
</div>

<script>
function openModal(id) {
    var modal = document.getElementById(id);
    if(modal) modal.style.display = 'block';
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if(modal) modal.style.display = 'none';
}

function showPlanTab(tab) {
    document.querySelectorAll('.plan-tab-content').forEach(function(el) { el.style.display = 'none'; });
    document.querySelectorAll('.plan-tab-btn').forEach(function(el) { el.classList.remove('active'); });
    
    var content = document.getElementById('plan-' + tab + '-tab');
    var btn = document.getElementById('tab-btn-' + tab);
    if(content) content.style.display = 'block';
    if(btn) btn.classList.add('active');
}

function openEditModal(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_name').value = p.name;
    document.getElementById('edit_speed').value = p.speed;
    document.getElementById('edit_price').value = p.price;
    document.getElementById('edit_validity').value = p.validity;
    document.getElementById('edit_data_limit').value = p.data_limit / 1073741824;
    document.getElementById('edit_fup1_limit').value = p.fup1_limit / 1073741824;
    document.getElementById('edit_fup1_speed').value = p.fup1_speed;
    document.getElementById('edit_fup2_limit').value = p.fup2_limit / 1073741824;
    document.getElementById('edit_fup2_speed').value = p.fup2_speed;
    document.getElementById('edit_fup3_limit').value = p.fup3_limit / 1073741824;
    document.getElementById('edit_fup3_speed').value = p.fup3_speed;
    openModal('editPlanModal');
}

window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
