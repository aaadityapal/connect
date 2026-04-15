<?php
/**
 * API to update overtime approval status in database
 */
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['attendance_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing attendance_id or status']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Begin transaction
    $pdo->beginTransaction();

    $manager_id = $_SESSION['user_id'];
    $status = $data['status']; // 'Approved' or 'Rejected'
    $newStatus = strtolower($status);
    $comments = isset($data['comments']) ? $data['comments'] : NULL;
    $otHoursDecimal = isset($data['otHours']) ? floatval($data['otHours']) : NULL;
    
    // Convert decimal hours to HH:MM:SS for attendance table
    $otHoursTime = NULL;
    if ($otHoursDecimal !== NULL) {
        $hours = floor($otHoursDecimal);
        $minutes = round(($otHoursDecimal - $hours) * 60);
        $otHoursTime = sprintf('%02d:%02d:00', $hours, $minutes);
    }

    // 1. Update attendance table
    $query = "
        UPDATE attendance 
        SET 
            overtime_status = :status,
            overtime_approved_by = :approver_id,
            overtime_actioned_at = CURRENT_TIMESTAMP,
            manager_comments = :comments,
            overtime_hours = :ot_hours_time
        WHERE id = :id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':status' => $newStatus,
        ':approver_id' => $manager_id,
        ':comments' => $comments,
        ':ot_hours_time' => $otHoursTime,
        ':id' => $data['attendance_id']
    ]);

    // Default values for permission check
    $manager_role = strtolower($_SESSION['role'] ?? '');
    $canUnsub = ($manager_role === 'admin');
    $canExp   = ($manager_role === 'admin');

    if ($manager_role !== 'admin') {
        $stmt_p = $pdo->prepare("SELECT can_action_unsubmitted, can_action_expired, can_action_completed FROM ot_unsubmitted_action_perms WHERE user_id = ?");
        $stmt_p->execute([$manager_id]);
        $perms = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if ($perms) {
            $canUnsub   = (bool)$perms['can_action_unsubmitted'];
            $canExp     = (bool)$perms['can_action_expired'];
            $canComp = (bool)$perms['can_action_completed'];
        }
    } else {
        $canComp = true;
    }

    // Check if the record already exists in overtime_requests
    $stmt_check = $pdo->prepare("SELECT id, status, date, submitted_at FROM overtime_requests WHERE attendance_id = :aid");
    $stmt_check->execute([':aid' => $data['attendance_id']]);
    $oreq = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $oreq_id = $oreq['id'] ?? null;
    $oreq_status = $oreq['status'] ?? null;
    $oreq_submitted_at = $oreq['submitted_at'] ?? null;

    // Fetch attendance data for fallback status/date
    $stmt_att = $pdo->prepare("SELECT date, overtime_status FROM attendance WHERE id = ?");
    $stmt_att->execute([$data['attendance_id']]);
    $att_row = $stmt_att->fetch(PDO::FETCH_ASSOC);
    $att_date = $att_row['date'] ?? '';
    $att_status = $att_row['overtime_status'] ?? '';

    // Calculate effective status and date
    $currentEffectiveStatus = strtolower($oreq_status ?: $att_status);
    $effectiveDate = $oreq['date'] ?? $att_date;

    // Calculate Expiry
    $daysDiff = (time() - strtotime($effectiveDate)) / 86400;
    $isSubmitted = !empty($oreq_submitted_at);
    $isExpStatus = ($daysDiff > 15 && !$isSubmitted && !in_array($currentEffectiveStatus, ['approved', 'rejected', 'paid']));

    // 2. MODIFICATION CHECK (If it's already Approved/Rejected/Paid)
    $terminalStates = ['approved', 'rejected', 'paid'];
    $isTerminal = in_array($currentEffectiveStatus, $terminalStates);

    if ($isTerminal && !$canComp) {
        throw new Exception("Unauthorized: You do not have permission to modify a completed (Approved/Rejected) overtime request.");
    }

    // 3. UN-SUBMITTED / EXPIRED CHECK
    // A record is "Actioned" if it was submitted by employee or already terminal
    $isActioned = ($isSubmitted || $isTerminal);

    if ($isExpStatus) {
        if (!$canExp) {
            throw new Exception("Unauthorized: You do not have permission to action EXPIRED overtime.");
        }
    } else if (!$isActioned) {
        // Not expired, and has NEVER been actioned/submitted
        if (!$canUnsub) {
            throw new Exception("Unauthorized: You do not have permission to action overtime that has not been submitted by the employee.");
        }
    }

    if ($oreq_id) {
        // Case 1: Record exists, just UPDATE it
        $query_requests = "
            UPDATE overtime_requests 
            SET 
                status = :status,
                manager_id = :manager_id,
                manager_comments = :comments,
                overtime_hours = :ot_hours_decimal,
                actioned_at = CURRENT_TIMESTAMP
            WHERE attendance_id = :attendance_id
        ";
        $stmt_req = $pdo->prepare($query_requests);
        $stmt_req->execute([
            ':status' => $newStatus,
            ':manager_id' => $manager_id,
            ':comments' => $comments,
            ':ot_hours_decimal' => $otHoursDecimal,
            ':attendance_id' => $data['attendance_id']
        ]);
    } else {
        // Case 2: Record DOES NOT exist, fetch attendance details and INSERT
        $stmt_fetch = $pdo->prepare("
            SELECT a.user_id, a.date, a.punch_out, a.work_report, a.overtime_reason, s.end_time
            FROM attendance a
            LEFT JOIN user_shifts us ON a.user_id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
            LEFT JOIN shifts s ON us.shift_id = s.id
            WHERE a.id = :aid
        ");
        $stmt_fetch->execute([':aid' => $data['attendance_id']]);
        $row = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $query_insert = "
                INSERT INTO overtime_requests 
                (user_id, attendance_id, date, shift_end_time, punch_out_time, overtime_hours, work_report, overtime_description, manager_id, status, manager_comments, submitted_at, actioned_at)
                VALUES 
                (:uid, :aid, :date, :set, :pot, :oth, :wr, :odesc, :mid, :status, :mcomm, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ";
            $stmt_ins = $pdo->prepare($query_insert);
            $stmt_ins->execute([
                ':uid' => $row['user_id'],
                ':aid' => $data['attendance_id'],
                ':date' => $row['date'],
                ':set' => $row['end_time'],
                ':pot' => $row['punch_out'],
                ':oth' => $otHoursDecimal,
                ':wr' => $row['work_report'],
                ':odesc' => $row['overtime_reason'] ?: 'Manager generated request',
                ':mid' => $manager_id,
                ':status' => $newStatus,
                ':mcomm' => $comments
            ]);
        }
    }

    // --- 4. ACTIVITY LOGGING (HIGH DETAIL) ---
    // Fetch exhaustive details for the audit trail
    $stmt_audit = $pdo->prepare("
        SELECT 
            u.username as emp_name,
            m.username as mgr_name,
            a.punch_in, a.punch_out, a.work_report, a.overtime_reason as emp_ot_reason,
            s.end_time as shift_end_time,
            oreq.overtime_hours as original_submitted_hours
        FROM attendance a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN users m ON m.id = :mid
        LEFT JOIN user_shifts us ON a.user_id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
        LEFT JOIN shifts s ON us.shift_id = s.id
        LEFT JOIN overtime_requests oreq ON a.id = oreq.attendance_id
        WHERE a.id = :aid
    ");
    $stmt_audit->execute([':aid' => $data['attendance_id'], ':mid' => $manager_id]);
    $audit = $stmt_audit->fetch(PDO::FETCH_ASSOC);

    $emp_id = $audit['user_id_audit'] ?? null; // We need to add this to the audit query
    if (!$emp_id) {
        $stmt_find_emp = $pdo->prepare("SELECT user_id FROM attendance WHERE id = ?");
        $stmt_find_emp->execute([$data['attendance_id']]);
        $emp_id = $stmt_find_emp->fetchColumn();
    }

    $empName = $audit['emp_name'] ?? 'Employee';
    $mgrName = $audit['mgr_name'] ?? 'Manager';
    
    $actionType = ($newStatus === 'approved') ? 'overtime_approved' : 'overtime_rejected';
    $origH = floatval($audit['original_submitted_hours'] ?? 0);
    $finalH = floatval($otHoursDecimal);
    
    $hourText = ($origH != $finalH) ? " (Changed from {$origH}h to {$finalH}h)" : " ({$finalH}h)";
    $statusText = ($newStatus === 'approved') ? "approved" : "rejected";
    $reasonText = !empty($comments) ? " Reason: " . $comments : "";

    // 1. LOG FOR THE MANAGER
    if ($newStatus === 'approved') {
        $mgrLogDesc = "You approved the $empName overtime and set the time$hourText.";
    } else {
        $mgrLogDesc = "You rejected the $empName overtime of {$origH}h. Reason: " . ($comments ?: 'No reason provided') . ".";
    }
    
    // 2. LOG FOR THE EMPLOYEE
    if ($newStatus === 'approved') {
        $empLogDesc = "Your overtime for $effectiveDate was approved by $mgrName. Hours: $hourText.";
    } else {
        $empLogDesc = "Your overtime for $effectiveDate ({$origH}h) was rejected by $mgrName.$reasonText";
    }

    $metaArray = [
        'overtime_date'    => $effectiveDate,
        'status_transition' => ($currentEffectiveStatus ?: 'pending') . ' -> ' . $newStatus,
        'hours_approved'   => $finalH,
        'hours_submitted'  => $origH,
        'manager_comments' => $comments,
        'technical_details' => [
            'punch_in'       => $audit['punch_in'],
            'punch_out'      => $audit['punch_out'],
            'shift_end'      => $audit['shift_end_time'],
        ]
    ];
    $logMeta = json_encode($metaArray, JSON_UNESCAPED_UNICODE);

    // SQL Template
    $sqlLog = "INSERT INTO global_activity_logs 
               (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
               VALUES (:uid, :atype, 'overtime', :eid, :desc, :meta, NOW(), 0)";
    $stmt_log = $pdo->prepare($sqlLog);

    // Execute Manager Log
    $stmt_log->execute([
        ':uid'   => $manager_id,
        ':atype' => $actionType,
        ':eid'   => $data['attendance_id'],
        ':desc'  => $mgrLogDesc,
        ':meta'  => $logMeta
    ]);

    // Execute Employee Log
    if ($emp_id) {
        $stmt_log->execute([
            ':uid'   => $emp_id,
            ':atype' => $actionType,
            ':eid'   => $data['attendance_id'],
            ':desc'  => $empLogDesc,
            ':meta'  => $logMeta
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Overtime $newStatus successfully"
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

