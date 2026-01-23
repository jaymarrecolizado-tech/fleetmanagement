<?php
/**
 * LOKA - Helper Functions
 */

/**
 * Get database instance
 */
function db(): Database
{
    return Database::getInstance();
}

/**
 * Escape HTML output
 */
function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL
 */
function redirect(string $url): void
{
    header("Location: " . APP_URL . $url);
    exit;
}

/**
 * Redirect with message
 */
function redirectWith(string $url, string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    redirect($url);
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function userId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function userRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user data
 */
function currentUser(): ?object
{
    // First try to get from session
    if (isset($_SESSION['user']) && is_object($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    
    // If not in session but user_id exists, fetch from database
    if (isset($_SESSION['user_id'])) {
        $auth = new Auth();
        $user = $auth->getUser($_SESSION['user_id']);
        if ($user) {
            // Store in session for next time
            $_SESSION['user'] = $user;
            return $user;
        }
    }
    
    return null;
}

/**
 * Check if user has minimum role level
 */
function hasRole(string $minRole): bool
{
    $userLevel = ROLE_LEVELS[userRole()] ?? 0;
    $requiredLevel = ROLE_LEVELS[$minRole] ?? 999;
    return $userLevel >= $requiredLevel;
}

/**
 * Check if user is admin
 */
function isAdmin(): bool
{
    return userRole() === ROLE_ADMIN;
}

/**
 * Check if user is motorpool head or higher
 */
function isMotorpool(): bool
{
    return hasRole(ROLE_MOTORPOOL);
}

/**
 * Check if user is approver or higher
 */
function isApprover(): bool
{
    return hasRole(ROLE_APPROVER);
}

/**
 * Generate CSRF token
 */
function csrfToken(): string
{
    return Security::getInstance()->getCsrfToken();
}

/**
 * CSRF token input field
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Verify CSRF token
 */
function verifyCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return Security::getInstance()->verifyCsrfToken($token);
}

/**
 * Require CSRF validation
 */
function requireCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
        $security = Security::getInstance();
        $security->logSecurityEvent('csrf_validation_failed', 'Request rejected due to invalid CSRF token', userId());
        http_response_code(403);
        die('Invalid or expired security token. Please refresh the page and try again.');
    }
}

/**
 * Format date for display
 */
function formatDate(?string $date): string
{
    if (!$date)
        return '-';
    // Ensure timezone is set to Manila (should be set in index.php, but ensure here too)
    $timezone = date_default_timezone_get();
    if ($timezone !== 'Asia/Manila') {
        date_default_timezone_set('Asia/Manila');
    }
    return date(DISPLAY_DATE, strtotime($date));
}

/**
 * Format datetime for display (Manila timezone)
 */
function formatDateTime(?string $datetime): string
{
    if (!$datetime)
        return '-';
    // Ensure timezone is set to Manila (should be set in index.php, but ensure here too)
    $timezone = date_default_timezone_get();
    if ($timezone !== 'Asia/Manila') {
        date_default_timezone_set('Asia/Manila');
    }
    return date(DISPLAY_DATETIME, strtotime($datetime));
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status, array $labels): string
{
    $info = $labels[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary'];
    return sprintf(
        '<span class="badge bg-%s">%s</span>',
        e($info['color']),
        e($info['label'])
    );
}

/**
 * Get request status badge
 */
function requestStatusBadge(string $status): string
{
    return statusBadge($status, STATUS_LABELS);
}

/**
 * Get vehicle status badge
 */
function vehicleStatusBadge(string $status): string
{
    return statusBadge($status, VEHICLE_STATUS_LABELS);
}

/**
 * Get driver status badge
 */
function driverStatusBadge(string $status): string
{
    return statusBadge($status, DRIVER_STATUS_LABELS);
}

/**
 * Get role badge
 */
function roleBadge(string $role): string
{
    return statusBadge($role, ROLE_LABELS);
}

/**
 * Truncate text
 */
function truncate(string $text, int $length = 50): string
{
    if (strlen($text) <= $length)
        return e($text);
    return e(substr($text, 0, $length)) . '...';
}

/**
 * Get pagination HTML
 */
function pagination(int $total, int $current, int $perPage, string $baseUrl): string
{
    $totalPages = ceil($total / $perPage);
    if ($totalPages <= 1)
        return '';

    $html = '<nav><ul class="pagination pagination-sm justify-content-center">';

    // Previous
    $prevDisabled = $current <= 1 ? 'disabled' : '';
    $html .= sprintf(
        '<li class="page-item %s"><a class="page-link" href="%s&p=%d">&laquo;</a></li>',
        $prevDisabled,
        $baseUrl,
        $current - 1
    );

    // Page numbers
    $start = max(1, $current - 2);
    $end = min($totalPages, $current + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current ? 'active' : '';
        $html .= sprintf(
            '<li class="page-item %s"><a class="page-link" href="%s&p=%d">%d</a></li>',
            $active,
            $baseUrl,
            $i,
            $i
        );
    }

    // Next
    $nextDisabled = $current >= $totalPages ? 'disabled' : '';
    $html .= sprintf(
        '<li class="page-item %s"><a class="page-link" href="%s&p=%d">&raquo;</a></li>',
        $nextDisabled,
        $baseUrl,
        $current + 1
    );

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get and sanitize POST value
 */
function post(string $key, $default = null): mixed
{
    if (!isset($_POST[$key]))
        return $default;
    $value = $_POST[$key];

    // Don't sanitize arrays, return as-is for further processing
    if (is_array($value))
        return $value;

    return trim($value);
}

/**
 * Get sanitized POST string (with HTML stripping)
 */
function postSafe(string $key, $default = null, int $maxLength = 0): string
{
    $value = post($key, $default);
    if ($value === null)
        return '';
    return Security::getInstance()->sanitizeString($value, $maxLength ?: MAX_INPUT_LENGTH);
}

/**
 * Get and sanitize GET value
 */
function get(string $key, $default = null): mixed
{
    if (!isset($_GET[$key]))
        return $default;
    $value = $_GET[$key];

    // Don't sanitize arrays
    if (is_array($value))
        return $value;

    return trim($value);
}

/**
 * Get sanitized GET string (with HTML stripping)
 */
function getSafe(string $key, $default = null, int $maxLength = 0): string
{
    $value = get($key, $default);
    if ($value === null)
        return '';
    return Security::getInstance()->sanitizeString($value, $maxLength ?: MAX_INPUT_LENGTH);
}

/**
 * Get sanitized integer from POST
 */
function postInt(string $key, int $default = 0): int
{
    return Security::getInstance()->sanitizeInt(post($key, $default));
}

/**
 * Get sanitized integer from GET
 */
function getInt(string $key, int $default = 0): int
{
    return Security::getInstance()->sanitizeInt(get($key, $default));
}

/**
 * Create audit log entry
 */
function auditLog(string $action, string $entityType, ?int $entityId = null, ?array $oldData = null, ?array $newData = null): void
{
    db()->insert('audit_logs', [
        'user_id' => userId(),
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'old_data' => $oldData ? json_encode($oldData) : null,
        'new_data' => $newData ? json_encode($newData) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'created_at' => date(DATETIME_FORMAT)
    ]);
}

/**
 * Create notification and send email
 */
function notify(int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    // Save to database (in-app notification)
    $notifId = db()->insert('notifications', [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'link' => $link,
        'is_read' => 0,
        'created_at' => date(DATETIME_FORMAT)
    ]);

    // Queue email notification ONLY - never process during request to prevent lag
    try {
        $user = db()->fetch("SELECT email, name FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if ($user && $user->email) {
            // Check if template exists, if not use a default one
            $templateKey = isset(MAIL_TEMPLATES[$type]) ? $type : 'default';
            
            $queue = new EmailQueue();
            $emailId = $queue->queueTemplate($user->email, $templateKey, [
                'message' => $message,
                'link' => $link,
                'link_text' => 'View Details'
            ], $user->name);
            
            error_log("NOTIFY: User #{$userId} ({$user->email}) - Notification #{$notifId} created, Email #{$emailId} queued (type: {$type})");
        } else {
            error_log("NOTIFY WARN: User #{$userId} has no email - Notification #{$notifId} created but email NOT queued");
        }
    } catch (Exception $e) {
        error_log("NOTIFY ERROR: Email queue failed for user #{$userId}: " . $e->getMessage());
    }
}

/**
 * Notify all passengers of a request about status changes
 * Only notifies users (not guests) since guests don't have email addresses
 */
function notifyPassengers(int $requestId, string $type, string $title, string $message, ?string $link = null): void
{
    // Get all user passengers (exclude guests who don't have user_id)
    $passengers = db()->fetchAll(
        "SELECT rp.user_id, u.name, u.email 
         FROM request_passengers rp
         LEFT JOIN users u ON rp.user_id = u.id
         WHERE rp.request_id = ? 
         AND rp.user_id IS NOT NULL 
         AND u.status = 'active' 
         AND u.deleted_at IS NULL",
        [$requestId]
    );

    if (empty($passengers)) {
        error_log("NOTIFY PASSENGERS: Request #{$requestId} - No passenger users to notify");
        return;
    }

    $notified = 0;
    foreach ($passengers as $passenger) {
        if ($passenger->user_id && $passenger->email) {
            try {
                notify($passenger->user_id, $type, $title, $message, $link);
                $notified++;
            } catch (Exception $e) {
                error_log("NOTIFY PASSENGERS ERROR: Failed to notify passenger {$passenger->user_id}: " . $e->getMessage());
            }
        }
    }
    error_log("NOTIFY PASSENGERS: Request #{$requestId} - Notified {$notified} passengers (type: {$type})");
}

/**
 * Batch notify all passengers - More efficient for large groups
 * Creates notifications and queues emails in optimized batches
 * 
 * @param int $requestId The request ID
 * @param string $type Notification type key
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link Optional link
 * @return int Number of passengers notified
 */
function notifyPassengersBatch(int $requestId, string $type, string $title, string $message, ?string $link = null): int
{
    // Single query to get all passengers
    $passengers = db()->fetchAll(
        "SELECT rp.user_id, u.email, u.name 
         FROM request_passengers rp
         LEFT JOIN users u ON rp.user_id = u.id
         WHERE rp.request_id = ? AND rp.user_id IS NOT NULL 
         AND u.status = 'active' AND u.deleted_at IS NULL",
        [$requestId]
    );
    
    if (empty($passengers)) {
        error_log("NOTIFY PASSENGERS BATCH: Request #{$requestId} - No passengers to notify");
        return 0;
    }
    
    // Batch insert notifications in a transaction
    db()->beginTransaction();
    $notified = 0;
    $now = date(DATETIME_FORMAT);
    
    foreach ($passengers as $passenger) {
        try {
            db()->insert('notifications', [
                'user_id' => $passenger->user_id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'is_read' => 0,
                'created_at' => $now
            ]);
            $notified++;
        } catch (Exception $e) {
            error_log("NOTIFY PASSENGERS BATCH ERROR: Failed to create notification for user {$passenger->user_id}: " . $e->getMessage());
        }
    }
    
    db()->commit();
    
    // Queue emails in batch AFTER transaction commits
    $queue = new EmailQueue();
    $emailsQueued = 0;
    
    foreach ($passengers as $passenger) {
        if ($passenger->email) {
            try {
                $templateKey = isset(MAIL_TEMPLATES[$type]) ? $type : 'default';
                $queue->queueTemplate($passenger->email, $templateKey, [
                    'message' => $message,
                    'link' => $link,
                    'link_text' => 'View Details'
                ], $passenger->name);
                $emailsQueued++;
            } catch (Exception $e) {
                error_log("NOTIFY PASSENGERS BATCH ERROR: Failed to queue email for {$passenger->email}: " . $e->getMessage());
            }
        }
    }
    
    error_log("NOTIFY PASSENGERS BATCH: Request #{$requestId} - Notified {$notified} passengers, queued {$emailsQueued} emails (type: {$type})");
    return $notified;
}

/**
 * Notify driver about request status changes
 * Notifies both requested driver (if specified) and assigned driver (if assigned)
 */
function notifyDriver(?int $driverId, string $type, string $title, string $message, ?string $link = null): void
{
    if (!$driverId) {
        error_log("NOTIFY DRIVER: No driver ID provided");
        return;
    }
    
    $driver = db()->fetch(
        "SELECT d.user_id, d.status as driver_status, d.deleted_at as driver_deleted,
                u.name, u.email, u.status as user_status, u.deleted_at as user_deleted
         FROM drivers d
         LEFT JOIN users u ON d.user_id = u.id
         WHERE d.id = ?",
        [$driverId]
    );
    
    if (!$driver) {
        error_log("NOTIFY DRIVER: Driver #{$driverId} not found");
        return;
    }
    
    if (!$driver->user_id || !$driver->email || $driver->user_status !== 'active' || $driver->user_deleted || $driver->driver_deleted) {
        error_log("NOTIFY DRIVER: Driver #{$driverId} is inactive or deleted - skipping notification");
        return;
    }
    
    try {
        notify($driver->user_id, $type, $title, $message, $link);
        error_log("NOTIFY DRIVER: Driver #{$driverId} ({$driver->email}) notified (type: {$type})");
    } catch (Exception $e) {
        error_log("NOTIFY DRIVER ERROR: Failed to notify driver #{$driverId}: " . $e->getMessage());
    }
}

/**
 * Notify all parties (requester, passengers, driver) about request status changes
 */
function notifyAllParties(int $requestId, string $type, string $title, string $message, ?string $link = null): void
{
    $request = db()->fetch(
        "SELECT user_id, requested_driver_id, driver_id 
         FROM requests 
         WHERE id = ? AND deleted_at IS NULL",
        [$requestId]
    );
    
    if (!$request) {
        return;
    }
    
    notify($request->user_id, $type, $title, $message, $link);
    notifyPassengers($requestId, $type, $title, $message, $link);
    
    $driverId = $request->driver_id ?: $request->requested_driver_id;
    if ($driverId) {
        notifyDriver($driverId, $type, $title, $message, $link);
    }
}

/**
 * Process email queue asynchronously (non-blocking)
 * NOTE: This function is kept for backward compatibility but does nothing.
 * Emails are processed by the cron job (process_queue.php) to prevent app lag.
 * 
 * To process emails, run: php LOKA/cron/process_queue.php
 * Or set up a Windows Task Scheduler job to run it every 1-2 minutes.
 */
function processEmailQueueAsync(): void
{
    // Do nothing - emails are processed by cron job only
    // This prevents any lag during requests
    return;
}

/**
 * Get unread notification count
 */
function unreadNotificationCount(): int
{
    if (!isLoggedIn())
        return 0;
    return db()->count('notifications', 'user_id = ? AND is_read = 0 AND is_archived = 0 AND deleted_at IS NULL', [userId()]);
}

/**
 * JSON response
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Require authentication
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            jsonResponse(['error' => 'Unauthorized'], 401);
        }
        redirectWith('/?page=login', 'warning', 'Please log in to continue.');
    }
}

/**
 * Require minimum role
 */
function requireRole(string $role): void
{
    requireAuth();
    if (!hasRole($role)) {
        redirectWith('/?page=dashboard', 'danger', 'You do not have permission to access this page.');
    }
}

/**
 * Check for driver schedule conflicts
 * 
 * @return array|null Conflict details if found, null otherwise
 */
function checkDriverConflict(int $driverId, string $start, string $end, ?int $excludeRequestId = null): ?array
{
    $sql = "SELECT r.*, u.name as requester_name 
            FROM requests r
            JOIN users u ON r.user_id = u.id
            WHERE r.driver_id = ? 
            AND r.status IN ('approved', 'pending_motorpool')
            AND r.start_datetime < ? 
            AND r.end_datetime > ?
            AND r.deleted_at IS NULL";

    $params = [$driverId, $end, $start];

    if ($excludeRequestId) {
        $sql .= " AND r.id != ?";
        $params[] = $excludeRequestId;
    }

    $conflict = db()->fetch($sql, $params);
    return $conflict ? (array) $conflict : null;
}

/**
 * Check for vehicle schedule conflicts
 * 
 * @return array|null Conflict details if found, null otherwise
 */
function checkVehicleConflict(int $vehicleId, string $start, string $end, ?int $excludeRequestId = null): ?array
{
    $sql = "SELECT r.*, u.name as requester_name 
            FROM requests r
            JOIN users u ON r.user_id = u.id
            WHERE r.vehicle_id = ? 
            AND r.status IN ('approved', 'pending_motorpool')
            AND r.start_datetime < ? 
            AND r.end_datetime > ?
            AND r.deleted_at IS NULL";

    $params = [$vehicleId, $end, $start];

    if ($excludeRequestId) {
        $sql .= " AND r.id != ?";
        $params[] = $excludeRequestId;
    }

    $conflict = db()->fetch($sql, $params);
    return $conflict ? (array) $conflict : null;
}

/**
 * Active menu class
 */
function activeMenu(string $page): string
{
    return (get('page', 'dashboard') === $page) ? 'active' : '';
}
