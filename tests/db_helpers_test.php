<?php
/**
 * Query helpers (includes/db.php).
 *
 * Only the pure functions are covered here; db_one()/db_all() need a
 * live mysqli connection and are exercised by the schema job in CI.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/** @var TestRunner $t */

$t->group('db_types() - bind_param type string');

$t->is('integers', db_types([1, 2]), 'ii');
$t->is('strings', db_types(['a', 'b']), 'ss');
$t->is('floats', db_types([1.5]), 'd');
$t->is('mixed', db_types([1, 'a', 2.5]), 'isd');
$t->is('empty', db_types([]), '');
// NULL has to bind as a string, otherwise mysqli coerces it to 0 and a
// nullable column silently gets the wrong value.
$t->is('null binds as string', db_types([null]), 's');
$t->is('booleans bind as integers', db_types([true, false]), 'ii');
$t->is('the type string matches the parameter count', strlen(db_types([1, 'a', null, 2.5])), 4);

$t->group('db_like() - LIKE escaping');

$t->is('plain text is wrapped in wildcards', db_like('ram'), '%ram%');

// Without escaping, a search for "100%" matches everything, and "_"
// matches any single character. Both are user-supplied on the customer
// search box.
$t->is('percent is escaped', db_like('100%'), '%100\\%%');
$t->is('underscore is escaped', db_like('a_b'), '%a\\_b%');
$t->is('backslash is escaped first', db_like('a\\b'), '%a\\\\b%');
$t->is('empty search still yields wildcards', db_like(''), '%%');
