-- 003_read_only_columns.sql
--
-- Two columns that the application reads but never writes.
--
-- BACKGROUND
--
-- database/schema.sql was reconstructed by reading the application's
-- INSERT and UPDATE statements. That finds every column the code
-- writes and no column it only reads, so a column used solely in a
-- SELECT, a WHERE or an ORDER BY was invisible to the exercise - and
-- is equally invisible to scripts/check_schema.php, which works the
-- same way.
--
-- Both were found by the 'Every page loads' CI job, which requests
-- each page against a database built from schema.sql. Both pages
-- returned HTTP 500.
--
--   knowledge_base.php:37
--       SELECT * FROM kb_categories ORDER BY sort_order
--       -> Unknown column 'sort_order' in 'order clause'
--
--   HotspotPlanManager::getAllVoucherTypes()
--       SELECT * FROM hotspot_voucher_types WHERE status = ?
--       -> Unknown column 'status' in 'where clause'
--
-- These are plain ALTERs rather than conditional ones. MySQL has no
-- ADD COLUMN IF NOT EXISTS, and emulating it needs PREPARE/EXECUTE
-- over a user variable, which is more machinery than a migration
-- should carry. The runner records what it has applied, so this runs
-- once; an install that already added these by hand should mark it
-- applied rather than run it:
--
--     INSERT INTO schema_migrations (version)
--     VALUES ('003_read_only_columns.sql');

ALTER TABLE kb_categories
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0;

ALTER TABLE hotspot_voucher_types
    ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    ADD KEY idx_hotspot_voucher_types_status (status);
