<?php
include 'config.php';
include 'includes/auth.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="user_report.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Category','Username','Plan','Speed','Expiry Date','Status']);

// Fetch all users
$users = $conn->query("
    SELECT u.id, u.username, u.plan_id, u.expiry, u.status,
           p.name AS plan_name, p.speed,
           (SELECT COUNT(*) FROM radacct r WHERE r.username=u.username AND r.acctstoptime IS NULL) AS online
    FROM customers u
    LEFT JOIN plans p ON u.plan_id = p.id
    ORDER BY u.id DESC
");

$expiry_threshold = 7;
$today = date('Y-m-d');

$new_users_count = 0;

while($row = $users->fetch_assoc()){
    $expiry_date = $row['expiry'];

    // New Users: top 30
    if($new_users_count < 30){
        fputcsv($output, ['New User',$row['username'],$row['plan_name'],$row['speed'],$expiry_date,$row['status']]);
        $new_users_count++;
    }

    // Expired Users
    if($expiry_date < $today){
        fputcsv($output, ['Expired',$row['username'],$row['plan_name'],$row['speed'],$expiry_date,$row['status']]);
    }
    // Expiring Users
    elseif(strtotime($expiry_date) <= strtotime("+$expiry_threshold days")){
        fputcsv($output, ['Expiring',$row['username'],$row['plan_name'],$row['speed'],$expiry_date,$row['status']]);
    }
    // Active Users
    if($row['online'] > 0){
        fputcsv($output, ['Active',$row['username'],$row['plan_name'],$row['speed'],$expiry_date,'Online']);
    }
}

fclose($output);
exit;

