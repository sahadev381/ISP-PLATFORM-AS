<?php
/**
 * Output escaping (includes/html.php).
 *
 * These are the functions the whole XSS sweep depends on, so the point
 * of this file is to pin the behaviour that makes each one safe in its
 * own context - and, just as importantly, to document that using the
 * wrong one is exploitable.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/html.php';

/** @var TestRunner $t */

$t->group('e() - HTML text and quoted attributes');

$t->is('escapes angle brackets', e('<script>'), '&lt;script&gt;');
$t->is('escapes double quotes', e('a"b'), 'a&quot;b');
$t->is('escapes single quotes', e("a'b"), 'a&#039;b');
$t->is('escapes ampersand', e('a&b'), 'a&amp;b');
$t->is('null becomes empty', e(null), '');
$t->is('false becomes empty', e(false), '');
$t->is('true becomes 1', e(true), '1');
$t->is('integers pass through', e(42), '42');
$t->is('leaves plain text alone', e('Ram Bahadur'), 'Ram Bahadur');
$t->is('handles UTF-8', e('काठमाडौँ'), 'काठमाडौँ');

// The classic payload: a customer whose "full name" is an image tag.
$t->lacks(
    'img/onerror payload cannot open a tag',
    e('<img src=x onerror=alert(1)>'),
    '<img'
);

$t->group('e_js() - inside <script>');

$t->is('emits a complete quoted literal', e_js('hello'), '"hello"');
$t->is('escapes embedded quotes', e_js('a"b'), '"a\u0022b"');
// A bare </script> inside a JS string still ends the script element, so
// the tag characters have to be hex-escaped, not just quote-escaped.
$t->lacks('cannot close the script element', e_js('</script>'), '</');
$t->lacks('escapes ampersands', e_js('a&b'), '&');
$t->is('booleans are JSON booleans', e_js(true), 'true');
$t->is('null is JSON null', e_js(null), 'null');

$t->group('e_attr_js() - inside onclick="fn(\'...\')"');

// This is the bug the Phase 3 sweep missed. The browser HTML-decodes an
// attribute before the JS parser sees it, so e()'s &#039; turns back
// into a bare quote and closes the string. e_attr_js() must JS-escape
// first, so a backslash survives the decode.
$decoded = html_entity_decode(e_attr_js("a'b"), ENT_QUOTES, 'UTF-8');
$t->is('quote survives HTML decoding as an escaped quote', $decoded, "a\\'b");
$t->lacks('never yields a bare quote after decoding', $decoded, "b'");
$t->is('escapes backslashes', html_entity_decode(e_attr_js('a\\b'), ENT_QUOTES, 'UTF-8'), 'a\\\\b');
$t->lacks('attribute delimiter is safe', e_attr_js('a"b'), '"');
$t->is('null becomes empty', e_attr_js(null), '');

$t->group('e_url() - query string and path segments');

$t->is('encodes spaces', e_url('a b'), 'a%20b');
$t->is('encodes ampersand so it cannot add a parameter', e_url('a&b=c'), 'a%26b%3Dc');
$t->is('encodes slashes', e_url('a/b'), 'a%2Fb');

$t->group('e_href() - whole URLs');

$t->is('relative paths pass through', e_href('users.php?user=ram'), 'users.php?user=ram');
$t->is('https passes through', e_href('https://example.com/x'), 'https://example.com/x');
$t->is('http passes through', e_href('http://example.com/x'), 'http://example.com/x');
$t->is('mailto passes through', e_href('mailto:a@example.com'), 'mailto:a@example.com');
$t->is('tel passes through', e_href('tel:+9779800000000'), 'tel:+9779800000000');

// Anything that can execute must be refused outright rather than escaped.
$t->is('javascript: is refused', e_href('javascript:alert(1)'), '#');
$t->is('mixed-case JaVaScRiPt: is refused', e_href('JaVaScRiPt:alert(1)'), '#');
$t->is('data: is refused', e_href('data:text/html,<script>alert(1)</script>'), '#');
$t->is('vbscript: is refused', e_href('vbscript:msgbox(1)'), '#');
$t->is('leading whitespace does not smuggle javascript:', e_href("  javascript:alert(1)"), '#');

$t->group('double-escaping');

// e(e($x)) shows up whenever a page is edited twice. It is not a
// security hole, but it renders visible &amp;lt; to the user, so the
// test records the behaviour rather than pretending it is fine.
$t->is('e() is not idempotent', e(e('<b>')), '&amp;lt;b&amp;gt;');
