<?php
/**
 * Shared helpers for backup, restore and the backup drill.
 *
 * The pure functions here are tested; the scripts that use them are
 * thin wrappers around a shell command.
 */

if (!function_exists('backup_dump_is_complete')) {

    /**
     * Does this look like a complete mysqldump?
     *
     * mysqldump writes a "Dump completed on ..." line as the very last
     * thing it does. If the disk filled, the connection dropped or the
     * process was killed, the file still exists, the exit code can still
     * be 0 in some shells, and the backup is silently useless.
     *
     * Checking for the trailer is the cheapest way to tell a finished
     * dump from a truncated one.
     */
    function backup_dump_is_complete(string $text): bool
    {
        $tail = substr($text, -400);
        return strpos($tail, 'Dump completed') !== false;
    }

    /**
     * Minimum plausible size for a dump of this application, in bytes.
     *
     * The schema alone is ~70 tables. A "successful" dump of a few
     * hundred bytes means the credentials were right but the database
     * was empty or the wrong one.
     */
    function backup_minimum_bytes(): int
    {
        return 4096;
    }

    /**
     * Refuse database names that are obviously the live one.
     *
     * A restore is destructive: it drops and recreates every table in
     * the target. The guard is deliberately crude, because the failure
     * mode it prevents is catastrophic and the cost of a false positive
     * is typing one more flag.
     */
    function backup_target_looks_live(string $target, string $configured): bool
    {
        if ($configured !== '' && strcasecmp($target, $configured) === 0) {
            return true;
        }
        return (bool) preg_match('/^(radius|isp|production|prod|live)$/i', $target);
    }

    /**
     * Compare two sets of table => row-count.
     *
     * @param array<string,int> $source
     * @param array<string,int> $restored
     * @return string[] human-readable differences, empty when identical
     */
    function backup_compare_counts(array $source, array $restored): array
    {
        $problems = [];

        foreach ($source as $table => $count) {
            if (!array_key_exists($table, $restored)) {
                $problems[] = "table `$table` is missing from the restored copy";
                continue;
            }
            if ($restored[$table] !== $count) {
                $problems[] = sprintf(
                    'table `%s` has %d row(s) in the backup but %d after restore',
                    $table,
                    $count,
                    $restored[$table]
                );
            }
        }

        foreach ($restored as $table => $count) {
            if (!array_key_exists($table, $source)) {
                $problems[] = "table `$table` appeared in the restored copy but is not in the source";
            }
        }

        return $problems;
    }

    /**
     * Build a mysqldump command.
     *
     * The flags matter more than they look:
     *
     *   --single-transaction  take the dump inside one consistent
     *                         snapshot. Without it mysqldump locks each
     *                         table in turn, which stalls a live RADIUS
     *                         system and still produces a dump where
     *                         different tables are from different
     *                         moments - so a restored customers row can
     *                         reference a plan that did not exist yet.
     *   --quick               stream rows instead of buffering a whole
     *                         table in memory (radacct gets large).
     *   --routines/--triggers/--events
     *                         these are part of the database and are not
     *                         included by default.
     *   --set-gtid-purged=OFF keeps the dump restorable into a different
     *                         server without GTID complaints.
     *
     * The password is NOT in here: it goes through MYSQL_PWD so it does
     * not appear in `ps` for every user on the box.
     */
    function backup_dump_command(string $host, string $user, string $database, int $port = 3306): string
    {
        return sprintf(
            'mysqldump --host=%s --port=%d --user=%s'
            . ' --single-transaction --quick --routines --triggers --events'
            . ' --default-character-set=utf8mb4 --set-gtid-purged=OFF %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            escapeshellarg($database)
        );
    }
}
