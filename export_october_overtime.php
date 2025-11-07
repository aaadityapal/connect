<?php
/**
 * Export October Overtime Data
 * 
 * This script exports all October overtime data from the attendance, 
 * overtime_notifications, and overtime_payments tables to the overtime_requests table.
 */

// Include database connection
require_once 'config/db_connect.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Export October Overtime Data to Overtime Requests Table</h1>\n";

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get all attendance records for October with overtime
    $query = "SELECT 
                a.id as attendance_id,
                a.user_id,
                a.date,
                a.punch_in,
                a.punch_out,
                a.overtime_hours,
                a.work_report,
                a.overtime_status,
                a.overtime_approved_by,
                a.overtime_actioned_at,
                s.end_time as shift_end_time,
                otn.message as overtime_message,
                otn.manager_response,
                op.hours as paid_hours,
                op.amount as paid_amount
              FROM attendance a
              LEFT JOIN user_shifts us ON a.user_id = us.user_id 
                AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              LEFT JOIN overtime_notifications otn ON a.id = otn.overtime_id
              LEFT JOIN overtime_payments op ON otn.id = op.overtime_id
              WHERE MONTH(a.date) = 10 
              AND a.punch_out IS NOT NULL
              AND a.overtime_hours IS NOT NULL
              AND a.overtime_hours > 0
              ORDER BY a.date, a.user_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($attendance_records) . " attendance records with overtime for October.</p>\n";
    
    $inserted_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    
    foreach ($attendance_records as $record) {
        // Skip records with no overtime hours
        if (empty($record['overtime_hours']) || $record['overtime_hours'] <= 0) {
            $skipped_count++;
            continue;
        }
        
        // Determine status for overtime_requests table
        $status = 'pending';
        if (!empty($record['overtime_status'])) {
            $status = $record['overtime_status'];
        }
        
        // Use overtime message as description if available, otherwise create a default one
        $overtime_description = !empty($record['overtime_message']) ? 
            $record['overtime_message'] : 
            'Overtime work performed on ' . $record['date'];
        
        // If no shift end time, use default
        $shift_end_time = !empty($record['shift_end_time']) ? 
            $record['shift_end_time'] : '18:00:00';
        
        // Check if a record already exists for this attendance
        $check_query = "SELECT id FROM overtime_requests WHERE attendance_id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$record['attendance_id']]);
        $existing_record = $check_stmt->fetch();
        
        if ($existing_record) {
            // Update existing record
            $update_query = "UPDATE overtime_requests SET 
                                user_id = ?,
                                date = ?,
                                shift_end_time = ?,
                                punch_out_time = ?,
                                overtime_hours = ?,
                                work_report = ?,
                                overtime_description = ?,
                                manager_id = ?,
                                status = ?,
                                actioned_at = ?,
                                manager_comments = ?,
                                updated_at = NOW()
                             WHERE attendance_id = ?";
            
            $update_stmt = $pdo->prepare($update_query);
            $result = $update_stmt->execute([
                $record['user_id'],
                $record['date'],
                $shift_end_time,
                $record['punch_out'],
                $record['overtime_hours'],
                $record['work_report'],
                $overtime_description,
                $record['overtime_approved_by'],
                $status,
                $record['overtime_actioned_at'],
                $record['manager_response'],
                $record['attendance_id']
            ]);
            
            if ($result) {
                $updated_count++;
            } else {
                echo "<p style='color: red;'>Error updating record for attendance ID " . $record['attendance_id'] . "</p>\n";
            }
        } else {
            // Insert new record
            $insert_query = "INSERT INTO overtime_requests (
                                user_id,
                                attendance_id,
                                date,
                                shift_end_time,
                                punch_out_time,
                                overtime_hours,
                                work_report,
                                overtime_description,
                                manager_id,
                                status,
                                submitted_at,
                                actioned_at,
                                manager_comments
                             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            
            $insert_stmt = $pdo->prepare($insert_query);
            $result = $insert_stmt->execute([
                $record['user_id'],
                $record['attendance_id'],
                $record['date'],
                $shift_end_time,
                $record['punch_out'],
                $record['overtime_hours'],
                $record['work_report'],
                $overtime_description,
                $record['overtime_approved_by'],
                $status,
                $record['overtime_actioned_at'],
                $record['manager_response']
            ]);
            
            if ($result) {
                $inserted_count++;
            } else {
                echo "<p style='color: red;'>Error inserting record for attendance ID " . $record['attendance_id'] . "</p>\n";
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "<h2>Export Summary</h2>\n";
    echo "<ul>\n";
    echo "<li>Inserted: $inserted_count records</li>\n";
    echo "<li>Updated: $updated_count records</li>\n";
    echo "<li>Skipped: $skipped_count records (no overtime or less than or equal to 0 hours)</li>\n";
    echo "</ul>\n";
    echo "<p style='color: green;'><strong>Export completed successfully!</strong></p>\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<p><a href='overtime_dashboard.php'>Back to Overtime Dashboard</a></p>\n";
?>