<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php';

// Bulk customer creation is a manager-level action.
require_role('manager');

$page_title = "Import Customers";

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main">
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-upload"></i> Import Customers</h3>
        </div>
        
        <p style="color: var(--text-muted); margin-bottom: 20px;">
            Import customers from CSV file. Format: username,password,full_name,email,phone,plan_id,address
        </p>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Select CSV File</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            </div>
            
            <button type="submit" name="import" class="btn btn-primary">
                <i class="fa fa-upload"></i> Import Customers
            </button>
        </form>
        
        <?php
        if (isset($_POST['import'])) {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $count = 0;
                
                fgetcsv($file); // Skip header
                
                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) >= 2) {
                        $username = trim($row[0]);
                        $password = password_hash(trim($row[1]), PASSWORD_DEFAULT);
                        $full_name = trim($row[2] ?? $username);
                        $email = trim($row[3] ?? '');
                        $phone = trim($row[4] ?? '');
                        $plan_id = intval($row[5] ?? 1);
                        $address = trim($row[6] ?? '');
                        
                        db_exec($conn, "
                            INSERT INTO customers (username, password, full_name, email, phone, plan_id, address, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', CURDATE())
                        ", [$username, $password, $full_name, $email, $phone, $plan_id, $address]);
                        $count++;
                    }
                }
                
                fclose($file);
                echo "<div class='alert alert-success'>Successfully imported " . (int) $count . " customers!</div>";
            }
        }
        ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
