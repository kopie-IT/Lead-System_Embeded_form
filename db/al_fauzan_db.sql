-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Generation Time: Jun 09, 2026 at 06:14 AM
-- Server version: 8.0.46
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `al_fauzan_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int NOT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_history`
--

CREATE TABLE `backup_history` (
  `id` int NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('database','files','full') COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint DEFAULT '0',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'system',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backup_history`
--

INSERT INTO `backup_history` (`id`, `filename`, `type`, `size_bytes`, `created_by`, `created_at`) VALUES
(6, 'files_backup_2026-05-25_15-15-02.zip', 'files', 9743356, 'admin', '2026-05-25 15:15:04'),
(7, 'db_backup_2026-05-25_15-15-19.sql', 'database', 14163, 'admin', '2026-05-25 15:15:19');

-- --------------------------------------------------------

--
-- Table structure for table `careers`
--

CREATE TABLE `careers` (
  `id` int NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_address` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('new','reviewed','rejected','hired') COLLATE utf8mb4_unicode_ci DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forms`
--

CREATE TABLE `forms` (
  `id` int NOT NULL,
  `form_key` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `destination` enum('leads','leads_profile','careers') NOT NULL DEFAULT 'leads',
  `fields` json NOT NULL,
  `settings` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `forms`
--

INSERT INTO `forms` (`id`, `form_key`, `title`, `description`, `destination`, `fields`, `settings`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '901f54c5ebd785db', 'Contact us', '', 'leads', '[{\"id\": \"default_full_name\", \"type\": \"text\", \"label\": \"Full Name\", \"mapping\": \"full_name\", \"required\": true, \"isDefault\": true, \"placeholder\": \"Enter your full name\"}, {\"id\": \"default_phone_number\", \"type\": \"phone\", \"label\": \"Phone Number\", \"mapping\": \"phone_number\", \"required\": true, \"isDefault\": true, \"placeholder\": \"+60 1x-xxx xxxx\"}, {\"id\": \"f_1780963847563_guqj\", \"type\": \"email\", \"label\": \"Email Address\", \"mapping\": \"email_address\", \"required\": true, \"placeholder\": \"Enter email...\"}, {\"id\": \"f_1780963716679_0d4b\", \"type\": \"textarea\", \"label\": \"Your Message\", \"mapping\": \"message\", \"required\": true, \"placeholder\": \"Enter message...\"}]', '{\"redirect_url\": \"\", \"primary_color\": \"#005abe\", \"success_message\": \"Thank you! Your submission has been received.\"}', 1, NULL, '2026-06-09 00:09:28', '2026-06-09 00:10:52');

-- --------------------------------------------------------

--
-- Table structure for table `form_submissions`
--

CREATE TABLE `form_submissions` (
  `id` int NOT NULL,
  `form_id` int NOT NULL,
  `form_key` varchar(64) NOT NULL,
  `submitted_data` json NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `status` enum('processed','failed') DEFAULT 'processed',
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `form_submissions`
--

INSERT INTO `form_submissions` (`id`, `form_id`, `form_key`, `submitted_data`, `ip_address`, `user_agent`, `status`, `error_message`, `created_at`) VALUES
(1, 1, '901f54c5ebd785db', '{\"_extra\": [], \"message\": \"test message\", \"full_name\": \"Nefi test\", \"phone_number\": \"0136321806\"}', '172.21.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'processed', '', '2026-06-09 00:10:13');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int NOT NULL,
  `leads_profile_id` int NOT NULL,
  `quotation_id` int DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('Unpaid','Paid','Cancelled') DEFAULT 'Unpaid',
  `items` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int NOT NULL,
  `leads_profile_id` int DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `inquiry_type` varchar(50) DEFAULT 'General',
  `message` text,
  `source_page` varchar(50) DEFAULT NULL,
  `status` enum('new','contacted','closed') DEFAULT 'new',
  `admin_comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `leads_profile_id`, `full_name`, `email_address`, `phone_number`, `inquiry_type`, `message`, `source_page`, `status`, `admin_comment`, `created_at`, `updated_at`) VALUES
(1, 1, 'Nefi test', 'nefizon2@gmail.com', '0136321806', 'General', 'test message', 'form:901f54c5ebd785db', 'new', NULL, '2026-06-09 00:10:13', '2026-06-09 00:11:01');

-- --------------------------------------------------------

--
-- Table structure for table `leads_profile`
--

CREATE TABLE `leads_profile` (
  `id` int NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email_address` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_notes` text,
  `ic_number` varchar(20) DEFAULT NULL,
  `address` text,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `leads_profile`
--

INSERT INTO `leads_profile` (`id`, `phone_number`, `full_name`, `email_address`, `created_at`, `updated_at`, `profile_notes`, `ic_number`, `address`, `date_of_birth`, `gender`) VALUES
(1, '0136321806', 'Nefi test', 'nefizon2@gmail.com', '2026-06-09 00:10:13', '2026-06-09 02:37:03', '', '', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `message_history`
--

CREATE TABLE `message_history` (
  `id` int NOT NULL,
  `leads_profile_id` int DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message_body` text NOT NULL,
  `status` varchar(50) DEFAULT 'Sent',
  `api_response` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `message_history`
--

INSERT INTO `message_history` (`id`, `leads_profile_id`, `phone_number`, `message_body`, `status`, `api_response`, `created_at`) VALUES
(1, NULL, 'nefizon2@gmail.com', 'Subject: SMTP Test — Al Fauzan Advisory\n\nThis is a test email sent from the admin panel to verify that SMTP is configured correctly.', 'Email Sent', 'Delivered via SMTP (TLS)', '2026-06-08 23:53:28');

-- --------------------------------------------------------

--
-- Table structure for table `page_content`
--

CREATE TABLE `page_content` (
  `id` int NOT NULL,
  `page_name` varchar(50) NOT NULL,
  `component_key` varchar(50) NOT NULL,
  `component_value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int NOT NULL,
  `leads_profile_id` int NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('Draft','Sent','Accepted','Rejected') DEFAULT 'Draft',
  `items` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'app_title', 'Lead Management System', 'Main Website Title'),
(2, 'wawp_api_token', '', 'WAWP API Bearer Token'),
(3, 'wawp_device_id', '', 'WAWP Device ID'),
(4, 'base_url', 'https://tl2.frenflo.com', 'Base URL of the website (e.g. https://yourdomain.com)'),
(5, 'turnstile_site_key', '', 'Cloudflare Turnstile Site Key'),
(6, 'turnstile_secret_key', '', 'Cloudflare Turnstile Secret Key'),
(7, 'wawp_server', 'https://api.wawp.net', 'WAWP Server URL (e.g. https://api.wawp.net)'),
(8, 'wawp_auto_reply_template', 'Hi {full_name},\r\n\r\nThank you for reaching out to Al Fauzan Advisory. We have received your inquiry regarding \'{inquiry_type}\' and we confirm this is your number on WhatsApp. Our team will be in touch with you shortly.\r\n\r\nWarm regards,\r\nAl Fauzan Advisory', 'WhatsApp Auto-Reply Template for Leads'),
(9, 'smtp_host', 'smtp.gmail.com', 'SMTP Host'),
(10, 'smtp_port', '587', 'SMTP Port'),
(11, 'smtp_user', 'nefizon@gmail.com', 'SMTP Username'),
(12, 'smtp_pass', 'ypjj xwnk vxwz xlmr', 'SMTP Password'),
(13, 'smtp_from_email', 'nefizon@gmail.com', 'SMTP From Email'),
(14, 'smtp_from_name', 'Nefizon', 'SMTP From Name'),
(15, 'whatsapp_provider', 'wawp', 'Active WhatsApp provider (wawp or waha)'),
(16, 'waha_server_url', 'https://wa.frenflo.com', 'WAHA Server URL (e.g. http://localhost:3000)'),
(17, 'waha_api_key', 'sha512:8e3d502b4b2824317754f477c11060e8ef94cc89f6002e327c0a568b8caf8d471f59d8ee7609d34418279a5bbb56ca098b0f105b1ea84aadfd79141a3cb4ea70', 'WAHA API Key (X-Api-Key header)'),
(18, 'waha_session', 'default', 'WAHA Session Name'),
(35, 'smtp_encryption', 'tls', 'SMTP Encryption: tls (STARTTLS/587), ssl (465), none (25)');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','editor') DEFAULT 'editor',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `role`, `created_at`, `updated_at`) VALUES
(2, 'nefizon', '$2y$10$BPs.4PXp4F1RxRG9qfSvp.uCdRu2cHnrGvCoc5nCNigL9XAvaor.a', 'nefizon@gmail.com', 'admin', '2026-05-24 08:26:33', '2026-05-25 14:53:28');

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_contacts`
--

CREATE TABLE `whatsapp_contacts` (
  `id` int NOT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Normalized: 60XXXXXXXXX, no + prefix',
  `display_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leads_profile_id` int DEFAULT NULL,
  `source` enum('inbound','form','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'inbound',
  `first_seen_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_incoming`
--

CREATE TABLE `whatsapp_incoming` (
  `id` int NOT NULL,
  `wawp_message_id` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` int DEFAULT NULL,
  `leads_profile_id` int DEFAULT NULL,
  `message_body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_payload` longtext COLLATE utf8mb4_unicode_ci,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'message',
  `processed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_outgoing`
--

CREATE TABLE `whatsapp_outgoing` (
  `id` int NOT NULL,
  `wawp_message_id` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` int DEFAULT NULL,
  `leads_profile_id` int DEFAULT NULL,
  `message_body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` enum('auto_reply','manual','notification','confirmation') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `status` enum('Sent','Failed','Pending') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `api_response` text COLLATE utf8mb4_unicode_ci,
  `sent_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_sessions`
--

CREATE TABLE `whatsapp_sessions` (
  `id` int NOT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` int DEFAULT NULL,
  `session_start` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `session_end` timestamp NULL DEFAULT NULL,
  `message_count` int DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `careers`
--
ALTER TABLE `careers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `forms`
--
ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `form_key` (`form_key`);

--
-- Indexes for table `form_submissions`
--
ALTER TABLE `form_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_fsub_form` (`form_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `fk_invoice_profile` (`leads_profile_id`),
  ADD KEY `fk_invoice_quotation` (`quotation_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_leads_profile` (`leads_profile_id`);

--
-- Indexes for table `leads_profile`
--
ALTER TABLE `leads_profile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone_number` (`phone_number`);

--
-- Indexes for table `message_history`
--
ALTER TABLE `message_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_message_profile` (`leads_profile_id`);

--
-- Indexes for table `page_content`
--
ALTER TABLE `page_content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_name` (`page_name`,`component_key`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `fk_quotation_profile` (`leads_profile_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `whatsapp_contacts`
--
ALTER TABLE `whatsapp_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone_number` (`phone_number`),
  ADD KEY `fk_wa_contact_profile` (`leads_profile_id`);

--
-- Indexes for table `whatsapp_incoming`
--
ALTER TABLE `whatsapp_incoming`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wawp_message_id` (`wawp_message_id`),
  ADD KEY `idx_wa_incoming_phone` (`phone_number`),
  ADD KEY `idx_wa_incoming_proc` (`processed`),
  ADD KEY `idx_wa_incoming_time` (`created_at`),
  ADD KEY `fk_wa_incoming_contact` (`contact_id`),
  ADD KEY `fk_wa_incoming_profile` (`leads_profile_id`);

--
-- Indexes for table `whatsapp_outgoing`
--
ALTER TABLE `whatsapp_outgoing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wa_out_phone` (`phone_number`),
  ADD KEY `fk_wa_out_contact` (`contact_id`),
  ADD KEY `fk_wa_out_profile` (`leads_profile_id`);

--
-- Indexes for table `whatsapp_sessions`
--
ALTER TABLE `whatsapp_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_wa_sess_contact` (`contact_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `careers`
--
ALTER TABLE `careers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forms`
--
ALTER TABLE `forms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `form_submissions`
--
ALTER TABLE `form_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leads_profile`
--
ALTER TABLE `leads_profile`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `message_history`
--
ALTER TABLE `message_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `page_content`
--
ALTER TABLE `page_content`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1130;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `whatsapp_contacts`
--
ALTER TABLE `whatsapp_contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_incoming`
--
ALTER TABLE `whatsapp_incoming`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_outgoing`
--
ALTER TABLE `whatsapp_outgoing`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_sessions`
--
ALTER TABLE `whatsapp_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `form_submissions`
--
ALTER TABLE `form_submissions`
  ADD CONSTRAINT `fk_fsub_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoice_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoice_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `fk_leads_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `message_history`
--
ALTER TABLE `message_history`
  ADD CONSTRAINT `fk_message_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `fk_quotation_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_contacts`
--
ALTER TABLE `whatsapp_contacts`
  ADD CONSTRAINT `fk_wa_contact_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `whatsapp_incoming`
--
ALTER TABLE `whatsapp_incoming`
  ADD CONSTRAINT `fk_wa_incoming_contact` FOREIGN KEY (`contact_id`) REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_wa_incoming_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `whatsapp_outgoing`
--
ALTER TABLE `whatsapp_outgoing`
  ADD CONSTRAINT `fk_wa_out_contact` FOREIGN KEY (`contact_id`) REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_wa_out_profile` FOREIGN KEY (`leads_profile_id`) REFERENCES `leads_profile` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `whatsapp_sessions`
--
ALTER TABLE `whatsapp_sessions`
  ADD CONSTRAINT `fk_wa_sess_contact` FOREIGN KEY (`contact_id`) REFERENCES `whatsapp_contacts` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
