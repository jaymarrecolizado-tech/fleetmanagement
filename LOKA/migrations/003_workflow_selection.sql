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
