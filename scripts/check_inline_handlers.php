<?php
/**
 * A ratchet on inline event handlers.
 *
 *     php scripts/check_inline_handlers.php            enforce the budget
 *     php scripts/check_inline_handlers.php --report   show the work list
 *     php scripts/check_inline_handlers.php --update   lower the budget
 *
 * Every onclick="..." in the codebase is a reason script-src must keep
 * 'unsafe-inline', and 'unsafe-inline' is the reason the CSP is not an
 * XSS defence. Removing them is a long job.
 *
 * Long jobs lose to new code unless something stops the number going
 * up, so this fails CI if the count exceeds the recorded budget. The
 * budget can only be lowered. It is not a style rule; it is the thing
 * that makes the cleanup finishable.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}


/* STDERR is not defined under every SAPI that can run this. */
function warn(string $msg): void
{
    $h = defined('STDERR') ? STDERR : fopen('php://stderr', 'w');
    fwrite($h, $msg);
}

$root = dirname(__DIR__);
$budgetFile = $root . '/.inline-handler-budget';

/* $argv is not populated under every CLI-ish SAPI; $_SERVER always is. */
$opts = array_slice((array) ($_SERVER['argv'] ?? []), 1);
$report = in_array('--report', $opts, true);
$update = in_array('--update', $opts, true);

/* The attributes that execute script. Not an exhaustive list of DOM
   events - just the ones this codebase actually uses, plus the common
   ones so a newly introduced onmouseover is caught too. */
$events = 'click|change|submit|input|keyup|keydown|keypress|load|error|focus|blur|'
        . 'mouseover|mouseout|mouseenter|mouseleave|mousedown|mouseup|dblclick|'
        . 'contextmenu|drop|dragover|paste|cut|copy|scroll|wheel|toggle|reset|select';

$found = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = $file->getPathname();
    $rel = ltrim(str_replace($root, '', $path), '/');

    if (!preg_match('/\.(php|html|phtml)$/i', $path)) {
        continue;
    }
    if (preg_match('#(^|/)(\.git|vendor|node_modules|tests)/#', '/' . $rel)) {
        continue;
    }

    $src = (string) file_get_contents($path);

    if (!preg_match_all(
        '/\bon(' . $events . ')\s*=\s*(["\'])(.*?)\2/is',
        $src,
        $matches,
        PREG_OFFSET_CAPTURE | PREG_SET_ORDER
    )) {
        continue;
    }

    foreach ($matches as $m) {
        $line = substr_count(substr($src, 0, (int) $m[0][1]), "\n") + 1;
        $body = trim(preg_replace('/\s+/', ' ', $m[3][0]) ?? '');

        $found[] = [
            'file' => $rel,
            'line' => $line,
            'event' => strtolower($m[1][0]),
            'body' => $body,
            'class' => classify($body),
        ];
    }
}

/**
 * How hard is this one to remove? The categories drive the order of
 * the work, not just the size of it.
 */
function classify(string $body): string
{
    if (strpos($body, '<?') !== false) {
        return 'php-interpolated';
    }
    if (preg_match('/^[A-Za-z_$][\w$]*\s*\(\s*\)\s*;?$/', $body)) {
        return 'bare-call';
    }
    if (preg_match('/^[A-Za-z_$][\w$]*\s*\(.*\)\s*;?$/s', $body) && !preg_match('/[;{}]|=>|function/', $body)) {
        return 'call-with-args';
    }
    return 'statements';
}

$count = count($found);
$budget = is_file($budgetFile) ? (int) trim((string) file_get_contents($budgetFile)) : $count;

if ($report) {
    $byClass = [];
    $byFile = [];
    foreach ($found as $f) {
        $byClass[$f['class']] = ($byClass[$f['class']] ?? 0) + 1;
        $byFile[$f['file']] = ($byFile[$f['file']] ?? 0) + 1;
    }
    arsort($byFile);

    echo "Inline event handlers: $count (budget $budget)\n\n";

    echo "By difficulty:\n";
    $order = [
        'bare-call' => 'a bare call - becomes an addEventListener with no argument plumbing',
        'call-with-args' => 'a call with literal arguments - data-* attributes carry them',
        'statements' => 'arbitrary statements - needs a named function writing first',
        'php-interpolated' => 'contains PHP - the value must move into a data-* attribute',
    ];
    foreach ($order as $class => $note) {
        if (isset($byClass[$class])) {
            printf("  %4d  %-18s %s\n", $byClass[$class], $class, $note);
        }
    }

    echo "\nBy file:\n";
    foreach (array_slice($byFile, 0, 20, true) as $file => $n) {
        printf("  %4d  %s\n", $n, $file);
    }

    echo "\nNote: reaching zero does not by itself allow 'unsafe-inline'\n";
    echo "to be dropped. The inline <script> blocks each need a nonce\n";
    echo "too, and a nonce disables 'unsafe-inline' the moment it\n";
    echo "appears - so the handlers must all be gone first.\n";
    exit(0);
}

if ($update) {
    file_put_contents($budgetFile, $count . "\n");
    echo "Budget set to $count.\n";
    exit(0);
}

if ($count > $budget) {
    warn("INLINE HANDLERS: $count found, budget is $budget.\n\n");
    warn("New inline event handlers have been added. Attach the listener\n");
    warn("from a <script> block instead - every on* attribute is a reason\n");
    warn("script-src must keep 'unsafe-inline', which is what stops the\n");
    warn("CSP being an XSS defence.\n\n");
    warn("Run with --report to see where they all are.\n");
    exit(1);
}

if ($count < $budget) {
    echo "INLINE HANDLERS: $count (budget $budget) - "
        . ($budget - $count) . " removed. Lower the budget:\n";
    echo "  php scripts/check_inline_handlers.php --update\n";
    exit(1);
}

echo "INLINE HANDLERS: $count, at budget.\n";
exit(0);
