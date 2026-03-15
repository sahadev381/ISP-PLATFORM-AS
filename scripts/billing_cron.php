#!/usr/bin/php
<?php
/**
 * Billing & SMS Reminder Cron Script
 * Run this every day at 00:01 AM
 */

include __DIR__ . '/../config.php';
include __DIR__ . '/../includes/messaging.php';

// Re-establish connection for CLI
$conn = new mysqli("localhost", "radius", "radiuspass", "radius");

echo "[" . date('Y-m-d H:i:s') . "] Starting Billing & Reminder Service...\n";

// --- PART 1: Automated Monthly Invoicing ---
$today_day = date('j');
$current_month = date('Y-m');
$auto_inv_day = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='auto_invoice_day'")->fetch_assoc()['setting_value'] ?? 1;

if ($today_day == $auto_inv_day) {
    // Check if we already generated for this month
    $check = $conn->query("SELECT id FROM auto_invoice_log WHERE month_year = '$current_month'")->num_rows;
    if ($check == 0) {
        echo "Generating monthly invoices...\n";
        
        $customers = $conn->query("
            SELECT c.username, p.price, p.name as plan_name, c.expiry 
            FROM customers c 
            JOIN plans p ON c.plan_id = p.id 
            WHERE c.status = 'active'
        ");
        
        $count = 0;
        while($u = $customers->fetch_assoc()) {
            $user = $u['username'];
            $amount = $u['price'];
            $expiry = $u['expiry'];
            
            // Insert Invoice
            $conn->query("INSERT INTO invoices (username, amount, months, expiry_date, status) VALUES ('$user', $amount, 1, '$expiry', 'unpaid')");
            $count++;
        }
        
        $conn->query("INSERT INTO auto_invoice_log (month_year, total_invoices) VALUES ('$current_month', $count)");
        echo "Successfully generated $count invoices.\n";
    }
}

// --- PART 2: SMS Expiry Reminders ---
$reminder_days = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='reminder_days_before'")->fetch_assoc()['setting_value'] ?? 3;
$target_date = date('Y-m-d', strtotime("+$reminder_days days"));

echo "Checking for expirations on $target_date...\n";

$expiring = $conn->query("
    SELECT username, phone, expiry 
    FROM customers 
    WHERE expiry = '$target_date' AND status = 'active'
");

while($u = $expiring->fetch_assoc()) {
    $msg = "Dear Customer, your ISP subscription for {$u['username']} expires on {$u['expiry']}. Please recharge to avoid interruption.";
    sendSMS($u['username'], $u['phone'], $msg);
    echo "Reminder sent to {$u['username']}\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Service Complete.\n";
?>
