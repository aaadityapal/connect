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
    
    // Fetch approved leave records for the user in the specified month with leave type name
    $leaveStmt = $pdo->prepare("
        SELECT 
            lr.id,
            lr.start_date,
            lr.end_date,
            lr.leave_type,
            lr.reason,
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
        
        $formattedRecords[] = [
            'start_date' => $record['start_date'],
            'end_date' => $record['end_date'],
            'start_date_display' => $startDate->format('d M Y'),
            'end_date_display' => $endDate->format('d M Y'),
            'date_range' => $startDate->format('d M Y') . ' to ' . $endDate->format('d M Y'),
            'leave_type' => $record['leave_type_name'] ?? 'N/A',
            'num_days' => intval($record['num_days']),
            'reason' => $record['reason'] ?? 'No reason provided',
            'status' => $record['status']
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
