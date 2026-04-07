<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

try {
    $fromDate = $_GET['from'] ?? date('Y-m-d');
    $toDate = $_GET['to'] ?? date('Y-m-d');
    $filterUserId = $_GET['user_id'] ?? '';
    $actorId = (int)($_SESSION['user_id'] ?? 0);

    $currentUserPermissions = [
        'can_approve_attendance' => 0,
        'can_reject_attendance' => 0,
        'can_edit_attendance' => 0
    ];

    if ($actorId > 0) {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'attendance_action_permissions'");
        if ($tableCheck && $tableCheck->fetchColumn()) {
            $stmtPerm = $pdo->prepare("SELECT can_approve_attendance, can_reject_attendance, can_edit_attendance FROM attendance_action_permissions WHERE user_id = ? LIMIT 1");
            $stmtPerm->execute([$actorId]);
            $permRow = $stmtPerm->fetch(PDO::FETCH_ASSOC);
            if ($permRow) {
                $currentUserPermissions['can_approve_attendance'] = ((int)($permRow['can_approve_attendance'] ?? 0)) === 1 ? 1 : 0;
                $currentUserPermissions['can_reject_attendance'] = ((int)($permRow['can_reject_attendance'] ?? 0)) === 1 ? 1 : 0;
                $currentUserPermissions['can_edit_attendance'] = ((int)($permRow['can_edit_attendance'] ?? 0)) === 1 ? 1 : 0;
            }
        }
    }
    
    // Fetch active users based on user filter
    if (!empty($filterUserId)) {
        $stmtUsers = $pdo->prepare("
            SELECT u.id, u.unique_id, u.username, u.email, u.role, s.shift_name, s.start_time, s.end_time 
            FROM users u 
            LEFT JOIN (
                SELECT user_id, shift_id 
                FROM user_shifts 
                WHERE id IN (SELECT MAX(id) FROM user_shifts GROUP BY user_id)
            ) us ON u.id = us.user_id
            LEFT JOIN shifts s ON us.shift_id = s.id
            WHERE u.status = 'active' AND u.id = ? ORDER BY u.username ASC
        ");
        $stmtUsers->execute([$filterUserId]);
    } else {
        $stmtUsers = $pdo->prepare("
            SELECT u.id, u.unique_id, u.username, u.email, u.role, s.shift_name, s.start_time, s.end_time 
            FROM users u 
            LEFT JOIN (
                SELECT user_id, shift_id 
                FROM user_shifts 
                WHERE id IN (SELECT MAX(id) FROM user_shifts GROUP BY user_id)
            ) us ON u.id = us.user_id
            LEFT JOIN shifts s ON us.shift_id = s.id
            WHERE u.status = 'active' ORDER BY u.username ASC
        ");
        $stmtUsers->execute();
    }
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Date range iterators
    $begin = new DateTime($fromDate);
    $end = new DateTime($toDate);
    $end->modify('+1 day'); // include the last day
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($begin, $interval, $end);

    // Fetch attendance for the specific range
    $stmtAtt = $pdo->prepare("SELECT id, user_id, date, punch_in, punch_out, status, approval_status, address, punch_out_address, punch_in_photo, punch_out_photo, latitude, longitude, work_report, punch_in_outside_reason, punch_out_outside_reason FROM attendance WHERE date >= ? AND date <= ?");
    $stmtAtt->execute([$fromDate, $toDate]);
    $attendanceData = [];
    while($row = $stmtAtt->fetch(PDO::FETCH_ASSOC)) {
        $attendanceData[$row['user_id']][$row['date']] = $row;
    }

    // Fetch approved leaves overlapping the date
    $stmtLeave = $pdo->prepare("SELECT user_id, start_date, end_date FROM leave_request WHERE start_date <= ? AND end_date >= ? AND LOWER(status) = 'approved'");
    $stmtLeave->execute([$toDate, $fromDate]);
    $leaveData = [];
    while($row = $stmtLeave->fetch(PDO::FETCH_ASSOC)) {
        $leaveData[] = $row;
    }

    $augmentedUsers = [];
    $stats = [
        'Total' => 0,
        'On Time' => 0,
        'Absent' => 0,
        'Late' => 0,
        'On Leave' => 0
    ];

    $currentTime = date('H:i:s');
    $currentDateStr = date('Y-m-d');

    foreach ($period as $dt) {
        $dateIter = $dt->format("Y-m-d");
        $displayDate = $dt->format("d M Y");
        
        foreach($users as $user) {
            $uid = $user['id'];
            $checkIn = '--:--';
            $checkOut = '--:--';
            $status = 'Absent';
            
            $userRow = $user;
            $userRow['attendance_date'] = $displayDate;
            $userRow['attendance_raw_date'] = $dateIter;
            $userRow['shift_name'] = $user['shift_name'] ?? 'Standard Shift';
            $userRow['shift_start'] = $user['start_time'] ? date('H:i', strtotime($user['start_time'])) : '10:00';
            $userRow['shift_end'] = $user['end_time'] ? date('H:i', strtotime($user['end_time'])) : '19:00';
            
            $stats['Total']++; 
            
            if (isset($attendanceData[$uid][$dateIter])) {
                $att = $attendanceData[$uid][$dateIter];
                $checkIn = $att['punch_in'] ? date('h:i A', strtotime($att['punch_in'])) : '--:--';
                $checkOut = $att['punch_out'] ? date('h:i A', strtotime($att['punch_out'])) : '--:--';
                
                $punchInTime = $att['punch_in'] ?: ($dateIter === $currentDateStr ? $currentTime : '00:00:00');
                
                $baseStart = $user['start_time'] ?: '10:00:00';
                // Apply 15 minute grace period offset globally
                $lateThreshold = date('H:i:s', strtotime('+15 minutes', strtotime($baseStart)));
                
                if ($punchInTime > $lateThreshold) {
                    $status = 'Late';
                    $stats['Late']++;
                } else {
                    $status = 'On Time';
                    $stats['On Time']++;
                }
                
                $userRow['punch_in_location'] = $att['address'] ?? '-';
                $userRow['punch_out_location'] = $att['punch_out_address'] ?? '-';
                $userRow['punch_in_photo'] = $att['punch_in_photo'] ?? null;
                $userRow['punch_out_photo'] = $att['punch_out_photo'] ?? null;
                $userRow['attendance_id'] = $att['id'] ?? null;
                $userRow['approval_status'] = $att['approval_status'] ?? 'pending';
                $userRow['latitude'] = $att['latitude'] ?? null;
                $userRow['longitude'] = $att['longitude'] ?? null;
                $userRow['work_report'] = $att['work_report'] ?? '-';
                $userRow['punch_in_outside_reason'] = $att['punch_in_outside_reason'] ?? null;
                $userRow['punch_out_outside_reason'] = $att['punch_out_outside_reason'] ?? null;
            } else {
                // Determine leave
                $onLeave = false;
                foreach($leaveData as $ld) {
                    if ($ld['user_id'] == $uid && $dateIter >= $ld['start_date'] && $dateIter <= $ld['end_date']) {
                        $onLeave = true; break;
                    }
                }
                
                if ($onLeave) {
                    $status = 'On Leave';
                    $stats['On Leave']++;
                } else {
                    if ($dateIter > $currentDateStr) {
                         $status = 'Upcoming';
                    } else {
                        $status = 'Absent';
                        $stats['Absent']++;
                    }
                }
                
                $userRow['punch_in_location'] = '-';
                $userRow['punch_out_location'] = '-';
                $userRow['punch_in_photo'] = null;
                $userRow['punch_out_photo'] = null;
                $userRow['attendance_id'] = null;
                $userRow['approval_status'] = 'pending';
                $userRow['latitude'] = null;
                $userRow['longitude'] = null;
                $userRow['work_report'] = '-';
                $userRow['punch_in_outside_reason'] = null;
                $userRow['punch_out_outside_reason'] = null;
            }
            
            $userRow['attendance_status'] = $status;
            $userRow['check_in'] = $checkIn;
            $userRow['check_out'] = $checkOut;
            $userRow['initial'] = strtoupper(substr($user['username'], 0, 1));
            
            if ($status !== 'Upcoming') {
                $augmentedUsers[] = $userRow;
            } else {
                $stats['Total']--; // Don't count upcoming days in total tracking metrics
            }
        }
    }
    
    // Sort logic to make the newest dates appear at the top, then by username
    usort($augmentedUsers, function($a, $b) {
        $dateDiff = strcmp($b['attendance_raw_date'], $a['attendance_raw_date']); // Descending by date
        if ($dateDiff === 0) {
            return strcmp($a['username'], $b['username']); // Ascending by user name
        }
        return $dateDiff;
    });

    echo json_encode([
        'success' => true, 
        'data' => $augmentedUsers,
        'stats' => $stats,
        'current_user_permissions' => $currentUserPermissions
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch attendance data: ' . $e->getMessage()
    ]);
}
