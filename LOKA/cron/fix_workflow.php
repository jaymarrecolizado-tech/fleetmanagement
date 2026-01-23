<?php
/**
 * LOKA - Workflow Verification & Fix Script
 * Run this to verify and fix any workflow status issues
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== LOKA WORKFLOW VERIFICATION & FIX ===\n\n";

$fixes = 0;
$warnings = 0;

// 1. Check for status mismatches
echo "1. CHECKING STATUS MISMATCHES...\n";

// Workflow=approved but request!=approved
$approvedMismatch = db()->fetchAll('
    SELECT r.id, r.status as request_status, w.status as workflow_status
    FROM requests r
    JOIN approval_workflow w ON r.id = w.request_id
    WHERE w.status = "approved" AND r.status != "approved"
');
foreach ($approvedMismatch as $m) {
    echo "   [MISMATCH] #$m->id: Request=$m->request_status, Workflow=$m->workflow_status\n";
    db()->update('requests', ['status' => 'approved'], 'id = ?', [$m->id]);
    $fixes++;
}

// Workflow=rejected but request!=rejected (and not cancelled)
$rejectedMismatch = db()->fetchAll('
    SELECT r.id, r.status as request_status, w.status as workflow_status
    FROM requests r
    JOIN approval_workflow w ON r.id = w.request_id
    WHERE w.status = "rejected" AND r.status NOT IN ("rejected", "cancelled")
');
foreach ($rejectedMismatch as $m) {
    echo "   [MISMATCH] #$m->id: Request=$m->request_status, Workflow=$m->workflow_status\n";
    db()->update('requests', ['status' => 'rejected'], 'id = ?', [$m->id]);
    $fixes++;
}

// Workflow=cancelled but request!=cancelled
$cancelledMismatch = db()->fetchAll('
    SELECT r.id, r.status as request_status, w.status as workflow_status
    FROM requests r
    JOIN approval_workflow w ON r.id = w.request_id
    WHERE w.status = "cancelled" AND r.status != "cancelled"
');
foreach ($cancelledMismatch as $m) {
    echo "   [MISMATCH] #$m->id: Request=$m->request_status, Workflow=$m->workflow_status\n";
    db()->update('requests', ['status' => 'cancelled'], 'id = ?', [$m->id]);
    $fixes++;
}

// Workflow=revision but request!=revision
$revisionMismatch = db()->fetchAll('
    SELECT r.id, r.status as request_status, w.status as workflow_status
    FROM requests r
    JOIN approval_workflow w ON r.id = w.request_id
    WHERE w.status = "revision" AND r.status != "revision"
');
foreach ($revisionMismatch as $m) {
    echo "   [MISMATCH] #$m->id: Request=$m->request_status, Workflow=$m->workflow_status\n";
    db()->update('requests', ['status' => 'revision'], 'id = ?', [$m->id]);
    $fixes++;
}

if (empty($approvedMismatch) && empty($rejectedMismatch) && empty($cancelledMismatch) && empty($revisionMismatch)) {
    echo "   ✓ No status mismatches found\n";
}

// 2. Check for orphaned workflow records
echo "\n2. CHECKING ORPHANED WORKFLOW RECORDS...\n";
$orphaned = db()->fetchAll('
    SELECT w.id, w.request_id, r.status as request_status
    FROM approval_workflow w
    LEFT JOIN requests r ON w.request_id = r.id
    WHERE r.id IS NULL
');
if (!empty($orphaned)) {
    foreach ($orphaned as $o) {
        echo "   [ORPHAN] Workflow #$o->id for request #$o->request_id (request doesn't exist)\n";
        db()->delete('approval_workflow', 'id = ?', [$o->id]);
        $fixes++;
    }
} else {
    echo "   ✓ No orphaned workflow records\n";
}

// 3. Check for missing workflow records
echo "\n3. CHECKING MISSING WORKFLOW RECORDS...\n";
$missingWorkflow = db()->fetchAll('
    SELECT r.id, r.status, r.purpose
    FROM requests r
    LEFT JOIN approval_workflow w ON r.id = w.request_id
    WHERE w.id IS NULL AND r.status NOT IN ("draft", "cancelled")
');
if (!empty($missingWorkflow)) {
    foreach ($missingWorkflow as $m) {
        echo "   [MISSING] Request #$m->id ($m->status): $m->purpose\n";
        // Create workflow record
        $step = in_array($m->status, ['pending_motorpool', 'approved']) ? 'motorpool' : 'department';
        $wfStatus = $m->status === 'approved' ? 'approved' : 
                    ($m->status === 'rejected' ? 'rejected' : 'pending');
        
        db()->insert('approval_workflow', [
            'request_id' => $m->id,
            'department_id' => $m->department_id ?? 1,
            'step' => $step,
            'status' => $wfStatus,
            'created_at' => date(DATETIME_FORMAT),
            'updated_at' => date(DATETIME_FORMAT)
        ]);
        $fixes++;
    }
    echo "   Created " . count($missingWorkflow) . " workflow record(s)\n";
} else {
    echo "   ✓ All requests have workflow records\n";
}

// 4. Check for missing approval records
echo "\n4. CHECKING MISSING APPROVAL RECORDS...\n";
$missingApprovals = db()->fetchAll('
    SELECT r.id, r.status, r.purpose
    FROM requests r
    WHERE r.status IN ("approved", "rejected")
    AND r.id NOT IN (SELECT request_id FROM approvals)
');
if (!empty($missingApprovals)) {
    echo "   [WARNING] " . count($missingApprovals) . " approved/rejected requests have no approval record\n";
    foreach ($missingApprovals as $m) {
        echo "      #$m->id: $m->purpose\n";
    }
    $warnings += count($missingApprovals);
} else {
    echo "   ✓ All approved/rejected requests have approval records\n";
}

// 5. Verify workflow step matches request status
echo "\n5. CHECKING WORKFLOW STEP CONSISTENCY...\n";
$stepIssues = db()->fetchAll('
    SELECT r.id, r.status as request_status, w.step as workflow_step, r.purpose
    FROM requests r
    JOIN approval_workflow w ON r.id = w.request_id
    WHERE (r.status = "approved" AND w.step = "department")
    OR (r.status = "pending_motorpool" AND w.step != "motorpool")
');
foreach ($stepIssues as $i) {
    echo "   [ISSUE] #$i->id: Status=$i->request_status, Step=$i->workflow_step\n";
    if ($i->request_status === 'approved') {
        db()->update('approval_workflow', ['step' => 'motorpool'], 'request_id = ?', [$i->id]);
        echo "      -> Fixed: Updated step to 'motorpool'\n";
        $fixes++;
    }
}
if (empty($stepIssues)) {
    echo "   ✓ All workflow steps are consistent\n";
}

// 6. Summary
echo "\n=== SUMMARY ===\n";
echo "Fixes applied: $fixes\n";
echo "Warnings: $warnings\n";

$statuses = db()->fetchAll('SELECT status, COUNT(*) as count FROM requests WHERE deleted_at IS NULL GROUP BY status');
echo "\nCurrent Request Status Distribution:\n";
foreach ($statuses as $s) {
    echo "   " . str_pad(ucfirst($s->status), 18) . ": {$s->count}\n";
}

echo "\nDone!\n";
