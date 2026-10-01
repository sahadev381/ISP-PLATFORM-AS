<?php
/**
 * Migration file parsing (includes/migrations.php) and the migration
 * files themselves.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/migrations.php';

/** @var TestRunner $t */

$t->group('splitting a migration into statements');

$t->is('one statement', count(split_statements("SELECT 1;")), 1);

$t->is('two statements',
    count(split_statements("ALTER TABLE a ADD COLUMN b INT;\nALTER TABLE c ADD COLUMN d INT;")), 2);

$t->is('comment-only lines are not sent to the server',
    split_statements("-- a comment\n# another\nSELECT 1;"), ['SELECT 1;']);

$t->is('a multi-line statement stays in one piece',
    count(split_statements("CREATE TABLE x (\n  a INT,\n  b INT\n);")), 1);

/* A semicolon inside a quoted value must not split the statement. The
   splitter only breaks on a semicolon that ENDS a line, which is what
   makes that safe. */
$t->is('a semicolon inside a string does not split the statement',
    count(split_statements("UPDATE t SET note = 'a; b' WHERE id = 1;")), 1);

$t->is('a trailing statement without a semicolon is still returned',
    count(split_statements("SELECT 1")), 1);

$t->is('an empty file yields nothing', split_statements(""), []);
$t->is('a file of only comments yields nothing', split_statements("-- nothing here\n"), []);

$t->group('the migration files in this repository');

$dir = __DIR__ . '/../database/migrations';
$files = glob($dir . '/*.sql') ?: [];

$t->true('there is at least one migration', count($files) > 0);

$names = array_map('basename', $files);
foreach ($names as $n) {
    $t->true("$n is named NNN_description.sql", preg_match('/^\d{3}_[a-z0-9_]+\.sql$/', $n) === 1);
}

/* Duplicate numbers mean the apply order depends on the rest of the
   filename, which is a silent way to get two databases into different
   states. */
$numbers = array_map(fn($n) => substr($n, 0, 3), $names);
$t->is('migration numbers are unique', count($numbers), count(array_unique($numbers)));

foreach ($files as $f) {
    $statements = split_statements((string) file_get_contents($f));
    $t->true(basename($f) . ' parses into at least one statement', count($statements) > 0);
    foreach ($statements as $s) {
        $t->true(basename($f) . ': no statement is left empty', trim($s) !== '');
    }
}
