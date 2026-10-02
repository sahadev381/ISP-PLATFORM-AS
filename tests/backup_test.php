<?php
/**
 * Backup helpers (includes/backup.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/backup.php';

/** @var TestRunner $t */

$t->group('detecting a truncated dump');

/* mysqldump writes this as the last thing it does. A dump cut short by
   a full disk or a dropped connection still leaves a file behind, and
   the old backup script would have accepted it and then deleted the
   older, good backups. */
$t->true('a dump ending with the completion marker is accepted',
    backup_dump_is_complete("INSERT INTO x VALUES (1);\n-- Dump completed on 2026-10-01 10:00:00\n"));

$t->false('a dump cut off mid-statement is rejected',
    backup_dump_is_complete("INSERT INTO x VALUES (1),(2),(3"));

$t->false('an empty file is rejected', backup_dump_is_complete(''));

$t->false('a file that only mentions the phrase early on is rejected',
    backup_dump_is_complete("-- Dump completed on 2020-01-01\n" . str_repeat("INSERT INTO t VALUES (1);\n", 200)));

$t->true('a tiny but complete dump is accepted',
    backup_dump_is_complete("-- Dump completed"));

$t->group('refusing to restore over a live database');

/* A restore drops and recreates every table in the target. */
$t->true('the configured database is protected',
    backup_target_looks_live('isp_live', 'isp_live'));
$t->true('the match is case-insensitive',
    backup_target_looks_live('ISP_LIVE', 'isp_live'));
$t->true('common production names are protected even if not configured',
    backup_target_looks_live('radius', ''));
$t->true('so is "production"', backup_target_looks_live('production', ''));
$t->false('a scratch database is allowed',
    backup_target_looks_live('drill_20261001_120000', 'isp_live'));
$t->false('a staging copy is allowed',
    backup_target_looks_live('isp_staging', 'isp_live'));

$t->group('comparing a restored copy against the source');

$src = ['customers' => 1200, 'invoices' => 4300, 'radacct' => 980000];

$t->is('an identical restore reports nothing',
    backup_compare_counts($src, $src), []);

$missing = backup_compare_counts($src, ['customers' => 1200, 'radacct' => 980000]);
$t->is('a missing table is reported', count($missing), 1);
$t->true('and names the table', strpos($missing[0], 'invoices') !== false);

$short = backup_compare_counts($src, ['customers' => 1200, 'invoices' => 4299, 'radacct' => 980000]);
$t->is('a single missing row is reported', count($short), 1);
$t->true('with both counts', strpos($short[0], '4300') !== false && strpos($short[0], '4299') !== false);

$extra = backup_compare_counts(['customers' => 1], ['customers' => 1, 'leftover' => 5]);
$t->is('an unexpected extra table is reported', count($extra), 1);

$t->is('an empty source and an empty restore match', backup_compare_counts([], []), []);

$t->group('the mysqldump command');

$cmd = backup_dump_command('db.internal', 'backup_user', 'isp_live', 3307);

/* Without --single-transaction mysqldump locks each table in turn,
   stalling a live RADIUS system, and still yields a dump where
   different tables come from different moments. */
$t->true('takes a consistent snapshot', strpos($cmd, '--single-transaction') !== false);
$t->true('streams rather than buffering whole tables', strpos($cmd, '--quick') !== false);
$t->true('includes stored routines', strpos($cmd, '--routines') !== false);
$t->true('includes triggers', strpos($cmd, '--triggers') !== false);
$t->true('includes events', strpos($cmd, '--events') !== false);
$t->true('uses utf8mb4', strpos($cmd, 'utf8mb4') !== false);
$t->true('passes the port through', strpos($cmd, '--port=3307') !== false);

/* The password must never reach the command line - it would be visible
   in ps to every user on the box. */
$t->lacks('no password on the command line', $cmd, '--password');
$t->lacks('and no -p either', $cmd, ' -p');
