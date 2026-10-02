<?php
/**
 * Asset base paths (includes/paths.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/paths.php';

/** @var TestRunner $t */

$t->group('how deep the page is');

$root = '/var/www/isp';

$t->is('a page at the root is depth 0',
    base_path_depth($root, '/var/www/isp/dashboard.php'), 0);
$t->is('one directory down is depth 1',
    base_path_depth($root, '/var/www/isp/billing/invoices.php'), 1);
$t->is('two directories down is depth 2',
    base_path_depth($root, '/var/www/isp/hotspot/admin/plans.php'), 2);

$t->is('a trailing slash on the root makes no difference',
    base_path_depth($root . '/', '/var/www/isp/billing/invoices.php'), 1);

$t->is('Windows separators are handled',
    base_path_depth('C:\\www\\isp', 'C:\\www\\isp\\billing\\invoices.php'), 1);

/* Rather than emit a confidently wrong path. */
$t->is('a script outside the root claims the root',
    base_path_depth($root, '/somewhere/else/page.php'), 0);
$t->is('an empty root claims the root',
    base_path_depth('', '/var/www/isp/billing/x.php'), 0);

/* A directory whose name starts with the root name must not count as
   being inside it: /var/www/isp-old is not under /var/www/isp. */
$t->is('a sibling with a similar name is not inside',
    base_path_depth($root, '/var/www/isp-old/billing/x.php'), 0);

$t->group('the prefix that produces');

$t->is('the root needs no prefix', base_path_for_depth(0), '');
$t->is('one level up', base_path_for_depth(1), '../');
$t->is('two levels up', base_path_for_depth(2), '../../');
$t->is('a negative depth is treated as the root', base_path_for_depth(-1), '');

$t->group('the values the pages used to set by hand');

/* These are the actual strings found in the codebase, and what they
   produced when concatenated with 'assets/css/theme.css'. */
$t->is('billing/ said "." which gives .assets/css/theme.css',
    '.' . 'assets/css/theme.css', '.assets/css/theme.css');
$t->is('the derived value gives ../assets/css/theme.css',
    base_path_for_depth(base_path_depth($root, '/var/www/isp/billing/invoices.php')) . 'assets/css/theme.css',
    '../assets/css/theme.css');
$t->is('hotspot/admin/ needs two levels, not one',
    base_path_for_depth(base_path_depth($root, '/var/www/isp/hotspot/admin/plans.php')) . 'assets/css/theme.css',
    '../../assets/css/theme.css');
