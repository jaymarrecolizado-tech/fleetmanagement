<?php
/**
 * LOKA - Debug Request Email Notifications
 * 
 * Checks why passengers and drivers didn't receive emails for a specific request
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
    die("Usage: php debug_request_emails.php <request_id>\n   OR: ?request_id=123\n");
}

echo "========================================\n";
echo "Debug Email Notifications for Request #{$requestId}\n";
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

echo "Request Details:\n";
echo "  ID: {$request->id}\n";
echo "  Requester: {$request->requester_name} ({$request->requester_email})\n";
echo "  Destination: {$request->destination}\n";
echo "  Status: {$request->status}\n";
echo "  Requested Driver ID: " . ($request->requested_driver_id ?? 'NULL') . "\n";
echo "  Assigned Driver ID: " . ($request->driver_id ?? 'NULL') . "\n";
echo "\n";

// Check passengers
echo "Passengers:\n";
$passengers = $db->fetchAll(
    "SELECT rp.*, u.name, u.email, u.status as user_status, u.deleted_at as user_deleted
     FROM request_passengers rp
     LEFT JOIN users u ON rp.user_id = u.id
     WHERE rp.request_id = ?",
    [$requestId]
);

if (empty($passengers)) {
    echo "  ⚠ No passengers found in request_passengers table\n";
} else {
    echo "  Found " . count($passengers) . " passenger(s):\n";
    foreach ($passengers as $idx => $passenger) {
        $num = $idx + 1;
        echo "  {$num}. Passenger Record:\n";
        echo "     - ID: {$passenger->id}\n";
        echo "     - Name: " . ($passenger->name ?? $passenger->name ?? 'Guest') . "\n";
        echo "     - User ID: " . ($passenger->user_id ?? 'NULL (Guest)') . "\n";
        echo "     - Email: " . ($passenger->email ?? 'NULL') . "\n";
        echo "     - User Status: " . ($passenger->user_status ?? 'N/A') . "\n";
        echo "     - User Deleted: " . ($passenger->user_deleted ?? 'NULL') . "\n";
        
        // Check if this passenger would be notified
        $willNotify = false;
        $reasons = [];
        
        if (!$passenger->user_id) {
            $reasons[] = "No user_id (guest passenger)";
        } else {
            if (!$passenger->email) {
                $reasons[] = "No email address";
            }
            if ($passenger->user_status !== 'active') {
                $reasons[] = "User status is '{$passenger->user_status}' (not 'active')";
            }
            if ($passenger->user_deleted) {
                $reasons[] = "User is deleted";
            }
            
            if (empty($reasons)) {
                $willNotify = true;
            }
        }
        
        if ($willNotify) {
            echo "     ✓ Would receive email notification\n";
        } else {
            echo "     ✗ Would NOT receive email: " . implode(', ', $reasons) . "\n";
        }
        echo "\n";
    }
}

// Check driver
echo "Driver:\n";
if ($request->requested_driver_id) {
    $driver = $db->fetch(
        "SELECT d.*, u.name, u.email, u.status as user_status, u.deleted_at as user_deleted
         FROM drivers d
         LEFT JOIN users u ON d.user_id = u.id
         WHERE d.id = ?",
        [$request->requested_driver_id]
    );
    
    if (!$driver) {
        echo "  ✗ Driver #{$request->requested_driver_id} not found\n";
    } else {
        echo "  Driver Record:\n";
        echo "    - Driver ID: {$driver->id}\n";
        echo "    - Name: " . ($driver->name ?? 'N/A') . "\n";
        echo "    - User ID: " . ($driver->user_id ?? 'NULL') . "\n";
        echo "    - Email: " . ($driver->email ?? 'NULL') . "\n";
        echo "    - User Status: " . ($driver->user_status ?? 'N/A') . "\n";
        echo "    - User Deleted: " . ($driver->user_deleted ?? 'NULL') . "\n";
        echo "    - Driver Status: " . ($driver->status ?? 'N/A') . "\n";
        echo "    - Driver Deleted: " . ($driver->deleted_at ?? 'NULL') . "\n";
        
        // Check if driver would be notified
        $willNotify = false;
        $reasons = [];
        
        if (!$driver->user_id) {
            $reasons[] = "No user_id linked to driver";
        } else {
            if (!$driver->email) {
                $reasons[] = "No email address";
            }
            if ($driver->user_status !== 'active') {
                $reasons[] = "User status is '{$driver->user_status}' (not 'active')";
            }
            if ($driver->user_deleted) {
                $reasons[] = "User is deleted";
            }
            if ($driver->deleted_at) {
                $reasons[] = "Driver record is deleted";
            }
            
            if (empty($reasons)) {
                $willNotify = true;
            }
        }
        
        if ($willNotify) {
            echo "    ✓ Would receive email notification\n";
        } else {
            echo "    ✗ Would NOT receive email: " . implode(', ', $reasons) . "\n";
        }
    }
} else {
    echo "  ⚠ No requested driver specified\n";
}
echo "\n";

// Check email queue for this request
echo "Email Queue Status:\n";
$queueEmails = $db->fetchAll(
    "SELECT eq.*, n.user_id, n.type, n.title 
     FROM email_queue eq
     LEFT JOIN notifications n ON eq.to_email = (
         SELECT email FROM users WHERE id = n.user_id LIMIT 1
     )
     WHERE eq.created_at >= (
         SELECT created_at FROM requests WHERE id = ?
     )
     AND eq.created_at <= DATE_ADD((
         SELECT created_at FROM requests WHERE id = ?
     ), INTERVAL 1 HOUR)
     ORDER BY eq.created_at DESC",
    [$requestId, $requestId]
);

if (empty($queueEmails)) {
    echo "  ⚠ No emails found in queue around request creation time\n";
} else {
    echo "  Found " . count($queueEmails) . " email(s) in queue:\n";
    foreach ($queueEmails as $idx => $email) {
        $num = $idx + 1;
        echo "  {$num}. Email Queue Record:\n";
        echo "     - ID: {$email->id}\n";
        echo "     - To: {$email->to_email} ({$email->to_name})\n";
        echo "     - Subject: {$email->subject}\n";
        echo "     - Template: {$email->template}\n";
        echo "     - Status: {$email->status}\n";
        echo "     - Created: {$email->created_at}\n";
        if ($email->status === 'failed') {
            echo "     - Error: " . ($email->error_message ?? 'Unknown') . "\n";
        }
        echo "\n";
    }
}

// Check notifications table
echo "Notifications Table:\n";
$notifications = $db->fetchAll(
    "SELECT n.*, u.email, u.name 
     FROM notifications n
     LEFT JOIN users u ON n.user_id = u.id
     WHERE n.link LIKE ? 
     ORDER BY n.created_at DESC",
    ['%request_id=' . $requestId . '%']
);

if (empty($notifications)) {
    echo "  ⚠ No notifications found for this request\n";
} else {
    echo "  Found " . count($notifications) . " notification(s):\n";
    foreach ($notifications as $idx => $notif) {
        $num = $idx + 1;
        echo "  {$num}. Notification:\n";
        echo "     - ID: {$notif->id}\n";
        echo "     - User: {$notif->name} ({$notif->email})\n";
        echo "     - Type: {$notif->type}\n";
        echo "     - Title: {$notif->title}\n";
        echo "     - Created: {$notif->created_at}\n";
        echo "\n";
    }
}

// Test notification functions
echo "Testing Notification Functions:\n";
echo "  Testing notifyPassengers()...\n";
try {
    $passengerCount = count($passengers ?? []);
    echo "    Found {$passengerCount} passenger(s) in database\n";
    
    // Simulate what notifyPassengers does
    $notifiablePassengers = $db->fetchAll(
        "SELECT rp.user_id, u.name, u.email 
         FROM request_passengers rp
         LEFT JOIN users u ON rp.user_id = u.id
         WHERE rp.request_id = ? 
         AND rp.user_id IS NOT NULL 
         AND u.status = 'active' 
         AND u.deleted_at IS NULL",
        [$requestId]
    );
    
    echo "    Would notify " . count($notifiablePassengers) . " passenger(s):\n";
    foreach ($notifiablePassengers as $p) {
        echo "      - {$p->name} ({$p->email})\n";
    }
    
    if (empty($notifiablePassengers)) {
        echo "    ⚠ No passengers meet notification criteria\n";
    }
} catch (Exception $e) {
    echo "    ✗ Error: " . $e->getMessage() . "\n";
}

if ($request->requested_driver_id) {
    echo "  Testing notifyDriver()...\n";
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
            echo "    ✓ Would notify driver: {$driver->name} ({$driver->email})\n";
        } else {
            echo "    ✗ Driver would NOT be notified\n";
            if (!$driver) {
                echo "      - Driver not found\n";
            } elseif (!$driver->user_id) {
                echo "      - Driver has no user_id\n";
            } elseif (!$driver->email) {
                echo "      - Driver has no email\n";
            }
        }
    } catch (Exception $e) {
        echo "    ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n========================================\n";
echo "Diagnostic Complete\n";
echo "========================================\n";
