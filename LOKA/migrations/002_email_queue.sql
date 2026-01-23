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
