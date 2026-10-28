-- Migration 002: Super Admin Role & System Settings Store
-- Kaldis Coffee Telegram Reply Tracker (v2)

-- 1. Modify admins role column to support 'super_admin'
ALTER TABLE `admins` 
MODIFY COLUMN `role` ENUM('super_admin', 'owner', 'viewer') NOT NULL DEFAULT 'viewer';

-- 2. Create System Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(64) PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Upgrade existing 'admin' user to super_admin
UPDATE `admins` SET `role` = 'super_admin' WHERE `username` = 'admin';

-- 4. Create dedicated superadmin account if not existing
INSERT IGNORE INTO `admins` (`username`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`)
VALUES (
    'superadmin', 
    'superadmin@kaldiscoffee.com', 
    '$2y$10$FERebuoJemShCct.1GaGfOIlajy40IVNreSzwJMc7Q.W3IWCF2ZNu', -- KaldisSuperAdmin2026!
    'super_admin', 
    1, 
    UTC_TIMESTAMP(), 
    UTC_TIMESTAMP()
);
