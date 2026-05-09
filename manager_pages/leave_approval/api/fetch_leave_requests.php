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
    // If user is Admin or HR, show all. If Manager, show only mapped employees
    if (in_array(strtolower($user_role), ['admin', 'hr'])) {
        $query = "SELECT lr.*, lt.name as leave_type_name, u.username as employee_name, u.employee_id as emp_code, u.role as user_role,
                         la.file_path, la.file_name, la.file_type
                  FROM leave_request lr
                  JOIN leave_types lt ON lr.leave_type = lt.id
                  JOIN users u ON lr.user_id = u.id
                  LEFT JOIN leave_attachments la ON lr.id = la.leave_request_id
                  ORDER BY lr.created_at DESC, lr.start_date ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } else {
        $query = "SELECT lr.*, lt.name as leave_type_name, u.username as employee_name, u.employee_id as emp_code, u.role as user_role,
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

    $final = [];
    foreach ($rows as $row) {
        $isHalf = stripos($row['leave_type_name'], 'Half') !== false;
        $hours = 0;
        $days = 0;
        $isHourBased = false;

        if ($row['time_from'] && $row['time_to']) {
            $t1 = new DateTime($row['time_from']);
            $t2 = new DateTime($row['time_to']);
            $diff = $t1->diff($t2);
            $hours = $diff->h + ($diff->i / 60);
            $isHourBased = true;
        } elseif ($isHalf) {
            $hours = 4;
            $isHourBased = true;
        } else {
            $days = (float)$row['duration'];
        }

        $shift = '';
        if ($row['day_type'] && $row['day_type'] !== 'full') {
            $shift = ($row['day_type'] === 'first_half') ? 'First Half' : 'Second Half';
        } elseif ($row['duration_type'] && $row['duration_type'] !== 'full') {
            $shift = ($row['duration_type'] === 'first_half') ? 'First Half' : 'Second Half';
        } elseif ($row['time_from']) {
            $hour = (int)explode(':', $row['time_from'])[0];
            $shift = ($hour < 12) ? 'Morning' : 'Evening';
        }

        $dateRange = $row['start_date'];
        if (!empty($row['end_date']) && $row['end_date'] !== $row['start_date']) {
            $dateRange = $row['start_date'] . ' to ' . $row['end_date'];
        }

        $attachments = [];
        if ($row['file_path']) {
            $attachments[] = [
                'path' => $row['file_path'],
                'name' => $row['file_name'],
                'type' => $row['file_type']
            ];
        }

        $final[] = [
            'id' => $row['id'],
            'employee' => $row['employee_name'],
            'user_role' => $row['user_role'] ?: 'Member',
            'emp_id' => $row['emp_code'] ?? ('EMP-' . $row['user_id']),
            'type' => $row['leave_type_name'],
            'dates' => $dateRange,
            'days' => $days,
            'hours' => $hours,
            'is_hour_based' => $isHourBased,
            'shift' => $shift,
            'duration_label' => ($isHourBased ? (round($hours, 1) . ' Hour(s)') : ($days . ' Day(s)')) . ($shift ? " ({$shift})" : ""),
            'manager_status' => $row['manager_approval'] ? ucfirst($row['manager_approval']) : 'Pending',
            'hr_status' => ucfirst($row['status']),
            'manager_reason' => $row['manager_action_reason'],
            'manager_at' => $row['manager_action_at'],
            'created_at' => $row['created_at'],
            'reason' => $row['reason'],
            'attachments' => $attachments
        ];
    }

    echo json_encode(['success' => true, 'data' => $final]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
