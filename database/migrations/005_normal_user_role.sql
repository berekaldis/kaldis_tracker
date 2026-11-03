-- Kaldis Coffee Telegram Reply Tracker (v2)
-- Migration 005: Normal User Role and Group-Specific Access Control

-- 1. Modify admins role column to support 'user' (Normal User)
ALTER TABLE `admins`
MODIFY COLUMN `role` ENUM('super_admin', 'owner', 'user', 'viewer') NOT NULL DEFAULT 'user';

-- 2. Add assigned_group_id column to admins table
-- If set, this admin user has exclusive access to ONLY this specific Telegram Group
ALTER TABLE `admins`
ADD COLUMN `assigned_group_id` INT NULL DEFAULT NULL AFTER `department`,
ADD INDEX `idx_admins_assigned_group` (`assigned_group_id`);
