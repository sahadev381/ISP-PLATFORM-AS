<?php
/**
 * Test entry point.
 *
 *     php tests/run.php              # run everything
 *     php tests/run.php escaping     # run one file
 *
 * Exits non-zero when anything fails, so CI can call it directly.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Tests may only be run from the command line.\n");
}

// The helpers read and write $_SESSION directly. Under CLI there is no
// session handler, so just provide the array they expect.
$_SESSION = [];
$_POST    = [];
$_GET     = [];

require_once __DIR__ . '/bootstrap.php';

$t = new TestRunner();

$only  = $argv[1] ?? null;
$files = glob(__DIR__ . '/*_test.php');
sort($files);

foreach ($files as $file) {
    if ($only !== null && strpos(basename($file), $only) !== 0) {
        continue;
    }
    require $file;
}

exit($t->report());
