-- Kaldis Coffee Telegram Reply Tracker (v2)
-- Migration 001: Initial Schema
-- Character set: utf8mb4 for complete emoji and Amharic language support

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('owner', 'viewer') NOT NULL DEFAULT 'viewer',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Login Rate Limiting Table
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `attempted_at` DATETIME NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_login_ip_time` (`ip_address`, `attempted_at`),
    INDEX `idx_login_user_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Telegram Groups Table
CREATE TABLE IF NOT EXISTS `telegram_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `telegram_chat_id` BIGINT NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `username` VARCHAR(100) NULL,
    `type` VARCHAR(32) NOT NULL DEFAULT 'supergroup',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `bot_status` ENUM('active', 'restricted', 'left', 'kicked', 'unknown') NOT NULL DEFAULT 'unknown',
    `bot_is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `bot_can_read_all_messages` TINYINT(1) NOT NULL DEFAULT 0,
    `last_health_check_at` DATETIME NULL,
    `last_update_at` DATETIME NULL,
    `previous_chat_id` BIGINT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_groups_active` (`is_active`),
    INDEX `idx_groups_chat_id` (`telegram_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Telegram Members Master Table
CREATE TABLE IF NOT EXISTS `members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `telegram_user_id` BIGINT NOT NULL UNIQUE,
    `full_name` VARCHAR(255) NOT NULL,
    `username` VARCHAR(100) NULL,
    `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_members_user_id` (`telegram_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Group Members Junction Table
CREATE TABLE IF NOT EXISTS `group_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `member_id` INT NOT NULL,
    `role` ENUM('member', 'admin', 'creator') NOT NULL DEFAULT 'member',
    `joined_at` DATETIME NOT NULL,
    `left_at` DATETIME NULL,
    `source` ENUM('imported', 'seen_in_group', 'admin_added') NOT NULL DEFAULT 'seen_in_group',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uq_group_member` (`group_id`, `member_id`),
    CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `telegram_groups` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gm_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
    INDEX `idx_gm_joined` (`joined_at`),
    INDEX `idx_gm_left` (`left_at`),
    INDEX `idx_gm_active` (`group_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tracked Messages Table
CREATE TABLE IF NOT EXISTS `tracked_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `telegram_message_id` BIGINT NOT NULL,
    `telegram_user_id` BIGINT NOT NULL,
    `thread_id` BIGINT NULL,
    `reference_code` VARCHAR(64) NULL,
    `message_text` TEXT NOT NULL,
    `message_date` DATETIME NOT NULL,
    `edited_at` DATETIME NULL,
    `tracking_source` ENUM('hashtag', 'command', 'dashboard') NOT NULL DEFAULT 'hashtag',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uq_group_message` (`group_id`, `telegram_message_id`),
    CONSTRAINT `fk_tm_group` FOREIGN KEY (`group_id`) REFERENCES `telegram_groups` (`id`) ON DELETE RESTRICT,
    INDEX `idx_tm_date` (`message_date`),
    INDEX `idx_tm_group` (`group_id`),
    INDEX `idx_tm_ref` (`reference_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Message Responses Table
CREATE TABLE IF NOT EXISTS `message_responses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tracked_message_id` INT NOT NULL,
    `member_id` INT NOT NULL,
    `member_telegram_user_id` BIGINT NOT NULL,
    `response_telegram_message_id` BIGINT NULL,
    `response_text` TEXT NULL,
    `response_date` DATETIME NULL,
    `status` ENUM('replied', 'pending_review', 'rejected', 'unknown', 'excluded') NOT NULL DEFAULT 'unknown',
    `matching_method` ENUM('direct_reply', 'reference', 'manual', 'reaction') NULL,
    `excluded_reason` TEXT NULL,
    `reviewed_by_admin_id` INT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uq_tracked_member` (`tracked_message_id`, `member_telegram_user_id`),
    CONSTRAINT `fk_mr_tracked` FOREIGN KEY (`tracked_message_id`) REFERENCES `tracked_messages` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mr_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mr_reviewer` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
    INDEX `idx_mr_status` (`status`),
    INDEX `idx_mr_tracked` (`tracked_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Telegram Updates Store (Webhook idempotency & raw debugging)
CREATE TABLE IF NOT EXISTS `telegram_updates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `update_id` BIGINT NOT NULL UNIQUE,
    `update_type` VARCHAR(64) NOT NULL,
    `chat_id` BIGINT NULL,
    `payload` JSON NOT NULL,
    `processed_at` DATETIME NULL,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_updates_chat` (`chat_id`),
    INDEX `idx_updates_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Audit Logs Table
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NULL,
    `action` VARCHAR(64) NOT NULL,
    `entity_type` VARCHAR(64) NULL,
    `entity_id` VARCHAR(64) NULL,
    `details` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
