<?php
include 'config.php';
include 'includes/auth.php';

$type = $_POST['type'] ?? '';

if (!$type) die("Invalid Report Type");

// Common Headers for Excel Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_'.$type.'_'.date('Ymd').'.csv"');
$output = fopen('php://output', 'w');

if ($type == 'financial') {
    $from = $_POST['from'];
    $to = $_POST['to'];
    
    // Check payments table (assuming invoices table exists, if not using mock logic for now)
    fputcsv($output, ['Invoice ID', 'Username', 'Amount', 'Date', 'Status', 'Gateway']);
    $res = $conn->query("SELECT * FROM invoices WHERE created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'");
    
    while($row = $res->fetch_assoc()) {
        fputcsv($output, [$row['id'], $row['username'], $row['amount'], $row['created_at'], $row['status'], 'Khalti/Manual']);
    }
}

elseif ($type == 'expiry') {
    $status = $_POST['status'];
    fputcsv($output, ['Username', 'Full Name', 'Phone', 'Plan', 'Expiry Date', 'Status']);
    
    $query = "SELECT u.username, u.full_name, u.phone, p.name as plan, u.expiry, u.status 
              FROM customers u LEFT JOIN plans p ON u.plan_id = p.id WHERE 1=1";
              
    if ($status == 'expired') {
        $query .= " AND u.expiry < CURDATE()";
    } elseif ($status == 'upcoming_3') {
        $query .= " AND u.expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
    } elseif ($status == 'upcoming_7') {
        $query .= " AND u.expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
    
    $res = $conn->query($query);
    while($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

elseif ($type == 'faults') {
    $from = $_POST['from'];
    $to = $_POST['to'];
    fputcsv($output, ['Fault ID', 'Type', 'Location', 'Reported At', 'Status', 'Resolved At']);
    
    $res = $conn->query("SELECT * FROM network_faults WHERE created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'");
    while($row = $res->fetch_assoc()) {
        fputcsv($output, [$row['id'], 'Fiber Break', $row['predicted_lat'].','.$row['predicted_lng'], $row['created_at'], $row['is_resolved']?'Resolved':'Open', $row['resolved_at']]);
    }
}

elseif ($type == 'fiber') {
    fputcsv($output, ['Route Name', 'Type', 'Total Cores', 'Used Cores', 'Utilization %', 'Length (m)']);
    
    $res = $conn->query("SELECT * FROM fiber_routes");
    while($row = $res->fetch_assoc()) {
        $util = $row['total_cores'] > 0 ? round(($row['used_cores']/$row['total_cores'])*100, 1) : 0;
        fputcsv($output, [$row['name'], $row['route_type'], $row['total_cores'], $row['used_cores'], $util.'%', $row['calculated_length_m']]);
    }
    
    // Add Lease Section
    fputcsv($output, []);
    fputcsv($output, ['--- ACTIVE LEASES ---']);
    fputcsv($output, ['Client', 'Route', 'Core #', 'Start Date', 'Monthly Price', 'Status']);
    
    $leases = $conn->query("SELECT l.*, r.name as route FROM wire_leases l JOIN fiber_routes r ON l.route_id = r.id");
    while($l = $leases->fetch_assoc()) {
        fputcsv($output, [$l['client_name'], $l['route'], $l['core_number'], $l['lease_start'], $l['monthly_price'], $l['status']]);
    }
}

fclose($output);
exit;
?>
