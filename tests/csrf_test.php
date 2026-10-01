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

$t->group('api_csrf_check() - machine callers are exempt');

require_once __DIR__ . '/../includes/api_auth.php';

$_SERVER['REQUEST_METHOD'] = 'POST';
unset($_POST['_csrf'], $_SERVER['HTTP_X_CSRF_TOKEN']);

// With a valid API key and no token, api_csrf_check() must return
// normally. If it ever starts demanding a token here, every cron job
// that posts to an endpoint dies - and csrf_check() exits, so the
// failure mode is a silent 419 rather than a test failure elsewhere.
$key = str_repeat('a', 32);
$_ENV['API_KEY'] = $key;
putenv('API_KEY=' . $key);
$_SERVER['HTTP_X_API_KEY'] = $key;

$returned = false;
api_csrf_check();
$returned = true;
$t->true('a valid API key skips the token requirement', $returned);

// And the browser case still needs one: csrf_valid() is what
// api_csrf_check() defers to, so assert on that rather than on the
// exiting wrapper.
unset($_SERVER['HTTP_X_API_KEY']);
$_ENV['API_KEY'] = '';
putenv('API_KEY=');
$t->false('a session caller with no token is not valid', csrf_valid());

$_POST['_csrf'] = csrf_token();
$t->true('a session caller with the token is valid', csrf_valid());

unset($_POST['_csrf']);
$_SERVER['REQUEST_METHOD'] = 'GET';
