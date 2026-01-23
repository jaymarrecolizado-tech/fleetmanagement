-- COMBINED MIGRATION SCRIPT (ultra.sql)
-- This file contains all migration scripts from 001 to 006.

-- From: migrations/001_security_tables.sql
-- LOKA Security Tables Migration
-- Run this SQL to create the security-related tables

-- Rate Limiting Table
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL,
    `identifier` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_action_identifier` (`action`, `identifier`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Logs Table
CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event` VARCHAR(100) NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `details` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_event` (`event`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_ip_address` (`ip_address`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add account lockout columns to users table if not exists
ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT UNSIGNED DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `last_failed_login` DATETIME NULL;

-- From: migrations/002_email_queue.sql
-- Email Queue Table
-- Stores emails for background processing

CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `to_email` VARCHAR(255) NOT NULL,
    `to_name` VARCHAR(255) NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `template` VARCHAR(50) NULL,
    `priority` TINYINT UNSIGNED DEFAULT 5 COMMENT '1=highest, 10=lowest',
    `attempts` TINYINT UNSIGNED DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED DEFAULT 3,
    `status` ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    `error_message` TEXT NULL,
    `scheduled_at` DATETIME NULL COMMENT 'Send after this time',
    `sent_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_scheduled` (`scheduled_at`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- From: migrations/003_workflow_selection.sql
-- Workflow Selection Feature
-- Allows requesters to choose approvers and save workflow configurations

-- Add approver columns to requests table
ALTER TABLE `requests` 
ADD COLUMN `approver_id` INT UNSIGNED NULL AFTER `department_id`,
ADD COLUMN `motorpool_head_id` INT UNSIGNED NULL AFTER `approver_id`,
ADD INDEX `idx_approver` (`approver_id`),
ADD INDEX `idx_motorpool_head` (`motorpool_head_id`);

-- Create saved workflows table
CREATE TABLE IF NOT EXISTS `saved_workflows` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `approver_id` INT UNSIGNED NOT NULL,
    `motorpool_head_id` INT UNSIGNED NOT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_default` (`user_id`, `is_default`),
    UNIQUE KEY `unique_user_workflow` (`user_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- From: migrations/004_notification_enhancements.sql
-- Add archiving and soft delete support to notifications
ALTER TABLE notifications ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read;
ALTER TABLE notifications ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER created_at;

-- Index for performance
CREATE INDEX idx_notifications_status ON notifications (user_id, is_read, is_archived, deleted_at);

-- From: sql/migrations/005_flexible_drivers.sql
-- Add requested_driver_id column to requests table
ALTER TABLE requests ADD COLUMN requested_driver_id INT UNSIGNED NULL AFTER driver_id;

-- Add foreign key constraint
ALTER TABLE requests 
ADD CONSTRAINT fk_requests_requested_driver 
FOREIGN KEY (requested_driver_id) REFERENCES drivers(id) ON DELETE SET NULL;

-- Add index for performance
CREATE INDEX idx_requests_requested_driver ON requests(requested_driver_id);

-- From: sql/migrations/006_passenger_enhancements.sql
-- Enhance request_passengers table for guest support
ALTER TABLE request_passengers MODIFY COLUMN user_id INT UNSIGNED NULL;
ALTER TABLE request_passengers ADD COLUMN guest_name VARCHAR(100) NULL AFTER user_id;
