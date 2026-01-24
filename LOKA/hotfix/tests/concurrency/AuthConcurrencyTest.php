<?php
/**
 * Concurrency Test - Auth Remember Me Token Race Condition
 * 
 * Purpose: Verify that only 1 remember token exists per user after
 *          concurrent requests
 * 
 * Usage: php tests/concurrency/AuthConcurrencyTest.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';

echo "=== AUTH CONCURRENCY TEST ===\n";

// Create test user
$db = Database::getInstance();
$testEmail = 'concurrency_test@example.com';
$db->query("INSERT IGNORE INTO users (email, name, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
    [$testEmail, 'Concurrency Test', password_hash('test123', PASSWORD_BCRYPT), 'requester', 'active']);

$user = $db->fetch("SELECT id FROM users WHERE email = ?", [$testEmail]);
if (!$user) {
    die("Failed to create test user\n");
}

$userId = $user->id;
echo "Test User ID: {$userId}\n";

// Simulate 10 concurrent requests using PHP's pcntl_fork
$processes = [];
$numProcesses = 10;

echo "Spawning {$numProcesses} concurrent processes...\n";

for ($i = 0; $i < $numProcesses; $i++) {
    $pid = pcntl_fork();
    
    if ($pid == -1) {
        die("Could not fork process\n");
    } else if ($pid == 0) {
        // Child process
        try {
            $auth = new Auth();
            // Call setRememberToken directly (simulating login)
            $reflection = new ReflectionClass($auth);
            $method = $reflection->getMethod('setRememberToken');
            $method->setAccessible(true);
            $method->invoke($auth, $userId);
            
            echo "Process {$i}: Token created successfully\n";
        } catch (Exception $e) {
            echo "Process {$i}: Error - {$e->getMessage()}\n";
        }
        exit(0);
    } else {
        // Parent process
        $processes[] = $pid;
    }
}

// Wait for all child processes to complete
foreach ($processes as $pid) {
    pcntl_waitpid($pid, $status);
}

echo "All processes completed\n";

// Check result
sleep(1); // Wait for transactions to commit
$tokenCount = $db->fetchColumn(
    "SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?",
    [$userId]
);

echo "Total tokens created: {$tokenCount}\n";

// Cleanup
$db->delete('remember_tokens', 'user_id = ?', [$userId]);
$db->delete('users', 'email = ?', [$testEmail]);

// Assert
if ($tokenCount == 1) {
    echo "✓ TEST PASSED: Only 1 token created (race condition fixed)\n";
    exit(0);
} else {
    echo "✗ TEST FAILED: {$tokenCount} tokens created (race condition exists)\n";
    exit(1);
}
