<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

csrf_check();

$type = $_POST['type'] ?? '';

// Whitelist the report type: it is interpolated into a response header below,
// where a newline would let a caller inject arbitrary headers.
$allowed_types = ['financial', 'expiry', 'faults', 'fiber'];
if (!in_array($type, $allowed_types, true)) die("Invalid Report Type");

/**
 * Prefix values that spreadsheet software would evaluate as a formula.
 */
function csv_cell($value) {
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

function csv_row($handle, array $row) {
    fputcsv($handle, array_map('csv_cell', $row));
}

// Common Headers for Excel Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_'.$type.'_'.date('Ymd').'.csv"');
$output = fopen('php://output', 'w');

if ($type == 'financial') {
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';

    // Check payments table (assuming invoices table exists, if not using mock logic for now)
    csv_row($output, ['Invoice ID', 'Username', 'Amount', 'Date', 'Status', 'Gateway']);
    $rows = db_all($conn, "SELECT * FROM invoices WHERE created_at BETWEEN ? AND ?", [$from . ' 00:00:00', $to . ' 23:59:59']);

    foreach ($rows as $row) {
        csv_row($output, [$row['id'], $row['username'], $row['amount'], $row['created_at'], $row['status'], 'Khalti/Manual']);
    }
}

elseif ($type == 'expiry') {
    $status = $_POST['status'] ?? '';
    csv_row($output, ['Username', 'Full Name', 'Phone', 'Plan', 'Expiry Date', 'Status']);
    
    $query = "SELECT u.username, u.full_name, u.phone, p.name as plan, u.expiry, u.status 
              FROM customers u LEFT JOIN plans p ON u.plan_id = p.id WHERE 1=1";
              
    if ($status == 'expired') {
        $query .= " AND u.expiry < CURDATE()";
    } elseif ($status == 'upcoming_3') {
        $query .= " AND u.expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
    } elseif ($status == 'upcoming_7') {
        $query .= " AND u.expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
    
    foreach (db_all($conn, $query) as $row) {
        csv_row($output, $row);
    }
}

elseif ($type == 'faults') {
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';
    csv_row($output, ['Fault ID', 'Type', 'Location', 'Reported At', 'Status', 'Resolved At']);

    $rows = db_all($conn, "SELECT * FROM network_faults WHERE created_at BETWEEN ? AND ?", [$from . ' 00:00:00', $to . ' 23:59:59']);
    foreach ($rows as $row) {
        csv_row($output, [$row['id'], 'Fiber Break', $row['predicted_lat'].','.$row['predicted_lng'], $row['created_at'], $row['is_resolved']?'Resolved':'Open', $row['resolved_at']]);
    }
}

elseif ($type == 'fiber') {
    csv_row($output, ['Route Name', 'Type', 'Total Cores', 'Used Cores', 'Utilization %', 'Length (m)']);

    foreach (db_all($conn, "SELECT * FROM fiber_routes") as $row) {
        $util = $row['total_cores'] > 0 ? round(($row['used_cores']/$row['total_cores'])*100, 1) : 0;
        csv_row($output, [$row['name'], $row['route_type'], $row['total_cores'], $row['used_cores'], $util.'%', $row['calculated_length_m']]);
    }

    // Add Lease Section
    fputcsv($output, []);
    csv_row($output, ['--- ACTIVE LEASES ---']);
    csv_row($output, ['Client', 'Route', 'Core #', 'Start Date', 'Monthly Price', 'Status']);

    foreach (db_all($conn, "SELECT l.*, r.name as route FROM wire_leases l JOIN fiber_routes r ON l.route_id = r.id") as $l) {
        csv_row($output, [$l['client_name'], $l['route'], $l['core_number'], $l['lease_start'], $l['monthly_price'], $l['status']]);
    }
}

fclose($output);
exit;
?>
