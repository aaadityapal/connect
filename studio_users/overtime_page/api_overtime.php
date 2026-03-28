<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$year = isset($_GET['year']) ? (int)$_GET['year'] : null;

try {
    $whereClause = "WHERE a.user_id = :user_id AND a.punch_out IS NOT NULL";
    $params = [':user_id' => $user_id];
    
    if ($month && $year) {
        $whereClause .= " AND MONTH(a.date) = :month AND YEAR(a.date) = :year";
        $params[':month'] = $month;
        $params[':year'] = $year;
    }

    // Joining attendance with user_shifts and shifts
    $query = "
        SELECT 
            a.id,
            a.date as submission_date,
            s.end_time,
            a.punch_out as punch_out_time,
            a.work_report,
            a.overtime_reason as overtime_report,
            a.overtime_status as raw_status,
            a.overtime_hours as raw_overtime_hours,
            COALESCE(oreq.overtime_hours, TIME_TO_SEC(a.overtime_hours) / 3600) as accepted_ot_decimal,
            a.overtime_manager_id,
            a.overtime_approved_by,
            a.overtime_actioned_at,
            (DATEDIFF(CURRENT_DATE, a.date) > 15) as db_expired,
            COALESCE(NULLIF(oreq.manager_comments, ''), NULLIF(a.manager_comments, ''), '') as reason,
            COALESCE(oreq.resubmit_count, 0) as resubmit_count
        FROM attendance a
        LEFT JOIN overtime_requests oreq 
               ON (a.id = oreq.attendance_id OR (a.date = oreq.date AND a.user_id = oreq.user_id))
        LEFT JOIN user_shifts us 
               ON a.user_id = us.user_id 
              AND a.date >= us.effective_from 
              AND (us.effective_to IS NULL OR a.date <= us.effective_to)
        LEFT JOIN shifts s ON us.shift_id = s.id
        $whereClause
        ORDER BY a.date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($records as $row) {
        if (empty($row['end_time']) || empty($row['punch_out_time'])) {
            continue;
        }

        $shiftEnd = strtotime($row['end_time']);
        $punchOut = strtotime($row['punch_out_time']);
        
        // Check if punched out after shift ended
        if ($punchOut > $shiftEnd) {
            $diffMins = floor(($punchOut - $shiftEnd) / 60);
            
            // Overtime countable only after 1 hour and 30 minutes (90 mins).
            // Example:
            // 90 mins (1h 30m) = 1.5 hr OT
            // 119 mins = 1.5 hr OT
            // 120 mins (2h) = 2.0 hr OT
            if ($diffMins >= 90) {
                // Determine the chunks of 30 minutes: 90 / 30 = 3; 3 * 0.5 = 1.5 hrs
                $calculatedOt = floor($diffMins / 30) * 0.5;
                
                $acceptedOtDec = floatval($row['accepted_ot_decimal'] ?? 0);

                $isExpired = false;
                $rawStatus = isset($row['raw_status']) ? trim(strtolower($row['raw_status'])) : 'pending';
                
                // If it's more than 15 days old and not strictly finalized (approved/rejected)
                if ((int)$row['db_expired'] === 1) {
                    if ($rawStatus !== 'approved' && $rawStatus !== 'rejected') {
                        $isExpired = true;
                    }
                }

                $results[] = [
                    'id' => $row['id'],
                    'submission_date' => $row['submission_date'],
                    'end_time' => $row['end_time'],
                    'punch_out_time' => $row['punch_out_time'],
                    'calculated_ot' => number_format($calculatedOt, 1),
                    'accepted_ot' => $acceptedOtDec !== null ? number_format($acceptedOtDec, 1) : null,
                    'work_report' => $row['work_report'] ?: 'No Report',
                    'overtime_report' => $row['overtime_report'] ?: 'No Reason Provided',
                    'status' => $isExpired ? 'expired' : ($rawStatus ?: 'pending'),
                    'is_expired' => $isExpired,
                    'overtime_manager_id' => $row['overtime_manager_id'],
                    'overtime_approved_by' => $row['overtime_approved_by'],
                    'overtime_actioned_at' => $row['overtime_actioned_at'],
                    'debug_days_old' => $row['db_expired'],
                    'rejection_reason' => $row['reason'] ?: '',
                    'resubmit_count' => (int)$row['resubmit_count']
                ];
            }
        }
    }

    // Fetch list of all potential managers (for the dropdown)
    $mgrStmt = $pdo->query("SELECT id, username as name FROM users WHERE deleted_at IS NULL AND (position LIKE '%Manager%' OR role = 'manager' OR role = 'admin') ORDER BY username ASC");
    $allManagers = $mgrStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the specific manager mapped to THIS user for overtime
    $mapStmt = $pdo->prepare("SELECT manager_id FROM overtime_approval_mapping WHERE employee_id = :uid LIMIT 1");
    $mapStmt->execute([':uid' => $user_id]);
    $userMap = $mapStmt->fetch(PDO::FETCH_ASSOC);
    $mappedManagerId = $userMap ? (int)$userMap['manager_id'] : null;

    echo json_encode([
        'status' => 'success', 
        'data' => $results,
        'managers' => $allManagers,
        'assigned_manager_id' => $mappedManagerId
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
