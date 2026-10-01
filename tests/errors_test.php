<?php
/**
 * Error capture (includes/errors.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/errors.php';

/** @var TestRunner $t */

$t->group('keeping secrets out of the log');

/* Error messages quote the code that failed, and the code that fails
   is often the code handling a password. The log is frequently more
   readable than the database it protects. */
$t->is('a password assignment is redacted',
    error_redact("Failed: password = hunter2"),
    'Failed: password = [redacted]');

$t->lacks('an array key holding a secret is redacted',
    error_redact("['DB_PASS' => 'fixture-pw-not-real']"), 'fixture-pw-not-real');

$t->lacks('an api key is redacted',
    error_redact('api_key: live_sk_9f8a7b6c5d'), 'live_sk_9f8a7b6c5d');

$t->lacks('a bearer token is redacted',
    error_redact('Authorization: Bearer eyJhbGciOiJIUzI1NiJ9abcdef'), 'eyJhbGciOiJIUzI1NiJ9abcdef');

$t->lacks('credentials inside a URL are redacted',
    error_redact('mysql://radius:fixture-pw-not-real@db.internal/radius'), 'fixture-pw-not-real');

$t->true('the surrounding text survives',
    strpos(error_redact('mysql://radius:fixture-pw-not-real@db.internal/radius'), 'db.internal') !== false);

$t->is('ordinary text is left alone',
    error_redact('Undefined array key "customer_id" in billing.php'),
    'Undefined array key "customer_id" in billing.php');

/* "password" appearing as a word, with no value attached, is not a
   leak and should not be mangled - it is often the useful part of the
   message. */
$t->is('the bare word is not touched',
    error_redact('The password field is required'),
    'The password field is required');

$t->group('which errors end the request');

$t->true('a fatal is fatal', error_is_fatal(E_ERROR));
$t->true('a parse error is fatal', error_is_fatal(E_PARSE));
$t->true('a compile error is fatal', error_is_fatal(E_COMPILE_ERROR));
$t->false('a warning is not', error_is_fatal(E_WARNING));
$t->false('a notice is not', error_is_fatal(E_NOTICE));
$t->false('a deprecation is not', error_is_fatal(E_DEPRECATED));

$t->is('warnings are labelled', error_severity_label(E_WARNING), 'warning');
$t->is('notices are labelled', error_severity_label(E_NOTICE), 'notice');
$t->is('deprecations are labelled', error_severity_label(E_USER_DEPRECATED), 'deprecated');

$t->group('never showing a stack trace to a visitor');

/* display_errors on in production is how database credentials end up
   in a screenshot attached to a support ticket. */
$t->false('production shows nothing', error_should_display(false));
$t->true('debug shows detail', error_should_display(true));

$t->group('answering AJAX callers in their own format');

/* An HTML error page returned to fetch() surfaces as a JSON parse
   error in the console and hides the actual failure. */
$t->true('an XMLHttpRequest gets JSON',
    error_wants_json('', 'XMLHttpRequest', '/dashboard.php'));
$t->true('an Accept header asking for JSON gets JSON',
    error_wants_json('application/json, text/plain, */*', '', '/dashboard.php'));
$t->true('the project api naming convention is honoured',
    error_wants_json('', '', '/mobile_tech_api.php?action=list'));
$t->true('so is an api/ path',
    error_wants_json('', '', '/api/customers'));
$t->false('an ordinary page load gets HTML',
    error_wants_json('text/html,application/xhtml+xml', '', '/dashboard.php'));

$t->group('the reference shown to the user');

$ref = error_reference_id();
$t->is('is short enough to read aloud', strlen($ref), 8);
$t->true('is hex', ctype_xdigit($ref));
$t->false('is not predictable', error_reference_id() === error_reference_id());

$t->group('the shape of a log record');

$line = error_format_record([
    'ts' => '2026-10-01T12:00:00+05:45',
    'ref' => 'a4f91c2e',
    'level' => 'exception',
    'message' => "mysqli_sql_exception: Access denied\nfor user",
    'admin' => 'sahadev',
    'empty' => '',
    'nothing' => null,
]);

/* One line per record, so grep works and concurrent requests cannot
   interleave into each other's records. */
$t->false('contains no newline', strpos($line, "\n") !== false);

$decoded = json_decode($line, true);
$t->true('is valid JSON', is_array($decoded));
$t->is('keeps the reference', $decoded['ref'], 'a4f91c2e');
$t->is('keeps the admin', $decoded['admin'], 'sahadev');
$t->false('drops empty fields', array_key_exists('empty', $decoded));
$t->false('drops null fields', array_key_exists('nothing', $decoded));

$secretLine = error_format_record(['message' => "connect failed, password = hunter2"]);
$t->lacks('redacts on the way into the log', $secretLine, 'hunter2');

$t->group('trimming stack traces');

$deep = implode("\n", array_map(fn($i) => "#$i /repo/includes/db.php($i): db_exec()", range(0, 40)));
$trimmed = error_trim_trace($deep);

$t->true('keeps the top frames, where the bug is',
    strpos($trimmed, '#0 ') !== false);
$t->true('says how much was dropped',
    strpos($trimmed, 'more frames') !== false);
$t->true('is much shorter than the original',
    strlen($trimmed) < strlen($deep) / 2);

$short = "#0 /repo/a.php(1): f()\n#1 {main}";
$t->is('a short trace is left intact', error_trim_trace($short), $short);

$t->group('choosing where to write');

$t->is('an unwritable directory falls back to the PHP error log',
    error_log_path('/nonexistent-dir-xyz/errors.log'), '');
$t->is('blank means the PHP error log', error_log_path(''), '');
$t->is('a writable directory is used',
    error_log_path(sys_get_temp_dir() . '/isp_errors.log'),
    sys_get_temp_dir() . '/isp_errors.log');
