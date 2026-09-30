<?php
/**
 * CSRF protection helpers.
 *
 * In a form:
 *     <?= csrf_field() ?>
 *
 * At the top of the POST handler:
 *     csrf_check();
 *
 * For fetch()/AJAX, send the token as the `_csrf` field or the
 * `X-CSRF-Token` header.
 */

if (!function_exists('csrf_token')) {

    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    function csrf_valid(): bool
    {
        $sent = $_POST['_csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
        return is_string($sent)
            && $sent !== ''
            && hash_equals(csrf_token(), $sent);
    }

    /**
     * Abort the request when the token is missing or wrong.
     */
    function csrf_check(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        if (!csrf_valid()) {
            http_response_code(419);
            exit('CSRF token mismatch. Please reload the page and try again.');
        }
    }
}
