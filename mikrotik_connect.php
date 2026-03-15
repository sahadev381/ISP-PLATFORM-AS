<?php
require_once 'includes/mikrotik_api.php';

$host = $argv[1] ?? '192.168.88.1';
$user = $argv[2] ?? 'admin';
$pass = $argv[3] ?? '';
$command = $argv[4] ?? '/system/resource/print';

if (empty($pass)) {
    echo "Usage: php mikrotik_connect.php <host> <username> <password> [command]\n";
    echo "Example: php mikrotik_connect.php 192.168.88.1 admin password /ip/address/print\n\n";
    echo "Available commands:\n";
    echo "  /system/resource/print      - Get system resource info\n";
    echo "  /ip/address/print          - Get IP addresses\n";
    echo "  /interface/print           - Get interfaces\n";
    echo "  /system/identity/print     - Get router identity\n";
    echo "  /tool/ping address=8.8.8.8 - Ping test\n";
    exit(1);
}

$api = new RouterosAPI();
$api->debug = false;

echo "Connecting to $host...\n";

if ($api->connect($host, $user, $pass)) {
    echo "Connected successfully!\n\n";
    
    $response = $api->comm($command);
    
    echo "Response:\n";
    print_r($response);
    
    $api->disconnect();
    echo "\nDisconnected.\n";
} else {
    echo "Failed to connect to $host\n";
    exit(1);
}
