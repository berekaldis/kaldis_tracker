-- Migration 003: Conversation Tracking Mode Support
-- Kaldis Coffee Telegram Reply Tracker (v2)

-- 1. Update tracking_source ENUM to include 'auto'
ALTER TABLE `tracked_messages` 
MODIFY COLUMN `tracking_source` ENUM('hashtag', 'command', 'dashboard', 'auto') NOT NULL DEFAULT 'auto';

-- 2. Add default tracking mode settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`)
VALUES ('telegram_tracking_mode', 'all_authorized', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE `setting_value` = 'all_authorized', `updated_at` = UTC_TIMESTAMP();

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`)
VALUES ('telegram_track_all_messages', '1', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE `setting_value` = '1', `updated_at` = UTC_TIMESTAMP();
