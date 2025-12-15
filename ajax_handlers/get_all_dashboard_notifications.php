<?php
/**
 * Get all dashboard notifications (Missing Punches + Assigned Tasks)
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = $pdo; // Use PDO connection

// --- Helper Functions for Attendance (Copied from get_missing_punches.php) ---

if (!function_exists('getUserWeeklyOffs')) {
    function getUserWeeklyOffs($conn, $user_id, $date)
    {
        try {
            $query = "
                SELECT us.weekly_offs 
                FROM user_shifts us
                JOIN shifts s ON us.shift_id = s.id
                WHERE us.user_id = ? 
                AND us.effective_from <= ?
                AND (us.effective_to IS NULL OR us.effective_to >= ?)
                ORDER BY us.effective_from DESC 
                LIMIT 1
            ";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id, $date, $date]);
            $result = $stmt->fetch();

            if ($result && !empty($result['weekly_offs'])) {
                return explode(',', $result['weekly_offs']);
            }
            return ['Saturday', 'Sunday'];
        } catch (Exception $e) {
            return ['Saturday', 'Sunday'];
        }
    }
}

if (!function_exists('isWeeklyOffDay')) {
    function isWeeklyOffDay($conn, $user_id, $date)
    {
        $weeklyOffs = getUserWeeklyOffs($conn, $user_id, $date);
        $dayOfWeek = date('l', strtotime($date));
        return in_array($dayOfWeek, $weeklyOffs);
    }
}

if (!function_exists('isOfficeHoliday')) {
    function isOfficeHoliday($conn, $date)
    {
        try {
            $tableCheckQuery = "SHOW TABLES LIKE 'office_holidays'";
            $tableCheckStmt = $conn->prepare($tableCheckQuery);
            $tableCheckStmt->execute();
            if ($tableCheckStmt->rowCount() == 0)
                return false;

            $query = "SELECT COUNT(*) as is_holiday FROM office_holidays WHERE holiday_date = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$date]);
            $result = $stmt->fetch();
            return $result && $result['is_holiday'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('isLeaveDay')) {
    function isLeaveDay($conn, $user_id, $date)
    {
        try {
            $tableCheckQuery = "SHOW TABLES LIKE 'leave_request'";
            $tableCheckStmt = $conn->prepare($tableCheckQuery);
            $tableCheckStmt->execute();
            if ($tableCheckStmt->rowCount() == 0)
                return false;

            $query = "
                SELECT COUNT(*) as is_leave 
                FROM leave_request 
                WHERE user_id = ? 
                AND status = 'approved' 
                AND ? BETWEEN start_date AND end_date
            ";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id, $date]);
            $result = $stmt->fetch();
            return $result && $result['is_leave'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('shouldExcludeDate')) {
    function shouldExcludeDate($conn, $user_id, $date)
    {
        return isWeeklyOffDay($conn, $user_id, $date) ||
            isOfficeHoliday($conn, $date) ||
            isLeaveDay($conn, $user_id, $date);
    }
}

$all_notifications = [];

try {
    // ---------------------------------------------------------
    // 1. FETCH MISSING PUNCHES
    // ---------------------------------------------------------

    $date_15_days_ago = date('Y-m-d', strtotime('-15 days'));
    $today = date('Y-m-d');

    $dates = [];
    $current_date = new DateTime($date_15_days_ago);
    $end_date = new DateTime($today);

    while ($current_date <= $end_date) {
        $date_str = $current_date->format('Y-m-d');
        if (!shouldExcludeDate($conn, $user_id, $date_str)) {
            $dates[] = $date_str;
        }
        $current_date->modify('+1 day');
    }

    $query = "
        SELECT id, user_id, date, punch_in, punch_out, approval_status, created_at
        FROM attendance 
        WHERE user_id = ? AND date >= ?
        ORDER BY date DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $date_15_days_ago]);
    $attendance_records = $stmt->fetchAll();

    $attendance_map = [];
    foreach ($attendance_records as $record) {
        if (!shouldExcludeDate($conn, $user_id, $record['date'])) {
            $attendance_map[$record['date']] = $record;
        }
    }

    foreach ($dates as $date) {
        if (isset($attendance_map[$date])) {
            $record = $attendance_map[$date];
            if ($record['punch_in'] === null && $record['punch_out'] === null) {
                // Both missing
                $r1 = $record;
                $r1['type'] = 'punch_in';
                $all_notifications[] = $r1;
                $r2 = $record;
                $r2['type'] = 'punch_out';
                $all_notifications[] = $r2;
            } else if ($record['punch_in'] === null) {
                $record['type'] = 'punch_in';
                $all_notifications[] = $record;
            } else if ($record['punch_out'] === null) {
                $record['type'] = 'punch_out';
                $all_notifications[] = $record;
            }
        } else {
            // Completely missing
            $base = [
                'id' => 0,
                'user_id' => $user_id,
                'date' => $date,
                'punch_in' => null,
                'punch_out' => null,
                'approval_status' => null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $r1 = $base;
            $r1['type'] = 'punch_in';
            $all_notifications[] = $r1;
            $r2 = $base;
            $r2['type'] = 'punch_out';
            $all_notifications[] = $r2;
        }
    }

    // ---------------------------------------------------------
    // 2. FETCH ASSIGNED TASKS (Site Coordinator / Senior Manager)
    // ---------------------------------------------------------

    // Check if tables exist first to avoid errors
    $tablesExist = true;
    try {
        $check = $conn->query("SHOW TABLES LIKE 'construction_site_tasks'");
        if ($check->rowCount() == 0)
            $tablesExist = false;
    } catch (Exception $e) {
        $tablesExist = false;
    }

    if ($tablesExist) {
        $taskQuery = "
            SELECT 
                t.id, 
                t.title, 
                t.description,
                t.status,
                t.start_date, 
                t.end_date,
                t.created_at,
                p.title as project_name, 
                u.username as creator_name,
                u.role as creator_role
            FROM construction_site_tasks t
            JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.assigned_user_id = ?
            AND t.status NOT IN ('cancelled', 'completed')
            AND (u.role = 'Site Coordinator' OR u.role = 'Senior Manager (Site)')
            ORDER BY t.created_at DESC
        ";

        $taskStmt = $conn->prepare($taskQuery);
        $taskStmt->execute([$user_id]);
        $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $task) {
            $all_notifications[] = [
                'type' => 'task_assignment',
                'id' => $task['id'],
                'date' => date('Y-m-d', strtotime($task['created_at'])), // Use creation date for sorting
                'title' => $task['title'],
                'description' => $task['description'],
                'message' => "New task assigned by {$task['creator_name']} ({$task['creator_role']}) for <strong>{$task['project_name']}</strong>",
                'project_name' => $task['project_name'],
                'status' => $task['status'],
                'due_date' => $task['end_date'],
                'created_at' => $task['created_at']
            ];
        }
    }

    // ---------------------------------------------------------
    // 3. SORT & RETURN
    // ---------------------------------------------------------

    // Sort by date/created_at descending
    usort($all_notifications, function ($a, $b) {
        $dateA = isset($a['created_at']) ? strtotime($a['created_at']) : strtotime($a['date']);
        $dateB = isset($b['created_at']) ? strtotime($b['created_at']) : strtotime($b['date']);
        return $dateB - $dateA;
    });

    echo json_encode([
        'success' => true,
        'data' => $all_notifications,
        'count' => count($all_notifications)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>