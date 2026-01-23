-- Add archiving and soft delete support to notifications
ALTER TABLE notifications ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read;
ALTER TABLE notifications ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER created_at;

-- Index for performance
CREATE INDEX idx_notifications_status ON notifications (user_id, is_read, is_archived, deleted_at);
