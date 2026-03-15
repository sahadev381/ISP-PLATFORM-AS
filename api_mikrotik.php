<?php
/**
 * Mikrotik Web API - REST API access via HTTP
 */

header('Content-Type: application/json');

$host = $_GET['host'] ?? '192.168.5.20';
$user = $_GET['user'] ?? 'apiuser';
$pass = $_GET['pass'] ?? '123456';

$response = [
    'host' => $host,
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => null,
    'error' => null
];

try {
    include 'includes/mikrotik_web.php';
    
    $mikrotik = new MikrotikWeb($host, $user, $pass);
    
    if (!$mikrotik->login()) {
        $response['error'] = 'Login failed';
        echo json_encode($response);
        exit;
    }
    
    $response['data'] = $mikrotik->getAll();
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
