<?php
/**
 * Database connection for command-line tooling.
 *
 * The maintenance scripts need a connection before config.php
 * necessarily exists — CI has no config.php at all, and a first-time
 * deployment runs migrations before the app is configured. So: use
 * config.php when it is there, fall back to the environment when it is
 * not.
 */

if (!function_exists('cli_db_connect')) {

    function cli_db_connect(): mysqli
    {
        $configPath = __DIR__ . '/../config.php';

        if (is_file($configPath)) {
            require_once $configPath;
            global $conn;
            if ($conn instanceof mysqli) {
                return $conn;
            }
        }

        require_once __DIR__ . '/env.php';
        env_load();

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = new mysqli(
                (string) (getenv('DB_HOST') ?: env('DB_HOST', '127.0.0.1')),
                (string) (getenv('DB_USER') ?: env('DB_USER', 'root')),
                (string) (getenv('DB_PASS') !== false ? getenv('DB_PASS') : env('DB_PASS', '')),
                (string) (getenv('DB_NAME') ?: env('DB_NAME', 'isp_platform')),
                (int) (getenv('DB_PORT') ?: env('DB_PORT', 3306))
            );
            $conn->set_charset('utf8mb4');
        } catch (Throwable $e) {
            fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
            fwrite(STDERR, "Set DB_HOST/DB_USER/DB_PASS/DB_NAME, or create config.php.\n");
            exit(1);
        }

        require_once __DIR__ . '/db.php';

        return $conn;
    }
}
