<?php
/**
 * Staging smoke test — load every page once and report what breaks.
 *
 *     php scripts/smoke_test.php --url=https://staging.example.com \
 *                                --user=admin --pass=secret
 *
 *     php scripts/smoke_test.php --url=... --user=... --pass=... --list
 *         show which pages would be visited and which are skipped
 *
 * WHY
 *
 * Every check in this repository so far is static: a parser, a JS
 * syntax check, unit tests over pure helpers, scanners. None of it
 * loads a page. A file can parse perfectly and still fail on the first
 * request because a column was renamed or an include is missing.
 * Phases 6 and 11 each found pages that had been broken for a long
 * time without anyone noticing.
 *
 * This closes that gap the only way it can be closed: by asking the
 * running application.
 *
 * SAFETY
 *
 * This is designed to be safe to point at staging, and it is NOT safe
 * to point at production — see the refusal below.
 *
 * It only ever issues GET requests, never with query parameters, and
 * it skips a denylist of pages that write or destroy data when merely
 * loaded. That denylist is not a nicety: expire.php runs a DELETE at
 * the top of the file with no guard at all, several endpoints act on
 * ?del=, and loading logout.php halfway through a crawl would end the
 * session and make every later page look like a redirect to login.
 *
 * Even so: run it against a database you can throw away.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "The curl extension is required.\n");
    exit(1);
}

/* ------------------------------------------------------------------ */
/* Arguments                                                           */
/* ------------------------------------------------------------------ */

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

$baseUrl  = rtrim((string) ($opts['url'] ?? ''), '/');
$username = (string) ($opts['user'] ?? '');
$password = (string) ($opts['pass'] ?? '');
$listOnly = isset($opts['list']);
$verbose  = isset($opts['verbose']);

if ($baseUrl === '') {
    fwrite(STDERR, "Usage: php scripts/smoke_test.php --url=https://staging.example.com --user=admin --pass=secret\n");
    exit(1);
}

/* Refuse the obvious mistake. This crawler is read-only by design but
   it logs in as an administrator and loads every screen; that is not
   something to do to a live system by accident. */
if (!$listOnly && !isset($opts['i-know-this-is-not-production'])) {
    $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
    $looksLikeStaging = (bool) preg_match('/(staging|localhost|127\.0\.0\.1|\.test$|\.local$|dev\.)/i', $host);
    if (!$looksLikeStaging) {
        fwrite(STDERR, "Refusing to crawl '$host': it does not look like a staging host.\n");
        fwrite(STDERR, "If you are certain, re-run with --i-know-this-is-not-production\n");
        exit(1);
    }
}

/* ------------------------------------------------------------------ */
/* What to visit                                                       */
/* ------------------------------------------------------------------ */

$root = dirname(__DIR__);

/**
 * Pages that must never be requested.
 *
 * Each entry is here for a reason, recorded so nobody removes one
 * without understanding what it does.
 */
$denylist = [
    // Ends the session mid-crawl; every later page would then look
    // like a redirect to the login screen.
    'logout.php',
    'customer/logout.php',
    'hotspot/logout.php',

    // Runs DELETE FROM radreply at the top of the file, on load, with
    // no POST guard and no confirmation.
    'expire.php',

    // Write or delete from a plain GET.
    'monitoring/delete_device.php',
    'branch_delete.php',
    'onu_power_api.php',
    'payment/esewa_verify.php',
    'payment/khalti_verify.php',
    'olt_power_sync.php',

    // Cron and CLI entry points. Loading these over HTTP would run a
    // billing cycle or a backup.
    'cron_block_expired.php',

    // These poll live network hardware - MikroTik over the API port,
    // OLTs over SNMP - and block until the socket times out. Against
    // a lab database with no devices behind it they simply hang, and
    // one 30-second stall per endpoint dominates the run. They need
    // a device to be tested meaningfully, not a crawler.
    'api_status.php',
    'api_network_status.php',
    'api_mikrotik_snmp.php',

    // Not pages.
    'config.php',
    'config.php.example',
    'user-config.php',
];

/** Directories that are never directly requested. */
$skipDirs = ['includes/', 'scripts/', 'tests/', 'vendor/', 'database/', 'config/'];

/** Collect candidate pages from the working tree. */
function discover_pages(string $root, array $denylist, array $skipDirs): array
{
    $pages = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($it as $f) {
        $path = $f->getPathname();
        if (substr($path, -4) !== '.php') {
            continue;
        }

        $rel = ltrim(str_replace($root, '', $path), '/');

        if (strpos($rel, '.git/') === 0) {
            continue;
        }
        foreach ($skipDirs as $dir) {
            if (strpos($rel, $dir) === 0) {
                continue 2;
            }
        }
        if (in_array($rel, $denylist, true)) {
            continue;
        }

        // Partials that only make sense when included by something else.
        $base = basename($rel);
        if (preg_match('/^(header|footer|sidebar|topbar|header_hotspot|user-header)/', $base)) {
            continue;
        }

        $pages[] = $rel;
    }

    sort($pages);
    return $pages;
}

$pages = discover_pages($root, $denylist, $skipDirs);

if ($listOnly) {
    echo "Would visit " . count($pages) . " pages:\n";
    foreach ($pages as $p) {
        echo "  $p\n";
    }
    echo "\nSkipped by denylist (" . count($denylist) . "):\n";
    foreach ($denylist as $p) {
        echo "  $p\n";
    }
    exit(0);
}

if ($username === '' || $password === '') {
    fwrite(STDERR, "--user and --pass are required (the crawl needs an admin session).\n");
    exit(1);
}

/* ------------------------------------------------------------------ */
/* HTTP                                                                */
/* ------------------------------------------------------------------ */

$cookieJar = tempnam(sys_get_temp_dir(), 'smoke');

/**
 * @return array{status:int,body:string,error:string}
 */
function http_get(string $url, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,   // a redirect is information
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'isp-platform-smoke-test',
    ]);
    $body   = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => $body, 'error' => $error];
}

function http_post(string $url, array $fields, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'isp-platform-smoke-test',
    ]);
    $body   = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => $body, 'error' => $error];
}

/* ------------------------------------------------------------------ */
/* Log in                                                              */
/* ------------------------------------------------------------------ */

echo "Logging in at $baseUrl/index.php ... ";

$loginPage = http_get($baseUrl . '/index.php', $cookieJar);
if ($loginPage['error'] !== '') {
    echo "FAILED\n";
    fwrite(STDERR, "Could not reach the site: {$loginPage['error']}\n");
    exit(1);
}

// The login form carries a CSRF token; without it the POST is rejected.
$token = '';
if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginPage['body'], $m)) {
    $token = html_entity_decode($m[1], ENT_QUOTES);
} elseif (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $loginPage['body'], $m)) {
    $token = html_entity_decode($m[1], ENT_QUOTES);
}

$login = http_post($baseUrl . '/index.php', [
    'username' => $username,
    'password' => $password,
    'login'    => '1',
    '_csrf'    => $token,
], $cookieJar);

$loggedIn = ($login['status'] === 302) || stripos($login['body'], 'dashboard') !== false;

if (!$loggedIn || stripos($login['body'], 'Invalid username') !== false) {
    echo "FAILED\n";
    fwrite(STDERR, "Login was rejected. Check --user/--pass, and that the account is not locked out.\n");
    if (getenv('GITHUB_ACTIONS') === 'true') {
        printf("::error::Smoke test could not log in (HTTP %d, csrf token %s)\n",
            $login['status'], $token === '' ? 'MISSING' : 'found');
    }
    if ($token === '') {
        fwrite(STDERR, "No CSRF token was found on the login page, which may itself be the problem.\n");
    }
    @unlink($cookieJar);
    exit(1);
}

echo "ok\n\n";

/* ------------------------------------------------------------------ */
/* Crawl                                                               */
/* ------------------------------------------------------------------ */

/** Signs of a page that rendered but is broken. */
$errorSignatures = [
    'Fatal error'            => 'fatal',
    'Parse error'            => 'fatal',
    'Uncaught'               => 'fatal',
    'Warning:'               => 'warning',
    'Notice:'                => 'warning',
    'Deprecated:'            => 'warning',
    'Undefined variable'     => 'warning',
    'Undefined index'        => 'warning',
    'Undefined array key'    => 'warning',
    'mysqli_sql_exception'   => 'fatal',
    'Call to a member function' => 'fatal',
    'No such file or directory' => 'fatal',
];

$results = ['ok' => [], 'fatal' => [], 'warning' => [], 'http' => [], 'empty' => [], 'redirect' => [], 'rejected' => []];

$total = count($pages);
$i = 0;

foreach ($pages as $page) {
    $i++;
    $url = $baseUrl . '/' . $page;
    $r = http_get($url, $cookieJar);

    printf("\r[%3d/%3d] %-50s", $i, $total, substr($page, 0, 50));

    if ($r['error'] !== '') {
        $results['http'][] = [$page, 0, $r['error']];
        continue;
    }

    if ($r['status'] >= 500) {
        $results['http'][] = [$page, $r['status'], 'server error'];
        continue;
    }

    if ($r['status'] === 302 || $r['status'] === 301) {
        // Being bounced to the login page means the session was lost, or
        // the page's own auth check is wrong.
        $results['redirect'][] = [$page, $r['status'], ''];
        continue;
    }

    /* 405 and 400 from an endpoint that only accepts POST, or that
       requires parameters, is the endpoint working. The crawler only
       issues bare GETs, so refusing one is the correct answer and
       counting it as a failure would train people to ignore this
       report. A 403 is also correct: it means an authorisation check
       fired.

       404 and 5xx are still failures - those mean the page is missing
       or broken. */
    if (in_array($r['status'], [400, 403, 405, 501], true)) {
        $results['rejected'][] = [$page, $r['status'], 'declined a bare GET, as it should'];
        continue;
    }

    if ($r['status'] >= 400) {
        $results['http'][] = [$page, $r['status'], 'client error'];
        continue;
    }

    $worst = null;
    $detail = '';
    foreach ($errorSignatures as $needle => $severity) {
        $pos = stripos($r['body'], $needle);
        if ($pos !== false) {
            if ($severity === 'fatal' || $worst === null) {
                $worst = $severity;
                $detail = trim(substr($r['body'], $pos, 160));
                $detail = preg_replace('/\s+/', ' ', strip_tags($detail)) ?? $detail;
            }
            if ($severity === 'fatal') {
                break;
            }
        }
    }

    if ($worst !== null) {
        $results[$worst][] = [$page, $r['status'], $detail];
        continue;
    }

    if (strlen(trim($r['body'])) === 0) {
        $results['empty'][] = [$page, $r['status'], 'empty response'];
        continue;
    }

    $results['ok'][] = [$page, $r['status'], ''];
}

printf("\r%-70s\r", '');

@unlink($cookieJar);

/* ------------------------------------------------------------------ */
/* Report                                                              */
/* ------------------------------------------------------------------ */

function section(string $title, array $rows, bool $showDetail = true): void
{
    if (!$rows) {
        return;
    }
    echo "\n$title (" . count($rows) . ")\n";
    foreach ($rows as [$page, $status, $detail]) {
        printf("  %-45s %3s  %s\n", $page, $status ?: '-', $showDetail ? $detail : '');
    }
}

/**
 * Emit a GitHub annotation per problem.
 *
 * Not decoration. The job log is only reachable from a machine that
 * can talk to the Actions blob storage; annotations come back through
 * the API, so on a restricted network they are the only way to find
 * out what failed.
 */
function annotate(string $title, array $rows): void
{
    if (getenv('GITHUB_ACTIONS') !== 'true') {
        return;
    }
    foreach ($rows as [$page, $status, $detail]) {
        printf("::error file=%s::%s (HTTP %s) %s\n",
            $page, $title, $status ?: '-', str_replace(["\r", "\n"], ' ', (string) $detail));
    }
}

echo str_repeat('=', 72) . "\n";
echo "Smoke test: $baseUrl\n";
echo str_repeat('=', 72) . "\n";

section('FATAL — the page did not render', $results['fatal']);
section('HTTP errors', $results['http']);
section('Empty responses', $results['empty']);
section('Warnings and notices leaked into the page', $results['warning']);
section('Redirected (session lost, or auth check wrong)', $results['redirect'], false);
section('Declined a bare GET (correct behaviour, listed for review)', $results['rejected']);

annotate('page did not render', $results['fatal']);
annotate('HTTP error', $results['http']);
annotate('empty response', $results['empty']);

printf(
    "\n%d pages: %d ok, %d fatal, %d http errors, %d empty, %d with warnings, %d redirected, %d declined a GET\n",
    $total,
    count($results['ok']),
    count($results['fatal']),
    count($results['http']),
    count($results['empty']),
    count($results['warning']),
    count($results['redirect']),
    count($results['rejected'])
);

if ($verbose && $results['ok']) {
    echo "\nOK:\n";
    foreach ($results['ok'] as [$page]) {
        echo "  $page\n";
    }
}

/* Warnings do not fail the run: display_errors should be off in any
   case, and a notice is not a broken page. Anything that stopped the
   page rendering does. */
$failed = count($results['fatal']) + count($results['http']) + count($results['empty']);

if ($failed > 0) {
    echo "\n$failed page(s) need attention.\n";
    if (getenv('GITHUB_ACTIONS') === 'true') {
        printf("::error::%d of %d pages failed to render\n", $failed, $total);
    }
    exit(1);
}

echo "\nEvery page rendered.\n";
exit(0);
