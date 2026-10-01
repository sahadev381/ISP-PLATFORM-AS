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
-- to scripts/check_schema.php, which works the same way.
--
-- Both of these were found by the 'Every page loads' CI job, which
-- requests each page against a database built from schema.sql. The
-- pages returned HTTP 500.
--
--   knowledge_base.php:37
--       SELECT * FROM kb_categories ORDER BY sort_order
--       -> Unknown column 'sort_order' in 'order clause'
--
--   HotspotPlanManager::getAllVoucherTypes()
--       SELECT * FROM hotspot_voucher_types WHERE status = ?
--       -> Unknown column 'status' in 'where clause'
--
-- An install whose database predates the reconstruction may already
-- have these columns, which is why both statements tolerate that.

-- MySQL has no ADD COLUMN IF NOT EXISTS, so check first.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'kb_categories'
             AND COLUMN_NAME = 'sort_order');
SET @sql := IF(@c = 0,
    'ALTER TABLE kb_categories ADD COLUMN sort_order INT NOT NULL DEFAULT 0',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'hotspot_voucher_types'
             AND COLUMN_NAME = 'status');
SET @sql := IF(@c = 0,
    'ALTER TABLE hotspot_voucher_types ADD COLUMN status ENUM(''active'',''inactive'') NOT NULL DEFAULT ''active'', ADD KEY idx_hotspot_voucher_types_status (status)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
