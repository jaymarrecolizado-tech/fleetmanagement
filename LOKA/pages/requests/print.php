<?php
/**
 * LOKA - Printable Trip Request Form
 * Generates a document with approval status and signature lines
 */

$requestId = (int) get('id');

// Get request with all related data
$request = db()->fetch(
    "SELECT r.*, 
            u.name as requester_name, u.email as requester_email, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model,
            vt.name as vehicle_type,
            (SELECT name FROM users WHERE id = dr.user_id) as driver_name,
            (SELECT phone FROM users WHERE id = dr.user_id) as driver_phone
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     WHERE r.id = ? AND r.deleted_at IS NULL",
    [$requestId]
);

if (!$request) {
    redirectWith('/?page=requests', 'danger', 'Request not found.');
}

// Check access
if ($request->user_id !== userId() && !isApprover()) {
    redirectWith('/?page=requests', 'danger', 'You do not have permission to view this request.');
}

// Get approval records
$deptApproval = db()->fetch(
    "SELECT a.*, u.name as approver_name 
     FROM approvals a 
     JOIN users u ON a.approver_id = u.id 
     WHERE a.request_id = ? AND a.approval_type = 'department'",
    [$requestId]
);

$motorpoolApproval = db()->fetch(
    "SELECT a.*, u.name as approver_name 
     FROM approvals a 
     JOIN users u ON a.approver_id = u.id 
     WHERE a.request_id = ? AND a.approval_type = 'motorpool'",
    [$requestId]
);

// Get workflow for pending approvers
$workflow = db()->fetch("SELECT * FROM approval_workflow WHERE request_id = ?", [$requestId]);

// Get passengers (users and guests)
$passengers = db()->fetchAll(
    "SELECT u.name, d.name as department_name, rp.guest_name
     FROM request_passengers rp
     LEFT JOIN users u ON rp.user_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     WHERE rp.request_id = ?
     ORDER BY u.name, rp.guest_name",
    [$requestId]
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Request Form #<?= $requestId ?> - LOKA</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 0.5in;
        }

        .document {
            max-width: 8.5in;
            margin: 0 auto;
            background: #fff;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 14pt;
            font-weight: normal;
        }

        .header .control-no {
            font-size: 11pt;
            margin-top: 10px;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            font-size: 11pt;
            background: #f0f0f0;
            padding: 5px 10px;
            border: 1px solid #000;
            border-bottom: none;
        }

        .section-content {
            border: 1px solid #000;
            padding: 10px;
        }

        .row {
            display: flex;
            margin-bottom: 8px;
        }

        .row:last-child {
            margin-bottom: 0;
        }

        .label {
            font-weight: bold;
            width: 150px;
            flex-shrink: 0;
        }

        .value {
            flex: 1;
            border-bottom: 1px solid #999;
            padding-left: 5px;
        }

        .col-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .approval-box {
            border: 1px solid #000;
            padding: 15px;
            min-height: 180px;
        }

        .approval-box h3 {
            font-size: 11pt;
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #000;
        }

        .approval-status {
            text-align: center;
            margin-bottom: 15px;
            padding: 8px;
            font-weight: bold;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #856404;
        }

        .signature-line {
            margin-top: 30px;
            text-align: center;
        }

        .signature-line .line {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto 5px;
        }

        .signature-line .name {
            font-weight: bold;
        }

        .signature-line .title {
            font-size: 10pt;
            color: #666;
        }

        .signature-line .date {
            font-size: 10pt;
            margin-top: 5px;
        }

        .checkbox-row {
            display: flex;
            gap: 20px;
            margin: 10px 0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .checkbox {
            width: 14px;
            height: 14px;
            border: 1px solid #000;
            display: inline-block;
        }

        .checkbox.checked {
            background: #000;
            position: relative;
        }

        .checkbox.checked::after {
            content: '✓';
            color: #fff;
            font-size: 10px;
            position: absolute;
            top: -1px;
            left: 2px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #0d6efd;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .print-btn:hover {
            background: #0b5ed7;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            right: 120px;
            padding: 10px 20px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-btn,
            .back-btn {
                display: none;
            }
        }
    </style>
</head>

<body>
    <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $requestId ?>" class="back-btn">← Back</a>
    <button class="print-btn" onclick="window.print()">🖨️ Print</button>

    <div class="document">
        <!-- Header -->
        <div class="header">
            <h1>DEPARTMENT OF INFORMATION AND COMMUNICATIONS TECHNOLOGY</h1>
            <h2>VEHICLE REQUEST FORM</h2>
            <div class="control-no">Control No.:
                <strong>VRF-<?= date('Y') ?>-<?= str_pad($requestId, 5, '0', STR_PAD_LEFT) ?></strong></div>
        </div>

        <!-- Requester Information -->
        <div class="section">
            <div class="section-title">I. REQUESTER INFORMATION</div>
            <div class="section-content">
                <div class="row">
                    <span class="label">Name:</span>
                    <span class="value"><?= e($request->requester_name) ?></span>
                </div>
                <div class="row">
                    <span class="label">Department:</span>
                    <span class="value"><?= e($request->department_name) ?></span>
                </div>
                <div class="row">
                    <span class="label">Contact No.:</span>
                    <span class="value"><?= e($request->requester_phone ?: 'N/A') ?></span>
                </div>
                <div class="row">
                    <span class="label">Email:</span>
                    <span class="value"><?= e($request->requester_email) ?></span>
                </div>
            </div>
        </div>

        <!-- Trip Details -->
        <div class="section">
            <div class="section-title">II. TRIP DETAILS</div>
            <div class="section-content">
                <div class="row">
                    <span class="label">Purpose:</span>
                    <span class="value"><?= e($request->purpose) ?></span>
                </div>
                <div class="row">
                    <span class="label">Destination:</span>
                    <span class="value"><?= e($request->destination) ?></span>
                </div>
                <div class="row">
                    <span class="label">Departure:</span>
                    <span class="value"><?= date('F j, Y - g:i A', strtotime($request->start_datetime)) ?></span>
                </div>
                <div class="row">
                    <span class="label">Return:</span>
                    <span class="value"><?= date('F j, Y - g:i A', strtotime($request->end_datetime)) ?></span>
                </div>
                <div class="row">
                    <span class="label">No. of Passengers:</span>
                    <span class="value"><?= $request->passenger_count ?></span>
                </div>
                <div class="row">
                    <span class="label">Passengers:</span>
                    <span class="value">
                        <?= e($request->requester_name) ?> (Requester)<?php if (!empty($passengers)): ?>,
                            <?= implode(', ', array_map(function ($p) {
                                return e($p->name ?: $p->guest_name); }, $passengers)) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($request->notes): ?>
                    <div class="row">
                        <span class="label">Remarks:</span>
                        <span class="value"><?= e($request->notes) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vehicle Assignment (if approved) -->
        <?php if ($request->vehicle_id): ?>
            <div class="section">
                <div class="section-title">III. VEHICLE & DRIVER ASSIGNMENT</div>
                <div class="section-content">
                    <div class="row">
                        <span class="label">Vehicle:</span>
                        <span class="value"><?= e($request->plate_number) ?> -
                            <?= e($request->make . ' ' . $request->vehicle_model) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Vehicle Type:</span>
                        <span class="value"><?= e($request->vehicle_type) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Driver:</span>
                        <span class="value"><?= e($request->driver_name) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Driver Contact:</span>
                        <span class="value"><?= e($request->driver_phone ?: 'N/A') ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Approval Section -->
        <div class="section">
            <div class="section-title"><?= $request->vehicle_id ? 'IV' : 'III' ?>. APPROVAL</div>
            <div class="section-content">
                <div class="col-2">
                    <!-- Department Approver -->
                    <div class="approval-box">
                        <h3>DEPARTMENT APPROVER</h3>

                        <?php if ($deptApproval): ?>
                            <div class="approval-status status-<?= $deptApproval->status ?>">
                                <?= strtoupper($deptApproval->status) ?>
                            </div>

                            <div class="checkbox-row">
                                <div class="checkbox-item">
                                    <span
                                        class="checkbox <?= $deptApproval->status === 'approved' ? 'checked' : '' ?>"></span>
                                    <span>Approved</span>
                                </div>
                                <div class="checkbox-item">
                                    <span
                                        class="checkbox <?= $deptApproval->status === 'rejected' ? 'checked' : '' ?>"></span>
                                    <span>Disapproved</span>
                                </div>
                            </div>

                            <?php if ($deptApproval->comments): ?>
                                <div style="font-size: 10pt; margin-bottom: 10px;">
                                    <em>Comments: <?= e($deptApproval->comments) ?></em>
                                </div>
                            <?php endif; ?>

                            <div class="signature-line">
                                <div class="line"></div>
                                <div class="name"><?= e($deptApproval->approver_name) ?></div>
                                <div class="title">Department Approver</div>
                                <div class="date">Date: <?= date('F j, Y', strtotime($deptApproval->created_at)) ?></div>
                            </div>

                        <?php else: ?>
                            <div class="approval-status status-pending">PENDING</div>

                            <div class="checkbox-row">
                                <div class="checkbox-item">
                                    <span class="checkbox"></span>
                                    <span>Approved</span>
                                </div>
                                <div class="checkbox-item">
                                    <span class="checkbox"></span>
                                    <span>Disapproved</span>
                                </div>
                            </div>

                            <div class="signature-line">
                                <div class="line"></div>
                                <div class="name">_______________________</div>
                                <div class="title">Department Approver</div>
                                <div class="date">Date: _______________</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Motorpool Head -->
                    <div class="approval-box">
                        <h3>MOTORPOOL HEAD</h3>

                        <?php if ($motorpoolApproval): ?>
                            <div class="approval-status status-<?= $motorpoolApproval->status ?>">
                                <?= strtoupper($motorpoolApproval->status) ?>
                            </div>

                            <div class="checkbox-row">
                                <div class="checkbox-item">
                                    <span
                                        class="checkbox <?= $motorpoolApproval->status === 'approved' ? 'checked' : '' ?>"></span>
                                    <span>Approved</span>
                                </div>
                                <div class="checkbox-item">
                                    <span
                                        class="checkbox <?= $motorpoolApproval->status === 'rejected' ? 'checked' : '' ?>"></span>
                                    <span>Disapproved</span>
                                </div>
                            </div>

                            <?php if ($motorpoolApproval->comments): ?>
                                <div style="font-size: 10pt; margin-bottom: 10px;">
                                    <em>Comments: <?= e($motorpoolApproval->comments) ?></em>
                                </div>
                            <?php endif; ?>

                            <div class="signature-line">
                                <div class="line"></div>
                                <div class="name"><?= e($motorpoolApproval->approver_name) ?></div>
                                <div class="title">Motorpool Head</div>
                                <div class="date">Date: <?= date('F j, Y', strtotime($motorpoolApproval->created_at)) ?>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="approval-status status-pending">
                                <?= $request->status === STATUS_PENDING ? 'AWAITING DEPT. APPROVAL' : 'PENDING' ?>
                            </div>

                            <div class="checkbox-row">
                                <div class="checkbox-item">
                                    <span class="checkbox"></span>
                                    <span>Approved</span>
                                </div>
                                <div class="checkbox-item">
                                    <span class="checkbox"></span>
                                    <span>Disapproved</span>
                                </div>
                            </div>

                            <div class="signature-line">
                                <div class="line"></div>
                                <div class="name">_______________________</div>
                                <div class="title">Motorpool Head</div>
                                <div class="date">Date: _______________</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requester Signature -->
        <div class="section">
            <div class="section-title"><?= $request->vehicle_id ? 'V' : 'IV' ?>. REQUESTER'S CERTIFICATION</div>
            <div class="section-content">
                <p style="margin-bottom: 15px;">I hereby certify that the above information is true and correct, and
                    that this vehicle request is for official business purposes only.</p>
                <div style="width: 300px;">
                    <div class="signature-line">
                        <div class="line"></div>
                        <div class="name"><?= e($request->requester_name) ?></div>
                        <div class="title">Requester</div>
                        <div class="date">Date: <?= date('F j, Y', strtotime($request->created_at)) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Generated from LOKA Fleet Management System on <?= date('F j, Y g:i A') ?></p>
            <p>Document Reference: VRF-<?= date('Y') ?>-<?= str_pad($requestId, 5, '0', STR_PAD_LEFT) ?></p>
        </div>
    </div>
</body>

</html>