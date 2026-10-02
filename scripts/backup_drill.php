<?php
/**
 * Backup restore drill.
 *
 *     php scripts/backup_drill.php              take a fresh backup and verify it
 *     php scripts/backup_drill.php --file=x.gz  verify an existing backup
 *     php scripts/backup_drill.php --keep       leave the scratch database behind
 *
 * A backup nobody has restored is not a backup. This restores one into
 * a scratch database and compares it, table by table and row by row,
 * against the source. Then it throws the scratch database away.
 *
 * Exits non-zero when the restored copy does not match, so it can run
 * from cron and actually mean something.
 *
 * It never touches the live database: it only reads from it, and it
 * writes exclusively to a scratch schema named drill_<timestamp>.
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

$host = (string) env('DB_HOST', 'localhost');
$user = (string) env('DB_USER', 'root');
$pass = (string) env('DB_PASS', '');
$live = (string) env('DB_NAME', 'radius');
$port = (int) env('DB_PORT', 3306);
$backupDir = (string) (env('BACKUP_DIR') ?: __DIR__ . '/../backups');

$scratch = 'drill_' . date('Ymd_His');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function connect(string $host, string $user, string $pass, string $db, int $port): mysqli
{
    $c = new mysqli($host, $user, $pass, $db, $port);
    $c->set_charset('utf8mb4');
    return $c;
}

/** @return array<string,int> table => row count */
function table_counts(mysqli $conn, string $schema): array
{
    $counts = [];

    $res = $conn->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($schema) . "'
           AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );

    $tables = [];
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }

    /* information_schema.TABLE_ROWS is an estimate for InnoDB, which is
       useless for a verification. Count properly. */
    foreach ($tables as $t) {
        $r = $conn->query("SELECT COUNT(*) FROM `" . str_replace('`', '', $schema) . "`.`" . str_replace('`', '', $t) . "`");
        $counts[$t] = (int) ($r->fetch_row()[0] ?? 0);
    }

    return $counts;
}

echo "Backup restore drill\n";
echo str_repeat('=', 50) . "\n\n";

/* ---- 1. obtain a backup ------------------------------------------- */

if (isset($opts['file'])) {
    $file = (string) $opts['file'];
    echo "Using existing backup: $file\n";
} else {
    echo "Taking a fresh backup ...\n";
    exec('php ' . escapeshellarg(__DIR__ . '/db_backup.php'), $out, $code);
    foreach ($out as $line) {
        echo "  $line\n";
    }
    if ($code !== 0) {
        fwrite(STDERR, "\nBackup failed; nothing to verify.\n");
        exit(1);
    }
    $candidates = glob($backupDir . '/backup_*.sql.gz') ?: [];
    usort($candidates, fn($a, $b) => filemtime($b) <=> filemtime($a));
    if (!$candidates) {
        fwrite(STDERR, "No backup file found in $backupDir\n");
        exit(1);
    }
    $file = basename($candidates[0]);
}

/* ---- 2. record what the live database contains --------------------- */

echo "\nReading row counts from '$live' ...\n";

try {
    $liveConn = connect($host, $user, $pass, $live, $port);
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to '$live': " . $e->getMessage() . "\n");
    exit(1);
}

$sourceCounts = table_counts($liveConn, $live);
printf("  %d tables, %s rows total\n", count($sourceCounts), number_format(array_sum($sourceCounts)));

/* ---- 3. restore into a scratch database ---------------------------- */

echo "\nRestoring into scratch database '$scratch' ...\n";

exec(
    'php ' . escapeshellarg(__DIR__ . '/db_restore.php')
    . ' --file=' . escapeshellarg($file)
    . ' --into=' . escapeshellarg($scratch),
    $restoreOut,
    $restoreCode
);

foreach ($restoreOut as $line) {
    echo "  $line\n";
}

$cleanup = function () use ($host, $user, $pass, $port, $scratch, $opts) {
    if (isset($opts['keep'])) {
        echo "\nScratch database '$scratch' left in place (--keep).\n";
        return;
    }
    putenv('MYSQL_PWD=' . $pass);
    exec(sprintf('mysql --host=%s --port=%d --user=%s -e %s',
        escapeshellarg($host), $port, escapeshellarg($user),
        escapeshellarg("DROP DATABASE IF EXISTS `$scratch`")));
    putenv('MYSQL_PWD');
};

if ($restoreCode !== 0) {
    fwrite(STDERR, "\nRestore failed. THE BACKUP IS NOT USABLE.\n");
    $cleanup();
    exit(1);
}

/* ---- 4. compare ---------------------------------------------------- */

echo "\nComparing ...\n";

try {
    $scratchConn = connect($host, $user, $pass, $scratch, $port);
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot read the restored copy: " . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}

$restoredCounts = table_counts($scratchConn, $scratch);
$problems = backup_compare_counts($sourceCounts, $restoredCounts);

$cleanup();

echo "\n" . str_repeat('=', 50) . "\n";

if ($problems) {
    echo "DRILL FAILED - the restored copy does not match the source.\n\n";
    foreach ($problems as $p) {
        echo "  - $p\n";
    }
    echo "\nNote: row counts can differ legitimately if the database was\n";
    echo "written to between the dump and this comparison. Re-run on a\n";
    echo "quiet system before concluding the backup is broken.\n";
    exit(1);
}

printf(
    "DRILL PASSED - %d tables and %s rows restored identically from %s.\n",
    count($sourceCounts),
    number_format(array_sum($sourceCounts)),
    $file
);
exit(0);
