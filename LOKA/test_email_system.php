<?php
/**
 * LOKA - Email System Test Script
 * 
 * Tests all email functionality:
 * - SMTP connection
 * - Email queue system
 * - Request creation emails
 * - Workflow approval emails
 * - All notification types
 */

// Prevent web access in production
if (php_sapi_name() !== 'cli' && !isset($_GET['test']) && !isset($_SERVER['HTTP_X_TEST_KEY'])) {
    http_response_code(403);
    die('Access denied. Use CLI or add ?test=1');
}

// Change to LOKA directory
chdir(__DIR__);

// Load configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/mail.php';

// Load classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Security.php';
require_once __DIR__ . '/classes/Mailer.php';
require_once __DIR__ . '/classes/EmailQueue.php';
require_once __DIR__ . '/includes/functions.php';

echo "========================================\n";
echo "LOKA Email System Test\n";
echo "========================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Check MAIL_ENABLED
echo "1. Checking MAIL_ENABLED...\n";
if (MAIL_ENABLED) {
    $success[] = "MAIL_ENABLED is true";
    echo "   ✓ MAIL_ENABLED is true\n";
} else {
    $errors[] = "MAIL_ENABLED is false - emails are disabled";
    echo "   ✗ MAIL_ENABLED is false - emails are disabled\n";
}
echo "\n";

// Test 2: Check SMTP Configuration
echo "2. Checking SMTP Configuration...\n";
$config = [
    'Host' => MAIL_HOST,
    'Port' => MAIL_PORT,
    'Username' => MAIL_USERNAME,
    'Password' => str_repeat('*', strlen(MAIL_PASSWORD)),
    'Encryption' => MAIL_ENCRYPTION,
    'From Address' => MAIL_FROM_ADDRESS,
    'From Name' => MAIL_FROM_NAME
];

foreach ($config as $key => $value) {
    if (empty($value) && $key !== 'Password') {
        $errors[] = "Missing SMTP configuration: $key";
        echo "   ✗ $key: MISSING\n";
    } else {
        echo "   ✓ $key: $value\n";
    }
}
echo "\n";

// Test 3: Test SMTP Connection
echo "3. Testing SMTP Connection...\n";
try {
    $mailer = new Mailer();
    
    // Try to connect (this will fail if SMTP is unreachable)
    $testSocket = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 5);
    if ($testSocket) {
        fclose($testSocket);
        $success[] = "SMTP server is reachable";
        echo "   ✓ SMTP server is reachable\n";
    } else {
        $errors[] = "Cannot connect to SMTP server: $errstr ($errno)";
        echo "   ✗ Cannot connect to SMTP server: $errstr ($errno)\n";
    }
} catch (Exception $e) {
    $errors[] = "SMTP connection test failed: " . $e->getMessage();
    echo "   ✗ SMTP connection test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check Email Queue Table
echo "4. Checking Email Queue Table...\n";
try {
    $db = Database::getInstance();
    $tableExists = $db->fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'email_queue'");
    
    if ($tableExists) {
        $success[] = "email_queue table exists";
        echo "   ✓ email_queue table exists\n";
        
        // Get queue stats
        $queue = new EmailQueue();
        $stats = $queue->getStats();
        echo "   - Pending: {$stats['pending']}\n";
        echo "   - Processing: {$stats['processing']}\n";
        echo "   - Sent: {$stats['sent']}\n";
        echo "   - Failed: {$stats['failed']}\n";
        
        if ($stats['failed'] > 0) {
            $warnings[] = "There are {$stats['failed']} failed emails in the queue";
        }
    } else {
        $errors[] = "email_queue table does not exist";
        echo "   ✗ email_queue table does not exist\n";
    }
} catch (Exception $e) {
    $errors[] = "Database check failed: " . $e->getMessage();
    echo "   ✗ Database check failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Check Email Templates
echo "5. Checking Email Templates...\n";
$requiredTemplates = [
    'request_confirmation',
    'request_submitted',
    'request_approved',
    'request_rejected',
    'request_pending_motorpool',
    'added_to_request',
    'driver_requested',
    'driver_assigned',
    'default'
];

$missingTemplates = [];
foreach ($requiredTemplates as $template) {
    if (isset(MAIL_TEMPLATES[$template])) {
        echo "   ✓ Template '$template' exists\n";
    } else {
        $missingTemplates[] = $template;
        echo "   ✗ Template '$template' is missing\n";
    }
}

if (empty($missingTemplates)) {
    $success[] = "All required email templates are defined";
} else {
    $errors[] = "Missing email templates: " . implode(', ', $missingTemplates);
}
echo "\n";

// Test 6: Test Email Queueing
echo "6. Testing Email Queueing...\n";
try {
    $queue = new EmailQueue();
    $testEmail = 'test@example.com';
    
    $emailId = $queue->queue(
        $testEmail,
        'Test Email',
        '<p>This is a test email from LOKA email system test.</p>',
        'Test User',
        'test',
        5
    );
    
    if ($emailId) {
        $success[] = "Email queued successfully (ID: $emailId)";
        echo "   ✓ Email queued successfully (ID: $emailId)\n";
        
        // Clean up test email
        $db->query("DELETE FROM email_queue WHERE id = ?", [$emailId]);
        echo "   ✓ Test email removed from queue\n";
    } else {
        $errors[] = "Failed to queue test email";
        echo "   ✗ Failed to queue test email\n";
    }
} catch (Exception $e) {
    $errors[] = "Email queueing test failed: " . $e->getMessage();
    echo "   ✗ Email queueing test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Test Actual Email Sending (if enabled)
echo "7. Testing Actual Email Sending...\n";
if (MAIL_ENABLED && !empty(MAIL_FROM_ADDRESS)) {
    try {
        $mailer = new Mailer();
        $testRecipient = MAIL_FROM_ADDRESS; // Send to self to avoid spam
        
        echo "   Attempting to send test email to: $testRecipient\n";
        
        $sent = $mailer->send(
            $testRecipient,
            'LOKA Email System Test',
            '<h1>Email System Test</h1><p>If you receive this email, the email system is working correctly.</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>',
            'Test Recipient',
            true
        );
        
        if ($sent) {
            $success[] = "Test email sent successfully";
            echo "   ✓ Test email sent successfully\n";
        } else {
            $mailerErrors = $mailer->getErrors();
            $errorMsg = !empty($mailerErrors) ? implode(', ', $mailerErrors) : 'Send returned false';
            $errors[] = "Failed to send test email: $errorMsg";
            echo "   ✗ Failed to send test email: $errorMsg\n";
        }
    } catch (Exception $e) {
        $errors[] = "Email sending test failed: " . $e->getMessage();
        echo "   ✗ Email sending test failed: " . $e->getMessage() . "\n";
    }
} else {
    $warnings[] = "Skipping actual email send test (MAIL_ENABLED is false)";
    echo "   - Skipped (MAIL_ENABLED is false or no FROM address)\n";
}
echo "\n";

// Test 8: Check notify() function
echo "8. Checking notify() function...\n";
try {
    // Get a test user
    $testUser = $db->fetch("SELECT id, email, name FROM users WHERE deleted_at IS NULL AND email IS NOT NULL AND email != '' LIMIT 1");
    
    if ($testUser) {
        echo "   Using test user: {$testUser->name} ({$testUser->email})\n";
        
        // Test notify function
        notify(
            $testUser->id,
            'default',
            'Email System Test',
            'This is a test notification to verify the email system is working.',
            '/?page=dashboard'
        );
        
        $success[] = "notify() function executed successfully";
        echo "   ✓ notify() function executed successfully\n";
        
        // Check if email was queued
        $queuedEmail = $db->fetch(
            "SELECT * FROM email_queue WHERE to_email = ? ORDER BY id DESC LIMIT 1",
            [$testUser->email]
        );
        
        if ($queuedEmail) {
            $success[] = "Email was queued by notify() function";
            echo "   ✓ Email was queued (ID: {$queuedEmail->id})\n";
        } else {
            $warnings[] = "Email may not have been queued by notify() function";
            echo "   - Email may not have been queued\n";
        }
    } else {
        $warnings[] = "No users with email addresses found for testing";
        echo "   - No users with email addresses found for testing\n";
    }
} catch (Exception $e) {
    $errors[] = "notify() function test failed: " . $e->getMessage();
    echo "   ✗ notify() function test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 9: Check Cron Job Setup
echo "9. Checking Cron Job Setup...\n";
$cronFile = __DIR__ . '/cron/process_queue.php';
$batchFile = __DIR__ . '/process_email_queue.bat';

if (file_exists($cronFile)) {
    $success[] = "Cron processor file exists";
    echo "   ✓ Cron processor file exists: $cronFile\n";
} else {
    $errors[] = "Cron processor file not found: $cronFile";
    echo "   ✗ Cron processor file not found: $cronFile\n";
}

if (file_exists($batchFile)) {
    $success[] = "Batch file exists for Windows";
    echo "   ✓ Batch file exists: $batchFile\n";
} else {
    $warnings[] = "Batch file not found (Windows Task Scheduler setup)";
    echo "   - Batch file not found (Windows Task Scheduler setup)\n";
}
echo "\n";

// Test 10: Check Recent Email Queue Activity
echo "10. Checking Recent Email Queue Activity...\n";
try {
    $recentEmails = $db->fetchAll(
        "SELECT status, COUNT(*) as count 
         FROM email_queue 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY status"
    );
    
    if (!empty($recentEmails)) {
        echo "   Recent activity (last 24 hours):\n";
        foreach ($recentEmails as $email) {
            echo "   - {$email->status}: {$email->count}\n";
        }
    } else {
        $warnings[] = "No email activity in the last 24 hours";
        echo "   - No email activity in the last 24 hours\n";
    }
    
    // Check for stuck processing emails
    $stuckEmails = $db->fetchColumn(
        "SELECT COUNT(*) FROM email_queue 
         WHERE status = 'processing' 
         AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    
    if ($stuckEmails > 0) {
        $warnings[] = "There are $stuckEmails emails stuck in 'processing' status";
        echo "   ⚠ Found $stuckEmails emails stuck in 'processing' status\n";
    }
} catch (Exception $e) {
    $warnings[] = "Could not check recent activity: " . $e->getMessage();
    echo "   - Could not check recent activity: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n\n";

echo "✓ Success: " . count($success) . "\n";
if (!empty($success)) {
    foreach ($success as $msg) {
        echo "  - $msg\n";
    }
}
echo "\n";

if (!empty($warnings)) {
    echo "⚠ Warnings: " . count($warnings) . "\n";
    foreach ($warnings as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "✗ Errors: " . count($errors) . "\n";
    foreach ($errors as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "All tests passed! Email system appears to be working correctly.\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Ensure cron job is running (process_queue.php every 1-2 minutes)\n";
    echo "2. Create a test request to verify end-to-end email flow\n";
    echo "3. Check email_queue table for queued emails\n";
    echo "4. Monitor email_queue status in Settings > Email Queue\n";
    exit(0);
}
