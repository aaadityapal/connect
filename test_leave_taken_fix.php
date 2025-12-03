<?php
require_once 'config/db_connect.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $userId = 21;
    $month = 11;
    $year = 2025;
    
    // Calculate month boundaries
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
    
    echo "Testing leave_taken calculation for User 21 (November 2025)<br><br>";
    echo "Month boundaries: $firstDayOfMonth to $lastDayOfMonth<br><br>";
    
    // Query to get all approved leaves for this user in November
    $checkStmt = $pdo->prepare("
        SELECT id, start_date, end_date, status
        FROM leave_request
        WHERE user_id = ?
        AND status = 'approved'
        AND (
            (MONTH(start_date) = ? AND YEAR(start_date) = ?) OR
            (MONTH(end_date) = ? AND YEAR(end_date) = ?) OR
            (start_date < ? AND end_date > ?)
        )
    ");
    
    $checkStmt->execute([
        $userId,
        $month, $year,
        $month, $year,
        $firstDayOfMonth, $lastDayOfMonth
    ]);
    
    $leaves = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Approved leaves found:<br>";
    if (count($leaves) > 0) {
        foreach ($leaves as $leave) {
            echo "- ID: {$leave['id']}, From: {$leave['start_date']} To: {$leave['end_date']}<br>";
        }
    } else {
        echo "No approved leaves found<br>";
    }
    
    echo "<br>";
    
    // Now test the fixed query
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
        $userId,
        $month, $year,
        $month, $year,
        $firstDayOfMonth, $lastDayOfMonth
    ]);
    
    $leaveResult = $leaveStmt->fetch(PDO::FETCH_ASSOC);
    $leaveTaken = $leaveResult['total_leave_days'] ?? 0;
    
    echo "<strong>FIXED QUERY RESULT:</strong><br>";
    echo "Leave Taken: <strong>" . $leaveTaken . " days</strong><br><br>";
    
    if ($leaveTaken > 0) {
        echo "<span style='color: green;'><strong>✓ SUCCESS! Leave is now being counted correctly.</strong></span>";
    } else {
        echo "<span style='color: red;'><strong>✗ FAILED! Leave is still not being counted.</strong></span>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
