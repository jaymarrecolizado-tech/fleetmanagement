<?php
/**
 * LOKA - Export Report to CSV
 */

requireRole(ROLE_APPROVER);

$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));

$requests = db()->fetchAll(
    "SELECT r.id, r.created_at, r.start_datetime, r.end_datetime, r.purpose, r.destination,
            r.passenger_count, r.status, u.name as requester, d.name as department,
            v.plate_number as vehicle, dr_u.name as driver
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     LEFT JOIN users dr_u ON dr.user_id = dr_u.id
     WHERE r.created_at BETWEEN ? AND ? AND r.deleted_at IS NULL
     ORDER BY r.created_at DESC",
    [$startDate, $endDate . ' 23:59:59']
);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="fleet_report_' . $startDate . '_to_' . $endDate . '.csv"');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, ['ID', 'Created', 'Start', 'End', 'Purpose', 'Destination', 'Passengers', 'Status', 'Requester', 'Department', 'Vehicle', 'Driver']);

// Data rows
foreach ($requests as $request) {
    fputcsv($output, [
        $request->id,
        $request->created_at,
        $request->start_datetime,
        $request->end_datetime,
        $request->purpose,
        $request->destination,
        $request->passenger_count,
        $request->status,
        $request->requester,
        $request->department,
        $request->vehicle ?: 'N/A',
        $request->driver ?: 'N/A'
    ]);
}

fclose($output);
exit;
