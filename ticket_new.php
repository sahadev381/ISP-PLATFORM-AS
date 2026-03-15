<?php
$base_path = './';
include $base_path . 'config.php';
include $base_path . 'includes/auth.php';

$page_title = "Create Ticket";
$active = "tickets";

if(isset($_POST['submit'])){
    $customer_id = (int)$_POST['customer_id'];
    $subject     = $conn->real_escape_string($_POST['subject']);
    $message     = $conn->real_escape_string($_POST['message']);
    $priority    = $conn->real_escape_string($_POST['priority']);
    $branch_id   = $_SESSION['branch_id'] ?? 1;

    $stmt = $conn->prepare("
        INSERT INTO tickets (customer_id, subject, message, priority, status, branch_id)
        VALUES (?, ?, ?, ?, 'Open', ?)
    ");
    $stmt->bind_param("isssi", $customer_id, $subject, $message, $priority, $branch_id);
    
    if($stmt->execute()){
        header("Location: tickets.php?msg=created");
        exit;
    }
}

$customers = $conn->query("SELECT id, full_name, username FROM customers ORDER BY username ASC");

include $base_path . 'includes/header.php';
include $base_path . 'includes/sidebar.php';
include $base_path . 'includes/topbar.php';
?>

<style>
    .form-container { padding: 30px; max-width: 800px; margin: 0 auto; }
    .card { background: #fff; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
    .card-header { background: #f8fafc; padding: 25px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px; }
    .card-header i { font-size: 24px; color: #3b82f6; }
    .card-header h2 { margin: 0; font-size: 20px; color: #1e293b; }
    
    .card-body { padding: 30px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
    
    .form-control { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 15px; transition: all 0.3s; color: #1e293b; outline: none; }
    .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    
    textarea.form-control { min-height: 150px; resize: vertical; }
    
    .btn-submit { background: #3b82f6; color: white; border: none; padding: 14px 30px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; }
    .btn-submit:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3); }
</style>

<div class="form-container">
    <div class="card">
        <div class="card-header">
            <i class="fa fa-plus-circle"></i>
            <h2>Open Support Ticket</h2>
        </div>
        
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Select Customer</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">-- Search Customer --</option>
                        <?php while($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['username']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Subject / Issue Title</label>
                        <input type="text" name="subject" class="form-control" placeholder="E.g. Internet not working, Slow speed" required>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Detailed Message</label>
                    <textarea name="message" class="form-control" placeholder="Describe the problem in detail..." required></textarea>
                </div>

                <button type="submit" name="submit" class="btn-submit">
                    <i class="fa fa-paper-plane"></i> Create Ticket
                </button>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="tickets.php" style="text-decoration: none; color: #64748b; font-size: 14px;">
                        <i class="fa fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>
