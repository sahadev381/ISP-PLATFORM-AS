-- 001_baseline.sql
--
-- Marks the schema as it stood at v0.9.0.
--
-- This migration intentionally does almost nothing. Existing installs
-- already have these tables; new installs get them from
-- database/schema.sql. Its purpose is to give every database a common
-- starting point so that migration 002 onwards can assume it.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version    VARCHAR(255) NOT NULL,
    applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
