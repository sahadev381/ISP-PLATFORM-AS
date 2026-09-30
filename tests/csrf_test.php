<?php
/**
 * CSRF tokens (includes/csrf.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/csrf.php';

/** @var TestRunner $t */

$t->group('token issuing');

$_SESSION = [];
$token = csrf_token();
$t->true('token is a non-empty string', is_string($token) && $token !== '');
$t->true('token is long enough to resist guessing', strlen($token) >= 32);
$t->is('token is stable within a session', csrf_token(), $token);
$t->true('token is stored in the session', isset($_SESSION['_csrf_token']));

$_SESSION = [];
$t->true('a new session gets a new token', csrf_token() !== $token);

$t->group('csrf_field()');

$_SESSION = [];
$field = csrf_field();
$t->true('renders a hidden input', strpos($field, 'type="hidden"') !== false);
$t->true('uses the _csrf name', strpos($field, 'name="_csrf"') !== false);
$t->true('carries the current token', strpos($field, csrf_token()) !== false);
// The token is hex, but the value still has to be attribute-escaped so
// the markup cannot be broken by a future token format.
$t->lacks('value cannot break the attribute', $field, 'value=""');

$t->group('csrf_valid() - POST only');

$_SESSION = [];
$good = csrf_token();

$_POST = ['_csrf' => $good];
$t->true('accepts the right token', csrf_valid());

$_POST = ['_csrf' => 'wrong'];
$t->false('rejects a wrong token', csrf_valid());

$_POST = ['_csrf' => ''];
$t->false('rejects an empty token', csrf_valid());

$_POST = [];
$t->false('rejects a missing token', csrf_valid());

// The header form is what the fetch()-based pages use.
$_POST = [];
$_SERVER['HTTP_X_CSRF_TOKEN'] = $good;
$t->true('accepts the token from the header', csrf_valid());
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

// A token belonging to a different session must not work.
$_POST = ['_csrf' => $good];
$_SESSION = [];
csrf_token();
$t->false('rejects a token from another session', csrf_valid());

$t->group('csrf_valid_request() - also accepts GET');

$_SESSION = [];
$good = csrf_token();

// csrf_check() returns early on GET, which is why the ?del= links were
// unprotected. csrf_valid_request() is the variant that reads $_GET.
$_POST = [];
$_GET = ['_csrf' => $good];
$t->true('accepts the token from the query string', csrf_valid_request());
$t->false('plain csrf_valid() still ignores the query string', csrf_valid());

$_GET = ['_csrf' => 'wrong'];
$t->false('rejects a wrong query token', csrf_valid_request());

$_GET = [];
$t->false('rejects a missing query token', csrf_valid_request());

$_POST = ['_csrf' => $good];
$_GET = [];
$t->true('still accepts a POST token', csrf_valid_request());

$_POST = [];
$_GET = [];
$_SESSION = [];
