<?php
/**
 * Invoice lookup and payment settlement.
 *
 * WHY THIS FILE EXISTS
 *
 * The platform grew two invoice tables:
 *
 *   invoices          keyed by username, written by auto_invoice.php,
 *                     recharge.php and quick_renew.php, read by
 *                     customer/invoices.php, dashboard.php and the
 *                     accounting reports
 *   billing_invoices  keyed by customer_id, written and read only by
 *                     billing/invoices.php and billing/index.php
 *
 * eSewa and Khalti marked `billing_invoices` paid. The invoice the
 * customer actually receives lives in `invoices`, so it stayed
 * "pending" forever, and neither gateway touched the customer's expiry
 * — so a customer could pay online and still be disconnected.
 *
 * The owner confirmed `invoices` is the table the business trusts.
 * Settlement now happens here, once, against that table, and does the
 * thing a payment is actually for: extending the service.
 *
 * Everything runs in one transaction and is safe to call twice — a
 * gateway that retries its callback must not renew the customer twice.
 */

require_once __DIR__ . '/db.php';

if (!function_exists('invoice_find')) {

    /**
     * Look up an invoice and the customer it belongs to.
     *
     * `invoices` is keyed by username, so the customer id that the
     * payment endpoints need for their ownership check has to be
     * resolved through `customers`.
     *
     * @return array|null id, username, customer_id, amount, months, status
     */
    function invoice_find(mysqli $conn, int $invoiceId): ?array
    {
        $row = db_one($conn, "
            SELECT i.id,
                   i.username,
                   i.amount,
                   i.months,
                   i.status,
                   i.expiry_date,
                   c.id AS customer_id,
                   c.plan_id,
                   c.expiry AS customer_expiry
            FROM invoices i
            LEFT JOIN customers c ON c.username = i.username
            WHERE i.id = ?
        ", [$invoiceId]);

        return $row ?: null;
    }

    /**
     * Mark an invoice paid and extend the customer's service.
     *
     * Mirrors recharge.php deliberately: the same expiry arithmetic, the
     * same RADIUS Expiration format, the same rate-limit refresh. Two
     * code paths that renew a customer differently is how this class of
     * bug appears in the first place.
     *
     * @param  string $reference  the gateway's reference, for the log
     * @return array{ok:bool,message:string,already:bool}
     */
    function invoice_settle(mysqli $conn, int $invoiceId, string $reference = ''): array
    {
        $invoice = invoice_find($conn, $invoiceId);

        if (!$invoice) {
            error_log("invoice_settle: invoice $invoiceId not found");
            return ['ok' => false, 'message' => 'Invoice not found', 'already' => false];
        }

        if ($invoice['status'] === 'paid') {
            // A replayed callback. Not an error, and must not renew again.
            return ['ok' => true, 'message' => 'Invoice already settled', 'already' => true];
        }

        if ($invoice['status'] === 'cancelled') {
            error_log("invoice_settle: refusing to settle cancelled invoice $invoiceId");
            return ['ok' => false, 'message' => 'Invoice is cancelled', 'already' => false];
        }

        $username = (string) $invoice['username'];

        $conn->begin_transaction();
        try {
            // Only move it out of 'pending'. If a concurrent callback got
            // here first, this matches zero rows and we stop.
            // Use the value db_exec() returns. It closes the statement
            // before returning, so $conn->affected_rows afterwards is not
            // dependable.
            $claimed = db_exec($conn, "
                UPDATE invoices
                SET status = 'paid', paid_at = NOW(), payment_reference = ?
                WHERE id = ? AND status = 'pending'
            ", [$reference !== '' ? $reference : null, $invoiceId]);

            if ($claimed !== 1) {
                $conn->rollback();
                return ['ok' => true, 'message' => 'Invoice already settled', 'already' => true];
            }

            $newExpiry = invoice_extend_service($conn, $invoice);

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("invoice_settle: failed for invoice $invoiceId: " . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not complete the payment', 'already' => false];
        }

        if (function_exists('logActivity')) {
            logActivity('payment', "Invoice #$invoiceId settled for $username"
                . ($reference !== '' ? " (ref $reference)" : '')
                . ($newExpiry ? ", expiry now $newExpiry" : ''));
        }

        return ['ok' => true, 'message' => 'Payment applied', 'already' => false];
    }

    /**
     * Work out the new expiry date.
     *
     * Renewing early must add to the time remaining rather than throw it
     * away; renewing late must start from today rather than from the date
     * the service lapsed. Same rule as recharge.php — kept as a separate
     * pure function so it can be tested without a database, because this
     * is the arithmetic that decides what a customer paid for.
     *
     * @param string $currentExpiry Y-m-d, or '' if the customer has none
     * @param string $today         Y-m-d, defaults to today (for tests)
     */
    function invoice_new_expiry(string $currentExpiry, int $months, int $validity, string $today = ''): string
    {
        $months   = max(1, $months);
        $validity = $validity > 0 ? $validity : 30;

        $todayTs   = strtotime(($today !== '' ? $today : date('Y-m-d')) . ' 00:00:00');
        $currentTs = $currentExpiry !== '' ? strtotime($currentExpiry) : 0;

        $base = max($currentTs ?: 0, $todayTs);

        return date('Y-m-d', $base + ($validity * 86400 * $months));
    }

    /**
     * Push the customer's expiry out and refresh their RADIUS entries.
     *
     * Called inside invoice_settle()'s transaction.
     *
     * @return string|null the new expiry date, or null if nothing to do
     */
    function invoice_extend_service(mysqli $conn, array $invoice): ?string
    {
        $username = (string) $invoice['username'];

        $customer = db_one($conn, "SELECT username, expiry, plan_id FROM customers WHERE username = ?", [$username]);
        if (!$customer) {
            // An invoice for a customer who no longer exists: the invoice
            // is still paid, there is just no service to extend.
            error_log("invoice_extend_service: no customer row for '$username'");
            return null;
        }

        $months = max(1, (int) $invoice['months']);

        // Plan validity is the length of one billing period in days.
        // Default to 30 so a missing plan cannot silently grant nothing.
        $validity = (int) db_value($conn, "SELECT validity FROM plans WHERE id = ?", [(int) $customer['plan_id']], 30);
        if ($validity <= 0) {
            $validity = 30;
        }

        $newExpiry    = invoice_new_expiry((string) ($customer['expiry'] ?? ''), $months, $validity);
        $radiusExpiry = date('d M Y 08:00:00', strtotime($newExpiry));

        db_exec($conn, "UPDATE customers SET expiry = ?, status = 'active', blocked = 0 WHERE username = ?",
            [$newExpiry, $username]);

        $has = db_one($conn, "SELECT id FROM radcheck WHERE username = ? AND attribute = 'Expiration'", [$username]);
        if ($has) {
            db_exec($conn, "UPDATE radcheck SET value = ?, op = ':=' WHERE username = ? AND attribute = 'Expiration'",
                [$radiusExpiry, $username]);
        } else {
            db_exec($conn, "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)",
                [$username, $radiusExpiry]);
        }

        // Restore full speed in case a FUP rule had throttled them.
        $speed = db_value($conn, "SELECT speed FROM plans WHERE id = ?", [(int) $customer['plan_id']], '');
        if ($speed !== '') {
            db_exec($conn, "DELETE FROM radreply WHERE username = ? AND attribute = 'Mikrotik-Rate-Limit'", [$username]);
            db_exec($conn, "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)",
                [$username, $speed]);
        }

        db_exec($conn, "UPDATE invoices SET expiry_date = ? WHERE id = ?", [$newExpiry, (int) $invoice['id']]);

        return $newExpiry;
    }
}
