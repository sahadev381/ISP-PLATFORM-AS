<?php
include '../includes/customer.php';
include '../includes/user-header.php';
include '../user-config.php';

$error = '';
$success = '';

if(isset($_POST['submit'])){
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $priority = $_POST['priority'] ?? 'Medium';


    $customer_id = $_SESSION['customer_id'];

// ? Get branch_id from customers table
    $stmt_branch = $conn->prepare("SELECT branch_id FROM customers WHERE id = ?");
    $stmt_branch->bind_param("i", $customer_id);
    $stmt_branch->execute();
    $result = $stmt_branch->get_result();
    $row = $result->fetch_assoc();
    $branch_id = $row['branch_id'] ?? 0;

    $stmt = $conn->prepare("
        INSERT INTO tickets (customer_id, subject, message, priority, branch_id, status)
        VALUES (?,?,?,?,?, 'Open')
    ");
    $stmt->bind_param("isssi", $customer_id, $subject, $message, $priority, $branch_id);

    if($stmt->execute()){
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Failed to create ticket.";
    }
}
?>
<div class="container">
    <h2>Create New Ticket</h2>
    <?php if($error){ ?><p style="color:red"><?= $error ?></p><?php } ?>
    <?php if($success){ ?><p style="color:lightgreen"><?= $success ?></p><?php } ?>

    <form method="post">
        <label>Subject</label><br>
        <input type="text" name="subject" required style="width:100%; padding:8px; margin-bottom:10px;"><br>

        <label>Priority</label><br>
        <select name="priority" style="width:100%; padding:8px; margin-bottom:10px;">
            <option>Low</option>
            <option selected>Medium</option>
            <option>High</option>
        </select><br>

        <label>Message</label><br>
        <textarea name="message" rows="5" style="width:100%; padding:8px; margin-bottom:10px;" required></textarea><br>

        <button type="submit" name="submit" style="padding:10px 20px; background: rgba(255,255,255,0.2); border:none; color:#fff; border-radius:5px; cursor:pointer;">Create Ticket</button>
    </form>
</div>


