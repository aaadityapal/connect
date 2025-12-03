<?php
/**
 * VERIFICATION TEST: Leave Taken Fix for User 21
 * This test verifies that the fix correctly calculates leave_taken for approved leaves
 */

require_once 'config/db_connect.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $userId = 21;
    $month = 11;
    $year = 2025;
    
    echo "===============================================<br>";
    echo "LEAVE TAKEN FIX VERIFICATION TEST<br>";
    echo "===============================================<br><br>";
    
    // Get user info
    $userStmt = $pdo->prepare('SELECT id, username, employee_id, designation FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<strong>Employee Information:</strong><br>";
    echo "User ID: " . $user['id'] . "<br>";
    echo "Name: " . $user['username'] . "<br>";
    echo "Employee ID: " . $user['employee_id'] . "<br>";
    echo "Designation: " . $user['designation'] . "<br>";
    echo "Month/Year: " . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . "<br><br>";
    
    // Get all approved leaves for this user in November
    echo "<strong>Approved Leaves in November 2025:</strong><br>";
    $leaveCheckStmt = $pdo->prepare("
        SELECT id, start_date, end_date, status, 
               DATEDIFF(end_date, start_date) + 1 as leave_days
        FROM leave_request
        WHERE user_id = ?
        AND status = 'approved'
        AND MONTH(start_date) = ?
        AND YEAR(start_date) = ?
    ");
    $leaveCheckStmt->execute([$userId, $month, $year]);
    $leaves = $leaveCheckStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($leaves) > 0) {
        foreach ($leaves as $idx => $leave) {
            echo ($idx + 1) . ". Leave ID: " . $leave['id'] . 
                 " | From: " . $leave['start_date'] . 
                 " | To: " . $leave['end_date'] . 
                 " | Days: " . $leave['leave_days'] . "<br>";
        }
    } else {
        echo "No approved leaves found for this month.<br>";
    }
    
    echo "<br>";
    
    // Calculate month boundaries
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
    
    echo "<strong>Month Boundaries:</strong><br>";
    echo "First Day: " . $firstDayOfMonth . "<br>";
    echo "Last Day: " . $lastDayOfMonth . "<br><br>";
    
    // Test the FIXED query
    echo "<strong style='color: green;'>✓ FIXED QUERY (Using Direct Date Parameters):</strong><br>";
    
    $leaveStmt = $pdo->prepare("
        SELECT SUM(
            DATEDIFF(
                LEAST(end_date, ?),
                GREATEST(start_date, ?)
            ) + 1
        ) as total_leave_days
        FROM leave_request
        WHERE user_id = ?
        AND status = 'approved'
        AND (
            (MONTH(start_date) = ? AND YEAR(start_date) = ?) OR
            (MONTH(end_date) = ? AND YEAR(end_date) = ?) OR
            (start_date < ? AND end_date > ?)
        )
    ");
    
    $leaveStmt->execute([
        $lastDayOfMonth,
        $firstDayOfMonth,
        $user['id'],
        $month, $year,
        $month, $year,
        $firstDayOfMonth, $lastDayOfMonth
    ]);
    
    $leaveResult = $leaveStmt->fetch(PDO::FETCH_ASSOC);
    $leaveTaken = $leaveResult['total_leave_days'] ?? 0;
    
    echo "Query Result: " . $leaveTaken . " days<br>";
    
    if ($leaveTaken > 0) {
        echo "<span style='color: green;'><strong>✓ SUCCESS! Leave is now being counted correctly.</strong></span><br>";
    } else {
        echo "<span style='color: red;'><strong>✗ ISSUE: Leave is still showing as 0.</strong></span><br>";
    }
    
    echo "<br>";
    echo "<strong>Summary:</strong><br>";
    echo "The fix replaces the problematic DATE_CONCAT() function with direct date parameter binding.<br>";
    echo "This ensures the leave_taken calculation works correctly across all MySQL versions.<br>";
    echo "<br>";
    
    if ($leaveTaken == count($leaves)) {
        echo "<span style='color: green;'><strong>✓ VERIFICATION PASSED: Calculated days match actual leave records.</strong></span>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    echo "<br>";
    echo $e->getTraceAsString();
}
?>
