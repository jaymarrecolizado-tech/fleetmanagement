<?php
/**
 * LOKA - Fleet Management System
 *
 * Main Entry Point / Router
 */

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Set timezone to Manila, Philippines
date_default_timezone_set('Asia/Manila');

// Load configuration (order matters)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/mail.php';

// Environment-based error reporting
// Auto-detect production: check if not localhost and HTTPS is enabled
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) 
    || strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false;
$isProduction = !$isLocalhost && (IS_PRODUCTION || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));

if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    // Try to use Hostinger error log location, fallback to local
    $errorLogPath = __DIR__ . '/logs/error.log';
    if (!is_dir(__DIR__ . '/logs')) {
        @mkdir(__DIR__ . '/logs', 0755, true);
    }
    ini_set('error_log', $errorLogPath);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Load classes (must be before session.php which uses Security class)
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Security.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Mailer.php';
require_once __DIR__ . '/classes/EmailQueue.php';

// Load session after classes (session.php uses Security class)
require_once __DIR__ . '/config/session.php';

// Load helpers
require_once __DIR__ . '/includes/functions.php';

// Initialize Security and send headers
$security = Security::getInstance();
$security->sendSecurityHeaders();

// Check IP access
if (!$security->checkIpAccess()) {
    http_response_code(403);
    die('Access denied.');
}

// Initialize Auth
$auth = new Auth();

// Check remember me on every request
$auth->checkRememberMe();

// Get requested page
$page = get('page', 'dashboard');
$action = get('action', 'index');

// Public pages (no auth required)
$publicPages = ['login', 'logout', 'forgot-password'];

// Route handling
if (!in_array($page, $publicPages)) {
    requireAuth();
}

// Page routing
$pageFile = PAGES_PATH . '/' . $page . '/' . $action . '.php';
$pageIndex = PAGES_PATH . '/' . $page . '/index.php';

// Handle special routes
switch ($page) {
    case 'login':
        require_once PAGES_PATH . '/auth/login.php';
        break;

    case 'logout':
        $auth->logout();
        redirectWith('/?page=login', 'success', 'You have been logged out.');
        break;

    case 'dashboard':
        require_once PAGES_PATH . '/dashboard/index.php';
        break;

    case 'requests':
        if ($action === 'create') {
            require_once PAGES_PATH . '/requests/create.php';
        } elseif ($action === 'view') {
            require_once PAGES_PATH . '/requests/view.php';
        } elseif ($action === 'edit') {
            require_once PAGES_PATH . '/requests/edit.php';
        } elseif ($action === 'cancel') {
            require_once PAGES_PATH . '/requests/cancel.php';
        } elseif ($action === 'complete') {
            require_once PAGES_PATH . '/requests/complete.php';
        } elseif ($action === 'print') {
            require_once PAGES_PATH . '/requests/print.php';
        } else {
            require_once PAGES_PATH . '/requests/index.php';
        }
        break;

    case 'approvals':
        if ($action === 'view') {
            require_once PAGES_PATH . '/approvals/view.php';
        } elseif ($action === 'process') {
            require_once PAGES_PATH . '/approvals/process.php';
        } else {
            require_once PAGES_PATH . '/approvals/index.php';
        }
        break;

    case 'schedule':
        // Calendar is available to all logged-in users
        if ($action === 'calendar') {
            require_once PAGES_PATH . '/schedule/calendar.php';
        } else {
            require_once PAGES_PATH . '/schedule/calendar.php';
        }
        break;

    case 'vehicles':
        requireRole(ROLE_APPROVER);
        if ($action === 'create') {
            require_once PAGES_PATH . '/vehicles/create.php';
        } elseif ($action === 'edit') {
            require_once PAGES_PATH . '/vehicles/edit.php';
        } elseif ($action === 'delete') {
            require_once PAGES_PATH . '/vehicles/delete.php';
        } elseif ($action === 'view') {
            require_once PAGES_PATH . '/vehicles/view.php';
        } else {
            require_once PAGES_PATH . '/vehicles/index.php';
        }
        break;

    case 'drivers':
        requireRole(ROLE_APPROVER);
        if ($action === 'create') {
            require_once PAGES_PATH . '/drivers/create.php';
        } elseif ($action === 'edit') {
            require_once PAGES_PATH . '/drivers/edit.php';
        } elseif ($action === 'delete') {
            require_once PAGES_PATH . '/drivers/delete.php';
        } else {
            require_once PAGES_PATH . '/drivers/index.php';
        }
        break;

    case 'users':
        requireRole(ROLE_MOTORPOOL);
        if ($action === 'create') {
            requireRole(ROLE_ADMIN);
            require_once PAGES_PATH . '/users/create.php';
        } elseif ($action === 'edit') {
            requireRole(ROLE_ADMIN);
            require_once PAGES_PATH . '/users/edit.php';
        } elseif ($action === 'toggle') {
            requireRole(ROLE_ADMIN);
            require_once PAGES_PATH . '/users/toggle.php';
        } else {
            require_once PAGES_PATH . '/users/index.php';
        }
        break;

    case 'departments':
        requireRole(ROLE_MOTORPOOL);
        if ($action === 'create') {
            requireRole(ROLE_ADMIN);
            require_once PAGES_PATH . '/departments/create.php';
        } elseif ($action === 'edit') {
            requireRole(ROLE_ADMIN);
            require_once PAGES_PATH . '/departments/edit.php';
        } else {
            require_once PAGES_PATH . '/departments/index.php';
        }
        break;

    case 'reports':
        requireRole(ROLE_APPROVER);
        if ($action === 'utilization') {
            require_once PAGES_PATH . '/reports/utilization.php';
        } elseif ($action === 'department') {
            require_once PAGES_PATH . '/reports/department.php';
        } elseif ($action === 'export') {
            require_once PAGES_PATH . '/reports/export.php';
        } else {
            require_once PAGES_PATH . '/reports/index.php';
        }
        break;

    case 'notifications':
        if ($action === 'read') {
            require_once PAGES_PATH . '/notifications/read.php';
        } elseif ($action === 'read-all') {
            require_once PAGES_PATH . '/notifications/read-all.php';
        } elseif ($action === 'archive') {
            require_once PAGES_PATH . '/notifications/archive.php';
        } elseif ($action === 'archive-all') {
            require_once PAGES_PATH . '/notifications/archive-all.php';
        } elseif ($action === 'delete') {
            require_once PAGES_PATH . '/notifications/delete.php';
        } elseif ($action === 'delete-all') {
            require_once PAGES_PATH . '/notifications/delete-all.php';
        } else {
            require_once PAGES_PATH . '/notifications/index.php';
        }
        break;

    case 'audit':
        requireRole(ROLE_ADMIN);
        require_once PAGES_PATH . '/audit/index.php';
        break;

    case 'settings':
        requireRole(ROLE_ADMIN);
        if ($action === 'email-queue') {
            require_once PAGES_PATH . '/settings/email-queue.php';
        } else {
            require_once PAGES_PATH . '/settings/index.php';
        }
        break;

    case 'profile':
        require_once PAGES_PATH . '/profile/index.php';
        break;

    case 'api':
        $action = get('action');
        if ($action === 'check_conflict') {
            require_once PAGES_PATH . '/api/check_conflict.php';
        } else {
            jsonResponse(['error' => 'Invalid action'], 404);
        }
        break;

    default:
        // 404 page
        http_response_code(404);
        require_once INCLUDES_PATH . '/header.php';
        echo '<div class="container-fluid py-4"><div class="alert alert-danger">Page not found.</div></div>';
        require_once INCLUDES_PATH . '/footer.php';
        break;
}
