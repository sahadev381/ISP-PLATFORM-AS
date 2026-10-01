<?php
/**
 * Session bootstrap.
 *
 * WHY THIS FILE EXISTS
 *
 * config.php.example starts the session with hardened cookie flags
 * (httponly, secure, samesite). That hardening only applies if config.php
 * is the thing that starts the session - and it was not. Twenty-five
 * files called session_start() on line 2, before including config.php:
 * the whole hotspot area, the whole billing area, every logout script,
 * includes/auth.php and includes/customer.php.
 *
 * By the time config.php ran, PHP had already sent
 *
 *     Set-Cookie: PHPSESSID=...
 *
 * with no HttpOnly, no Secure and no SameSite, and
 * session_set_cookie_params() does nothing once a session is active. So
 * the session cookie was readable by any injected script and was sent on
 * cross-site requests.
 *
 * Every entry point now calls session_boot() instead, which configures
 * the cookie before starting the session - and repairs the flags if some
 * older file got there first.
 */

require_once __DIR__ . '/env.php';

if (!function_exists('session_boot')) {

    /**
     * Start the session with hardened cookie attributes. Safe to call
     * repeatedly and safe to call from CLI scripts (where it does nothing).
     */
    function session_boot(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        env_load();

        if (session_status() === PHP_SESSION_ACTIVE) {
            // Something started the session before us - an old deployed
            // config.php, or a file we have not converted yet. We cannot
            // retroactively change the cookie PHP already queued, but we
            // can overwrite it with one carrying the right flags.
            session_repair_cookie();
            return;
        }

        if (headers_sent($file, $line)) {
            // Too late to send a cookie at all. Log it rather than
            // emitting a warning into the middle of the page.
            error_log("session_boot(): output already started at $file:$line");
            return;
        }

        session_set_cookie_params(session_cookie_params());

        // Refuse a session id the client invented. Without this an
        // attacker can fix a victim's session id by planting a cookie.
        ini_set('session.use_strict_mode', '1');

        // Never read the session id out of the URL.
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_start();
    }

    /**
     * The cookie attributes we want, in session_set_cookie_params() form.
     */
    function session_cookie_params(): array
    {
        return [
            'lifetime' => 0,            // dies with the browser
            'path'     => '/',
            'domain'   => '',
            'secure'   => session_https(),
            'httponly' => true,         // not visible to document.cookie
            'samesite' => 'Lax',        // not sent on cross-site POSTs
        ];
    }

    /**
     * Whether to mark the cookie Secure.
     *
     * APP_HTTPS wins when it is set, because a site behind a TLS
     * terminating proxy looks like plain HTTP to PHP. Otherwise fall back
     * to what the request itself looks like - marking a cookie Secure on
     * a plain-HTTP deployment would make logins impossible.
     */
    function session_https(): bool
    {
        $configured = env('APP_HTTPS', null);
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Re-send the session cookie with the right flags when the session was
     * already started by someone else.
     */
    function session_repair_cookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $name = session_name();
        if (empty($_COOKIE[$name]) && session_id() === '') {
            return;
        }

        setcookie($name, session_id(), session_cookie_params());
    }

    /**
     * Log the current user out completely: session data, the $_SESSION
     * array and the cookie.
     *
     * session_destroy() on its own leaves the cookie in the browser, so
     * the next request arrives carrying a session id that no longer
     * exists. With use_strict_mode on, PHP rejects it and issues a fresh
     * one, but relying on that is not a logout.
     */
    function session_kill(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_boot();
        }

        $_SESSION = [];

        if (!headers_sent() && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
