<?php
require_once 'includes/mikrotik_api.php';

function getRouterInfo($api) {
    $resource = $api->comm('/system/resource/print');
    $identity = $api->comm('/system/identity/print');
    $health = $api->comm('/system/health/print');
    
    return [
        'resource' => $resource[0] ?? [],
        'identity' => $identity[0] ?? [],
        'health' => $health[0] ?? []
    ];
}

function getInterfaces($api) {
    return $api->comm('/interface/print');
}

function getIpAddresses($api) {
    return $api->comm('/ip/address/print');
}

function getActiveUsers($api) {
    return $api->comm('/ip/hotspot/active/print');
}

function getDhcpLeases($api) {
    return $api->comm('/ip/dhcp-server/lease/print');
}

function getQueueSimple($api) {
    return $api->comm('/queue/simple/print');
}

function ping($api, $address, $count = 5) {
    return $api->comm('/tool/ping', [
        'address' => $address,
        'count' => $count
    ]);
}

function rebootRouter($api) {
    return $api->comm('/system/reboot');
}

function disableInterface($api, $name) {
    return $api->comm('/interface/disable', ['name' => $name]);
}

function enableInterface($api, $name) {
    return $api->comm('/interface/enable', ['name' => $name]);
}

$host = $argv[1] ?? '';
$user = $argv[2] ?? 'admin';
$pass = $argv[3] ?? '';
$action = $argv[4] ?? '';

if (empty($host) || empty($pass)) {
    echo "=== MikroTik Router Connection Tool ===\n\n";
    echo "Usage: php mikrotik_manager.php <host> <username> <password> <action> [params]\n\n";
    echo "Examples:\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password info\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password interfaces\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password ips\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password active\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password leases\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password ping 8.8.8.8\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password reboot\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password disable ether1\n";
    echo "  php mikrotik_manager.php 192.168.88.1 admin password enable ether1\n\n";
    echo "Actions:\n";
    echo "  info       - Get router system info\n";
    echo "  interfaces - List all interfaces\n";
    echo "  ips        - List IP addresses\n";
    echo "  active     - List active hotspot users\n";
    echo "  leases     - List DHCP leases\n";
    echo "  queue      - List queue simple\n";
    echo "  ping       - Ping test (requires IP address)\n";
    echo "  reboot     - Reboot router\n";
    echo "  disable    - Disable interface (requires name)\n";
    echo "  enable     - Enable interface (requires name)\n";
    exit(1);
}

$api = new RouterosAPI();
$api->debug = false;

echo "Connecting to $host...\n";

if (!$api->connect($host, $user, $pass)) {
    echo "ERROR: Failed to connect to $host\n";
    exit(1);
}

echo "Connected successfully!\n\n";

switch ($action) {
    case 'info':
        $info = getRouterInfo($api);
        echo "=== Router Information ===\n";
        echo "Identity: " . ($info['identity']['name'] ?? 'N/A') . "\n";
        echo "Model: " . ($info['resource']['board-name'] ?? 'N/A') . "\n";
        echo "Version: " . ($info['resource']['version'] ?? 'N/A') . "\n";
        echo "Uptime: " . ($info['resource']['uptime'] ?? 'N/A') . "\n";
        echo "CPU Load: " . ($info['resource']['cpu-load'] ?? 'N/A') . "%\n";
        echo "Free Memory: " . ($info['resource']['free-memory'] ?? 'N/A') . "\n";
        echo "Free HDD: " . ($info['resource']['free-hdd-space'] ?? 'N/A') . "\n";
        break;
        
    case 'interfaces':
        $interfaces = getInterfaces($api);
        echo "=== Interfaces ===\n";
        foreach ($interfaces as $iface) {
            echo sprintf("%-15s %-10s %-10s %s\n", 
                $iface['name'] ?? 'N/A',
                $iface['type'] ?? 'N/A',
                $iface['running'] ?? 'N/A',
                $iface['comment'] ?? ''
            );
        }
        break;
        
    case 'ips':
        $ips = getIpAddresses($api);
        echo "=== IP Addresses ===\n";
        foreach ($ips as $ip) {
            echo ($ip['address'] ?? 'N/A') . " (" . ($ip['interface'] ?? 'N/A') . ")\n";
        }
        break;
        
    case 'active':
        $active = getActiveUsers($api);
        echo "=== Active Hotspot Users ===\n";
        foreach ($active as $u) {
            echo sprintf("%-20s %-15s %s\n", 
                $u['user'] ?? 'N/A',
                $u['address'] ?? 'N/A',
                $u['session-time-left'] ?? ''
            );
        }
        break;
        
    case 'leases':
        $leases = getDhcpLeases($api);
        echo "=== DHCP Leases ===\n";
        foreach ($leases as $lease) {
            echo sprintf("%-15s %-20s %s\n", 
                $lease['address'] ?? 'N/A',
                $lease['mac-address'] ?? 'N/A',
                $lease['status'] ?? 'N/A'
            );
        }
        break;
        
    case 'queue':
        $queues = getQueueSimple($api);
        echo "=== Queue Simple ===\n";
        foreach ($queues as $q) {
            echo sprintf("%-20s %s -> %s\n", 
                $q['name'] ?? 'N/A',
                $q['target'] ?? 'N/A',
                ($q['max-limit'] ?? 'N/A')
            );
        }
        break;
        
    case 'ping':
        $target = $argv[5] ?? '8.8.8.8';
        echo "Pinging $target...\n";
        $result = ping($api, $target);
        foreach ($result as $r) {
            if (isset($r['host'])) {
                echo "Reply from {$r['host']}: time={$r['time']}ms\n";
            }
        }
        break;
        
    case 'reboot':
        echo "Rebooting router...\n";
        rebootRouter($api);
        echo "Reboot command sent!\n";
        break;
        
    case 'disable':
        $name = $argv[5] ?? '';
        if (empty($name)) {
            echo "ERROR: Interface name required\n";
        } else {
            disableInterface($api, $name);
            echo "Interface '$name' disabled!\n";
        }
        break;
        
    case 'enable':
        $name = $argv[5] ?? '';
        if (empty($name)) {
            echo "ERROR: Interface name required\n";
        } else {
            enableInterface($api, $name);
            echo "Interface '$name' enabled!\n";
        }
        break;
        
    default:
        echo "Unknown action: $action\n";
        echo "Run without arguments to see available actions.\n";
}

$api->disconnect();
echo "\nDisconnected.\n";
