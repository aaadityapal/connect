<?php
// Test script to verify evening short leaves don't reduce late days

$pdo = new PDO('mysql:host=localhost;dbname=hrms', 'root', '');

// Find Aditya Kumar Pal
$userStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(name) LIKE ? LIMIT 1');
$userStmt->execute(['%aditya%']);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $userId = $user['id'];
    echo "<h3>Aditya Kumar Pal (ID: $userId) - November 2025 Short Leaves</h3>";
    
    // Check short leaves in November 2025
    $shortStmt = $pdo->prepare('
        SELECT lr.id, lr.start_date, lr.time_from, lr.time_to, 
               s.start_time, s.end_time
        FROM leave_request lr
        INNER JOIN user_shifts us ON us.user_id = lr.user_id
        INNER JOIN shifts s ON s.id = us.shift_id
        WHERE lr.user_id = ?
        AND lr.status = "approved"
        AND MONTH(lr.start_date) = 11
        AND YEAR(lr.start_date) = 2025
        AND lr.leave_type IN (
            SELECT id FROM leave_types 
            WHERE LOWER(name) LIKE "%short%" OR LOWER(name) LIKE "%half%"
        )
        AND (us.effective_from IS NULL OR us.effective_from <= lr.start_date)
        AND (us.effective_to IS NULL OR us.effective_to >= lr.start_date)
    ');
    $shortStmt->execute([$userId]);
    $shortLeaves = $shortStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($shortLeaves)) {
        echo "<p>No short leaves found in November 2025.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Date</th><th>Time</th><th>Shift</th><th>Classification</th></tr>";
        
        foreach ($shortLeaves as $leave) {
            $timeFrom = $leave['time_from'];
            $shiftStart = $leave['start_time'];
            
            $referenceDate = '2000-01-01';
            $timeFromDT = new DateTime($referenceDate . ' ' . $timeFrom);
            $shiftStartDT = new DateTime($referenceDate . ' ' . $shiftStart);
            $shiftStart1_5HoursDT = new DateTime($referenceDate . ' ' . $shiftStart);
            $shiftStart1_5HoursDT->add(new DateInterval('PT1H30M'));
            
            $isMorning = ($timeFromDT >= $shiftStartDT && $timeFromDT <= $shiftStart1_5HoursDT);
            $type = $isMorning ? '<span style="color:green;font-weight:bold;">MORNING</span>' : '<span style="color:red;font-weight:bold;">EVENING</span>';
            
            echo "<tr>";
            echo "<td>{$leave['id']}</td>";
            echo "<td>{$leave['start_date']}</td>";
            echo "<td>{$leave['time_from']} - {$leave['time_to']}</td>";
            echo "<td>{$leave['start_time']} - {$leave['end_time']}</td>";
            echo "<td>$type</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Now check late days
    echo "<h3>Late Days in November 2025 (with updated logic)</h3>";
    
    // Get user's current shift
    $shiftStmt = $pdo->prepare("
        SELECT s.start_time, s.end_time
        FROM user_shifts us
        INNER JOIN shifts s ON s.id = us.shift_id
        WHERE us.user_id = ?
        AND (us.effective_from IS NULL AND us.effective_to IS NULL) 
        OR (us.effective_from <= CURDATE() AND (us.effective_to IS NULL OR us.effective_to >= CURDATE()))
        ORDER BY us.effective_from DESC
        LIMIT 1
    ");
    $shiftStmt->execute([$userId]);
    $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userShift) {
        $shiftStartTime = $userShift['start_time'];
        $graceTime = date('H:i:s', strtotime($shiftStartTime . ' +15 minutes'));
        
        // Fetch ONLY MORNING short leave dates
        $shortLeaveDates = [];
        $shortLeavesStmt = $pdo->prepare("
            SELECT DISTINCT DATE(lr.start_date) as leave_date, 
                   lr.time_from, lr.time_to,
                   s.start_time, s.end_time
            FROM leave_request lr
            INNER JOIN user_shifts us ON us.user_id = lr.user_id
            INNER JOIN shifts s ON s.id = us.shift_id
            WHERE lr.user_id = ?
            AND lr.status = 'approved'
            AND MONTH(lr.start_date) = 11
            AND YEAR(lr.start_date) = 2025
            AND lr.leave_type IN (
                SELECT id FROM leave_types 
                WHERE LOWER(name) LIKE '%short%' OR LOWER(name) LIKE '%half%'
            )
            AND (us.effective_from IS NULL OR us.effective_from <= lr.start_date)
            AND (us.effective_to IS NULL OR us.effective_to >= lr.start_date)
        ");
        $shortLeavesStmt->execute([$userId]);
        $shortLeaves = $shortLeavesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($shortLeaves as $shortLeave) {
            $timeFrom = $shortLeave['time_from'];
            $shiftStart = $shortLeave['start_time'];
            
            $referenceDate = '2000-01-01';
            $timeFromDT = new DateTime($referenceDate . ' ' . $timeFrom);
            $shiftStartDT = new DateTime($referenceDate . ' ' . $shiftStart);
            $shiftStart1_5HoursDT = new DateTime($referenceDate . ' ' . $shiftStart);
            $shiftStart1_5HoursDT->add(new DateInterval('PT1H30M'));
            
            if ($timeFromDT >= $shiftStartDT && $timeFromDT <= $shiftStart1_5HoursDT) {
                $leaveDate = $shortLeave['leave_date'];
                $shortLeaveDates[$leaveDate] = true;
            }
        }
        
        echo "<p>Morning short leave dates excluded from late calculation: " . implode(", ", array_keys($shortLeaveDates)) . "</p>";
        
        // Get late days (excluding morning short leaves only)
        $lateDaysStmt = $pdo->prepare("
            SELECT DATE(date) as late_date, punch_in
            FROM attendance
            WHERE user_id = ?
            AND MONTH(date) = 11
            AND YEAR(date) = 2025
            AND punch_in IS NOT NULL
            AND punch_in != ''
            AND TIME(punch_in) > ?
        ");
        $lateDaysStmt->execute([$userId, $graceTime]);
        $lateDaysResults = $lateDaysStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $lateDays = 0;
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Date</th><th>Punch In</th><th>Excluded?</th></tr>";
        
        foreach ($lateDaysResults as $lateDate) {
            $date = $lateDate['late_date'];
            $excluded = isset($shortLeaveDates[$date]) ? 'Yes (Morning Short Leave)' : 'No';
            if (!isset($shortLeaveDates[$date])) {
                $lateDays++;
            }
            echo "<tr><td>$date</td><td>{$lateDate['punch_in']}</td><td>$excluded</td></tr>";
        }
        echo "</table>";
        echo "<p><strong>Total Late Days (after filtering): $lateDays</strong></p>";
    }
} else {
    echo "User not found.";
}
?>
