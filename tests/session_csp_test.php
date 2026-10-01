<?php
/**
 * Session bootstrap and security-header tests.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/http_headers.php';
require_once __DIR__ . '/../includes/session.php';

/** @var TestRunner $t */

/* ------------------------------------------------------------------ */
$t->group('session cookie attributes');

$p = session_cookie_params();
$t->true('the cookie is HttpOnly, so injected script cannot read it', $p['httponly']);
$t->is('SameSite=Lax keeps the cookie off cross-site POSTs', $p['samesite'], 'Lax');
$t->is('scoped to the whole application', $p['path'], '/');
$t->is('the cookie dies with the browser', $p['lifetime'], 0);

/* Secure must follow the deployment, not a guess: forcing it on a plain
   HTTP install would make login impossible. */
$_SERVER['HTTPS'] = 'on';
$t->true('HTTPS=on is detected', session_https());
$_SERVER['HTTPS'] = 'off';
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
$t->false('HTTPS=off is not treated as secure', session_https());
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$t->true('a TLS-terminating proxy is detected via X-Forwarded-Proto', session_https());
unset($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTPS']);

/* ------------------------------------------------------------------ */
$t->group('no entry point starts a session by hand any more');

/* This is the regression that mattered: 25 files called session_start()
   on line 2, before config.php could set the cookie flags, so the
   hardening never applied. */
$offenders = [];
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) {
    $path = $f->getPathname();
    if (substr($path, -4) !== '.php') { continue; }
    if (strpos($path, '/vendor/') !== false || strpos($path, '/.git/') !== false) { continue; }
    if (substr($path, -strlen('includes/session.php')) === 'includes/session.php') { continue; }

    $src = file_get_contents($path);
    // strip comments so a commented-out call does not count
    $code = '';
    foreach (token_get_all($src) as $tok) {
        $code .= is_array($tok)
            ? (in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $tok[1])
            : $tok;
    }
    if (preg_match('/\bsession_start\s*\(/', $code)) {
        $offenders[] = str_replace($root . '/', '', $path);
    }
}
$t->is('every entry point goes through session_boot()', $offenders, []);

/* ------------------------------------------------------------------ */
$t->group('content security policy');

$enforced = csp_enforced_policy();

$t->true('object-src none - no plugin embedding', strpos($enforced, "object-src 'none'") !== false);
$t->true('base-uri self - an injected <base> cannot repoint relative URLs', strpos($enforced, "base-uri 'self'") !== false);
$t->true('frame-ancestors self - clickjacking', strpos($enforced, "frame-ancestors 'self'") !== false);
$t->true('form-action is constrained', strpos($enforced, "form-action 'self'") !== false);
$t->lacks('eval() stays blocked - the codebase uses neither eval nor new Function', $enforced, "'unsafe-eval'");

/* esewa_pay.php submits a real form to eSewa; a bare form-action 'self'
   would break checkout, which is how CSP usually gets switched off. */
$t->true('the eSewa checkout form is still allowed to submit', strpos($enforced, 'https://esewa.com.np') !== false);

/* Everything the pages actually load must be in the policy, or the
   panel breaks the moment the header goes on. */
foreach (['https://cdn.jsdelivr.net', 'https://code.jquery.com',
          'https://cdnjs.cloudflare.com', 'https://cdn.datatables.net',
          'https://unpkg.com'] as $host) {
    $t->true("script host allowed: $host", strpos($enforced, $host) !== false);
}
$t->true('Google Fonts stylesheet allowed', strpos($enforced, 'https://fonts.googleapis.com') !== false);
$t->true('Google Fonts files allowed', strpos($enforced, 'https://fonts.gstatic.com') !== false);

/* The report-only policy is the one worth reaching: it is the enforced
   policy minus 'unsafe-inline'. If it ever gains unsafe-inline it has
   stopped being a goal. */
$nonce = csp_nonce();
$report = csp_report_only_policy($nonce);
$t->lacks('the report-only policy has no unsafe-inline - that is the point of it', $report, "'unsafe-inline'");
$t->true('the report-only policy carries a nonce', strpos($report, "'nonce-$nonce'") !== false);
$t->is('the nonce is stable within one response', csp_nonce(), $nonce);

/* ------------------------------------------------------------------ */
$t->group('the CSP admits what it cannot do');

/* Honesty check: as long as inline handlers exist, the enforced policy
   must keep unsafe-inline. If someone removes unsafe-inline without
   removing the handlers, the panel breaks; if the handlers are gone,
   this test should be updated deliberately. */
$inline = 0;
foreach ($it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $f) {
    $path = $f->getPathname();
    if (substr($path, -4) !== '.php') { continue; }
    if (strpos($path, '/vendor/') !== false || strpos($path, '/.git/') !== false) { continue; }
    $inline += preg_match_all('/\son[a-z]+\s*=\s*["\']/i', file_get_contents($path));
}
$t->true("inline on* handlers still exist ($inline found)", $inline > 0);
$t->true('so the enforced policy still needs unsafe-inline, and is not an XSS defence', strpos($enforced, "'unsafe-inline'") !== false);

/* ------------------------------------------------------------------ */
$t->group('client IP cannot be forged in the audit log');

/* getClientIP() used to prefer X-Forwarded-For unconditionally, so every
   address in login_attempts and activity_log was attacker-controlled.
   Security's constructor needs a database, so exercise the logic through
   a subclass that skips it. */
require_once __DIR__ . '/../includes/security.php';

$probe = new class extends Security {
    public function __construct() {}   // no DB
};

$_SERVER['REMOTE_ADDR']          = '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
$_SERVER['HTTP_CLIENT_IP']       = '5.6.7.8';

putenv('TRUSTED_PROXIES=');
$_ENV['TRUSTED_PROXIES'] = '';
$t->is('a forged X-Forwarded-For is ignored when no proxy is configured',
    $probe->getClientIP(), '203.0.113.9');

$_ENV['TRUSTED_PROXIES'] = '198.51.100.7';
putenv('TRUSTED_PROXIES=198.51.100.7');
$t->is('a header from an untrusted source is still ignored',
    $probe->getClientIP(), '203.0.113.9');

$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
$t->is('the header is believed only when the request came from the proxy',
    $probe->getClientIP(), '1.2.3.4');

$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip, 9.9.9.9';
$t->is('garbage entries are skipped', $probe->getClientIP(), '9.9.9.9');

unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);
putenv('TRUSTED_PROXIES=');
$_ENV['TRUSTED_PROXIES'] = '';

$t->group('the session cookie options setcookie() will accept');

/* session_set_cookie_params() and setcookie() take almost the same
   array and disagree about one key. Passing the session version to
   setcookie() raises ValueError: option "lifetime" is invalid - which
   is what session_repair_cookie() did, on the login path, from the
   moment the session hardening was written. Nothing caught it until
   a browser-less CI job actually posted the login form. */
$opts = session_setcookie_options();

$t->false('there is no lifetime key', array_key_exists('lifetime', $opts));
$t->true('there is an expires key', array_key_exists('expires', $opts));

/* A session cookie is expires=0. time()+0 would be a cookie that
   expired the instant it was set - an immediate logout. */
$t->is('a zero lifetime stays zero, not time()', $opts['expires'], 0);

$t->is('the path survives', $opts['path'], '/');
$t->true('httponly survives', $opts['httponly']);
$t->is('samesite survives', $opts['samesite'], 'Lax');
$t->true('secure is a boolean', is_bool($opts['secure']));

/* Every key must be one setcookie() recognises, or it throws. */
$allowed = ['expires', 'path', 'domain', 'secure', 'httponly', 'samesite'];
foreach (array_keys($opts) as $key) {
    $t->true("setcookie() accepts the '$key' option", in_array($key, $allowed, true));
}

/* The session_* spelling must stay as it is: session_set_cookie_params()
   rejects 'expires' just as firmly as setcookie() rejects 'lifetime'. */
$sessionParams = session_cookie_params();
$t->true('the session version still uses lifetime',
    array_key_exists('lifetime', $sessionParams));
$t->false('and not expires', array_key_exists('expires', $sessionParams));
