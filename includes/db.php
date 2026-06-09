<?php
// includes/db.php

// Start session immediately so $_SESSION is available before header.php is included.
// Uses session_status() guard to avoid double-start on pages that call session_start() themselves.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = 'localhost'; // cPanel / shared hosting → 'localhost'  |  Docker → 'mysql'
$dbname   = 'al_fauzan_db';
$username = 'root'; // Update with your DB username
$password = 'root'; // Update with your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    // ── Bootstrap all required tables (CREATE TABLE IF NOT EXISTS is safe to run every time) ──

    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id`            INT          NOT NULL AUTO_INCREMENT,
        `username`      VARCHAR(50)  NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `email`         VARCHAR(100) NOT NULL,
        `role`          ENUM('admin','editor') DEFAULT 'editor',
        `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email`    (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `leads_profile` (
        `id`            INT         NOT NULL AUTO_INCREMENT,
        `phone_number`  VARCHAR(20) NOT NULL,
        `full_name`     VARCHAR(100) NOT NULL,
        `email_address` VARCHAR(100) DEFAULT NULL,
        `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `phone_number` (`phone_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `leads` (
        `id`               INT          NOT NULL AUTO_INCREMENT,
        `leads_profile_id` INT          DEFAULT NULL,
        `full_name`        VARCHAR(100) NOT NULL,
        `email_address`    VARCHAR(100) NOT NULL,
        `phone_number`     VARCHAR(20)  DEFAULT NULL,
        `inquiry_type`     VARCHAR(50)  DEFAULT 'General',
        `message`          TEXT,
        `source_page`      VARCHAR(50)  DEFAULT NULL,
        `status`           ENUM('new','contacted','closed') DEFAULT 'new',
        `admin_comment`    TEXT,
        `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `fk_leads_profile` (`leads_profile_id`),
        CONSTRAINT `fk_leads_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `quotations` (
        `id`               INT           NOT NULL AUTO_INCREMENT,
        `leads_profile_id` INT           NOT NULL,
        `quotation_number` VARCHAR(50)   NOT NULL,
        `amount`           DECIMAL(10,2) NOT NULL DEFAULT '0.00',
        `status`           ENUM('Draft','Sent','Accepted','Rejected') DEFAULT 'Draft',
        `items`            JSON          DEFAULT NULL,
        `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `quotation_number` (`quotation_number`),
        KEY `fk_quotation_profile` (`leads_profile_id`),
        CONSTRAINT `fk_quotation_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `invoices` (
        `id`               INT           NOT NULL AUTO_INCREMENT,
        `leads_profile_id` INT           NOT NULL,
        `quotation_id`     INT           DEFAULT NULL,
        `invoice_number`   VARCHAR(50)   NOT NULL,
        `amount`           DECIMAL(10,2) NOT NULL DEFAULT '0.00',
        `status`           ENUM('Unpaid','Paid','Cancelled') DEFAULT 'Unpaid',
        `items`            JSON          DEFAULT NULL,
        `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `invoice_number` (`invoice_number`),
        KEY `fk_invoice_profile`   (`leads_profile_id`),
        KEY `fk_invoice_quotation` (`quotation_id`),
        CONSTRAINT `fk_invoice_profile`   FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_invoice_quotation` FOREIGN KEY (`quotation_id`)     REFERENCES `quotations`     (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `careers` (
        `id`            INT          NOT NULL AUTO_INCREMENT,
        `full_name`     VARCHAR(100) NOT NULL,
        `email_address` VARCHAR(100) NOT NULL,
        `phone_number`  VARCHAR(20)  DEFAULT NULL,
        `position`      VARCHAR(100) DEFAULT NULL,
        `message`       TEXT,
        `status`        ENUM('new','reviewed','rejected','hired') DEFAULT 'new',
        `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `forms` (
        `id`          INT          NOT NULL AUTO_INCREMENT,
        `form_key`    VARCHAR(64)  NOT NULL,
        `title`       VARCHAR(255) NOT NULL,
        `description` TEXT,
        `destination` ENUM('leads','leads_profile','careers') NOT NULL DEFAULT 'leads',
        `fields`      JSON         NOT NULL,
        `settings`    JSON         DEFAULT NULL,
        `is_active`   TINYINT(1)   DEFAULT '1',
        `created_by`  INT          DEFAULT NULL,
        `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `form_key` (`form_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `form_submissions` (
        `id`             INT         NOT NULL AUTO_INCREMENT,
        `form_id`        INT         NOT NULL,
        `form_key`       VARCHAR(64) NOT NULL,
        `submitted_data` JSON        NOT NULL,
        `ip_address`     VARCHAR(45) DEFAULT NULL,
        `user_agent`     TEXT,
        `status`         ENUM('processed','failed') DEFAULT 'processed',
        `error_message`  TEXT,
        `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `fk_fsub_form` (`form_id`),
        CONSTRAINT `fk_fsub_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `whatsapp_contacts` (
        `id`               INT          NOT NULL AUTO_INCREMENT,
        `phone_number`     VARCHAR(20)  NOT NULL COMMENT 'Normalized: 60XXXXXXXXX',
        `display_name`     VARCHAR(150) DEFAULT NULL,
        `leads_profile_id` INT          DEFAULT NULL,
        `source`           ENUM('inbound','form','manual') DEFAULT 'inbound',
        `first_seen_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `phone_number` (`phone_number`),
        KEY `fk_wa_contact_profile` (`leads_profile_id`),
        CONSTRAINT `fk_wa_contact_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `whatsapp_incoming` (
        `id`               INT          NOT NULL AUTO_INCREMENT,
        `wawp_message_id`  VARCHAR(150) DEFAULT NULL,
        `phone_number`     VARCHAR(20)  NOT NULL,
        `contact_id`       INT          DEFAULT NULL,
        `leads_profile_id` INT          DEFAULT NULL,
        `message_body`     TEXT         NOT NULL,
        `raw_payload`      LONGTEXT     DEFAULT NULL,
        `event_type`       VARCHAR(50)  DEFAULT 'message',
        `processed`        TINYINT(1)   DEFAULT '0',
        `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `wawp_message_id`     (`wawp_message_id`),
        KEY `idx_wa_incoming_phone`      (`phone_number`),
        KEY `idx_wa_incoming_proc`       (`processed`),
        KEY `idx_wa_incoming_time`       (`created_at`),
        KEY `fk_wa_incoming_contact`     (`contact_id`),
        KEY `fk_wa_incoming_profile`     (`leads_profile_id`),
        CONSTRAINT `fk_wa_incoming_contact` FOREIGN KEY (`contact_id`)       REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_wa_incoming_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile`     (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `whatsapp_outgoing` (
        `id`               INT          NOT NULL AUTO_INCREMENT,
        `wawp_message_id`  VARCHAR(150) DEFAULT NULL,
        `phone_number`     VARCHAR(20)  NOT NULL,
        `contact_id`       INT          DEFAULT NULL,
        `leads_profile_id` INT          DEFAULT NULL,
        `message_body`     TEXT         NOT NULL,
        `message_type`     ENUM('auto_reply','manual','notification','confirmation') DEFAULT 'manual',
        `status`           ENUM('Sent','Failed','Pending') DEFAULT 'Pending',
        `api_response`     TEXT         DEFAULT NULL,
        `sent_by`          VARCHAR(100) DEFAULT NULL,
        `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_wa_out_phone`   (`phone_number`),
        KEY `fk_wa_out_contact`  (`contact_id`),
        KEY `fk_wa_out_profile`  (`leads_profile_id`),
        CONSTRAINT `fk_wa_out_contact` FOREIGN KEY (`contact_id`)       REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_wa_out_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile`     (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `whatsapp_sessions` (
        `id`            INT         NOT NULL AUTO_INCREMENT,
        `phone_number`  VARCHAR(20) NOT NULL,
        `contact_id`    INT         DEFAULT NULL,
        `session_start` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `session_end`   TIMESTAMP NULL DEFAULT NULL,
        `message_count` INT         DEFAULT '0',
        `notes`         TEXT        DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `fk_wa_sess_contact` (`contact_id`),
        CONSTRAINT `fk_wa_sess_contact` FOREIGN KEY (`contact_id`) REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_history` (
        `id`               INT         NOT NULL AUTO_INCREMENT,
        `leads_profile_id` INT         DEFAULT NULL,
        `phone_number`     VARCHAR(20) NOT NULL,
        `message_body`     TEXT        NOT NULL,
        `status`           VARCHAR(50) DEFAULT 'Sent',
        `api_response`     TEXT        DEFAULT NULL,
        `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `fk_message_profile` (`leads_profile_id`),
        CONSTRAINT `fk_message_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `backup_history` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `filename`   VARCHAR(255) NOT NULL,
        `type`       ENUM('database','files','full') NOT NULL,
        `size_bytes` BIGINT       DEFAULT '0',
        `created_by` VARCHAR(100) DEFAULT 'system',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `page_content` (
        `id`              INT         NOT NULL AUTO_INCREMENT,
        `page_name`       VARCHAR(50) NOT NULL,
        `component_key`   VARCHAR(50) NOT NULL,
        `component_value` TEXT,
        PRIMARY KEY (`id`),
        UNIQUE KEY `page_name` (`page_name`, `component_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_activity_log` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `admin_id`   INT          DEFAULT NULL,
        `action`     VARCHAR(100) DEFAULT NULL,
        `details`    TEXT,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id`            INT         NOT NULL AUTO_INCREMENT,
        `setting_key`   VARCHAR(50) NOT NULL,
        `setting_value` TEXT,
        `description`   VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Seed default admin user (INSERT IGNORE = skip if already exists) ──────
    $pdo->exec("INSERT IGNORE INTO `users` (username, password_hash, email, role) VALUES
        ('admin', '\$2y\$10\$PWG9FDsZr9hViVzWwI3I0u4KDPZU4b/X.ogtyNUIHevoy62f3Awm.', 'admin@example.com', 'admin')
    ");

    // ── Seed default settings (INSERT IGNORE = skip if already exists) ────────
    $pdo->exec("INSERT IGNORE INTO `settings` (setting_key, setting_value, description) VALUES
        ('app_title',          'Admin',                 'Main Application Title'),
        ('whatsapp_provider',  'wawp',                  'Active WhatsApp provider (wawp or waha)'),
        ('wawp_api_token',     '',                      'WAWP API Bearer Token'),
        ('wawp_device_id',     '',                      'WAWP Device ID'),
        ('wawp_server',        'https://api.wawp.net',  'WAWP Server URL'),
        ('waha_server_url',    '',                      'WAHA Server URL (e.g. http://localhost:3000)'),
        ('waha_api_key',       '',                      'WAHA API Key (X-Api-Key header)'),
        ('waha_session',       'default',               'WAHA Session Name'),
        ('smtp_host',          '',                      'SMTP Host'),
        ('smtp_port',          '587',                   'SMTP Port'),
        ('smtp_encryption',    'tls',                   'SMTP Encryption: tls (STARTTLS/587), ssl (465), none (25)'),
        ('smtp_user',          '',                      'SMTP Username'),
        ('smtp_pass',          '',                      'SMTP Password'),
        ('smtp_from_email',    '',                      'SMTP From Email'),
        ('smtp_from_name',     '',                      'SMTP From Name')
    ");

} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage(), 3, __DIR__ . '/../logs/system-log.log');
    die("Database connection failed. Please check the system logs.");
}

// Load base_url and app_title from settings
$base_url  = '';
$app_title = 'Admin';
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('base_url', 'app_title')");
    foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
        if ($row->setting_key === 'base_url' && $row->setting_value) {
            $base_url = rtrim($row->setting_value, '/');
        }
        if ($row->setting_key === 'app_title' && $row->setting_value) {
            $app_title = $row->setting_value;
        }
    }
} catch (Exception $e) {
    $base_url  = '';
    $app_title = 'Admin';
}
if (empty($base_url)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// BASE_PATH: the subfolder the app lives in, e.g. '/admin' or '' for root.
// Auto-detected from the script path so it works on both root and subfolders.
if (!defined('BASE_PATH')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Walk up until we find the app root (where db.php's includes/ folder sits)
    // We detect by checking if the current script is inside an 'includes' subfolder
    if (basename($scriptDir) === 'includes') {
        $scriptDir = dirname($scriptDir);
    }
    define('BASE_PATH', rtrim($scriptDir === '/' ? '' : $scriptDir, '/'));
}
?>