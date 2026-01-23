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
