<?php
/**
 * API to fetch overtime approval data from database using original dashboard tables (attendance, users, overtime_requests)
 */
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$manager_id = $_SESSION['user_id'];
$manager_role = strtolower($_SESSION['role'] ?? '');

$isAdminOrHr = ($manager_role === 'admin' || $manager_role === 'hr');

try {
    // Check if the current user has permission to action unsubmitted or expired overtime
    $current_user_id = $_SESSION['user_id'];
    $user_role = strtolower($_SESSION['role'] ?? '');
    
    // Admin always has all permissions
    $has_unsubmitted_perm = ($user_role === 'admin');
    $has_expired_perm     = ($user_role === 'admin');

    if ($user_role !== 'admin') {
        $stmt_p = $pdo->prepare("SELECT can_action_unsubmitted, can_action_expired, can_action_completed FROM ot_unsubmitted_action_perms WHERE user_id = ?");
        $stmt_p->execute([$current_user_id]);
        $perms = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if ($perms) {
            $has_unsubmitted_perm = (bool)$perms['can_action_unsubmitted'];
            $has_expired_perm     = (bool)$perms['can_action_expired'];
            $has_modify_completed = (bool)$perms['can_action_completed'];
        }
    }

    $params = [':m_id1' => $manager_id];
    $where_conditions = ["u.id != :m_id1"];
    
    // If not Admin or HR, filter by mapping
    if (!$isAdminOrHr) {
        $where_conditions[] = "u.id IN (SELECT employee_id FROM overtime_approval_mapping WHERE manager_id = :m_id2)";
        $params[':m_id2'] = $manager_id;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Optimized query to get all necessary data
    $query = "
        SELECT 
            a.id as attendance_id,
            u.username as employee,
            u.employee_id as employeeCode,
            u.id as employeeId,
            u.unique_id as uniqueId,
            u.role as role,
            a.date as date,
            a.punch_in as punchIn,
            a.punch_out as punchOut,
            a.overtime_hours as attendance_ot,
            a.work_report as workReport,
            a.overtime_status as attendance_status,
            a.overtime_reason as attendance_reason,
            s.start_time as startTime,
            s.end_time as endTime,
            s.shift_name as shift,
            CASE 
                WHEN a.punch_out IS NULL OR s.end_time IS NULL THEN 0
                WHEN TIME(a.punch_out) <= TIME(s.end_time) THEN 0
                ELSE TIMESTAMPDIFF(SECOND, 
                    STR_TO_DATE(CONCAT(a.date, ' ', s.end_time), '%Y-%m-%d %H:%i:%s'),
                    STR_TO_DATE(CONCAT(a.date, ' ', a.punch_out), '%Y-%m-%d %H:%i:%s')
                )
            END as overtime_seconds,
            oreq.overtime_description as otReport,
            oreq.overtime_hours as submitted_ot_hours,
            oreq.status as oreq_status,
            oreq.submitted_at as submittedAt,
            oreq.actioned_at as actionedAt,
            oreq.updated_at as updatedAt,
            oreq.id as request_id,
            oreq.manager_comments as managerComment,
            a.punch_out_address,
            a.punch_out_latitude,
            a.punch_out_longitude
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN user_shifts us ON u.id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
        LEFT JOIN shifts s ON us.shift_id = s.id
        LEFT JOIN overtime_requests oreq ON a.id = oreq.attendance_id
        $where_clause
        HAVING (overtime_seconds >= 5400 OR (submitted_ot_hours IS NOT NULL AND submitted_ot_hours > 0))
        ORDER BY a.date DESC, a.id DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedData = array_map(function ($row) {
        $daysDiff = (time() - strtotime($row['date'])) / 86400; // 86400 seconds in a day
        $attendanceStatus = strtolower(trim((string)($row['attendance_status'] ?? '')));
        $isSubmitted = !empty($row['submittedAt']);
        
        // Resolve status
        $status = 'Pending';
        if (!empty($row['oreq_status'])) {
            $status = ucfirst($row['oreq_status']);
        } else if ($isSubmitted) {
            // Mark as submitted only when the employee actually submitted
            $status = 'Submitted';
        } else if (in_array($attendanceStatus, ['approved', 'rejected', 'paid'], true)) {
            // Keep terminal attendance states visible for older records without overtime_requests rows
            $status = ucfirst($attendanceStatus);
        }

        // Expiration Rule: If > 15 days old and not yet submitted by the user, it is Expired.
        // Submitted requests stay actionable (approve/reject) and must not expire.
        if ($daysDiff > 15 && strtolower($status) === 'pending' && !$isSubmitted) {
            $status = 'Expired';
        }

        // Calculate System Calculated OT hours 
        $overtime_minutes = $row['overtime_seconds'] / 60;
        $systemOtHours = 0;
        if ($overtime_minutes > 0) {
            if ($overtime_minutes < 90) {
                $systemOtHours = 1.5;
            } else {
                $systemOtHours = ($overtime_minutes - 90) / 60;
                $systemOtHours = floor($systemOtHours * 2) / 2 + 1.5;
            }
        }

        // Use submitted OT if available, otherwise use system calculated
        $submittedOt = $row['submitted_ot_hours'] ?? $systemOtHours;

        return [
            'employee' => $row['employee'],
            'employeeId' => $row['uniqueId'] ?: $row['employeeCode'] ?: $row['employeeId'],
            'db_user_id' => $row['employeeId'],
            'date' => $row['date'],
            'endTime' => $row['endTime'] ? date('g:i A', strtotime($row['endTime'])) : '—',
            'punchOut' => $row['punchOut'] ? date('g:i A', strtotime($row['punchOut'])) : '—',
            'otHours' => number_format($systemOtHours, 1),
            'submittedOt' => number_format(floatval($submittedOt), 1),
            'workReport' => $row['workReport'] ?: 'No work report submitted',
            'otReport' => $row['otReport'] ?: $row['attendance_reason'] ?: 'No description provided',
            'status' => $status,
            'role' => $row['role'],
            'shift' => $row['shift'] ?: 'Standard Shift',
            'startTime' => $row['startTime'] ? date('g:i A', strtotime($row['startTime'])) : '—',
            'punchIn' => $row['punchIn'] ? date('g:i A', strtotime($row['punchIn'])) : '—',
            'submittedAt' => !empty($row['submittedAt']) ? date('M j, Y g:i A', strtotime($row['submittedAt'])) : '—',
            'actionedAt' => !empty($row['actionedAt']) ? date('M j, Y g:i A', strtotime($row['actionedAt'])) : '—',
            'updatedAt' => !empty($row['updatedAt']) ? date('M j, Y g:i A', strtotime($row['updatedAt'])) : '—',
            'otDescription' => $row['otReport'] ?: $row['attendance_reason'] ?: '—',
            'otReason' => $row['attendance_reason'] ?: '—',
            'managerComment' => $row['managerComment'] ?: '',
            'attendance_id' => $row['attendance_id'],
            'request_id' => $row['request_id'],
            'isSubmitted' => $isSubmitted,
            'punchOutAddress' => $row['punch_out_address'] ?: '—',
            'punchOutLat' => $row['punch_out_latitude'],
            'punchOutLng' => $row['punch_out_longitude']
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'hasUnsubmittedPerm' => $has_unsubmitted_perm,
        'hasExpiredPerm' => $has_expired_perm,
        'hasModifyCompletedPerm' => $has_modify_completed ?? ($user_role === 'admin'),
        'data' => $formattedData
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

