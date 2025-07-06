<?php
/**
 * Fix Overtime Status Script
 * 
 * This script fixes any incorrect status values in the attendance table
 * and ensures that the overtime_notifications table is in sync.
 */

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Set up error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Overtime Status Fix Utility</h1>";
echo "<p>Starting status fix process...</p>";

try {
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    // 1. Check for records with overtime_status = 'submitted' that have already been approved/rejected
    $checkQuery = "SELECT a.id, a.user_id, a.date, a.overtime_status, a.overtime_approved_by, 
                         n.status as notification_status, n.id as notification_id
                  FROM attendance a
                  LEFT JOIN overtime_notifications n ON a.id = n.overtime_id
                  WHERE a.overtime_status = 'submitted' 
                  AND n.status IN ('approved', 'rejected')";
    
    $checkResult = mysqli_query($conn, $checkQuery);
    $mismatchCount = mysqli_num_rows($checkResult);
    
    echo "<p>Found $mismatchCount records with status mismatches between attendance and notifications tables.</p>";
    
    if ($mismatchCount > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Date</th><th>Attendance Status</th><th>Notification Status</th><th>Action</th></tr>";
        
        while ($row = mysqli_fetch_assoc($checkResult)) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['date']}</td>";
            echo "<td>{$row['overtime_status']}</td>";
            echo "<td>{$row['notification_status']}</td>";
            
            // Update attendance status to match notification status
            $updateQuery = "UPDATE attendance 
                           SET overtime_status = '{$row['notification_status']}' 
                           WHERE id = {$row['id']}";
            
            if (mysqli_query($conn, $updateQuery)) {
                echo "<td style='color: green;'>Fixed: Updated to {$row['notification_status']}</td>";
            } else {
                echo "<td style='color: red;'>Error: " . mysqli_error($conn) . "</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // 2. Check for records with overtime_status = NULL or empty
    $nullCheckQuery = "SELECT id, user_id, date, overtime_status 
                      FROM attendance 
                      WHERE overtime_status IS NULL OR overtime_status = ''";
    
    $nullCheckResult = mysqli_query($conn, $nullCheckQuery);
    $nullCount = mysqli_num_rows($nullCheckResult);
    
    echo "<p>Found $nullCount records with NULL or empty overtime status.</p>";
    
    if ($nullCount > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Date</th><th>Status</th><th>Action</th></tr>";
        
        while ($row = mysqli_fetch_assoc($nullCheckResult)) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['date']}</td>";
            echo "<td>NULL/Empty</td>";
            
            // Set status to 'pending' for NULL/empty values
            $updateQuery = "UPDATE attendance 
                           SET overtime_status = 'pending' 
                           WHERE id = {$row['id']}";
            
            if (mysqli_query($conn, $updateQuery)) {
                echo "<td style='color: green;'>Fixed: Set to 'pending'</td>";
            } else {
                echo "<td style='color: red;'>Error: " . mysqli_error($conn) . "</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // 3. Check for inconsistencies between attendance and notifications tables
    $inconsistencyQuery = "SELECT a.id, a.user_id, a.date, a.overtime_status, 
                                n.id as notification_id, n.status as notification_status
                          FROM attendance a
                          JOIN overtime_notifications n ON a.id = n.overtime_id
                          WHERE a.overtime_status != n.status
                          AND a.overtime_status IN ('approved', 'rejected')
                          AND n.status IN ('approved', 'rejected')";
    
    $inconsistencyResult = mysqli_query($conn, $inconsistencyQuery);
    $inconsistencyCount = mysqli_num_rows($inconsistencyResult);
    
    echo "<p>Found $inconsistencyCount records with inconsistent statuses between tables.</p>";
    
    if ($inconsistencyCount > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Date</th><th>Attendance Status</th><th>Notification Status</th><th>Action</th></tr>";
        
        while ($row = mysqli_fetch_assoc($inconsistencyResult)) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['date']}</td>";
            echo "<td>{$row['overtime_status']}</td>";
            echo "<td>{$row['notification_status']}</td>";
            
            // Use the most recent status (assume notification is more recent)
            $updateQuery = "UPDATE attendance 
                           SET overtime_status = '{$row['notification_status']}' 
                           WHERE id = {$row['id']}";
            
            if (mysqli_query($conn, $updateQuery)) {
                echo "<td style='color: green;'>Fixed: Updated attendance to {$row['notification_status']}</td>";
            } else {
                echo "<td style='color: red;'>Error: " . mysqli_error($conn) . "</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo "<p style='color: green; font-weight: bold;'>Status fix completed successfully!</p>";
    
    // Provide instructions for next steps
    echo "<h2>Next Steps</h2>";
    echo "<p>The overtime status values have been fixed. To complete the process:</p>";
    echo "<ol>";
    echo "<li>Check the logs for any errors</li>";
    echo "<li>Test the overtime approval system to ensure it's working correctly</li>";
    echo "<li>Make sure both approval and rejection are working as expected</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    echo "<p style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</p>";
} finally {
    // Close connection
    mysqli_close($conn);
}
?> 