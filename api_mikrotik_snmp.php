<?php
/**
 * Mikrotik SNMP Monitor API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/includes/api_auth.php';
api_require_auth();


$host = $_GET['host'] ?? '192.168.5.20';
$community = $_GET['community'] ?? 'public';

$response = [
    'host' => $host,
    'community' => $community,
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => null,
    'error' => null
];

if (!function_exists('snmpget')) {
    $response['error'] = 'PHP SNMP extension not installed';
    echo json_encode($response);
    exit;
}

try {
    include __DIR__ . '/includes/mikrotik_snmp.php';
    
    $snmp = new MikrotikSNMP($host, $community);
    $response['data'] = $snmp->getAll();
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
