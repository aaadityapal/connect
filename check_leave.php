<?php
$pdo = new PDO('mysql:host=localhost;dbname=hrms', 'root', '');

// Find Aditya Kumar Pal
$userStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(name) LIKE ? LIMIT 1');
$userStmt->execute(['%aditya%']);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $userId = $user['id'];
    echo "Checking leaves for Aditya Kumar Pal (ID: $userId) on 2025-11-28:\n\n";
    
    // Get the short leave on 28-Nov-2025
    $stmt = $pdo->prepare('
        SELECT lr.id, lr.start_date, lr.time_from, lr.time_to, 
               s.start_time, s.end_time
        FROM leave_request lr
        INNER JOIN user_shifts us ON us.user_id = lr.user_id
        INNER JOIN shifts s ON s.id = us.shift_id
        WHERE lr.user_id = ?
        AND lr.status = "approved"
        AND DATE(lr.start_date) = "2025-11-28"
        AND (us.effective_from IS NULL OR us.effective_from <= lr.start_date)
        AND (us.effective_to IS NULL OR us.effective_to >= lr.start_date)
    ');
    $stmt->execute([$userId]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($leaves as $leave) {
        echo "Leave ID: {$leave['id']}\n";
        echo "Date: {$leave['start_date']}\n";
        echo "Leave Time: {$leave['time_from']} to {$leave['time_to']}\n";
        echo "Shift Time: {$leave['start_time']} to {$leave['end_time']}\n\n";
        
        $timeFrom = $leave['time_from'];
        $shiftStart = $leave['start_time'];
        
        $referenceDate = '2000-01-01';
        $timeFromDT = new DateTime($referenceDate . ' ' . $timeFrom);
        $shiftStartDT = new DateTime($referenceDate . ' ' . $shiftStart);
        $shiftStart1_5HoursDT = new DateTime($referenceDate . ' ' . $shiftStart);
        $shiftStart1_5HoursDT->add(new DateInterval('PT1H30M'));
        
        echo "Classification:\n";
        echo "  time_from: " . $timeFromDT->format('H:i:s') . "\n";
        echo "  shift_start: " . $shiftStartDT->format('H:i:s') . "\n";
        echo "  shift_start + 1.5h: " . $shiftStart1_5HoursDT->format('H:i:s') . "\n";
        
        $isMorning = ($timeFromDT >= $shiftStartDT && $timeFromDT <= $shiftStart1_5HoursDT);
        $type = $isMorning ? "MORNING" : "EVENING";
        echo "  Result: $type SHORT LEAVE\n\n";
        
        if (!$isMorning) {
            echo "✓ This is an EVENING short leave - should NOT reduce late days\n";
        }
    }
}
?>
