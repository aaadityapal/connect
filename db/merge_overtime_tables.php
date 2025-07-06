<?php
/**
 * Merge Overtime Tables Script
 * 
 * This script merges data from overtime_notification (singular) into overtime_notifications (plural)
 * and then removes the singular table to prevent future issues.
 * 
 * Steps:
 * 1. Check if both tables exist
 * 2. Transfer any unique records from singular to plural
 * 3. Drop the singular table
 * 4. Update any references in code (if needed)
 */

// Include database connection
require_once '../config/db_connect.php';

// Set up error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Overtime Tables Merge Utility</h1>";
echo "<p>Starting merge process...</p>";

try {
    // 1. Check if both tables exist
    $checkSingularQuery = "SHOW TABLES LIKE 'overtime_notification'";
    $singularResult = mysqli_query($conn, $checkSingularQuery);
    $singularExists = mysqli_num_rows($singularResult) > 0;
    
    $checkPluralQuery = "SHOW TABLES LIKE 'overtime_notifications'";
    $pluralResult = mysqli_query($conn, $checkPluralQuery);
    $pluralExists = mysqli_num_rows($pluralResult) > 0;
    
    echo "<p>Table status:<br>";
    echo "- overtime_notification (singular): " . ($singularExists ? "EXISTS" : "DOES NOT EXIST") . "<br>";
    echo "- overtime_notifications (plural): " . ($pluralExists ? "EXISTS" : "DOES NOT EXIST") . "</p>";
    
    if (!$singularExists && !$pluralExists) {
        echo "<p>Error: Neither table exists. Nothing to merge.</p>";
        exit;
    }
    
    if (!$singularExists) {
        echo "<p>The singular table does not exist. No merge needed.</p>";
        exit;
    }
    
    if (!$pluralExists) {
        echo "<p>The plural table does not exist. Creating it first...</p>";
        
        // Create the plural table using the SQL from create_overtime_notifications_table.sql
        $createTableSQL = file_get_contents('../db/create_overtime_notifications_table.sql');
        if (!$createTableSQL) {
            throw new Exception("Could not read create_overtime_notifications_table.sql file");
        }
        
        // Execute the SQL to create the table
        if (!mysqli_multi_query($conn, $createTableSQL)) {
            throw new Exception("Failed to create overtime_notifications table: " . mysqli_error($conn));
        }
        
        // Clear results to allow next query
        while (mysqli_next_result($conn)) {
            if ($result = mysqli_store_result($conn)) {
                mysqli_free_result($result);
            }
        }
        
        echo "<p>Successfully created overtime_notifications table.</p>";
    }
    
    // 2. Transfer records from singular to plural
    echo "<p>Starting data transfer...</p>";
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    // Get column structure of both tables
    $singularColumnsQuery = "DESCRIBE overtime_notification";
    $singularColumnsResult = mysqli_query($conn, $singularColumnsQuery);
    $singularColumns = [];
    
    while ($column = mysqli_fetch_assoc($singularColumnsResult)) {
        $singularColumns[] = $column['Field'];
    }
    
    $pluralColumnsQuery = "DESCRIBE overtime_notifications";
    $pluralColumnsResult = mysqli_query($conn, $pluralColumnsQuery);
    $pluralColumns = [];
    
    while ($column = mysqli_fetch_assoc($pluralColumnsResult)) {
        $pluralColumns[] = $column['Field'];
    }
    
    // Find common columns
    $commonColumns = array_intersect($singularColumns, $pluralColumns);
    
    // Build column list for INSERT query
    $columnList = implode(', ', $commonColumns);
    
    // Get all records from singular table
    $getRecordsQuery = "SELECT * FROM overtime_notification";
    $recordsResult = mysqli_query($conn, $getRecordsQuery);
    $recordCount = mysqli_num_rows($recordsResult);
    
    echo "<p>Found $recordCount records in overtime_notification table.</p>";
    
    $transferredCount = 0;
    $skippedCount = 0;
    
    // Process each record
    while ($record = mysqli_fetch_assoc($recordsResult)) {
        // Check if this record already exists in plural table
        $checkExistingQuery = "SELECT id FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkExistingQuery);
        mysqli_stmt_bind_param($checkStmt, 'i', $record['overtime_id']);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) > 0) {
            // Record already exists, skip it
            $skippedCount++;
            continue;
        }
        
        // Build placeholders for values
        $placeholders = array_fill(0, count($commonColumns), '?');
        $placeholderList = implode(', ', $placeholders);
        
        // Build INSERT query
        $insertQuery = "INSERT INTO overtime_notifications ($columnList) VALUES ($placeholderList)";
        $insertStmt = mysqli_prepare($conn, $insertQuery);
        
        // Build parameter types and values
        $paramTypes = '';
        $paramValues = [];
        
        foreach ($commonColumns as $column) {
            // Determine parameter type
            if (is_int($record[$column])) {
                $paramTypes .= 'i';
            } elseif (is_float($record[$column])) {
                $paramTypes .= 'd';
            } else {
                $paramTypes .= 's';
            }
            
            $paramValues[] = $record[$column];
        }
        
        // Bind parameters dynamically
        $bindParams = array_merge([$insertStmt, $paramTypes], $paramValues);
        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        
        // Execute INSERT
        $insertResult = mysqli_stmt_execute($insertStmt);
        
        if ($insertResult) {
            $transferredCount++;
        } else {
            echo "<p>Error transferring record ID {$record['id']}: " . mysqli_error($conn) . "</p>";
        }
    }
    
    echo "<p>Transfer complete:<br>";
    echo "- Records transferred: $transferredCount<br>";
    echo "- Records skipped (already exist): $skippedCount</p>";
    
    // 3. Drop the singular table
    if ($transferredCount > 0 || $skippedCount > 0) {
        $dropQuery = "DROP TABLE overtime_notification";
        $dropResult = mysqli_query($conn, $dropQuery);
        
        if ($dropResult) {
            echo "<p>Successfully dropped overtime_notification table.</p>";
        } else {
            throw new Exception("Failed to drop overtime_notification table: " . mysqli_error($conn));
        }
    } else {
        echo "<p>No records were transferred. The singular table was not dropped.</p>";
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo "<p style='color: green; font-weight: bold;'>Merge completed successfully!</p>";
    
    // 4. Provide instructions for code updates
    echo "<h2>Next Steps</h2>";
    echo "<p>The tables have been merged successfully. To complete the process:</p>";
    echo "<ol>";
    echo "<li>Update any code references from 'overtime_notification' to 'overtime_notifications'</li>";
    echo "<li>Check for any SQL files that create the singular table and update them</li>";
    echo "<li>Test the overtime approval system to ensure it's working correctly</li>";
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