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

/* ------------------------------------------------------------------ */
$t->group('invoice expiry arithmetic (includes/billing.php)');

require_once __DIR__ . '/../includes/billing.php';

/* This is the arithmetic that decides what a customer actually got for
   their money, so it is pinned down explicitly rather than trusted. */

$t->is('renewing early adds to the time remaining, it is not thrown away',
    invoice_new_expiry('2026-12-31', 1, 30, '2026-10-01'), '2027-01-30');

$t->is('renewing after expiry starts from today, not from the lapsed date',
    invoice_new_expiry('2026-01-01', 1, 30, '2026-10-01'), '2026-10-31');

$t->is('renewing on the expiry date continues from it',
    invoice_new_expiry('2026-10-01', 1, 30, '2026-10-01'), '2026-10-31');

$t->is('a customer with no expiry starts from today',
    invoice_new_expiry('', 1, 30, '2026-10-01'), '2026-10-31');

$t->is('multiple months multiply the plan validity',
    invoice_new_expiry('', 3, 30, '2026-10-01'), '2026-12-30');

$t->is('a non-30-day plan is honoured',
    invoice_new_expiry('', 1, 7, '2026-10-01'), '2026-10-08');

/* Guards: a bad plan row must not silently grant nothing. */
$t->is('zero months is treated as one', invoice_new_expiry('', 0, 30, '2026-10-01'), '2026-10-31');
$t->is('negative months is treated as one', invoice_new_expiry('', -5, 30, '2026-10-01'), '2026-10-31');
$t->is('zero validity falls back to 30 days', invoice_new_expiry('', 1, 0, '2026-10-01'), '2026-10-31');

/* Crossing a month and a year boundary, since date maths is where this
   kind of code usually goes wrong. */
$t->is('crosses a year boundary', invoice_new_expiry('2026-12-20', 1, 30, '2026-12-01'), '2027-01-19');
$t->is('crosses February', invoice_new_expiry('', 1, 30, '2027-02-01'), '2027-03-03');
