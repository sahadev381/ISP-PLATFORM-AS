<?php
session_start();
$page_title = "Roles & Permissions";
$base_path = '../..';

chdir(__DIR__ . '/../..');
$base_path = '.';
include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_path . '/login.php');
    exit;
}

$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add role
    if ($action == 'add_role') {
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $conn->query("INSERT INTO roles (name, description) VALUES ('$name', '$description')");
        $message = "Role created successfully!";
    }
    
    // Update permissions
    if ($action == 'save_permissions' && isset($_POST['role_id'])) {
        $roleId = (int)$_POST['role_id'];
        
        // Clear existing permissions
        $conn->query("DELETE FROM role_permissions WHERE role_id = $roleId");
        
        // Add new permissions
        if (!empty($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $perm) {
                $perm = $conn->real_escape_string($perm);
                $conn->query("INSERT INTO role_permissions (role_id, permission) VALUES ($roleId, '$perm')");
            }
        }
        $message = "Permissions updated!";
    }
    
    // Delete role
    if ($action == 'delete_role' && isset($_POST['role_id'])) {
        $roleId = (int)$_POST['role_id'];
        $conn->query("DELETE FROM role_permissions WHERE role_id = $roleId");
        $conn->query("DELETE FROM roles WHERE id = $roleId");
        $message = "Role deleted!";
    }
}

// Get all roles
$roles = $conn->query("SELECT * FROM roles ORDER BY id");

// Get all permissions
$permissions = [
    'dashboard' => 'Dashboard Access',
    'customers' => 'Customer Management',
    'users' => 'User Management',
    'hotspot' => 'Hotspot Portal',
    'plans_vouchers' => 'Plans & Vouchers',
    'hotel' => 'Hotel Solution',
    'access_control' => 'Access Control',
    'network' => 'Network Devices',
    'olt' => 'OLT Management',
    'mikrotik' => 'MikroTik Management',
    'tr069' => 'TR-069 Devices',
    'reports' => 'Reports',
    'finance' => 'Finance & Billing',
    'payments' => 'Payments',
    'inventory' => 'Inventory',
    'tickets' => 'Tickets',
    'settings' => 'System Settings',
    'admin_users' => 'Admin Users',
    'branches' => 'Branches',
    'roles' => 'Roles & Permissions'
];

// Get role permissions
$rolePermissions = [];
$perms = $conn->query("SELECT role_id, permission FROM role_permissions");
while ($p = $perms->fetch_assoc()) {
    $rolePermissions[$p['role_id']][] = $p['permission'];
}
?>
<?php include $base_path . '/includes/header.php'; ?>
<?php include $base_path . '/includes/sidebar.php'; ?>

<div class="container-fluid p-4">
    <h2><i class="fas fa-user-shield"></i> Roles & Permissions</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Roles List -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Roles</h5>
                </div>
                <div class="card-body">
                    <!-- Add Role Form -->
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_role">
                        <div class="input-group">
                            <input type="text" name="name" class="form-control" placeholder="New role name" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Roles List -->
                    <div class="list-group">
                        <?php while ($role = $roles->fetch_assoc()): ?>
                            <a href="?role_id=<?= $role['id'] ?>" class="list-group-item list-group-item-action <?= ($_GET['role_id'] ?? '') == $role['id'] ? 'active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <strong><?= htmlspecialchars($role['name']) ?></strong>
                                        <br>
                                        <small><?= htmlspecialchars($role['description'] ?? 'No description') ?></small>
                                    </span>
                                    <?php if ($role['id'] > 1): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="delete_role">
                                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this role?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Permissions -->
        <div class="col-md-8">
            <?php if (!empty($_GET['role_id'])): ?>
                <?php $roleId = (int)$_GET['role_id']; ?>
                <?php $currentRole = $conn->query("SELECT * FROM roles WHERE id = $roleId")->fetch_assoc(); ?>
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-shield-alt"></i> Permissions for: <?= htmlspecialchars($currentRole['name']) ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="save_permissions">
                            <input type="hidden" name="role_id" value="<?= $roleId ?>">
                            
                            <div class="row">
                                <?php foreach ($permissions as $key => $label): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="permissions[]" value="<?= $key ?>" 
                                                id="perm_<?= $key ?>"
                                                class="form-check-input"
                                                <?= in_array($key, $rolePermissions[$roleId] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="perm_<?= $key ?>">
                                                <?= $label ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Permissions
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-arrow-left"></i>
                        <p>Select a role from the left to manage permissions</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>
</div>
</div>
<?php include $base_path . '/includes/footer.php'; ?>