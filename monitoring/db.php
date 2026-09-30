<?php
/**
 * Monitoring database connection.
 * Credentials come from .env (MON_DB_*) — never hardcode them here.
 */
require_once __DIR__ . '/../includes/env.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        env('MON_DB_HOST', 'localhost'),
        env('MON_DB_USER', 'monitordb'),
        env('MON_DB_PASS', ''),
        env('MON_DB_NAME', 'monitoring')
    );
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Monitoring DB connection failed: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Monitoring database connection failed. Check .env\n");
        exit(1);
    }
    http_response_code(503);
    exit('Service temporarily unavailable.');
}
