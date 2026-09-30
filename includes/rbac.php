<?php
/**
 * Role-based access control and branch (tenant) isolation.
 *
 * Before this file existed, role was only ever consulted to decide what to
 * draw in the sidebar. Every page still ran the same unscoped queries, so a
 * "support" account in branch 2 could open admin_edit.php?id=1 and promote
 * itself, or user_view.php?user=x for a customer in branch 5. The helpers
 * here move the decision into the query.
 *
 * Roles actually stored in `admins`.`role`:
 *
 *   superadmin  full access, sees every branch
 *   manager     full access within its own branch
 *   support     read/operate within its own branch, no admin or settings
 *
 * Loaded by includes/auth.php, so any page that authenticates gets it.
 */

if (!defined('RBAC_LOADED')) {
    define('RBAC_LOADED', true);

    /** Canonical roles, most privileged first. */
    const RBAC_ROLES = ['superadmin', 'manager', 'support'];

    /** Higher number wins. Unknown roles get 0, i.e. no privileges. */
    const RBAC_RANK = ['superadmin' => 30, 'manager' => 20, 'support' => 10];

    /**
     * End the request with an error, matching the caller's content type.
     * Several guarded endpoints return JSON, so an HTML error body there
     * would just surface as a parse error in the browser.
     */
    function rbac_deny(int $status, string $message): void
    {
        if (function_exists('logActivity')) {
            logActivity('access_denied', $message . ' @ ' . ($_SERVER['SCRIPT_NAME'] ?? '?'));
        }

        http_response_code($status);

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || strpos($script, '/api/') !== false
            || (defined('RBAC_JSON_ENDPOINT') && RBAC_JSON_ENDPOINT);

        if ($wantsJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['status' => 'error', 'message' => $message]);
        } else {
            echo '<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        exit;
    }

    function current_role(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }

    /** Null for superadmin (unrestricted), otherwise the admin's branch. */
    function current_branch_id(): ?int
    {
        if (is_superadmin()) {
            return null;
        }
        $branch = $_SESSION['branch_id'] ?? null;
        return ($branch === null || $branch === '') ? null : (int) $branch;
    }

    function is_superadmin(): bool
    {
        return current_role() === 'superadmin';
    }

    function is_manager(): bool
    {
        return current_role() === 'manager';
    }

    function is_support(): bool
    {
        return current_role() === 'support';
    }

    function role_rank(string $role): int
    {
        return RBAC_RANK[$role] ?? 0;
    }

    /** True when the current role is $role or more privileged. */
    function role_at_least(string $role): bool
    {
        return role_rank(current_role()) >= role_rank($role);
    }

    /**
     * Stop the request unless the current role is allowed.
     *
     * @param string|string[] $allowed A minimum role, or an explicit list.
     */
    function require_role($allowed): void
    {
        $ok = is_array($allowed)
            ? in_array(current_role(), $allowed, true)
            : role_at_least($allowed);

        if ($ok) {
            return;
        }

        rbac_deny(403, 'You do not have permission to perform this action.');
    }

    /**
     * SQL fragment restricting a branch-owned table to the caller's branch.
     *
     * Returns ['', []] for a superadmin so the caller can always splice the
     * fragment in unconditionally:
     *
     *   [$scope, $params] = branch_scope('c');
     *   db_all($conn, "SELECT * FROM customers c WHERE 1=1 $scope", $params);
     *
     * Tables carrying branch_id: admins, customers, tickets.
     */
    function branch_scope(string $alias = '', string $column = 'branch_id'): array
    {
        $branch = current_branch_id();
        if ($branch === null) {
            return ['', []];
        }
        $prefix = $alias !== '' ? $alias . '.' : '';
        return [" AND {$prefix}{$column} = ?", [$branch]];
    }

    /**
     * True when a row the caller fetched actually belongs to them.
     * Use after loading a record by id to turn an IDOR into a 404/403.
     */
    function branch_owns($row, string $column = 'branch_id'): bool
    {
        $branch = current_branch_id();
        if ($branch === null) {
            return true;
        }
        if (!is_array($row) || !array_key_exists($column, $row)) {
            return false;
        }
        return (int) $row[$column] === $branch;
    }

    /** Load a record by id and 403 if it belongs to another branch. */
    function require_branch_access($row, string $column = 'branch_id'): void
    {
        if (!branch_owns($row, $column)) {
            rbac_deny(403, 'This record belongs to another branch.');
        }
    }

    /**
     * Guard a page that operates on one customer identified in the URL.
     *
     * Many pages take ?user=<username> or ?id=<n> and then query radacct,
     * usage tables and so on without ever touching `customers`, so there is
     * no branch_id to compare against. This resolves the customer first and
     * refuses the request if it belongs to another branch.
     *
     * @return array The customer row, so the caller can reuse it.
     */
    function require_customer_access($conn, $identifier, string $column = 'username'): array
    {
        if (!in_array($column, ['username', 'id'], true)) {
            throw new InvalidArgumentException('Unsupported lookup column');
        }

        $customer = db_one($conn, "SELECT * FROM customers WHERE {$column} = ?", [$identifier]);

        if (!$customer) {
            rbac_deny(404, 'Customer not found.');
        }

        require_branch_access($customer);
        return $customer;
    }

    /* ---- Backwards-compatible aliases -------------------------------- */
    /* isBranchAdmin()/isStaff() used to test for 'branchadmin' and 'staff',
       which are not values this application ever stores, so they were always
       false. They now map onto the roles that really exist. */

    if (!function_exists('isSuperAdmin')) {
        function isSuperAdmin(): bool { return is_superadmin(); }
    }
    if (!function_exists('isBranchAdmin')) {
        function isBranchAdmin(): bool { return is_manager(); }
    }
    if (!function_exists('isStaff')) {
        function isStaff(): bool { return is_support(); }
    }
}
