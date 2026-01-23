<?php
/**
 * System Health Check
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== LOKA Fleet Management System Check ===\n\n";

// 1. Check PHP version
echo "1. PHP Version: " . PHP_VERSION . "\n";

// 2. Check required extensions
$required = ['pdo', 'pdo_mysql', 'mbstring', 'openssl'];
$missing = [];
foreach ($required as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if (empty($missing)) {
    echo "2. Required Extensions: OK\n";
} else {
    echo "2. Missing Extensions: " . implode(', ', $missing) . "\n";
}

// 3. Check configuration files
echo "3. Configuration Files:\n";
$configs = [
    'config/database.php',
    'config/constants.php',
    'config/security.php',
    'config/mail.php',
    'config/session.php'
];
foreach ($configs as $config) {
    $path = __DIR__ . '/' . $config;
    if (file_exists($path)) {
        echo "   ✓ $config\n";
    } else {
        echo "   ✗ $config (MISSING)\n";
    }
}

// 4. Check class files
echo "4. Class Files:\n";
$classes = [
    'classes/Database.php',
    'classes/Security.php',
    'classes/Auth.php',
    'classes/Mailer.php',
    'classes/EmailQueue.php'
];
foreach ($classes as $class) {
    $path = __DIR__ . '/' . $class;
    if (file_exists($path)) {
        echo "   ✓ $class\n";
    } else {
        echo "   ✗ $class (MISSING)\n";
    }
}

// 5. Test database connection
echo "5. Database Connection:\n";
try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/classes/Database.php';
    
    $db = Database::getInstance();
    $result = $db->fetch("SELECT DATABASE() as db_name");
    echo "   ✓ Connected to database: " . ($result->db_name ?? DB_NAME) . "\n";
    
    // Check if tables exist
    $tables = $db->fetchAll("SHOW TABLES");
    $tableCount = count($tables);
    echo "   ✓ Found $tableCount tables in database\n";
    
    if ($tableCount > 0) {
        echo "   ✓ Database tables exist\n";
    } else {
        echo "   ⚠ No tables found - you may need to run migrations\n";
    }
} catch (Exception $e) {
    echo "   ✗ Database Error: " . $e->getMessage() . "\n";
}

// 6. Check directories
echo "6. Required Directories:\n";
$dirs = ['logs', 'assets/css', 'assets/js', 'assets/img'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $writable = is_writable($path) ? ' (writable)' : ' (not writable)';
        echo "   ✓ $dir$writable\n";
    } else {
        echo "   ✗ $dir (MISSING)\n";
    }
}

// 7. Check timezone
echo "7. Timezone: " . date_default_timezone_get() . "\n";

// 8. Check URL configuration
echo "8. URL Configuration:\n";
try {
    require_once __DIR__ . '/config/constants.php';
    echo "   APP_URL: " . (defined('APP_URL') ? APP_URL : 'NOT DEFINED') . "\n";
    echo "   SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'NOT DEFINED') . "\n";
} catch (Exception $e) {
    echo "   ✗ Error loading constants: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
echo "Access the application at: http://localhost/fleetManagement/LOKA/\n";
