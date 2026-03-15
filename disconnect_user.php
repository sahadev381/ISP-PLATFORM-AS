<?php
include 'config.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = escapeshellarg($_POST['username']);
    $nas = $conn->query("SELECT * FROM nas WHERE status=1 LIMIT 1")->fetch_assoc();

    if (!$nas) {
        echo json_encode(['success'=>false,'msg'=>'NAS not configured']);
        exit;
    }

    $radclient = '/usr/bin/radclient';
    $cmd = "echo 'User-Name = $username' | $radclient -x {$nas['ip_address']}:3799 disconnect {$nas['secret']} 2>&1";
    exec($cmd, $output, $status);
    $output_str = implode("\n", $output);

    if (stripos($output_str, 'Received Disconnect-ACK') !== false) {
        echo json_encode(['success'=>true,'msg'=>'User disconnected successfully.']);
    } else {
        echo json_encode(['success'=>false,'msg'=>'Disconnect may have failed. Debug: '.$output_str]);
    }
}
?>

