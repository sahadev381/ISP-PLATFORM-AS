#!/usr/bin/php
<?php
// FUP MULTI-TIER CRON SCRIPT
// Run every 15 minutes: */15 * * * * /usr/bin/php /home/devdutta/isp\ system\ with\ tr069/scripts/fup_cron.php >> /var/log/fup_cron.log
include __DIR__ . '/../config.php';

// Re-establish connection for CLI
$conn = new mysqli("localhost", "radius", "radiuspass", "radius");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Multi-Tier FUP Check...\n";

// 1. Get all active customers with their multi-tier plan details
$sql = "
    SELECT c.username, c.plan_id, 
           p.data_limit, p.speed as base_speed,
           p.fup1_limit, p.fup1_speed,
           p.fup2_limit, p.fup2_speed,
           p.fup3_limit, p.fup3_speed,
           du.fup_reset
    FROM customers c
    JOIN plans p ON c.plan_id = p.id
    LEFT JOIN data_usage du ON c.username = du.username
    WHERE c.status = 'active'
";
$result = $conn->query($sql);

$updated = 0;
$errors = 0;

while ($user = $result->fetch_assoc()) {
    $username = $user['username'];
    $plan_id = $user['plan_id'];
    $fup_reset = (int)($user['fup_reset'] ?? 0);
    $total_limit = (float)$user['data_limit'];
    $base_speed = $user['base_speed'];
    
    // FUP Tiers
    $t1_limit = (float)$user['fup1_limit'];
    $t1_speed = $user['fup1_speed'];
    $t2_limit = (float)$user['fup2_limit'];
    $t2_speed = $user['fup2_speed'];
    $t3_limit = (float)$user['fup3_limit'];
    $t3_speed = $user['fup3_speed'];

    // 2. Calculate Usage for Current Month
    $start_date = date('Y-m-01 00:00:00'); 
    
    $usage_sql = "
        SELECT COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) as total_usage
        FROM radacct
        WHERE username = '$username'
        AND acctstarttime >= '$start_date'
    ";
    $usage_res = $conn->query($usage_sql)->fetch_assoc();
    $current_usage = (float)$usage_res['total_usage'];

    // 3. Update Cache Table (Preserve fup_reset)
    // If fup_reset is set, clear used_quota (monthly reset)
    if ($fup_reset === 1) {
        $conn->query("UPDATE data_usage SET used_quota = 0, fup_reset = 0, updated_at = NOW() WHERE username = '$username'");
        $current_usage = 0;
    } else {
        $conn->query("
            INSERT INTO data_usage (username, plan_id, used_quota) 
            VALUES ('$username', $plan_id, $current_usage)
            ON DUPLICATE KEY UPDATE 
                plan_id = $plan_id,
                used_quota = $current_usage,
                updated_at = NOW()
        ");
    }

    // 4. Determine Target Speed
    $target_speed = $base_speed;
    $status = "Normal (Base: $base_speed)";

    if ($fup_reset === 1) {
        $status = "FUP Reset (Manual Override)";
        $target_speed = $base_speed;
    } else {
        // Tier 3 Check (Highest priority - most throttled)
        if ($t3_limit > 0 && $current_usage >= $t3_limit) {
            $target_speed = !empty($t3_speed) ? $t3_speed : '1M/1M';
            $status = "Tier 3 - " . round($current_usage/1073741824,2) . "GB / " . round($t3_limit/1073741824,1) . "GB -> $t3_speed";
        }
        // Tier 2 Check
        elseif ($t2_limit > 0 && $current_usage >= $t2_limit) {
            $target_speed = !empty($t2_speed) ? $t2_speed : $base_speed;
            $status = "Tier 2 - " . round($current_usage/1073741824,2) . "GB / " . round($t2_limit/1073741824,1) . "GB -> $t2_speed";
        }
        // Tier 1 Check
        elseif ($t1_limit > 0 && $current_usage >= $t1_limit) {
            $target_speed = !empty($t1_speed) ? $t1_speed : $base_speed;
            $status = "Tier 1 - " . round($current_usage/1073741824,2) . "GB / " . round($t1_limit/1073741824,1) . "GB -> $t1_speed";
        }
    }

    // Remove old speed if within base limit
    if ($t1_limit == 0 && $t2_limit == 0 && $t3_limit == 0) {
        $status = "No FUP (Base: $base_speed)";
    }

    // 5. Update Radreply if changed
    $check = $conn->query("SELECT value FROM radreply WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'")->fetch_assoc();
    
    if (!$check || $check['value'] !== $target_speed) {
        $conn->query("DELETE FROM radreply WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'");
        $stmt = $conn->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");
        $stmt->bind_param("ss", $username, $target_speed);
        $stmt->execute();
        
        echo "✓ $username: $status -> $target_speed\n";
        $updated++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] FUP Check Complete. Updated: $updated users\n";
?>
