<?php
/**
 * Which invoice table does your accounting actually trust?
 *
 *     php scripts/compare_invoice_tables.php
 *     php scripts/compare_invoice_tables.php --months=24
 *
 * RELEASE_READINESS 3.1 is the most serious open issue in this
 * project and it cannot be fixed by writing code, because the fix
 * depends on a fact nobody has written down: which of the two
 * invoice tables holds the numbers your business believes.
 *
 *   invoices          keyed by username, column `amount`. Written by
 *                     six places: registration, recharge, quick
 *                     renew, the mobile tech API, auto_invoice and
 *                     billing_cron. This is the invoice the customer
 *                     actually receives.
 *
 *   billing_invoices  keyed by customer_id, column `total_amount`.
 *                     Read and written only by billing/index.php and
 *                     billing/invoices.php - and marked paid by the
 *                     eSewa and Khalti callbacks.
 *
 * So online payments have been settling one table while the customer
 * holds an invoice from the other.
 *
 * This script writes nothing. It prints the evidence - volumes, date
 * ranges, money, and the overlap between them - so the decision can
 * be made from what is in the database rather than from memory.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../includes/cli_db.php';

$opts = [];
foreach (array_slice((array) ($_SERVER['argv'] ?? []), 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

$months = max(1, (int) ($opts['months'] ?? 12));

$conn = cli_db_connect();

function has_table(mysqli $conn, string $name): bool
{
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($name) . "'");
    return $r !== false && $r->num_rows > 0;
}

function scalar(mysqli $conn, string $sql)
{
    $r = $conn->query($sql);
    if ($r === false) {
        return null;
    }
    $row = $r->fetch_row();
    return $row[0] ?? null;
}

function money($v): string
{
    return $v === null ? '-' : number_format((float) $v, 2);
}

function heading(string $text): void
{
    echo "\n" . $text . "\n" . str_repeat('-', strlen($text)) . "\n";
}

echo "Invoice table comparison\n";
echo str_repeat('=', 60) . "\n";
echo "Window: the last $months months.\n";

$tables = [
    'invoices' => [
        'amount' => 'amount',
        'date' => 'created_at',
        'key' => 'username',
        'paidStatus' => "'paid'",
    ],
    'billing_invoices' => [
        'amount' => 'total_amount',
        'date' => 'created_at',
        'key' => 'customer_id',
        'paidStatus' => "'paid'",
    ],
];

$summary = [];

foreach ($tables as $table => $cfg) {
    heading($table);

    if (!has_table($conn, $table)) {
        echo "  table does not exist in this database\n";
        continue;
    }

    $since = "DATE_SUB(CURDATE(), INTERVAL $months MONTH)";
    $amount = $cfg['amount'];
    $date = $cfg['date'];

    $rows = (int) scalar($conn, "SELECT COUNT(*) FROM `$table`");
    $recent = (int) scalar($conn, "SELECT COUNT(*) FROM `$table` WHERE `$date` >= $since");
    $first = scalar($conn, "SELECT MIN(`$date`) FROM `$table`");
    $last = scalar($conn, "SELECT MAX(`$date`) FROM `$table`");
    $total = scalar($conn, "SELECT SUM(`$amount`) FROM `$table` WHERE `$date` >= $since");
    $paid = scalar($conn, "SELECT SUM(`$amount`) FROM `$table` WHERE status = {$cfg['paidStatus']} AND `$date` >= $since");
    $zero = (int) scalar($conn, "SELECT COUNT(*) FROM `$table` WHERE (`$amount` IS NULL OR `$amount` = 0)");

    printf("  rows total              %s\n", number_format($rows));
    printf("  rows in window          %s\n", number_format($recent));
    printf("  first / last            %s  ..  %s\n", $first ?? '-', $last ?? '-');
    printf("  value in window         %s\n", money($total));
    printf("  of which marked paid    %s\n", money($paid));
    printf("  rows with no amount     %s%s\n", number_format($zero),
        $zero > 0 ? '   <- a renamed column reads as NULL, which sums to 0' : '');

    echo "  by status:\n";
    $r = $conn->query("SELECT status, COUNT(*) c, SUM(`$amount`) s FROM `$table` GROUP BY status ORDER BY c DESC");
    while ($row = $r->fetch_assoc()) {
        printf("      %-12s %8s rows   %14s\n",
            $row['status'] ?? 'NULL', number_format((int) $row['c']), money($row['s']));
    }

    /* Which code paths are still writing? A table nobody has written
       to for a year is not the one to migrate towards. */
    $recentDays = (int) scalar($conn, "SELECT COUNT(*) FROM `$table` WHERE `$date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    printf("  written in last 30 days %s%s\n", number_format($recentDays),
        $recentDays === 0 ? '   <- nothing is creating these any more' : '');

    $summary[$table] = [
        'rows' => $rows,
        'recent' => $recent,
        'last30' => $recentDays,
        'value' => (float) ($total ?? 0),
    ];
}

/* ---- where the money actually arrived --------------------------- */

heading('payment_transactions');

if (has_table($conn, 'payment_transactions')) {
    $completed = (int) scalar($conn, "SELECT COUNT(*) FROM payment_transactions WHERE status = 'completed'");
    $value = scalar($conn, "SELECT SUM(amount) FROM payment_transactions WHERE status = 'completed'");
    printf("  completed payments      %s worth %s\n", number_format($completed), money($value));

    /* payment_transactions.invoice_id means invoices.id. If the
       gateways were settling billing_invoices, these ids will mostly
       NOT resolve against `invoices` - which is the clearest
       available evidence of the mismatch. */
    if (has_table($conn, 'invoices')) {
        $resolve = (int) scalar($conn,
            "SELECT COUNT(*) FROM payment_transactions p
             JOIN invoices i ON i.id = p.invoice_id
             WHERE p.status = 'completed'");
        printf("  whose invoice_id exists in invoices          %s of %s\n",
            number_format($resolve), number_format($completed));
    }
    if (has_table($conn, 'billing_invoices')) {
        $resolveB = (int) scalar($conn,
            "SELECT COUNT(*) FROM payment_transactions p
             JOIN billing_invoices b ON b.id = p.invoice_id
             WHERE p.status = 'completed'");
        printf("  whose invoice_id exists in billing_invoices  %s of %s\n",
            number_format($resolveB), number_format($completed));
    }

    /* The symptom described in 3.1: money arrived, invoice still
       pending, expiry never extended. */
    if (has_table($conn, 'invoices')) {
        $stranded = (int) scalar($conn,
            "SELECT COUNT(*) FROM payment_transactions p
             JOIN invoices i ON i.id = p.invoice_id
             WHERE p.status = 'completed' AND i.status <> 'paid'");
        printf("\n  PAID BUT STILL UNSETTLED in invoices:       %s\n", number_format($stranded));
        if ($stranded > 0) {
            echo "  These customers paid and their invoice was never marked paid.\n";
            echo "  Each one is a customer who may also never have been renewed.\n";
            $r = $conn->query(
                "SELECT p.transaction_id, p.amount, p.created_at, i.id inv, i.username, i.status
                 FROM payment_transactions p
                 JOIN invoices i ON i.id = p.invoice_id
                 WHERE p.status = 'completed' AND i.status <> 'paid'
                 ORDER BY p.created_at DESC LIMIT 10");
            echo "\n      most recent 10:\n";
            while ($row = $r->fetch_assoc()) {
                printf("      %-22s %10s  %s  invoice %s (%s, %s)\n",
                    substr((string) $row['transaction_id'], 0, 22),
                    money($row['amount']), (string) $row['created_at'],
                    (string) $row['inv'], (string) $row['username'], (string) $row['status']);
            }
        }
    }
} else {
    echo "  table does not exist in this database\n";
}

/* ---- the reading ------------------------------------------------ */

heading('What this suggests');

if (count($summary) < 2) {
    echo "  Only one of the two tables exists here, which settles it:\n";
    echo "  there is nothing to choose between.\n";
} else {
    $a = $summary['invoices'];
    $b = $summary['billing_invoices'];

    if ($a['last30'] > 0 && $b['last30'] === 0) {
        echo "  Only `invoices` is still being written to. Repointing the\n";
        echo "  gateways at `invoices` is the smaller and safer change;\n";
        echo "  `billing_invoices` becomes a read-only historical record.\n";
    } elseif ($b['last30'] > 0 && $a['last30'] === 0) {
        echo "  Only `billing_invoices` is still being written to, which\n";
        echo "  contradicts the six INSERT sites found in the code. Check\n";
        echo "  whether those pages are actually reachable before acting.\n";
    } elseif ($a['last30'] > 0 && $b['last30'] > 0) {
        echo "  Both tables are actively written. That is the worst case:\n";
        echo "  two systems of record disagreeing in real time. Decide by\n";
        echo "  which figures your accounts were filed from, not by row\n";
        echo "  counts - and reconcile the window before switching.\n";
    } else {
        echo "  Neither table has been written to in 30 days. Either this\n";
        echo "  is not production data, or invoicing has stopped.\n";
    }

    printf("\n  invoices:         %s rows, %s in window, %s in last 30 days\n",
        number_format($a['rows']), number_format($a['recent']), number_format($a['last30']));
    printf("  billing_invoices: %s rows, %s in window, %s in last 30 days\n",
        number_format($b['rows']), number_format($b['recent']), number_format($b['last30']));
}

echo "\n  Nothing was modified. Record the answer in RELEASE_READINESS.md\n";
echo "  section 3.1 so the next person does not have to ask again.\n";

exit(0);
