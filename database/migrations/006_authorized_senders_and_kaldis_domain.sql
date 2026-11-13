-- Kaldis Coffee Telegram Reply Tracker (v2)
-- Migration 006: Authorized Senders by Groups & Group Authorizer Role

-- Drop non-prefixed table if created by previous raw run
DROP TABLE IF EXISTS `group_authorized_senders`;

-- 1. Modify admins role column to support 'group_authorizer'
ALTER TABLE `admins`
MODIFY COLUMN `role` ENUM('super_admin', 'owner', 'group_authorizer', 'user', 'viewer') NOT NULL DEFAULT 'group_authorizer';

-- 2. Create group_authorized_senders table
CREATE TABLE IF NOT EXISTS `group_authorized_senders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `telegram_user_id` BIGINT NOT NULL,
  `full_name` VARCHAR(255) NULL,
  `username` VARCHAR(100) NULL,
  `notes` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_gas_group_user` (`group_id`, `telegram_user_id`),
  KEY `fk_gas_group` (`group_id`),
  KEY `idx_gas_user` (`telegram_user_id`),
  CONSTRAINT `fk_tg_gas_group` FOREIGN KEY (`group_id`) REFERENCES `telegram_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed initial group authorized senders from known active groups
INSERT IGNORE INTO `group_authorized_senders` (`group_id`, `telegram_user_id`, `full_name`, `username`, `notes`, `is_active`, `created_at`, `updated_at`)
SELECT 21, 814523272, 'Bereket', 'bereket_kaldis', 'Supervisor', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM `telegram_groups` WHERE id = 21);

INSERT IGNORE INTO `group_authorized_senders` (`group_id`, `telegram_user_id`, `full_name`, `username`, `notes`, `is_active`, `created_at`, `updated_at`)
SELECT 21, 1626288394, 'Berhanu Seyoum', 'bere_sender2', 'Team Lead', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM `telegram_groups` WHERE id = 21);

INSERT IGNORE INTO `group_authorized_senders` (`group_id`, `telegram_user_id`, `full_name`, `username`, `notes`, `is_active`, `created_at`, `updated_at`)
SELECT 21, 6492922880, 'KALDI\'S Coffee', 'kaldis_official', 'Official Channel', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM `telegram_groups` WHERE id = 21);

INSERT IGNORE INTO `group_authorized_senders` (`group_id`, `telegram_user_id`, `full_name`, `username`, `notes`, `is_active`, `created_at`, `updated_at`)
SELECT 65, 814523272, 'Bereket', 'bereket_kaldis', 'Airport Manager', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM `telegram_groups` WHERE id = 65);

INSERT IGNORE INTO `group_authorized_senders` (`group_id`, `telegram_user_id`, `full_name`, `username`, `notes`, `is_active`, `created_at`, `updated_at`)
SELECT 65, 6492922880, 'KALDI\'S Coffee', 'kaldis_official', 'Official Channel', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM `telegram_groups` WHERE id = 65);

-- 4. Update default admin emails to @kaldisbunna.et and set default password to 'kaldis123'
UPDATE `admins` 
SET `email` = 'superadmin@kaldisbunna.et', 
    `password_hash` = '$2y$10$/XvOUnL/6EU6Q9pbUZE12.4eXHxLTBYqbcE/j2YxYaT5tIuwUCvR.'
WHERE `username` = 'superadmin';

UPDATE `admins` 
SET `email` = 'admin@kaldisbunna.et', 
    `password_hash` = '$2y$10$/XvOUnL/6EU6Q9pbUZE12.4eXHxLTBYqbcE/j2YxYaT5tIuwUCvR.'
WHERE `username` = 'admin';
