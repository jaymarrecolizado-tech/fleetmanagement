<?php
/**
 * Concurrency Test - Rate Limiting Bypass
 * 
 * Purpose: Verify that rate limiting cannot be bypassed through
 *          concurrent requests
 * 
 * Usage: php tests/concurrency/RateLimitConcurrencyTest.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Security.php';

echo "=== RATE LIMITING CONCURRENCY TEST ===\n";

$db = Database::getInstance();
$security = Security::getInstance();

$testEmail = 'ratelimit_test@example.com';
$action = 'login';
$identifier = $testEmail;
$maxAttempts = 5;

echo "Max attempts allowed: {$maxAttempts}\n";

// Simulate 10 concurrent login attempts
$processes = [];
$numProcesses = 10;
$successCount = 0;

echo "Spawning {$numProcesses} concurrent login attempts...\n";

for ($i = 0; $i < $numProcesses; $i++) {
    $pid = pcntl_fork();
    
    if ($pid == -1) {
        die("Could not fork process\n");
    } else if ($pid == 0) {
        // Child process
        try {
            // Check if rate limited
            $isLimited = $security->isRateLimited($action, $identifier, $maxAttempts, 900);
            
            if (!$isLimited) {
                // Record attempt (simulating failed login)
                $security->recordAttempt($action, $identifier);
                $successCount++;
                echo "Process {$i}: Attempt recorded\n";
            } else {
                echo "Process {$i}: Rate limited\n";
            }
        } catch (Exception $e) {
            echo "Process {$i}: Error - {$e->getMessage()}\n";
        }
        exit(0);
    } else {
        // Parent process
        $processes[] = $pid;
    }
}

// Wait for all child processes
foreach ($processes as $pid) {
    pcntl_waitpid($pid, $status);
}

echo "All processes completed\n";
sleep(1);

// Check database
$attemptCount = $db->fetchColumn(
    "SELECT COUNT(*) FROM rate_limits WHERE action = ? AND identifier = ?",
    [$action, $identifier]
);

echo "Total attempts recorded: {$attemptCount}\n";

// Cleanup
$db->delete('rate_limits', 'action = ? AND identifier = ?', [$action, $identifier]);

// Assert
if ($attemptCount <= $maxAttempts) {
    echo "✓ TEST PASSED: Rate limiting enforced ({$attemptCount} <= {$maxAttempts})\n";
    exit(0);
} else {
    echo "✗ TEST FAILED: Rate limiting bypassed ({$attemptCount} > {$maxAttempts})\n";
    exit(1);
}
