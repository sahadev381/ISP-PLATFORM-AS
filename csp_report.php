<?php
/**
 * CSP violation collector.
 *
 * Point Content-Security-Policy-Report-Only's report-uri here. See
 * includes/csp_report.php for why this endpoint is unauthenticated
 * and what guards it instead.
 *
 * It always answers 204, whatever happens. A browser does not read
 * the response, and an error page here would be pure noise.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/csp_report.php';

env_load();

http_response_code(204);
header('Content-Length: 0');

/* Reports arrive by POST and nothing else. */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    exit;
}

$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > csp_report_max_bytes()) {
    exit;
}

$body = (string) file_get_contents('php://input', false, null, 0, csp_report_max_bytes() + 1);
if ($body === '' || strlen($body) > csp_report_max_bytes()) {
    exit;
}

$report = csp_report_extract($body);
if ($report === null || csp_report_is_noise($report)) {
    exit;
}

$dir = sys_get_temp_dir() . '/isp_csp_seen';
if (csp_report_seen(csp_report_signature($report), $dir)) {
    exit;
}

error_record('csp', csp_report_summary($report), [
    'directive' => $report['directive'],
    'blocked' => $report['blocked'],
    'page' => $report['document'],
    'source' => $report['source'],
    'line' => $report['line'],
    /* script-sample is a fragment of the offending inline script. It
       is the single most useful field for finding which handler to
       remove, and the one most likely to contain something private,
       so error_redact() runs over it like everything else. */
    'sample' => $report['sample'],
]);
