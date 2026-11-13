/* ========================================================
   Kaldi's Coffee Telegram Reply Tracker
   Database Schema for: kaldisbp_ict (with 'tg_' prefix)
   Compatible with phpMyAdmin / MySQL 5.7+ / 8.0+ / MariaDB
======================================================== */

SET FOREIGN_KEY_CHECKS = 0;

/* --------------------------------------------------------
   Table structure for `tg_admins`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('super_admin','owner','group_authorizer','user','viewer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'group_authorizer',
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_group_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_admins_department` (`department`),
  KEY `idx_admins_assigned_group` (`assigned_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_system_settings`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_system_settings` (
  `setting_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_telegram_groups`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_telegram_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `telegram_chat_id` bigint NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'supergroup',
  `department` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `bot_status` enum('active','restricted','left','kicked','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `bot_is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `bot_can_read_all_messages` tinyint(1) NOT NULL DEFAULT '0',
  `last_health_check_at` datetime DEFAULT NULL,
  `last_update_at` datetime DEFAULT NULL,
  `previous_chat_id` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_chat_id` (`telegram_chat_id`),
  KEY `idx_groups_active` (`is_active`),
  KEY `idx_groups_chat_id` (`telegram_chat_id`),
  KEY `idx_groups_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_members`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `telegram_user_id` bigint NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_bot` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_user_id` (`telegram_user_id`),
  KEY `idx_members_user_id` (`telegram_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_group_members`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_group_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `member_id` int NOT NULL,
  `role` enum('member','admin','creator') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `joined_at` datetime NOT NULL,
  `left_at` datetime DEFAULT NULL,
  `source` enum('imported','seen_in_group','admin_added') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'seen_in_group',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_member` (`group_id`,`member_id`),
  KEY `fk_gm_member` (`member_id`),
  KEY `idx_gm_joined` (`joined_at`),
  KEY `idx_gm_left` (`left_at`),
  KEY `idx_gm_active` (`group_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_group_authorized_senders`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_group_authorized_senders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `telegram_user_id` bigint NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gas_group_user` (`group_id`,`telegram_user_id`),
  KEY `fk_gas_group` (`group_id`),
  KEY `idx_gas_user` (`telegram_user_id`),
  CONSTRAINT `fk_tg_gas_group` FOREIGN KEY (`group_id`) REFERENCES `tg_telegram_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_tracked_messages`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_tracked_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `telegram_message_id` bigint NOT NULL,
  `telegram_user_id` bigint NOT NULL,
  `thread_id` bigint DEFAULT NULL,
  `reference_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_date` datetime NOT NULL,
  `deadline_at` datetime DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `tracking_source` enum('hashtag','command','dashboard','auto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_message` (`group_id`,`telegram_message_id`),
  KEY `idx_tm_date` (`message_date`),
  KEY `idx_tm_group` (`group_id`),
  KEY `idx_tm_ref` (`reference_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_message_responses`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_message_responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tracked_message_id` int NOT NULL,
  `member_id` int NOT NULL,
  `member_telegram_user_id` bigint NOT NULL,
  `response_telegram_message_id` bigint DEFAULT NULL,
  `response_text` text COLLATE utf8mb4_unicode_ci,
  `response_date` datetime DEFAULT NULL,
  `status` enum('replied','pending_review','rejected','unknown','excluded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `matching_method` enum('direct_reply','reference','manual','reaction') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excluded_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by_admin_id` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracked_member` (`tracked_message_id`,`member_telegram_user_id`),
  KEY `fk_mr_member` (`member_id`),
  KEY `fk_mr_reviewer` (`reviewed_by_admin_id`),
  KEY `idx_mr_status` (`status`),
  KEY `idx_mr_tracked` (`tracked_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_telegram_updates`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_telegram_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `update_id` bigint NOT NULL,
  `update_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chat_id` bigint DEFAULT NULL,
  `payload` json NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `update_id` (`update_id`),
  KEY `idx_updates_chat` (`chat_id`),
  KEY `idx_updates_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_login_attempts`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempted_at` datetime NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_login_ip_time` (`ip_address`,`attempted_at`),
  KEY `idx_login_user_time` (`username`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Table structure for `tg_audit_logs`
-------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `tg_audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_audit_admin` (`admin_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------
   Default Administrator Accounts (admin & superadmin)
   Default password for all accounts: kaldis123
   Email domain: @kaldisbunna.et
-------------------------------------------------------- */
INSERT IGNORE INTO `tg_admins` (`id`, `username`, `email`, `password_hash`, `role`, `department`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', 'superadmin@kaldisbunna.et', '$2y$10$/XvOUnL/6EU6Q9pbUZE12.4eXHxLTBYqbcE/j2YxYaT5tIuwUCvR.', 'super_admin', NULL, 1, NOW(), NOW()),
(2, 'admin', 'admin@kaldisbunna.et', '$2y$10$/XvOUnL/6EU6Q9pbUZE12.4eXHxLTBYqbcE/j2YxYaT5tIuwUCvR.', 'super_admin', NULL, 1, NOW(), NOW());

/* --------------------------------------------------------
   Default Authorized Senders by Group
-------------------------------------------------------- */
INSERT IGNORE INTO `tg_group_authorized_senders` (`group_id`, `telegram_user_id`, `full_name`, `username`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(21, 814523272, 'Bereket', 'bereket_kaldis', 'Supervisor', 1, NOW(), NOW()),
(21, 1626288394, 'Berhanu Seyoum', 'bere_sender2', 'Team Lead', 1, NOW(), NOW()),
(21, 6492922880, 'KALDI\'S Coffee', 'kaldis_official', 'Official Channel', 1, NOW(), NOW()),
(65, 814523272, 'Bereket', 'bereket_kaldis', 'Airport Manager', 1, NOW(), NOW()),
(65, 6492922880, 'KALDI\'S Coffee', 'kaldis_official', 'Official Channel', 1, NOW(), NOW());

/* --------------------------------------------------------
   Default System Configuration Settings
-------------------------------------------------------- */
INSERT IGNORE INTO `tg_system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('app_retention_days', '180', NOW()),
('telegram_authorized_user_ids', '814523272, 1626288394, 6492922880', NOW()),
('telegram_bot_username', 'kaldis_tracker_bot', NOW()),
('telegram_enable_reactions', '0', NOW()),
('telegram_reaction_counts_as_replied', '0', NOW()),
('telegram_track_all_messages', '1', NOW()),
('telegram_track_command', '/track', NOW()),
('telegram_track_hashtag', '#track', NOW()),
('telegram_tracking_mode', 'all_authorized', NOW());

SET FOREIGN_KEY_CHECKS = 1;
