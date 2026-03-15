<?php
/**
 * OLT via MikroTik Integration API
 * Pulls OLT ONT data through MikroTik router
 * Useful when MikroTik is the BNG/Edge router
 */

header('Content-Type: application/json');
include_once '../config.php';
include_once '../includes/mikrotik_api.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

switch ($action) {
    
    // Get MikroTik's connected OLT data
    case 'get_mikrotik_olt_data':
        $mikrotik_id = intval($_GET['mikrotik_id'] ?? 0);
        
        if (!$mikrotik_id) {
            jsonResponse(false, 'MikroTik ID required');
        }
        
        $mikrotik = $conn->query("SELECT * FROM nas WHERE id = $mikrotik_id AND device_type = 'mikrotik'")->fetch_assoc();
        
        if (!$mikrotik) {
            jsonResponse(false, 'MikroTik not found');
        }
        
        $api = new RouterosAPI();
        $api->setPort($mikrotik['api_port'] ?: 8728);
        
        if (!$api->connect($mikrotik['ip_address'], $mikrotik['api_user'], $mikrotik['api_pass'])) {
            jsonResponse(false, 'Cannot connect to MikroTik');
        }
        
        // Get GPON/SFP interfaces
        $gpon_status = $api->getGPONStatus();
        
        // Get all OLTs
        $olts = $conn->query("SELECT * FROM nas WHERE device_type = 'olt'");
        
        $olt_list = [];
        while ($olt = $olts->fetch_assoc()) {
            $olt_list[] = [
                'id' => $olt['id'],
                'name' => $olt['nasname'],
                'ip' => $olt['ip_address'],
                'type' => $olt['brand'] ?? 'Generic',
                'connected' => false // Will be determined by ARP or other methods
            ];
        }
        
        $api->disconnect();
        
        jsonResponse(true, '', [
            'mikrotik' => [
                'id' => $mikrotik['id'],
                'name' => $mikrotik['nasname'],
                'ip' => $mikrotik['ip_address']
            ],
            'gpon_interfaces' => $gpon_status,
            'olts' => $olt_list
        ]);
        break;
    
    // Get OLT ONT data from specific OLT through MikroTik
    case 'get_ont_through_mikrotik':
        $mikrotik_id = intval($_GET['mikrotik_id'] ?? 0);
        $olt_id = intval($_GET['olt_id'] ?? 0);
        
        if (!$mikrotik_id || !$olt_id) {
            jsonResponse(false, 'MikroTik ID and OLT ID required');
        }
        
        $mikrotik = $conn->query("SELECT * FROM nas WHERE id = $mikrotik_id AND device_type = 'mikrotik'")->fetch_assoc();
        $olt = $conn->query("SELECT * FROM nas WHERE id = $olt_id AND device_type = 'olt'")->fetch_assoc();
        
        if (!$mikrotik || !$olt) {
            jsonResponse(false, 'MikroTik or OLT not found');
        }
        
        // First connect to MikroTik
        $api = new RouterosAPI();
        $api->setPort($mikrotik['api_port'] ?: 8728);
        
        if (!$api->connect($mikrotik['ip_address'], $mikrotik['api_user'], $mikrotik['api_pass'])) {
            jsonResponse(false, 'Cannot connect to MikroTik');
        }
        
        // Get OLT data - method depends on setup
        // Option 1: Try to get from MikroTik directly if it has visibility
        $olt_data = $api->getOLTData($olt['ip_address']);
        
        // Get ARP table to find ONUs behind OLT
        $arp = $api->comm("/ip/arp/print");
        
        $onu_list = [];
        foreach ($arp as $entry) {
            if (isset($entry['address'])) {
                // Filter IPs that might be ONUs (typically in the same subnet as OLT)
                $onu_list[] = [
                    'ip' => $entry['address'],
                    'mac' => $entry['mac-address'] ?? '',
                    'interface' => $entry['interface'] ?? ''
                ];
            }
        }
        
        $api->disconnect();
        
        // Also try to get ONT data directly from OLT
        include_once '../includes/olt_api.php';
        $olt_driver = new OLT_Driver($olt);
        $direct_onus = $olt_driver->getAllOnus();
        
        jsonResponse(true, '', [
            'mikrotik' => $mikrotik['nasname'],
            'olt' => $olt['nasname'],
            'olt_ip' => $olt['ip_address'],
            'arus_from_mikrotik' => $onu_list,
            'ont_from_olt' => $direct_onus
        ]);
        break;
    
    // Get MikroTik interface that's connected to OLT
    case 'get_olt_interface':
        $mikrotik_id = intval($_GET['mikrotik_id'] ?? 0);
        
        if (!$mikrotik_id) {
            jsonResponse(false, 'MikroTik ID required');
        }
        
        $mikrotik = $conn->query("SELECT * FROM nas WHERE id = $mikrotik_id AND device_type = 'mikrotik'")->fetch_assoc();
        
        if (!$mikrotik) {
            jsonResponse(false, 'MikroTik not found');
        }
        
        $api = new RouterosAPI();
        $api->setPort($mikrotik['api_port'] ?: 8728);
        
        if (!$api->connect($mikrotik['ip_address'], $mikrotik['api_user'], $mikrotik['api_pass'])) {
            jsonResponse(false, 'Cannot connect to MikroTik');
        }
        
        // Get GPON/SFP interfaces
        $gpon_status = $api->getGPONStatus();
        
        // Get all interfaces
        $interfaces = $api->getInterfaces();
        
        $api->disconnect();
        
        jsonResponse(true, '', [
            'gpon_interfaces' => $gpon_status,
            'all_interfaces' => $interfaces
        ]);
        break;
    
    // Get PPPoE session details (includes ONT info if available)
    case 'get_pppoe_sessions':
        $mikrotik_id = intval($_GET['mikrotik_id'] ?? 0);
        
        if (!$mikrotik_id) {
            jsonResponse(false, 'MikroTik ID required');
        }
        
        $mikrotik = $conn->query("SELECT * FROM nas WHERE id = $mikrotik_id AND device_type = 'mikrotik'")->fetch_assoc();
        
        if (!$mikrotik) {
            jsonResponse(false, 'MikroTik not found');
        }
        
        $api = new RouterosAPI();
        $api->setPort($mikrotik['api_port'] ?: 8728);
        
        if (!$api->connect($mikrotik['ip_address'], $mikrotik['api_user'], $mikrotik['api_pass'])) {
            jsonResponse(false, 'Cannot connect to MikroTik');
        }
        
        $sessions = $api->getPPPoESessions();
        
        $api->disconnect();
        
        jsonResponse(true, '', $sessions);
        break;
    
    // Get DHCP leases (for ONTs with IP addresses)
    case 'get_dhcp_leases':
        $mikrotik_id = intval($_GET['mikrotik_id'] ?? 0);
        
        if (!$mikrotik_id) {
            jsonResponse(false, 'MikroTik ID required');
        }
        
        $mikrotik = $conn->query("SELECT * FROM nas WHERE id = $mikrotik_id AND device_type = 'mikrotik'")->fetch_assoc();
        
        if (!$mikrotik) {
            jsonResponse(false, 'MikroTik not found');
        }
        
        $api = new RouterosAPI();
        $api->setPort($mikrotik['api_port'] ?: 8728);
        
        if (!$api->connect($mikrotik['ip_address'], $mikrotik['api_user'], $mikrotik['api_pass'])) {
            jsonResponse(false, 'Cannot connect to MikroTik');
        }
        
        $leases = $api->getDHCPLeases();
        
        $api->disconnect();
        
        jsonResponse(true, '', $leases);
        break;
    
    default:
        jsonResponse(false, 'Unknown action. Use: get_mikrotik_olt_data, get_ont_through_mikrotik, get_olt_interface, get_pppoe_sessions, get_dhcp_leases');
}
