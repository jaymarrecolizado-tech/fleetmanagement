<?php
/**
 * LOKA - Email Queue Processor
 * 
 * Run this script via cron/task scheduler to process queued emails
 * 
 * Recommended schedule: Every 1-2 minutes
 * 
 * HOSTINGER (cPanel Cron Jobs):
 *   Frequency: Every 2 minutes (use: every 2 minutes in cPanel)
 *   Command: /usr/bin/php /home/u123456789/domains/yourdomain.com/public_html/LOKA/cron/process_queue.php
 *   
 * Windows Task Scheduler:
 *   Program: C:\wamp64\bin\php\php8.x.x\php.exe
 *   Arguments: C:\wamp64\www\fleetManagement\LOKA\cron\process_queue.php
 *   
 * Linux Cron:
 *   Run every 2 minutes: /usr/bin/php /var/www/html/LOKA/cron/process_queue.php >> /var/log/email_queue.log 2>&1
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI access only');
}

// Change to LOKA directory
chdir(dirname(__DIR__));

// Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/mail.php';

// Load classes
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Security.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/EmailQueue.php';

// Configuration
$batchSize = 20;        // Process 20 emails per run (increased for faster processing)
$cleanupDays = 30;      // Delete sent emails older than 30 days

// Lock file to prevent concurrent runs
$lockFile = __DIR__ . '/queue.lock';

// Check lock
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // If lock is older than 5 minutes, assume previous process died
    if (time() - $lockTime < 300) {
        echo date('[Y-m-d H:i:s]') . " Queue processor already running. Exiting.\n";
        exit(0);
    }
}

// Create lock
file_put_contents($lockFile, getmypid());

try {
    $queue = new EmailQueue();
    
    // Get stats before processing
    $statsBefore = $queue->getStats();
    echo date('[Y-m-d H:i:s]') . " Starting queue processor\n";
    echo "  Pending: {$statsBefore['pending']}, Processing: {$statsBefore['processing']}\n";
    
    // Process queue
    $results = $queue->process($batchSize);
    
    echo date('[Y-m-d H:i:s]') . " Processed: Sent={$results['sent']}, Failed={$results['failed']}\n";
    
    // FIX: Alert admins if too many recent failures
    $statsAfter = $queue->getStats();
    if ($statsAfter['recent_failures'] > 10) {
        echo date('[Y-m-d H:i:s]') . " WARNING: {$statsAfter['recent_failures']} recent failures detected!\n";
        
        // Get admin user IDs
        $adminUsers = Database::getInstance()->fetchAll(
            "SELECT id, email, name FROM users WHERE role = 'admin' AND status = 'active' AND deleted_at IS NULL"
        );
        
        // Send alert to all admins
        if (!empty($adminUsers)) {
            $alertMessage = "CRITICAL: More than 10 emails have failed in the last hour.\n\n" .
                            "Please check:\n" .
                            "- Email configuration (config/mail.php)\n" .
                            "- SMTP server status\n" .
                            "- Error logs for details";
            
            foreach ($adminUsers as $admin) {
                $queue->queueTemplate(
                    $admin->email,
                    'default',
                    [
                        'message' => $alertMessage,
                        'link' => null,
                        'link_text' => null
                    ],
                    $admin->name,
                    1 // High priority
                );
            }
            
            echo date('[Y-m-d H:i:s]') . " Alert queued for " . count($adminUsers) . " admins\n";
        }
    }
    
    // Cleanup old sent emails (run occasionally)
    if (rand(1, 10) === 1) {
        $cleaned = $queue->cleanup($cleanupDays);
        if ($cleaned > 0) {
            echo date('[Y-m-d H:i:s]') . " Cleaned up {$cleaned} old emails\n";
        }
    }
    
    // Reset stuck "processing" emails (older than 5 minutes)
    $stuck = Database::getInstance()->query(
        "UPDATE email_queue SET status = 'pending', updated_at = NOW() 
         WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    if ($stuck->rowCount() > 0) {
        echo date('[Y-m-d H:i:s]') . " Reset {$stuck->rowCount()} stuck emails\n";
    }
    
} catch (Exception $e) {
    echo date('[Y-m-d H:i:s]') . " ERROR: " . $e->getMessage() . "\n";
    error_log("Email queue error: " . $e->getMessage());
} finally {
    // Remove lock
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

echo date('[Y-m-d H:i:s]') . " Queue processor finished\n";
