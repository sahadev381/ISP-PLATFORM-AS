<?php
/**
 * Database backup.
 *
 *     php scripts/db_backup.php
 *
 * Writes a compressed, verified dump to BACKUP_DIR and prunes old ones.
 *
 * WHAT CHANGED AND WHY
 *
 * The previous version ran a bare `mysqldump db > file`, checked the
 * exit code, gzipped the result and deleted anything older than seven
 * days. Three problems:
 *
 *  1. No --single-transaction. mysqldump locked each table in turn,
 *     which stalls a live RADIUS system, and still produced a dump
 *     where different tables came from different moments - so a
 *     restored customers row could reference a plan that did not exist
 *     yet.
 *  2. No verification. A dump truncated by a full disk or a dropped
 *     connection still produced a file, and the retention sweep then
 *     deleted the older, good backups.
 *  3. No routines, triggers or events - those are not dumped by
 *     default and would have been lost.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/backup.php';

env_load();

$backupDir = (string) (env('BACKUP_DIR') ?: __DIR__ . '/../backups');
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Could not create $backupDir\n");
    exit(1);
}

$host = (string) env('DB_HOST', 'localhost');
$user = (string) env('DB_USER', 'radius');
$pass = (string) env('DB_PASS', '');
$name = (string) env('DB_NAME', 'radius');
$port = (int) env('DB_PORT', 3306);

$timestamp = date('Y-m-d_H-i-s');
$filename  = "backup_{$name}_{$timestamp}.sql";
$filepath  = $backupDir . '/' . $filename;

echo "Backing up '$name' from $host ...\n";

putenv('MYSQL_PWD=' . $pass);
$command = backup_dump_command($host, $user, $name, $port)
    . ' > ' . escapeshellarg($filepath) . ' 2> ' . escapeshellarg($filepath . '.err');
exec($command, $output, $exitCode);
putenv('MYSQL_PWD');

$stderr = is_file($filepath . '.err') ? trim((string) file_get_contents($filepath . '.err')) : '';
@unlink($filepath . '.err');

if ($exitCode !== 0) {
    fwrite(STDERR, "mysqldump exited with $exitCode\n");
    if ($stderr !== '') {
        fwrite(STDERR, $stderr . "\n");
    }
    @unlink($filepath);
    exit(1);
}

/* Verify BEFORE pruning anything. A bad backup that causes the good
   ones to be deleted is worse than no backup at all. */

$size = (int) @filesize($filepath);
if ($size < backup_minimum_bytes()) {
    fwrite(STDERR, "Dump is only $size bytes - refusing to treat that as a backup.\n");
    @unlink($filepath);
    exit(1);
}

$tail = '';
$fh = fopen($filepath, 'rb');
if ($fh) {
    fseek($fh, max(0, $size - 400));
    $tail = (string) fread($fh, 400);
    fclose($fh);
}

if (!backup_dump_is_complete($tail)) {
    fwrite(STDERR, "Dump does not end with mysqldump's completion marker - it is truncated.\n");
    @unlink($filepath);
    exit(1);
}

echo "  dump ok (" . number_format($size / 1048576, 1) . " MB)\n";

/* Compress, then checksum the compressed file so a later restore can
   prove the file has not rotted on disk. */

exec('gzip -f ' . escapeshellarg($filepath), $gzOut, $gzCode);
if ($gzCode !== 0 || !is_file($filepath . '.gz')) {
    fwrite(STDERR, "gzip failed with $gzCode\n");
    exit(1);
}

$gzPath = $filepath . '.gz';
@chmod($gzPath, 0600);

$sum = hash_file('sha256', $gzPath);
file_put_contents($gzPath . '.sha256', $sum . '  ' . basename($gzPath) . "\n");
@chmod($gzPath . '.sha256', 0600);

echo "  wrote " . basename($gzPath) . "\n";
echo "  sha256 $sum\n";

/* Prune - only now, and only files that are not the one just made. */

$days = (int) env('BACKUP_RETENTION_DAYS', 7);
$cutoff = time() - ($days * 86400);
$removed = 0;

foreach (glob($backupDir . '/backup_*.sql.gz') ?: [] as $old) {
    if ($old === $gzPath) {
        continue;
    }
    if (filemtime($old) < $cutoff) {
        @unlink($old);
        @unlink($old . '.sha256');
        $removed++;
        echo "  pruned " . basename($old) . "\n";
    }
}

$remaining = count(glob($backupDir . '/backup_*.sql.gz') ?: []);
echo "Done. $removed pruned, $remaining backup(s) retained.\n";

/* A backup nobody has restored is not a backup. */
echo "\nVerify it: php scripts/backup_drill.php --file=" . basename($gzPath) . "\n";
