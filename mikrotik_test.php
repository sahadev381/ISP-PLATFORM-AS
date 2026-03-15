<?php
/**
 * Mikrotik API Test & Setup Script
 * Run: php mikrotik_test.php
 */

include 'includes/mikrotik_api.php';

$host = $argv[1] ?? '192.168.5.20';
$user = $argv[2] ?? 'admin';
$pass = $argv[3] ?? 'admin';

echo "==========================================\n";
echo "  Mikrotik API Connection Test\n";
echo "==========================================\n\n";

echo "Target: $host\n";
echo "User: $user\n";
echo "Password: $pass\n\n";

// Test connection
$api = new RouterosAPI();
$api->setTimeout(10);
$api->debug = true;

echo "Connecting...\n";
$result = $api->connect($host, $user, $pass);

if ($result) {
    echo "\n✅ SUCCESS! Connected to Mikrotik!\n\n";
    
    // Get system info
    echo "Getting system info...\n";
    $res = $api->comm('/system/resource/print');
    if ($res && count($res) > 0) {
        echo "  Platform: " . ($res[0]['platform'] ?? 'N/A') . "\n";
        echo "  Version: " . ($res[0]['version'] ?? 'N/A') . "\n";
        echo "  Model: " . ($res[0]['board'] ?? 'N/A') . "\n";
        echo "  Uptime: " . ($res[0]['uptime'] ?? 'N/A') . "\n";
    }
    
    // Get active PPPoE
    echo "\nGetting PPPoE stats...\n";
    $ppp = $api->comm('/ppp active print count-only');
    echo "  Active PPPoE: $ppp\n";
    
    // Get interfaces
    echo "\nGetting interfaces...\n";
    $iface = $api->comm('/interface print');
    echo "  Total Interfaces: " . count($iface) . "\n";
    
    // Get hotspot users
    echo "\nGetting Hotspot users...\n";
    $hs = $api->comm('/ip hotspot active print count-only');
    echo "  Active Hotspot: $hs\n";
    
    // Get OLT info if available
    echo "\nChecking for OLT/GPON interfaces...\n";
    $gpon = $api->comm('/interface gpon print');
    if ($gpon && count($gpon) > 0) {
        echo "  GPON Interfaces found: " . count($gpon) . "\n";
        foreach ($gpon as $g) {
            echo "    - " . ($g['name'] ?? 'N/A') . "\n";
        }
    } else {
        echo "  No GPON interfaces\n";
    }
    
    echo "\n✅ All tests passed!\n";
    $api->disconnect();
    
} else {
    echo "\n❌ FAILED to connect!\n\n";
    echo "Possible reasons:\n";
    echo "1. API not enabled on Mikrotik\n";
    echo "2. Wrong username/password\n";
    echo "3. Firewall blocking port 8728\n";
    echo "4. API user doesn't have permissions\n\n";
    
    echo "==========================================\n";
    echo "  SETUP INSTRUCTIONS\n";
    echo "==========================================\n\n";
    
    echo "STEP 1: Enable API Service\n";
    echo "Winbox > IP > API > Check 'enabled'\n";
    echo "Or terminal:\n";
    echo "  /ip service enable api\n\n";
    
    echo "STEP 2: Allow All IPs\n";
    echo "Winbox > IP > Service > api > Available From: 0.0.0.0/0\n";
    echo "Or terminal:\n";
    echo "  /ip service set api address=0.0.0.0/0\n\n";
    
    echo "STEP 3: Create API User (Optional)\n";
    echo "Winbox > IP > API > Users > Add\n";
    echo "  User: apiusr\n";
    echo "  Password: StrongPassword123\n";
    echo "  Group: full\n\n";
    
    echo "STEP 4: Check Firewall\n";
    echo "Make sure firewall allows TCP 8728\n\n";
    
    echo "STEP 5: Update Script\n";
    echo "Edit this script with correct credentials:\n";
    echo "  php mikrotik_test.php 192.168.5.20 admin YourPassword\n";
}

echo "\n==========================================\n";
?>
