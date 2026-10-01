<?php
/**
 * Integration tests — these run against a real MySQL server.
 *
 *     DB_HOST=127.0.0.1 DB_USER=root DB_PASS=ci DB_NAME=isp_platform \
 *         php tests/integration/run.php
 *
 * WHY THESE EXIST
 *
 * Every other test in this repository exercises a pure function. That
 * was a deliberate limit — there was no database available — but it
 * means the code that moves money has never actually run.
 *
 * invoice_settle() marks an invoice paid, extends a customer's expiry
 * and rewrites their RADIUS attributes, in a transaction, and has to be
 * safe when a payment gateway replays its callback. None of that can be
 * verified without a database, and all of it is the kind of thing that
 * is expensive to get wrong.
 *
 * These are kept in tests/integration/ so that tests/run.php, which
 * globs tests/*_test.php, does not pick them up and fail on a machine
 * with no MySQL.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../bootstrap.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'isp_platform';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $name);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    fwrite(STDERR, "Could not connect to MySQL at $host: " . $e->getMessage() . "\n");
    fwrite(STDERR, "These tests need a database. Skipping is not the same as passing.\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/billing.php';

$t = new TestRunner();

/* ------------------------------------------------------------------ */
/* Fixtures                                                            */
/* ------------------------------------------------------------------ */

/**
 * Build a customer, a plan and a pending invoice.
 *
 * Returns [invoiceId, username].
 */
function fixture(mysqli $conn, array $o = []): array
{
    $suffix   = bin2hex(random_bytes(4));
    $username = 'smoke_' . $suffix;

    $validity = $o['validity'] ?? 30;
    $speed    = $o['speed'] ?? '10M/10M';
    $price    = $o['price'] ?? 1500.00;

    $conn->query("INSERT INTO plans (name, price, speed, validity)
                  VALUES ('p_$suffix', $price, '$speed', $validity)");
    $planId = $conn->insert_id;

    $expiry = $o['expiry'] ?? null;
    $expirySql = $expiry === null ? 'NULL' : "'" . $conn->real_escape_string($expiry) . "'";

    $conn->query("INSERT INTO customers (username, password, full_name, plan_id, expiry, status, branch_id)
                  VALUES ('$username', 'x', 'Test $suffix', $planId, $expirySql, 'expired', 1)");

    $status = $o['status'] ?? 'pending';
    $months = $o['months'] ?? 1;
    $conn->query("INSERT INTO invoices (username, amount, months, status)
                  VALUES ('$username', $price, $months, '$status')");

    return [$conn->insert_id, $username];
}

function customer(mysqli $conn, string $username): array
{
    return db_one($conn, "SELECT * FROM customers WHERE username = ?", [$username]) ?: [];
}

function invoice(mysqli $conn, int $id): array
{
    return db_one($conn, "SELECT * FROM invoices WHERE id = ?", [$id]) ?: [];
}

function radValue(mysqli $conn, string $table, string $username, string $attribute): ?string
{
    $v = db_value($conn, "SELECT value FROM $table WHERE username = ? AND attribute = ?",
        [$username, $attribute], null);
    return $v === null ? null : (string) $v;
}

/* ------------------------------------------------------------------ */
$t->group('invoice_settle() against a real database');

[$id, $username] = fixture($conn, ['expiry' => '2020-01-01', 'validity' => 30]);

$result = invoice_settle($conn, $id, 'TESTREF123');

$t->true('it reports success', $result['ok']);
$t->false('it was not already settled', $result['already']);

$inv = invoice($conn, $id);
$t->is('the invoice is marked paid', $inv['status'], 'paid');
$t->true('paid_at was recorded', !empty($inv['paid_at']));
$t->is('the gateway reference was recorded', $inv['payment_reference'], 'TESTREF123');

/* The whole point of the fix: paying must actually restore service. */
$c = customer($conn, $username);
$expected = date('Y-m-d', strtotime(date('Y-m-d')) + 30 * 86400);
$t->is('the customer expiry was extended from today, not from 2020', $c['expiry'], $expected);
$t->is('the customer was reactivated', $c['status'], 'active');
$t->is('the customer was unblocked', (int) $c['blocked'], 0);

$t->is('a RADIUS Expiration attribute was written',
    radValue($conn, 'radcheck', $username, 'Expiration'),
    date('d M Y 08:00:00', strtotime($expected)));

$t->is('the rate limit was restored',
    radValue($conn, 'radreply', $username, 'Mikrotik-Rate-Limit'), '10M/10M');

/* ------------------------------------------------------------------ */
$t->group('a replayed gateway callback must not renew twice');

/* Gateways retry. eSewa and Khalti both have a callback and a webhook
   that can fire for the same payment. If settling twice extended the
   expiry twice, every retry would be a free month. */
$second = invoice_settle($conn, $id, 'TESTREF123');

$t->true('the second call still reports success', $second['ok']);
$t->true('but reports that it was already settled', $second['already']);

$c2 = customer($conn, $username);
$t->is('the expiry did not move a second time', $c2['expiry'], $expected);

/* ------------------------------------------------------------------ */
$t->group('renewing early adds to the time remaining');

$future = date('Y-m-d', strtotime(date('Y-m-d')) + 10 * 86400);
[$id2, $user2] = fixture($conn, ['expiry' => $future, 'validity' => 30]);

invoice_settle($conn, $id2, 'REF2');

$c3 = customer($conn, $user2);
$t->is('30 days were added to the existing expiry, not to today',
    $c3['expiry'], date('Y-m-d', strtotime($future) + 30 * 86400));

/* ------------------------------------------------------------------ */
$t->group('multi-month and non-monthly plans');

[$id3, $user3] = fixture($conn, ['expiry' => '2020-01-01', 'validity' => 7, 'months' => 4]);
invoice_settle($conn, $id3, 'REF3');
$t->is('4 periods of a 7-day plan is 28 days',
    customer($conn, $user3)['expiry'],
    date('Y-m-d', strtotime(date('Y-m-d')) + 28 * 86400));

/* ------------------------------------------------------------------ */
$t->group('invoices that must not be settled');

[$id4, $user4] = fixture($conn, ['status' => 'cancelled', 'expiry' => '2020-01-01']);
$r4 = invoice_settle($conn, $id4, 'REF4');
$t->false('a cancelled invoice is refused', $r4['ok']);
$t->is('a cancelled invoice stays cancelled', invoice($conn, $id4)['status'], 'cancelled');
$t->is('and the customer is not renewed', customer($conn, $user4)['expiry'], '2020-01-01');

$r5 = invoice_settle($conn, 999999999, 'REF5');
$t->false('an invoice that does not exist is refused', $r5['ok']);
$t->is('with a clear message', $r5['message'], 'Invoice not found');

/* ------------------------------------------------------------------ */
$t->group('an invoice whose customer is gone');

/* invoices is keyed by username, with no foreign key, so a deleted
   customer leaves orphaned invoices behind. Settling one must not throw
   — the money was still received. */
[$id6, $user6] = fixture($conn, ['expiry' => '2020-01-01']);
$conn->query("DELETE FROM customers WHERE username = '" . $conn->real_escape_string($user6) . "'");

$r6 = invoice_settle($conn, $id6, 'REF6');
$t->true('it does not throw when the customer row is missing', $r6['ok']);
$t->is('the invoice is still marked paid', invoice($conn, $id6)['status'], 'paid');

/* ------------------------------------------------------------------ */
$t->group('the status ENUM rejects what billing_cron used to write');

/* scripts/billing_cron.php wrote status = 'unpaid'. Under the strict
   mode CI runs, that is an error rather than a silent coercion — which
   is exactly why the bug survived so long on installs without it. */
$threw = false;
try {
    $conn->query("INSERT INTO invoices (username, amount, months, status)
                  VALUES ('enum_probe', 1, 1, 'unpaid')");
} catch (Throwable $e) {
    $threw = true;
}
$t->true("'unpaid' is not an accepted status value", $threw);

exit($t->report());
