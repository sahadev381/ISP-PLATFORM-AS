#!/usr/bin/php
<?php
/**
 * Billing & SMS Reminder Cron Script
 * Run this every day at 00:01 AM
 */

include __DIR__ . '/../config.php';
include __DIR__ . '/../includes/messaging.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Billing & Reminder Service...\n";

// --- PART 1: Automated Monthly Invoicing ---
$today_day = date('j');
$current_month = date('Y-m');
$auto_inv_day = db_value($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'auto_invoice_day'", [], 1);

if ($today_day == $auto_inv_day) {
    // Check if we already generated for this month
    $check = db_value($conn, "SELECT COUNT(*) FROM auto_invoice_log WHERE month_year = ?", [$current_month], 0);
    if ($check == 0) {
        echo "Generating monthly invoices...\n";

        $customers = db_all($conn, "
            SELECT c.username, p.price, p.name as plan_name, c.expiry
            FROM customers c
            JOIN plans p ON c.plan_id = p.id
            WHERE c.status = 'active'
        ");

        $count = 0;
        foreach ($customers as $u) {
            // Insert Invoice
            db_exec($conn, "INSERT INTO invoices (username, amount, months, expiry_date, status) VALUES (?, ?, 1, ?, 'unpaid')",
                [$u['username'], $u['price'], $u['expiry']]);
            $count++;
        }

        db_exec($conn, "INSERT INTO auto_invoice_log (month_year, total_invoices) VALUES (?, ?)", [$current_month, $count]);
        echo "Successfully generated $count invoices.\n";
    }
}

// --- PART 2: SMS Expiry Reminders ---
$reminder_days = (int) db_value($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'reminder_days_before'", [], 3);
$target_date = date('Y-m-d', strtotime("+$reminder_days days"));

echo "Checking for expirations on $target_date...\n";

$expiring = db_all($conn, "
    SELECT username, phone, expiry
    FROM customers
    WHERE expiry = ? AND status = 'active'
", [$target_date]);

foreach ($expiring as $u) {
    $msg = "Dear Customer, your ISP subscription for {$u['username']} expires on {$u['expiry']}. Please recharge to avoid interruption.";
    sendSMS($u['username'], $u['phone'], $msg);
    echo "Reminder sent to {$u['username']}\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Service Complete.\n";
?>
