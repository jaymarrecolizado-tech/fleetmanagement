<?php
/**
 * LOKA - Notification Debug Tool
 * Run this to check notification and email queue status
 */

require 'config/database.php';
require 'config/mail.php';
require 'classes/Database.php';
require 'classes/EmailQueue.php';

echo "=== LOKA NOTIFICATION SYSTEM DIAGNOSTIC ===\n\n";

echo "1. EMAIL CONFIGURATION STATUS:\n";
echo "   MAIL_ENABLED: " . (MAIL_ENABLED ? 'Yes' : 'No') . "\n";
echo "   MAIL_HOST: " . MAIL_HOST . "\n";
echo "   MAIL_PORT: " . MAIL_PORT . "\n";
echo "   MAIL_USERNAME: " . (MAIL_USERNAME !== 'your-email@gmail.com' ? 'Set' : 'NOT CONFIGURED') . "\n";
echo "   MAIL_PASSWORD: " . (MAIL_PASSWORD !== 'your-16-digit-app-password' ? 'Set' : 'NOT CONFIGURED') . "\n";
echo "   MAIL_FROM_ADDRESS: " . MAIL_FROM_ADDRESS . "\n";
echo "   MAIL_ENCRYPTION: " . MAIL_ENCRYPTION . "\n";
echo "   isEmailConfigured(): " . (isEmailConfigured() ? 'YES' : 'NO - NEEDS CONFIGURATION') . "\n";
echo "\n";

try {
    $db = Database::getInstance()->getConnection();
    
    echo "2. DATABASE STATUS: Connected\n\n";
    
    echo "3. NOTIFICATION STATS:\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM notifications");
    echo "   Total Notifications: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM notifications WHERE is_read = 0");
    echo "   Unread: " . $stmt->fetchColumn() . "\n\n";
    
    echo "4. EMAIL QUEUE STATUS:\n";
    $stmt = $db->query("SELECT status, COUNT(*) as cnt, MIN(created_at) as oldest FROM email_queue GROUP BY status ORDER BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   " . str_pad($row['status'], 12) . ": " . str_pad($row['cnt'], 5) . " (oldest: " . ($row['oldest'] ?: 'N/A') . ")\n";
    }
    echo "\n";
    
    echo "5. RECENT NOTIFICATIONS (last 10):\n";
    $stmt = $db->query("SELECT n.*, u.name as user_name, u.email FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   [" . $row['created_at'] . "] To: " . ($row['user_name'] ?: 'Unknown') . " | " . $row['title'] . "\n";
    }
    echo "\n";
    
    echo "6. PENDING EMAILS (first 5):\n";
    $stmt = $db->query("SELECT * FROM email_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5");
    $hasPending = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hasPending = true;
        echo "   [#" . $row['id'] . "] To: " . $row['to_email'] . " | " . $row['subject'] . "\n";
    }
    if (!$hasPending) {
        echo "   No pending emails\n";
    }
    echo "\n";
    
    echo "7. FAILED EMAILS:\n";
    $stmt = $db->query("SELECT * FROM email_queue WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 5");
    $hasFailed = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hasFailed = true;
        echo "   [#" . $row['id'] . "] To: " . $row['to_email'] . " | Error: " . substr($row['error_message'], 0, 80) . "\n";
    }
    if (!$hasFailed) {
        echo "   No failed emails\n";
    }
    echo "\n";
    
    echo "8. RECOMMENDED ACTIONS:\n";
    if (!isEmailConfigured()) {
        echo "   ⚠️  CONFIGURE EMAIL: Edit config/mail.php and set MAIL_USERNAME and MAIL_PASSWORD\n";
    }
    $pendingCount = $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
    if ($pendingCount > 0) {
        echo "   ⚠️  PROCESS QUEUE: Run 'php cron/process_queue.php' to send " . $pendingCount . " pending emails\n";
    }
    $failedCount = $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'")->fetchColumn();
    if ($failedCount > 0) {
        echo "   ⚠️  FAILED EMAILS: " . $failedCount . " emails failed. Check error messages above.\n";
    }
    if (isEmailConfigured() && $pendingCount == 0 && $failedCount == 0) {
        echo "   ✅ Email system is configured and working!\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "3. DATABASE STATUS: FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
    echo "   Make sure WAMP MySQL is running and the database exists.\n";
}

echo "=== END DIAGNOSTIC ===\n";
