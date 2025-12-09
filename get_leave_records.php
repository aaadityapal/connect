<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Get parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;

if (!$user_id || !$month || !$year) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid month or year']);
    exit;
}

try {
    // Calculate month boundaries
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
    
    // Format month/year for display
    $monthYear = date('F Y', strtotime($firstDayOfMonth));
    
    // Fetch user's current shift times to determine morning/evening short leave
    $shiftStmt = $pdo->prepare("
        SELECT s.start_time, s.end_time
        FROM user_shifts us
        INNER JOIN shifts s ON us.shift_id = s.id
        WHERE us.user_id = ?
        AND (us.effective_from IS NULL OR us.effective_from <= ?)
        AND (us.effective_to IS NULL OR us.effective_to >= ?)
        ORDER BY us.effective_from DESC
        LIMIT 1
    ");
    
    $shiftStmt->execute([$user_id, $lastDayOfMonth, $firstDayOfMonth]);
    $shiftData = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    
    $shiftStartTime = $shiftData ? $shiftData['start_time'] : '09:00:00';
    $shiftEndTime = $shiftData ? $shiftData['end_time'] : '18:00:00';
    
    // Fetch approved leave records for the user in the specified month with leave type name
    $leaveStmt = $pdo->prepare("
        SELECT 
            lr.id,
            lr.start_date,
            lr.end_date,
            lr.leave_type,
            lr.reason,
            lr.time_from,
            lr.time_to,
            DATEDIFF(lr.end_date, lr.start_date) + 1 as num_days,
            lr.status,
            lt.name as leave_type_name
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = ?
        AND lr.status = 'approved'
        AND (
            (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?) OR
            (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?) OR
            (lr.start_date <= ? AND lr.end_date >= ?)
        )
        ORDER BY lr.start_date ASC
    ");
    
    $leaveStmt->execute([
        $user_id,
        $month, $year,
        $month, $year,
        $lastDayOfMonth, $firstDayOfMonth
    ]);
    
    $records = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the records for display
    $formattedRecords = [];
    foreach ($records as $record) {
        $startDate = new DateTime($record['start_date']);
        $endDate = new DateTime($record['end_date']);
        
        // Determine short leave type (morning or evening) if it's a short leave
        $shortLeaveType = '';
        if (stripos($record['leave_type_name'], 'short') !== false && $record['time_from'] && $record['time_to']) {
            // Create DateTime objects for time comparison with a common reference date
            $referenceDate = '2000-01-01'; // arbitrary date for time comparison
            $timeFrom = new DateTime($referenceDate . ' ' . $record['time_from']);
            $timeTo = new DateTime($referenceDate . ' ' . $record['time_to']);
            $shiftStart = new DateTime($referenceDate . ' ' . $shiftStartTime);
            $shiftEnd = new DateTime($referenceDate . ' ' . $shiftEndTime);
            
            // Calculate 1.5 hours from shift start and end
            $morningLimit = clone $shiftStart;
            $morningLimit->add(new DateInterval('PT1H30M')); // Add 1.5 hours
            
            $eveningLimit = clone $shiftEnd;
            $eveningLimit->sub(new DateInterval('PT1H30M')); // Subtract 1.5 hours
            
            // Check if leave is morning short leave (time_from within shift_start to shift_start + 1.5 hrs)
            if ($timeFrom >= $shiftStart && $timeFrom <= $morningLimit) {
                $shortLeaveType = ' (Morning)';
            }
            // Check if leave is evening short leave (time_to within shift_end - 1.5 hrs to shift_end)
            elseif ($timeTo >= $eveningLimit && $timeTo <= $shiftEnd) {
                $shortLeaveType = ' (Evening)';
            }
        }
        
        $leaveTypeDisplay = ($record['leave_type_name'] ?? 'N/A') . $shortLeaveType;
        
        $formattedRecords[] = [
            'start_date' => $record['start_date'],
            'end_date' => $record['end_date'],
            'start_date_display' => $startDate->format('d M Y'),
            'end_date_display' => $endDate->format('d M Y'),
            'date_range' => $startDate->format('d M Y') . ' to ' . $endDate->format('d M Y'),
            'leave_type' => $leaveTypeDisplay,
            'num_days' => intval($record['num_days']),
            'reason' => $record['reason'] ?? 'No reason provided',
            'status' => $record['status'],
            'short_leave_type' => $shortLeaveType
        ];
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'records' => $formattedRecords,
        'monthYear' => $monthYear,
        'total_leave_days' => count($formattedRecords) > 0 ? array_sum(array_column($formattedRecords, 'num_days')) : 0
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_leave_records.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_leave_records.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
    exit;
}
?>
