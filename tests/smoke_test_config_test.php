<?php
/**
 * The smoke test's safety rules.
 *
 * These are worth testing because the cost of getting them wrong is
 * data loss on whatever the crawler is pointed at.
 */

declare(strict_types=1);

/** @var TestRunner $t */

$t->group('smoke test denylist');

$src = (string) file_get_contents(__DIR__ . '/../scripts/smoke_test.php');

/* expire.php runs DELETE FROM radreply at the top of the file with no
   guard, so merely requesting it destroys RADIUS reply attributes. */
$t->true('expire.php is denied - it DELETEs on load',
    strpos($src, "'expire.php',") !== false);

/* Requesting a logout halfway through would end the session and make
   every page after it look like a redirect to login. */
foreach (['logout.php', 'customer/logout.php', 'hotspot/logout.php'] as $p) {
    $t->true("$p is denied - it would end the crawl's session",
        strpos($src, "'$p',") !== false);
}

foreach (['monitoring/delete_device.php', 'branch_delete.php', 'cron_block_expired.php'] as $p) {
    $t->true("$p is denied - it writes or deletes", strpos($src, "'$p',") !== false);
}

/* The crawler must never issue anything but GET to the pages it
   discovers. POST is used once, to log in. */
$t->is('only one POST helper exists', substr_count($src, 'function http_post'), 1);
$t->is('the crawl loop uses http_get', substr_count($src, 'http_get($url, $cookieJar)'), 1);

$t->true('it refuses hosts that do not look like staging',
    strpos($src, 'Refusing to crawl') !== false);
$t->true('the override is deliberately awkward to type',
    strpos($src, 'i-know-this-is-not-production') !== false);

$t->group('smoke test discovers the right pages');

/* Partials and library directories are not pages and would produce
   noise - or fatals - if requested directly. */
foreach (['includes/', 'scripts/', 'tests/', 'vendor/'] as $d) {
    $t->true("$d is not crawled", strpos($src, "'$d'") !== false);
}
$t->true('header/footer/sidebar partials are excluded',
    strpos($src, 'header|footer|sidebar|topbar') !== false);

/* Every file named in the denylist must actually exist, or the entry is
   stale and gives false confidence. */
$root = dirname(__DIR__);
preg_match('/\$denylist = \[(.*?)\];/s', $src, $m);
preg_match_all("/'([^']+\.php)'/", $m[1] ?? '', $entries);
$missing = [];
foreach ($entries[1] ?? [] as $entry) {
    if ($entry === 'config.php' || $entry === 'user-config.php') {
        continue;   // gitignored, exists only on a deployment
    }
    if (!file_exists($root . '/' . $entry)) {
        $missing[] = $entry;
    }
}
$t->is('every denylisted file exists (a stale entry is false confidence)', $missing, []);
