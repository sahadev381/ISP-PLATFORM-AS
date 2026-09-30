<?php
/**
 * Guard for api/*.php endpoints.
 *
 * Accepts either:
 *   1. a logged-in admin session (normal AJAX calls from the panel), or
 *   2. a valid API key in the `X-API-Key` header / `api_key` parameter
 *      (for cron jobs and external integrations).  The key comes from
 *      the API_KEY entry in .env and is compared in constant time.
 *
 * Usage — put this right after `include '../config.php';`:
 *
 *     require_once __DIR__ . '/../includes/api_auth.php';
 *     api_require_auth();                 // any authenticated caller
 *     api_require_auth(['superadmin']);   // role restricted
 */

require_once __DIR__ . '/env.php';

if (!function_exists('api_require_auth')) {

    function api_client_key(): string
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                $headers[strtolower($k)] = $v;
            }
        }

        return (string) (
            $headers['x-api-key']
            ?? $_SERVER['HTTP_X_API_KEY']
            ?? $_GET['api_key']
            ?? $_POST['api_key']
            ?? ''
        );
    }

    function api_has_valid_key(): bool
    {
        $expected = (string) env('API_KEY', '');
        // An empty/unset API_KEY must never authenticate anyone.
        if (strlen($expected) < 16) {
            return false;
        }
        return hash_equals($expected, api_client_key());
    }

    function api_is_logged_in(): bool
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }
        return !empty($_SESSION['user_id']);
    }

    function api_fail(int $code, string $message): void
    {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    /**
     * @param string[] $roles Allowed admin roles. Empty = any logged-in admin.
     */
    function api_require_auth(array $roles = []): void
    {
        if (PHP_SAPI === 'cli') {
            return; // cron scripts run locally
        }

        if (api_has_valid_key()) {
            return;
        }

        if (!api_is_logged_in()) {
            api_fail(401, 'Authentication required');
        }

        if ($roles) {
            $role = $_SESSION['role'] ?? '';
            if (!in_array($role, $roles, true)) {
                api_fail(403, 'Insufficient permissions');
            }
        }
    }

    /**
     * Reject anything that is not a POST request (for state-changing endpoints).
     */
    function api_require_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            api_fail(405, 'POST required');
        }
    }
}
