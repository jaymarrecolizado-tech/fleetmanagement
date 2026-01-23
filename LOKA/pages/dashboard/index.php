<?php
/**
 * LOKA - Dashboard Page
 */

$pageTitle = 'Dashboard';

// Get statistics
$userId = userId();
$userRole = userRole();
$departmentId = currentUser()->department_id;

// Pagination for Recent Activity
$perPage = 10;
$currentPage = max(1, (int) get('p', 1));
$offset = ($currentPage - 1) * $perPage;

// My Requests count
$myRequestsCount = db()->count('requests', 'user_id = ? AND deleted_at IS NULL', [$userId]);

// Pending Approvals count (based on role)
$pendingApprovalsCount = 0;
if (isMotorpool()) {
    $pendingApprovalsCount = db()->count('requests', "status = 'pending_motorpool' AND deleted_at IS NULL");
} elseif (isApprover()) {
    $pendingApprovalsCount = db()->count('requests', "status = 'pending' AND department_id = ? AND deleted_at IS NULL", [$departmentId]);
}

// Available Vehicles count
$availableVehiclesCount = db()->count('vehicles', "status = 'available' AND deleted_at IS NULL");

// Active Drivers count
$activeDriversCount = db()->count('drivers', "status = 'available' AND deleted_at IS NULL");

// Upcoming Trips (approved requests starting in next 7 days)
$upcomingTrips = db()->fetchAll(
    "SELECT r.*, u.name as requester_name, v.plate_number, v.make, v.model
     FROM requests r
     LEFT JOIN users u ON r.user_id = u.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     WHERE r.status = 'approved' 
     AND r.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
     AND r.deleted_at IS NULL
     ORDER BY r.start_datetime ASC
     LIMIT 5"
);

// Recent Activity with pagination (my requests or all for admin)
if (isAdmin()) {
    $totalActivity = db()->count('requests', 'deleted_at IS NULL');
    $recentActivity = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, d.name as department_name
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         WHERE r.deleted_at IS NULL
         ORDER BY r.updated_at DESC
         LIMIT ? OFFSET ?",
        [$perPage, $offset]
    );
} else {
    $totalActivity = db()->count('requests', 'user_id = ? AND deleted_at IS NULL', [$userId]);
    $recentActivity = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, d.name as department_name
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         WHERE r.user_id = ? AND r.deleted_at IS NULL
         ORDER BY r.updated_at DESC
         LIMIT ? OFFSET ?",
        [$userId, $perPage, $offset]
    );
}

$totalPages = ceil($totalActivity / $perPage);

// Vehicle status distribution (for motorpool/admin)
$vehicleStats = [];
if (isMotorpool()) {
    $vehicleStats = db()->fetchAll(
        "SELECT status, COUNT(*) as count FROM vehicles WHERE deleted_at IS NULL GROUP BY status"
    );
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Dashboard</h4>
            <p class="text-muted mb-0">Welcome back, <?= e(currentUser()->name) ?>!</p>
        </div>
        <div>
            <a href="<?= APP_URL ?>/?page=requests&action=create" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Request
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <!-- My Requests -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-primary"><?= $myRequestsCount ?></div>
                            <div class="stat-label">My Requests</div>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isApprover()): ?>
        <!-- Pending Approvals -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-warning"><?= $pendingApprovalsCount ?></div>
                            <div class="stat-label">Pending Approvals</div>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Available Vehicles -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-success"><?= $availableVehiclesCount ?></div>
                            <div class="stat-label">Available Vehicles</div>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-car-front"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Active Drivers -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-info"><?= $activeDriversCount ?></div>
                            <div class="stat-label">Available Drivers</div>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-person-badge"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Upcoming Trips -->
        <div class="col-lg-6">
            <div class="card table-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-calendar-event me-2"></i>Upcoming Trips</h5>
                    <a href="<?= APP_URL ?>/?page=requests" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingTrips)): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-calendar-x"></i>
                        <p class="mb-0">No upcoming trips</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Requester</th>
                                    <th>Vehicle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingTrips as $trip): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><?= formatDateTime($trip->start_datetime) ?></div>
                                    </td>
                                    <td><?= e($trip->requester_name) ?></td>
                                    <td>
                                        <?php if ($trip->plate_number): ?>
                                        <span class="badge bg-light text-dark"><?= e($trip->plate_number) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity / My Requests -->
        <div class="col-lg-6">
            <div class="card table-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-clock-history me-2"></i><?= isAdmin() ? 'Recent Activity' : 'My Requests' ?></h5>
                    <small class="text-muted"><?= $totalActivity ?> total</small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentActivity)): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-inbox"></i>
                        <p class="mb-0">No requests found</p>
                    </div>
                    <?php else: ?>
                    <ul class="activity-feed px-3">
                        <?php foreach ($recentActivity as $activity): ?>
                        <li>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-medium">
                                        <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $activity->id ?>" class="text-decoration-none">
                                            <?= truncate($activity->purpose, 40) ?>
                                        </a>
                                    </div>
                                    <small class="text-muted">
                                        <?= e($activity->requester_name) ?> • <?= e($activity->department_name) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <?= requestStatusBadge($activity->status) ?>
                                    <div class="activity-time"><?= formatDateTime($activity->updated_at) ?></div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-transparent border-top">
                        <nav aria-label="Activity pagination">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <!-- Previous -->
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= APP_URL ?>/?page=dashboard&p=<?= $currentPage - 1 ?>">&laquo;</a>
                                </li>
                                
                                <?php
                                $start = max(1, $currentPage - 2);
                                $end = min($totalPages, $currentPage + 2);
                                
                                if ($start > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= APP_URL ?>/?page=dashboard&p=1">1</a></li>
                                <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= APP_URL ?>/?page=dashboard&p=<?= $i ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?= APP_URL ?>/?page=dashboard&p=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                                <?php endif; ?>
                                
                                <!-- Next -->
                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= APP_URL ?>/?page=dashboard&p=<?= $currentPage + 1 ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                        <div class="text-center mt-2">
                            <small class="text-muted">Page <?= $currentPage ?> of <?= $totalPages ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isMotorpool() && !empty($vehicleStats)): ?>
    <!-- Vehicle Status Overview -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card table-card">
                <div class="card-header">
                    <h5><i class="bi bi-pie-chart me-2"></i>Vehicle Status Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php foreach ($vehicleStats as $stat): ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="py-3">
                                <div class="h3 mb-1"><?= $stat->count ?></div>
                                <div><?= vehicleStatusBadge($stat->status) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
