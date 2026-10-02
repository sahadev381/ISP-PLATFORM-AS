<?php
/**
 * Cross-origin allowlist (includes/cors.php).
 *
 * cors_apply() sends headers and can exit, so it is not callable from
 * a test. cors_allowed_origins() holds the parsing and validation, and
 * that is where a mistake would actually let an origin through.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/cors.php';

/** @var TestRunner $t */

/** Set CORS_ALLOWED_ORIGINS for the next call. */
$conf = static function (string $value): void {
    $_ENV['CORS_ALLOWED_ORIGINS'] = $value;
    putenv('CORS_ALLOWED_ORIGINS=' . $value);
};

$t->group('cors_allowed_origins() - parsing');

$conf('');
$t->is('unset means no origins', cors_allowed_origins(), []);

$conf('   ');
$t->is('whitespace means no origins', cors_allowed_origins(), []);

$conf('https://a.example');
$t->is('a single origin', cors_allowed_origins(), ['https://a.example']);

$conf('https://a.example,https://b.example');
$t->is('several origins', cors_allowed_origins(), ['https://a.example', 'https://b.example']);

$conf(' https://a.example , https://b.example ');
$t->is('surrounding spaces are trimmed', cors_allowed_origins(), ['https://a.example', 'https://b.example']);

$conf('https://a.example:8443');
$t->is('a port is preserved', cors_allowed_origins(), ['https://a.example:8443']);

$conf('https://a.example,https://a.example');
$t->is('duplicates collapse', cors_allowed_origins(), ['https://a.example']);

$t->group('cors_allowed_origins() - rejections');

// The whole point of the file: a wildcard must never be configurable,
// because it is what the payment endpoints used to send.
$conf('*');
$t->is('a bare wildcard is dropped', cors_allowed_origins(), []);

$conf('https://a.example,*');
$t->is('a wildcard among valid origins is dropped', cors_allowed_origins(), ['https://a.example']);

// An Origin header is scheme://host[:port] and nothing else, so these
// could never match a real request and indicate a misconfiguration.
$conf('a.example');
$t->is('a bare host has no scheme and is dropped', cors_allowed_origins(), []);

$conf('https://a.example/some/path');
$t->is('a path is stripped back to the origin', cors_allowed_origins(), ['https://a.example']);

$conf('not a url,https://ok.example');
$t->is('junk is dropped but valid entries survive', cors_allowed_origins(), ['https://ok.example']);

$t->group('matching is exact');

$conf('https://a.example');
$allowed = cors_allowed_origins();

$t->true('the configured origin matches', in_array('https://a.example', $allowed, true));
// Substring or suffix matching is the classic CORS bypass.
$t->false('a different scheme does not match', in_array('http://a.example', $allowed, true));
$t->false('a subdomain does not match', in_array('https://evil.a.example', $allowed, true));
$t->false('a suffix does not match', in_array('https://a.example.evil.com', $allowed, true));
$t->false('a port variant does not match', in_array('https://a.example:8443', $allowed, true));

$conf('');

$t->group('api_auth - payment guard building blocks');

require_once __DIR__ . '/../includes/api_auth.php';

// An unset or short API_KEY must never authenticate anybody: that would
// turn "no key configured" into "everyone is authorised".
$_ENV['API_KEY'] = '';
putenv('API_KEY=');
$_SERVER['HTTP_X_API_KEY'] = '';
$t->false('an empty API_KEY authenticates nobody', api_has_valid_key());

$_ENV['API_KEY'] = 'short';
putenv('API_KEY=short');
$_SERVER['HTTP_X_API_KEY'] = 'short';
$t->false('a too-short API_KEY is rejected even when it matches', api_has_valid_key());

$key = str_repeat('k', 32);
$_ENV['API_KEY'] = $key;
putenv('API_KEY=' . $key);
$_SERVER['HTTP_X_API_KEY'] = $key;
$t->true('a long matching key is accepted', api_has_valid_key());

$_SERVER['HTTP_X_API_KEY'] = str_repeat('k', 31) . 'x';
$t->false('a near-miss key is rejected', api_has_valid_key());

unset($_SERVER['HTTP_X_API_KEY'], $_GET['api_key'], $_POST['api_key']);
$_ENV['API_KEY'] = '';
putenv('API_KEY=');

$t->group('api_customer_id()');

$_SESSION = [];
$t->is('no customer session yields null', api_customer_id(), null);

$_SESSION['customer_id'] = 0;
$t->is('customer_id 0 is not a customer', api_customer_id(), null);

$_SESSION['customer_id'] = '7';
$t->is('a customer id comes back as an int', api_customer_id(), 7);

// An admin session must not be mistaken for a customer, or the
// ownership check in the payment endpoints would scope to the wrong id.
$_SESSION = ['user_id' => 3];
$t->is('an admin session is not a customer', api_customer_id(), null);

$_SESSION = [];
