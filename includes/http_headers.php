<?php
/**
 * Security response headers, including Content-Security-Policy.
 *
 * WHAT THIS CAN AND CANNOT DO
 *
 * Be clear about this, because a CSP header creates a comfortable
 * feeling that is often not earned. This codebase currently contains:
 *
 *     186   on* attribute handlers  (onclick="...")
 *     1357  style="..." attributes
 *     57    inline <style> blocks
 *     44    inline <script> blocks
 *
 * A policy that blocks inline script - the kind that actually stops XSS -
 * would break every one of those. And 'unsafe-inline' cannot be combined
 * with a nonce: the moment a nonce is present, browsers ignore
 * 'unsafe-inline' entirely.
 *
 * So the enforced policy below keeps 'unsafe-inline' and is NOT an XSS
 * defence. The XSS defence in this project is the output escaping in
 * includes/html.php. What the enforced policy does buy, today, for free:
 *
 *   object-src 'none'      no Flash/plugin embedding
 *   base-uri 'self'        an injected <base href> cannot re-point every
 *                          relative URL on the page at an attacker
 *   form-action            an injected form cannot post the admin's input
 *                          to somewhere else
 *   frame-ancestors        clickjacking
 *   script-src allowlist   an injected <script src=evil.com> is blocked
 *                          even though inline script is not
 *   no 'unsafe-eval'       eval() and new Function() are refused
 *                          (verified: the codebase uses neither)
 *
 * The strict policy we would like to reach is sent at the same time as
 * Content-Security-Policy-Report-Only. It changes nothing for users; it
 * makes the browser report what would break. That report is the work
 * list for removing the inline handlers.
 */

require_once __DIR__ . '/env.php';

if (!function_exists('csp_sources')) {

    /**
     * Third-party origins this application genuinely loads from.
     * Derived from the actual <script src> and <link href> in the repo.
     */
    function csp_sources(): array
    {
        return [
            'script' => [
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://code.jquery.com',
                'https://cdn.datatables.net',
                'https://unpkg.com',
                'https://khalti.com',
            ],
            'style' => [
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://cdn.datatables.net',
                'https://unpkg.com',
                'https://fonts.googleapis.com',
            ],
            'font' => [
                'https://fonts.gstatic.com',
                'https://cdnjs.cloudflare.com',
                'https://cdn.jsdelivr.net',
            ],
            // payment/esewa_pay.php submits a real <form> to eSewa, so
            // form-action 'self' alone would break checkout.
            'form' => [
                'https://esewa.com.np',
                'https://uat.esewa.com.np',
                'https://khalti.com',
            ],
            'connect' => [
                'https://khalti.com',
                'https://a.khalti.com',
            ],
        ];
    }

    /**
     * The enforced policy: everything we can turn on without breaking
     * the application as it is written today.
     */
    function csp_enforced_policy(): string
    {
        $s = csp_sources();
        $self = "'self'";

        $directives = [
            "default-src $self",
            "script-src $self 'unsafe-inline' " . implode(' ', $s['script']),
            "style-src $self 'unsafe-inline' " . implode(' ', $s['style']),
            "font-src $self data: " . implode(' ', $s['font']),
            // Report logos, QR codes and uploaded avatars come from
            // several places; images are a low-risk sink.
            "img-src $self data: blob: https:",
            "connect-src $self " . implode(' ', $s['connect']),
            "form-action $self " . implode(' ', $s['form']),
            "frame-src $self https://khalti.com",
            "base-uri $self",
            "object-src 'none'",
            "frame-ancestors $self",
        ];

        if (csp_is_https()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /**
     * The policy we are aiming for, sent report-only. The difference from
     * the enforced one is the absence of 'unsafe-inline' - which is the
     * whole point.
     */
    function csp_report_only_policy(string $nonce): string
    {
        $s = csp_sources();
        $self = "'self'";

        return implode('; ', [
            "default-src $self",
            "script-src $self 'nonce-$nonce' " . implode(' ', $s['script']),
            "style-src $self 'nonce-$nonce' " . implode(' ', $s['style']),
            "font-src $self data: " . implode(' ', $s['font']),
            "img-src $self data: blob: https:",
            "connect-src $self " . implode(' ', $s['connect']),
            "form-action $self " . implode(' ', $s['form']),
            "base-uri $self",
            "object-src 'none'",
            "frame-ancestors $self",
        ]);
    }

    function csp_is_https(): bool
    {
        env_load();
        $configured = env('APP_HTTPS', null);
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * A per-response nonce. Exposed so that, as inline blocks get a
     * nonce attribute added, they start satisfying the strict policy.
     */
    function csp_nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }
        return $nonce;
    }

    /**
     * Send every security header. Safe to call more than once and safe
     * to call after output has started (it does nothing).
     */
    function send_security_headers(): void
    {
        static $sent = false;
        if ($sent || PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        $sent = true;

        header('Content-Security-Policy: ' . csp_enforced_policy());
        header('Content-Security-Policy-Report-Only: ' . csp_report_only_policy(csp_nonce()));

        // Stop the browser guessing that a .txt upload is really HTML.
        header('X-Content-Type-Options: nosniff');

        // frame-ancestors covers this for modern browsers; keep the
        // legacy header for the old ones.
        header('X-Frame-Options: SAMEORIGIN');

        // Do not leak customer ids and usernames in the Referer when an
        // admin clicks an external link.
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // This panel needs none of these.
        header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=(), usb=()');

        if (csp_is_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
