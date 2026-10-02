<?php
/**
 * Prepared-statement helpers.
 *
 * Use these instead of building SQL strings with variables in them.
 *
 *   $user  = db_one($conn, "SELECT * FROM customers WHERE username = ?", [$username]);
 *   $rows  = db_all($conn, "SELECT * FROM invoices WHERE username = ?", [$username]);
 *   db_exec($conn, "UPDATE customers SET status = ? WHERE id = ?", ['active', $id]);
 *
 * Types are inferred (int / float / string), so callers never touch bind_param.
 */

if (!function_exists('db_types')) {

    function db_types(array $params): string
    {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p) || is_bool($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Run a prepared statement and return the mysqli_stmt.
     */
    function db_stmt(mysqli $conn, string $sql, array $params = []): mysqli_stmt
    {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('SQL prepare failed: ' . $conn->error);
        }
        if ($params) {
            // bools must be cast; mysqli has no bool type
            $params = array_map(static fn($p) => is_bool($p) ? (int) $p : $p, $params);
            $stmt->bind_param(db_types($params), ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * Fetch a single row as an associative array, or null.
     */
    function db_one(mysqli $conn, string $sql, array $params = []): ?array
    {
        $stmt = db_stmt($conn, $sql, $params);
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Fetch all rows as a list of associative arrays.
     */
    function db_all(mysqli $conn, string $sql, array $params = []): array
    {
        $stmt = db_stmt($conn, $sql, $params);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Fetch a single scalar value (first column of first row), or $default.
     */
    function db_value(mysqli $conn, string $sql, array $params = [], $default = null)
    {
        $stmt = db_stmt($conn, $sql, $params);
        $row  = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row[0] ?? $default;
    }

    /**
     * Run INSERT/UPDATE/DELETE. Returns affected row count.
     */
    function db_exec(mysqli $conn, string $sql, array $params = []): int
    {
        $stmt     = db_stmt($conn, $sql, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /**
     * Run an INSERT and return the new auto-increment id.
     */
    function db_insert(mysqli $conn, string $sql, array $params = []): int
    {
        $stmt = db_stmt($conn, $sql, $params);
        $id   = $conn->insert_id;
        $stmt->close();
        return (int) $id;
    }

    /**
     * Escape a value for use inside a LIKE pattern, then wrap it in %...%.
     */
    function db_like(string $value): string
    {
        return '%' . addcslashes($value, '%_\\') . '%';
    }
}
