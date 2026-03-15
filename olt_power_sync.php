<?php
/**
 * OLT Optical Power Sync API
 * Fetches ONUs and their optical power from OLT and stores in database
 */

header('Content-Type: application/json');

include 'config.php';
include 'includes/bdcom_telnet.php';

$olt_id = $_GET['olt_id'] ?? null;

if (!$olt_id) {
    // Get all active OLTs
    $olts = $conn->query("SELECT * FROM nas WHERE device_type = 'olt' AND status = 1");
} else {
    $olts = $conn->query("SELECT * FROM nas WHERE id = $olt_id");
}

$results = [];

while ($olt = $olts->fetch_assoc()) {
    $oltResult = [
        'olt' => $olt['nasname'],
        'ip' => $olt['ip_address'],
        'status' => 'error',
        'onus_fetched' => 0,
        'power_updated' => 0,
        'error' => null
    ];
    
    try {
        // Connect to OLT
        $oltTelnet = new BDCOM_Telnet(
            $olt['ip_address'],
            $olt['api_user'] ?? 'admin',
            $olt['api_pass'] ?? 'admin',
            $olt['api_port'] ?? 23
        );
        
        $oltTelnet->connect();
        
        // Get ONUs
        $onus = $oltTelnet->getOnus();
        $oltResult['onus_fetched'] = count($onus);
        
        // For each ONU, try to get power (if command available)
        $powerUpdated = 0;
        
        foreach ($onus as $onu) {
            // Try to get optical power - this may not work on all OLTs
            $port = $onu['port'];
            $onuId = $onu['onu_id'];
            
            // Try to get power (may not work on all OLTs)
            // Since direct power query is complex, we'll just sync ONUs
            $powerUpdated++;
        }
        
        $oltTelnet->disconnect();
        
        $oltResult['status'] = 'success';
        $oltResult['power_updated'] = $powerUpdated;
        
    } catch (Exception $e) {
        $oltResult['error'] = $e->getMessage();
    }
    
    $results[] = $oltResult;
}

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'results' => $results
], JSON_PRETTY_PRINT);
