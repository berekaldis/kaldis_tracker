/* ========================================================================= */
/* KALDIS COFFEE TELEGRAM TRACKER - PRODUCTION DATA MIGRATION                */
/* Target Database: kaldisbp_ict                                              */
/* Generated: 2026-11-13 15:20:31 UTC                             */
/* ========================================================================= */

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/* ------------------------------------------------------------------------- */
/* Table Data: tg_admins (2 rows)                                        */
/* Default password: kaldis123, email domain: @kaldisbunna.et                */
/* ------------------------------------------------------------------------- */
INSERT INTO `tg_admins` (`id`, `username`, `email`, `password_hash`, `role`, `department`, `assigned_group_id`, `is_active`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', 'superadmin@kaldisbunna.et', '$2y$10$/XvOUnL/6EU6Q9pbUZE12.4eXHxLTBYqbcE/j2YxYaT5tIuwUCvR.', 'super_admin', NULL, NULL, 1, '2026-11-13 14:34:48', '127.0.0.1', '2026-11-13 13:43:04', '2026-11-13 13:43:04'),
(2, 'admin', 'admin@kaldisbunna.et', '$2y$10$/XvOUnL/6EU6Q9pbUZE12.4eXHxLTBYqbcE/j2YxYaT5tIuwUCvR.', 'super_admin', NULL, NULL, 1, '2026-11-13 15:20:12', '127.0.0.1', '2026-10-28 09:09:01', '2026-10-28 09:09:01')
ON DUPLICATE KEY UPDATE 
  `email` = VALUES(`email`), 
  `password_hash` = VALUES(`password_hash`);

/* ------------------------------------------------------------------------- */
/* Table Data: tg_system_settings (9 rows)                                        */
/* ------------------------------------------------------------------------- */
INSERT IGNORE INTO `tg_system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('telegram_authorized_user_ids', '814523272, 1626288394, 6492922880', '2026-11-13 14:34:48'),
('telegram_bot_token', '9084062791:AAE1iBuTjjdmFoxGwwURN7RmcgK2GF7Z7Mo', '2026-10-29 14:45:39'),
('telegram_bot_username', 'kaldis_reply_tracker_bot', '2026-10-29 14:45:39'),
('telegram_track_all_messages', '1', '2026-11-13 14:34:48'),
('telegram_track_command', '/track', '2026-10-29 14:45:39'),
('telegram_track_hashtag', '#track', '2026-11-13 14:34:49'),
('telegram_tracking_mode', 'all_authorized', '2026-11-13 14:34:48'),
('telegram_webhook_secret', 'Kaldis_hC-DCBlbMlRBYLUhAQaN8DCqorPebPsV', '2026-10-29 14:45:39'),
('telegram_webhook_url', 'https://ict.kaldisbunnaet.com/tgroup/webhook', '2026-10-29 14:45:39');

/* ------------------------------------------------------------------------- */
/* Table Data: tg_telegram_groups (2 rows)                                        */
/* ------------------------------------------------------------------------- */
INSERT IGNORE INTO `tg_telegram_groups` (`id`, `telegram_chat_id`, `title`, `username`, `type`, `department`, `is_active`, `bot_status`, `bot_is_admin`, `bot_can_read_all_messages`, `last_health_check_at`, `last_update_at`, `previous_chat_id`, `created_at`, `updated_at`) VALUES
(21, -1003535567243, 'Project Testing Group', NULL, 'supergroup', 'General', 1, 'active', 1, 0, '2026-10-29 14:53:36', '2026-10-30 08:21:40', NULL, '2026-10-28 13:16:22', '2026-10-30 08:21:40'),
(65, -1004340593090, 'Airport Waiters Team', NULL, 'supergroup', 'General', 1, 'active', 1, 0, '2026-10-29 14:53:38', '2026-11-13 10:54:02', NULL, '2026-10-29 14:48:29', '2026-11-13 10:54:02');

/* ------------------------------------------------------------------------- */
/* Table Data: tg_members (28 rows)                                        */
/* ------------------------------------------------------------------------- */
INSERT IGNORE INTO `tg_members` (`id`, `telegram_user_id`, `full_name`, `username`, `is_bot`, `created_at`, `updated_at`) VALUES
(1, 555000111, 'Abebe Bikila', 'abebikila', 0, '2026-10-28 09:11:05', '2026-11-13 15:20:12'),
(2, 555000222, 'Almaz Ayana', 'almazayana', 0, '2026-10-28 09:11:05', '2026-11-13 15:20:12'),
(3, 123456789, 'Bereket', 'bereket_kaldis', 0, '2026-10-28 09:11:05', '2026-10-28 14:00:08'),
(4, 6492922880, 'KALDI\'S Coffee', 'kaldis_official', 0, '2026-10-28 13:16:22', '2026-11-13 14:34:48'),
(5, 814523272, 'Bereket', 'bereket_kaldis', 0, '2026-10-28 13:16:34', '2026-11-13 15:20:12'),
(6, 1626288394, 'Berhanu Seyoum', 'bere_sender2', 0, '2026-10-28 13:37:14', '2026-11-13 14:34:48'),
(7, 777111001, 'Tigist', NULL, 0, '2026-10-28 14:03:20', '2026-11-13 14:34:47'),
(8, 777222002, 'Dawit', NULL, 0, '2026-10-28 14:03:20', '2026-11-13 14:34:47'),
(9, 333444555, 'Sara', NULL, 0, '2026-10-29 14:05:19', '2026-11-13 14:34:48'),
(10, 777999111, 'Abebe Team Member', 'abebe_kaldis', 0, '2026-10-29 14:23:49', '2026-11-13 14:34:48'),
(11, 999888777, 'Regular Member', 'reg_user', 0, '2026-10-29 14:23:49', '2026-11-13 14:34:48'),
(16, 5301093367, 'Airrp kald', 'kalAirpot', 0, '2026-10-29 14:56:15', '2026-10-30 08:06:57'),
(17, 555666777, 'Tadesse', NULL, 0, '2026-10-29 14:56:40', '2026-11-13 14:34:48'),
(18, 555666888, 'Selamawit', NULL, 0, '2026-10-29 14:56:40', '2026-11-13 14:34:48'),
(26, 381784049, 'Μυστήριο Ichthus 🀄', 'Yebeatiye', 0, '2026-10-30 07:23:44', '2026-11-13 10:54:01'),
(27, 8294281777, 'Rekik A', 'Rekik86', 0, '2026-10-30 07:23:44', '2026-10-30 07:23:44'),
(28, 6611223344, 'Kassahun Waiter', 'kassa_airport', 0, '2026-10-30 07:27:04', '2026-11-13 14:34:45'),
(29, 77001122, 'Abebe Bikila', NULL, 0, '2026-10-30 07:27:05', '2026-11-13 14:34:46'),
(30, -3129746451, 'Tigist Alemu', 'tigist_kaldis', 0, '2026-10-30 07:27:05', '2026-11-13 14:34:46'),
(31, 77003344, 'Dawit Kebede', NULL, 0, '2026-10-30 07:27:05', '2026-11-13 14:34:46'),
(32, -561414099, 'Marta Tesfaye', 'marta_t', 0, '2026-10-30 07:27:05', '2026-11-13 14:34:46'),
(53, 5125622665, 'dani Dave', NULL, 0, '2026-10-30 07:55:38', '2026-10-30 07:55:38'),
(56, 973642925, 'Tseday', NULL, 0, '2026-10-30 12:12:56', '2026-11-02 07:45:40'),
(65, 1032297647, 'Addis', NULL, 0, '2026-11-13 10:54:00', '2026-11-13 10:54:00'),
(66, 5888725618, 'mesfen gezahgen', 'mafiy27', 0, '2026-11-13 10:54:00', '2026-11-13 10:54:01'),
(67, 1612771932, 'Andualm Alemyehu Asfw', NULL, 0, '2026-11-13 10:54:01', '2026-11-13 10:54:01'),
(68, 7954660867, 'Mesfin Gezajgen', NULL, 0, '2026-11-13 10:54:01', '2026-11-13 10:54:01'),
(69, 5003387853, 'Beti K', NULL, 0, '2026-11-13 10:54:02', '2026-11-13 10:54:02');

/* ------------------------------------------------------------------------- */
/* Table Data: tg_group_members (14 rows)                                        */
/* ------------------------------------------------------------------------- */
INSERT IGNORE INTO `tg_group_members` (`id`, `group_id`, `member_id`, `role`, `joined_at`, `left_at`, `source`, `is_active`, `created_at`, `updated_at`) VALUES
(31, 21, 4, 'member', '2026-10-28 13:16:22', NULL, 'seen_in_group', 1, '2026-10-28 13:16:22', '2026-10-28 13:16:22'),
(32, 21, 5, 'admin', '2026-10-28 13:16:34', NULL, 'seen_in_group', 1, '2026-10-28 13:16:34', '2026-10-28 13:40:54'),
(33, 21, 6, 'admin', '2026-10-28 13:37:14', NULL, 'seen_in_group', 1, '2026-10-28 13:37:14', '2026-10-29 13:55:40'),
(120, 65, 4, 'admin', '2026-10-29 14:48:29', NULL, 'seen_in_group', 1, '2026-10-29 14:48:30', '2026-10-30 08:06:57'),
(122, 65, 16, 'creator', '2026-10-29 14:56:15', NULL, 'seen_in_group', 1, '2026-10-29 14:56:15', '2026-10-30 08:06:57'),
(146, 65, 26, 'member', '2026-10-29 16:11:02', NULL, 'seen_in_group', 1, '2026-10-30 07:23:44', '2026-10-30 07:23:44'),
(147, 65, 27, 'member', '2026-10-30 06:23:28', NULL, 'seen_in_group', 1, '2026-10-30 07:23:44', '2026-10-30 07:23:44'),
(210, 65, 53, 'member', '2026-10-30 07:55:38', NULL, 'seen_in_group', 1, '2026-10-30 07:55:38', '2026-10-30 07:55:38'),
(213, 65, 56, 'member', '2026-10-30 12:12:56', NULL, 'seen_in_group', 1, '2026-10-30 12:12:56', '2026-10-30 12:12:56'),
(238, 65, 65, 'member', '2026-11-12 12:32:44', NULL, 'seen_in_group', 1, '2026-11-13 10:54:00', '2026-11-13 10:54:00'),
(239, 65, 66, 'member', '2026-11-12 12:35:21', NULL, 'seen_in_group', 1, '2026-11-13 10:54:00', '2026-11-13 10:54:00'),
(240, 65, 67, 'member', '2026-11-12 12:49:14', NULL, 'seen_in_group', 1, '2026-11-13 10:54:01', '2026-11-13 10:54:01'),
(241, 65, 68, 'member', '2026-11-12 13:12:39', NULL, 'seen_in_group', 1, '2026-11-13 10:54:01', '2026-11-13 10:54:01'),
(242, 65, 69, 'member', '2026-11-12 15:44:40', NULL, 'seen_in_group', 1, '2026-11-13 10:54:02', '2026-11-13 10:54:02');

/* ------------------------------------------------------------------------- */
/* Table Data: tg_tracked_messages (12 rows)                                        */
/* ------------------------------------------------------------------------- */
INSERT IGNORE INTO `tg_tracked_messages` (`id`, `group_id`, `telegram_message_id`, `telegram_user_id`, `thread_id`, `reference_code`, `message_text`, `message_date`, `edited_at`, `tracking_source`, `is_active`, `created_at`, `updated_at`) VALUES
(11, 21, 1, 814523272, NULL, NULL, 'Welcome Familyy', '2026-10-28 10:35:00', NULL, 'dashboard', 1, '2026-10-28 13:35:30', '2026-10-28 13:35:30'),
(26, 21, 2, 814523272, NULL, NULL, 'Yoooooooooo How Are You', '2026-10-29 10:35:00', NULL, 'dashboard', 1, '2026-10-29 13:35:39', '2026-10-29 13:35:39'),
(27, 21, 10, 814523272, 9, NULL, 'what?', '2026-10-29 13:33:06', NULL, 'auto', 1, '2026-10-29 13:40:05', '2026-10-29 13:40:05'),
(28, 21, 11, 814523272, NULL, NULL, '/track', '2026-10-29 13:34:47', NULL, 'auto', 1, '2026-10-29 13:40:07', '2026-10-29 13:40:07'),
(29, 21, 12, 814523272, NULL, '#Yoooooooooo', '#Yoooooooooo How Are You', '2026-10-29 13:36:14', NULL, 'auto', 1, '2026-10-29 13:40:08', '2026-10-29 13:40:08'),
(30, 21, 15, 814523272, NULL, NULL, 'Team, please confirm attendance for tomorrow', '2026-10-29 13:49:56', NULL, 'auto', 1, '2026-10-29 13:49:56', '2026-10-29 13:49:56'),
(31, 21, 20, 814523272, NULL, NULL, 'What Country Of This ?', '2026-10-29 13:55:01', NULL, 'auto', 1, '2026-10-29 13:55:02', '2026-10-29 13:55:02'),
(74, 21, 24, 6492922880, NULL, NULL, 'My Name Is Kaldis Coffee', '2026-10-29 14:44:15', NULL, 'auto', 1, '2026-10-29 14:44:15', '2026-10-29 14:44:15'),
(75, 21, 25, 814523272, NULL, NULL, 'So What Can I Help You', '2026-10-29 14:44:40', NULL, 'auto', 1, '2026-10-29 14:44:40', '2026-10-29 14:44:40'),
(76, 21, 27, 814523272, NULL, NULL, 'Is There any problem?', '2026-10-29 14:45:48', NULL, 'auto', 1, '2026-10-29 14:45:48', '2026-10-29 14:45:48'),
(107, 21, 32, 814523272, NULL, NULL, 'HI Evryone', '2026-10-30 08:20:31', NULL, 'auto', 1, '2026-10-30 08:20:31', '2026-10-30 08:20:31'),
(108, 21, 34, 6492922880, NULL, NULL, 'I dont Know', '2026-10-30 08:21:40', NULL, 'auto', 1, '2026-10-30 08:21:40', '2026-10-30 08:21:40');

/* ------------------------------------------------------------------------- */
/* Table Data: tg_message_responses (7 rows)                                        */
/* ------------------------------------------------------------------------- */
INSERT IGNORE INTO `tg_message_responses` (`id`, `tracked_message_id`, `member_id`, `member_telegram_user_id`, `response_telegram_message_id`, `response_text`, `response_date`, `status`, `matching_method`, `excluded_reason`, `reviewed_by_admin_id`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(10, 29, 6, 1626288394, 13, 'Yooo are you Pic', '2026-10-29 13:36:39', 'replied', 'direct_reply', NULL, NULL, NULL, '2026-10-29 13:40:09', '2026-10-29 13:40:09'),
(11, 30, 4, 6492922880, 16, 'I Request Leave', '2026-10-29 13:50:40', 'replied', 'direct_reply', NULL, NULL, NULL, '2026-10-29 13:50:40', '2026-10-29 13:50:40'),
(12, 30, 6, 1626288394, 18, 'Ok I Will Do', '2026-10-29 13:52:15', 'replied', 'direct_reply', NULL, NULL, NULL, '2026-10-29 13:52:16', '2026-10-29 13:52:16'),
(13, 31, 6, 1626288394, 22, 'Confirmed verbally / offline', '2026-10-29 14:12:05', 'replied', 'manual', NULL, 1, '2026-10-29 14:12:05', '2026-10-29 13:56:22', '2026-10-29 14:12:05'),
(66, 76, 4, 6492922880, 29, 'Yes I Have', '2026-10-29 14:46:39', 'replied', 'direct_reply', NULL, NULL, NULL, '2026-10-29 14:46:39', '2026-10-29 14:46:39'),
(67, 76, 6, 1626288394, 30, 'no', '2026-10-29 14:47:38', 'replied', 'direct_reply', NULL, NULL, NULL, '2026-10-29 14:47:38', '2026-10-29 14:47:38'),
(104, 107, 4, 6492922880, 33, 'Hi All How Are You', '2026-10-30 08:21:20', 'replied', 'direct_reply', NULL, NULL, NULL, '2026-10-30 08:21:20', '2026-10-30 08:21:20');

/* ------------------------------------------------------------------------- */
/* Table Structure & Data: tg_group_authorized_senders                       */
/* ------------------------------------------------------------------------- */
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

INSERT IGNORE INTO `tg_group_authorized_senders` (`group_id`, `telegram_user_id`, `full_name`, `username`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(21, 814523272, 'Bereket', 'bereket_kaldis', 'Supervisor', 1, NOW(), NOW()),
(21, 1626288394, 'Berhanu Seyoum', 'bere_sender2', 'Team Lead', 1, NOW(), NOW()),
(21, 6492922880, 'KALDI\'S Coffee', 'kaldis_official', 'Official Channel', 1, NOW(), NOW()),
(65, 814523272, 'Bereket', 'bereket_kaldis', 'Airport Manager', 1, NOW(), NOW()),
(65, 6492922880, 'KALDI\'S Coffee', 'kaldis_official', 'Official Channel', 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

/* Migration data successfully prepared for kaldisbp_ict */