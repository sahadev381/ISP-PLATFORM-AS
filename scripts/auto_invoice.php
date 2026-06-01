<?php
include '../config.php';

function generateMonthlyInvoices() {
    global $conn;
    
    $count = 0;
    $monthStart = date('Y-m-01');
    $today = strtotime(date('Y-m-d'));

    // Optimization 1: Pre-fetch all usernames who already have an invoice this month
    // to avoid N+1 SELECT queries.
    $existingInvoices = [];
    $existingRes = $conn->query("SELECT DISTINCT username FROM invoices WHERE created_at >= '$monthStart'");
    if ($existingRes) {
        while ($row = $existingRes->fetch_assoc()) {
            $existingInvoices[$row['username']] = true;
        }
    }
    
    // Get all active customers with expired or near-expiry subscriptions
    $customers = $conn->query("
        SELECT c.username, c.expiry, p.price, p.validity
        FROM customers c
        LEFT JOIN plans p ON c.plan_id = p.id
        WHERE c.status = 'active'
    ");
    
    if (!$customers) return 0;

    $invoicesToCreate = [];

    while ($customer = $customers->fetch_assoc()) {
        $expiry = strtotime($customer['expiry']);
        $daysUntilExpiry = floor(($expiry - $today) / (60 * 60 * 24));
        
        // Generate invoice if expiry is within 7 days or already expired
        if ($daysUntilExpiry <= 7) {
            // Optimization 2: Use the pre-fetched hash map instead of a per-customer query
            if (!isset($existingInvoices[$customer['username']])) {
                // Calculate new expiry
                $validity = $customer['validity'] ?? 30;
                $newExpiry = date('Y-m-d', strtotime("+$validity days", $expiry));
                
                // Create invoice
                $amount = $customer['price'] ?? 0;
                $username = $conn->real_escape_string($customer['username']);
                $newExpiryEscaped = $conn->real_escape_string($newExpiry);
                
                // Optimization 3: Collect for bulk insertion
                $invoicesToCreate[] = "('$username', $amount, '$newExpiryEscaped', 'pending', 'system', 1)";
                $count++;

                // Perform bulk insert in chunks to avoid extremely large queries
                if (count($invoicesToCreate) >= 100) {
                    $conn->query("
                        INSERT INTO invoices (username, amount, expiry_date, status, admin, months)
                        VALUES " . implode(',', $invoicesToCreate)
                    );
                    $invoicesToCreate = [];
                }
            }
        }
    }
    
    // Final bulk insert for any remaining invoices
    if (count($invoicesToCreate) > 0) {
        $conn->query("
            INSERT INTO invoices (username, amount, expiry_date, status, admin, months)
            VALUES " . implode(',', $invoicesToCreate)
        );
    }

    return $count;
}

// Run if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $generated = generateMonthlyInvoices();
    echo "Generated $generated invoices\n";
}
?>
