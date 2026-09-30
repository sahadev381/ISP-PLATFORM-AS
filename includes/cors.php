<?php
/**
 * Cross-origin policy for the JSON endpoints.
 *
 * The payment endpoints used to send `Access-Control-Allow-Origin: *`,
 * which is the widest possible setting on the most sensitive route in
 * the application. Nothing actually needed it:
 *
 *   - gateway callbacks are server-to-server, and CORS does not apply
 *     to requests that are not made by a browser;
 *   - our own pages call these endpoints from the same origin, which
 *     does not need CORS either.
 *
 * So the default here is to send no CORS headers at all. If a genuinely
 * separate front-end has to call the API, list its origins in .env:
 *
 *     CORS_ALLOWED_ORIGINS=https://portal.example.com,https://app.example.com
 *
 * Only an exact match is echoed back. The wildcard is never emitted,
 * and `*` in the allowlist is rejected, because a wildcard combined
 * with credentials is exactly the mistake this file exists to prevent.
 */

require_once __DIR__ . '/env.php';
env_load();

if (!function_exists('cors_apply')) {

    /** @return string[] Configured origins, normalised and validated. */
    function cors_allowed_origins(): array
    {
        $raw = (string) env('CORS_ALLOWED_ORIGINS', '');
        if (trim($raw) === '') {
            return [];
        }

        $origins = [];
        foreach (explode(',', $raw) as $origin) {
            $origin = trim($origin);
            if ($origin === '' || $origin === '*') {
                // A wildcard is deliberately unsupported.
                continue;
            }
            // An Origin header is scheme://host[:port] with no path, so
            // anything else in the config is a mistake.
            $parts = parse_url($origin);
            if (!isset($parts['scheme'], $parts['host'])) {
                continue;
            }
            $normalised = $parts['scheme'] . '://' . $parts['host']
                . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $origins[] = $normalised;
        }

        return array_values(array_unique($origins));
    }

    /**
     * Emit CORS headers when the caller's Origin is allowed, and answer
     * preflight requests.
     *
     * @param string[] $methods Methods this endpoint accepts.
     */
    function cors_apply(array $methods = ['POST']): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Caches must not serve one origin's response to another.
        if (!headers_sent()) {
            header('Vary: Origin');
        }

        if ($origin !== '' && in_array($origin, cors_allowed_origins(), true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: ' . implode(', ', array_merge($methods, ['OPTIONS'])));
            header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-CSRF-Token');
            header('Access-Control-Max-Age: 600');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            // Either the origin was allowed and the headers above apply,
            // or it was not and the browser will block the real request.
            http_response_code(204);
            exit;
        }
    }
}
