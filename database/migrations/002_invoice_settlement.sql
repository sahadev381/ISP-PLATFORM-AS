-- 002_invoice_settlement.sql
--
-- Supports the move of online payment settlement from
-- `billing_invoices` to `invoices`.
--
-- BACKGROUND
--
-- eSewa and Khalti used to mark `billing_invoices` paid. The invoice a
-- customer actually receives lives in `invoices`, so it was never
-- settled, and neither gateway extended the customer's expiry: a
-- customer could pay online and still be disconnected.
--
-- payment_transactions.invoice_id now refers to `invoices.id`.
--
-- IMPORTANT, READ BEFORE RUNNING
--
-- If this install has existing rows in payment_transactions whose
-- invoice_id pointed at billing_invoices, those ids now mean something
-- different. Run
--
--     php scripts/check_invoice_migration.php
--
-- BEFORE applying this. It is read-only and reports how many historical
-- transactions are affected.
--
-- No automatic remapping is attempted. The two tables have no shared
-- key, so any mapping would be a guess, and guessing is not acceptable
-- for payment records.

-- NOTE ON THE DEFAULT
--
-- `invoices.status` defaults to 'paid', which looks wrong for an
-- invoice that has just been raised. It is deliberately NOT changed
-- here: recharge.php and mobile_tech_api.php insert without naming the
-- column, so flipping the default would silently turn every
-- admin-performed renewal into an unpaid invoice. The paths that raise
-- an invoice to be paid later (auto_invoice.php, billing_cron.php) all
-- state 'pending' explicitly.
--
-- Changing this safely means making those two INSERTs explicit first,
-- in a release of their own.

-- Add a paid_at so a payment can be distinguished from an invoice that
-- was raised as already paid.
ALTER TABLE invoices
    ADD COLUMN paid_at DATETIME DEFAULT NULL AFTER status;

-- Record which gateway reference settled an invoice, so a payment can
-- be traced back from the invoice rather than only forwards from the
-- transaction.
ALTER TABLE invoices
    ADD COLUMN payment_reference VARCHAR(120) DEFAULT NULL AFTER paid_at;

-- scripts/billing_cron.php inserted status = 'unpaid', which is not one
-- of the values the ENUM accepts. On installs not running strict mode,
-- MySQL coerced that to the empty string, producing invoices that match
-- neither 'paid' nor 'pending' and are therefore invisible to every
-- report and to the customer portal. Recover them as pending.
UPDATE invoices SET status = 'pending' WHERE status = '';
