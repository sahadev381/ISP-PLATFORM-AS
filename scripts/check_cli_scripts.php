<?php
/**
 * Guard: a #! line and declare(strict_types=1) cannot coexist.
 *
 * PHP requires declare(strict_types=1) to be the very first statement
 * in a file. A shebang line is output that precedes it, so the file
 * dies with a fatal error the moment it runs - but token_get_all(),
 * which is what the lint step uses, parses it happily.
 *
 * That is exactly how four working-looking backup scripts shipped in
 * the previous phase: lint passed, the test suite passed, and every
 * one of them would have fatally errored on first execution. They are
 * exec()-based, so the PHP-WASM harness could not run them either.
 *
 * The scripts are all invoked as `php scripts/x.php`, so the shebang
 * bought nothing and the declare is worth keeping.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

$root = dirname(__DIR__);
$bad = [];

foreach (glob($root . '/scripts/*.php') ?: [] as $path) {
    $src = (string) file_get_contents($path);
    $first = strtok($src, "\n");

    if ($first !== false && substr($first, 0, 2) === '#!' && strpos($src, 'declare(strict_types') !== false) {
        $bad[] = basename($path);
    }
}

if ($bad) {
    fwrite(STDERR, "CLI SCRIPTS: a shebang before declare(strict_types=1) is a fatal error.\n\n");
    foreach ($bad as $name) {
        fwrite(STDERR, "  scripts/$name\n");
    }
    fwrite(STDERR, "\nRemove the shebang. These are run as `php scripts/<name>.php`.\n");
    exit(1);
}

echo 'CLI SCRIPTS: ' . count(glob($root . '/scripts/*.php') ?: []) . " checked, no shebang/declare conflicts.\n";
exit(0);
