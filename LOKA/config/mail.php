<?php
/**
 * LOKA - Mail Configuration
 * 
 * IMPORTANT: Update these settings with your email credentials
 * For Gmail, use an App Password, not your regular password
 * Get App Password: https://myaccount.google.com/apppasswords
 */

// SMTP Settings
define('MAIL_ENABLED', true);
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);

// ⚠️ UPDATE THESE WITH YOUR GMAIL CREDENTIALS
define('MAIL_USERNAME', 'jelite.demo@gmail.com');        // Your Gmail address
define('MAIL_PASSWORD', 'typq agna gfvg mlbt');  // App Password, not regular password
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'jelite.demo@gmail.com');    // Must match MAIL_USERNAME for Gmail
define('MAIL_FROM_NAME', 'LOKA Fleet Management');

// Configuration validation - check if email is properly configured
function isEmailConfigured(): bool {
    return MAIL_ENABLED 
        && !empty(MAIL_HOST) 
        && !empty(MAIL_USERNAME) 
        && MAIL_USERNAME !== 'your-email@gmail.com'
        && !empty(MAIL_PASSWORD) 
        && MAIL_PASSWORD !== 'your-16-digit-app-password'
        && !empty(MAIL_FROM_ADDRESS);
}

// Email Templates
define('MAIL_TEMPLATES', [
    // Approver notifications
    'request_submitted' => [
        'subject' => 'New Vehicle Request Submitted',
        'template' => 'A new vehicle request has been submitted and requires your approval.'
    ],
    'request_pending_motorpool' => [
        'subject' => 'Request Awaiting Vehicle Assignment',
        'template' => 'A request has been approved by department and needs vehicle/driver assignment.'
    ],
    
    // Requester notifications
    'request_confirmation' => [
        'subject' => 'Your Vehicle Request Has Been Submitted',
        'template' => 'Your vehicle request has been submitted successfully and is now awaiting approval.'
    ],
    'request_approved' => [
        'subject' => 'Your Request Has Been Approved',
        'template' => 'Great news! Your vehicle request has been approved.'
    ],
    'request_rejected' => [
        'subject' => 'Your Request Has Been Rejected',
        'template' => 'Unfortunately, your vehicle request has been rejected.'
    ],
    'vehicle_assigned' => [
        'subject' => 'Vehicle and Driver Assigned',
        'template' => 'A vehicle and driver have been assigned to your request.'
    ],
    'trip_completed' => [
        'subject' => 'Trip Completed',
        'template' => 'Your trip has been marked as completed.'
    ],
    
    // Passenger notifications
    'added_to_request' => [
        'subject' => 'You Have Been Added to a Vehicle Request',
        'template' => 'You have been added as a passenger to a vehicle request.'
    ],
    'removed_from_request' => [
        'subject' => 'Removed from Vehicle Request',
        'template' => 'You have been removed from a vehicle request.'
    ],
    'request_modified' => [
        'subject' => 'Trip Details Updated',
        'template' => 'A trip you are part of has been modified.'
    ],
    'request_cancelled' => [
        'subject' => 'Trip Cancelled',
        'template' => 'A trip you were part of has been cancelled.'
    ],
    
    // Driver notifications
    'driver_requested' => [
        'subject' => 'You Have Been Requested as Driver',
        'template' => 'You have been requested as the driver for a vehicle request. The request is pending approval.'
    ],
    'driver_assigned' => [
        'subject' => 'You Have Been Assigned as Driver',
        'template' => 'You have been assigned as the driver for an approved vehicle request.'
    ],
    'driver_status_update' => [
        'subject' => 'Trip Status Update',
        'template' => 'There has been an update to a trip you are assigned to drive.'
    ],
    'trip_cancelled_driver' => [
        'subject' => 'Trip Cancelled - Driver Assignment',
        'template' => 'A trip you were assigned to drive has been cancelled.'
    ],
    
    // Default template for notifications without specific template
    'default' => [
        'subject' => 'Fleet Management Notification',
        'template' => 'You have received a notification from the Fleet Management System.'
    ],

    // Two-stage approval workflow notifications
    'department_approved' => [
        'subject' => 'Request Approved by Department',
        'template' => 'Your request has been approved by the department and is now awaiting motorpool assignment.'
    ],
    'pending_motorpool_approval' => [
        'subject' => 'Request Awaiting Motorpool Approval',
        'template' => 'A vehicle request has been approved by the department and requires your approval and vehicle assignment.'
    ],
    'request_fully_approved' => [
        'subject' => 'Request Fully Approved!',
        'template' => 'Great news! Your vehicle request has been fully approved with vehicle and driver assignment.'
    ],
    'trip_fully_approved' => [
        'subject' => 'Trip Fully Approved',
        'template' => 'A trip you are part of has been fully approved with vehicle and driver assignment.'
    ],
    'trip_rejected' => [
        'subject' => 'Trip Rejected',
        'template' => 'A trip you were part of has been rejected.'
    ],
    'driver_not_selected' => [
        'subject' => 'Driver Assignment Update',
        'template' => 'A trip you were requested to drive has been assigned to another driver.'
    ],

    // Revision notifications
    'request_revision' => [
        'subject' => 'Request Sent Back for Revision',
        'template' => 'Your request has been sent back for revision. Please update and resubmit.'
    ],
    'trip_revision' => [
        'subject' => 'Trip Sent Back for Revision',
        'template' => 'A trip you are part of has been sent back for revision by the approver.'
    ]
]);
