<?php
/**
 * Find recent requests to debug
 */

chdir(__DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

// Get recent requests
$requests = $db->fetchAll(
    "SELECT r.id, r.destination, r.start_datetime, r.created_at, r.status,
            u.name as requester_name,
            (SELECT COUNT(*) FROM request_passengers WHERE request_id = r.id) as passenger_count,
            r.requested_driver_id
     FROM requests r
     LEFT JOIN users u ON r.user_id = u.id
     WHERE r.deleted_at IS NULL
     ORDER BY r.created_at DESC
     LIMIT 10"
);

echo "Recent Requests:\n";
echo "========================================\n\n";

foreach ($requests as $req) {
    echo "Request #{$req->id}\n";
    echo "  Requester: {$req->requester_name}\n";
    echo "  Destination: {$req->destination}\n";
    echo "  Date: " . date('M j, Y', strtotime($req->start_datetime)) . "\n";
    echo "  Status: {$req->status}\n";
    echo "  Passengers: {$req->passenger_count}\n";
    echo "  Driver ID: " . ($req->requested_driver_id ?? 'None') . "\n";
    echo "  Created: {$req->created_at}\n";
    echo "\n";
}

if (!empty($requests)) {
    $latestId = $requests[0]->id;
    echo "========================================\n";
    echo "Running diagnostic for latest request #{$latestId}...\n";
    echo "========================================\n\n";
    
    // Run diagnostic
    $_GET['request_id'] = $latestId;
    include __DIR__ . '/debug_request_emails.php';
}
