<?php
/**
 * LOKA - Reports Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Reports';

// Date range
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));

// Vehicle utilization stats
$vehicleStats = db()->fetchAll(
    "SELECT v.plate_number, v.make, v.model, COUNT(r.id) as trip_count,
            SUM(TIMESTAMPDIFF(HOUR, r.start_datetime, r.end_datetime)) as total_hours
     FROM vehicles v
     LEFT JOIN requests r ON v.id = r.vehicle_id AND r.status IN ('approved', 'completed')
            AND r.start_datetime BETWEEN ? AND ?
     WHERE v.deleted_at IS NULL
     GROUP BY v.id
     ORDER BY trip_count DESC
     LIMIT 10",
    [$startDate, $endDate . ' 23:59:59']
);

// Department usage stats
$deptStats = db()->fetchAll(
    "SELECT d.name as department_name, COUNT(r.id) as request_count,
            SUM(CASE WHEN r.status = 'approved' OR r.status = 'completed' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
     FROM departments d
     LEFT JOIN requests r ON d.id = r.department_id AND r.created_at BETWEEN ? AND ?
     WHERE d.deleted_at IS NULL
     GROUP BY d.id
     ORDER BY request_count DESC",
    [$startDate, $endDate . ' 23:59:59']
);

// Overall stats
$overallStats = db()->fetch(
    "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' OR status = 'completed' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' OR status = 'pending_motorpool' THEN 1 ELSE 0 END) as pending
     FROM requests
     WHERE created_at BETWEEN ? AND ? AND deleted_at IS NULL",
    [$startDate, $endDate . ' 23:59:59']
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Reports</h4>
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li><li class="breadcrumb-item active">Reports</li></ol></nav>
        </div>
        <a href="<?= APP_URL ?>/?page=reports&action=export&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-outline-primary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
    
    <!-- Date Filter -->
    <div class="card table-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="reports">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="text" class="form-control datepicker" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="text" class="form-control datepicker" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-filter me-1"></i>Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-primary"><?= $overallStats->total_requests ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-success"><?= $overallStats->approved ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-danger"><?= $overallStats->rejected ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-warning"><?= $overallStats->pending ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Vehicle Utilization -->
        <div class="col-lg-6">
            <div class="card table-card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle Utilization</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Vehicle</th><th>Trips</th><th>Hours</th></tr></thead>
                            <tbody>
                                <?php foreach ($vehicleStats as $stat): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($stat->plate_number) ?></strong>
                                        <small class="d-block text-muted"><?= e($stat->make . ' ' . $stat->model) ?></small>
                                    </td>
                                    <td><?= $stat->trip_count ?></td>
                                    <td><?= $stat->total_hours ?: 0 ?>h</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department Usage -->
        <div class="col-lg-6">
            <div class="card table-card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-building me-2"></i>Department Usage</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Department</th><th>Requests</th><th>Approved</th><th>Rejected</th></tr></thead>
                            <tbody>
                                <?php foreach ($deptStats as $stat): ?>
                                <tr>
                                    <td><strong><?= e($stat->department_name) ?></strong></td>
                                    <td><?= $stat->request_count ?></td>
                                    <td><span class="text-success"><?= $stat->approved_count ?></span></td>
                                    <td><span class="text-danger"><?= $stat->rejected_count ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
