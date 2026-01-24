<?php
/**
 * Security Test - Audit Log Tamper Protection
 * 
 * Purpose: Verify that audit logs cannot be modified or deleted
 * 
 * Usage: php tests/security/AuditLogProtectionTest.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';

echo "=== AUDIT LOG TAMPER PROTECTION TEST ===\n";

$db = Database::getInstance();

// Create test audit log
$logId = $db->insert('audit_logs', [
    'user_id' => 1,
    'action' => 'test_action',
    'entity_type' => 'test',
    'entity_id' => 1,
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Agent',
    'created_at' => date('Y-m-d H:i:s')
]);

echo "Created test audit log ID: {$logId}\n";

// Test 1: Try to UPDATE audit log
echo "\nTest 1: Attempting to UPDATE audit log...\n";
try {
    $result = $db->update('audit_logs', 
        ['action' => 'modified_action'], 
        'id = ?', 
        [$logId]
    );
    
    if ($result > 0) {
        echo "✗ TEST FAILED: Audit log was modified (UPDATE allowed)\n";
        exit(1);
    } else {
        echo "✓ UPDATE blocked\n";
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Audit logs cannot be modified') !== false) {
        echo "✓ UPDATE blocked with trigger\n";
    } else {
        echo "✓ UPDATE failed with error: {$e->getMessage()}\n";
    }
}

// Test 2: Try to DELETE audit log
echo "\nTest 2: Attempting to DELETE audit log...\n";
try {
    $result = $db->delete('audit_logs', 'id = ?', [$logId]);
    
    if ($result > 0) {
        echo "✗ TEST FAILED: Audit log was deleted (DELETE allowed)\n";
        exit(1);
    } else {
        echo "✓ DELETE blocked\n";
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Audit logs cannot be deleted') !== false) {
        echo "✓ DELETE blocked with trigger\n";
    } else {
        echo "✓ DELETE failed with error: {$e->getMessage()}\n";
    }
}

// Verify audit log still exists
$log = $db->fetch("SELECT * FROM audit_logs WHERE id = ?", [$logId]);
if ($log && $log->action === 'test_action') {
    echo "\n✓ Audit log integrity verified (still exists and unchanged)\n";
} else {
    echo "\n✗ Audit log was modified or deleted\n";
    exit(1);
}

// Cleanup (if possible via direct query)
$db->query("DELETE FROM audit_logs WHERE id = ?", [$logId]);

echo "✓ TEST PASSED: Audit log tamper protection working\n";
exit(0);
