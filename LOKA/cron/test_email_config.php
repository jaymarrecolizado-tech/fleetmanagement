<?php
/**
 * LOKA - Email Configuration Test & Setup
 * 
 * Run this script to test and configure email settings
 * 
 * Usage: php cron/test_email_config.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/EmailQueue.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== LOKA EMAIL CONFIGURATION TEST ===\n\n";

// Check current configuration
echo "CURRENT CONFIGURATION:\n";
echo "  MAIL_ENABLED: " . (MAIL_ENABLED ? 'Yes' : 'No') . "\n";
echo "  MAIL_HOST: " . (MAIL_HOST ?: 'NOT SET') . "\n";
echo "  MAIL_PORT: " . MAIL_PORT . "\n";
echo "  MAIL_USERNAME: " . (MAIL_USERNAME ? 'Set (' . MAIL_USERNAME . ')' : 'NOT SET') . "\n";
echo "  MAIL_PASSWORD: " . (MAIL_PASSWORD ? 'Set (hidden)' : 'NOT SET') . "\n";
echo "  MAIL_ENCRYPTION: " . MAIL_ENCRYPTION . "\n";
echo "  MAIL_FROM_ADDRESS: " . (MAIL_FROM_ADDRESS ?: 'NOT SET') . "\n";
echo "  MAIL_FROM_NAME: " . MAIL_FROM_NAME . "\n";

// Check queue status
echo "\nEMAIL QUEUE STATUS:\n";
$stats = db()->fetchAll('SELECT status, COUNT(*) as count FROM email_queue GROUP BY status');
foreach ($stats as $s) {
    echo "  " . ucfirst($s->status) . ": {$s->count}\n";
}

$pendingCount = db()->fetchColumn('SELECT COUNT(*) FROM email_queue WHERE status = ?', ['pending']);
echo "  Total pending: $pendingCount\n";

if ($pendingCount > 0) {
    $oldest = db()->fetch('SELECT created_at FROM email_queue WHERE status = ? ORDER BY created_at ASC LIMIT 1', ['pending']);
    echo "  Oldest pending email: " . $oldest->created_at . "\n";
}

// Test email sending (if configured)
echo "\n";

if (empty(MAIL_HOST) || empty(MAIL_USERNAME) || empty(MAIL_FROM_ADDRESS)) {
    echo "❌ EMAIL NOT CONFIGURED!\n";
    echo "\nTo configure email, edit: config/mail.php\n";
    echo "\nFor Gmail:\n";
    echo "  MAIL_HOST: smtp.gmail.com\n";
    echo "  MAIL_PORT: 587\n";
    echo "  MAIL_USERNAME: your-email@gmail.com\n";
    echo "  MAIL_PASSWORD: your-app-password (not your login password!)\n";
    echo "  MAIL_FROM_ADDRESS: your-email@gmail.com\n";
    echo "  MAIL_ENCRYPTION: tls\n";
    echo "\nTo create an App Password:\n";
    echo "  1. Go to: https://myaccount.google.com/security\n";
    echo "  2. Enable 2-Step Verification\n";
    echo "  3. Go to: https://myaccount.google.com/apppasswords\n";
    echo "  4. Create a new app password for 'Mail'\n";
    echo "  5. Use that 16-digit password as MAIL_PASSWORD\n";
} else {
    echo "Testing email connection...\n";
    
    try {
        $mailer = new Mailer();
        
        // Get a test email address
        $testEmail = MAIL_FROM_ADDRESS; // Send to yourself for testing
        
        $result = $mailer->send(
            $testEmail,
            'LOKA Email Test',
            '<html><body><h1>Email Test Successful!</h1><p>This is a test email from LOKA Fleet Management System.</p></body></html>',
            'LOKA Test',
            true
        );
        
        if ($result) {
            echo "✅ Email sent successfully to $testEmail!\n";
        } else {
            echo "❌ Email sending failed!\n";
            $errors = $mailer->getErrors();
            foreach ($errors as $e) {
                echo "   Error: $e\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\n=== EMAIL QUEUE MANAGEMENT ===\n";
echo "To process pending emails:\n";
echo "  Windows: Run process_email_queue.bat\n";
echo "  Linux: Run: php /path/to/LOKA/cron/process_queue.php\n";
echo "\nTo process emails now:\n";

// Process a few pending emails
if ($pendingCount > 0) {
    echo "Processing up to 5 pending emails...\n";
    $queue = new EmailQueue();
    $results = $queue->process(5);
    echo "  Sent: {$results['sent']}\n";
    echo "  Failed: {$results['failed']}\n";
}

echo "\nDone!\n";
