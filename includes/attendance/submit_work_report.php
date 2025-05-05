<?php
// Include database connection
require_once '../../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get form data
$attendanceId = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;
$workReport = isset($_POST['work_report']) ? trim($_POST['work_report']) : '';
$overtime = isset($_POST['overtime']) && $_POST['overtime'] === '1' ? 1 : 0;
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
$isPunchingOut = isset($_POST['is_punching_out']) && $_POST['is_punching_out'] === '1';

// Validate inputs
if (!$attendanceId) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid attendance ID'
    ]);
    exit;
}

if (empty($workReport)) {
    echo json_encode([
        'success' => false,
        'message' => 'Work report is required'
    ]);
    exit;
}

try {
    // Check if attendance record exists and belongs to the user
    $checkStmt = $pdo->prepare("SELECT * FROM attendance WHERE id = ? AND user_id = ?");
    $checkStmt->execute([$attendanceId, $_SESSION['user_id']]);
    $attendance = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendance) {
        echo json_encode([
            'success' => false,
            'message' => 'Attendance record not found'
        ]);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update the attendance record with work report
    $updateStmt = $pdo->prepare("UPDATE attendance 
                                SET work_report = ?, 
                                    overtime = ?, 
                                    remarks = ?,
                                    modified_at = NOW(),
                                    modified_by = ?
                                WHERE id = ? AND user_id = ?");
    
    $updateStmt->execute([
        $workReport,
        $overtime,
        $remarks,
        $_SESSION['user_id'],
        $attendanceId,
        $_SESSION['user_id']
    ]);
    
    // If this is part of the punch out process, also update punch out time
    if ($isPunchingOut) {
        $currentDateTime = date('Y-m-d H:i:s');
        
        // Calculate working hours
        $punchIn = new DateTime($attendance['punch_in']);
        $punchOut = new DateTime($currentDateTime);
        $interval = $punchIn->diff($punchOut);
        
        $workingHours = $interval->h + ($interval->days * 24);
        $workingMinutes = $interval->i;
        $totalMinutes = ($workingHours * 60) + $workingMinutes;
        $decimalHours = round($totalMinutes / 60, 2);
        
        // Update the punch out time
        $punchOutStmt = $pdo->prepare("UPDATE attendance 
                                     SET punch_out = ?, 
                                         working_hours = ?
                                     WHERE id = ? AND user_id = ?");
        
        $punchOutStmt->execute([
            $currentDateTime,
            $decimalHours,
            $attendanceId,
            $_SESSION['user_id']
        ]);
        
        // Calculate overtime hours if overtime is checked
        if ($overtime) {
            // Assume standard working hours is 8 hours
            $standardHours = 8.0;
            
            if ($decimalHours > $standardHours) {
                $overtimeHours = $decimalHours - $standardHours;
                
                $overtimeStmt = $pdo->prepare("UPDATE attendance 
                                              SET overtime_hours = ? 
                                              WHERE id = ?");
                $overtimeStmt->execute([$overtimeHours, $attendanceId]);
            }
        }
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Prepare the success message
    if ($isPunchingOut) {
        $message = 'Work report submitted and punched out successfully.';
    } else {
        $message = 'Work report submitted successfully.';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    // Rollback the transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    
    // Log the error
    error_log('Submit Work Report Error: ' . $e->getMessage());
} 