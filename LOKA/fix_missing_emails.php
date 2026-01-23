<?php
/**
 * LOKA - Fix Missing Email Notifications
 * 
 * Re-sends email notifications for passengers and drivers of a specific request
 */

// Prevent web access in production
if (php_sapi_name() !== 'cli' && !isset($_GET['request_id'])) {
    http_response_code(403);
    die('Access denied. Provide request_id parameter.');
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

$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : (isset($argv[1]) ? (int)$argv[1] : null);

if (!$requestId) {
    die("Usage: php fix_missing_emails.php <request_id>\n   OR: ?request_id=123\n");
}

echo "========================================\n";
echo "Fix Missing Email Notifications for Request #{$requestId}\n";
echo "========================================\n\n";

$db = Database::getInstance();

// Get request details
$request = $db->fetch(
    "SELECT r.*, u.name as requester_name, u.email as requester_email 
     FROM requests r 
     LEFT JOIN users u ON r.user_id = u.id 
     WHERE r.id = ?",
    [$requestId]
);

if (!$request) {
    die("Request #{$requestId} not found.\n");
}

echo "Request: {$request->destination} on " . date('M j, Y', strtotime($request->start_datetime)) . "\n\n";

$emailsQueued = 0;
$errors = [];

// Re-notify passengers
echo "Re-notifying passengers...\n";
$passengers = $db->fetchAll(
    "SELECT rp.user_id, u.name, u.email 
     FROM request_passengers rp
     LEFT JOIN users u ON rp.user_id = u.id
     WHERE rp.request_id = ? 
     AND rp.user_id IS NOT NULL 
     AND u.status = 'active' 
     AND u.deleted_at IS NULL",
    [$requestId]
);

if (empty($passengers)) {
    echo "  ⚠ No passengers with user accounts found (guests don't receive emails)\n";
} else {
    foreach ($passengers as $passenger) {
        if ($passenger->user_id && $passenger->email) {
            try {
                notify(
                    $passenger->user_id,
                    'added_to_request',
                    'Added to Vehicle Request',
                    $request->requester_name . ' has added you as a passenger for a trip to ' . $request->destination . ' on ' . date('M j, Y', strtotime($request->start_datetime)) . '. The request is now awaiting approval.',
                    '/?page=requests&action=view&id=' . $requestId
                );
                echo "  ✓ Queued email for passenger: {$passenger->name} ({$passenger->email})\n";
                $emailsQueued++;
            } catch (Exception $e) {
                echo "  ✗ Error notifying {$passenger->name}: " . $e->getMessage() . "\n";
                $errors[] = "Passenger {$passenger->name}: " . $e->getMessage();
            }
        } else {
            echo "  - Skipping {$passenger->name}: " . (!$passenger->email ? 'no email' : 'no user_id') . "\n";
        }
    }
}

// Re-notify driver
echo "\nRe-notifying driver...\n";
if ($request->requested_driver_id) {
    try {
        $driver = $db->fetch(
            "SELECT d.user_id, u.name, u.email 
             FROM drivers d
             JOIN users u ON d.user_id = u.id
             WHERE d.id = ? 
             AND u.status = 'active' 
             AND u.deleted_at IS NULL 
             AND d.deleted_at IS NULL",
            [$request->requested_driver_id]
        );
        
        if ($driver && $driver->user_id && $driver->email) {
            notifyDriver(
                $request->requested_driver_id,
                'driver_requested',
                'You Have Been Requested as Driver',
                $request->requester_name . ' has requested you as the driver for a trip to ' . $request->destination . ' on ' . date('M j, Y g:i A', strtotime($request->start_datetime)) . '. The request is pending approval and you will be notified once approved.',
                '/?page=requests&action=view&id=' . $requestId
            );
            echo "  ✓ Queued email for driver: {$driver->name} ({$driver->email})\n";
            $emailsQueued++;
        } else {
            echo "  ⚠ Driver #{$request->requested_driver_id} not found or has no email\n";
            if (!$driver) {
                echo "     - Driver record not found\n";
            } elseif (!$driver->user_id) {
                echo "     - Driver not linked to user account\n";
            } elseif (!$driver->email) {
                echo "     - Driver has no email address\n";
            }
        }
    } catch (Exception $e) {
        echo "  ✗ Error notifying driver: " . $e->getMessage() . "\n";
        $errors[] = "Driver: " . $e->getMessage();
    }
} else {
    echo "  ⚠ No requested driver specified\n";
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Emails queued: {$emailsQueued}\n";

if (!empty($errors)) {
    echo "Errors: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\nNext step: Run the email queue processor:\n";
echo "  php cron/process_queue.php\n";
echo "\n";
