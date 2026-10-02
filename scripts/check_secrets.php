<?php
/**
 * Guard: no credentials in code.
 *
 *     php scripts/check_secrets.php
 *
 * Phases 1 and 2 moved every hardcoded credential out of the code and
 * into .env. Nothing stops them coming back, and they come back the
 * same way they arrived the first time: somebody debugging at 2am
 * types the real password in to see if that was the problem, and the
 * commit lands.
 *
 * That matters more here than in most projects, because these
 * particular secrets are already in the git history and cannot be
 * removed from it. Re-adding one does not make things slightly worse;
 * it makes the rotation that finally fixes them pointless.
 *
 * Documentation is excluded. AUDIT_REPORT.md and RELEASE_READINESS.md
 * quote the leaked values deliberately - that is the record of what
 * needs rotating, and a scanner that forbids naming the problem makes
 * the problem harder to fix.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

function secrets_warn(string $msg): void
{
    $h = defined('STDERR') ? STDERR : @fopen('php://stderr', 'w');
    if (is_resource($h)) {
        fwrite($h, $msg);
    } else {
        echo $msg;
    }
}

$root = dirname(__DIR__);

/**
 * Values known to be in the git history. These must never reappear,
 * whatever the context.
 */
function secret_known_values(): array
{
    return [
        'radiuspass',
        'StrongPass123',
        'admin123',
    ];
}

/**
 * Shapes that are a credential regardless of the value.
 */
function secret_patterns(): array
{
    return [
        'mysqli with a literal password' =>
            '/new\s+mysqli\s*\(\s*(["\'])[^"\']*\1\s*,\s*(["\'])[^"\']*\2\s*,\s*(["\'])[^"\']{1,}\3/i',

        'a password assigned a literal' =>
            '/\$(?:db_)?(?:pass|passwd|password|secret|api_key|apikey|token)\w*\s*=\s*(["\'])(?!\s*$)[^"\'$]{4,}\1\s*;/i',

        'a non-empty default for a secret' =>
            '/\benv\s*\(\s*(["\'])[A-Z0-9_]*(?:PASS|PASSWORD|SECRET|TOKEN|KEY|SID)[A-Z0-9_]*\1\s*,\s*(["\'])[^"\']{3,}\2\s*\)/',

        'a Twilio account sid' => '/\bAC[0-9a-f]{32}\b/i',
        'a Stripe-style live key' => '/\b[sr]k_live_[0-9A-Za-z]{10,}/',
        'an AWS access key id' => '/\bAKIA[0-9A-Z]{16}\b/',
        'a private key block' => '/-----BEGIN (?:RSA |EC |OPENSSH |PGP )?PRIVATE KEY-----/',
        'a bearer token literal' => '/["\']Bearer\s+[A-Za-z0-9._\-]{16,}["\']/i',
        'a basic-auth URL' => '#["\'][a-z][a-z0-9+.\-]*://[^/\s:"\']+:[^@/\s"\']{3,}@#i',
    ];
}

/**
 * Lines that are deliberate and reviewed.
 *
 * One entry per line: "path:needle # why". A substring match on both
 * so it survives the line moving.
 */
function secret_allowlist(string $root): array
{
    $file = $root . '/.secret-allowlist';
    if (!is_file($file)) {
        return [];
    }

    $out = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $line = trim(explode('#', $line)[0]);
        if ($line === '' || strpos($line, ':') === false) {
            continue;
        }
        [$path, $needle] = explode(':', $line, 2);
        $out[] = ['path' => trim($path), 'needle' => trim($needle)];
    }

    return $out;
}

function secret_is_allowed(array $allowlist, string $relPath, string $line): bool
{
    foreach ($allowlist as $entry) {
        if ($entry['path'] === $relPath && $entry['needle'] !== '' && strpos($line, $entry['needle']) !== false) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------------ */

$allowlist = secret_allowlist($root);
$findings = [];
$scanned = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($it as $file) {
    $path = $file->getPathname();
    $rel = ltrim(str_replace($root, '', $path), '/');

    if (preg_match('#(^|/)(\.git|vendor|node_modules|backups)/#', '/' . $rel)) {
        continue;
    }
    /* Docs quote the leaked values on purpose; .env is gitignored and
       is where the real values are supposed to live. */
    if (preg_match('/\.(md|lock|png|jpg|jpeg|gif|svg|ico|zip|gz|pdf)$/i', $rel)) {
        continue;
    }
    if ($rel === '.env' || $rel === '.secret-allowlist' || $rel === 'scripts/check_secrets.php') {
        continue;
    }
    if (!preg_match('/\.(php|js|sql|ya?ml|sh|json|ini|conf|html|example|txt)$/i', $rel) && strpos($rel, '.') !== false) {
        continue;
    }

    $scanned++;
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

    foreach ($lines as $i => $line) {
        $n = $i + 1;

        foreach (secret_known_values() as $value) {
            if (stripos($line, $value) !== false && !secret_is_allowed($allowlist, $rel, $line)) {
                $findings[] = [$rel, $n, "the leaked value '$value'", trim($line)];
            }
        }

        /* Test files contain fabricated credentials on purpose - the
           redaction tests cannot verify that a password is stripped
           without a password to strip. The shape-based rules would
           flag all of them. The known-leaked values above still
           apply, because a real secret pasted into a fixture is as
           exposed as one pasted into a page. */
        if (strpos($rel, 'tests/') === 0) {
            continue;
        }

        foreach (secret_patterns() as $label => $pattern) {
            if (preg_match($pattern, $line) && !secret_is_allowed($allowlist, $rel, $line)) {
                $findings[] = [$rel, $n, $label, trim($line)];
            }
        }
    }
}

if ($findings) {
    secrets_warn("SECRETS: credentials found in code.\n\n");
    foreach ($findings as [$rel, $n, $why, $line]) {
        $snippet = strlen($line) > 100 ? substr($line, 0, 100) . '...' : $line;
        secrets_warn(sprintf("  %s:%d  %s\n      %s\n", $rel, $n, $why, $snippet));
        echo sprintf("::error file=%s,line=%d::%s\n", $rel, $n, $why);
    }
    secrets_warn("\nMove the value to .env and read it with env(). If this is a\n");
    secrets_warn("false positive, add it to .secret-allowlist with a reason.\n");
    exit(1);
}

echo "SECRETS: $scanned files scanned, no credentials in code.\n";
exit(0);
