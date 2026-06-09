-- =============================================================================
-- Al Fauzan Advisory — Lead Management System
-- Database : al_fauzan_db
-- Engine   : MySQL 8.0  |  Charset: utf8mb4_unicode_ci
-- Generated: 2026-06-09 (live mysqldump + cleaned sample data)
--
-- SENSITIVE VALUES (SMTP password, API keys) have been redacted.
-- Configure them via Admin → Settings after first login.
--
-- Default admin login:
--   Username : admin
--   Password : admin123   ← change immediately after first login
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `al_fauzan_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `al_fauzan_db`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- -----------------------------------------------------------------------------
-- TABLE: users
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email`         VARCHAR(100) NOT NULL,
  `role`          ENUM('admin','editor') DEFAULT 'editor',
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin — password: admin123
INSERT INTO `users` (`username`, `password_hash`, `email`, `role`) VALUES
('admin', '$2y$10$PWG9FDsZr9hViVzWwI3I0u4KDPZU4b/X.ogtyNUIHevoy62f3Awm.', 'admin@example.com', 'admin');

-- -----------------------------------------------------------------------------
-- TABLE: settings
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(50)  NOT NULL,
  `setting_value` TEXT,
  `description`   VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('app_title',                 'Lead Management System',   'Main Website Title'),
('base_url',                  '',                         'Base URL of the website (e.g. https://yourdomain.com)'),
('company_logo',              '',                         'Company Logo Path'),
-- WhatsApp — WAWP
('whatsapp_provider',         'wawp',                     'Active WhatsApp provider (wawp or waha)'),
('wawp_api_token',            '',                         'WAWP API Bearer Token  ← set in Settings'),
('wawp_device_id',            '',                         'WAWP Device / Instance ID'),
('wawp_server',               'https://api.wawp.net',     'WAWP Server URL'),
('wawp_auto_reply_template',  'Hi {full_name},\n\nThank you for reaching out. We have received your inquiry regarding ''{inquiry_type}'' and our team will be in touch shortly.\n\nWarm regards,\nAdmin', 'WhatsApp Auto-Reply Template for Leads'),
-- WhatsApp — WAHA
('waha_server_url',           '',                         'WAHA Server URL (e.g. http://localhost:3000)'),
('waha_api_key',              '',                         'WAHA API Key (X-Api-Key header)  ← set in Settings'),
('waha_session',              'default',                  'WAHA Session Name'),
-- Cloudflare Turnstile
('turnstile_site_key',        '',                         'Cloudflare Turnstile Site Key  ← set in Settings'),
('turnstile_secret_key',      '',                         'Cloudflare Turnstile Secret Key  ← set in Settings'),
-- SMTP Email
('smtp_host',                 'smtp.gmail.com',           'SMTP Host'),
('smtp_port',                 '587',                      'SMTP Port'),
('smtp_encryption',           'tls',                      'SMTP Encryption: tls (STARTTLS/587), ssl (465), none (25)'),
('smtp_user',                 '',                         'SMTP Username / Gmail address  ← set in Settings'),
('smtp_pass',                 '',                         'SMTP App Password  ← set in Settings'),
('smtp_from_email',           '',                         'SMTP From Email'),
('smtp_from_name',            'Admin',                    'SMTP From Name');

-- -----------------------------------------------------------------------------
-- TABLE: leads_profile  (unique contact per phone number)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `leads_profile`;
CREATE TABLE `leads_profile` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `phone_number`  VARCHAR(20)  NOT NULL,
  `full_name`     VARCHAR(100) NOT NULL,
  `email_address` VARCHAR(100) DEFAULT NULL,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample profiles
INSERT INTO `leads_profile` (`phone_number`, `full_name`, `email_address`, `created_at`) VALUES
('60123456789', 'Ahmad Faizal bin Hassan',      'ahmad.faizal@email.com',    '2026-01-10 09:00:00'),
('60198765432', 'Siti Nurhaliza binti Ahmad',   'siti.nurhaliza@email.com',  '2026-02-14 10:30:00'),
('60112345678', 'Muhammad Rizwan bin Omar',     'rizwan.omar@email.com',     '2026-03-05 14:15:00');

-- -----------------------------------------------------------------------------
-- TABLE: leads  (inquiry records, many per profile)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
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
  `created_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_leads_profile` (`leads_profile_id`),
  CONSTRAINT `fk_leads_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample inquiries
INSERT INTO `leads` (`leads_profile_id`, `full_name`, `email_address`, `phone_number`, `inquiry_type`, `message`, `source_page`, `status`, `admin_comment`, `created_at`) VALUES
(1, 'Ahmad Faizal bin Hassan',    'ahmad.faizal@email.com',   '60123456789', 'Takaful',           'I am interested in a family takaful plan.',              'contact-form', 'new',       NULL,                          '2026-01-10 09:05:00'),
(2, 'Siti Nurhaliza binti Ahmad', 'siti.nurhaliza@email.com', '60198765432', 'Life Insurance',    'Looking for whole life insurance coverage for myself.',  'contact-form', 'contacted', 'Called client, sent quotation.','2026-02-14 10:35:00'),
(3, 'Muhammad Rizwan bin Omar',   'rizwan.omar@email.com',    '60112345678', 'Medical & Health',  'Need a comprehensive medical card for family of four.',  'manual',       'closed',    'Signed up — AIA medical card.', '2026-03-05 14:20:00');

-- -----------------------------------------------------------------------------
-- TABLE: quotations
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id`               INT           NOT NULL AUTO_INCREMENT,
  `leads_profile_id` INT           NOT NULL,
  `quotation_number` VARCHAR(50)   NOT NULL,
  `amount`           DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `status`           ENUM('Draft','Sent','Accepted','Rejected') DEFAULT 'Draft',
  `items`            JSON          DEFAULT NULL,
  `created_at`       TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `fk_quotation_profile` (`leads_profile_id`),
  CONSTRAINT `fk_quotation_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample quotation
INSERT INTO `quotations` (`leads_profile_id`, `quotation_number`, `amount`, `status`, `items`, `created_at`) VALUES
(2, 'QT-2026-0001', 1800.00, 'Sent',
 '[{"description":"Whole Life Insurance — AIA Signature Life","quantity":1,"unit_price":1800.00,"total":1800.00}]',
 '2026-02-15 11:00:00');

-- -----------------------------------------------------------------------------
-- TABLE: invoices
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id`               INT           NOT NULL AUTO_INCREMENT,
  `leads_profile_id` INT           NOT NULL,
  `quotation_id`     INT           DEFAULT NULL,
  `invoice_number`   VARCHAR(50)   NOT NULL,
  `amount`           DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `status`           ENUM('Unpaid','Paid','Cancelled') DEFAULT 'Unpaid',
  `items`            JSON          DEFAULT NULL,
  `created_at`       TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_invoice_profile`   (`leads_profile_id`),
  KEY `fk_invoice_quotation` (`quotation_id`),
  CONSTRAINT `fk_invoice_profile`   FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoice_quotation` FOREIGN KEY (`quotation_id`)     REFERENCES `quotations`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: careers
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `careers`;
CREATE TABLE `careers` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_address` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number`  VARCHAR(20)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position`      VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message`       TEXT         COLLATE utf8mb4_unicode_ci,
  `status`        ENUM('new','reviewed','rejected','hired') COLLATE utf8mb4_unicode_ci DEFAULT 'new',
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample career application
INSERT INTO `careers` (`full_name`, `email_address`, `phone_number`, `position`, `message`, `status`, `created_at`) VALUES
('Aminah binti Yusof', 'aminah.yusof@email.com', '60134567890',
 'Insurance Agent',
 'I have 3 years of experience in financial planning and insurance sales. Eager to join a growing advisory firm.',
 'new', '2026-04-01 09:30:00');

-- -----------------------------------------------------------------------------
-- TABLE: forms  (form builder)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `forms`;
CREATE TABLE `forms` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `form_key`    VARCHAR(64)  NOT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT,
  `destination` ENUM('leads','leads_profile','careers') NOT NULL DEFAULT 'leads',
  `fields`      JSON         NOT NULL,
  `settings`    JSON         DEFAULT NULL,
  `is_active`   TINYINT(1)   DEFAULT '1',
  `created_by`  INT          DEFAULT NULL,
  `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_key` (`form_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: form_submissions
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `form_submissions`;
CREATE TABLE `form_submissions` (
  `id`             INT         NOT NULL AUTO_INCREMENT,
  `form_id`        INT         NOT NULL,
  `form_key`       VARCHAR(64) NOT NULL,
  `submitted_data` JSON        NOT NULL,
  `ip_address`     VARCHAR(45) DEFAULT NULL,
  `user_agent`     TEXT,
  `status`         ENUM('processed','failed') DEFAULT 'processed',
  `error_message`  TEXT,
  `created_at`     TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_fsub_form` (`form_id`),
  CONSTRAINT `fk_fsub_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: whatsapp_contacts
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_contacts`;
CREATE TABLE `whatsapp_contacts` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `phone_number`     VARCHAR(20)  COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Normalized: 60XXXXXXXXX, no + prefix',
  `display_name`     VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leads_profile_id` INT          DEFAULT NULL,
  `source`           ENUM('inbound','form','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'inbound',
  `first_seen_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`),
  KEY `fk_wa_contact_profile` (`leads_profile_id`),
  CONSTRAINT `fk_wa_contact_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: whatsapp_incoming
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_incoming`;
CREATE TABLE `whatsapp_incoming` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `wawp_message_id`  VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number`     VARCHAR(20)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id`       INT          DEFAULT NULL,
  `leads_profile_id` INT          DEFAULT NULL,
  `message_body`     TEXT         COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_payload`      LONGTEXT     COLLATE utf8mb4_unicode_ci,
  `event_type`       VARCHAR(50)  COLLATE utf8mb4_unicode_ci DEFAULT 'message',
  `processed`        TINYINT(1)   DEFAULT '0',
  `created_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wawp_message_id`    (`wawp_message_id`),
  KEY `idx_wa_incoming_phone`     (`phone_number`),
  KEY `idx_wa_incoming_proc`      (`processed`),
  KEY `idx_wa_incoming_time`      (`created_at`),
  KEY `fk_wa_incoming_contact`    (`contact_id`),
  KEY `fk_wa_incoming_profile`    (`leads_profile_id`),
  CONSTRAINT `fk_wa_incoming_contact` FOREIGN KEY (`contact_id`)       REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wa_incoming_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: whatsapp_outgoing
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_outgoing`;
CREATE TABLE `whatsapp_outgoing` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `wawp_message_id`  VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number`     VARCHAR(20)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id`       INT          DEFAULT NULL,
  `leads_profile_id` INT          DEFAULT NULL,
  `message_body`     TEXT         COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type`     ENUM('auto_reply','manual','notification','confirmation') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `status`           ENUM('Sent','Failed','Pending') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `api_response`     TEXT         COLLATE utf8mb4_unicode_ci,
  `sent_by`          VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wa_out_phone`   (`phone_number`),
  KEY `fk_wa_out_contact`  (`contact_id`),
  KEY `fk_wa_out_profile`  (`leads_profile_id`),
  CONSTRAINT `fk_wa_out_contact` FOREIGN KEY (`contact_id`)       REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wa_out_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: whatsapp_sessions
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_sessions`;
CREATE TABLE `whatsapp_sessions` (
  `id`            INT         NOT NULL AUTO_INCREMENT,
  `phone_number`  VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id`    INT         DEFAULT NULL,
  `session_start` TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP,
  `session_end`   TIMESTAMP   NULL DEFAULT NULL,
  `message_count` INT         DEFAULT '0',
  `notes`         TEXT        COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `fk_wa_sess_contact` (`contact_id`),
  CONSTRAINT `fk_wa_sess_contact` FOREIGN KEY (`contact_id`) REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: message_history  (unified send/receive log)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `message_history`;
CREATE TABLE `message_history` (
  `id`               INT         NOT NULL AUTO_INCREMENT,
  `leads_profile_id` INT         DEFAULT NULL,
  `phone_number`     VARCHAR(20) NOT NULL,
  `message_body`     TEXT        NOT NULL,
  `status`           VARCHAR(50) DEFAULT 'Sent',
  `api_response`     TEXT,
  `created_at`       TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_message_profile` (`leads_profile_id`),
  CONSTRAINT `fk_message_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: admin_activity_log
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_activity_log`;
CREATE TABLE `admin_activity_log` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `admin_id`   INT          DEFAULT NULL,
  `action`     VARCHAR(100) DEFAULT NULL,
  `details`    TEXT,
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: page_content  (CMS)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `page_content`;
CREATE TABLE `page_content` (
  `id`              INT         NOT NULL AUTO_INCREMENT,
  `page_name`       VARCHAR(50) NOT NULL,
  `component_key`   VARCHAR(50) NOT NULL,
  `component_value` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_name` (`page_name`, `component_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TABLE: backup_history
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `backup_history`;
CREATE TABLE `backup_history` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `filename`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type`       ENUM('database','files','full') COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` BIGINT       DEFAULT '0',
  `created_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT 'system',
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
SET FOREIGN_KEY_CHECKS = 1;
-- =============================================================================
-- Schema complete. Tables: 16 | Sample rows seeded in:
--   users (1), settings (20), leads_profile (3), leads (3),
--   quotations (1), careers (1)
-- All other tables start empty and are populated at runtime.
-- =============================================================================
