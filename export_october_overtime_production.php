<?php
/**
 * Export October Overtime Data - Production Version
 * 
 * This script exports all October overtime data from the attendance, 
 * overtime_notifications, and overtime_payments tables to the overtime_requests table.
 * 
 * FOR PRODUCTION USE - Includes proper error handling, logging, and safety checks.
 */

// Include database connection
require_once 'config/db_connect.php';

// Set error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file for production
$log_file = 'export_october_overtime.log';
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("Starting October overtime data export process");

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
                op.amount as paid_amount,
                u.role as user_role
              FROM attendance a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN user_shifts us ON a.user_id = us.user_id 
                AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              LEFT JOIN overtime_notifications otn ON a.id = otn.overtime_id
              LEFT JOIN overtime_payments op ON otn.id = op.overtime_id
              WHERE MONTH(a.date) = 10 
              AND a.punch_out IS NOT NULL
              AND a.overtime_hours IS NOT NULL
              AND a.overtime_hours > '00:00:00'
              ORDER BY a.date, a.user_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    log_message("Found " . count($attendance_records) . " attendance records with overtime for October");
    
    $inserted_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $error_count = 0;
    
    foreach ($attendance_records as $record) {
        try {
            // Skip records with no overtime hours
            if (empty($record['overtime_hours']) || $record['overtime_hours'] <= '00:00:00') {
                $skipped_count++;
                log_message("Skipped attendance ID " . $record['attendance_id'] . " - no overtime hours");
                continue;
            }
            
            // Convert overtime_hours from TIME to DECIMAL(5,2)
            // TIME format is 'HH:MM:SS', we need to convert to decimal hours
            $overtime_hours_time = $record['overtime_hours'];
            $overtime_hours_decimal = 0;
            
            // Handle TIME format conversion more robustly
            if (preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $overtime_hours_time, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
                $seconds = (int)$matches[3];
                $overtime_hours_decimal = $hours + ($minutes / 60) + ($seconds / 3600);
                
                // Round to 2 decimal places
                $overtime_hours_decimal = round($overtime_hours_decimal, 2);
            } else {
                // Try to handle other formats
                $overtime_hours_parts = explode(':', $overtime_hours_time);
                if (count($overtime_hours_parts) >= 2) {
                    $hours = (int)$overtime_hours_parts[0];
                    $minutes = (int)$overtime_hours_parts[1];
                    $overtime_hours_decimal = $hours + ($minutes / 60);
                    
                    // Round to 2 decimal places
                    $overtime_hours_decimal = round($overtime_hours_decimal, 2);
                }
            }
            
            // If the converted value is 0 or less, skip
            if ($overtime_hours_decimal <= 0) {
                $skipped_count++;
                log_message("Skipped attendance ID " . $record['attendance_id'] . " - overtime hours converted to 0 or less (" . $overtime_hours_time . " -> " . $overtime_hours_decimal . ")");
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
                    $overtime_hours_decimal,
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
                    log_message("Updated existing record for attendance ID " . $record['attendance_id'] . " (Role: " . $record['user_role'] . ", Hours: " . $overtime_hours_decimal . ")");
                } else {
                    $error_count++;
                    log_message("Error updating record for attendance ID " . $record['attendance_id'] . " (Role: " . $record['user_role'] . ")");
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
                    $overtime_hours_decimal,
                    $record['work_report'],
                    $overtime_description,
                    $record['overtime_approved_by'],
                    $status,
                    $record['overtime_actioned_at'],
                    $record['manager_response']
                ]);
                
                if ($result) {
                    $inserted_count++;
                    log_message("Inserted new record for attendance ID " . $record['attendance_id'] . " (Role: " . $record['user_role'] . ", Hours: " . $overtime_hours_decimal . ")");
                } else {
                    $error_count++;
                    log_message("Error inserting record for attendance ID " . $record['attendance_id'] . " (Role: " . $record['user_role'] . ")");
                }
            }
        } catch (Exception $e) {
            $error_count++;
            log_message("Exception processing attendance ID " . $record['attendance_id'] . " (Role: " . $record['user_role'] . "): " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    log_message("Export completed successfully");
    log_message("Summary: Inserted: $inserted_count, Updated: $updated_count, Skipped: $skipped_count, Errors: $error_count");
    
    echo json_encode([
        'success' => true,
        'message' => 'Export completed successfully',
        'summary' => [
            'inserted' => $inserted_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'errors' => $error_count
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    
    log_message("Export failed with error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Export failed: ' . $e->getMessage()
    ]);
}
?>