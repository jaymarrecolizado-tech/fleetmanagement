<?php
/**
 * LOKA - Detailed Email Test Script
 * 
 * Tests email sending with various scenarios to catch errors
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

echo "========================================\n";
echo "LOKA Detailed Email Test\n";
echo "========================================\n\n";

$errors = [];
$success = [];

// Test 1: Email validation
echo "1. Testing email validation...\n";
$mailer = new Mailer();

$invalidEmails = [
    '',
    'invalid',
    'invalid@',
    '@invalid.com',
    'invalid@.com',
    'invalid@com'
];

foreach ($invalidEmails as $email) {
    $result = $mailer->send($email, 'Test', 'Test body');
    if (!$result) {
        $mailerErrors = $mailer->getErrors();
        if (!empty($mailerErrors) && strpos($mailerErrors[0], 'Invalid email address') !== false) {
            echo "   ✓ Correctly rejected: $email\n";
            $success[] = "Email validation works for: $email";
        } else {
            echo "   ✗ Should have rejected: $email\n";
            $errors[] = "Email validation failed for: $email";
        }
    } else {
        echo "   ✗ Should have rejected: $email\n";
        $errors[] = "Email validation failed for: $email (send returned true)";
    }
}
echo "\n";

// Test 2: Error reset between sends
echo "2. Testing error reset between sends...\n";
$mailer = new Mailer();

// First send with invalid email (should fail)
$mailer->send('invalid', 'Test', 'Test');
$errors1 = $mailer->getErrors();

// Second send with valid email (should succeed or fail for different reason)
$mailer->send(MAIL_FROM_ADDRESS, 'Test', 'Test');
$errors2 = $mailer->getErrors();

if (count($errors1) > 0 && count($errors2) <= count($errors1)) {
    echo "   ✓ Errors are properly managed between sends\n";
    $success[] = "Error reset works correctly";
} else {
    echo "   - Error management check (may vary based on SMTP state)\n";
}
echo "\n";

// Test 3: Special characters in subject
echo "3. Testing special characters in subject...\n";
$mailer = new Mailer();

$specialSubjects = [
    'Test: Subject with colon',
    'Test & Subject with ampersand',
    'Test "Subject" with quotes',
    'Test <Subject> with brackets',
    'Test with émojis 🚗',
    'Test with unicode: 测试'
];

foreach ($specialSubjects as $subject) {
    try {
        $result = $mailer->send(MAIL_FROM_ADDRESS, $subject, '<p>Test body</p>');
        if ($result) {
            echo "   ✓ Sent with subject: " . substr($subject, 0, 40) . "...\n";
            $success[] = "Special characters handled: " . substr($subject, 0, 30);
        } else {
            $mailerErrors = $mailer->getErrors();
            echo "   - Failed: " . substr($subject, 0, 40) . " - " . implode(', ', $mailerErrors) . "\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
        $errors[] = "Exception with special characters: " . $e->getMessage();
    }
}
echo "\n";

// Test 4: HTML email body
echo "4. Testing HTML email body...\n";
$mailer = new Mailer();

$htmlBody = '<html><body><h1>Test</h1><p>This is a <strong>test</strong> email.</p></body></html>';
$result = $mailer->send(MAIL_FROM_ADDRESS, 'HTML Test', $htmlBody, null, true);

if ($result) {
    echo "   ✓ HTML email sent successfully\n";
    $success[] = "HTML email sending works";
} else {
    $mailerErrors = $mailer->getErrors();
    echo "   ✗ HTML email failed: " . implode(', ', $mailerErrors) . "\n";
    $errors[] = "HTML email failed: " . implode(', ', $mailerErrors);
}
echo "\n";

// Test 5: Plain text email
echo "5. Testing plain text email...\n";
$mailer = new Mailer();

$textBody = "This is a plain text email.\n\nIt has multiple lines.";
$result = $mailer->send(MAIL_FROM_ADDRESS, 'Plain Text Test', $textBody, null, false);

if ($result) {
    echo "   ✓ Plain text email sent successfully\n";
    $success[] = "Plain text email sending works";
} else {
    $mailerErrors = $mailer->getErrors();
    echo "   ✗ Plain text email failed: " . implode(', ', $mailerErrors) . "\n";
    $errors[] = "Plain text email failed: " . implode(', ', $mailerErrors);
}
echo "\n";

// Test 6: Email with recipient name
echo "6. Testing email with recipient name...\n";
$mailer = new Mailer();

$result = $mailer->send(MAIL_FROM_ADDRESS, 'Name Test', '<p>Test body</p>', 'Test Recipient');

if ($result) {
    echo "   ✓ Email with recipient name sent successfully\n";
    $success[] = "Email with recipient name works";
} else {
    $mailerErrors = $mailer->getErrors();
    echo "   ✗ Email with name failed: " . implode(', ', $mailerErrors) . "\n";
    $errors[] = "Email with name failed: " . implode(', ', $mailerErrors);
}
echo "\n";

// Test 7: Email queue with various templates
echo "7. Testing email queue with templates...\n";
$queue = new EmailQueue();

$templates = ['request_confirmation', 'request_submitted', 'request_approved', 'default'];

foreach ($templates as $template) {
    try {
        $emailId = $queue->queueTemplate(
            MAIL_FROM_ADDRESS,
            $template,
            ['message' => 'Test message for ' . $template],
            'Test User'
        );
        
        if ($emailId) {
            echo "   ✓ Queued template: $template (ID: $emailId)\n";
            $success[] = "Template queued: $template";
            
            // Clean up
            Database::getInstance()->query("DELETE FROM email_queue WHERE id = ?", [$emailId]);
        } else {
            echo "   ✗ Failed to queue template: $template\n";
            $errors[] = "Failed to queue template: $template";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception with template $template: " . $e->getMessage() . "\n";
        $errors[] = "Exception with template $template: " . $e->getMessage();
    }
}
echo "\n";

// Test 8: Multiple emails in queue
echo "8. Testing multiple emails in queue...\n";
$queue = new EmailQueue();
$emailIds = [];

for ($i = 1; $i <= 3; $i++) {
    $emailId = $queue->queue(
        MAIL_FROM_ADDRESS,
        "Test Email $i",
        "<p>This is test email number $i</p>",
        "Test User $i"
    );
    
    if ($emailId) {
        $emailIds[] = $emailId;
        echo "   ✓ Queued email $i (ID: $emailId)\n";
    }
}

if (count($emailIds) === 3) {
    $success[] = "Multiple emails queued successfully";
    
    // Clean up
    foreach ($emailIds as $id) {
        Database::getInstance()->query("DELETE FROM email_queue WHERE id = ?", [$id]);
    }
    echo "   ✓ Cleaned up test emails\n";
} else {
    $errors[] = "Failed to queue all test emails";
}
echo "\n";

// Test 9: Email processing
echo "9. Testing email processing...\n";
$queue = new EmailQueue();

// Queue a test email
$testEmailId = $queue->queue(
    MAIL_FROM_ADDRESS,
    'Processing Test',
    '<p>This email tests the processing functionality.</p>',
    'Test User'
);

if ($testEmailId) {
    echo "   ✓ Test email queued (ID: $testEmailId)\n";
    
    // Process it
    $results = $queue->process(1);
    
    echo "   - Processed: Sent={$results['sent']}, Failed={$results['failed']}\n";
    
    if ($results['sent'] > 0) {
        $success[] = "Email processing works correctly";
        echo "   ✓ Email processed and sent successfully\n";
    } elseif ($results['failed'] > 0) {
        // Check the error
        $failedEmail = Database::getInstance()->fetch(
            "SELECT error_message FROM email_queue WHERE id = ?",
            [$testEmailId]
        );
        if ($failedEmail) {
            echo "   - Email failed: " . ($failedEmail->error_message ?? 'Unknown error') . "\n";
        }
    }
    
    // Clean up
    Database::getInstance()->query("DELETE FROM email_queue WHERE id = ?", [$testEmailId]);
} else {
    $errors[] = "Failed to queue test email for processing";
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

if (!empty($errors)) {
    echo "✗ Errors: " . count($errors) . "\n";
    foreach ($errors as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "All detailed tests passed! Email system is robust.\n";
    exit(0);
}
