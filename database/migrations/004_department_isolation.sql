-- Kaldis Coffee Telegram Reply Tracker (v2)
-- Migration 004: Multi-Department Group Isolation & User Access Control

-- 1. Add department column to telegram_groups table
ALTER TABLE `telegram_groups`
ADD COLUMN `department` VARCHAR(100) NOT NULL DEFAULT 'General' AFTER `type`,
ADD INDEX `idx_groups_department` (`department`);

-- 2. Add department column to admins table
-- NULL or 'All' means full access across all departments (Super Admin / Corporate)
ALTER TABLE `admins`
ADD COLUMN `department` VARCHAR(100) NULL DEFAULT NULL AFTER `role`,
ADD INDEX `idx_admins_department` (`department`);
