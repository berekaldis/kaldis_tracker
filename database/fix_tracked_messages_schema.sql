-- Kaldis Coffee Tracker - Table Schema Repair Script
-- Database: kaldisbp_ict

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tracked Messages Table
ALTER TABLE `tg_tracked_messages` ADD COLUMN IF NOT EXISTS `telegram_user_id` BIGINT NOT NULL DEFAULT 0 AFTER `telegram_message_id`;
ALTER TABLE `tg_tracked_messages` ADD COLUMN IF NOT EXISTS `deadline_at` DATETIME NULL AFTER `message_date`;
ALTER TABLE `tg_tracked_messages` MODIFY COLUMN `tracking_source` ENUM('hashtag', 'command', 'dashboard', 'auto') NOT NULL DEFAULT 'auto';

-- 2. Message Responses Table
ALTER TABLE `tg_message_responses` ADD COLUMN IF NOT EXISTS `member_telegram_user_id` BIGINT NOT NULL DEFAULT 0 AFTER `member_id`;
ALTER TABLE `tg_message_responses` ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME NULL AFTER `reviewed_by_admin_id`;
ALTER TABLE `tg_message_responses` ADD COLUMN IF NOT EXISTS `excluded_reason` TEXT NULL AFTER `matching_method`;
ALTER TABLE `tg_message_responses` MODIFY COLUMN `status` ENUM('replied', 'pending_review', 'rejected', 'unknown', 'excluded') NOT NULL DEFAULT 'unknown';

-- 3. Members Table
ALTER TABLE `tg_members` ADD COLUMN IF NOT EXISTS `telegram_user_id` BIGINT NOT NULL DEFAULT 0 AFTER `id`;

-- 4. Authorized Senders by Group
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
  KEY `idx_gas_user` (`telegram_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
