<?php
/**
 * LOKA - AJAX Conflict Checker
 */

header('Content-Type: application/json');

// This script is called via AJAX, so we need to manually load dependencies if not using index.php route
// BUT, the system uses index.php as a router, so we should check if this is called via index.php?page=api&action=check_conflict

requireAuth();

$type = get('type'); // 'driver' or 'vehicle'
$id = (int) get('id');
$start = get('start');
$end = get('end');
$excludeId = (int) get('exclude_id') ?: null;

if (!$type || !$id || !$start || !$end) {
    jsonResponse(['error' => 'Missing parameters'], 400);
}

$conflict = null;
if ($type === 'driver') {
    $conflict = checkDriverConflict($id, $start, $end, $excludeId);
} elseif ($type === 'vehicle') {
    $conflict = checkVehicleConflict($id, $start, $end, $excludeId);
} else {
    jsonResponse(['error' => 'Invalid type'], 400);
}

if ($conflict) {
    jsonResponse([
        'conflict' => true,
        'message' => sprintf(
            'Conflict detected: Already booked by %s for trip to %s (%s to %s)',
            $conflict['requester_name'],
            $conflict['destination'],
            formatDateTime($conflict['start_datetime']),
            formatDateTime($conflict['end_datetime'])
        )
    ]);
} else {
    jsonResponse(['conflict' => false]);
}
