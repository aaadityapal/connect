<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$manager_id = $_SESSION['user_id'];
$user_role  = $_SESSION['role'] ?? 'user'; // Assuming 'role' is stored in session

try {
    // 1. Fetch leave requests
    // If user is Admin, show all. If Manager, show only mapped employees
    if ($user_role === 'admin') {
        $query = "SELECT lr.*, lt.name as leave_type_name, u.username as employee_name, u.employee_id as emp_code, u.position as user_role,
                         la.file_path, la.file_name, la.file_type
                  FROM leave_request lr
                  JOIN leave_types lt ON lr.leave_type = lt.id
                  JOIN users u ON lr.user_id = u.id
                  LEFT JOIN leave_attachments la ON lr.id = la.leave_request_id
                  ORDER BY lr.created_at DESC, lr.start_date ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } else {
        $query = "SELECT lr.*, lt.name as leave_type_name, u.username as employee_name, u.employee_id as emp_code, u.position as user_role,
                         la.file_path, la.file_name, la.file_type
                  FROM leave_request lr
                  JOIN leave_types lt ON lr.leave_type = lt.id
                  JOIN users u ON lr.user_id = u.id
                  JOIN leave_approval_mapping lam ON lr.user_id = lam.employee_id
                  LEFT JOIN leave_attachments la ON lr.id = la.leave_request_id
                  WHERE lam.manager_id = ?
                  ORDER BY lr.created_at DESC, lr.start_date ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$manager_id]);
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $row) {
        // Create a unique key for grouping multi-day requests
        $key = $row['user_id'] . '_' . $row['created_at'] . '_' . substr($row['reason'], 0, 50);
        
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'id' => $row['id'],
                'user_role' => $row['user_role'] ?: 'Member',
                'employee' => $row['employee_name'],
                'emp_id' => $row['emp_code'] ?? ('EMP-' . $row['user_id']),
                'type' => $row['leave_type_name'],
                'reason' => $row['reason'],
                'status' => $row['manager_approval'] ? ucfirst($row['manager_approval']) : 'Pending',
                'hr_status' => ucfirst($row['status']),
                'raw_dates' => [],
                'days' => 0,
                'hours' => 0,
                'is_hour_based' => false,
                'shift' => '',
                'created_at' => $row['created_at'],
                'attachments' => []
            ];
        }
        
        if (!in_array($row['start_date'], $grouped[$key]['raw_dates'])) {
            $grouped[$key]['raw_dates'][] = $row['start_date'];
            
            // Check if it's hour-based (Short Leave or Time fields present)
            $isShort = stripos($row['leave_type_name'], 'Short') !== false;
            $isHalf  = stripos($row['leave_type_name'], 'Half') !== false;

            if ($row['time_from'] && $row['time_to']) {
                $t1 = new DateTime($row['time_from']);
                $t2 = new DateTime($row['time_to']);
                $diff = $t1->diff($t2);
                $hours = $diff->h + ($diff->i / 60);
                $grouped[$key]['hours'] += $hours;
                $grouped[$key]['is_hour_based'] = true;
            } elseif ($isHalf) {
                // If duration is 0.5, show as 4 hours (assumption)
                $grouped[$key]['hours'] += 4;
                $grouped[$key]['is_hour_based'] = true;
            } else {
                $grouped[$key]['days'] += (float)$row['duration'];
            }

            // Determine Shift Label (First Half, Second Half, Morning, Evening)
            $shift = '';
            if ($row['day_type'] && $row['day_type'] !== 'full') {
                $shift = ($row['day_type'] === 'first_half') ? 'First Half' : 'Second Half';
            } elseif ($row['duration_type'] && $row['duration_type'] !== 'full') {
                $shift = ($row['duration_type'] === 'first_half') ? 'First Half' : 'Second Half';
            } elseif ($row['time_from']) {
                $hour = (int)explode(':', $row['time_from'])[0];
                $shift = ($hour < 12) ? 'Morning' : 'Evening';
            }
            if ($shift) $grouped[$key]['shift'] = $shift;
        }

        if ($row['file_path']) {
            $found = false;
            foreach ($grouped[$key]['attachments'] as $att) {
                if ($att['path'] === $row['file_path']) { $found = true; break; }
            }
            if (!$found) {
                $grouped[$key]['attachments'][] = [
                    'path' => $row['file_path'],
                    'name' => $row['file_name'],
                    'type' => $row['file_type']
                ];
            }
        }
    }

    $final = [];
    foreach ($grouped as $g) {
        sort($g['raw_dates']);
        $dateRange = $g['raw_dates'][0];
        if (count($g['raw_dates']) > 1) {
            $dateRange .= ' to ' . end($g['raw_dates']);
        }

        $final[] = [
            'id' => $g['id'],
            'employee' => $g['employee'],
            'user_role' => $g['user_role'],
            'emp_id' => $g['emp_id'],
            'type' => $g['type'],
            'dates' => $dateRange,
            'days' => $g['days'],
            'hours' => $g['hours'],
            'is_hour_based' => $g['is_hour_based'],
            'shift' => $g['shift'],
            'duration_label' => ($g['is_hour_based'] ? (round($g['hours'], 1) . ' Hour(s)') : ($g['days'] . ' Day(s)')) . ($g['shift'] ? " ({$g['shift']})" : ""),
            'manager_status' => $g['status'], // Map internal 'status' (manager_approval) to manager_status
            'hr_status' => $g['hr_status'],
            'reason' => $g['reason'],
            'attachments' => $g['attachments']
        ];
    }

    echo json_encode(['success' => true, 'data' => $final]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
