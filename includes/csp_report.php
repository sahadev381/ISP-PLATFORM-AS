<?php
/**
 * Collecting CSP violation reports.
 *
 * includes/http_headers.php has been sending
 * Content-Security-Policy-Report-Only for some time, with a comment
 * saying the resulting report is "the work list for removing the
 * inline handlers". It was not, because the policy carried no
 * report-uri: the violations went to the browser console of whoever
 * happened to have devtools open, and nowhere else.
 *
 * This file is the collector. Two things make it unusual among the
 * endpoints in this project:
 *
 *   1. It cannot be authenticated. The browser posts reports without
 *      credentials, so anything that requires a session would reject
 *      every real report.
 *   2. Anyone on the internet can make a browser post to it, as many
 *      times as they like, by embedding a page that violates its own
 *      policy. An unprotected report endpoint is a log-flooding
 *      vector, and a full disk takes the whole platform down.
 *
 * So the defences here are about volume and noise rather than
 * identity: cap the body, drop duplicates, drop the browser-extension
 * reports that make up most of the traffic in practice, and refuse to
 * write more than a fixed number of records per hour.
 *
 * The helpers are pure; csp_report_receive() is the part with side
 * effects.
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';

/** A report larger than this is not a real report. */
function csp_report_max_bytes(): int
{
    return 16384;
}

/** Records written per hour, per violation signature. */
function csp_report_hourly_cap(): int
{
    return 200;
}

/**
 * Pull the fields worth keeping out of a report body.
 *
 * Browsers disagree on the format: the original spec uses
 * `{"csp-report": {...}}` with hyphenated keys, the Reporting API uses
 * a JSON array of `{"type":"csp-violation","body":{...}}` with
 * camelCase keys. Both arrive in practice, so both are handled.
 *
 * Returns null when the payload is not a CSP report at all.
 */
function csp_report_extract(string $json): ?array
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    /* Reporting API: an array of reports. Take the first CSP one. */
    if (isset($data[0]) && is_array($data[0])) {
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['type'] ?? '') === 'csp-violation' && is_array($entry['body'] ?? null)) {
                $data = $entry['body'];
                break;
            }
        }
    }

    if (isset($data['csp-report']) && is_array($data['csp-report'])) {
        $data = $data['csp-report'];
    }

    $get = function (string ...$keys) use ($data) {
        foreach ($keys as $k) {
            if (isset($data[$k]) && $data[$k] !== '') {
                return is_scalar($data[$k]) ? (string) $data[$k] : null;
            }
        }
        return null;
    };

    $directive = $get('effective-directive', 'effectiveDirective', 'violated-directive', 'violatedDirective');
    $document = $get('document-uri', 'documentURL', 'document-url');

    /* Neither present means this was not a CSP report. */
    if ($directive === null && $document === null) {
        return null;
    }

    return [
        'directive' => $directive,
        'document' => $document,
        'blocked' => $get('blocked-uri', 'blockedURL', 'blocked-url'),
        'source' => $get('source-file', 'sourceFile'),
        'line' => $get('line-number', 'lineNumber'),
        'column' => $get('column-number', 'columnNumber'),
        'sample' => $get('script-sample', 'sample'),
        'disposition' => $get('disposition'),
    ];
}

/**
 * A stable identity for "the same violation again".
 *
 * Deliberately excludes the line number's neighbours and the sample,
 * so one broken page reported by a thousand visitors collapses to one
 * record rather than a thousand.
 */
function csp_report_signature(array $report): string
{
    return substr(hash('sha256', implode('|', [
        $report['directive'] ?? '',
        $report['blocked'] ?? '',
        /* The query string differs per visitor; the path does not. */
        parse_url((string) ($report['document'] ?? ''), PHP_URL_PATH) ?? '',
        $report['source'] ?? '',
        $report['line'] ?? '',
    ])), 0, 16);
}

/**
 * Is this report about our own page, or about something injected into
 * the browser by the user's own software?
 *
 * In practice most reports from a real deployment are browser
 * extensions rewriting the page: password managers, ad blockers,
 * translation tools. They are not bugs in this application and they
 * drown the reports that are.
 */
function csp_report_is_noise(array $report): bool
{
    $haystack = strtolower(implode(' ', array_filter([
        $report['blocked'] ?? '',
        $report['source'] ?? '',
        $report['document'] ?? '',
    ], 'is_string')));

    $extensionSchemes = [
        'chrome-extension', 'moz-extension', 'safari-extension',
        'safari-web-extension', 'ms-browser-extension', 'webkit-masked-url',
    ];

    foreach ($extensionSchemes as $scheme) {
        if (strpos($haystack, $scheme) !== false) {
            return true;
        }
    }

    /* Some mobile browsers and in-app webviews report their own
       injected about:/data: frames. */
    if (in_array($report['blocked'] ?? '', ['about', 'about:blank', 'null'], true)) {
        return true;
    }

    return false;
}

/**
 * Has this signature already been recorded recently?
 *
 * A tiny file-backed set, pruned by age. Not perfect under
 * concurrency - two processes can both decide a signature is new -
 * which costs one duplicate record and is cheaper than a lock.
 */
function csp_report_seen(string $signature, string $dir, int $ttlSeconds = 3600): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        /* Cannot dedupe; better to record than to drop. */
        return false;
    }

    $marker = $dir . '/csp_' . preg_replace('/[^a-f0-9]/', '', $signature);

    if (is_file($marker) && (time() - (int) filemtime($marker)) < $ttlSeconds) {
        return true;
    }

    @touch($marker);

    return false;
}

/**
 * Turn a report into the one-line summary that goes in the log.
 */
function csp_report_summary(array $report): string
{
    return sprintf(
        'CSP %s blocked %s on %s%s',
        $report['directive'] ?? 'unknown-directive',
        $report['blocked'] ?? 'unknown',
        $report['document'] ?? 'unknown page',
        isset($report['line']) && $report['line'] !== null ? ' line ' . $report['line'] : ''
    );
}
