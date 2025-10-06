<?php
/**
 * Test script to demonstrate the approval process for missing punch requests
 */

// Include database connection
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

try {
    global $conn;
    
    // Simulate submitting a missing punch-in request (initially pending)
    echo "1. Submitting missing punch-in request (status: pending)...\n";
    
    // Insert a sample missing punch-in request
    $insertQuery = "INSERT INTO missing_punch_in (user_id, date, punch_in_time, reason, confirmed, status) VALUES (1, '2025-10-05', '09:00:00', 'Missed punch-in due to system issue', 1, 'pending')";
    $conn->query($insertQuery);
    $missingPunchId = $conn->insert_id;
    
    echo "   Missing punch-in request submitted with ID: " . $missingPunchId . "\n";
    
    // Check that attendance table is NOT updated yet
    $checkQuery = "SELECT * FROM attendance WHERE user_id = 1 AND date = '2025-10-05'";
    $result = $conn->query($checkQuery);
    $attendanceRecord = $result->fetch_assoc();
    
    echo "2. Attendance record before approval:\n";
    if ($attendanceRecord) {
        echo "   Record exists but should not have punch_in data yet\n";
        echo "   punch_in: " . ($attendanceRecord['punch_in'] ?? 'NULL') . "\n";
        echo "   missing_punch_approval_status: " . ($attendanceRecord['missing_punch_approval_status'] ?? 'NULL') . "\n";
    } else {
        echo "   No attendance record found (as expected)\n";
    }
    
    // Simulate admin approval
    echo "3. Simulating admin approval...\n";
    
    // Update the missing punch status to approved
    $updateMissingPunchQuery = "UPDATE missing_punch_in SET status = 'approved', admin_notes = 'Approved by admin' WHERE id = ?";
    $updateStmt = $conn->prepare($updateMissingPunchQuery);
    $updateStmt->bind_param("i", $missingPunchId);
    $updateStmt->execute();
    
    // Get the missing punch details
    $selectQuery = "SELECT user_id, date, punch_in_time, reason FROM missing_punch_in WHERE id = ?";
    $selectStmt = $conn->prepare($selectQuery);
    $selectStmt->bind_param("i", $missingPunchId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $missingPunch = $result->fetch_assoc();
    
    // Update or create attendance record
    $checkQuery = "SELECT id FROM attendance WHERE user_id = ? AND date = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("is", $missingPunch['user_id'], $missingPunch['date']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existingRecord = $result->fetch_assoc();
    
    if ($existingRecord) {
        // Update existing record
        $updateQuery = "UPDATE attendance SET punch_in = ?, missing_punch_reason = ?, missing_punch_in_id = ?, missing_punch_approval_status = 'approved' WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ssii", $missingPunch['punch_in_time'], $missingPunch['reason'], $missingPunchId, $existingRecord['id']);
        $updateStmt->execute();
        echo "   Updated existing attendance record\n";
    } else {
        // Create new attendance record
        $insertQuery = "INSERT INTO attendance (user_id, date, punch_in, missing_punch_reason, missing_punch_in_id, missing_punch_approval_status) VALUES (?, ?, ?, ?, ?, 'approved')";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("isssi", $missingPunch['user_id'], $missingPunch['date'], $missingPunch['punch_in_time'], $missingPunch['reason'], $missingPunchId);
        $insertStmt->execute();
        echo "   Created new attendance record\n";
    }
    
    // Check that attendance table is now updated
    $checkQuery = "SELECT * FROM attendance WHERE user_id = 1 AND date = '2025-10-05'";
    $result = $conn->query($checkQuery);
    $attendanceRecord = $result->fetch_assoc();
    
    echo "4. Attendance record after approval:\n";
    if ($attendanceRecord) {
        echo "   punch_in: " . ($attendanceRecord['punch_in'] ?? 'NULL') . "\n";
        echo "   missing_punch_approval_status: " . ($attendanceRecord['missing_punch_approval_status'] ?? 'NULL') . "\n";
        echo "   missing_punch_in_id: " . ($attendanceRecord['missing_punch_in_id'] ?? 'NULL') . "\n";
    }
    
    // Clean up test data
    echo "5. Cleaning up test data...\n";
    $conn->query("DELETE FROM missing_punch_in WHERE id = " . $missingPunchId);
    $conn->query("DELETE FROM attendance WHERE user_id = 1 AND date = '2025-10-05'");
    
    echo "Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>