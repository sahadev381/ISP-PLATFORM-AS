<?php
/**
 * Error capture.
 *
 * Before this file, an error went to the PHP error log and nowhere
 * else. A 500 on a customer page was invisible until somebody phoned
 * to complain, and when they did there was no way to connect their
 * complaint to a line in the log.
 *
 * What this adds:
 *
 *   - every warning, exception and fatal is recorded as one JSON line,
 *     with the URL, the admin who hit it and a stack trace;
 *   - each record carries a short reference, which is also shown to
 *     the user and returned as the X-Request-Id header, so "I got an
 *     error, it said a4f91c2e" locates the exact record;
 *   - fatals are caught on shutdown, which is the only way to see the
 *     errors that kill a request outright;
 *   - the visitor gets a plain apology, never a stack trace. Leaking
 *     one hands over file paths, SQL and sometimes credentials.
 *
 * The helpers below are pure so they can be tested; error_boot() is
 * the only part that touches global state.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * A short, unguessable handle for one failure.
 *
 * Short enough to read down a phone line, random so it cannot be
 * guessed to probe whether an error occurred.
 */
function error_reference_id(): string
{
    return bin2hex(random_bytes(4));
}

function error_severity_label(int $errno): string
{
    $map = [
        E_ERROR => 'error',
        E_WARNING => 'warning',
        E_PARSE => 'error',
        E_NOTICE => 'notice',
        E_CORE_ERROR => 'error',
        E_CORE_WARNING => 'warning',
        E_COMPILE_ERROR => 'error',
        E_COMPILE_WARNING => 'warning',
        E_USER_ERROR => 'error',
        E_USER_WARNING => 'warning',
        E_USER_NOTICE => 'notice',
        E_RECOVERABLE_ERROR => 'error',
        E_DEPRECATED => 'deprecated',
        E_USER_DEPRECATED => 'deprecated',
    ];

    return $map[$errno] ?? 'unknown';
}

/**
 * Does this error type end the request?
 *
 * Only these reach the shutdown handler, and only these should turn
 * into a 500 page.
 */
function error_is_fatal(int $errno): bool
{
    return (bool) ($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR));
}

/**
 * Strip secrets out of text before it is written to a log.
 *
 * Error messages quote the code that failed, and the code that fails
 * is often the code handling a password. A mysqli connection error
 * names the user; a dumped request body contains the login form. The
 * log is usually world-readable on a shared host, so this is the last
 * place to catch it.
 */
function error_redact(string $text): string
{
    $patterns = [
        /* key = value, key: value, "key" => "value" */
        /* The value alternation tries "Bearer xyz" first: otherwise
           `Authorization: Bearer eyJ...` redacts the word Bearer and
           leaves the token sitting in the log. */
        '/\b(password|passwd|pwd|secret|token|api[_-]?key|authorization|auth|private[_-]?key|DB_PASS|SMS_TOKEN|KHALTI_SECRET_KEY|ESEWA_SECRET)\b(\s*[:=>]+\s*|["\']\s*(?:=>|:)\s*)(["\']?)((?:Bearer|Basic)\s+[A-Za-z0-9._\-\/+=]+|[^"\'\s,;)&]+)\3/i',
    ];

    foreach ($patterns as $p) {
        $text = (string) preg_replace($p, '$1$2$3[redacted]$3', $text);
    }

    /* Credentials embedded in a URL: mysql://user:pass@host */
    $text = (string) preg_replace('#(://[^:/\s]+):([^@/\s]+)@#', '$1:[redacted]@', $text);

    /* Bearer tokens */
    $text = (string) preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._\-\/+=]{8,}/i', '$1 [redacted]', $text);

    return $text;
}

/**
 * Should the failure be described to the person who triggered it?
 *
 * Only ever in debug mode. "display_errors on in production" is how
 * database credentials end up in a screenshot in a support ticket.
 */
function error_should_display(bool $debug): bool
{
    return $debug;
}

/**
 * Does this caller want JSON rather than an HTML page?
 *
 * Returning an HTML error page to an AJAX caller produces a json parse
 * error in the browser console and hides the real failure.
 */
function error_wants_json(string $accept, string $requestedWith, string $uri): bool
{
    if (stripos($requestedWith, 'xmlhttprequest') !== false) {
        return true;
    }
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }
    /* The project's convention: anything named *_api.php or under api/ */
    if (preg_match('#(^|/)api/|_api\.php#i', $uri)) {
        return true;
    }

    return false;
}

/**
 * One log record, as a single line of JSON.
 *
 * One line per record matters: it survives grep, and interleaved
 * writes from concurrent requests cannot corrupt each other the way
 * multi-line records do.
 */
function error_format_record(array $parts): string
{
    $parts = array_filter($parts, fn($v) => $v !== null && $v !== '');

    foreach ($parts as $k => $v) {
        if (is_string($v)) {
            $parts[$k] = error_redact($v);
        }
    }

    $json = json_encode($parts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    /* A newline inside the record would break one-line-per-record. */
    return str_replace("\n", ' ', (string) $json);
}

/**
 * Trim a stack trace to something a log can hold.
 *
 * A deep framework trace can be tens of kilobytes; the top frames are
 * where the bug is.
 */
function error_trim_trace(string $trace, int $maxFrames = 12): string
{
    $lines = preg_split('/\r?\n/', trim($trace)) ?: [];
    if (count($lines) <= $maxFrames) {
        return implode("\n", $lines);
    }

    $kept = array_slice($lines, 0, $maxFrames);
    $kept[] = sprintf('... %d more frames', count($lines) - $maxFrames);

    return implode("\n", $kept);
}

/**
 * Where records are written.
 *
 * A dedicated file by default so application failures are not buried
 * in whatever else shares the PHP error log. Returning '' means "use
 * the configured PHP error log", which is the right fallback when the
 * directory is not writable.
 */
function error_log_path(?string $configured = null): string
{
    $path = $configured !== null ? $configured : (string) env('ERROR_LOG_FILE', '');
    if ($path === '') {
        return '';
    }

    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        return '';
    }

    return $path;
}

/* ------------------------------------------------------------------ */

if (!function_exists('error_record')) {
    /**
     * Write one record. Returns the reference shown to the user.
     */
    function error_record(string $level, string $message, array $context = []): string
    {
        $ref = $context['ref'] ?? error_reference_id();

        $record = array_merge([
            'ts' => date('c'),
            'ref' => $ref,
            'level' => $level,
            'message' => $message,
            'uri' => $_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? 'cli:' . basename($_SERVER['argv'][0] ?? '?') : ''),
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'admin' => $_SESSION['username'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], $context);

        unset($record['ref']);
        $record = ['ts' => date('c'), 'ref' => $ref] + $record;

        $line = error_format_record($record);

        $path = error_log_path();
        if ($path !== '') {
            /* FILE_APPEND plus a single write() is atomic enough for
               line-sized records on every filesystem this runs on. */
            @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }

        return $ref;
    }
}

if (!function_exists('error_render_page')) {
    /**
     * Tell the visitor something went wrong, and nothing else.
     */
    function error_render_page(string $ref, bool $debug, string $detail = ''): void
    {
        if (PHP_SAPI === 'cli') {
            $out = defined('STDERR') ? STDERR : fopen('php://stderr', 'w');
            fwrite($out, "Error [$ref]" . ($debug && $detail ? ": $detail" : '') . "\n");
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('X-Request-Id: ' . $ref);
        }

        $json = error_wants_json(
            (string) ($_SERVER['HTTP_ACCEPT'] ?? ''),
            (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''),
            (string) ($_SERVER['REQUEST_URI'] ?? '')
        );

        if ($json) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'status' => 'error',
                'message' => 'Something went wrong. Quote reference ' . $ref . ' if you contact support.',
                'reference' => $ref,
            ] + ($debug && $detail ? ['debug' => $detail] : []));
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $safeRef = htmlspecialchars($ref, ENT_QUOTES, 'UTF-8');
        echo "<!doctype html><html><head><meta charset=\"utf-8\">"
            . "<title>Something went wrong</title>"
            . "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head>"
            . "<body style=\"font-family:system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1rem;color:#222\">"
            . "<h1 style=\"font-size:1.4rem\">Something went wrong</h1>"
            . "<p>The page could not be loaded. The problem has been recorded.</p>"
            . "<p>If you contact support, quote reference <code>{$safeRef}</code>.</p>";

        if ($debug && $detail !== '') {
            echo "<pre style=\"background:#f6f6f6;padding:1rem;overflow:auto;font-size:.8rem\">"
                . htmlspecialchars(error_redact($detail), ENT_QUOTES, 'UTF-8') . "</pre>";
        }

        echo "</body></html>";
    }
}

if (!function_exists('error_boot')) {
    /**
     * Install the handlers. Call once, as early as possible.
     */
    function error_boot(?bool $debug = null): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }
        $booted = true;

        $debug = $debug ?? (bool) env('APP_DEBUG', false);

        set_error_handler(function (int $errno, string $msg, string $file = '', int $line = 0) use ($debug): bool {
            /* Respect @ suppression and the configured error_reporting
               level, otherwise every silenced filesystem probe in the
               codebase fills the log. */
            if (!(error_reporting() & $errno)) {
                return false;
            }

            $ref = error_record(error_severity_label($errno), $msg, [
                'file' => $file,
                'line' => $line,
            ]);

            if (error_is_fatal($errno)) {
                error_render_page($ref, $debug, "$msg in $file:$line");
                exit(1);
            }

            /* false = also let PHP's own logging run, which keeps
               existing log-watching setups working. */
            return false;
        });

        set_exception_handler(function (Throwable $e) use ($debug): void {
            $ref = error_record('exception', get_class($e) . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => error_trim_trace($e->getTraceAsString()),
            ]);

            error_render_page($ref, $debug, get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        });

        register_shutdown_function(function () use ($debug): void {
            $last = error_get_last();
            if ($last === null || !error_is_fatal((int) $last['type'])) {
                return;
            }

            /* The only way to see an out-of-memory, a call to an
               undefined function, or a parse error in an include. */
            $ref = error_record('fatal', (string) $last['message'], [
                'file' => $last['file'] ?? '',
                'line' => $last['line'] ?? 0,
            ]);

            error_render_page($ref, $debug, (string) $last['message']);
        });
    }
}
