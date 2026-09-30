#!/usr/bin/php
<?php
/**
 * Automated Database Backup Worker
 * Generates SQL dumps and cleans old files
 */

require_once __DIR__ . '/../includes/env.php';

$backup_dir = env('BACKUP_DIR') ?: __DIR__ . '/../backups';
if (!file_exists($backup_dir)) mkdir($backup_dir, 0700, true);

$db_host = env('DB_HOST', 'localhost');
$db_user = env('DB_USER', 'radius');
$db_pass = (string) env('DB_PASS', '');
$db_name = env('DB_NAME', 'radius');

$timestamp = date('Y-m-d_H-i-s');
$filename = "backup_{$db_name}_{$timestamp}.sql";
$filepath = "{$backup_dir}/{$filename}";

echo "Starting database backup for '{$db_name}'...\n";

// Execute mysqldump.
// The password is passed via MYSQL_PWD instead of the command line so it
// does not show up in `ps` output for every user on the box.
putenv('MYSQL_PWD=' . $db_pass);
$command = sprintf(
    'mysqldump -h %s -u %s %s > %s',
    escapeshellarg($db_host),
    escapeshellarg($db_user),
    escapeshellarg($db_name),
    escapeshellarg($filepath)
);
exec($command, $output, $return_var);
putenv('MYSQL_PWD');

if ($return_var === 0) {
    // Compress the backup
    exec('gzip ' . escapeshellarg($filepath));
    @chmod($filepath . '.gz', 0600);
    echo "Successfully created: {$filename}.gz\n";
    
    // --- Cleanup: Remove backups older than 7 days ---
    $files = glob("{$backup_dir}/backup_*.sql.gz");
    $now = time();
    $days = (int) env('BACKUP_RETENTION_DAYS', 7);
    
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
