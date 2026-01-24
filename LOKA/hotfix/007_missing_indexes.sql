-- Migration 007: Add Missing Security & Performance Indexes
-- Created: 2026-01-24 (ULTRATHINK Audit)
-- Purpose: Fix missing indexes identified in security audit

-- Email queue optimization for priority processing
CREATE INDEX idx_email_queue_priority ON email_queue(status, priority ASC, created_at ASC);

-- Notifications optimization for user-specific queries
CREATE INDEX idx_notifications_user_type ON notifications(user_id, type, created_at DESC);

-- Remember tokens optimization for selector lookups
CREATE INDEX idx_remember_tokens_selector ON remember_tokens(selector, expires DESC);

-- Users optimization for login queries
CREATE INDEX idx_users_email_status ON users(email, status);

-- Rate limits optimization for frequent checks
CREATE INDEX idx_rate_limits_action_identifier ON rate_limits(action, identifier, created_at DESC);

-- Security logs optimization for event filtering
CREATE INDEX idx_security_logs_event_date ON security_logs(event, created_at DESC);

-- Dead letter queue for failed emails (new table)
CREATE TABLE IF NOT EXISTS dead_letter_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_queue_id INT,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255),
    subject VARCHAR(500),
    body TEXT,
    template VARCHAR(50),
    error_message TEXT,
    attempts INT DEFAULT 0,
    failed_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_at (failed_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log protection trigger (prevent modifications)
DELIMITER //
CREATE TRIGGER prevent_audit_log_update
BEFORE UPDATE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Audit logs cannot be modified';
END//
DELIMITER ;

DELIMITER //
CREATE TRIGGER prevent_audit_log_delete
BEFORE DELETE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Audit logs cannot be deleted. Use soft delete if needed.';
END//
DELIMITER ;

-- Log this migration
INSERT INTO schema_migrations (migration, executed_at) VALUES ('007_missing_indexes', NOW())
ON DUPLICATE KEY UPDATE executed_at = NOW();
