<?php
/**
 * LOKA - View Request Page
 */

$requestId = (int) get('id');

// Get request with all related data
$request = db()->fetch(
    "SELECT r.*, 
            u.name as requester_name, u.email as requester_email, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model, v.color as vehicle_color,
            vt.name as vehicle_type,
            dr.license_number as driver_license,
            (SELECT name FROM users WHERE id = dr.user_id) as driver_name,
            (SELECT phone FROM users WHERE id = dr.user_id) as driver_phone,
            appr.name as approver_name,
            mph.name as motorpool_head_name
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     LEFT JOIN users appr ON r.approver_id = appr.id
     LEFT JOIN users mph ON r.motorpool_head_id = mph.id
     WHERE r.id = ? AND r.deleted_at IS NULL",
    [$requestId]
);

if (!$request) {
    redirectWith('/?page=requests', 'danger', 'Request not found.');
}

// Check access - only owner or approver/admin can view
if ($request->user_id !== userId() && !isApprover()) {
    redirectWith('/?page=requests', 'danger', 'You do not have permission to view this request.');
}

// Mark notifications as read when requester views their request
if ($request->user_id === userId()) {
    db()->update('notifications', 
        ['is_read' => 1], 
        'user_id = ? AND link LIKE ? AND is_read = 0',
        [userId(), '%page=requests%action=view%id=' . $requestId . '%']
    );
}

// Get approval history
$approvals = db()->fetchAll(
    "SELECT a.*, u.name as approver_name
     FROM approvals a
     JOIN users u ON a.approver_id = u.id
     WHERE a.request_id = ?
     ORDER BY a.created_at ASC",
    [$requestId]
);

// Get workflow status
$workflow = db()->fetch(
    "SELECT * FROM approval_workflow WHERE request_id = ?",
    [$requestId]
);

// Get passengers (users and guests)
$passengers = db()->fetchAll(
    "SELECT rp.user_id, u.id as user_table_id, u.name, u.email, d.name as department_name, rp.guest_name
     FROM request_passengers rp
     LEFT JOIN users u ON rp.user_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     WHERE rp.request_id = ?
     ORDER BY u.name, rp.guest_name",
    [$requestId]
);

$pageTitle = 'Request #' . $requestId;

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Request #<?= $requestId ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=requests">Requests</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="<?= APP_URL ?>/?page=requests&action=print&id=<?= $requestId ?>"
                class="btn btn-outline-secondary me-2" target="_blank">
                <i class="bi bi-printer me-1"></i>Print Form
            </a>

            <?php if ($request->user_id === userId() && in_array($request->status, [STATUS_PENDING, STATUS_DRAFT])): ?>
                <a href="<?= APP_URL ?>/?page=requests&action=edit&id=<?= $requestId ?>"
                    class="btn btn-outline-primary me-2">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="<?= APP_URL ?>/?page=requests&action=cancel&id=<?= $requestId ?>" class="btn btn-outline-danger"
                    data-confirm="Cancel this request?">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </a>
            <?php endif; ?>

            <?php if (isMotorpool() && $request->status === STATUS_APPROVED): ?>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeModal">
                    <i class="bi bi-check-circle me-1"></i>Complete Trip
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Details -->
        <div class="col-lg-8">
            <!-- Status Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Status</span>
                            <h3 class="mb-0 mt-1"><?= requestStatusBadge($request->status) ?></h3>
                        </div>
                        <div class="text-end">
                            <span class="text-muted">Created</span>
                            <div class="fw-medium"><?= formatDateTime($request->created_at) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trip Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Trip Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Start Date/Time</label>
                            <div class="fw-medium"><?= formatDateTime($request->start_datetime) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">End Date/Time</label>
                            <div class="fw-medium"><?= formatDateTime($request->end_datetime) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Purpose</label>
                            <div class="fw-medium"><?= nl2br(e($request->purpose)) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Destination</label>
                            <div class="fw-medium"><?= e($request->destination) ?></div>
                        </div>
                        <div class="col-12">
                            <?php 
                            // Calculate actual passenger count: requester (1) + passengers in table
                            $actualPassengerCount = count($passengers) + 1;
                            ?>
                            <label class="text-muted small">Passengers (<?= $actualPassengerCount ?>)</label>
                            <div class="mt-1">
                                <span class="badge bg-primary me-1 mb-1">
                                    <i class="bi bi-person-fill me-1"></i><?= e($request->requester_name) ?> (Requester)
                                </span>
                                <?php foreach ($passengers as $passenger): ?>
                                    <span class="badge bg-<?= $passenger->user_id ? 'secondary' : 'info' ?> me-1 mb-1">
                                        <i class="bi bi-person<?= $passenger->user_id ? '' : '-plus' ?> me-1"></i>
                                        <?= e($passenger->name ?: $passenger->guest_name) ?>
                                        <?= $passenger->user_id ? '' : ' <small>(Guest)</small>' ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($request->notes): ?>
                            <div class="col-12">
                                <label class="text-muted small">Notes</label>
                                <div><?= nl2br(e($request->notes)) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vehicle & Driver Assignment -->
            <?php if ($request->vehicle_id || $request->driver_id): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-car-front me-2"></i>Assignment</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <?php if ($request->vehicle_id): ?>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Vehicle</h6>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                            <i class="bi bi-car-front text-primary fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= e($request->plate_number) ?></div>
                                            <div class="text-muted"><?= e($request->make . ' ' . $request->vehicle_model) ?>
                                            </div>
                                            <small class="text-muted"><?= e($request->vehicle_type) ?> •
                                                <?= e($request->vehicle_color) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($request->driver_id): ?>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Driver</h6>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 rounded p-3 me-3">
                                            <i class="bi bi-person-badge text-success fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= e($request->driver_name) ?></div>
                                            <div class="text-muted"><?= e($request->driver_phone) ?></div>
                                            <small class="text-muted">License: <?= e($request->driver_license) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Approval History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Approval History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($approvals)): ?>
                        <p class="text-muted mb-0">No approval actions yet.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($approvals as $approval): ?>
                                <div class="d-flex mb-3">
                                    <div class="me-3">
                                        <?php if ($approval->status === 'approved'): ?>
                                            <span class="badge bg-success rounded-circle p-2"><i class="bi bi-check-lg"></i></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-circle p-2"><i class="bi bi-x-lg"></i></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-medium">
                                            <?= e($approval->approver_name) ?>
                                            <span class="text-<?= $approval->status === 'approved' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($approval->status) ?>
                                            </span>
                                            (<?= ucfirst($approval->approval_type) ?> Level)
                                        </div>
                                        <small class="text-muted"><?= formatDateTime($approval->created_at) ?></small>
                                        <?php if ($approval->comments): ?>
                                            <div class="mt-1 fst-italic">"<?= e($approval->comments) ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Requester Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>Requester</h6>
                </div>
                <div class="card-body">
                    <div class="fw-bold"><?= e($request->requester_name) ?></div>
                    <div class="text-muted small"><?= e($request->requester_email) ?></div>
                    <?php if ($request->requester_phone): ?>
                        <div class="text-muted small"><?= e($request->requester_phone) ?></div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <span class="badge bg-light text-dark"><?= e($request->department_name) ?></span>
                    </div>
                </div>
            </div>

            <!-- Workflow Status -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Approval Workflow</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <?php if (in_array($request->status, [STATUS_PENDING])): ?>
                                <span class="badge bg-warning rounded-circle p-2"><i class="bi bi-hourglass"></i></span>
                            <?php elseif (in_array($request->status, [STATUS_REJECTED, STATUS_CANCELLED])): ?>
                                <span class="badge bg-danger rounded-circle p-2"><i class="bi bi-x"></i></span>
                            <?php else: ?>
                                <span class="badge bg-success rounded-circle p-2"><i class="bi bi-check"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Department Approval</div>
                            <?php if ($request->approver_name): ?>
                                <small class="text-primary d-block"><i
                                        class="bi bi-person-check me-1"></i><?= e($request->approver_name) ?></small>
                            <?php endif; ?>
                            <small class="text-muted">
                                <?php
                                if ($request->status === STATUS_PENDING)
                                    echo 'Pending';
                                elseif ($request->status === STATUS_REJECTED)
                                    echo 'Rejected';
                                else
                                    echo 'Approved';
                                ?>
                            </small>
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <?php if (in_array($request->status, [STATUS_PENDING_MOTORPOOL])): ?>
                                <span class="badge bg-warning rounded-circle p-2"><i class="bi bi-hourglass"></i></span>
                            <?php elseif (in_array($request->status, [STATUS_APPROVED, STATUS_COMPLETED])): ?>
                                <span class="badge bg-success rounded-circle p-2"><i class="bi bi-check"></i></span>
                            <?php elseif (in_array($request->status, [STATUS_REJECTED])): ?>
                                <span class="badge bg-danger rounded-circle p-2"><i class="bi bi-x"></i></span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-circle p-2"><i class="bi bi-dash"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Motorpool Approval</div>
                            <?php if ($request->motorpool_head_name): ?>
                                <small class="text-primary d-block"><i
                                        class="bi bi-person-check me-1"></i><?= e($request->motorpool_head_name) ?></small>
                            <?php endif; ?>
                            <small class="text-muted">
                                <?php
                                if ($request->status === STATUS_PENDING_MOTORPOOL)
                                    echo 'Pending';
                                elseif (in_array($request->status, [STATUS_APPROVED, STATUS_COMPLETED]))
                                    echo 'Approved';
                                elseif ($request->status === STATUS_REJECTED)
                                    echo 'Rejected';
                                else
                                    echo 'Waiting';
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isMotorpool() && $request->status === STATUS_APPROVED): ?>
    <!-- Complete Trip Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?= APP_URL ?>/?page=requests&action=complete">
                    <?= csrfField() ?>
                    <input type="hidden" name="request_id" value="<?= $requestId ?>">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Complete Trip</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Mark this trip as completed. This will release the vehicle and driver back to
                            available status.</p>

                        <div class="mb-3">
                            <label class="form-label">Vehicle</label>
                            <div class="fw-bold"><?= e($request->plate_number) ?> -
                                <?= e($request->make . ' ' . $request->vehicle_model) ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Driver</label>
                            <div class="fw-bold"><?= e($request->driver_name) ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="ending_mileage" class="form-label">Ending Mileage (Optional)</label>
                            <input type="number" class="form-control" id="ending_mileage" name="ending_mileage"
                                placeholder="Current odometer reading">
                        </div>

                        <div class="mb-3">
                            <label for="completion_notes" class="form-label">Completion Notes (Optional)</label>
                            <textarea class="form-control" id="completion_notes" name="completion_notes" rows="2"
                                placeholder="Any notes about the trip..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i>Mark Complete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>