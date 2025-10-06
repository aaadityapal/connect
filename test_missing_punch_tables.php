<?php
/**
 * Test script to verify missing punch tables structure
 */

// Include database connection
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

try {
    global $conn;
    
    // Test if missing_punch_in table exists
    $result1 = $conn->query("SHOW TABLES LIKE 'missing_punch_in'");
    $table1Exists = $result1 && $result1->num_rows > 0;
    
    // Test if missing_punch_out table exists
    $result2 = $conn->query("SHOW TABLES LIKE 'missing_punch_out'");
    $table2Exists = $result2 && $result2->num_rows > 0;
    
    // Get structure of missing_punch_in table
    $structure1 = [];
    if ($table1Exists) {
        $result = $conn->query("DESCRIBE missing_punch_in");
        while ($row = $result->fetch_assoc()) {
            $structure1[] = $row;
        }
    }
    
    // Get structure of missing_punch_out table
    $structure2 = [];
    if ($table2Exists) {
        $result = $conn->query("DESCRIBE missing_punch_out");
        while ($row = $result->fetch_assoc()) {
            $structure2[] = $row;
        }
    }
    
    // Check if required columns exist in attendance table
    $attendanceColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM attendance WHERE Field IN ('missing_punch_in_id', 'missing_punch_out_id', 'missing_punch_reason', 'missing_punch_out_reason', 'missing_punch_approval_status')");
    while ($row = $result->fetch_assoc()) {
        $attendanceColumns[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'missing_punch_in_table_exists' => $table1Exists,
        'missing_punch_out_table_exists' => $table2Exists,
        'missing_punch_in_structure' => $structure1,
        'missing_punch_out_structure' => $structure2,
        'attendance_columns' => $attendanceColumns
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>