<?php
/**
 * Migration file parsing.
 *
 * Kept apart from scripts/migrate.php so it can be tested without a
 * database. This code decides which SQL gets executed against
 * production, so it is worth pinning down.
 */

if (!function_exists('split_statements')) {

    /**
     * Split a migration file into individual statements.
     *
     * Splits only on a semicolon that ends a line. That keeps the parser
     * simple and means a semicolon inside a string literal does not tear
     * a statement in half. Comment-only lines are dropped so they are not
     * sent to the server.
     *
     * @return string[]
     */
    function split_statements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $statements = [];
        $current = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $current .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }
}
