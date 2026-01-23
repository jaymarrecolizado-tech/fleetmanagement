<?php
/**
 * LOKA - Requests List Page
 */

$pageTitle = 'My Requests';

// Get filter parameters
$statusFilter = get('status', '');
$searchFilter = get('search', '');

// Build query based on role
$params = [];
$whereClause = 'r.deleted_at IS NULL';

if (!isAdmin()) {
    $whereClause .= ' AND r.user_id = ?';
    $params[] = userId();
}

if ($statusFilter) {
    $whereClause .= ' AND r.status = ?';
    $params[] = $statusFilter;
}

if ($searchFilter) {
    $whereClause .= ' AND (r.purpose LIKE ? OR r.destination LIKE ?)';
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
}

$requests = db()->fetchAll(
    "SELECT r.*, u.name as requester_name, d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model,
            dr.license_number as driver_license,
            dr_u.name as driver_name,
            appr.name as approver_name,
            mph.name as motorpool_name
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     LEFT JOIN users dr_u ON dr.user_id = dr_u.id
     LEFT JOIN users appr ON r.approver_id = appr.id
     LEFT JOIN users mph ON r.motorpool_head_id = mph.id
     WHERE {$whereClause}
     ORDER BY r.created_at DESC",
    $params
);

// Fetch notification counts separately (eliminates N+1 query)
$notificationCounts = [];
if (!empty($requests) && !isAdmin()) {
    $requestIds = array_column($requests, 'id');
    if (!empty($requestIds)) {
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $linkConditions = array_map(fn($id) => "n.link LIKE ?", $requestIds);
        $notifications = db()->fetchAll(
            "SELECT n.link, COUNT(*) as count
             FROM notifications n
             WHERE n.user_id = ?
             AND n.is_read = 0
             AND n.deleted_at IS NULL
             AND n.link LIKE '%page=requests%action=view%'
             AND (" . implode(' OR ', $linkConditions) . ")
             GROUP BY n.link",
            array_merge([userId()], array_map(fn($id) => "%id={$id}%", $requestIds)),
            'link'
        );

        // Match notification counts to requests
        foreach ($requests as $request) {
            $linkPattern = "id={$request->id}";
            $count = 0;
            foreach ($notifications as $link => $data) {
                if (strpos($link, $linkPattern) !== false) {
                    $count = $data['count'];
                    break;
                }
            }
            $request->unread_notifications = $count;
        }
    }
} else {
    // Admin doesn't have unread notifications on requests
    foreach ($requests as $request) {
        $request->unread_notifications = 0;
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">My Requests</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Requests</li>
                </ol>
            </nav>
        </div>
        <a href="<?= APP_URL ?>/?page=requests&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card table-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="requests">
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (STATUS_LABELS as $key => $info): ?>
                        <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>>
                            <?= e($info['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search purpose or destination..." 
                           value="<?= e($searchFilter) ?>">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="<?= APP_URL ?>/?page=requests" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Requests Table -->
    <div class="card table-card">
        <div class="card-body">
            <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-x"></i>
                <h5>No requests found</h5>
                <p class="text-muted">Create your first vehicle request to get started.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date/Time</th>
                            <th>Purpose</th>
                            <th>Destination</th>
                            <th>Vehicle</th>
                            <th>Status</th>
                            <th>Stage</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><strong>#<?= $request->id ?></strong></td>
                            <td>
                                <div><?= formatDateTime($request->start_datetime) ?></div>
                                <small class="text-muted">to <?= formatDateTime($request->end_datetime) ?></small>
                            </td>
                            <td><?= truncate($request->purpose, 30) ?></td>
                            <td><?= truncate($request->destination, 25) ?></td>
                            <td>
                                <?php if ($request->plate_number): ?>
                                <span class="badge bg-light text-dark"><?= e($request->plate_number) ?></span>
                                <small class="d-block text-muted"><?= e($request->make . ' ' . $request->vehicle_model) ?></small>
                                <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= requestStatusBadge($request->status) ?>
                                <?php if (in_array($request->status, [STATUS_PENDING, STATUS_PENDING_MOTORPOOL]) && $request->unread_notifications > 0): ?>
                                <span class="badge bg-danger ms-1" title="New updates">New</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if ($request->status === STATUS_PENDING) {
                                    $who = $request->approver_name ?: 'Dept Approver';
                                    echo '<span class="badge bg-warning text-dark">Waiting on ' . e($who) . '</span>';
                                } elseif ($request->status === STATUS_PENDING_MOTORPOOL) {
                                    $who = $request->motorpool_name ?: 'Motorpool Head';
                                    echo '<span class="badge bg-info text-dark">Waiting on ' . e($who) . '</span>';
                                } else {
                                    echo '<span class="text-muted">—</span>';
                                }
                                ?>
                            </td>
                            <td><?= formatDate($request->created_at) ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $request->id ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (in_array($request->status, [STATUS_PENDING, STATUS_DRAFT])): ?>
                                    <a href="<?= APP_URL ?>/?page=requests&action=edit&id=<?= $request->id ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= APP_URL ?>/?page=requests&action=cancel&id=<?= $request->id ?>" 
                                       class="btn btn-sm btn-outline-danger" title="Cancel"
                                       data-confirm="Are you sure you want to cancel this request?">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
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

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
