<?php
/**
 * CSP violation collection (includes/csp_report.php) and the
 * report-uri wiring in includes/http_headers.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/csp_report.php';
require_once __DIR__ . '/../includes/http_headers.php';

/** @var TestRunner $t */

$t->group('the policy actually asks for reports');

/* This header has been sent for several phases with no report-uri,
   so every violation went to the console of whoever had devtools
   open and nowhere else. */
$policy = csp_report_only_policy('abc123');

$t->true('report-only names an endpoint', strpos($policy, 'report-uri ') !== false);
$t->true('and declares a Reporting API group', strpos($policy, 'report-to csp-endpoint') !== false);
$t->true('the endpoint is same-origin', strpos($policy, 'report-uri /csp_report.php') !== false);

/* A nonce and 'unsafe-inline' cancel each other out: a browser that
   sees a nonce ignores 'unsafe-inline' completely. The report-only
   policy is the strict one, so it must never contain it. */
$t->lacks('the strict policy has no unsafe-inline', $policy, "'unsafe-inline'");
$t->true('it uses the nonce instead', strpos($policy, "'nonce-abc123'") !== false);

$enforced = csp_enforced_policy();
$t->lacks('and the enforced policy carries no nonce', $enforced, 'nonce-');
$t->true('because it still needs unsafe-inline', strpos($enforced, "'unsafe-inline'") !== false);
$t->lacks('neither policy allows eval', $enforced . $policy, "'unsafe-eval'");

$t->group('reading a report, whichever format the browser sent');

/* The original spec's format, which Firefox and Safari still use. */
$legacy = csp_report_extract('{"csp-report":{"document-uri":"https://isp.example/admin.php",
    "violated-directive":"script-src","effective-directive":"script-src",
    "blocked-uri":"inline","source-file":"https://isp.example/admin.php",
    "line-number":412,"script-sample":"toggleAddAdmin()"}}');

$t->true('the legacy format parses', is_array($legacy));
$t->is('directive', $legacy['directive'], 'script-src');
$t->is('page', $legacy['document'], 'https://isp.example/admin.php');
$t->is('line', $legacy['line'], '412');
$t->is('the sample names the handler to remove', $legacy['sample'], 'toggleAddAdmin()');

/* The Reporting API format Chrome sends: an array, camelCase keys. */
$modern = csp_report_extract('[{"type":"csp-violation","age":0,"url":"https://isp.example/map.php",
    "body":{"documentURL":"https://isp.example/map.php","effectiveDirective":"script-src-attr",
    "blockedURL":"inline","lineNumber":88,"sample":"setMode(\'add_node\', this)"}}]');

$t->true('the Reporting API format parses', is_array($modern));
$t->is('camelCase directive is read', $modern['directive'], 'script-src-attr');
$t->is('camelCase line is read', $modern['line'], '88');

$t->is('something that is not a report is rejected',
    csp_report_extract('{"hello":"world"}'), null);
$t->is('invalid JSON is rejected', csp_report_extract('not json at all'), null);
$t->is('an empty body is rejected', csp_report_extract(''), null);

$t->group('not drowning in browser extensions');

/* In a real deployment most reports are password managers and ad
   blockers rewriting the page. They are not bugs in this
   application and they bury the ones that are. */
$t->true('a Chrome extension is noise',
    csp_report_is_noise(['blocked' => 'chrome-extension://abcdefg/inject.js']));
$t->true('a Firefox extension is noise',
    csp_report_is_noise(['source' => 'moz-extension://1234/content.js']));
$t->true('a Safari extension is noise',
    csp_report_is_noise(['blocked' => 'safari-web-extension://xyz/x.js']));
$t->true('about:blank is noise', csp_report_is_noise(['blocked' => 'about']));

$t->false('a real inline-script violation is kept',
    csp_report_is_noise([
        'blocked' => 'inline',
        'source' => 'https://isp.example/map.php',
        'document' => 'https://isp.example/map.php',
    ]));

$t->group('collapsing the same violation');

/* One broken page reported by a thousand visitors must become one
   record, not a thousand. An unauthenticated endpoint that writes a
   line per request is a way to fill the disk. */
$a = ['directive' => 'script-src', 'blocked' => 'inline',
      'document' => 'https://isp.example/map.php?id=1', 'source' => 'map.php', 'line' => '88'];
$b = ['directive' => 'script-src', 'blocked' => 'inline',
      'document' => 'https://isp.example/map.php?id=9999', 'source' => 'map.php', 'line' => '88'];

$t->is('the query string does not create a new violation',
    csp_report_signature($a), csp_report_signature($b));

$c = array_merge($a, ['line' => '140']);
$t->false('a different line is a different violation',
    csp_report_signature($a) === csp_report_signature($c));

$d = array_merge($a, ['directive' => 'style-src']);
$t->false('a different directive is a different violation',
    csp_report_signature($a) === csp_report_signature($d));

$t->is('signatures are short enough to index', strlen(csp_report_signature($a)), 16);

$t->group('deduplication on disk');

$dir = sys_get_temp_dir() . '/isp_csp_test_' . bin2hex(random_bytes(4));
$sig = csp_report_signature($a);

$t->false('the first sighting is new', csp_report_seen($sig, $dir));
$t->true('the second is a duplicate', csp_report_seen($sig, $dir));
$t->false('a different signature is still new',
    csp_report_seen(csp_report_signature($c), $dir));

/* If the marker directory cannot be created we must record rather
   than silently drop every report. */
$t->false('an unusable directory fails open, not closed',
    csp_report_seen($sig, '/proc/nonexistent/csp'));

array_map('unlink', glob($dir . '/*') ?: []);
@rmdir($dir);

$t->group('limits');

$t->true('the body cap is small', csp_report_max_bytes() <= 65536);
$t->true('but large enough for a real report', csp_report_max_bytes() >= 4096);

$t->group('the log line');

$summary = csp_report_summary($legacy);
$t->true('names the directive', strpos($summary, 'script-src') !== false);
$t->true('names the page', strpos($summary, 'admin.php') !== false);
$t->true('gives the line', strpos($summary, '412') !== false);
