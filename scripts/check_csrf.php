<?php
/**
 * Fail the build when a page accepts a POST without checking the CSRF
 * token, or renders a POST form without emitting one.
 *
 * Run from CI: php scripts/check_csrf.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);

// Paths that are allowed to be missing one half, with the reason.
$exempt = [
    // Posts to eSewa's own URL; handing our token to a third party
    // would be worse than not having one. Its inbound POST is checked.
    'payment/esewa_pay.php' => 'field',
];

$skipPrefixes = ['tests/', 'vendor/', 'includes/csrf.php', 'includes/api_auth.php', 'scripts/'];

$problems = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $file) {
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }
    $rel = ltrim(str_replace($root, '', $path), '/');
    if (strpos($rel, '.git/') === 0 || strpos($rel, 'vendor/') === 0) {
        continue;
    }
    foreach ($skipPrefixes as $p) {
        if (strpos($rel, $p) === 0) {
            continue 2;
        }
    }

    $src = file_get_contents($path);
    $hasForm  = (bool) preg_match('/<form\b[^>]*method\s*=\s*[\'"]?post/i', $src);
    $hasPost  = strpos($src, '$_POST') !== false;
    $hasField = strpos($src, 'csrf_field') !== false;
    $hasCheck = (bool) preg_match('/csrf_(check|valid)/', $src);

    $allow = $exempt[$rel] ?? '';

    if ($hasForm && !$hasField && $allow !== 'field') {
        $problems[] = "$rel: POST form with no csrf_field()";
    }
    if ($hasPost && !$hasCheck && $allow !== 'check') {
        $problems[] = "$rel: reads \$_POST with no csrf_check()";
    }

    // "The token appears somewhere in the file" is not the same as
    // "the token is checked before anything is written". admin.php and
    // nas.php both passed the check above while their POST branch wrote
    // to the database unprotected - the csrf_check_request() that
    // satisfied the grep was further down, on a GET branch.
    if ($hasCheck && $allow !== 'order') {
        $checkAt = null;
        if (preg_match('/\bcsrf_check(_request)?\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
            $checkAt = $m[0][1];
        }
        $writeAt = null;
        if (preg_match('/\b(db_exec|->prepare|->query)\s*\(\s*["\'][^"\']*\b(INSERT|UPDATE|DELETE)\b/i',
                       $src, $m, PREG_OFFSET_CAPTURE)) {
            $writeAt = $m[0][1];
        }
        if ($checkAt !== null && $writeAt !== null && $writeAt < $checkAt) {
            $line = substr_count(substr($src, 0, $writeAt), "\n") + 1;
            $problems[] = "$rel: writes to the database on line $line, before the first csrf_check()";
        }
    }
}

sort($problems);
foreach ($problems as $p) {
    echo "::error::$p\n";
}

if ($problems) {
    echo count($problems) . " CSRF problem(s) found.\n";
    exit(1);
}

echo "CSRF: every POST form carries a token and every POST handler checks one.\n";
exit(0);
