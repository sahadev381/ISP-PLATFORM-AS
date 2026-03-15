#!/usr/bin/php
<?php
/**
 * Automated Database Backup Worker
 * Generates SQL dumps and cleans old files
 */

$backup_dir = __DIR__ . '/../backups';
if (!file_exists($backup_dir)) mkdir($backup_dir, 0777, true);

$db_user = "radius";
$db_pass = "radiuspass";
$db_name = "radius";

$timestamp = date('Y-m-d_H-i-s');
$filename = "backup_{$db_name}_{$timestamp}.sql";
$filepath = "{$backup_dir}/{$filename}";

echo "Starting database backup for '{$db_name}'...\n";

// Execute mysqldump
$command = "mysqldump -u {$db_user} -p{$db_pass} {$db_name} > {$filepath}";
exec($command, $output, $return_var);

if ($return_var === 0) {
    // Compress the backup
    exec("gzip {$filepath}");
    echo "Successfully created: {$filename}.gz\n";
    
    // --- Cleanup: Remove backups older than 7 days ---
    $files = glob("{$backup_dir}/backup_*.sql.gz");
    $now = time();
    $days = 7;
    
    foreach ($files as $file) {
        if ($now - filemtime($file) > ($days * 86400)) {
            unlink($file);
            echo "Cleaned up old backup: " . basename($file) . "\n";
        }
    }
} else {
    echo "Backup FAILED with error code: {$return_var}\n";
}
?>
