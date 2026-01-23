<?php
/**
 * LOKA - Cancel Request Page (Hardened Version)
 * 
 * Allows requester to cancel their own request
 * Or admin/approver to cancel any request
 * 
 * Handles:
 * - Status transition to 'cancelled' with state machine validation
 * - Vehicle/driver release when applicable
 * - Notification of all parties (AFTER successful commit)
 * - Audit logging with admin override tracking
 */

requireAuth();

$requestId = (int) get('id');

if (!$requestId) {
    redirectWith('/?page=dashboard', 'danger', 'Request ID required.');
}

// Get request with full details - FOR UPDATE locking
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

if (!$request) {
    redirectWith('/?page=dashboard', 'danger', 'Request not found.');
}

// Check permissions
$canCancel = false;
$isAdminOrApprover = isAdmin() || isApprover() || isMotorpool();

if ($request->user_id == userId()) {
    // Requester can cancel their own request if it's not already terminal
    $canCancel = true;
} elseif ($isAdminOrApprover) {
    // Admin/approver can cancel requests
    $canCancel = true;
}

if (!$canCancel) {
    redirectWith('/?page=requests&action=view&id=' . $requestId, 'danger', 'You cannot cancel this request.');
}

// Check if request is in a cancellable state
$cancellableStatuses = [STATUS_DRAFT, STATUS_PENDING, STATUS_PENDING_MOTORPOOL, STATUS_REVISION, STATUS_APPROVED];
if (!in_array($request->status, $cancellableStatuses)) {
    $statusLabels = [
        STATUS_DRAFT => 'Draft',
        STATUS_PENDING => 'Pending Approval',
        STATUS_PENDING_MOTORPOOL => 'Pending Motorpool',
        STATUS_REVISION => 'Under Revision',
        STATUS_APPROVED => 'Approved',
        STATUS_REJECTED => 'Rejected',
        STATUS_CANCELLED => 'Cancelled'
    ];
    redirectWith('/?page=requests&action=view&id=' . $requestId, 'danger', "Cannot cancel request in '{$statusLabels[$request->status]}' status.");
}

$pageTitle = 'Cancel Request #' . $requestId;
require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-x-circle me-2"></i>Cancel Request #<?= $requestId ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('confirm_cancel') === '1'): ?>
                        <?php
                        try {
                            db()->beginTransaction();
                            
                            $oldStatus = $request->status;
                            $now = date(DATETIME_FORMAT);
                            
                            // STATE MACHINE VALIDATION
                            $validTransitions = [
                                STATUS_DRAFT => [STATUS_CANCELLED],
                                STATUS_PENDING => [STATUS_CANCELLED],
                                STATUS_PENDING_MOTORPOOL => [STATUS_CANCELLED],
                                STATUS_REVISION => [STATUS_CANCELLED],
                                STATUS_APPROVED => [STATUS_CANCELLED]
                            ];
                            
                            if (!in_array(STATUS_CANCELLED, $validTransitions[$oldStatus] ?? [])) {
                                throw new Exception("Cannot cancel request in '{$oldStatus}' status.");
                            }
                            
                            // Release vehicle if assigned
                            if ($request->vehicle_id) {
                                db()->update('vehicles', [
                                    'status' => 'available',
                                    'updated_at' => $now
                                ], 'id = ?', [$request->vehicle_id]);
                            }
                            
                            // Release driver if assigned
                            if ($request->driver_id) {
                                db()->update('drivers', [
                                    'status' => 'available',
                                    'updated_at' => $now
                                ], 'id = ?', [$request->driver_id]);
                            }
                            
                            // Update request status
                            db()->update('requests', [
                                'status' => STATUS_CANCELLED,
                                'updated_at' => $now,
                                'cancelled_at' => $now,
                                'cancelled_by' => userId(),
                                'cancellation_reason' => trim(post('reason') ?: 'Cancelled by user')
                            ], 'id = ?', [$requestId]);
                            
                            // Update workflow
                            $workflow = db()->fetch(
                                "SELECT * FROM approval_workflow WHERE request_id = ?",
                                [$requestId]
                            );
                            
                            if ($workflow) {
                                db()->update('approval_workflow', [
                                    'status' => 'cancelled',
                                    'action_at' => $now,
                                    'updated_at' => $now,
                                    'comments' => 'Request cancelled: ' . trim(post('reason') ?: 'No reason provided')
                                ], 'request_id = ?', [$requestId]);
                            }
                            
                            // Audit log with admin override tracking
                            $isAdminOverride = ($request->user_id != userId() && $isAdminOrApprover);
                            $auditData = [
                                'status' => STATUS_CANCELLED,
                                'cancelled_by' => userId(),
                                'is_admin_override' => $isAdminOverride,
                                'reason' => trim(post('reason') ?: 'No reason provided')
                            ];
                            
                            if ($isAdminOverride) {
                                $auditData['original_requester_id'] = $request->user_id;
                            }
                            
                            auditLog(
                                'request_cancelled',
                                'request',
                                $requestId,
                                ['status' => $oldStatus],
                                $auditData
                            );
                            
                            db()->commit();
                            
                            // Send notifications AFTER successful commit
                            $cancelledBy = ($request->user_id == userId()) 
                                ? 'You' 
                                : (currentUser()->name ?? 'An administrator');
                            
                            // Notify requester
                            notify(
                                $request->user_id,
                                'request_cancelled',
                                'Request Cancelled',
                                "Your request for {$request->destination} on " . formatDate($request->start_datetime) . " has been cancelled.\n\nCancelled by: {$cancelledBy}\nReason: " . trim(post('reason') ?: 'No reason provided'),
                                '/?page=requests&action=view&id=' . $requestId
                            );
                            
                            // Notify passengers using batch function
                            notifyPassengersBatch(
                                $requestId,
                                'request_cancelled',
                                'Trip Cancelled',
                                "The trip to {$request->destination} on " . formatDate($request->start_datetime) . " has been cancelled.\n\nCancelled by: {$cancelledBy}\nReason: " . trim(post('reason') ?: 'No reason provided'),
                                '/?page=requests&action=view&id=' . $requestId
                            );
                            
                            // Notify assigned driver
                            if ($request->driver_id) {
                                notifyDriver(
                                    $request->driver_id,
                                    'trip_cancelled_driver',
                                    'Trip Cancelled',
                                    "A trip you were assigned to drive to {$request->destination} on " . formatDate($request->start_datetime) . " has been cancelled.\n\nCancelled by: {$cancelledBy}\nReason: " . trim(post('reason') ?: 'No reason provided'),
                                    '/?page=requests&action=view&id=' . $requestId
                                );
                            }
                            
                            // Notify requested driver (if different)
                            if ($request->requested_driver_id && $request->requested_driver_id != $request->driver_id) {
                                notifyDriver(
                                    $request->requested_driver_id,
                                    'trip_cancelled_driver',
                                    'Trip Cancelled',
                                    "A trip you were requested to drive to {$request->destination} on " . formatDate($request->start_datetime) . " has been cancelled.\n\nCancelled by: {$cancelledBy}\nReason: " . trim(post('reason') ?: 'No reason provided'),
                                    '/?page=requests&action=view&id=' . $requestId
                                );
                            }
                            
                            // Notify approver if request was pending
                            if (in_array($oldStatus, [STATUS_PENDING, STATUS_PENDING_MOTORPOOL])) {
                                if ($oldStatus === STATUS_PENDING && $request->approver_id) {
                                    notify(
                                        $request->approver_id,
                                        'request_cancelled',
                                        'Request Cancelled',
                                        "Request #{$requestId} for {$request->destination} has been cancelled by the requester.",
                                        '/?page=approvals&action=view&id=' . $requestId
                                    );
                                }
                                if ($oldStatus === STATUS_PENDING_MOTORPOOL && $request->motorpool_head_id) {
                                    notify(
                                        $request->motorpool_head_id,
                                        'request_cancelled',
                                        'Request Cancelled',
                                        "Request #{$requestId} for {$request->destination} has been cancelled.",
                                        '/?page=approvals&action=view&id=' . $requestId
                                    );
                                }
                            }
                            
                            redirectWith('/?page=requests&action=view&id=' . $requestId, 'success', 'Request has been cancelled successfully.');
                            
                        } catch (Exception $e) {
                            db()->rollback();
                            error_log("Request cancellation error: " . $e->getMessage());
                            redirectWith('/?page=requests&action=view&id=' . $requestId, 'danger', 'Failed to cancel request. Please try again.');
                        }
                        ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Are you sure you want to cancel this request?
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted small">Request</label>
                            <div class="fw-bold">#<?= $requestId ?> - <?= e($request->purpose) ?></div>
                            <small class="text-muted"><?= e($request->destination) ?> | <?= formatDateTime($request->start_datetime) ?></small>
                        </div>
                        
                        <?php if ($request->status === STATUS_APPROVED): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> This request has already been approved. 
                            The assigned vehicle and driver will be released.
                            <?php if ($request->vehicle_plate): ?>
                            <br><br>
                            <strong>Vehicle:</strong> <?= e($request->vehicle_plate) ?> - <?= e($request->vehicle_make) ?> <?= e($request->vehicle_model) ?>
                            <?php endif; ?>
                            <?php if ($request->driver_name): ?>
                            <br><strong>Driver:</strong> <?= e($request->driver_name) ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="confirm_cancel" value="1">
                            
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for cancellation</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" 
                                    placeholder="Please provide a reason for cancellation (optional)"></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-x-circle me-1"></i>Yes, Cancel Request
                                </button>
                                <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $requestId ?>" 
                                   class="btn btn-outline-secondary">
                                    No, Go Back
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
