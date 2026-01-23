<?php
/**
 * Temporary Migration Fix
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();

    echo "Adding columns to notifications table...\n";

    // Check if columns exist first to avoid errors
    $columns = $db->fetchAll("DESCRIBE notifications");
    $existing = array_column($columns, 'Field');

    if (!in_array('is_archived', $existing)) {
        $db->query("ALTER TABLE notifications ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read");
        echo "Added 'is_archived' column.\n";
    }

    if (!in_array('deleted_at', $existing)) {
        $db->query("ALTER TABLE notifications ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER created_at");
        echo "Added 'deleted_at' column.\n";
    }

    // Add index if it doesn't exist
    try {
        $db->query("CREATE INDEX idx_notifications_status ON notifications (user_id, is_read, is_archived, deleted_at)");
        echo "Created index.\n";
    } catch (Exception $e) {
        echo "Index might already exist: " . $e->getMessage() . "\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
