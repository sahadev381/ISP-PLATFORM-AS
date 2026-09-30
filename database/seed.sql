-- =====================================================================
--  ISP Platform - minimal seed data
-- =====================================================================
--
--  Enough rows to log in and see a working UI after a fresh install.
--  Run after database/schema.sql:
--
--      mysql -u root -p isp_platform < database/seed.sql
--
--  No admin account is created here on purpose. Shipping a known
--  password hash in version control is exactly the problem this audit
--  found elsewhere in the project. Create the first account with:
--
--      php scripts/create_admin.php
--
--  which prompts for the password and stores it with password_hash().
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO branches (id, name, code, address, phone, status) VALUES
    (1, 'Head Office', 'HO', 'Kathmandu', '01-0000000', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO roles (id, name, description) VALUES
    (1, 'superadmin', 'Full access to every branch'),
    (2, 'manager',    'Full access within its own branch'),
    (3, 'support',    'Read and operate within its own branch')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO plans (id, name, price, speed, validity, data_limit) VALUES
    (1, 'Home 20 Mbps',  1200.00, '20 Mbps', 30, NULL),
    (2, 'Home 50 Mbps',  1800.00, '50 Mbps', 30, NULL),
    (3, 'Office 100 Mbps', 4500.00, '100 Mbps', 30, NULL)
ON DUPLICATE KEY UPDATE price = VALUES(price);

INSERT INTO billing_cycles (id, name, days) VALUES
    (1, 'Monthly', 30),
    (2, 'Quarterly', 90),
    (3, 'Yearly', 365)
ON DUPLICATE KEY UPDATE days = VALUES(days);

-- includes/auth.php reads these two on every request; without them it
-- silently falls back to 30 and 15 minutes.
INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('session_timeout',      '30'),
    ('session_idle_timeout', '15'),
    ('auto_invoice_day',     '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- system_config.php does "SELECT * FROM system_config LIMIT 1", so it
-- needs exactly one row to exist.
INSERT INTO system_config (id, config_key, config_value, expire_block_time) VALUES
    (1, 'general', NULL, '00:00:00')
ON DUPLICATE KEY UPDATE config_key = VALUES(config_key);

INSERT INTO hotspot_settings (setting_key, setting_value) VALUES
    ('portal_title',     'Hotspot Portal'),
    ('session_timeout',  '3600'),
    ('otp_expiry',       '300'),
    ('captive_bg_type',  'bg-gradient')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO kb_categories (id, name) VALUES
    (1, 'Installation'),
    (2, 'Troubleshooting'),
    (3, 'Billing')
ON DUPLICATE KEY UPDATE name = VALUES(name);
