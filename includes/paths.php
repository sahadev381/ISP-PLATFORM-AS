<?php
/**
 * Where the application root is, as seen from the page being served.
 *
 * Pages reference assets as `<?= e($base_path) ?>assets/css/theme.css`,
 * and every page sets `$base_path` by hand. They do not agree:
 *
 *     payment/, report/, reports/   '../'      correct
 *     billing/                      '.'        -> ".assets/css/..."
 *     hotspot/admin/                '.'        -> two levels too shallow
 *     hotspot/admin/roles.php       '../..'    -> "../..assets/css/..."
 *     root pages                    '' or './' both correct
 *
 * So the stylesheet and the scripts have been 404ing on every page
 * under billing/ and hotspot/admin/. The browser check found thirteen
 * missing assets; this is where they came from.
 *
 * A value that must be correct in thirty files, and is derivable in
 * one, should be derived.
 */

declare(strict_types=1);

/**
 * How many directories deep a script sits below the application root.
 *
 * Pure, so the arithmetic can be tested without a web server.
 */
function base_path_depth(string $root, string $script): int
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $script = str_replace('\\', '/', $script);

    if ($root === '' || strpos($script, $root . '/') !== 0) {
        /* The script is not below the root we were told about - a
           symlinked docroot, say. Guessing would produce a path that
           is confidently wrong, so claim the root. */
        return 0;
    }

    $relative = substr($script, strlen($root) + 1);

    return substr_count($relative, '/');
}

/** '' at the root, '../' one level down, '../../' two, and so on. */
function base_path_for_depth(int $depth): string
{
    return $depth > 0 ? str_repeat('../', $depth) : '';
}

/**
 * The prefix for this request. Computed once.
 */
function app_base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    /* includes/ sits directly under the application root. */
    $root = realpath(dirname(__DIR__));
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $real = $script !== '' ? realpath($script) : false;

    if ($root === false || $real === false) {
        return $cached = '';
    }

    return $cached = base_path_for_depth(base_path_depth($root, $real));
}
