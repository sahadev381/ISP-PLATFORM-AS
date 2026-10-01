#!/usr/bin/php
<?php
/**
 * Restore a backup into a database.
 *
 *     php scripts/db_restore.php --file=backup_x_2026-10-01.sql.gz --into=scratch
 *
 * DESTRUCTIVE. It drops and recreates every table the dump contains.
 *
 * It refuses to restore into the configured live database unless given
 * --i-understand-this-overwrites. The guard is crude on purpose: the
 * failure it prevents is catastrophic and the cost of a false positive
 * is typing one more flag.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/backup.php';

env_load();

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

$backupDir = (string) (env('BACKUP_DIR') ?: __DIR__ . '/../backups');
$file      = (string) ($opts['file'] ?? '');
$into      = (string) ($opts['into'] ?? '');

if ($file === '' || $into === '') {
    fwrite(STDERR, "Usage: php scripts/db_restore.php --file=<name.sql.gz> --into=<database>\n");
    exit(1);
}

$path = (strpos($file, '/') === 0) ? $file : $backupDir . '/' . $file;
if (!is_file($path)) {
    fwrite(STDERR, "No such backup: $path\n");
    exit(1);
}

$configured = (string) env('DB_NAME', '');
if (backup_target_looks_live($into, $configured) && !isset($opts['i-understand-this-overwrites'])) {
    fwrite(STDERR, "Refusing to restore into '$into': it looks like a live database.\n");
    fwrite(STDERR, "Re-run with --i-understand-this-overwrites if that is really what you want.\n");
    exit(1);
}

/* If a checksum was recorded, a mismatch means the file rotted on disk
   and restoring it would be worse than not restoring at all. */
if (is_file($path . '.sha256')) {
    $expected = strtok((string) file_get_contents($path . '.sha256'), ' ');
    $actual   = hash_file('sha256', $path);
    if ($expected !== $actual) {
        fwrite(STDERR, "Checksum mismatch for $file - the backup is corrupt.\n");
        fwrite(STDERR, "  expected $expected\n  actual   $actual\n");
        exit(1);
    }
    echo "Checksum ok.\n";
}

$host = (string) env('DB_HOST', 'localhost');
$user = (string) env('DB_USER', 'root');
$pass = (string) env('DB_PASS', '');
$port = (int) env('DB_PORT', 3306);

putenv('MYSQL_PWD=' . $pass);

$mysql = sprintf('mysql --host=%s --port=%d --user=%s --default-character-set=utf8mb4',
    escapeshellarg($host), $port, escapeshellarg($user));

echo "Creating database '$into' if needed ...\n";
exec($mysql . ' -e ' . escapeshellarg(
    "CREATE DATABASE IF NOT EXISTS `$into` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
), $o1, $c1);

if ($c1 !== 0) {
    putenv('MYSQL_PWD');
    fwrite(STDERR, "Could not create '$into'\n");
    exit(1);
}

echo "Restoring $file into '$into' ...\n";

$reader = (substr($path, -3) === '.gz') ? 'gunzip -c ' : 'cat ';
$command = $reader . escapeshellarg($path) . ' | ' . $mysql . ' ' . escapeshellarg($into);
exec($command, $o2, $c2);

putenv('MYSQL_PWD');

if ($c2 !== 0) {
    fwrite(STDERR, "Restore failed with exit code $c2\n");
    exit(1);
}

echo "Restored.\n";
