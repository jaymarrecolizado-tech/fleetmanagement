# 🚨 CRITICAL HOTFIX - Workflow & Notification System

**Status:** REVISION REQUIRED  
**Severity:** HIGH  
**Priority:** URGENT  
**Date:** January 23, 2026

---

## 📋 Overview

This document contains 6 critical fixes that must be applied to the LOKA Fleet Management System to achieve **production-ready** status. These fixes address race conditions, transaction isolation issues, and data corruption risks identified during ULTRATHINK audit.

**Impact if NOT fixed:**
- Orphaned emails sent for failed transactions
- Concurrent request edits causing data loss
- Race conditions in trip completion/cancellation
- User experience degradation from spam notifications

**Estimated Time to Fix:** 30-45 minutes

---

## 🔴 CRITICAL FIXES (Must Apply Immediately)

---

## Fix #1: create.php - Notifications Before Commit

**File:** `pages/requests/create.php`  
**Lines:** 270-305, 319  
**Severity:** 🔴 CRITICAL

### Problem
All notifications are sent **BEFORE** `db()->commit()`. If request creation fails after notifications are queued, users receive emails for non-existent requests.

### Current Code (WRONG)
```php
db()->beginTransaction();

// ... request creation ...

// ❌ Notifications sent BEFORE commit
notify(userId(), 'request_confirmation', ...);           // Line 270
notify($approverId, 'request_submitted', ...);        // Line 279
notifyPassengers($requestId, 'added_to_request', ...); // Line 288
notifyDriver($requestedDriverId, 'driver_requested', ...); // Line 298

auditLog('request_created', ...);
db()->commit();  // Line 319 - TOO LATE!
```

### Fix Implementation
Replace the notification section (Lines 269-305) with:

```php
// =====================================================
// DEFER NOTIFICATIONS UNTIL AFTER COMMIT
// This prevents orphaned emails if transaction fails
// =====================================================

$deferredNotifications = [];

// Queue requester confirmation
$deferredNotifications[] = [
    'user_id' => userId(),
    'type' => 'request_confirmation',
    'title' => 'Request Submitted Successfully',
    'message' => 'Your vehicle request to ' . $destination . ' on ' . date('M j, Y g:i A', strtotime($startDatetime)) . ' has been submitted and is awaiting approval.',
    'link' => '/?page=requests&action=view&id=' . $requestId
];

// Queue approver notification
$deferredNotifications[] = [
    'user_id' => $approverId,
    'type' => 'request_submitted',
    'title' => 'New Request Awaiting Your Approval',
    'message' => currentUser()->name . ' submitted a vehicle request for ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '. You have been selected as the approver.',
    'link' => '/?page=approvals&action=view&id=' . $requestId
];

// Audit log
auditLog('request_created', 'request', $requestId, null, [
    'purpose' => $purpose,
    'destination' => $destination,
    'start_datetime' => $startDatetime,
    'end_datetime' => $endDatetime,
    'passenger_count' => $passengerCount,
    'approver_id' => $approverId,
    'motorpool_head_id' => $motorpoolHeadId,
    'requested_driver_id' => $requestedDriverId
]);

db()->commit();

// =====================================================
// SEND NOTIFICATIONS AFTER SUCCESSFUL COMMIT
// =====================================================

// Send deferred notifications
foreach ($deferredNotifications as $notif) {
    notify($notif['user_id'], $notif['type'], $notif['title'], $notif['message'], $notif['link']);
}

// Notify passengers using batch function
notifyPassengers(
    $requestId,
    'added_to_request',
    'Added to Vehicle Request',
    currentUser()->name . ' has added you as a passenger for a trip to ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '. The request is now awaiting approval.',
    '/?page=requests&action=view&id=' . $requestId
);

// Notify requested driver (if specified)
if ($requestedDriverId) {
    notifyDriver(
        $requestedDriverId,
        'driver_requested',
        'You Have Been Requested as Driver',
        currentUser()->name . ' has requested you as the driver for a trip to ' . $destination . ' on ' . date('M j, Y g:i A', strtotime($startDatetime)) . '. The request is pending approval and you will be notified once approved.',
        '/?page=requests&action=view&id=' . $requestId
    );
}
```

---

## Fix #2: edit.php - Notifications Before Commit

**File:** `pages/requests/edit.php`  
**Lines:** 117-227  
**Severity:** 🔴 CRITICAL

### Problem
Multiple `notify()` and `notifyDriver()` calls throughout the edit process, all sent **BEFORE** `db()->commit()`. If edit fails, users receive misleading notifications.

### Current Code (WRONG)
```php
db()->beginTransaction();

// ... edit logic ...

// ❌ Notifications scattered BEFORE commit throughout function
notify((int) $identifier, 'removed_from_request', ...);     // Line 117
notify((int) $val, 'added_to_request', ...);                  // Line 137
notify((int) $id, 'request_modified', ...);                    // Line 175
notify($request->user_id, 'request_modified', ...);             // Line 193
notifyDriver($requestedDriverId, 'driver_status_update', ...);     // Line 212

auditLog('request_updated', ...);
db()->commit();  // Line 227 - TOO LATE!
```

### Fix Implementation
Create a deferred notification pattern:

**Step 1:** At the beginning of the transaction block (after Line 85), add:

```php
// Initialize notification queue
$deferredNotifications = [];
```

**Step 2:** Replace all `notify()` calls with array push (Lines 117, 137, 175, 193):

```php
// Line 117 - Replace with:
$deferredNotifications[] = [
    'user_id' => (int) $identifier,
    'type' => 'removed_from_request',
    'title' => 'Removed from Trip',
    'message' => 'You have been removed from a vehicle request by ' . currentUser()->name . '.',
    'link' => '/?page=requests&action=view&id=' . $requestId
];

// Line 137 - Replace with:
$deferredNotifications[] = [
    'user_id' => (int) $val,
    'type' => 'added_to_request',
    'title' => 'Added to Vehicle Request',
    'message' => currentUser()->name . ' has added you as a passenger for a trip to ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '.',
    'link' => '/?page=requests&action=view&id=' . $requestId
];

// Line 175 - Replace with:
$deferredNotifications[] = [
    'user_id' => (int) $id,
    'type' => 'request_modified',
    'title' => 'Trip Details Updated',
    'message' => 'A trip you are part of has been modified by ' . currentUser()->name . '.',
    'link' => '/?page=requests&action=view&id=' . $requestId
];

// Line 193 - Replace with:
$deferredNotifications[] = [
    'user_id' => $request->user_id,
    'type' => 'request_modified',
    'title' => 'Trip Details Updated',
    'message' => 'Your vehicle request has been modified. Please review updated details.',
    'link' => '/?page=requests&action=view&id=' . $requestId
];
```

**Step 3:** Create a deferred driver notification variable after audit log (around Line 221):

```php
// Prepare driver notification
$deferredDriverNotification = null;

if ($requestedDriverId && (
    $oldData['destination'] !== $destination ||
    $oldData['start_datetime'] !== $startDatetime ||
    $oldData['end_datetime'] !== $endDatetime
)) {
    $deferredDriverNotification = [
        'driver_id' => $requestedDriverId,
        'type' => 'driver_status_update',
        'title' => 'Trip Details Updated',
        'message' => 'A trip you were requested to drive has been modified. Please review updated details.',
        'link' => '/?page=requests&action=view&id=' . $requestId
    ];
}
```

**Step 4:** Replace the commit section (Lines 221-227) with:

```php
auditLog('request_updated', 'request', $requestId, $oldData, [
    'purpose' => $purpose,
    'destination' => $destination,
    'passenger_count' => $passengerCount
]);

db()->commit();

// =====================================================
// SEND NOTIFICATIONS AFTER SUCCESSFUL COMMIT
// =====================================================

// Send deferred notifications
foreach ($deferredNotifications as $notif) {
    notify($notif['user_id'], $notif['type'], $notif['title'], $notif['message'], $notif['link']);
}

// Send driver notification if needed
if ($deferredDriverNotification) {
    notifyDriver(
        $deferredDriverNotification['driver_id'],
        $deferredDriverNotification['type'],
        $deferredDriverNotification['title'],
        $deferredDriverNotification['message'],
        $deferredDriverNotification['link']
    );
}
```

---

## Fix #3: edit.php - Missing FOR UPDATE

**File:** `pages/requests/edit.php`  
**Line:** 12  
**Severity:** 🟡 HIGH

### Problem
Request is fetched without row-level locking. Two users could edit the same request simultaneously, causing data loss.

### Current Code (WRONG)
```php
$request = db()->fetch(
    "SELECT * FROM requests WHERE id = ? AND deleted_at IS NULL",
    [$requestId]
);
```

### Fix Implementation
Replace Line 10-13 with:

```php
// Get request with FOR UPDATE locking - PREVENTS RACE CONDITIONS
$request = db()->fetch(
    "SELECT * FROM requests WHERE id = ? AND deleted_at IS NULL FOR UPDATE",
    [$requestId]
);
```

---

## Fix #4: complete.php - Notifications Before Commit

**File:** `pages/requests/complete.php`  
**Lines:** 86-120  
**Severity:** 🔴 CRITICAL

### Problem
All notifications sent **BEFORE** `db()->commit()`. If trip completion fails, users receive false completion emails.

### Current Code (WRONG)
```php
db()->beginTransaction();

// ... completion logic ...

// ❌ All notifications sent BEFORE commit
notify($request->user_id, 'trip_completed', ...);     // Line 86
notifyPassengers($requestId, 'trip_completed', ...);   // Line 95
notifyDriver($request->driver_id, 'trip_completed', ...); // Line 104

auditLog('request_completed', ...);
db()->commit();  // Line 120 - TOO LATE!
```

### Fix Implementation
Replace the notification section (Lines 79-120) with:

```php
// =====================================================
// PREPARE NOTIFICATIONS FOR DEFERRED SENDING
// =====================================================

$completionMessage = 'Your trip has been marked as completed. Vehicle and driver have been released.';
$passengerMessage = 'A trip you were part of has been marked as completed.';
$driverMessage = 'A trip you were assigned to drive has been marked as completed. Vehicle and driver have been released.';
$link = '/?page=requests&action=view&id=' . $requestId;

// Prepare deferred notifications
$deferredNotifications = [];

// Requester notification
$deferredNotifications[] = [
    'user_id' => $request->user_id,
    'type' => 'trip_completed',
    'title' => 'Trip Completed',
    'message' => $completionMessage,
    'link' => $link
];

// Prepare passenger notification data
$passengerNotificationData = [
    'request_id' => $requestId,
    'type' => 'trip_completed',
    'title' => 'Trip Completed',
    'message' => $passengerMessage,
    'link' => $link
];

// Prepare driver notification
$driverNotificationData = null;

if ($request->driver_id) {
    $driverNotificationData = [
        'driver_id' => $request->driver_id,
        'type' => 'trip_completed',
        'title' => 'Trip Completed',
        'message' => $driverMessage,
        'link' => $link
    ];
}

// Audit log
auditLog('request_completed', 'request', $requestId, 
    ['status' => STATUS_APPROVED], 
    ['status' => STATUS_COMPLETED, 'notes' => $notes]
);

db()->commit();

// =====================================================
// SEND NOTIFICATIONS AFTER SUCCESSFUL COMMIT
// =====================================================

// Send deferred notifications
foreach ($deferredNotifications as $notif) {
    notify($notif['user_id'], $notif['type'], $notif['title'], $notif['message'], $notif['link']);
}

// Notify passengers
notifyPassengers(
    $passengerNotificationData['request_id'],
    $passengerNotificationData['type'],
    $passengerNotificationData['title'],
    $passengerNotificationData['message'],
    $passengerNotificationData['link']
);

// Notify driver
if ($driverNotificationData) {
    notifyDriver(
        $driverNotificationData['driver_id'],
        $driverNotificationData['type'],
        $driverNotificationData['title'],
        $driverNotificationData['message'],
        $driverNotificationData['link']
    );
}
```

---

## Fix #5: complete.php - Missing FOR UPDATE

**File:** `pages/requests/complete.php`  
**Lines:** 19-27  
**Severity:** 🟡 HIGH

### Problem
Request is fetched without row-level locking. Two motorpool heads could complete the same trip simultaneously.

### Current Code (WRONG)
```php
$request = db()->fetch(
    "SELECT r.*, v.plate_number, d.id as driver_id, u.name as driver_name
     FROM requests r
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers d ON r.driver_id = d.id AND d.deleted_at IS NULL
     LEFT JOIN users u ON d.user_id = u.id
     WHERE r.id = ? AND r.deleted_at IS NULL",
    [$requestId]
);
```

### Fix Implementation
Replace Lines 19-27 with:

```php
// Get request with FOR UPDATE locking - PREVENTS RACE CONDITIONS
$request = db()->fetch(
    "SELECT r.*, v.plate_number, d.id as driver_id, u.name as driver_name
     FROM requests r
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers d ON r.driver_id = d.id AND d.deleted_at IS NULL
     LEFT JOIN users u ON d.user_id = u.id
     WHERE r.id = ? AND r.deleted_at IS NULL
     FOR UPDATE",
    [$requestId]
);
```

---

## Fix #6: cancel.php - Missing FOR UPDATE

**File:** `pages/requests/cancel.php`  
**Lines:** 24-35  
**Severity:** 🟡 HIGH

### Problem
Request is fetched without row-level locking. Two users could attempt to cancel the same request simultaneously.

### Current Code (WRONG)
```php
$request = db()->fetch(
    "SELECT r.*, 
            u.name as requester_name, u.email as requester_email,
            v.plate_number as vehicle_plate, v.make as vehicle_make, v.model as vehicle_model,
            d.id as driver_db_id, du.name as driver_name
     FROM requests r
     JOIN users u ON r.user_id = u.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id
     LEFT JOIN drivers d ON r.driver_id = d.id
     LEFT JOIN users du ON d.user_id = du.id
     WHERE r.id = ? AND r.deleted_at IS NULL",
    [$requestId]
);
```

### Fix Implementation
Replace Lines 24-36 with:

```php
// Get request with FOR UPDATE locking - PREVENTS RACE CONDITIONS
$request = db()->fetch(
    "SELECT r.*, 
            u.name as requester_name, u.email as requester_email,
            v.plate_number as vehicle_plate, v.make as vehicle_make, v.model as vehicle_model,
            d.id as driver_db_id, du.name as driver_name
     FROM requests r
     JOIN users u ON r.user_id = u.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id
     LEFT JOIN drivers d ON r.driver_id = d.id
     LEFT JOIN users du ON d.user_id = du.id
     WHERE r.id = ? AND r.deleted_at IS NULL
     FOR UPDATE",
    [$requestId]
);
```

---

## ✅ TESTING PROCEDURE

After applying all fixes, run these tests:

### Test 1: Transaction Rollback
1. Create a request with invalid data (causes rollback)
2. Verify: NO emails sent for failed request
3. Check `email_queue` table - should be empty for this request

### Test 2: Concurrent Edit
1. Open same request in two browser windows
2. Both users edit and save simultaneously
3. Verify: Only one edit succeeds, the other gets lock timeout
4. Verify: No data corruption

### Test 3: Completion Race Condition
1. Have two motorpool heads complete same trip simultaneously
2. Verify: Only one completion succeeds
3. Verify: No duplicate notifications

### Test 4: Cancellation Race Condition
1. Have requester and admin both cancel same request simultaneously
2. Verify: Only one cancellation succeeds
3. Verify: Proper status in database

---

## 📊 FIXES SUMMARY

| Fix | File | Issue Type | Lines Affected | Est. Time |
|------|-------|-------------|-----------------|------------|
| #1 | create.php | Notifications before commit | 270-305 | 10 min |
| #2 | edit.php | Notifications before commit | 117-227 | 15 min |
| #3 | edit.php | Missing FOR UPDATE | 12 | 2 min |
| #4 | complete.php | Notifications before commit | 86-120 | 10 min |
| #5 | complete.php | Missing FOR UPDATE | 19-27 | 2 min |
| #6 | cancel.php | Missing FOR UPDATE | 24-36 | 2 min |

**Total Estimated Time:** 41 minutes

---

## 🎯 SUCCESS CRITERIA

After applying all fixes, the system should:

- ✅ Never send emails for failed transactions
- ✅ Prevent concurrent modifications to same request
- ✅ Maintain data integrity under high load
- ✅ Pass all transaction rollback tests
- ✅ Pass all race condition tests

---

## 📝 NOTES

- All notification deferral patterns follow the same approach used successfully in `pages/approvals/process.php`
- FOR UPDATE locking is standard MySQL pattern for row-level locking
- These fixes bring the system to **PRODUCTION-READY** status
- No changes to database schema required
- No changes to frontend required

---

## 🏁 SIGN-OFF

**Fixed By:** _______________________  
**Date:** _______________________  
**Reviewer:** _______________________  
**All Tests Passed:** [ ] Yes [ ] No  

---

**END OF HOTFIX DOCUMENT**
