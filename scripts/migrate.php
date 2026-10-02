<?php
/**
 * Database migration runner.
 *
 *     php scripts/migrate.php status     what has run, what has not
 *     php scripts/migrate.php up         apply everything pending
 *     php scripts/migrate.php up --dry   print the SQL, change nothing
 *     php scripts/migrate.php baseline   mark everything as applied
 *
 * NEW INSTALL vs EXISTING INSTALL
 *
 *     new:       load database/schema.sql, then `migrate.php baseline`
 *     existing:  `migrate.php up`
 *
 * schema.sql always describes the CURRENT shape of the database, so a
 * fresh install already has everything the migrations would add.
 * Running them anyway would fail on "Duplicate column name". `baseline`
 * records them as applied without executing them, which is what puts a
 * new database and an upgraded one on the same footing.
 *
 * WHY
 *
 * `database/schema.sql` describes what a *new* database should look
 * like. There was no way to move an existing one from one version to
 * the next, so upgrading a live install meant hand-written ALTERs typed
 * at a production console, with no record of what had been applied.
 * That blocks safe releases more than any individual bug.
 *
 * HOW
 *
 * Files in database/migrations/ named NNN_description.sql, applied in
 * filename order, each recorded in a `schema_migrations` table so it
 * runs exactly once. Statements are split on semicolons at the end of a
 * line, which keeps the parser simple and is adequate for DDL.
 *
 * Each migration runs inside a transaction where the storage engine
 * allows it. MySQL commits implicitly on DDL, so a half-applied
 * migration is still possible — hence the dry run, and hence the rule
 * that migrations must be written to be re-runnable where practical
 * (IF NOT EXISTS, IF EXISTS).
 *
 * Deliberately not included: a `down` path. Rolling a schema change
 * backwards on a live database is usually more dangerous than rolling
 * forward with a new migration, and an untested down-migration is a
 * trap rather than a safety net.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Migrations may only be run from the command line.\n");
}

require_once __DIR__ . '/../includes/cli_db.php';
require_once __DIR__ . '/../includes/migrations.php';
$conn = cli_db_connect();

$migrationsDir = __DIR__ . '/../database/migrations';

$command = $argv[1] ?? 'status';
$dryRun  = in_array('--dry', $argv, true) || in_array('--dry-run', $argv, true);

/* ------------------------------------------------------------------ */

function ensure_migrations_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version     VARCHAR(255) NOT NULL,
            applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/** @return string[] versions already applied */
function applied_versions(mysqli $conn): array
{
    $out = [];
    $res = $conn->query("SELECT version FROM schema_migrations ORDER BY version");
    while ($row = $res->fetch_assoc()) {
        $out[] = $row['version'];
    }
    return $out;
}

/** @return string[] migration filenames, in order */
function available_migrations(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql') ?: [];
    $files = array_map('basename', $files);
    sort($files, SORT_STRING);
    return $files;
}

/* ------------------------------------------------------------------ */

ensure_migrations_table($conn);

$applied   = applied_versions($conn);
$available = available_migrations($migrationsDir);
$pending   = array_values(array_diff($available, $applied));

/* A migration that was applied but whose file has since vanished means
   the database is ahead of the checkout. Worth saying out loud. */
$orphans = array_values(array_diff($applied, $available));

if ($command === 'status') {
    echo "Applied:\n";
    foreach ($applied as $v) {
        echo "  [x] $v\n";
    }
    if (!$applied) {
        echo "  (none)\n";
    }

    echo "\nPending:\n";
    foreach ($pending as $v) {
        echo "  [ ] $v\n";
    }
    if (!$pending) {
        echo "  (none)\n";
    }

    if ($orphans) {
        echo "\nWARNING: recorded in the database but missing from the checkout:\n";
        foreach ($orphans as $v) {
            echo "  [?] $v\n";
        }
        echo "  The database may be running a newer version than this code.\n";
    }

    exit(0);
}

if ($command === 'baseline') {
    if (!$pending) {
        echo "Nothing to record - every migration is already applied.\n";
        exit(0);
    }

    echo "Recording " . count($pending) . " migration(s) as applied WITHOUT running them.\n";
    echo "Only correct on a database created from database/schema.sql.\n\n";

    $stmt = $conn->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
    foreach ($pending as $version) {
        $stmt->bind_param('s', $version);
        $stmt->execute();
        echo "  recorded $version\n";
    }

    echo "\nDone.\n";
    exit(0);
}

if ($command !== 'up') {
    fwrite(STDERR, "Usage: php scripts/migrate.php [status|up|baseline] [--dry]\n");
    exit(1);
}

if (!$pending) {
    echo "Nothing to do - the database is up to date.\n";
    exit(0);
}

echo ($dryRun ? "DRY RUN - nothing will be changed.\n\n" : "");

foreach ($pending as $version) {
    $path = $migrationsDir . '/' . $version;
    $sql  = file_get_contents($path);

    if ($sql === false) {
        fwrite(STDERR, "Could not read $path\n");
        exit(1);
    }

    $statements = split_statements($sql);

    echo "-- $version (" . count($statements) . " statement"
        . (count($statements) === 1 ? '' : 's') . ")\n";

    if ($dryRun) {
        foreach ($statements as $s) {
            echo $s . "\n";
        }
        echo "\n";
        continue;
    }

    $conn->begin_transaction();
    try {
        foreach ($statements as $s) {
            if (!$conn->query($s)) {
                throw new RuntimeException($conn->error);
            }
        }

        $stmt = $conn->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
        $stmt->bind_param('s', $version);
        $stmt->execute();

        $conn->commit();
        echo "   applied\n";
    } catch (Throwable $e) {
        $conn->rollback();
        fwrite(STDERR, "\nFAILED on $version: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Note: MySQL commits DDL implicitly, so earlier statements in\n");
        fwrite(STDERR, "this file may already have taken effect. Check before retrying.\n");
        exit(1);
    }
}

echo "\nDone.\n";
