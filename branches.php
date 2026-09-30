<?php
include 'config.php';
include 'includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$page_title = "Branches";
$active = "branches";

if(!isSuperAdmin()){
    die("Access Denied");
}

// Fetch all branches
$branches = $conn->query("SELECT * FROM branches ORDER BY id DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
    <div class="table-box">
        <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3><i class="fa fa-sitemap"></i> Branches</h3>
            <a href="branch_add.php" class="btn btn-primary">
                <i class="fa fa-plus"></i> Add New Branch
            </a>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($b = $branches->fetch_assoc()): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['code'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($b['address'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($b['phone'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $b['status'] == 'active' ? 'active' : 'inactive' ?>">
                                <?= ucfirst($b['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="branch_edit.php?id=<?= $b['id'] ?>" class="btn btn-sm edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <form action="branch_delete.php" method="POST" style="display:inline"
                                      onsubmit="return confirm('Delete this branch?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                    <button type="submit" class="btn btn-sm danger">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
