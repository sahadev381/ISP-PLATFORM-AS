<?php
/**
 * Read-only report on what moving payment settlement from
 * `billing_invoices` to `invoices` means for THIS install's data.
 *
 *     php scripts/check_invoice_migration.php
 *
 * Changes nothing. Run it before applying migration 002.
 *
 * payment_transactions.invoice_id used to refer to billing_invoices.id
 * and now refers to invoices.id. The two tables share no key, so no
 * automatic remapping is possible or attempted — guessing is not
 * acceptable for payment records. This tells you how much history is
 * affected so you can decide what to do about it.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../includes/cli_db.php';
$conn = cli_db_connect();

function count_of(mysqli $conn, string $sql): int
{
    $res = $conn->query($sql);
    if (!$res) {
        return -1;
    }
    return (int) ($res->fetch_row()[0] ?? 0);
}

$tableExists = function (mysqli $conn, string $name): bool {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($name) . "'");
    return $res && $res->num_rows > 0;
};

echo "Invoice settlement migration — data report\n";
echo str_repeat('=', 46) . "\n\n";

echo "Row counts\n";
printf("  invoices                        %6d\n", count_of($conn, "SELECT COUNT(*) FROM invoices"));

if ($tableExists($conn, 'billing_invoices')) {
    printf("  billing_invoices                %6d\n", count_of($conn, "SELECT COUNT(*) FROM billing_invoices"));
} else {
    echo "  billing_invoices                 absent\n";
}

printf("  payment_transactions            %6d\n", count_of($conn, "SELECT COUNT(*) FROM payment_transactions"));

echo "\nHistorical transactions affected\n";

$withInvoice = count_of($conn, "SELECT COUNT(*) FROM payment_transactions WHERE invoice_id IS NOT NULL AND invoice_id > 0");
printf("  transactions carrying an invoice_id   %6d\n", $withInvoice);

if ($withInvoice > 0) {
    $resolvable = count_of($conn, "
        SELECT COUNT(*) FROM payment_transactions t
        JOIN invoices i ON i.id = t.invoice_id
        WHERE t.invoice_id IS NOT NULL AND t.invoice_id > 0
    ");
    printf("  ... that resolve against `invoices`   %6d\n", $resolvable);
    printf("  ... that now point at nothing         %6d\n", $withInvoice - $resolvable);

    if ($withInvoice - $resolvable > 0) {
        echo "\n  Those transactions were recorded against billing_invoices ids.\n";
        echo "  After migration 002 their invoice_id is meaningless. The\n";
        echo "  payment records themselves are intact - only the link to an\n";
        echo "  invoice is lost. No remapping is attempted because the two\n";
        echo "  tables share no key.\n";
    }
}

echo "\nInvoices that online payment could not settle before\n";
$pending = count_of($conn, "SELECT COUNT(*) FROM invoices WHERE status = 'pending'");
printf("  invoices still pending                %6d\n", $pending);

$broken = count_of($conn, "SELECT COUNT(*) FROM invoices WHERE status = ''");
if ($broken > 0) {
    printf("  invoices with an empty status         %6d\n", $broken);
    echo "  These came from billing_cron.php writing 'unpaid', which the\n";
    echo "  ENUM does not accept. Migration 002 recovers them as pending.\n";
}

if ($tableExists($conn, 'billing_invoices')) {
    $bPaid = count_of($conn, "SELECT COUNT(*) FROM billing_invoices WHERE status = 'paid'");
    printf("\n  billing_invoices marked paid          %6d\n", $bPaid);
    if ($bPaid > 0) {
        echo "  Each of these was an online payment that settled the wrong\n";
        echo "  table. The customer's invoice in `invoices` was left pending\n";
        echo "  and their expiry was never extended. Worth reconciling by\n";
        echo "  hand against the customers involved.\n";
    }
}

echo "\nNothing was changed by this script.\n";
