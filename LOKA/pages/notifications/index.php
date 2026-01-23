<?php
/**
 * LOKA - Notifications Page
 */

$pageTitle = 'Notifications';
$view = get('view', 'inbox'); // 'inbox' or 'archive'
$isArchive = $view === 'archive';

$where = "user_id = ? AND deleted_at IS NULL";
$params = [userId()];

if ($isArchive) {
    $where .= " AND is_archived = 1";
} else {
    $where .= " AND is_archived = 0";
}

$notifications = db()->fetchAll(
    "SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC LIMIT 100",
    $params
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Notifications</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Notifications</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            <?php if (!$isArchive): ?>
                <a href="<?= APP_URL ?>/?page=notifications&action=read-all" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-check-all me-1"></i>Mark All Read
                </a>
                <a href="<?= APP_URL ?>/?page=notifications&action=archive-all" class="btn btn-outline-secondary btn-sm"
                    data-confirm="Archive all notifications in inbox?">
                    <i class="bi bi-archive me-1"></i>Archive All
                </a>
            <?php endif; ?>
            <form method="POST" action="<?= APP_URL ?>/?page=notifications&action=delete-all&view=<?= $view ?>" 
                  class="d-inline" onsubmit="return confirm('Are you sure you want to permanently clear all <?= $isArchive ? 'archived' : 'inbox' ?> notifications? This action cannot be undone.');">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>Clear All
                </button>
            </form>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= !$isArchive ? 'active' : '' ?>" href="<?= APP_URL ?>/?page=notifications&view=inbox">
                <i class="bi bi-inbox me-1"></i>Inbox
                <?php
                $unread = unreadNotificationCount();
                if ($unread > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $unread ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $isArchive ? 'active' : '' ?>"
                href="<?= APP_URL ?>/?page=notifications&view=archive">
                <i class="bi bi-archive me-1"></i>Archive
            </a>
        </li>
    </ul>

    <div class="card table-card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="empty-state py-5">
                    <div class="text-center">
                        <i class="bi bi-bell-slash display-4 text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No notifications in <?= $view ?></h5>
                        <p class="text-muted">You're all caught up!</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notif): ?>
                        <div
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $notif->is_read ? '' : 'bg-light border-start border-primary border-4' ?>">
                            <div class="d-flex align-items-start flex-grow-1">
                                <div
                                    class="p-2 me-3 bg-opacity-10 rounded-circle <?= $notif->is_read ? 'bg-secondary' : 'bg-primary' ?>">
                                    <i class="bi bi-bell <?= $notif->is_read ? 'text-secondary' : 'text-primary' ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between mb-1">
                                        <h6 class="mb-0 <?= $notif->is_read ? '' : 'fw-bold' ?>">
                                            <a href="<?= APP_URL ?>/?page=notifications&action=read&id=<?= $notif->id ?>"
                                                class="text-decoration-none text-dark">
                                                <?= e($notif->title) ?>
                                            </a>
                                        </h6>
                                        <span class="text-muted small"><?= formatDateTime($notif->created_at) ?></span>
                                    </div>
                                    <p class="mb-1 text-muted small"><?= e($notif->message) ?></p>
                                    <div class="d-flex gap-3">
                                        <?php if ($notif->link): ?>
                                            <a href="<?= APP_URL ?>/?page=notifications&action=read&id=<?= $notif->id ?>"
                                                class="btn btn-link btn-sm p-0 text-decoration-none small">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>View Details
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Item Actions -->
                            <div class="ms-3 dropdown">
                                <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <a class="dropdown-item"
                                            href="<?= APP_URL ?>/?page=notifications&action=archive&id=<?= $notif->id ?>&view=<?= $view ?>">
                                            <i class="bi <?= $isArchive ? 'bi-inbox' : 'bi-archive' ?> me-2"></i>
                                            <?= $isArchive ? 'Move to Inbox' : 'Archive' ?>
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="<?= APP_URL ?>/?page=notifications&action=delete&view=<?= $view ?>" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $notif->id ?>">
                                            <button type="submit" class="dropdown-item text-danger" data-confirm="Delete this notification?">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>