<?php
require_once 'config/db_connect.php';

// Function to print results in a readable format
function printDebugInfo($title, $data) {
    echo "<h3>$title</h3>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    echo "<hr>";
}

// Test for specific user
$user_id = 21; // You can change this to test different users

// 1. Check User Shifts
$shifts_query = "
    SELECT 
        us.*,
        DAYNAME(CURRENT_DATE()) as current_day
    FROM user_shifts us 
    WHERE us.user_id = ?
    ORDER BY us.effective_from DESC";

$shifts_stmt = $conn->prepare($shifts_query);
$shifts_stmt->bind_param('i', $user_id);
$shifts_stmt->execute();
$shifts_result = $shifts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
printDebugInfo("User Shifts Configuration", $shifts_result);

// 2. Check Attendance Records
$attendance_query = "
    SELECT 
        a.date,
        DAYNAME(a.date) as day_name,
        a.status,
        us.weekly_offs,
        CASE 
            WHEN DAYNAME(a.date) = us.weekly_offs 
            AND a.status = 'present' 
            THEN 'YES' 
            ELSE 'NO' 
        END as is_weekly_off_worked
    FROM attendance a
    JOIN user_shifts us ON a.user_id = us.user_id
        AND a.date >= us.effective_from
        AND (us.effective_to IS NULL OR a.date <= us.effective_to)
    WHERE a.user_id = ?
    AND YEAR(a.date) = YEAR(CURRENT_DATE())
    ORDER BY a.date DESC";

$attendance_stmt = $conn->prepare($attendance_query);
$attendance_stmt->bind_param('i', $user_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
printDebugInfo("Attendance Records", $attendance_result);

// 3. Count Weekly Offs Worked
$weekly_offs_query = "
    SELECT COUNT(*) as weekly_offs_worked
    FROM attendance a
    JOIN user_shifts us ON a.user_id = us.user_id
        AND a.date >= us.effective_from
        AND (us.effective_to IS NULL OR a.date <= us.effective_to)
    WHERE a.user_id = ?
    AND a.status = 'present'
    AND DAYNAME(a.date) = us.weekly_offs
    AND YEAR(a.date) = YEAR(CURRENT_DATE())";

$weekly_offs_stmt = $conn->prepare($weekly_offs_query);
$weekly_offs_stmt->bind_param('i', $user_id);
$weekly_offs_stmt->execute();
$weekly_offs_result = $weekly_offs_stmt->get_result()->fetch_assoc();
printDebugInfo("Weekly Offs Worked Count", $weekly_offs_result);

// 4. Check Used Compensate Leaves
$used_leaves_query = "
    SELECT 
        COALESCE(SUM(lr.duration), 0) as used_compensate_leaves
    FROM leave_request lr
    JOIN leave_types lt ON lr.leave_type = lt.id
    WHERE lr.user_id = ?
    AND lt.name = 'Compensate Leave'
    AND lr.status = 'approved'
    AND lr.manager_approval = 'approved'
    AND lr.hr_approval = 'approved'
    AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())";

$used_leaves_stmt = $conn->prepare($used_leaves_query);
$used_leaves_stmt->bind_param('i', $user_id);
$used_leaves_stmt->execute();
$used_leaves_result = $used_leaves_stmt->get_result()->fetch_assoc();
printDebugInfo("Used Compensate Leaves", $used_leaves_result);

// 5. Final Balance Calculation
$final_balance = $weekly_offs_result['weekly_offs_worked'] - $used_leaves_result['used_compensate_leaves'];
printDebugInfo("Final Compensate Leave Balance", [
    'weekly_offs_worked' => $weekly_offs_result['weekly_offs_worked'],
    'used_leaves' => $used_leaves_result['used_compensate_leaves'],
    'available_balance' => $final_balance
]);

// 6. Verify Leave Type Configuration
$leave_type_query = "
    SELECT *
    FROM leave_types
    WHERE name = 'Compensate Leave'
    AND status = 'active'";

$leave_type_result = $conn->query($leave_type_query)->fetch_assoc();
printDebugInfo("Compensate Leave Type Configuration", $leave_type_result);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Compensate Leave Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        h3 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }
        pre {
            background: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <h1>Compensate Leave Test Results</h1>
    <!-- Results will be printed above -->
</body>
</html> 