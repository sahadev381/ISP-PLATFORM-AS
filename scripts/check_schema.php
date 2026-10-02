<?php
/**
 * Check that every table and column the PHP writes to exists in
 * database/schema.sql — without needing a database.
 *
 *     php scripts/check_schema.php
 *
 * The CI `schema` job already does this properly, by querying
 * information_schema after loading the schema into MySQL. That is the
 * stronger check and it stays.
 *
 * This is the same check done by parsing schema.sql, so it runs on a
 * laptop with no MySQL and in the lint job, which is minutes earlier.
 * It exists because a commit shipped with `schema_migrations` missing
 * from schema.sql and nothing caught it until CI, twice.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

$root = dirname(__DIR__);
$schemaFile = $root . '/database/schema.sql';

if (!is_file($schemaFile)) {
    fwrite(STDERR, "database/schema.sql not found\n");
    exit(1);
}

$sql = (string) file_get_contents($schemaFile);

/* ---- what the schema defines ---------------------------------------- */

$schema = [];
if (preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?\s*\((.*?)\n\)/s', $sql, $tables, PREG_SET_ORDER)) {
    foreach ($tables as $tm) {
        $table = strtolower($tm[1]);
        $schema[$table] = [];
        foreach (preg_split('/\R/', $tm[2]) as $line) {
            if (preg_match('/^\s{4}`?(\w+)`?\s+/', $line, $cm)) {
                $word = strtoupper($cm[1]);
                if (in_array($word, ['PRIMARY', 'KEY', 'UNIQUE', 'CONSTRAINT', 'INDEX', 'FOREIGN', 'FULLTEXT'], true)) {
                    continue;
                }
                $schema[$table][] = strtolower($cm[1]);
            }
        }
    }
}

if (!$schema) {
    fwrite(STDERR, "Could not parse any tables out of schema.sql\n");
    exit(1);
}

/* ---- what the code writes ------------------------------------------- */

$problems = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) {
    $path = $f->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }
    if (strpos($path, '/vendor/') !== false || strpos($path, '/.git/') !== false) {
        continue;
    }

    $rel = ltrim(str_replace($root, '', $path), '/');
    $src = (string) file_get_contents($path);

    if (preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*\(([^)]*)\)/is', $src, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) {
            $table = strtolower($m[1]);
            if (!isset($schema[$table])) {
                $problems[] = "$rel: INSERT into unknown table `$table`";
                continue;
            }
            foreach (explode(',', $m[2]) as $col) {
                $col = strtolower(trim(trim($col), '`'));
                if ($col !== '' && preg_match('/^[a-z_]\w*$/', $col) && !in_array($col, $schema[$table], true)) {
                    $problems[] = "$rel: INSERT INTO $table names unknown column `$col`";
                }
            }
        }
    }

    if (preg_match_all('/UPDATE\s+`?(\w+)`?\s+SET\s+(.*?)(?:\bWHERE\b|")/is', $src, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) {
            $table = strtolower($m[1]);
            if (!isset($schema[$table])) {
                continue;   // aliased or joined update; the MySQL job catches these
            }
            if (preg_match_all('/(?:^|,)\s*`?([a-z_]\w*)`?\s*=/im', $m[2], $cols)) {
                foreach ($cols[1] as $col) {
                    $col = strtolower($col);
                    if (!in_array($col, $schema[$table], true)) {
                        $problems[] = "$rel: UPDATE $table sets unknown column `$col`";
                    }
                }
            }
        }
    }
}

$problems = array_values(array_unique($problems));

if ($problems) {
    foreach ($problems as $p) {
        echo "::error::$p\n";
    }
    echo count($problems) . " schema problem(s) found.\n";
    exit(1);
}

echo "SCHEMA: every table and column written by the code exists in schema.sql ("
    . count($schema) . " tables).\n";
exit(0);
