<?php
include '../config.php';

function generateMonthlyInvoices() {
    global $conn;
    
    $count = 0;
    
    // Get all active customers with expired or near-expiry subscriptions
    $customers = $conn->query("
        SELECT c.*, p.name as plan_name, p.price, p.validity, p.id as plan_id
        FROM customers c
        LEFT JOIN plans p ON c.plan_id = p.id
        WHERE c.status = 'active'
    ");
    
    while ($customer = $customers->fetch_assoc()) {
        $expiry = strtotime($customer['expiry']);
        $today = strtotime(date('Y-m-d'));
        $daysUntilExpiry = floor(($expiry - $today) / (60 * 60 * 24));
        
        // Generate invoice if expiry is within 7 days or already expired
        if ($daysUntilExpiry <= 7) {
            // Check if invoice already exists for this month
            $monthStart = date('Y-m-01');
            $existing = $conn->query("
                SELECT id FROM invoices 
                WHERE username = '{$customer['username']}'
                AND created_at >= '$monthStart'
                LIMIT 1
            ");
            
            if ($existing->num_rows == 0) {
                // Calculate new expiry
                $validity = $customer['validity'] ?? 30;
                $newExpiry = date('Y-m-d', strtotime("+$validity days", $expiry));
                
                // Create invoice
                $amount = $customer['price'] ?? 0;
                $conn->query("
                    INSERT INTO invoices (username, amount, expiry_date, status, admin, months)
                    VALUES (
                        '{$customer['username']}',
                        $amount,
                        '$newExpiry',
                        'pending',
                        'system',
                        1
                    )
                ");
                
                $count++;
            }
        }
    }
    
    return $count;
}

// Run if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $generated = generateMonthlyInvoices();
    echo "Generated $generated invoices\n";
}
?>
