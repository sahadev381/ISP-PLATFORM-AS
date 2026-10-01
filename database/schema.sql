-- =====================================================================
--  ISP Platform - database schema
-- =====================================================================
--
--  This file was reconstructed from the queries in the application. The
--  project had no schema in version control, so a fresh clone could not
--  be run at all: every page died on the first query.
--
--  How it was built: every INSERT column list, UPDATE ... SET clause and
--  qualified column reference in the PHP was collected, then the types
--  were inferred from how each column is used (bound as an int, compared
--  with CURDATE(), formatted as money, and so on).
--
--  Read this as a starting point, not as a dump of a known-good
--  production database. Column types are best-effort. If you already run
--  this application, diff your live database against this file rather
--  than replacing it.
--
--  Usage:
--      mysql -u root -p -e "CREATE DATABASE isp_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--      mysql -u root -p isp_platform < database/schema.sql
--      mysql -u root -p isp_platform < database/seed.sql   # optional demo data
--
--  The FreeRADIUS tables (radacct, radcheck, radreply, radusergroup,
--  radpostauth) follow the upstream FreeRADIUS 3.x MySQL layout, because
--  radiusd owns them and expects exactly those definitions.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';


-- =====================================================================
--  1. Tenancy, staff and authentication
-- =====================================================================

CREATE TABLE IF NOT EXISTS branches (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120) NOT NULL,
    code        VARCHAR(40)  DEFAULT NULL,
    address     VARCHAR(255) DEFAULT NULL,
    phone       VARCHAR(40)  DEFAULT NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_branches_code (code),
    KEY idx_branches_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff accounts. `role` drives includes/rbac.php; only these three
-- values are recognised, anything else is treated as having no rights.
CREATE TABLE IF NOT EXISTS admins (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username    VARCHAR(80)  NOT NULL,
    password    VARCHAR(255) NOT NULL COMMENT 'password_hash(), never plaintext',
    role        ENUM('superadmin','manager','support') NOT NULL DEFAULT 'support',
    branch_id   INT UNSIGNED DEFAULT NULL COMMENT 'NULL only for superadmin',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username),
    KEY idx_admins_branch (branch_id),
    CONSTRAINT fk_admins_branch FOREIGN KEY (branch_id)
        REFERENCES branches (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kept for the roles/permissions screens. rbac.php does not read these
-- yet; the enum on admins.role is the live source of truth.
CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60)  NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id     INT UNSIGNED NOT NULL,
    permission  VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_permission (role_id, permission),
    CONSTRAINT fk_roleperm_role FOREIGN KEY (role_id)
        REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username     VARCHAR(80)  DEFAULT NULL,
    ip_address   VARCHAR(45)  DEFAULT NULL COMMENT 'IPv6-capable',
    user_agent   VARCHAR(255) DEFAULT NULL,
    status       ENUM('success','failed','blocked') NOT NULL DEFAULT 'failed',
    attempt_time DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_user_time (username, attempt_time),
    KEY idx_login_attempts_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED DEFAULT NULL,
    username    VARCHAR(80)  DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    description TEXT,
    details     TEXT,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_user (user_id),
    KEY idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  2. Plans and customers
-- =====================================================================

CREATE TABLE IF NOT EXISTS plans (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120)   NOT NULL,
    price       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    speed       VARCHAR(40)    DEFAULT NULL COMMENT 'display value, e.g. "20 Mbps"',
    validity    INT UNSIGNED   NOT NULL DEFAULT 30 COMMENT 'days',
    data_limit  BIGINT UNSIGNED DEFAULT NULL COMMENT 'bytes; NULL or 0 means unlimited',
    -- Three-step fair-use policy, applied by scripts/fup_speed.php.
    fup1_limit  BIGINT UNSIGNED DEFAULT NULL,
    fup1_speed  VARCHAR(40)     DEFAULT NULL,
    fup2_limit  BIGINT UNSIGNED DEFAULT NULL,
    fup2_speed  VARCHAR(40)     DEFAULT NULL,
    fup3_limit  BIGINT UNSIGNED DEFAULT NULL,
    fup3_speed  VARCHAR(40)     DEFAULT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_plans_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The central table. `username` is the join key used by the RADIUS
-- tables and by most reports, so it is unique and indexed everywhere.
CREATE TABLE IF NOT EXISTS customers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(80)  NOT NULL,
    password        VARCHAR(255) NOT NULL COMMENT 'PPPoE secret, mirrored into radcheck',
    full_name       VARCHAR(160) DEFAULT NULL,
    phone           VARCHAR(40)  DEFAULT NULL,
    email           VARCHAR(160) DEFAULT NULL,
    address         VARCHAR(255) DEFAULT NULL,
    lat             DECIMAL(10,7) DEFAULT NULL,
    lng             DECIMAL(10,7) DEFAULT NULL,
    plan_id         INT UNSIGNED DEFAULT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL COMMENT 'drives branch_scope() in rbac.php',
    expiry          DATE         DEFAULT NULL,
    status          ENUM('active','expired','blocked','suspended') NOT NULL DEFAULT 'active',
    blocked         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'set by cron_block_expired.php',
    wallet          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    -- FTTH / ONU provisioning details
    onu_serial      VARCHAR(64)  DEFAULT NULL,
    onu_mac         VARCHAR(32)  DEFAULT NULL,
    vlan            VARCHAR(20)  DEFAULT NULL,
    wifi_ssid       VARCHAR(64)  DEFAULT NULL,
    wifi_password   VARCHAR(128) DEFAULT NULL,
    olt             VARCHAR(80)  DEFAULT NULL,
    olt_port        VARCHAR(40)  DEFAULT NULL,
    master_box      VARCHAR(80)  DEFAULT NULL,
    db_box          VARCHAR(80)  DEFAULT NULL,
    db_port         VARCHAR(40)  DEFAULT NULL,
    tr069_device_id VARCHAR(128) DEFAULT NULL COMMENT 'GenieACS device id',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_username (username),
    KEY idx_customers_branch (branch_id),
    KEY idx_customers_plan (plan_id),
    KEY idx_customers_status_expiry (status, expiry),
    KEY idx_customers_expiry (expiry),
    KEY idx_customers_phone (phone),
    CONSTRAINT fk_customers_branch FOREIGN KEY (branch_id)
        REFERENCES branches (id) ON DELETE SET NULL,
    CONSTRAINT fk_customers_plan FOREIGN KEY (plan_id)
        REFERENCES plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rolling quota counter maintained by the FUP scripts.
CREATE TABLE IF NOT EXISTS data_usage (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(80) NOT NULL,
    plan_id    INT UNSIGNED DEFAULT NULL,
    used_quota BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'bytes since fup_reset',
    fup_reset  DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_usage_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_logs (
    id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    usage_mb BIGINT UNSIGNED NOT NULL DEFAULT 0,
    logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usage_logs_username (username, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(160) NOT NULL,
    phone           VARCHAR(40)  DEFAULT NULL,
    email           VARCHAR(160) DEFAULT NULL,
    company         VARCHAR(160) DEFAULT NULL,
    address         VARCHAR(255) DEFAULT NULL,
    plan_interested VARCHAR(120) DEFAULT NULL,
    source          VARCHAR(80)  DEFAULT NULL,
    status          ENUM('new','contacted','qualified','proposal','converted','lost')
                    NOT NULL DEFAULT 'new',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_leads_status (status),
    KEY idx_leads_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  3. FreeRADIUS
--  Upstream FreeRADIUS 3.x MySQL layout - radiusd requires these exact
--  column names. Do not "tidy" them.
-- =====================================================================

CREATE TABLE IF NOT EXISTS radcheck (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username  VARCHAR(64)  NOT NULL DEFAULT '',
    attribute VARCHAR(64)  NOT NULL DEFAULT '',
    op        CHAR(2)      NOT NULL DEFAULT '==',
    value     VARCHAR(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radreply (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username  VARCHAR(64)  NOT NULL DEFAULT '',
    attribute VARCHAR(64)  NOT NULL DEFAULT '',
    op        CHAR(2)      NOT NULL DEFAULT '=',
    value     VARCHAR(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radusergroup (
    username  VARCHAR(64) NOT NULL DEFAULT '',
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    priority  INT(11)     NOT NULL DEFAULT 1,
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radacct (
    radacctid           BIGINT(21)  NOT NULL AUTO_INCREMENT,
    acctsessionid       VARCHAR(64) NOT NULL DEFAULT '',
    acctuniqueid        VARCHAR(32) NOT NULL DEFAULT '',
    username            VARCHAR(64) NOT NULL DEFAULT '',
    realm               VARCHAR(64) DEFAULT '',
    nasipaddress        VARCHAR(15) NOT NULL DEFAULT '',
    nasportid           VARCHAR(32) DEFAULT NULL,
    nasporttype         VARCHAR(32) DEFAULT NULL,
    acctstarttime       DATETIME    NULL DEFAULT NULL,
    acctupdatetime      DATETIME    NULL DEFAULT NULL,
    acctstoptime        DATETIME    NULL DEFAULT NULL,
    acctinterval        INT(12)     DEFAULT NULL,
    acctsessiontime     INT UNSIGNED DEFAULT NULL,
    acctauthentic       VARCHAR(32) DEFAULT NULL,
    connectinfo_start   VARCHAR(50) DEFAULT NULL,
    connectinfo_stop    VARCHAR(50) DEFAULT NULL,
    acctinputoctets     BIGINT(20)  DEFAULT NULL,
    acctoutputoctets    BIGINT(20)  DEFAULT NULL,
    calledstationid     VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid    VARCHAR(50) NOT NULL DEFAULT '',
    acctterminatecause  VARCHAR(32) NOT NULL DEFAULT '',
    servicetype         VARCHAR(32) DEFAULT NULL,
    framedprotocol      VARCHAR(32) DEFAULT NULL,
    framedipaddress     VARCHAR(15) NOT NULL DEFAULT '',
    framedipv6address   VARCHAR(45) NOT NULL DEFAULT '',
    framedipv6prefix    VARCHAR(45) NOT NULL DEFAULT '',
    framedinterfaceid   VARCHAR(44) NOT NULL DEFAULT '',
    delegatedipv6prefix VARCHAR(45) NOT NULL DEFAULT '',
    PRIMARY KEY (radacctid),
    UNIQUE KEY acctuniqueid (acctuniqueid),
    KEY username (username),
    KEY framedipaddress (framedipaddress),
    KEY acctsessionid (acctsessionid),
    KEY acctsessiontime (acctsessiontime),
    KEY acctstarttime (acctstarttime),
    KEY acctstoptime (acctstoptime),
    KEY nasipaddress (nasipaddress),
    -- The dashboards repeatedly ask "who is online right now", which is
    -- WHERE acctstoptime IS NULL. Without this they table-scan radacct.
    KEY idx_radacct_online (acctstoptime, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radpostauth (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64)  NOT NULL DEFAULT '',
    pass     VARCHAR(64)  NOT NULL DEFAULT '',
    reply    VARCHAR(32)  NOT NULL DEFAULT '',
    authdate TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NAS/device inventory. FreeRADIUS reads nasname/secret/shortname/type;
-- the extra columns are this application's own device management.
CREATE TABLE IF NOT EXISTS nas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nasname        VARCHAR(128) NOT NULL COMMENT 'IP or hostname, RADIUS client id',
    shortname      VARCHAR(32)  DEFAULT NULL,
    type           VARCHAR(30)  DEFAULT 'other',
    ports          INT(5)       DEFAULT NULL,
    secret         VARCHAR(60)  NOT NULL DEFAULT 'secret',
    server         VARCHAR(64)  DEFAULT NULL,
    community      VARCHAR(50)  DEFAULT NULL,
    description    VARCHAR(200) DEFAULT 'RADIUS Client',
    -- application-specific device management
    device_type    ENUM('mikrotik','olt','switch','other') NOT NULL DEFAULT 'mikrotik',
    brand          VARCHAR(60)  DEFAULT 'generic',
    model          VARCHAR(80)  DEFAULT NULL,
    ip_address     VARCHAR(45)  DEFAULT NULL,
    api_user       VARCHAR(80)  DEFAULT NULL,
    api_pass       VARCHAR(160) DEFAULT NULL,
    api_port       SMALLINT UNSIGNED DEFAULT 8728,
    snmp_community VARCHAR(80)  DEFAULT NULL,
    snmp_version   ENUM('1','2c','3') NOT NULL DEFAULT '2c',
    pon_ports      SMALLINT UNSIGNED DEFAULT NULL,
    lat            DECIMAL(10,7) DEFAULT NULL,
    lng            DECIMAL(10,7) DEFAULT NULL,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    KEY nasname (nasname),
    KEY idx_nas_device_type (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  4. Billing
--  Note: `invoices` and `billing_invoices` are two different tables with
--  two different shapes, used by invoices.php and billing/invoices.php
--  respectively. They are duplicates in spirit and should eventually be
--  merged, but both are kept here so the existing pages keep working.
-- =====================================================================

CREATE TABLE IF NOT EXISTS invoices (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username    VARCHAR(80)   NOT NULL,
    amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'gross',
    base_amount DECIMAL(10,2) DEFAULT NULL,
    vat_amount  DECIMAL(10,2) DEFAULT NULL,
    tsc_amount  DECIMAL(10,2) DEFAULT NULL COMMENT 'telecom service charge',
    months      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    expiry_date DATE          DEFAULT NULL,
    status      ENUM('paid','pending','cancelled') NOT NULL DEFAULT 'paid',
    paid_at     DATETIME      DEFAULT NULL,
    payment_reference VARCHAR(120) DEFAULT NULL COMMENT 'gateway reference that settled it',
    admin       VARCHAR(80)   DEFAULT NULL COMMENT 'username of the staff member',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_invoices_username (username),
    KEY idx_invoices_created (created_at),
    KEY idx_invoices_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_invoices (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number VARCHAR(40)   NOT NULL,
    customer_id    INT UNSIGNED  NOT NULL,
    issue_date     DATE          NOT NULL,
    due_date       DATE          DEFAULT NULL,
    subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status         ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
    paid_at        DATETIME      DEFAULT NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_billing_invoice_number (invoice_number),
    KEY idx_billing_invoices_customer (customer_id),
    KEY idx_billing_invoices_status (status),
    CONSTRAINT fk_billing_invoices_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_subscriptions (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id       INT UNSIGNED NOT NULL,
    plan_id           INT UNSIGNED NOT NULL,
    billing_cycle_id  INT UNSIGNED DEFAULT NULL,
    status            ENUM('active','pending','suspended','cancelled') NOT NULL DEFAULT 'active',
    next_billing_date DATE         DEFAULT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_subs_customer (customer_id),
    KEY idx_subs_status (status),
    KEY idx_subs_next_billing (next_billing_date),
    CONSTRAINT fk_subs_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_subs_plan FOREIGN KEY (plan_id)
        REFERENCES plans (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_gateways (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    gateway_name VARCHAR(60)  NOT NULL COMMENT 'esewa, khalti, ...',
    display_name VARCHAR(120) DEFAULT NULL,
    api_key      VARCHAR(255) DEFAULT NULL COMMENT 'secret - never render this',
    api_secret   VARCHAR(255) DEFAULT NULL COMMENT 'secret - never render this',
    merchant_id  VARCHAR(120) DEFAULT NULL,
    public_key   VARCHAR(255) DEFAULT NULL,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    is_test_mode TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gateway_name (gateway_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id   VARCHAR(80)   NOT NULL COMMENT 'our reference',
    reference_id     VARCHAR(120)  DEFAULT NULL COMMENT 'gateway reference / idx',
    gateway_id       INT UNSIGNED  DEFAULT NULL,
    invoice_id       BIGINT UNSIGNED DEFAULT NULL,
    customer_id      INT UNSIGNED  DEFAULT NULL,
    customer_name    VARCHAR(160)  DEFAULT NULL,
    customer_email   VARCHAR(160)  DEFAULT NULL,
    customer_phone   VARCHAR(40)   DEFAULT NULL,
    amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status           ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_method   VARCHAR(40)   DEFAULT NULL,
    gateway_response TEXT,
    notes            VARCHAR(255)  DEFAULT NULL,
    verified_at      DATETIME      DEFAULT NULL,
    completed_at     DATETIME      DEFAULT NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_txn_id (transaction_id),
    -- A gateway reference must never be credited twice. khalti_verify.php
    -- relied on application code alone, which is replayable under
    -- concurrency; this makes the database enforce it.
    UNIQUE KEY uq_txn_gateway_reference (gateway_id, reference_id),
    KEY idx_txn_customer (customer_id),
    KEY idx_txn_status (status),
    KEY idx_txn_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(80)   NOT NULL,
    amount     DECIMAL(10,2) NOT NULL,
    gateway    VARCHAR(40)   DEFAULT NULL COMMENT 'khalti, esewa, cash, ...',
    txn_id     VARCHAR(120)  DEFAULT NULL,
    status     ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    start_date DATE          DEFAULT NULL,
    end_date   DATE          DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wallet_gateway_txn (gateway, txn_id),
    KEY idx_wallet_username (username),
    KEY idx_wallet_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_cycles (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(60)  NOT NULL COMMENT 'Monthly, Quarterly, ...',
    days INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_billing_cycle_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_history (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id    INT UNSIGNED  NOT NULL,
    billing_date   DATE          NOT NULL,
    amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status         ENUM('paid','pending','failed') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(40)   DEFAULT NULL,
    transaction_id VARCHAR(80)   DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_billing_history_customer (customer_id, billing_date),
    CONSTRAINT fk_billing_history_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual top-ups recorded by recharge.php and the technician app. This
-- overlaps with `invoices`; both are written on renewal.
CREATE TABLE IF NOT EXISTS recharge (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(80)   NOT NULL,
    amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    months     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recharge_username (username),
    KEY idx_recharge_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auto_invoice_log (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    month_year     VARCHAR(10) NOT NULL COMMENT 'YYYY-MM',
    total_invoices INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auto_invoice_month (month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  5. Support: tickets, knowledge base, work diary
-- =====================================================================

CREATE TABLE IF NOT EXISTS tickets (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id    INT UNSIGNED DEFAULT NULL,
    branch_id      INT UNSIGNED DEFAULT NULL COMMENT 'drives branch_scope() in rbac.php',
    admin_id       INT UNSIGNED DEFAULT NULL COMMENT 'assigned technician',
    subject        VARCHAR(200) NOT NULL,
    message        TEXT,
    category       VARCHAR(60)  DEFAULT 'Support',
    priority       ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
    status         ENUM('Open','In Progress','Closed') NOT NULL DEFAULT 'Open',
    download_speed VARCHAR(40)  DEFAULT NULL COMMENT 'captured during diagnosis',
    upload_speed   VARCHAR(40)  DEFAULT NULL,
    signature      MEDIUMTEXT   COMMENT 'base64 job-completion signature',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tickets_branch (branch_id),
    KEY idx_tickets_customer (customer_id),
    KEY idx_tickets_status (status),
    KEY idx_tickets_created (created_at),
    CONSTRAINT fk_tickets_branch FOREIGN KEY (branch_id)
        REFERENCES branches (id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_replies (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id  BIGINT UNSIGNED NOT NULL,
    sender     ENUM('Admin','Customer') NOT NULL DEFAULT 'Admin',
    message    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_replies_ticket (ticket_id, created_at),
    CONSTRAINT fk_replies_ticket FOREIGN KEY (ticket_id)
        REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time codes the technician reads out to close a job on site.
CREATE TABLE IF NOT EXISTS job_otps (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id  BIGINT UNSIGNED NOT NULL,
    otp        VARCHAR(10) NOT NULL,
    used       TINYINT(1)  NOT NULL DEFAULT 0,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_otps_ticket (ticket_id),
    CONSTRAINT fk_job_otps_ticket FOREIGN KEY (ticket_id)
        REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kb_categories (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kb_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_base (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(200) NOT NULL,
    category   VARCHAR(80)  DEFAULT NULL,
    content    MEDIUMTEXT,
    is_public  TINYINT(1)   NOT NULL DEFAULT 0,
    views      INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kb_category (category),
    KEY idx_kb_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_diary (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id   INT UNSIGNED NOT NULL,
    category   VARCHAR(60)  DEFAULT NULL,
    title      VARCHAR(200) NOT NULL,
    content    TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diary_admin (admin_id),
    KEY idx_diary_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diary_comments (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    diary_id   BIGINT UNSIGNED NOT NULL,
    admin_id   INT UNSIGNED NOT NULL,
    comment    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diary_comments_diary (diary_id, created_at),
    CONSTRAINT fk_diary_comments_diary FOREIGN KEY (diary_id)
        REFERENCES work_diary (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  6. Network: monitoring, FTTH plant, topology
-- =====================================================================

CREATE TABLE IF NOT EXISTS devices (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    type         ENUM('ping','http','snmp') NOT NULL DEFAULT 'ping',
    status       ENUM('UP','DOWN') NOT NULL DEFAULT 'UP',
    fail_count   INT UNSIGNED NOT NULL DEFAULT 0,
    last_checked DATETIME     DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_devices_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uptime_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type VARCHAR(30) NOT NULL COMMENT 'device, nas, ...',
    target_id   INT UNSIGNED NOT NULL,
    status      ENUM('UP','DOWN') NOT NULL,
    latency_ms  INT UNSIGNED DEFAULT NULL,
    checked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_uptime_target (target_type, target_id, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS performance_metrics (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type  VARCHAR(30)  NOT NULL,
    target_id    INT UNSIGNED NOT NULL,
    metric_type  VARCHAR(40)  NOT NULL COMMENT 'cpu, memory, ...',
    metric_value DOUBLE       NOT NULL,
    recorded_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_perf_target (target_type, target_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS snmp_metrics (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id    INT UNSIGNED NOT NULL,
    device_ip    VARCHAR(45)  DEFAULT NULL,
    metric_type  VARCHAR(40)  NOT NULL,
    metric_value DOUBLE       NOT NULL,
    recorded_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_snmp_device_time (device_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS network_alerts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id   INT UNSIGNED DEFAULT NULL,
    device_name VARCHAR(120) DEFAULT NULL,
    alert_type  VARCHAR(60)  DEFAULT NULL,
    severity    ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    message     TEXT,
    status      ENUM('active','resolved') NOT NULL DEFAULT 'active',
    resolved_at DATETIME     DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alerts_status_severity (status, severity),
    KEY idx_alerts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS network_faults (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    node_id       INT UNSIGNED DEFAULT NULL,
    fault_type    VARCHAR(60)  DEFAULT NULL,
    severity      ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    description   TEXT,
    predicted_lat DECIMAL(10,7) DEFAULT NULL,
    predicted_lng DECIMAL(10,7) DEFAULT NULL,
    is_resolved   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_faults_resolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS network_topology_links (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_device_id INT UNSIGNED NOT NULL,
    to_device_id   INT UNSIGNED NOT NULL,
    cable_name     VARCHAR(120) DEFAULT NULL,
    cable_type     VARCHAR(40)  DEFAULT NULL COMMENT 'fiber, copper, ...',
    PRIMARY KEY (id),
    KEY idx_topology_from (from_device_id),
    KEY idx_topology_to (to_device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fibre plant, drawn on map.php with Leaflet.
CREATE TABLE IF NOT EXISTS ftth_nodes (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name     VARCHAR(120) NOT NULL,
    type     VARCHAR(40)  NOT NULL COMMENT 'OLT, SERVER, splitter, db_box, ...',
    lat      DECIMAL(10,7) DEFAULT NULL,
    lng      DECIMAL(10,7) DEFAULT NULL,
    capacity INT UNSIGNED  DEFAULT NULL,
    metadata JSON          DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_ftth_nodes_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiber_routes (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(120) NOT NULL,
    route_type          VARCHAR(40)  DEFAULT NULL,
    path_data           JSON         DEFAULT NULL COMMENT 'array of lat/lng points',
    total_cores         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    used_cores          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    calculated_length_m DOUBLE       DEFAULT NULL,
    predicted_loss_db   DOUBLE       DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wire_leases (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    route_id      INT UNSIGNED NOT NULL,
    client_name   VARCHAR(160) NOT NULL,
    core_number   SMALLINT UNSIGNED NOT NULL,
    monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    lease_start   DATE DEFAULT NULL,
    lease_end     DATE DEFAULT NULL,
    status        ENUM('Active','Terminated') NOT NULL DEFAULT 'Active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_route_core (route_id, core_number),
    CONSTRAINT fk_wire_leases_route FOREIGN KEY (route_id)
        REFERENCES fiber_routes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS port_assignments (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    node_id           INT UNSIGNED NOT NULL,
    port_number       SMALLINT UNSIGNED NOT NULL,
    port_name         VARCHAR(80)  DEFAULT NULL,
    customer_username VARCHAR(80)  DEFAULT NULL,
    linked_node_id    INT UNSIGNED DEFAULT NULL,
    in_color          VARCHAR(30)  DEFAULT NULL,
    out_color         VARCHAR(30)  DEFAULT NULL,
    status            VARCHAR(30)  NOT NULL DEFAULT 'free',
    PRIMARY KEY (id),
    UNIQUE KEY uq_node_port (node_id, port_number),
    KEY idx_port_customer (customer_username),
    CONSTRAINT fk_port_node FOREIGN KEY (node_id)
        REFERENCES ftth_nodes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS olt_onu_signal (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    olt_id      INT UNSIGNED NOT NULL,
    port        VARCHAR(40)  DEFAULT NULL,
    onu_serial  VARCHAR(64)  NOT NULL,
    onu_type    VARCHAR(60)  DEFAULT NULL,
    rx_power    DECIMAL(6,2) DEFAULT NULL COMMENT 'dBm',
    tx_power    DECIMAL(6,2) DEFAULT NULL COMMENT 'dBm',
    status      ENUM('online','offline') NOT NULL DEFAULT 'offline',
    online      TINYINT(1)   NOT NULL DEFAULT 0,
    recorded_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_onu_signal_olt (olt_id, port),
    KEY idx_onu_signal_serial (onu_serial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onu_power_history (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username  VARCHAR(80) NOT NULL,
    rx_power  DECIMAL(6,2) DEFAULT NULL,
    tx_power  DECIMAL(6,2) DEFAULT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_onu_power_user_time (username, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS optical_power_history (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_username VARCHAR(80) NOT NULL,
    rx_power          DECIMAL(6,2) DEFAULT NULL,
    status            VARCHAR(30)  DEFAULT NULL,
    recorded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_optical_user_time (customer_username, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_name      VARCHAR(160) NOT NULL,
    brand          VARCHAR(80)  DEFAULT NULL,
    serial_number  VARCHAR(120) DEFAULT NULL,
    mac_address    VARCHAR(32)  DEFAULT NULL,
    in_stock       INT NOT NULL DEFAULT 0,
    status         ENUM('available','issued','faulty') NOT NULL DEFAULT 'available',
    issued_to_user INT UNSIGNED DEFAULT NULL,
    username       VARCHAR(80)  DEFAULT NULL COMMENT 'customer it was issued to',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_serial (serial_number),
    KEY idx_inventory_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  7. Hotspot / captive portal
-- =====================================================================

CREATE TABLE IF NOT EXISTS hotspot_profiles (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    type            ENUM('time','data','unlimited') NOT NULL DEFAULT 'time',
    price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    speed_kbps      INT UNSIGNED DEFAULT NULL,
    data_limit_mb   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
    validity_hours  INT UNSIGNED DEFAULT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hotspot_profiles_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_plan_types (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    type            ENUM('prepaid','postpaid','free_trial') NOT NULL DEFAULT 'prepaid',
    price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    setup_fee       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    billing_cycle   VARCHAR(30)  DEFAULT NULL,
    speed_kbps      INT UNSIGNED DEFAULT NULL,
    speed_down_kbps INT UNSIGNED DEFAULT NULL,
    speed_up_kbps   INT UNSIGNED DEFAULT NULL,
    data_limit_mb   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fup_limit_mb    BIGINT UNSIGNED DEFAULT NULL,
    fup_speed_kbps  INT UNSIGNED DEFAULT NULL,
    time_limit_mins INT UNSIGNED DEFAULT NULL,
    validity_days   INT UNSIGNED DEFAULT NULL,
    is_shared       TINYINT(1)   NOT NULL DEFAULT 0,
    shared_users    SMALLINT UNSIGNED DEFAULT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hotspot_plan_types_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(80)  NOT NULL,
    password        VARCHAR(255) NOT NULL,
    phone           VARCHAR(40)  DEFAULT NULL,
    profile_id      INT UNSIGNED DEFAULT NULL,
    plan_type       VARCHAR(40)  DEFAULT NULL,
    auth_method     VARCHAR(30)  DEFAULT 'password' COMMENT 'password, otp, mac, voucher',
    mac_address     VARCHAR(32)  DEFAULT NULL,
    ip_address      VARCHAR(45)  DEFAULT NULL,
    allowed_ips     TEXT,
    ip_mac_binding  TINYINT(1)   NOT NULL DEFAULT 0,
    single_session  TINYINT(1)   NOT NULL DEFAULT 1,
    max_devices     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    data_limit_mb   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fup_speed_kbps  INT UNSIGNED DEFAULT NULL,
    current_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    -- "hours of service" window, used by the hotel module
    hos_enabled     TINYINT(1)   NOT NULL DEFAULT 0,
    hos_start       TIME         DEFAULT NULL,
    hos_end         TIME         DEFAULT NULL,
    valid_until     DATETIME     DEFAULT NULL,
    last_login      DATETIME     DEFAULT NULL,
    status          ENUM('active','expired','blocked') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotspot_users_username (username),
    KEY idx_hotspot_users_status (status),
    KEY idx_hotspot_users_mac (mac_address),
    CONSTRAINT fk_hotspot_users_profile FOREIGN KEY (profile_id)
        REFERENCES hotspot_profiles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_sessions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id  VARCHAR(80)  NOT NULL,
    username    VARCHAR(80)  NOT NULL,
    profile_id  INT UNSIGNED DEFAULT NULL,
    mac         VARCHAR(32)  DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    login_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME     DEFAULT NULL,
    status      ENUM('active','closed') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotspot_session_id (session_id),
    KEY idx_hotspot_sessions_user (username, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_access_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED DEFAULT NULL,
    username    VARCHAR(80)  DEFAULT NULL,
    action      ENUM('login','logout') NOT NULL,
    auth_method VARCHAR(30)  DEFAULT NULL,
    status      ENUM('success','failed','blocked') NOT NULL DEFAULT 'success',
    message     VARCHAR(255) DEFAULT NULL,
    mac_address VARCHAR(32)  DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hotspot_logs_created (created_at),
    KEY idx_hotspot_logs_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_access_lists (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    list_type   ENUM('mac','ip') NOT NULL DEFAULT 'mac',
    value       VARCHAR(120) NOT NULL,
    mac         VARCHAR(32)  DEFAULT NULL,
    ip          VARCHAR(45)  DEFAULT NULL,
    action      ENUM('block','allow') NOT NULL DEFAULT 'block',
    description VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_access_list_value (list_type, value),
    KEY idx_access_list_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_sms_otp (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone      VARCHAR(40) NOT NULL,
    otp        VARCHAR(10) DEFAULT NULL,
    otp_code   VARCHAR(10) DEFAULT NULL,
    status     ENUM('pending','used','expired') NOT NULL DEFAULT 'pending',
    used       TINYINT(1)  NOT NULL DEFAULT 0,
    used_at    DATETIME    DEFAULT NULL,
    expires_at DATETIME    DEFAULT NULL,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sms_otp_phone (phone, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_sms_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone      VARCHAR(40) NOT NULL,
    otp        VARCHAR(10) DEFAULT NULL,
    message    TEXT,
    status     VARCHAR(30) NOT NULL DEFAULT 'sent',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hotspot_sms_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_voucher_types (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120) NOT NULL,
    type          ENUM('time','data') NOT NULL DEFAULT 'time',
    value         INT UNSIGNED NOT NULL DEFAULT 0,
    unit          VARCHAR(20)  DEFAULT NULL COMMENT 'hours, MB, ...',
    price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    validity_days INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_vouchers (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pin_code     VARCHAR(40) NOT NULL,
    profile_id   INT UNSIGNED DEFAULT NULL,
    status       ENUM('unused','used','expired') NOT NULL DEFAULT 'unused',
    used_by      VARCHAR(80) DEFAULT NULL,
    used_at      DATETIME    DEFAULT NULL,
    expires_at   DATETIME    DEFAULT NULL,
    generated_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_voucher_pin (pin_code),
    KEY idx_voucher_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_invoices (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number    VARCHAR(40)   NOT NULL,
    user_id           INT UNSIGNED  NOT NULL,
    plan_id           INT UNSIGNED  DEFAULT NULL,
    description       VARCHAR(255)  DEFAULT NULL,
    amount            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status            ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    payment_method    VARCHAR(40)   DEFAULT NULL,
    payment_reference VARCHAR(120)  DEFAULT NULL,
    due_date          DATE          DEFAULT NULL,
    paid_at           DATETIME      DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotspot_invoice_number (invoice_number),
    KEY idx_hotspot_invoices_user (user_id),
    KEY idx_hotspot_invoices_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_hotels (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(160) NOT NULL,
    code              VARCHAR(40)  DEFAULT NULL,
    contact_person    VARCHAR(120) DEFAULT NULL,
    phone             VARCHAR(40)  DEFAULT NULL,
    email             VARCHAR(160) DEFAULT NULL,
    address           VARCHAR(255) DEFAULT NULL,
    checkout_time     TIME         DEFAULT '12:00:00',
    grace_period_mins INT UNSIGNED NOT NULL DEFAULT 60,
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotel_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_rooms (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    hotel_id       INT UNSIGNED NOT NULL,
    room_number    VARCHAR(20)  NOT NULL,
    floor          VARCHAR(20)  DEFAULT NULL,
    plan_id        INT UNSIGNED DEFAULT NULL,
    guest_name     VARCHAR(160) DEFAULT NULL,
    guest_phone    VARCHAR(40)  DEFAULT NULL,
    guest_id_proof VARCHAR(120) DEFAULT NULL,
    mac_address    VARCHAR(32)  DEFAULT NULL,
    checkin_time   DATETIME     DEFAULT NULL,
    checkout_time  DATETIME     DEFAULT NULL,
    status         ENUM('vacant','occupied','maintenance') NOT NULL DEFAULT 'vacant',
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotel_room (hotel_id, room_number),
    KEY idx_rooms_status (status),
    CONSTRAINT fk_rooms_hotel FOREIGN KEY (hotel_id)
        REFERENCES hotspot_hotels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotspot_portal_profiles (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120) NOT NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    settings   JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Key/value store for the captive portal (captive_bg_type, portal_title,
-- sms_api_key, ...). Secrets live here, so never render them back into a
-- form field; see hotspot/admin/settings.php.
CREATE TABLE IF NOT EXISTS hotspot_settings (
    setting_key   VARCHAR(80) NOT NULL,
    setting_value TEXT,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  8. Platform settings and notifications
-- =====================================================================

-- Key/value settings read by includes/auth.php (session_timeout,
-- session_idle_timeout), the billing cron and the SMS/eSewa integrations.
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(80) NOT NULL,
    setting_value TEXT,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-row branding/config table. system_config.php does
-- "SELECT * FROM system_config LIMIT 1", so keep exactly one row.
CREATE TABLE IF NOT EXISTS system_config (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    config_key        VARCHAR(80)  DEFAULT NULL,
    config_value      TEXT,
    config_data       JSON         DEFAULT NULL,
    logo              VARCHAR(255) DEFAULT NULL,
    favicon           VARCHAR(255) DEFAULT NULL,
    expire_block_time TIME         DEFAULT '00:00:00',
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_system_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_user VARCHAR(80)  DEFAULT NULL,
    phone_number  VARCHAR(40)  NOT NULL,
    message       TEXT,
    status        ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sms_logs_sent (sent_at),
    KEY idx_sms_logs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
