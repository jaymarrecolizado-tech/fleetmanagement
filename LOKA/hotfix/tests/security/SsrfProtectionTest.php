<?php
/**
 * Security Test - SSRF Protection in notify()
 * 
 * Purpose: Verify that malicious URLs are rejected in notifications
 * 
 * Usage: php tests/security/SsrfProtectionTest.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

echo "=== SSRF PROTECTION TEST ===\n";

$db = Database::getInstance();

// Create test user
$testEmail = 'ssrf_test@example.com';
$db->query("INSERT IGNORE INTO users (email, name, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
    [$testEmail, 'SSRF Test', password_hash('test123', PASSWORD_BCRYPT), 'requester', 'active']);

$user = $db->fetch("SELECT id FROM users WHERE email = ?", [$testEmail]);
if (!$user) {
    die("Failed to create test user\n");
}

$userId = $user->id;

echo "Test User ID: {$userId}\n";

// Test cases: malicious URLs that should be blocked
$maliciousUrls = [
    'http://169.254.169.254/latest/meta-data/iam/security-credentials/',  // AWS metadata
    'http://localhost:8080/admin',  // Internal localhost
    'http://192.168.1.1/secret',  // Internal network
    'http://127.0.0.1:6379',  // Redis
    'http://0.0.0.0:22',  // SSH
    'file:///etc/passwd',  // Local file
    'javascript:alert(1)',  // JavaScript
    'data:text/html,<script>alert(1)</script>',  // Data URI
    'http://evil.com',  // External domain
];

$allowedUrls = [
    '/?page=requests&action=view&id=123',  // Relative path
    'http://localhost/fleetManagement/LOKA/?page=dashboard',  // Internal site
    SITE_URL . '/?page=profile',  // Full internal URL
];

$blockedCount = 0;
$allowedCount = 0;

echo "\nTesting malicious URLs (should be blocked):\n";
foreach ($maliciousUrls as $url) {
    // Create notification with malicious link
    notify($userId, 'default', 'Test Title', 'Test Message', $url);
    
    // Check if link was sanitized
    $notification = $db->fetch(
        "SELECT link FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
        [$userId]
    );
    
    if ($notification->link !== $url || $notification->link === null) {
        echo "  ✓ BLOCKED: " . substr($url, 0, 50) . "...\n";
        $blockedCount++;
    } else {
        echo "  ✗ NOT BLOCKED: {$url}\n";
    }
}

echo "\nTesting allowed URLs (should pass):\n";
foreach ($allowedUrls as $url) {
    notify($userId, 'default', 'Test Title', 'Test Message', $url);
    
    $notification = $db->fetch(
        "SELECT link FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
        [$userId]
    );
    
    if ($notification->link === $url) {
        echo "  ✓ ALLOWED: " . substr($url, 0, 50) . "...\n";
        $allowedCount++;
    } else {
        echo "  ✗ BLOCKED: {$url}\n";
    }
}

// Cleanup
$db->query("DELETE FROM notifications WHERE user_id = ?", [$userId]);
$db->query("DELETE FROM email_queue WHERE to_email = ?", [$testEmail]);
$db->delete('users', 'email = ?', [$testEmail]);

// Assert
$expectedBlocked = count($maliciousUrls);
$expectedAllowed = count($allowedUrls);

echo "\nResults:\n";
echo "  Blocked: {$blockedCount}/{$expectedBlocked}\n";
echo "  Allowed: {$allowedCount}/{$expectedAllowed}\n";

if ($blockedCount === $expectedBlocked && $allowedCount === $expectedAllowed) {
    echo "✓ TEST PASSED: SSRF protection working correctly\n";
    exit(0);
} else {
    echo "✗ TEST FAILED: SSRF protection not working\n";
    exit(1);
}
