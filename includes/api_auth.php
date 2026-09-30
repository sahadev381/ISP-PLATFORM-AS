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

    /**
     * The id of the logged-in customer portal user, or null.
     *
     * Customer sessions are separate from admin sessions: customer/*.php
     * sets customer_id / customer_user, never user_id. Endpoints that
     * customers may reach (paying their own invoice) have to accept this
     * as well, and must then scope the record to the returned id.
     */
    function api_customer_id(): ?int
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }
        $id = (int) ($_SESSION['customer_id'] ?? 0);
        return $id > 0 ? $id : null;
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
     * Who may ask us to start or check a payment.
     *
     * The payment endpoints had no authentication at all, so any origin
     * could drive the gateway with them. A caller must now be an
     * authenticated admin (session or API key) or a logged-in customer;
     * a customer must additionally be confined to their own records by
     * the endpoint, via api_customer_id().
     *
     * Gateway-driven actions (webhook/callback) are deliberately exempt:
     * they arrive server-to-server with no session, and are validated by
     * re-checking the payment with the gateway instead.
     */
    function api_require_payment_caller(): void
    {
        if (PHP_SAPI === 'cli' || api_has_valid_key() || api_is_logged_in()) {
            return;
        }
        if (api_customer_id() !== null) {
            return;
        }
        api_fail(401, 'Authentication required');
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
