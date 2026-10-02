<?php
/**
 * action_attr() - the attributes that hand an element to
 * assets/js/actions.js in place of an inline on* handler.
 *
 * The behaviour worth pinning is that arguments are DATA. The form
 * being replaced,
 *
 *     onclick="openWifiModal('<?= e_attr_js($name) ?>')"
 *
 * assembles a line of JavaScript out of a database value, so its
 * safety rests on remembering a two-stage escape in the right order on
 * every single call site. The form replacing it puts the value in JSON
 * inside an attribute, where a quote is a quote and nothing is parsed
 * as code. The only part that is not data is the function name, which
 * the dispatcher looks up on window - so that is validated hard.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/html.php';

/** @var TestRunner $t */

$t->group('action_attr(): markup that hands an element to the dispatcher');

$t->is('bare call needs no data-args',
    action_attr('toggleSidebar'),
    'data-action="toggleSidebar"');

$t->is('a non-click event is declared',
    action_attr('reload', [], 'change'),
    'data-action="reload" data-action-on="change"');

$t->is('arguments are JSON, not JavaScript',
    action_attr('deleteLead', [42]),
    'data-action="deleteLead" data-args="[42]"');

$t->is('strings keep their type',
    action_attr('submitAction', ['mark_paid', 7]),
    'data-action="submitAction" data-args="[&quot;mark_paid&quot;,7]"');

/* The whole point. The old form built a line of JavaScript out of a
   database value; a quote in a customer name closed the string and the
   rest ran as code. Here a quote is just a character in JSON, and the
   JSON is just text in an attribute. */
$t->is('a quote in a value cannot close anything',
    action_attr('openWifiModal', ["O'Brien"]),
    'data-action="openWifiModal" data-args="[&quot;O&#039;Brien&quot;]"');

$t->lacks('an attribute-closing quote is escaped',
    action_attr('f', ['" onmouseover="alert(1)']),
    '" onmouseover=');

$t->lacks('a script tag in a value stays inert',
    action_attr('f', ['</script><script>alert(1)</script>']),
    '<script>');

$t->is('backslashes survive as data',
    action_attr('f', ['a\\b']),
    'data-action="f" data-args="[&quot;a\\\\b&quot;]"');

$t->is('slashes are not escaped, so URLs stay readable',
    action_attr('openInNewTab', ['map.php?user=bob']),
    'data-action="openInNewTab" data-args="[&quot;map.php?user=bob&quot;]"');

$t->is('nested arrays are fine',
    action_attr('openEditModal', [['id' => 3, 'name' => 'Basic']]),
    'data-action="openEditModal" data-args="[{&quot;id&quot;:3,&quot;name&quot;:&quot;Basic&quot;}]"');

/* A function name is the one thing that is NOT data - the dispatcher
   looks it up on window - so it may only ever be an identifier. */
$t->throws('a call in the function name is refused',
    fn() => action_attr('alert(1)'));

$t->throws('a method path is refused',
    fn() => action_attr('window.alert'));

$t->throws('an attribute break in the function name is refused',
    fn() => action_attr('f" onclick="alert(1)'));

$t->throws('an unsupported event is refused',
    fn() => action_attr('f', [], 'mouseover'));
